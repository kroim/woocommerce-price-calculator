<?php
/**
 * WooCommerce Measurement Price Calculator
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Measurement Price Calculator to newer
 * versions in the future. If you wish to customize WooCommerce Measurement Price Calculator for your
 * needs please refer to http://docs.woothemes.com/document/measurement-price-calculator/ for more information.
 *
 * @package   WC-Measurement-Price-Calculator/Compatibility
 * @author    SkyVerge
 * @copyright Copyright (c) 2012-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Measurement Price Calculator Compatibility Class
 *
 * @since 3.7.0
 */
class WC_Price_Calculator_Compatibility {


	/**
	 * Construct and initialize the class
	 *
	 * @since 3.7.0
	 */
	public function __construct() {

		// Catalog Visibility Options compatibility
		if ( wc_measurement_price_calculator()->is_plugin_active( 'woocommerce-catalog-visibility-options.php' ) ) {

			// add the pricing calculator and quantity input to products restricted by Catalog Visibility options
			add_action( 'catalog_visibility_after_alternate_add_to_cart_button', array( $this, 'catalog_visibility_options_pricing_calculator_quantity_input' ), 10 );
		}

		// Google Product Feed compatibility
		if ( wc_measurement_price_calculator()->is_plugin_active( 'woocommerce-gpf.php' ) ) {
			add_filter( 'woocommerce_gpf_feed_item', array( $this, 'google_product_feed_pricing_rules_price_adjustment' ) );
		}
	}


	/**
	 * Add the pricing calculator and quantity input if the user can view the price
	 *
	 * @since 3.7.0
	 */
	public function catalog_visibility_options_pricing_calculator_quantity_input() {
		global $product;

		// bail if the calculator is not enabled for this product
		if ( ! $product || ! WC_Price_Calculator_Product::calculator_enabled( $product ) ) {
			return;
		}

		// bail if current user can't view the price
		if ( class_exists( 'WC_Catalog_Restrictions_Filters' ) && ! WC_Catalog_Restrictions_Filters::instance()->user_can_view_price( $product ) ) {
			return;
		}

		// render pricing calculator
		wc_measurement_price_calculator()->get_product_page_instance()->render_price_calculator();

		// render quantity input
		if ( ! $product->is_sold_individually() ) {

			woocommerce_quantity_input( array(
				'min_value' => apply_filters( 'woocommerce_quantity_input_min', 1, $product ),
				'max_value' => apply_filters( 'woocommerce_quantity_input_max', $product->backorders_allowed() ? '' : $product->get_stock_quantity(), $product )
			) );
		}
	}


	/**
	 * Ensure Google Product Feed includes products with the pricing rules enabled
	 *
	 * @since 3.8.0
	 * @param object $feed_item
	 * @return object
	 */
	public function google_product_feed_pricing_rules_price_adjustment( $feed_item ) {

		$product = wc_get_product( $feed_item->ID );

		$price = $regular_price = $sale_price = '';

		if ( $product ) {

			$settings = new WC_Price_Calculator_Settings( $product );

			// user-defined calculator with pricing rules enabled (nothing needs to be changed for user-defined calculators with no pricing rules)
			if ( $settings->pricing_rules_enabled() ) {

				$price         = $settings->get_pricing_rules_maximum_price();
				$regular_price = $settings->get_pricing_rules_maximum_regular_price();
				$sale_price    = $settings->pricing_rules_is_on_sale() ? $settings->get_pricing_rules_maximum_sale_price() : '';
			}

			// quantity calculator with per unit pricing
			else if ( $settings->is_quantity_calculator_enabled() && WC_Price_Calculator_Product::pricing_per_unit_enabled( $product ) ) {

				$measurement = null;

				// for variable products we must synchronize price levels to our per unit price
				if ( $product->is_type( 'variable' ) ) {

					// synchronize to the price per unit pricing
					WC_Price_Calculator_Product::variable_product_sync( $product, $settings );

					// save the original price and remove the filter that we're currently within, to avoid an infinite loop
					$price         = $product->min_variation_price;
					$regular_price = $product->min_variation_regular_price;
					$sale_price    = $product->min_variation_sale_price;

					// restore the original values
					WC_Price_Calculator_Product::variable_product_unsync( $product, $settings );
				}

				// all ther product types
				else {

					$measurement = WC_Price_Calculator_Product::get_product_measurement( $product, $settings );
					$measurement->set_unit( $settings->get_pricing_unit() );

					if ( $measurement && $measurement->get_value() ) {

						// convert to price per unit
						$price         = $product->price         / $measurement->get_value();
						$regular_price = $product->regular_price / $measurement->get_value();
						$sale_price    = $product->sale_price    / $measurement->get_value();
					}
				}
			}

			// set the feed item prices if available
			if ( ! empty( $price ) ) {
				$feed_item->price_inc_tax = $product->get_price_excluding_tax( 1, $price );
				$feed_item->price_ex_tax  = $product->get_price_including_tax( 1, $price );
			}

			if ( ! empty( $regular_price ) ) {
				$feed_item->regular_price_ex_tax  = $product->get_price_excluding_tax( 1, $regular_price );
				$feed_item->regular_price_inc_tax = $product->get_price_including_tax( 1, $regular_price );
			}

			if ( ! empty( $sale_price ) ) {
				$feed_item->sale_price_ex_tax  = $product->get_price_excluding_tax( 1, $sale_price );
				$feed_item->sale_price_inc_tax = $product->get_price_including_tax( 1, $sale_price );
			}
		}

		return $feed_item;
	}


}
