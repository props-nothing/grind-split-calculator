<?php
/**
 * Shortcodes and frontend rendering.
 *
 * @package Grind_Split_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register and render shortcodes.
 */
class GSC_Shortcodes {
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

		add_shortcode( 'grind_calculator', array( $this, 'render_calculator' ) );
		add_shortcode( 'grind_wizard', array( $this, 'render_wizard' ) );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		wp_enqueue_style(
			'gsc-frontend',
			GSC_PLUGIN_URL . 'assets/css/grind-split-calculator.css',
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'gsc-frontend',
			GSC_PLUGIN_URL . 'assets/js/grind-split-calculator.js',
			array(),
			'1.0.0',
			true
		);

		wp_localize_script(
			'gsc-frontend',
			'gscData',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'cartUrl'           => wc_get_cart_url(),
				'attributeFormaat'  => GSC_Product_Service::ATTR_FORMAAT,
				'attributeQty'      => GSC_Product_Service::ATTR_HOEVEELHEID,
				'i18n'              => array(
					'needSelections'   => __( 'Selecteer eerst formaat en hoeveelheid.', 'grind-split-calculator' ),
					'invalidInput'     => __( 'Vul een geldig aantal m² in.', 'grind-split-calculator' ),
					'variationMissing' => __( 'Deze combinatie is niet beschikbaar.', 'grind-split-calculator' ),
					'calculationNote'  => __( 'De berekening is gebaseerd op een laagdikte van %s cm. Pas dit aan naar uw situatie.', 'grind-split-calculator' ),
					'addToCartLabel'   => __( 'Voeg %1$s %2$s toe aan winkelwagen', 'grind-split-calculator' ),
					'bagFallback'      => __( 'zakken', 'grind-split-calculator' ),
					'noFormats'        => __( 'Geen formaten gevonden voor dit product.', 'grind-split-calculator' ),
					'noQuantities'     => __( 'Geen hoeveelheden gevonden voor dit formaat.', 'grind-split-calculator' ),
					'noCategories'     => __( 'Geen categorieën gevonden.', 'grind-split-calculator' ),
					'selectCategory'   => __( 'Selecteer eerst een categorie.', 'grind-split-calculator' ),
					'noProducts'       => __( 'Geen variabele producten gevonden in deze categorie.', 'grind-split-calculator' ),
				),
			)
		);
	}

	/**
	 * Render product page calculator.
	 *
	 * @return string
	 */
	public function render_calculator() {
		$this->enqueue_assets();

		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return '';
		}

		$data = $this->product_service->get_calculator_data( $product_id );
		if ( empty( $data ) ) {
			return '<p>' . esc_html__( 'Deze calculator werkt alleen op variabele WooCommerce producten met hoeveelheid attributen.', 'grind-split-calculator' ) . '</p>';
		}

		$layer_thickness = $this->product_service->get_layer_thickness_for_product( $product_id );
		$has_formats = ! empty( $data['formats'] );

		ob_start();
		?>
		<div class="gsc-calculator" data-gsc-calculator data-config="<?php echo esc_attr( wp_json_encode( array(
			'productId'          => $data['product_id'],
			'formats'            => $data['formats'],
			'quantitiesByFormat' => $data['quantities_by_format'],
			'layerThickness'     => $layer_thickness,
			'hasFormats'         => $has_formats,
		) ) ); ?>">
			<h3><?php esc_html_e( 'Grind & Split Calculator', 'grind-split-calculator' ); ?></h3>

			<div class="gsc-field" <?php echo $has_formats ? '' : 'style="display: none;"'; ?>>
				<label><?php esc_html_e( 'Formaat', 'grind-split-calculator' ); ?></label>
				<select data-gsc-format></select>
			</div>

			<div class="gsc-field" style="display: none;">
				<label><?php esc_html_e( 'Hoeveelheid', 'grind-split-calculator' ); ?></label>
				<select data-gsc-quantity></select>
			</div>

			<div class="gsc-field">
				<label><?php esc_html_e( 'Aantal vierkante meter (m²)', 'grind-split-calculator' ); ?></label>
				<input type="number" min="0" step="0.1" data-gsc-sqm />
			</div>

			<div class="gsc-field">
				<label><?php esc_html_e( 'Laagdikte (cm)', 'grind-split-calculator' ); ?></label>
				<input type="number" min="0.1" step="0.1" data-gsc-thickness value="<?php echo esc_attr( $layer_thickness ); ?>" />
			</div>

			<div class="gsc-results" data-gsc-results>
				<p><?php esc_html_e( 'Aanbevolen verpakking:', 'grind-split-calculator' ); ?> <span class="gsc-result-value"><strong data-gsc-result-package>-</strong></span></p>
				<p><?php esc_html_e( 'Kubieke meter benodigd:', 'grind-split-calculator' ); ?> <span class="gsc-result-value"><strong data-gsc-result-volume>0</strong> m³</span></p>
				<p><?php esc_html_e( 'Aantal bigbags:', 'grind-split-calculator' ); ?> <span class="gsc-result-value"><strong data-gsc-result-bags>0</strong></span></p>
				<p class="gsc-note" data-gsc-note>
					<?php esc_html_e( 'De berekening is gebaseerd op een laagdikte van 5 cm. Pas dit aan naar uw situatie.', 'grind-split-calculator' ); ?>
				</p>
			</div>

			<button type="button" class="button alt" data-gsc-add-to-cart>
				<?php esc_html_e( 'Voeg toe aan winkelwagen', 'grind-split-calculator' ); ?>
			</button>
			<p class="gsc-message" data-gsc-message></p>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render wizard shortcode.
	 *
	 * @return string
	 */
	public function render_wizard() {
		$this->enqueue_assets();

		$wizard_data = $this->get_wizard_data();

		ob_start();
		?>
		<div class="gsc-wizard" data-gsc-wizard data-config="<?php echo esc_attr( wp_json_encode( $wizard_data ) ); ?>">
			<div class="gsc-progress">
				<div class="gsc-progress-step is-active" data-step="1"><?php esc_html_e( 'Stap 1', 'grind-split-calculator' ); ?></div>
				<div class="gsc-progress-step" data-step="2"><?php esc_html_e( 'Stap 2', 'grind-split-calculator' ); ?></div>
				<div class="gsc-progress-step" data-step="3"><?php esc_html_e( 'Stap 3', 'grind-split-calculator' ); ?></div>
				<div class="gsc-progress-step" data-step="4"><?php esc_html_e( 'Stap 4', 'grind-split-calculator' ); ?></div>
			</div>

			<section class="gsc-wizard-step is-active" data-gsc-step="1">
				<div class="gsc-wizard-header">
					<h3><?php esc_html_e( 'Stap 1 – Kies een categorie', 'grind-split-calculator' ); ?></h3>
				</div>
				<div class="gsc-category-grid" data-gsc-category-cards>
					<?php if ( empty( $wizard_data['categories'] ) ) : ?>
						<p class="gsc-empty"><?php esc_html_e( 'Geen categorieën gevonden. Controleer de plugininstellingen.', 'grind-split-calculator' ); ?></p>
					<?php endif; ?>
				</div>
			</section>

			<section class="gsc-wizard-step" data-gsc-step="2">
				<div class="gsc-wizard-header">
					<button type="button" class="gsc-back-btn-top" data-gsc-back-step="1" aria-label="<?php esc_attr_e( 'Terug', 'grind-split-calculator' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
					</button>
					<h3><?php esc_html_e( 'Stap 2 – Kies een product', 'grind-split-calculator' ); ?></h3>
				</div>
				<div class="gsc-product-grid" data-gsc-product-cards></div>
				<div class="gsc-actions">
					<button type="button" class="button" data-gsc-back-step="1"><?php esc_html_e( 'Terug', 'grind-split-calculator' ); ?></button>
				</div>
			</section>

			<section class="gsc-wizard-step" data-gsc-step="3">
				<div class="gsc-wizard-header">
					<button type="button" class="gsc-back-btn-top" data-gsc-back-step="2" aria-label="<?php esc_attr_e( 'Terug', 'grind-split-calculator' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
					</button>
					<h3><?php esc_html_e( 'Stap 3 – Kies formaat', 'grind-split-calculator' ); ?></h3>
				</div>
				<div class="gsc-format-grid" data-gsc-format-cards></div>
				<div class="gsc-actions">
					<button type="button" class="button" data-gsc-back-step="2"><?php esc_html_e( 'Terug', 'grind-split-calculator' ); ?></button>
				</div>
			</section>

			<section class="gsc-wizard-step" data-gsc-step="4">
				<div class="gsc-wizard-header">
					<button type="button" class="gsc-back-btn-top" data-gsc-back-step="3" aria-label="<?php esc_attr_e( 'Terug', 'grind-split-calculator' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
					</button>
					<h3><?php esc_html_e( 'Stap 4 – Berekening', 'grind-split-calculator' ); ?></h3>
				</div>
				<div class="gsc-quantity-options" data-gsc-quantity-options style="display: none;"></div>

				<div class="gsc-field">
					<label><?php esc_html_e( 'Aantal vierkante meter (m²)', 'grind-split-calculator' ); ?></label>
					<input type="number" min="0" step="0.1" data-gsc-sqm />
				</div>

				<div class="gsc-field">
					<label><?php esc_html_e( 'Laagdikte (cm)', 'grind-split-calculator' ); ?></label>
					<input type="number" min="0.1" step="0.1" data-gsc-thickness value="<?php echo esc_attr( GSC_Settings::get_default_layer_thickness() ); ?>" />
				</div>

				<div class="gsc-results" data-gsc-results>
					<p><?php esc_html_e( 'Aanbevolen verpakking:', 'grind-split-calculator' ); ?> <span class="gsc-result-value"><strong data-gsc-result-package>-</strong></span></p>
					<p><?php esc_html_e( 'Kubieke meter benodigd:', 'grind-split-calculator' ); ?> <span class="gsc-result-value"><strong data-gsc-result-volume>0</strong> m³</span></p>
					<p><?php esc_html_e( 'Aantal bigbags:', 'grind-split-calculator' ); ?> <span class="gsc-result-value"><strong data-gsc-result-bags>0</strong></span></p>
					<p class="gsc-note" data-gsc-note>
						<?php esc_html_e( 'De berekening is gebaseerd op een laagdikte van 5 cm. Pas dit aan naar uw situatie.', 'grind-split-calculator' ); ?>
					</p>
				</div>

				<div class="gsc-actions">
					<button type="button" class="button" data-gsc-back-step="3"><?php esc_html_e( 'Terug', 'grind-split-calculator' ); ?></button>
					<button type="button" class="button alt" data-gsc-add-to-cart>
						<?php esc_html_e( 'Voeg toe aan winkelwagen', 'grind-split-calculator' ); ?>
					</button>
				</div>
				<p class="gsc-message" data-gsc-message></p>
			</section>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Get wizard categories and products grouped by category.
	 *
	 * @return array<string, mixed>
	 */
	private function get_wizard_data() {
		$category_ids = GSC_Settings::get_wizard_category_ids();

		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		if ( ! empty( $category_ids ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $category_ids,
				),
			);
		}

		$products_query        = new WP_Query( $query_args );
		$categories_map        = array();
		$products_by_category  = array();

		if ( $products_query->have_posts() ) {
			foreach ( $products_query->posts as $post ) {
				$product = wc_get_product( $post->ID );
				if ( ! $product || ! is_a( $product, 'WC_Product_Variable' ) ) {
					continue;
				}

				$product_data = array(
					'id'          => $product->get_id(),
					'name'        => $product->get_name(),
					'description' => wp_strip_all_tags( $product->get_short_description() ),
					'image'       => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_single' ),
				);

				$product_terms = wp_get_post_terms( $product->get_id(), 'product_cat' );
				if ( empty( $product_terms ) || is_wp_error( $product_terms ) ) {
					continue;
				}

				foreach ( $product_terms as $term ) {
					$term_id = absint( $term->term_id );
					if ( ! empty( $category_ids ) && ! in_array( $term_id, $category_ids, true ) ) {
						continue;
					}

					if ( ! isset( $categories_map[ $term_id ] ) ) {
						$thumbnail_id = absint( get_term_meta( $term_id, 'thumbnail_id', true ) );
						$categories_map[ $term_id ] = array(
							'id'          => $term_id,
							'name'        => $term->name,
							'description' => wp_strip_all_tags( $term->description ),
							'image'       => $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'woocommerce_single' ) : '',
						);
					}

					if ( ! isset( $products_by_category[ $term_id ] ) ) {
						$products_by_category[ $term_id ] = array();
					}

					$products_by_category[ $term_id ][ $product->get_id() ] = $product_data;
				}
			}
		}

		wp_reset_postdata();

		$categories = array_values( $categories_map );
		usort(
			$categories,
			static function( $left, $right ) {
				return strcasecmp( $left['name'], $right['name'] );
			}
		);

		$products_serialized = array();
		foreach ( $products_by_category as $category_id => $products ) {
			$products_serialized[ (string) $category_id ] = array_values( $products );
		}

		return array(
			'categories'         => $categories,
			'productsByCategory' => $products_serialized,
		);
	}
}
