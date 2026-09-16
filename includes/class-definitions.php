<?php
namespace WEM\Content_Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Definitions {
	private Storage $storage;
	private Normalizer $normalizer;
	private Validator $validator;
	private ?array $cache = null;

	public function __construct( Storage $storage, Normalizer $normalizer, Validator $validator ) {
		$this->storage    = $storage;
		$this->normalizer = $normalizer;
		$this->validator  = $validator;
	}

	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$groups = $this->storage->all();

		/**
		 * Filter runtime field group definitions.
		 *
		 * This affects registration/meta boxes only and is never written back
		 * to wp_options. It is the intended integration point for future
		 * wem-content-model definitions.
		 */
		$groups = apply_filters( 'wem_cf_runtime_field_groups', $groups );
		$groups = is_array( $groups ) ? $groups : [];
		$groups = $this->normalizer->groups( $groups );

		$this->cache = $this->validator->validate_runtime_groups( $groups );
		return $this->cache;
	}

	public function for_post_type( string $post_type ): array {
		$matched = [];
		foreach ( $this->all() as $key => $group ) {
			if ( in_array( $post_type, (array) ( $group['post_types'] ?? [] ), true ) ) {
				$matched[ $key ] = $group;
			}
		}
		return $matched;
	}

	public function clear_cache(): void {
		$this->cache = null;
	}
}
