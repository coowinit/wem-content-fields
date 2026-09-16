<?php
namespace WEM\Content_Fields\Field_Types;

use WEM\Content_Fields\Field_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Textarea extends Field_Type {
	public function get_type(): string {
		return 'textarea';
	}

	public function get_label(): string {
		return '多行文本';
	}

	public function get_settings_schema(): array {
		return [
			'placeholder' => [
				'type'  => 'textarea',
				'label' => '占位示例',
				'help'  => '用于提示填写内容。',
			],
		];
	}

	public function validate_settings( array $settings, array $existing_settings = [] ) {
		$value = $settings['placeholder'] ?? '';
		return [
			'placeholder' => sanitize_textarea_field( is_scalar( $value ) || null === $value ? (string) $value : '' ),
		];
	}

	public function render( array $field, $value, string $input_name, string $input_id ): void {
		$placeholder = (string) ( $field['settings']['placeholder'] ?? '' );
		printf(
			'<textarea class="large-text" rows="5" id="%1$s" name="%2$s" placeholder="%3$s">%4$s</textarea>',
			esc_attr( $input_id ),
			esc_attr( $input_name ),
			esc_attr( $placeholder ),
			esc_textarea( (string) $value )
		);
	}

	public function sanitize_value( $value, array $field ) {
		return is_scalar( $value ) || null === $value ? sanitize_textarea_field( (string) $value ) : '';
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
