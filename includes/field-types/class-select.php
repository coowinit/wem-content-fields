<?php
namespace WEM\Content_Fields\Field_Types;

use WEM\Content_Fields\Field_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Select extends Field_Type {
	public function get_type(): string {
		return 'select';
	}

	public function get_label(): string {
		return '下拉框';
	}

	public function get_settings_schema(): array {
		return [
			'options' => [
				'type'  => 'options',
				'label' => '选项',
				'help'  => '值使用小写英文、数字和下划线；创建后的选项值会锁定。',
			],
		];
	}

	public function validate_settings( array $settings, array $existing_settings = [] ) {
		$errors   = new \WP_Error();
		$options  = [];
		$seen     = [];
		$existing = [];

		foreach ( (array) ( $existing_settings['options'] ?? [] ) as $existing_option ) {
			if ( is_array( $existing_option ) && isset( $existing_option['value'] ) ) {
				$existing[ (string) $existing_option['value'] ] = true;
			}
		}

		foreach ( (array) ( $settings['options'] ?? [] ) as $index => $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}

			$raw_option_value = $option['value'] ?? '';
			$raw_label        = $option['label'] ?? '';
			$raw_original     = $option['_original_value'] ?? '';

			$raw_value = is_scalar( $raw_option_value ) ? trim( (string) $raw_option_value ) : '';
			$value     = sanitize_key( $raw_value );
			$label     = sanitize_text_field( is_scalar( $raw_label ) ? (string) $raw_label : '' );
			$original  = is_scalar( $raw_original ) ? sanitize_key( (string) $raw_original ) : '';

			if ( '' === $raw_value && '' === $label ) {
				continue;
			}

			if ( '' !== $original ) {
				if ( ! isset( $existing[ $original ] ) ) {
					$errors->add( 'option_original_missing_' . $index, '原选项值不存在，无法按原选项修改。' );
				} elseif ( $value !== $original ) {
					$errors->add( 'option_value_immutable_' . $index, '选项值创建后不可修改；如需变更，请删除旧选项后新增。' );
				}
			}

			if ( '' === $value || $raw_value !== $value || false !== strpos( $value, '-' ) ) {
				$errors->add( 'option_value_invalid_' . $index, '选项值仅允许小写英文、数字和下划线。' );
				continue;
			}
			if ( '' === $label ) {
				$errors->add( 'option_label_required_' . $index, '选项显示名称不能为空。' );
				continue;
			}
			if ( isset( $seen[ $value ] ) ) {
				$errors->add( 'option_value_duplicate_' . $index, '选项值重复：' . esc_html( $value ) );
				continue;
			}

			$seen[ $value ] = true;
			$options[]       = [
				'value' => $value,
				'label' => $label,
			];
		}

		if ( empty( $options ) ) {
			$errors->add( 'options_required', '下拉框至少需要一个选项。' );
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return [ 'options' => $options ];
	}

	public function render( array $field, $value, string $input_name, string $input_id ): void {
		$options = (array) ( $field['settings']['options'] ?? [] );
		echo '<select id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $input_name ) . '">';
		echo '<option value="">— 请选择 —</option>';
		foreach ( $options as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}
			$option_value = (string) ( $option['value'] ?? '' );
			$option_label = (string) ( $option['label'] ?? '' );
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $option_value ),
				selected( (string) $value, $option_value, false ),
				esc_html( $option_label )
			);
		}
		echo '</select>';
	}

	public function sanitize_value( $value, array $field ) {
		if ( ! is_scalar( $value ) && null !== $value ) {
			return '';
		}

		$value   = sanitize_key( (string) $value );
		$options = (array) ( $field['settings']['options'] ?? [] );
		foreach ( $options as $option ) {
			if ( is_array( $option ) && isset( $option['value'] ) && $value === (string) $option['value'] ) {
				return $value;
			}
		}
		return '';
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
