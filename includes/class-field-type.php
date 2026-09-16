<?php
namespace WEM\Content_Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Field_Type {
	abstract public function get_type(): string;

	abstract public function get_label(): string;

	/**
	 * Describe type-specific field-definition settings for the admin UI.
	 * Supported setting UI types in Schema v1: text, textarea, options.
	 */
	abstract public function get_settings_schema(): array;

	/**
	 * Validate and normalize type-specific settings.
	 * Return sanitized settings or WP_Error.
	 */
	abstract public function validate_settings( array $settings, array $existing_settings = [] );

	/**
	 * Render one field value control inside a post edit meta box.
	 */
	abstract public function render( array $field, $value, string $input_name, string $input_id ): void;

	/**
	 * Sanitize a submitted field value.
	 */
	abstract public function sanitize_value( $value, array $field );

	/**
	 * Return type-specific register_post_meta() arguments.
	 */
	abstract public function get_meta_args( array $field ): array;
}
