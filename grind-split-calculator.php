<?php
/**
 * Plugin Name: Grind & Split Calculator
 * Description: WooCommerce calculator en keuzehulp wizard voor grind en split variabele producten.
 * Version: 1.0.0
 * Author: Your Company
 * Text Domain: grind-split-calculator
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'GSC_PLUGIN_FILE' ) ) {
	define( 'GSC_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'GSC_PLUGIN_PATH' ) ) {
	define( 'GSC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'GSC_PLUGIN_URL' ) ) {
	define( 'GSC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! class_exists( 'Grind_Split_Calculator' ) ) {
	require_once GSC_PLUGIN_PATH . 'includes/class-gsc-parser.php';
	require_once GSC_PLUGIN_PATH . 'includes/class-gsc-product-service.php';
	require_once GSC_PLUGIN_PATH . 'includes/class-gsc-settings.php';
	require_once GSC_PLUGIN_PATH . 'includes/class-gsc-ajax.php';
	require_once GSC_PLUGIN_PATH . 'includes/class-gsc-shortcodes.php';

	class Grind_Split_Calculator {
		/**
		 * Product service.
		 *
		 * @var GSC_Product_Service
		 */
		private $product_service;

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
			add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		}

		/**
		 * Load plugin translations.
		 *
		 * @return void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'grind-split-calculator', false, dirname( plugin_basename( GSC_PLUGIN_FILE ) ) . '/languages' );
		}

		/**
		 * Initialize plugin modules.
		 *
		 * @return void
		 */
		public function init() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}

			$this->product_service = new GSC_Product_Service();

			new GSC_Settings();
			new GSC_Ajax( $this->product_service );
			new GSC_Shortcodes( $this->product_service );
		}
	}
}

new Grind_Split_Calculator();
