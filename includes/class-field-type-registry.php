<?php
namespace WEM\Content_Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Field_Type_Registry {
	/** @var array<string, Field_Type> */
	private array $types = [];

	public function register( Field_Type $type ): void {
		$this->types[ $type->get_type() ] = $type;
	}

	public function has( string $type ): bool {
		return isset( $this->types[ $type ] );
	}

	public function get( string $type ): ?Field_Type {
		return $this->types[ $type ] ?? null;
	}

	public function all(): array {
		return $this->types;
	}

	public function labels(): array {
		$labels = [];
		foreach ( $this->types as $key => $type ) {
			$labels[ $key ] = $type->get_label();
		}
		return $labels;
	}

	public function settings_schemas(): array {
		$schemas = [];
		foreach ( $this->types as $key => $type ) {
			$schemas[ $key ] = $type->get_settings_schema();
		}
		return $schemas;
	}
}
