<?php
namespace WEM\Content_Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Storage {
	public const OPTION_GROUPS         = 'wem_cf_field_groups';
	public const OPTION_SCHEMA_VERSION = 'wem_cf_schema_version';

	public function all(): array {
		$groups = get_option( self::OPTION_GROUPS, [] );
		return is_array( $groups ) ? $groups : [];
	}

	public function get( string $key ): ?array {
		$groups = $this->all();
		return isset( $groups[ $key ] ) && is_array( $groups[ $key ] ) ? $groups[ $key ] : null;
	}

	public function exists( string $key ): bool {
		$groups = $this->all();
		return isset( $groups[ $key ] );
	}

	public function save( array $group ): bool {
		$key = isset( $group['key'] ) ? (string) $group['key'] : '';
		if ( '' === $key ) {
			return false;
		}

		$groups         = $this->all();
		$groups[ $key ] = $group;

		return update_option( self::OPTION_GROUPS, $groups, false );
	}

	/**
	 * Add multiple groups atomically without overwriting existing keys.
	 */
	public function add_many( array $new_groups ): bool {
		if ( empty( $new_groups ) ) {
			return false;
		}

		$groups = $this->all();
		foreach ( $new_groups as $key => $group ) {
			$key = sanitize_key( is_scalar( $key ) ? (string) $key : '' );
			if ( '' === $key || isset( $groups[ $key ] ) || ! is_array( $group ) ) {
				return false;
			}
		}

		foreach ( $new_groups as $key => $group ) {
			$groups[ (string) $key ] = $group;
		}

		return update_option( self::OPTION_GROUPS, $groups, false );
	}

	public function delete( string $key ): bool {
		$groups = $this->all();
		if ( ! isset( $groups[ $key ] ) ) {
			return false;
		}

		unset( $groups[ $key ] );
		return update_option( self::OPTION_GROUPS, $groups, false );
	}

	public function schema_version(): int {
		return (int) get_option( self::OPTION_SCHEMA_VERSION, WEM_CF_SCHEMA_VERSION );
	}
}
