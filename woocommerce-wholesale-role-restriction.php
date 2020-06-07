<?php
/**
 * Plugin Name: WooCommerce Wholesale Role Restriction
 * Plugin URI: https://wptheming.com
 * Description: Excludes any customer with the "wholesale" role from using coupons.
 * Version: 1.0.0
 * Author: Devin Price
 * Author URI: https://wptheming.com
 *
 * WC requires at least: 3.5.0
 * WC tested up to: 4.0.0
 *
 * Copyright: © 2020 WP Theming
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class WC_Wholesale_Role_Restriction {

	/**
	 * Required WooCommerce Version.
	 *
	 * @access public
	 * @since  1.4.0
	 */
	public $required_woo = '3.5.0';

	/**
	 * Loads the plugin.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function __construct() {

		// Checks WooCommerce version.
		add_action( 'plugins_loaded', array( $this, 'load_plugin' ) );

		// Validates coupons during checkout.
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_coupons_after_checkout'), 1 );

	}

	/**
	 * Check requirements on activation.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function load_plugin() {
		// Check we're running the required version of WooCommerce.
		if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, $this->required_woo, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_compatibility_notice' ) );
			return false;
		}
	}

	/**
	 * Display a warning message if minimum version of WooCommerce check fails.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function woocommerce_compatibility_notice() {
		echo '<div class="error"><p>' . sprintf( __( '%1$s requires at least %2$s v%3$s in order to function. Please upgrade %2$s.', 'woocommerce-coupon-restrictions' ), 'WooCommerce Wholesale Role Restriction', 'WooCommerce', $this->required_woo ) . '</p></div>';
	}

	/**
	 * Validates the coupon during checkout.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function validate_coupons_after_checkout( $posted ) {

		if ( ! empty( WC()->cart->applied_coupons ) ) {

			// If no billing email is set, we'll default to empty string.
			// WooCommerce validation should catch this before we do.
			if ( ! isset( $posted['billing_email'] ) ) {
				$posted['billing_email'] = '';
			}

			foreach ( WC()->cart->applied_coupons as $code ) :

				$coupon = new WC_Coupon( $code );

				if ( $coupon->is_valid() ) {
					$email = strtolower( $posted['billing_email'] );

					$valid = $this->validate_role_restriction( $coupon, $email );

					if ( false === $valid ) {
						$msg = 'Sorry, coupons are not available for wholesale customers.';
						$this->remove_coupon( $code, $msg );
					}
				}

			endforeach;
		}
	}

	/**
	 * Validates role restrictions.
	 * Returns true (i.e. valid) if role is not restricted.
	 *
	 * @param string $email
	 * @return boolean
	 */
	public function validate_role_restriction( $email ) {
		
		// Returns an array with all the restricted roles.
		$restricted_roles = ['wholesale'];
		
		// Checks if there is an account associated with the $email.
		$user = get_user_by( 'email', $email );
		
		// If user account does not exist, coupon is invalid.
		if ( ! $user ) {
			return false;
		}
		
		$user_meta = get_userdata( $user->ID );
		$user_roles = $user_meta->roles;
		
		// If any the user roles do not match the restricted roles, coupon is invalid.
		if ( ! array_intersect( $user_roles, $restricted_roles ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Removes coupon and displays validation message.
	 *
	 * @param string $code
	 * @param string $msg
	 * @return void
	 */
	public function remove_coupon( $code, $msg ) {

		// Remove the coupon.
		WC()->cart->remove_coupon( $code );

		// Throw a notice to stop checkout.
		wc_add_notice( $msg, 'error' );

		// Flag totals for refresh.
		WC()->session->set( 'refresh_totals', true );
	}

}

new WC_Wholesale_Role_Restriction();