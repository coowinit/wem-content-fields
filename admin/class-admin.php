<?php
namespace WEM\Content_Fields\Admin;

use WEM\Content_Fields\Field_Type_Registry;
use WEM\Content_Fields\Normalizer;
use WEM\Content_Fields\Storage;
use WEM\Content_Fields\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {
	private Storage $storage;
	private Normalizer $normalizer;
	private Validator $validator;
	private Field_Type_Registry $types;

	public function __construct( Storage $storage, Normalizer $normalizer, Validator $validator, Field_Type_Registry $types ) {
		$this->storage    = $storage;
		$this->normalizer = $normalizer;
		$this->validator  = $validator;
		$this->types      = $types;
	}

	public function hooks(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'admin_post_wem_cf_save_field_group', [ $this, 'save_field_group' ] );
		add_action( 'admin_post_wem_cf_delete_field_group', [ $this, 'delete_field_group' ] );
	}

	private function capability(): string {
		return (string) apply_filters( 'wem_cf_manage_capability', 'manage_options' );
	}

	public function menu(): void {
		add_menu_page(
			'WEM 字段',
			'WEM 字段',
			$this->capability(),
			'wem-content-fields',
			[ $this, 'render_page' ],
			'dashicons-editor-table',
			59
		);

		add_submenu_page(
			'wem-content-fields',
			'字段组',
			'字段组',
			$this->capability(),
			'wem-content-fields',
			[ $this, 'render_page' ]
		);
	}

	public function assets( string $hook ): void {
		if ( false === strpos( $hook, 'wem-content-fields' ) ) {
			return;
		}

		wp_enqueue_style(
			'wem-content-fields-admin',
			WEM_CF_URL . 'assets/css/admin.css',
			[],
			WEM_CF_VERSION
		);

		wp_enqueue_script(
			'wem-content-fields-admin',
			WEM_CF_URL . 'assets/js/admin.js',
			[],
			WEM_CF_VERSION,
			true
		);

		wp_localize_script(
			'wem-content-fields-admin',
			'WEMCFAdmin',
			[
				'fieldTypes'           => $this->types->labels(),
				'fieldSettings'        => $this->types->settings_schemas(),
				'confirmDeleteField'   => '确定删除这个字段吗？字段定义会被移除，但已有文章中的字段数据不会自动删除。',
				'confirmDeleteOption'  => '确定删除这个选项吗？已有文章中保存的旧值不会自动删除。',
			]
		);
	}

	public function render_page(): void {
		$this->guard();
		$this->render_notice();

		$action = sanitize_key( wp_unslash( $_GET['action'] ?? '' ) );
		$key    = sanitize_key( wp_unslash( $_GET['group'] ?? '' ) );

		if ( in_array( $action, [ 'add', 'edit' ], true ) ) {
			$this->render_form( 'edit' === $action ? $key : null );
			return;
		}

		$groups = $this->storage->all();
		include WEM_CF_PATH . 'admin/views/field-groups.php';
	}

	private function render_form( ?string $key ): void {
		$config = $key ? $this->storage->get( $key ) : null;
		if ( $key && ! $config ) {
			$this->redirect( 'group_not_found' );
		}

		$is_new = null === $config;
		$config = wp_parse_args(
			$config ?: [],
			[
				'key'         => '',
				'title'       => '',
				'description' => '',
				'post_types'  => [],
				'fields'      => [],
			]
		);

		$available_post_types = $this->available_post_types( (array) $config['post_types'] );
		$available_pages      = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'ASC', 'post_status' => [ 'publish', 'draft', 'private', 'pending' ] ] );
		$field_type_labels    = $this->types->labels();
		$field_settings       = $this->types->settings_schemas();
		include WEM_CF_PATH . 'admin/views/field-group-edit.php';
	}

	public function save_field_group(): void {
		$this->guard();
		check_admin_referer( 'wem_cf_save_field_group' );

		$editing_key = sanitize_key( wp_unslash( $_POST['editing_key'] ?? '' ) );
		$raw         = isset( $_POST['group'] ) && is_array( $_POST['group'] ) ? wp_unslash( $_POST['group'] ) : [];
		$normalized  = $this->normalizer->group( $raw );
		$groups      = $this->storage->all();
		$existing    = $editing_key ? $this->storage->get( $editing_key ) : null;
		$result      = $this->validator->validate_group( $normalized, $groups, $editing_key ?: null, $existing );

		if ( is_wp_error( $result ) ) {
			$this->redirect(
				'validation_error',
				[
					'errors' => implode( '||', $result->get_error_messages() ),
					'action' => $editing_key ? 'edit' : 'add',
					'group'  => $editing_key,
				]
			);
		}

		$this->storage->save( $result );
		$this->redirect( 'saved' );
	}

	public function delete_field_group(): void {
		$this->guard();
		$key = sanitize_key( wp_unslash( $_GET['group'] ?? '' ) );
		check_admin_referer( 'wem_cf_delete_field_group_' . $key );

		if ( '' !== $key ) {
			$this->storage->delete( $key );
		}

		$this->redirect( 'deleted' );
	}

	private function available_post_types( array $preserve_slugs = [] ): array {
		$objects  = get_post_types( [ 'show_ui' => true ], 'objects' );
		$builtin  = [];
		$external = [];

		foreach ( [ 'post', 'page' ] as $slug ) {
			if ( isset( $objects[ $slug ] ) ) {
				$builtin[ $slug ] = $objects[ $slug ]->labels->name ?? $slug;
			}
		}

		foreach ( $objects as $slug => $object ) {
			if ( isset( $builtin[ $slug ] ) ) {
				continue;
			}
			if ( ! empty( $object->_builtin ) || empty( $object->public ) ) {
				continue;
			}
			$external[ $slug ] = $object->labels->name ?? $slug;
		}

		$available = $builtin + $external;
		foreach ( $preserve_slugs as $slug ) {
			$slug = sanitize_key( $slug );
			if ( $slug && ! isset( $available[ $slug ] ) ) {
				$available[ $slug ] = $slug . ' — 当前不可用';
			}
		}

		/**
		 * Filter post types available to field groups.
		 */
		$available = (array) apply_filters( 'wem_cf_available_post_types', $available, $preserve_slugs );
		return $available;
	}

	private function render_notice(): void {
		$notice = sanitize_key( wp_unslash( $_GET['wem_cf_notice'] ?? '' ) );
		if ( '' === $notice ) {
			return;
		}

		$messages = [
			'saved'           => [ 'success', '字段组已保存。' ],
			'deleted'         => [ 'success', '字段组已删除。已有文章中的字段数据未被删除。' ],
			'group_not_found' => [ 'error', '没有找到该字段组。' ],
		];

		if ( 'validation_error' === $notice ) {
			$raw    = (string) wp_unslash( $_GET['errors'] ?? '' );
			$errors = array_filter( explode( '||', $raw ) );
			echo '<div class="notice notice-error"><p><strong>字段组保存失败：</strong></p><ul class="wem-cf-error-list">';
			foreach ( $errors as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul></div>';
			return;
		}

		if ( isset( $messages[ $notice ] ) ) {
			[ $type, $message ] = $messages[ $notice ];
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
		}
	}

	private function guard(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( '你没有权限管理 WEM 字段。', 'wem-content-fields' ) );
		}
	}

	private function redirect( string $notice, array $args = [] ): void {
		$url = add_query_arg(
			array_merge(
				[
					'page'          => 'wem-content-fields',
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
