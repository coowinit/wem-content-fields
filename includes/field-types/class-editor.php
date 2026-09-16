<?php
namespace WEM\Content_Fields\Field_Types;

use WEM\Content_Fields\Field_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Editor extends Field_Type {
	public function get_type(): string {
		return 'editor';
	}

	public function get_label(): string {
		return '微型富文本';
	}

	public function get_settings_schema(): array {
		return [];
	}

	public function validate_settings( array $settings, array $existing_settings = [] ) {
		return [];
	}

	public function render( array $field, $value, string $input_name, string $input_id ): void {
		/*
		 * 不在 Meta Box 回调中直接调用 wp_editor()。
		 * Gutenberg 会移动传统 Meta Box 的 DOM，而 TinyMCE 初始化后不能安全移动。
		 * 这里只输出 textarea，等 Meta Box 到最终位置后由 assets/js/meta-boxes.js
		 * 使用 wp.editor.initialize() 动态初始化。
		 */
		printf(
			'<textarea class="large-text wem-cf-mini-editor" rows="6" id="%1$s" name="%2$s">%3$s</textarea>',
			esc_attr( $input_id ),
			esc_attr( $input_name ),
			esc_textarea( (string) $value )
		);
	}

	public function sanitize_value( $value, array $field ) {
		return is_scalar( $value ) || null === $value ? wp_kses_post( (string) $value ) : '';
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
