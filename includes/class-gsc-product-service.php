<?php
/**
 * Product service.
 *
 * @package Grind_Split_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product helper service for variable products.
 */
class GSC_Product_Service {
	/**
	 * Attribute taxonomy for format.
	 *
	 * @var string
	 */
	const ATTR_FORMAAT = 'pa_formaat';

	/**
	 * Attribute taxonomy for quantity.
	 *
	 * @var string
	 */
	const ATTR_HOEVEELHEID = 'pa_hoeveelheid';

	/**
	 * Get variable product by ID.
	 *
	 * @param int $product_id Product ID.
	 * @return WC_Product_Variable|null
	 */
	public function get_variable_product( $product_id ) {
		$product = wc_get_product( absint( $product_id ) );

		if ( ! $product || ! is_a( $product, 'WC_Product_Variable' ) ) {
			return null;
		}

		return $product;
	}

	/**
	 * Get unique formats from variable product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, string>>
	 */
	public function get_formats( $product_id ) {
		$product = $this->get_variable_product( $product_id );

		if ( ! $product ) {
			return array();
		}

		$attributes = $product->get_variation_attributes();
		$formats    = isset( $attributes[ self::ATTR_FORMAAT ] ) ? (array) $attributes[ self::ATTR_FORMAAT ] : array();

		$results = array();
		foreach ( $formats as $format_value ) {
			$slug = (string) $format_value;
			$name = $this->resolve_attribute_label( self::ATTR_FORMAAT, $slug );

			if ( '' === $slug || '' === $name ) {
				continue;
			}

			$results[ $slug ] = array(
				'slug' => $slug,
				'name' => $name,
			);
		}

		return array_values( $results );
	}

	/**
	 * Get quantities for selected format.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $format_value Selected format (slug or label).
	 * @return array<int, array<string, mixed>>
	 */
	public function get_quantities_by_format( $product_id, $format_value = '' ) {
		$product = $this->get_variable_product( $product_id );

		if ( ! $product ) {
			return array();
		}

		$format_slug = $format_value ? $this->normalize_attribute_value( self::ATTR_FORMAAT, $format_value ) : '';

		$variations = $product->get_available_variations();
		$results    = array();

		foreach ( $variations as $variation ) {
			$attributes = isset( $variation['attributes'] ) ? (array) $variation['attributes'] : array();
			$v_format   = isset( $attributes[ 'attribute_' . self::ATTR_FORMAAT ] ) ? (string) $attributes[ 'attribute_' . self::ATTR_FORMAAT ] : '';
			$v_qty      = isset( $attributes[ 'attribute_' . self::ATTR_HOEVEELHEID ] ) ? (string) $attributes[ 'attribute_' . self::ATTR_HOEVEELHEID ] : '';
			$v_id       = isset( $variation['variation_id'] ) ? absint( $variation['variation_id'] ) : 0;

			if ( '' === $v_qty || 0 === $v_id ) {
				continue;
			}

			if ( $format_slug !== '' && $v_format !== $format_slug ) {
				continue;
			}

			$qty_label = $this->resolve_attribute_label( self::ATTR_HOEVEELHEID, $v_qty );
			$parsed    = GSC_Parser::parse_quantity_value( $qty_label );

			if ( ! $parsed ) {
				continue;
			}

			$results[ $v_qty ] = array(
				'slug'            => $v_qty,
				'label'           => $qty_label,
				'variation_id'    => $v_id,
				'bag_type'        => $parsed['bag_type'],
				'weight_per_bag'  => $parsed['weight_per_bag'],
				'volume_per_bag'  => $parsed['volume_per_bag'],
			);
		}

		return array_values( $results );
	}

	/**
	 * Find variation ID by format and quantity value.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $format_value Format slug or label.
	 * @param string $quantity_value Quantity slug or label.
	 * @return int
	 */
	public function find_variation_id( $product_id, $format_value, $quantity_value ) {
		$product = $this->get_variable_product( $product_id );

		if ( ! $product ) {
			return 0;
		}

		$format_slug   = $format_value ? $this->normalize_attribute_value( self::ATTR_FORMAAT, $format_value ) : '';
		$quantity_slug = $this->normalize_attribute_value( self::ATTR_HOEVEELHEID, $quantity_value );

		if ( '' === $quantity_slug ) {
			return 0;
		}

		$variations = $product->get_available_variations();

		foreach ( $variations as $variation ) {
			$attributes = isset( $variation['attributes'] ) ? (array) $variation['attributes'] : array();
			$v_format   = isset( $attributes[ 'attribute_' . self::ATTR_FORMAAT ] ) ? (string) $attributes[ 'attribute_' . self::ATTR_FORMAAT ] : '';
			$v_qty      = isset( $attributes[ 'attribute_' . self::ATTR_HOEVEELHEID ] ) ? (string) $attributes[ 'attribute_' . self::ATTR_HOEVEELHEID ] : '';

			if ( $v_format === $format_slug && $v_qty === $quantity_slug ) {
				return isset( $variation['variation_id'] ) ? absint( $variation['variation_id'] ) : 0;
			}
		}

		return 0;
	}

	/**
	 * Get product data for calculator shortcode.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public function get_calculator_data( $product_id ) {
		$product = $this->get_variable_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		$formats = $this->get_formats( $product_id );
		$quantities_by_format = array();

		if ( empty( $formats ) ) {
			// Product has no formats, just get quantities
			$quantities = $this->get_quantities_by_format( $product_id, '' );
			if ( empty( $quantities ) ) {
				return array();
			}
			$quantities_by_format[''] = $quantities;
		} else {
			foreach ( $formats as $format ) {
				$quantities_by_format[ $format['slug'] ] = $this->get_quantities_by_format( $product_id, $format['slug'] );
			}
		}

		return array(
			'product_id'            => $product->get_id(),
			'product_name'          => $product->get_name(),
			'formats'               => $formats,
			'quantities_by_format'  => $quantities_by_format,
		);
	}

	/**
	 * Get layer thickness with fallback.
	 *
	 * @param int $product_id Product ID.
	 * @return float
	 */
	public function get_layer_thickness_for_product( $product_id ) {
		$product_id = absint( $product_id );
		$meta_value = get_post_meta( $product_id, '_grind_layer_thickness', true );

		if ( '' !== $meta_value && is_numeric( $meta_value ) ) {
			$value = (float) $meta_value;
			if ( $value > 0 ) {
				return $value;
			}
		}

		$default = GSC_Settings::get_default_layer_thickness();
		return $default > 0 ? $default : 5.0;
	}

	/**
	 * Normalize attribute value to slug.
	 *
	 * @param string $taxonomy Attribute taxonomy.
	 * @param string $value Raw value.
	 * @return string
	 */
	private function normalize_attribute_value( $taxonomy, $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$term_by_slug = get_term_by( 'slug', $value, $taxonomy );
		if ( $term_by_slug && ! is_wp_error( $term_by_slug ) ) {
			return (string) $term_by_slug->slug;
		}

		$term_by_name = get_term_by( 'name', $value, $taxonomy );
		if ( $term_by_name && ! is_wp_error( $term_by_name ) ) {
			return (string) $term_by_name->slug;
		}

		return sanitize_title( $value );
	}

	/**
	 * Resolve attribute label from term slug.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $value Slug or text.
	 * @return string
	 */
	private function resolve_attribute_label( $taxonomy, $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$term = get_term_by( 'slug', $value, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			return (string) $term->name;
		}

		$term = get_term_by( 'name', $value, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			return (string) $term->name;
		}

		return $value;
	}
}
