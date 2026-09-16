<?php
namespace WEM\Content_Fields\Admin;

use WEM\Content_Fields\Definitions;
use WEM\Content_Fields\Field_Type_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meta_Boxes {
	private Definitions $definitions;
	private Field_Type_Registry $types;

	public function __construct( Definitions $definitions, Field_Type_Registry $types ) {
		$this->definitions = $definitions;
		$this->types       = $types;
	}

	public function hooks(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'save_post', [ $this, 'save_post' ], 10, 2 );
	}

	private function capability(): string {
		return (string) apply_filters( 'wem_cf_manage_capability', 'manage_options' );
	}

	public function assets(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->post_type ) || empty( $this->definitions->for_post_type( (string) $screen->post_type ) ) ) {
			return;
		}

		wp_enqueue_style(
			'wem-content-fields-admin',
			WEM_CF_URL . 'assets/css/admin.css',
			[],
			WEM_CF_VERSION
		);

		$has_editor = false;
		$has_media  = false;
		foreach ( $this->definitions->for_post_type( (string) $screen->post_type ) as $group ) {
			foreach ( (array) ( $group['fields'] ?? [] ) as $field ) {
				$field_type = (string) ( $field['type'] ?? '' );
				if ( 'editor' === $field_type ) {
					$has_editor = true;
				}
				if ( in_array( $field_type, [ 'image', 'file' ], true ) ) {
					$has_media = true;
				}
			}
		}

		$dependencies = [ 'jquery' ];
		if ( $has_editor ) {
			wp_enqueue_editor();
			$dependencies[] = 'editor';
		}

		if ( $has_media ) {
			wp_enqueue_media();
		}

		if ( $has_editor || $has_media ) {
			wp_enqueue_script(
				'wem-content-fields-meta-boxes',
				WEM_CF_URL . 'assets/js/meta-boxes.js',
				$dependencies,
				WEM_CF_VERSION,
				true
			);
		}
	}

	public function add_meta_boxes( string $post_type, $post ): void {
		if ( ! current_user_can( $this->capability() ) ) {
			return;
		}

		$groups = $this->definitions->for_post_type( $post_type );
		if ( empty( $groups ) ) {
			return;
		}

		// Keep custom-fields support for the REST API while hiding WordPress' raw
		// Custom Fields box. WEM fields should be edited through structured boxes.
		remove_meta_box( 'postcustom', $post_type, 'normal' );

		foreach ( $groups as $group ) {
			if ( empty( $group['fields'] ) ) {
				continue;
			}

			add_meta_box(
				'wem-cf-' . sanitize_html_class( (string) $group['key'] ),
				(string) $group['title'],
				[ $this, 'render_meta_box' ],
				$post_type,
				'normal',
				'default',
				[ 'group' => $group ]
			);
		}
	}

	public function render_meta_box( $post, array $box ): void {
		$group = $box['args']['group'] ?? null;
		if ( ! is_array( $group ) ) {
			return;
		}

		wp_nonce_field( 'wem_cf_save_values_' . $group['key'], 'wem_cf_nonce_' . $group['key'] );

		if ( ! empty( $group['description'] ) ) {
			echo '<p class="description wem-cf-group-description">' . esc_html( $group['description'] ) . '</p>';
		}

		echo '<div class="wem-cf-values">';
		foreach ( (array) $group['fields'] as $field ) {
			$type = $this->types->get( (string) ( $field['type'] ?? '' ) );
			if ( ! $type ) {
				continue;
			}

			$field_key = (string) $field['key'];
			$meta_key  = (string) $field['meta_key'];
			$value     = get_post_meta( $post->ID, $meta_key, true );
			$input_id  = 'wem_cf_value_' . $field_key;
			$input_name = 'wem_cf_values[' . $field_key . ']';

			echo '<div class="wem-cf-value-row">';
			printf( '<label class="wem-cf-value-label" for="%1$s">%2$s</label>', esc_attr( $input_id ), esc_html( $field['label'] ) );
			$type->render( $field, $value, $input_name, $input_id );
			if ( ! empty( $field['description'] ) ) {
				echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	public function save_post( int $post_id, $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( $this->capability() ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return;
		}

		$submitted = isset( $_POST['wem_cf_values'] ) && is_array( $_POST['wem_cf_values'] )
			? wp_unslash( $_POST['wem_cf_values'] )
			: [];

		foreach ( $this->definitions->for_post_type( $post_type ) as $group ) {
			$nonce_name = 'wem_cf_nonce_' . $group['key'];
			$nonce      = isset( $_POST[ $nonce_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'wem_cf_save_values_' . $group['key'] ) ) {
				continue;
			}

			foreach ( (array) $group['fields'] as $field ) {
				$field_key = (string) ( $field['key'] ?? '' );
				$meta_key  = (string) ( $field['meta_key'] ?? '' );
				$type      = $this->types->get( (string) ( $field['type'] ?? '' ) );

				if ( '' === $field_key || '' === $meta_key || ! $type || ! array_key_exists( $field_key, $submitted ) ) {
					continue;
				}

				$value = $type->sanitize_value( $submitted[ $field_key ], $field );
				if ( '' === $value || 0 === $value || null === $value || ( is_array( $value ) && empty( $value ) ) ) {
					delete_post_meta( $post_id, $meta_key );
				} else {
					update_post_meta( $post_id, $meta_key, $value );
				}
			}
		}
	}
}
