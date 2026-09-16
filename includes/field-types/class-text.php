<?php
namespace WEM\Content_Fields\Field_Types;

use WEM\Content_Fields\Field_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Text extends Field_Type {
	public function get_type(): string {
		return 'text';
	}

	public function get_label(): string {
		return '单行文本';
	}

	public function get_settings_schema(): array {
		return [
			'placeholder' => [
				'type'  => 'text',
				'label' => '占位示例',
				'help'  => '例如：140 × 25 mm',
			],
		];
	}

	public function validate_settings( array $settings, array $existing_settings = [] ) {
		$value = $settings['placeholder'] ?? '';
		return [
			'placeholder' => sanitize_text_field( is_scalar( $value ) || null === $value ? (string) $value : '' ),
		];
	}

	public function render( array $field, $value, string $input_name, string $input_id ): void {
		$placeholder = (string) ( $field['settings']['placeholder'] ?? '' );
		printf(
			'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s">',
			esc_attr( $input_id ),
			esc_attr( $input_name ),
			esc_attr( (string) $value ),
			esc_attr( $placeholder )
		);
	}

	public function sanitize_value( $value, array $field ) {
		return is_scalar( $value ) || null === $value ? sanitize_text_field( (string) $value ) : '';
	}

	public function get_meta_args( array $field ): array {
		return [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => ! empty( $field['show_in_rest'] ),
			'sanitize_callback' => function ( $value ) use ( $field ) {
				return $this->sanitize_value( $value, $field );
			},
		];
	}
}
