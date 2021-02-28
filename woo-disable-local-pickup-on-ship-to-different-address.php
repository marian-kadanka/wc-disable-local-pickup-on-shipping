<?php

/**
 * Plugin Name:       WooCommerce Disable Local Pickup on Ship to Different Address
 * Plugin URI:        https://wordpress.org/plugins/woo-disable-local-pickup-on-ship-to-different-address/
 * Description:       An extension that disables WooCommerce built-in Local Pickup shipment method on checkout when a customer chooses to ship to a different address
 * Version:           1.2
 * Author:            Marian Kadanka
 * Author URI:        https://kadanka.net/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wcdlpos
 * Domain Path:       /languages
 * GitHub Plugin URI: marian-kadanka/woo-disable-local-pickup-on-ship-to-different-address
 * WC tested up to:   5.0
 */

/**
 * WooCommerce Disable Local Pickup on Ship to Different Address
 * Copyright (C) 2017 Marian Kadanka. All rights reserved.

 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Disable_Local_Pickup_On_Shipping {

	// could be the default, but it may lead to bad user experience on non ajax-enabled checkouts, when Local Pickup won't be available based on those settings, no matter if customer unchecks the "ship to different address" option
	// apply_filters( 'woocommerce_ship_to_different_address_checked', get_option( 'woocommerce_ship_to_destination' ) === 'shipping' ? true : false );
	public $ship_to_different_address = false;

	function __construct() {

		add_action( 'wc_ajax_update_order_review', array( $this, 'hook_cart_shipping_packages' ), 1 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'checkout_update_s2da_flag' ) );
		add_filter( 'woocommerce_shipping_local_pickup_is_available', array( $this, 'is_local_pickup_available' ), 10, 2 );
		add_action( 'wc_ajax_checkout', array( $this, 'maybe_hook_package_rates' ), 1 );

	}

	function hook_cart_shipping_packages() {
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'package_add_s2da_var' ) );
	}

	/**
	 * Add variable indicating whether ship to different address is selected to the shipping package so that changing it forces a recalc
	 * Don't process multi-package cart shipments
	 */
	function package_add_s2da_var( $packages ) {

		if ( count( $packages ) === 1 ) {

			// only one iteration, but rather retain key=>value relation for compatibility
			foreach ( $packages as $package_index => $unused ) {
				$packages[ $package_index ]['wcdlpos_ship_to_different_address'] = $this->ship_to_different_address;
			}
		}

		return $packages;
	}

	/**
	 * Parse checkout form data and update the internal flag during an order review update event
	 */
	function checkout_update_s2da_flag( $post_data_str ) {
		$post_data = array();
		parse_str( $post_data_str, $post_data );

		$this->ship_to_different_address = isset( $post_data['ship_to_different_address'] );
	}

	/**
	 * Filter Local Pickup shipment method availability
	 */
	function is_local_pickup_available( $available, $package ) {
		return $available && ! $this->ship_to_different_address;
	}

	function maybe_hook_package_rates() {
		if ( isset( $_POST['ship_to_different_address'] ) ) {
			add_filter( 'woocommerce_package_rates', array( $this, 'filter_package_rates' ), 10, 2 );
		}
	}

	/**
	 * Filter package rates too, to fix "Invalid Payment Method" issue
	 */
	function filter_package_rates( $package_rates, $package ) {
		foreach ( $package_rates as $id => $unused ) {
			if ( substr( $id, 0, 12 ) === 'local_pickup' ) {
				unset( $package_rates[ $id ] );
			}
		}
		return $package_rates;
	}
}

new WC_Disable_Local_Pickup_On_Shipping();
