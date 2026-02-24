<?php
/**
 * Quantity parser.
 *
 * @package Grind_Split_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parser helpers for pa_hoeveelheid values.
 */
class GSC_Parser {
	/**
	 * Parse quantity attribute value.
	 *
	 * Expected format example: 800 kg Bigbag 1m3.
	 *
	 * @param string $raw_value Raw attribute value.
	 * @return array<string, mixed>|null
	 */
	public static function parse_quantity_value( $raw_value ) {
		$raw_value = trim( (string) $raw_value );

		if ( '' === $raw_value ) {
			return null;
		}

		$pattern = '/^([\d\.,]+)\s*kg\s+(\w+)\s+([\d\.]+)m3$/i';

		if ( ! preg_match( $pattern, $raw_value, $matches ) ) {
			return null;
		}

		$weight = self::normalize_numeric_string( $matches[1] );
		$type   = sanitize_text_field( $matches[2] );
		$volume = self::normalize_numeric_string( $matches[3] );

		if ( $weight <= 0 || $volume <= 0 ) {
			return null;
		}

		return array(
			'raw'            => $raw_value,
			'weight_per_bag' => $weight,
			'bag_type'       => $type,
			'volume_per_bag' => $volume,
		);
	}

	/**
	 * Normalize localized decimal string.
	 *
	 * @param string $value Number value.
	 * @return float
	 */
	private static function normalize_numeric_string( $value ) {
		$value = str_replace( ' ', '', (string) $value );

		if ( false !== strpos( $value, ',' ) && false !== strpos( $value, '.' ) ) {
			$value = str_replace( '.', '', $value );
			$value = str_replace( ',', '.', $value );
		} elseif ( false !== strpos( $value, ',' ) ) {
			$value = str_replace( ',', '.', $value );
		}

		return (float) $value;
	}
}
