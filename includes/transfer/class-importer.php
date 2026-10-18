<?php
namespace WEM\Content_Fields\Transfer;

use WEM\Content_Fields\Normalizer;
use WEM\Content_Fields\Storage;
use WEM\Content_Fields\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Importer {
	private Storage $storage;
	private Normalizer $normalizer;
	private Validator $validator;

	public function __construct( Storage $storage, Normalizer $normalizer, Validator $validator ) {
		$this->storage    = $storage;
		$this->normalizer = $normalizer;
		$this->validator  = $validator;
	}

	/**
	 * Decode, normalize and validate one portable JSON package.
	 * No database writes occur here.
	 */
	public function inspect_json( string $json ) {
		$data = json_decode( $json, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new \WP_Error( 'invalid_json', 'JSON 文件无法解析：' . json_last_error_msg() );
		}

		if ( 'wem-content-fields' !== (string) ( $data['format'] ?? '' ) ) {
			return new \WP_Error( 'invalid_format', '这不是有效的 WEM Content Fields 导出文件。' );
		}

		$schema_version = isset( $data['schema_version'] ) ? absint( $data['schema_version'] ) : 0;
		if ( ! in_array( $schema_version, [ 1, WEM_CF_SCHEMA_VERSION ], true ) ) {
			return new \WP_Error(
				'unsupported_schema',
				sprintf( '当前插件支持 Schema v%d，该文件为 Schema v%d。', WEM_CF_SCHEMA_VERSION, $schema_version )
			);
		}

		$raw_groups = $data['field_groups'] ?? null;
		if ( ! is_array( $raw_groups ) || empty( $raw_groups ) ) {
			return new \WP_Error( 'no_groups', '导入文件中没有字段组。' );
		}


		if ( 2 === $schema_version ) {
			foreach ( $raw_groups as $group_key => &$raw_group ) {
				if ( ! is_array( $raw_group ) ) {
					continue;
				}
				$location = isset( $raw_group['location'] ) && is_array( $raw_group['location'] ) ? $raw_group['location'] : [ 'mode' => 'all' ];
				if ( ! in_array( (string) ( $location['mode'] ?? 'all' ), [ 'all', 'specific_pages' ], true ) ) {
					return new \\WP_Error( 'invalid_location_mode', sprintf( '字段组 %s 的显示条件无效，未执行导入。', sanitize_key( (string) $group_key ) ) );
				}
				if ( 'all' === ( $location['mode'] ?? 'all' ) ) {
					$raw_group['location'] = [ 'mode' => 'all' ];
					continue;
				}
				$page_ids = [];
				foreach ( (array) ( $location['page_refs'] ?? [] ) as $page_ref ) {
					if ( ! is_array( $page_ref ) || '' === trim( (string) ( $page_ref['path'] ?? '' ) ) ) {
						return new \WP_Error( 'invalid_page_reference', sprintf( '字段组 %s 包含无效的页面引用，未执行导入。', sanitize_key( (string) $group_key ) ) );
					}
					$page = get_page_by_path( trim( (string) $page_ref['path'], '/' ), OBJECT, 'page' );
					if ( ! $page || 'page' !== $page->post_type ) {
						return new \WP_Error( 'page_reference_not_found', sprintf( '字段组 %s 引用的页面“%s”在目标站点不存在。请先创建该页面，或在目标站点手动创建字段组。', sanitize_key( (string) $group_key ), sanitize_text_field( (string) ( $page_ref['title'] ?? $page_ref['path'] ) ) ) );
					}
					$page_ids[] = (int) $page->ID;
				}
				if ( empty( $page_ids ) ) {
					return new \WP_Error( 'page_reference_empty', sprintf( '字段组 %s 没有可匹配的指定页面，未执行导入。', sanitize_key( (string) $group_key ) ) );
				}
				$raw_group['location'] = [ 'mode' => 'specific_pages', 'page_ids' => array_values( array_unique( $page_ids ) ) ];
			}
			unset( $raw_group );
		}

		$groups = $this->normalizer->groups( $raw_groups );
		if ( empty( $groups ) ) {
			return new \WP_Error( 'no_valid_groups', '导入文件中没有可识别的字段组。' );
		}

		$validated = [];
		$errors    = new \WP_Error();

		foreach ( $groups as $key => $group ) {
			$result = $this->validator->validate_group( $group, $validated );
			if ( is_wp_error( $result ) ) {
				foreach ( $result->get_error_messages() as $message ) {
					$errors->add( 'group_' . sanitize_key( $key ), sprintf( '字段组 %s：%s', $key, $message ) );
				}
				continue;
			}
			$validated[ $result['key'] ] = $result;
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return $this->analyze_groups(
			$validated,
			[
				'plugin_version' => sanitize_text_field( (string) ( $data['plugin_version'] ?? '' ) ),
				'exported_at'    => sanitize_text_field( (string) ( $data['exported_at'] ?? '' ) ),
			]
		);
	}

	/**
	 * Re-check conflicts against the current site. Used both for preview and confirm.
	 */
	public function analyze_groups( array $groups, array $source = [] ): array {
		$stored = $this->storage->all();

		// Conflicts must include both stored definitions and runtime definitions
		// injected by extensions such as a future wem-content-model.
		$runtime_raw    = (array) apply_filters( 'wem_cf_runtime_field_groups', $stored );
		$runtime_groups = $this->validator->validate_runtime_groups( $this->normalizer->groups( $runtime_raw ) );

		$existing_field_keys = [];
		$existing_meta_keys  = [];

		foreach ( $runtime_groups as $stored_group ) {
			if ( ! is_array( $stored_group ) ) {
				continue;
			}
			foreach ( (array) ( $stored_group['fields'] ?? [] ) as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				if ( ! empty( $field['key'] ) ) {
					$existing_field_keys[ (string) $field['key'] ] = true;
				}
				if ( ! empty( $field['meta_key'] ) ) {
					$existing_meta_keys[ (string) $field['meta_key'] ] = true;
				}
			}
		}

		$rows          = [];
		$conflicts     = [];
		$warning_count = 0;
		$field_count   = 0;

		foreach ( $groups as $key => $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$row_conflicts = [];
			$warnings      = [];
			$fields        = (array) ( $group['fields'] ?? [] );
			$field_count  += count( $fields );

			if ( isset( $runtime_groups[ $key ] ) ) {
				$row_conflicts[] = isset( $stored[ $key ] ) ? '字段组标识已存在' : '字段组标识已被运行时定义使用';
			}

			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$field_key = (string) ( $field['key'] ?? '' );
				$meta_key  = (string) ( $field['meta_key'] ?? '' );
				if ( '' !== $field_key && isset( $existing_field_keys[ $field_key ] ) ) {
					$row_conflicts[] = '字段标识已存在：' . $field_key;
				}
				if ( '' !== $meta_key && isset( $existing_meta_keys[ $meta_key ] ) ) {
					$row_conflicts[] = 'Meta Key 已存在：' . $meta_key;
				}
			}

			foreach ( (array) ( $group['post_types'] ?? [] ) as $post_type ) {
				$post_type = (string) $post_type;
				if ( '' !== $post_type && ! post_type_exists( $post_type ) ) {
					$warnings[] = $post_type . ' 当前不可用';
				}
			}

			$row_conflicts = array_values( array_unique( $row_conflicts ) );
			$warnings      = array_values( array_unique( $warnings ) );
			$warning_count += count( $warnings );

			if ( $row_conflicts ) {
				foreach ( $row_conflicts as $message ) {
					$conflicts[] = $key . '：' . $message;
				}
			}

			$rows[] = [
				'key'        => $key,
				'title'      => (string) ( $group['title'] ?? $key ),
				'field_count'=> count( $fields ),
				'post_types' => array_values( (array) ( $group['post_types'] ?? [] ) ),
				'conflicts'  => $row_conflicts,
				'warnings'   => $warnings,
				'status'     => $row_conflicts ? 'conflict' : ( $warnings ? 'warning' : 'new' ),
			];
		}

		return [
			'schema_version' => WEM_CF_SCHEMA_VERSION,
			'plugin_version' => (string) ( $source['plugin_version'] ?? '' ),
			'exported_at'    => (string) ( $source['exported_at'] ?? '' ),
			'groups'         => $groups,
			'rows'           => $rows,
			'group_count'    => count( $groups ),
			'field_count'    => $field_count,
			'warning_count'  => $warning_count,
			'conflicts'      => array_values( array_unique( $conflicts ) ),
			'can_import'     => empty( $conflicts ),
		];
	}

	/**
	 * Add groups without overwriting any existing definition.
	 */
	public function import_groups( array $groups ) {
		$analysis = $this->analyze_groups( $groups );
		if ( ! $analysis['can_import'] ) {
			$error = new \WP_Error( 'import_conflict', '导入前检测到冲突，未写入任何字段组。' );
			foreach ( $analysis['conflicts'] as $message ) {
				$error->add( 'import_conflict_item', $message );
			}
			return $error;
		}

		if ( empty( $groups ) ) {
			return new \WP_Error( 'no_groups', '没有可导入的字段组。' );
		}

		if ( ! $this->storage->add_many( $groups ) ) {
			return new \WP_Error( 'storage_failed', '字段组写入失败，数据库未完成更新。' );
		}

		return count( $groups );
	}
}
