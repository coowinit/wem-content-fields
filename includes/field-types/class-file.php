<?php
namespace WEM\Content_Fields\Field_Types;

use WEM\Content_Fields\Field_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class File extends Field_Type {
	public function get_type(): string {
		return 'file';
	}

	public function get_label(): string {
		return 'PDF 文件';
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

		if ( $attachment_id ) {
			$path = get_attached_file( $attachment_id );
			if ( is_string( $path ) && '' !== $path ) {
				$filename = wp_basename( $path );
			}
		}

		echo '<div class="wem-cf-media-field" data-wem-media-kind="pdf">';
		printf(
			'<input type="hidden" class="wem-cf-media-id" id="%1$s" name="%2$s" value="%3$s">',
			esc_attr( $input_id ),
			esc_attr( $input_name ),
			esc_attr( (string) $attachment_id )
		);

		echo '<div class="wem-cf-media-preview">';
		if ( $attachment_id ) {
			echo '<span class="dashicons dashicons-media-document" aria-hidden="true"></span>';
			echo '<span class="wem-cf-media-name">' . esc_html( $filename ?: 'PDF 文件' ) . '</span>';
		} else {
			echo '<span class="wem-cf-media-empty">尚未选择 PDF</span>';
		}
		echo '</div>';

		printf(
			'<button type="button" class="button wem-cf-media-select" data-select-label="选择 PDF" data-replace-label="更换 PDF">%s</button>',
			esc_html( $attachment_id ? '更换 PDF' : '选择 PDF' )
		);
		printf(
			' <button type="button" class="button-link-delete wem-cf-media-remove"%s>移除</button>',
			$attachment_id ? '' : ' hidden'
		);
		echo '<p class="description">仅允许选择 PDF 文件。</p>';
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

		return 'application/pdf' === get_post_mime_type( $attachment_id ) ? $attachment_id : 0;
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
