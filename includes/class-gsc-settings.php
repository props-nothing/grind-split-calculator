<?php
/**
 * Admin settings.
 *
 * @package Grind_Split_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin options page.
 */
class GSC_Settings {
	/**
	 * Option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'gsc_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register submenu under WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Grind Calculator', 'grind-split-calculator' ),
			__( 'Grind Calculator', 'grind-split-calculator' ),
			'manage_woocommerce',
			'gsc-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register options and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'gsc_settings_group',
			self::OPTION_KEY,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'gsc_main_section',
			__( 'Instellingen', 'grind-split-calculator' ),
			'__return_false',
			'gsc-settings'
		);

		add_settings_field(
			'default_layer_thickness',
			__( 'Standaard laagdikte (cm)', 'grind-split-calculator' ),
			array( $this, 'render_default_thickness_field' ),
			'gsc-settings',
			'gsc_main_section'
		);

		add_settings_field(
			'wizard_category_ids',
			__( 'Categorie-ID\'s voor wizard', 'grind-split-calculator' ),
			array( $this, 'render_category_ids_field' ),
			'gsc-settings',
			'gsc_main_section'
		);

		add_settings_field(
			'attribute_reference',
			__( 'Beschikbare attribuutwaarden (referentie)', 'grind-split-calculator' ),
			array( $this, 'render_attribute_reference_field' ),
			'gsc-settings',
			'gsc_main_section'
		);
	}

	/**
	 * Sanitize option values.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ) {
		$settings = self::get_settings();

		$thickness = isset( $input['default_layer_thickness'] ) ? (float) $input['default_layer_thickness'] : $settings['default_layer_thickness'];
		if ( $thickness <= 0 ) {
			$thickness = 5;
		}

		$category_ids_raw = isset( $input['wizard_category_ids'] ) ? (string) $input['wizard_category_ids'] : '';
		$category_ids     = $this->sanitize_csv_ids( $category_ids_raw );

		return array(
			'default_layer_thickness' => $thickness,
			'wizard_category_ids'     => implode( ',', $category_ids ),
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Grind & Split Calculator', 'grind-split-calculator' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'gsc_settings_group' );
				do_settings_sections( 'gsc-settings' );
				submit_button();
				?>
			</form>
			<p>
				<?php esc_html_e( 'Attribuutwaarden worden altijd live uit WooCommerce productattributen gelezen en niet hardcoded opgeslagen in deze plugin.', 'grind-split-calculator' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render default layer thickness field.
	 *
	 * @return void
	 */
	public function render_default_thickness_field() {
		$settings = self::get_settings();
		?>
		<input
			type="number"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_layer_thickness]"
			value="<?php echo esc_attr( $settings['default_layer_thickness'] ); ?>"
			min="0.1"
			step="0.1"
		/>
		<?php
	}

	/**
	 * Render category IDs field.
	 *
	 * @return void
	 */
	public function render_category_ids_field() {
		$settings = self::get_settings();
		?>
		<input
			type="text"
			class="regular-text"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>[wizard_category_ids]"
			value="<?php echo esc_attr( $settings['wizard_category_ids'] ); ?>"
			placeholder="12,34,56"
		/>
		<p class="description"><?php esc_html_e( 'Voer categorie-ID\'s in, gescheiden door komma\'s.', 'grind-split-calculator' ); ?></p>
		<?php
	}

	/**
	 * Render attribute reference values.
	 *
	 * @return void
	 */
	public function render_attribute_reference_field() {
		$format_terms    = get_terms(
			array(
				'taxonomy'   => 'pa_formaat',
				'hide_empty' => false,
			)
		);
		$quantity_terms  = get_terms(
			array(
				'taxonomy'   => 'pa_hoeveelheid',
				'hide_empty' => false,
			)
		);
		?>
		<p class="description">
			<?php esc_html_e( 'Deze lijst is alleen ter referentie en wordt live uit WooCommerce attributen gelezen. De plugin hardcodeert geen productwaarden.', 'grind-split-calculator' ); ?>
		</p>
		<p><strong><?php esc_html_e( 'pa_formaat', 'grind-split-calculator' ); ?></strong>:</p>
		<p>
			<?php
			if ( ! empty( $format_terms ) && ! is_wp_error( $format_terms ) ) {
				echo esc_html( implode( ', ', wp_list_pluck( $format_terms, 'name' ) ) );
			} else {
				esc_html_e( 'Geen waarden gevonden.', 'grind-split-calculator' );
			}
			?>
		</p>
		<p><strong><?php esc_html_e( 'pa_hoeveelheid', 'grind-split-calculator' ); ?></strong>:</p>
		<p>
			<?php
			if ( ! empty( $quantity_terms ) && ! is_wp_error( $quantity_terms ) ) {
				echo esc_html( implode( ', ', wp_list_pluck( $quantity_terms, 'name' ) ) );
			} else {
				esc_html_e( 'Geen waarden gevonden.', 'grind-split-calculator' );
			}
			?>
		</p>
		<?php
	}

	/**
	 * Get full settings with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		$defaults = array(
			'default_layer_thickness' => 5,
			'wizard_category_ids'     => '',
		);

		$settings = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Get default layer thickness.
	 *
	 * @return float
	 */
	public static function get_default_layer_thickness() {
		$settings = self::get_settings();
		$value    = isset( $settings['default_layer_thickness'] ) ? (float) $settings['default_layer_thickness'] : 5.0;
		return $value > 0 ? $value : 5.0;
	}

	/**
	 * Get configured wizard category IDs.
	 *
	 * @return array<int>
	 */
	public static function get_wizard_category_ids() {
		$settings = self::get_settings();
		$raw      = isset( $settings['wizard_category_ids'] ) ? (string) $settings['wizard_category_ids'] : '';
		$ids      = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );

		return array_values( array_filter( $ids ) );
	}

	/**
	 * Sanitize CSV ID list.
	 *
	 * @param string $raw Raw list.
	 * @return array<int>
	 */
	private function sanitize_csv_ids( $raw ) {
		$parts = array_map( 'trim', explode( ',', (string) $raw ) );
		$ids   = array();

		foreach ( $parts as $part ) {
			$id = absint( $part );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}
}
