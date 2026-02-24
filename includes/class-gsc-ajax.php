<?php
/**
 * AJAX handlers.
 *
 * @package Grind_Split_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle AJAX actions.
 */
class GSC_Ajax {
	/**
	 * Product service.
	 *
	 * @var GSC_Product_Service
	 */
	private $product_service;

	/**
	 * Constructor.
	 *
	 * @param GSC_Product_Service $product_service Product service.
	 */
	public function __construct( $product_service ) {
		$this->product_service = $product_service;

		add_action( 'wp_ajax_grind_get_formats', array( $this, 'get_formats' ) );
		add_action( 'wp_ajax_nopriv_grind_get_formats', array( $this, 'get_formats' ) );
		add_action( 'wp_ajax_grind_get_quantities', array( $this, 'get_quantities' ) );
		add_action( 'wp_ajax_nopriv_grind_get_quantities', array( $this, 'get_quantities' ) );
		add_action( 'wp_ajax_grind_get_variation_id', array( $this, 'get_variation_id' ) );
		add_action( 'wp_ajax_nopriv_grind_get_variation_id', array( $this, 'get_variation_id' ) );
	}

	/**
	 * Return formats for product.
	 *
	 * @return void
	 */
	public function get_formats() {
		$this->verify_nonce();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$formats    = $this->product_service->get_formats( $product_id );

		wp_send_json_success(
			array(
				'formats'          => $formats,
				'layer_thickness'  => $this->product_service->get_layer_thickness_for_product( $product_id ),
			)
		);
	}

	/**
	 * Return quantities for product + format.
	 *
	 * @return void
	 */
	public function get_quantities() {
		$this->verify_nonce();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$format     = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : '';
		$quantities = $this->product_service->get_quantities_by_format( $product_id, $format );

		wp_send_json_success(
			array(
				'quantities'      => $quantities,
				'layer_thickness' => $this->product_service->get_layer_thickness_for_product( $product_id ),
			)
		);
	}

	/**
	 * Return variation ID for product + format + quantity.
	 *
	 * @return void
	 */
	public function get_variation_id() {
		$this->verify_nonce();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$format     = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : '';
		$quantity   = isset( $_POST['quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['quantity'] ) ) : '';

		$variation_id = $this->product_service->find_variation_id( $product_id, $format, $quantity );

		if ( $variation_id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Geen geldige variatie gevonden.', 'grind-split-calculator' ),
				),
				404
			);
		}

		wp_send_json_success(
			array(
				'variation_id' => $variation_id,
			)
		);
	}

	/**
	 * Verify nonce.
	 *
	 * @return void
	 */
	private function verify_nonce() {
		$valid = check_ajax_referer( 'gsc_nonce', 'nonce', false );
		if ( ! $valid ) {
			wp_send_json_error(
				array(
					'message' => __( 'Ongeldige beveiligingstoken.', 'grind-split-calculator' ),
				),
				403
			);
		}
	}
}
