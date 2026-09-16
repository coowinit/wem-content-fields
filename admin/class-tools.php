<?php
namespace WEM\Content_Fields\Admin;

use WEM\Content_Fields\Storage;
use WEM\Content_Fields\Transfer\Exporter;
use WEM\Content_Fields\Transfer\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tools {
	private Storage $storage;
	private Exporter $exporter;
	private Importer $importer;

	public function __construct( Storage $storage, Exporter $exporter, Importer $importer ) {
		$this->storage  = $storage;
		$this->exporter = $exporter;
		$this->importer = $importer;
	}

	public function hooks(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_wem_cf_export', [ $this, 'export' ] );
		add_action( 'admin_post_wem_cf_import_preview', [ $this, 'import_preview' ] );
		add_action( 'admin_post_wem_cf_import_confirm', [ $this, 'import_confirm' ] );
	}

	private function capability(): string {
		return (string) apply_filters( 'wem_cf_manage_capability', 'manage_options' );
	}

	public function menu(): void {
		add_submenu_page(
			'wem-content-fields',
			'导入导出',
			'导入导出',
			$this->capability(),
			'wem-content-fields-tools',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		$this->guard();
		$groups  = $this->storage->all();
		$preview = null;
		$token   = sanitize_key( wp_unslash( $_GET['import_token'] ?? '' ) );

		if ( '' !== $token ) {
			$preview = get_transient( $this->transient_key( $token ) );
			if ( ! is_array( $preview ) ) {
				$preview = null;
			}
		}

		$this->render_notice();
		include WEM_CF_PATH . 'admin/views/tools.php';
	}

	public function export(): void {
		$this->guard();
		check_admin_referer( 'wem_cf_export' );

		$keys = isset( $_POST['groups'] ) && is_array( $_POST['groups'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['groups'] ) )
			: [];

		$json = $this->exporter->json( $keys );
		if ( is_wp_error( $json ) ) {
			$this->redirect( 'export_error', [ 'message' => $json->get_error_message() ] );
		}

		$filename = 'wem-content-fields-' . gmdate( 'Y-m-d' ) . '.json';
		if ( 1 === count( $keys ) ) {
			$filename = 'wem-content-fields-' . sanitize_file_name( $keys[0] ) . '-' . gmdate( 'Y-m-d' ) . '.json';
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function import_preview(): void {
		$this->guard();
		check_admin_referer( 'wem_cf_import_preview' );

		$file = $_FILES['wem_cf_import_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$this->redirect( 'import_error', [ 'message' => '请选择一个有效的 JSON 文件。' ] );
		}

		$name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
		$size = absint( $file['size'] ?? 0 );
		$tmp  = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		if ( 'json' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			$this->redirect( 'import_error', [ 'message' => '导入文件必须使用 .json 扩展名。' ] );
		}
		if ( $size <= 0 || $size > 2 * MB_IN_BYTES ) {
			$this->redirect( 'import_error', [ 'message' => 'JSON 文件不能为空，且大小不能超过 2 MB。' ] );
		}
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) || ! is_readable( $tmp ) ) {
			$this->redirect( 'import_error', [ 'message' => '无法读取上传的 JSON 文件。' ] );
		}

		$json = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $json ) {
			$this->redirect( 'import_error', [ 'message' => 'JSON 文件读取失败。' ] );
		}

		$inspection = $this->importer->inspect_json( $json );
		if ( is_wp_error( $inspection ) ) {
			$this->redirect(
				'import_error',
				[ 'message' => implode( '；', $inspection->get_error_messages() ) ]
			);
		}

		$token = strtolower( wp_generate_password( 20, false, false ) );
		set_transient(
			$this->transient_key( $token ),
			[
				'filename'   => $name,
				'inspection' => $inspection,
			],
			15 * MINUTE_IN_SECONDS
		);

		$this->redirect( 'import_preview_ready', [ 'import_token' => $token ] );
	}

	public function import_confirm(): void {
		$this->guard();
		$token = sanitize_key( wp_unslash( $_POST['import_token'] ?? '' ) );
		if ( '' === $token ) {
			$this->redirect( 'import_error', [ 'message' => '导入预览已失效，请重新选择 JSON 文件。' ] );
		}

		check_admin_referer( 'wem_cf_import_confirm_' . $token );
		$key     = $this->transient_key( $token );
		$preview = get_transient( $key );

		if ( ! is_array( $preview ) || empty( $preview['inspection']['groups'] ) ) {
			$this->redirect( 'import_error', [ 'message' => '导入预览已过期，请重新检查文件。' ] );
		}

		$groups   = (array) $preview['inspection']['groups'];
		$analysis = $this->importer->analyze_groups( $groups );
		if ( ! $analysis['can_import'] ) {
			delete_transient( $key );
			$this->redirect(
				'import_error',
				[ 'message' => '确认导入前检测到新的冲突：' . implode( '；', $analysis['conflicts'] ) ]
			);
		}

		$result = $this->importer->import_groups( $groups );
		if ( is_wp_error( $result ) ) {
			delete_transient( $key );
			$this->redirect( 'import_error', [ 'message' => implode( '；', $result->get_error_messages() ) ] );
		}

		delete_transient( $key );
		$this->redirect( 'imported', [ 'count' => absint( $result ) ] );
	}

	private function transient_key( string $token ): string {
		return 'wem_cf_import_' . get_current_user_id() . '_' . sanitize_key( $token );
	}

	private function render_notice(): void {
		$notice = sanitize_key( wp_unslash( $_GET['wem_cf_notice'] ?? '' ) );
		if ( '' === $notice || 'import_preview_ready' === $notice ) {
			return;
		}

		if ( 'imported' === $notice ) {
			$count = absint( $_GET['count'] ?? 0 );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( '导入完成：已新增 %d 个字段组。', $count ) )
			);
			return;
		}

		$message = sanitize_text_field( wp_unslash( $_GET['message'] ?? '' ) );
		if ( in_array( $notice, [ 'import_error', 'export_error' ], true ) && '' !== $message ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
		}
	}

	private function guard(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( '你没有权限使用 WEM 字段导入导出。', 'wem-content-fields' ) );
		}
	}

	private function redirect( string $notice, array $args = [] ): void {
		$url = add_query_arg(
			array_merge(
				[
					'page'          => 'wem-content-fields-tools',
					'wem_cf_notice' => $notice,
				],
				array_filter(
					$args,
					static function ( $value ): bool {
						return '' !== $value && null !== $value;
					}
				)
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
