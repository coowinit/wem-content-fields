<?php
namespace WEM\Content_Fields\Transfer;

use WEM\Content_Fields\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Exporter {
	private Storage $storage;

	public function __construct( Storage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Build a portable Schema v1 export package from stored definitions only.
	 * Runtime definitions injected by filters are intentionally excluded.
	 */
	public function package( array $selected_keys = [] ): array {
		$stored = $this->storage->all();
		$groups = [];

		foreach ( $selected_keys as $key ) {
			$key = sanitize_key( is_scalar( $key ) ? (string) $key : '' );
			if ( '' === $key || ! isset( $stored[ $key ] ) || ! is_array( $stored[ $key ] ) ) {
				continue;
			}
			$groups[ $key ] = $stored[ $key ];
		}

		return [
			'format'         => 'wem-content-fields',
			'schema_version' => WEM_CF_SCHEMA_VERSION,
			'plugin_version' => WEM_CF_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'field_groups'   => $groups,
		];
	}

	public function json( array $selected_keys = [] ) {
		$package = $this->package( $selected_keys );

		if ( empty( $package['field_groups'] ) ) {
			return new \WP_Error( 'no_groups', '请至少选择一个字段组进行导出。' );
		}

		$json = wp_json_encode(
			$package,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		if ( false === $json ) {
			return new \WP_Error( 'encode_failed', 'JSON 导出生成失败。' );
		}

		return $json;
	}
}
