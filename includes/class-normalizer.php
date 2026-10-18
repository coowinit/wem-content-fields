<?php
namespace WEM\Content_Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Normalizer {
	/**
	 * Normalize a raw field group into the stable Schema v1 shape.
	 * This method intentionally does not decide whether values are valid.
	 */
	public function group( array $input ): array {
		$fields = [];
		foreach ( (array) ( $input['fields'] ?? [] ) as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$fields[] = $this->field( $field );
		}

		$post_types = array_values(
			array_filter(
				array_map(
					function ( $value ): string {
						return trim( $this->scalar_string( $value ) );
					},
					(array) ( $input['post_types'] ?? [] )
				),
				static function ( $value ): bool {
					return '' !== $value;
				}
			)
		);

		$has_location = isset( $input['location'] ) && is_array( $input['location'] );
		$location = $has_location ? $input['location'] : [];
		$location_mode = $has_location ? $this->scalar_string( $location['mode'] ?? '' ) : 'all';
		if ( ! in_array( $location_mode, [ 'all', 'specific_pages' ], true ) ) {
			$location_mode = $has_location ? 'invalid' : 'all';
		}
		$page_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $location['page_ids'] ?? [] ) ) ) ) );

		return [
			'key'         => trim( $this->scalar_string( $input['key'] ?? '' ) ),
			'title'       => trim( $this->scalar_string( $input['title'] ?? '' ) ),
			'description' => $this->scalar_string( $input['description'] ?? '' ),
			'post_types'  => array_values( array_unique( $post_types ) ),
			'location'    => [
				'mode'     => $location_mode,
				'page_ids' => $page_ids,
			],
			'fields'      => $fields,
		];
	}

	public function field( array $input ): array {
		$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : [];

		return [
			'key'           => trim( $this->scalar_string( $input['key'] ?? '' ) ),
			'_original_key' => trim( $this->scalar_string( $input['_original_key'] ?? '' ) ),
			'label'         => trim( $this->scalar_string( $input['label'] ?? '' ) ),
			'type'          => trim( $this->scalar_string( $input['type'] ?? '' ) ),
			'meta_key'      => trim( $this->scalar_string( $input['meta_key'] ?? '' ) ),
			'description'   => $this->scalar_string( $input['description'] ?? '' ),
			'show_in_rest' => $this->to_bool( $input['show_in_rest'] ?? false ),
			'settings'     => $settings,
		];
	}

	public function groups( array $groups ): array {
		$normalized = [];
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$item = $this->group( $group );
			if ( '' !== $item['key'] ) {
				$normalized[ $item['key'] ] = $item;
			}
		}
		return $normalized;
	}

	private function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( ! is_scalar( $value ) && null !== $value ) {
			return false;
		}

		return in_array( $value, [ 1, '1', 'yes', 'true', 'on' ], true );
	}

	private function scalar_string( $value ): string {
		return is_scalar( $value ) || null === $value ? (string) $value : '';
	}
}
