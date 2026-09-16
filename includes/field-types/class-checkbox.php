<?php
namespace WEM\Content_Fields\Field_Types;

use WEM\Content_Fields\Field_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Checkbox extends Field_Type {
	public function get_type(): string {
		return 'checkbox';
	}

	public function get_label(): string {
		return '多选框';
	}

	public function get_settings_schema(): array {
		return [
			'options' => [
				'type'  => 'options',
				'label' => '选项',
				'help'  => '值使用小写英文、数字和下划线；一个字段会保存为一个字符串数组。',
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
			$errors->add( 'options_required', '多选框至少需要一个选项。' );
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return [ 'options' => $options ];
	}

	public function render( array $field, $value, string $input_name, string $input_id ): void {
		$selected = is_array( $value ) ? array_map( 'strval', $value ) : [];
		$options  = (array) ( $field['settings']['options'] ?? [] );

		// Ensure the field is submitted even when every checkbox is unchecked.
		echo '<input type="hidden" name="' . esc_attr( $input_name ) . '[]" value="">';
		echo '<div class="wem-cf-checkboxes" id="' . esc_attr( $input_id ) . '">';
		foreach ( $options as $index => $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}
			$option_value = (string) ( $option['value'] ?? '' );
			$option_label = (string) ( $option['label'] ?? '' );
			$option_id    = $input_id . '_' . $index;
			printf(
				'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[]" value="%3$s" %4$s> %5$s</label>',
				esc_attr( $option_id ),
				esc_attr( $input_name ),
				esc_attr( $option_value ),
				checked( in_array( $option_value, $selected, true ), true, false ),
				esc_html( $option_label )
			);
		}
		echo '</div>';
	}

	public function sanitize_value( $value, array $field ) {
		$submitted = [];
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) || null === $item ) {
					$clean = sanitize_key( (string) $item );
					if ( '' !== $clean ) {
						$submitted[] = $clean;
					}
				}
			}
		}
		$submitted = array_values( array_unique( $submitted ) );
		$selected  = [];

		foreach ( (array) ( $field['settings']['options'] ?? [] ) as $option ) {
			if ( ! is_array( $option ) || ! isset( $option['value'] ) ) {
				continue;
			}
			$option_value = (string) $option['value'];
			if ( in_array( $option_value, $submitted, true ) ) {
				$selected[] = $option_value;
			}
		}

		return $selected;
	}

	public function get_meta_args( array $field ): array {
		$show_in_rest = false;
		if ( ! empty( $field['show_in_rest'] ) ) {
			$show_in_rest = [
				'schema' => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
			];
		}

		return [
			'type'              => 'array',
			'single'            => true,
			'show_in_rest'      => $show_in_rest,
			'sanitize_callback' => function ( $value ) use ( $field ) {
				return $this->sanitize_value( $value, $field );
			},
		];
	}
}
