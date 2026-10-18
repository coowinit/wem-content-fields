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
	 * Build a portable Schema v2 export package from stored definitions only.
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
			$groups[ $key ] = $this->portable_group( $stored[ $key ] );
		}

		return [
			'format'         => 'wem-content-fields',
			'schema_version' => WEM_CF_SCHEMA_VERSION,
			'plugin_version' => WEM_CF_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'field_groups'   => $groups,
		];
	}

	/**
	 * Replace site-local Page IDs with path references for portable JSON.
	 */
	private function portable_group( array $group ): array {
		$location = isset( $group['location'] ) && is_array( $group['location'] ) ? $group['location'] : [ 'mode' => 'all' ];
		if ( 'specific_pages' === ( $location['mode'] ?? 'all' ) ) {
			$refs = [];
			foreach ( array_map( 'absint', (array) ( $location['page_ids'] ?? [] ) ) as $page_id ) {
				$page = get_post( $page_id );
				if ( ! $page || 'page' !== $page->post_type ) {
					continue;
				}
				$refs[] = [
					'path'  => trim( (string) get_page_uri( $page_id ), '/' ),
					'slug'  => (string) $page->post_name,
					'title' => (string) $page->post_title,
				];
			}
			$group['location'] = [ 'mode' => 'specific_pages', 'page_refs' => $refs ];
		} elseif ( 'all' === ( $location['mode'] ?? 'all' ) ) {
			$group['location'] = [ 'mode' => 'all' ];
		} else {
			$group['location'] = [ 'mode' => 'invalid' ];
		}
		return $group;
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
