<?php
namespace WEM\Content_Fields\Field_Types;

use WEM\Content_Fields\Field_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Image extends Field_Type {
	public function get_type(): string {
		return 'image';
	}

	public function get_label(): string {
		return '图片';
	}

	public function get_settings_schema(): array {
		return [];
	}

	public function validate_settings( array $settings, array $existing_settings = [] ) {
		return [];
	}

	public function render( array $field, $value, string $input_name, string $input_id ): void {
		$attachment_id = $this->sanitize_value( $value, $field );
		$filename      = '';
		$preview       = '';

		if ( $attachment_id ) {
			$preview = wp_get_attachment_image(
				$attachment_id,
				'thumbnail',
				false,
				[ 'class' => 'wem-cf-media-image' ]
			);
			$path = get_attached_file( $attachment_id );
			if ( is_string( $path ) && '' !== $path ) {
				$filename = wp_basename( $path );
			}
		}

		echo '<div class="wem-cf-media-field" data-wem-media-kind="image">';
		printf(
			'<input type="hidden" class="wem-cf-media-id" id="%1$s" name="%2$s" value="%3$s">',
			esc_attr( $input_id ),
			esc_attr( $input_name ),
			esc_attr( (string) $attachment_id )
		);

		echo '<div class="wem-cf-media-preview">';
		if ( $attachment_id && $preview ) {
			echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( '' !== $filename ) {
				echo '<div class="wem-cf-media-name">' . esc_html( $filename ) . '</div>';
			}
		} else {
			echo '<span class="wem-cf-media-empty">尚未选择图片</span>';
		}
		echo '</div>';

		printf(
			'<button type="button" class="button wem-cf-media-select" data-select-label="选择图片" data-replace-label="更换图片">%s</button>',
			esc_html( $attachment_id ? '更换图片' : '选择图片' )
		);
		printf(
			' <button type="button" class="button-link-delete wem-cf-media-remove"%s>移除</button>',
			$attachment_id ? '' : ' hidden'
		);
		echo '</div>';
	}

	public function sanitize_value( $value, array $field ) {
		if ( ! is_scalar( $value ) && null !== $value ) {
			return 0;
		}

		$attachment_id = absint( $value );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return 0;
		}

		return wp_attachment_is_image( $attachment_id ) ? $attachment_id : 0;
	}

	public function get_meta_args( array $field ): array {
		return [
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => ! empty( $field['show_in_rest'] ),
			'sanitize_callback' => function ( $value ) use ( $field ) {
				return $this->sanitize_value( $value, $field );
			},
		];
	}
}
