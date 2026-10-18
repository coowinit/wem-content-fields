<?php
namespace WEM\Content_Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Validator {
	private Field_Type_Registry $types;

	public function __construct( Field_Type_Registry $types ) {
		$this->types = $types;
	}

	/**
	 * Validate one field group against an existing stored collection.
	 * Returns the sanitized Schema v1 group or WP_Error.
	 */
	public function validate_group( array $input, array $existing_groups = [], ?string $editing_key = null, ?array $existing_group = null ) {
		$errors  = new \WP_Error();
		$raw_key = trim( (string) ( $input['key'] ?? '' ) );
		$key     = sanitize_key( $raw_key );

		if ( '' === $key ) {
			$errors->add( 'group_key_required', '字段组标识不能为空。' );
		} elseif ( $raw_key !== $key || false !== strpos( $key, '-' ) ) {
			$errors->add( 'group_key_format', '字段组标识仅允许小写英文、数字和下划线。' );
		}

		if ( $editing_key && $key !== $editing_key ) {
			$errors->add( 'group_key_immutable', '字段组标识创建后不可修改。' );
		}

		if ( ! $editing_key && isset( $existing_groups[ $key ] ) ) {
			$errors->add( 'group_key_exists', '该字段组标识已经存在。' );
		}

		$title = sanitize_text_field( $input['title'] ?? '' );
		if ( '' === $title ) {
			$errors->add( 'group_title_required', '字段组名称不能为空。' );
		}

		$post_types = [];
		foreach ( (array) ( $input['post_types'] ?? [] ) as $post_type ) {
			$raw_post_type = trim( (string) $post_type );
			$clean          = sanitize_key( $raw_post_type );
			if ( '' === $clean || $raw_post_type !== $clean ) {
				$errors->add( 'post_type_invalid', '内容类型标识格式不正确：' . $raw_post_type );
				continue;
			}
			$post_types[] = $clean;
		}
		$post_types = array_values( array_unique( $post_types ) );
		if ( empty( $post_types ) ) {
			$errors->add( 'post_type_required', '请至少选择一个内容类型。' );
		}

		$raw_location = isset( $input['location'] ) && is_array( $input['location'] ) ? $input['location'] : [];
		$location_mode = sanitize_key( $raw_location['mode'] ?? 'all' );
		if ( ! in_array( $location_mode, [ 'all', 'specific_pages' ], true ) ) {
			$errors->add( 'location_mode_invalid', '显示条件不正确，请重新选择。' );
			$location_mode = 'all';
		}
		$page_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $raw_location['page_ids'] ?? [] ) ) ) ) );
		if ( 'specific_pages' === $location_mode && ( [ 'page' ] !== $post_types ) ) {
			$errors->add( 'location_post_type_required', '使用“仅在指定页面显示”时，应用到内容类型必须只选择 Pages。' );
		}
		if ( 'specific_pages' === $location_mode && empty( $page_ids ) ) {
			$errors->add( 'location_pages_required', '请选择至少一个指定页面。' );
		}
		if ( 'specific_pages' === $location_mode ) {
			foreach ( $page_ids as $page_id ) {
				if ( 'page' !== get_post_type( $page_id ) ) {
					$errors->add( 'location_page_invalid', '指定页面中包含无效的页面，请刷新后重新选择。' );
				}
			}
		}

		$other_field_keys = [];
		$other_meta_keys  = [];
		foreach ( $existing_groups as $group_key => $group ) {
			if ( $editing_key && $group_key === $editing_key ) {
				continue;
			}
			foreach ( (array) ( $group['fields'] ?? [] ) as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				if ( ! empty( $field['key'] ) ) {
					$other_field_keys[ (string) $field['key'] ] = true;
				}
				if ( ! empty( $field['meta_key'] ) ) {
					$other_meta_keys[ (string) $field['meta_key'] ] = true;
				}
			}
		}

		$existing_fields = [];
		foreach ( (array) ( $existing_group['fields'] ?? [] ) as $field ) {
			if ( is_array( $field ) && ! empty( $field['key'] ) ) {
				$existing_fields[ (string) $field['key'] ] = $field;
			}
		}

		$raw_fields = (array) ( $input['fields'] ?? [] );
		if ( empty( $raw_fields ) ) {
			$errors->add( 'fields_required', '请至少添加一个字段后再保存字段组。' );
		}

		$fields          = [];
		$seen_field_keys = [];
		$seen_meta_keys  = [];

		foreach ( $raw_fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$validated = $this->validate_field( $field, $existing_fields, $other_field_keys, $other_meta_keys );
			if ( is_wp_error( $validated ) ) {
				foreach ( $validated->get_error_messages() as $message ) {
					$errors->add( 'field_' . $index, sprintf( '字段 #%d：%s', $index + 1, $message ) );
				}
				continue;
			}

			if ( isset( $seen_field_keys[ $validated['key'] ] ) ) {
				$errors->add( 'field_key_duplicate', '字段标识重复：' . $validated['key'] );
				continue;
			}
			if ( isset( $seen_meta_keys[ $validated['meta_key'] ] ) ) {
				$errors->add( 'meta_key_duplicate', 'Meta Key 重复：' . $validated['meta_key'] );
				continue;
			}

			$seen_field_keys[ $validated['key'] ] = true;
			$seen_meta_keys[ $validated['meta_key'] ]   = true;
			$fields[]                                   = $validated;
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return [
			'key'         => $key,
			'title'       => $title,
			'description' => sanitize_textarea_field( $input['description'] ?? '' ),
			'post_types'  => $post_types,
			'location'    => [
				'mode'     => $location_mode,
				'page_ids' => 'specific_pages' === $location_mode ? $page_ids : [],
			],
			'fields'      => $fields,
		];
	}

	private function validate_field( array $field, array $existing_fields, array $other_field_keys, array $other_meta_keys ) {
		$errors       = new \WP_Error();
		$raw_key      = trim( (string) ( $field['key'] ?? '' ) );
		$key          = sanitize_key( $raw_key );
		$original_key = sanitize_key( (string) ( $field['_original_key'] ?? '' ) );

		if ( '' === $key ) {
			$errors->add( 'field_key_required', '字段标识不能为空。' );
		} elseif ( $raw_key !== $key || false !== strpos( $key, '-' ) ) {
			$errors->add( 'field_key_format', '字段标识仅允许小写英文、数字和下划线。' );
		}

		if ( isset( $other_field_keys[ $key ] ) ) {
			$errors->add( 'field_key_exists', '字段标识已被其他字段使用：' . $key );
		}

		$existing = null;
		if ( '' !== $original_key ) {
			if ( ! isset( $existing_fields[ $original_key ] ) ) {
				$errors->add( 'field_original_missing', '原字段不存在，无法按已有字段修改。' );
			} else {
				$existing = $existing_fields[ $original_key ];
				if ( $key !== $original_key ) {
					$errors->add( 'field_key_immutable', '字段标识创建后不可修改；如需更换标识，请删除旧字段后新增。' );
				}
			}
		} elseif ( isset( $existing_fields[ $key ] ) ) {
			$existing = $existing_fields[ $key ];
		}

		$label = sanitize_text_field( $field['label'] ?? '' );
		if ( '' === $label ) {
			$errors->add( 'field_label_required', '字段名称不能为空。' );
		}

		$type = sanitize_key( $field['type'] ?? '' );
		if ( ! $this->types->has( $type ) ) {
			$errors->add( 'field_type_invalid', '不支持的字段类型：' . esc_html( $type ) );
		}

		$meta_key = '_wem_cf_' . $key;
		if ( isset( $other_meta_keys[ $meta_key ] ) ) {
			$errors->add( 'meta_key_exists', 'Meta Key 已被其他字段使用：' . $meta_key );
		}

		if ( is_array( $existing ) ) {
			if ( isset( $existing['type'] ) && $type !== $existing['type'] ) {
				$errors->add( 'field_type_immutable', '字段类型创建后不可修改。' );
			}
			if ( isset( $existing['meta_key'] ) && $meta_key !== $existing['meta_key'] ) {
				$errors->add( 'meta_key_immutable', 'Meta Key 创建后不可修改。' );
			}
		}

		$settings = [];
		if ( $this->types->has( $type ) ) {
			$handler           = $this->types->get( $type );
			$existing_settings = is_array( $existing ) ? (array) ( $existing['settings'] ?? [] ) : [];
			$settings          = $handler ? $handler->validate_settings( (array) ( $field['settings'] ?? [] ), $existing_settings ) : [];
			if ( is_wp_error( $settings ) ) {
				foreach ( $settings->get_error_messages() as $message ) {
					$errors->add( 'settings_invalid', $message );
				}
			}
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return [
			'key'          => $key,
			'label'        => $label,
			'type'         => $type,
			'meta_key'     => $meta_key,
			'description'  => sanitize_textarea_field( $field['description'] ?? '' ),
			'show_in_rest' => ! empty( $field['show_in_rest'] ),
			'settings'     => is_array( $settings ) ? $settings : [],
		];
	}

	/**
	 * Validate a runtime definition collection. Invalid groups are skipped,
	 * avoiding fatal errors if an extension provides malformed definitions.
	 */
	public function validate_runtime_groups( array $groups ): array {
		$valid = [];
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$result = $this->validate_group( $group, $valid );
			if ( is_wp_error( $result ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[WEM Content Fields] Invalid runtime field group: ' . implode( ' | ', $result->get_error_messages() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				continue;
			}
			$valid[ $result['key'] ] = $result;
		}
		return $valid;
	}
}
