<?php
namespace WEM\Content_Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registry {
	private Definitions $definitions;
	private Field_Type_Registry $types;

	public function __construct( Definitions $definitions, Field_Type_Registry $types ) {
		$this->definitions = $definitions;
		$this->types       = $types;
	}

	public function hooks(): void {
		add_action( 'init', [ $this, 'register' ], 99 );
	}

	public function register(): void {
		$groups = $this->definitions->all();

		foreach ( $groups as $group ) {
			$fields = (array) ( $group['fields'] ?? [] );
			if ( empty( $fields ) ) {
				continue;
			}

			foreach ( (array) ( $group['post_types'] ?? [] ) as $post_type ) {
				if ( ! post_type_exists( $post_type ) ) {
					continue;
				}

				if ( ! post_type_supports( $post_type, 'custom-fields' ) ) {
					$allow_support = (bool) apply_filters( 'wem_cf_add_custom_fields_support', true, $post_type, $group );
					if ( $allow_support ) {
						add_post_type_support( $post_type, 'custom-fields' );
					}
				}

				foreach ( $fields as $field ) {
					$this->register_field( $post_type, $field );
				}
			}
		}
	}

	private function register_field( string $post_type, array $field ): void {
		$type = $this->types->get( (string) ( $field['type'] ?? '' ) );
		if ( ! $type || empty( $field['meta_key'] ) ) {
			return;
		}

		$args = $type->get_meta_args( $field );
		$args['description'] = (string) ( $field['description'] ?? '' );
		$args['auth_callback'] = static function ( $allowed, $meta_key, $post_id ): bool {
			$capability = (string) apply_filters( 'wem_cf_manage_capability', 'manage_options' );
			return current_user_can( $capability ) && current_user_can( 'edit_post', (int) $post_id );
		};

		$args = (array) apply_filters( 'wem_cf_meta_args', $args, $field, $post_type );

		register_post_meta( $post_type, (string) $field['meta_key'], $args );
	}
}
