<?php
/**
 * Plugin Name: WooCommerce TrueLayer Gateway
 * Plugin URI: https://github.com/signalfire/woocommerce-truelayer-gateway
 * Description: Receive payments using the TrueLayer OpenBanking API
 * Author: Robert Coster
 * Author URI: https://signalfire.co.uk
 * Version: 1.0.0
 *
 * @package  woocommerce-truelayer-gateway
 * @author   Robert Coster
 * @license  MIT
 * @link     https://github.com/signalfire/woocommerce-truelayer-gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Include vendor packages
 */
require_once 'vendor/autoload.php';

/**
 * Initialize the gateway
 *
 * @since 1.0.0
 */
function woocommerce_truelayer_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) || ! class_exists( 'WC_Abstract_Privacy' ) ) {
		return;
	}

	define( 'WC_GATEWAY_TRUELAYER_VERSION', '1.0.0' );

	define( 'WC_GATEWAY_TRUELAYER_NAME', 'woocommerce-truelayer-gateway' );

	new Signalfire\Woocommerce\TrueLayer\WCGatewayTrueLayerPrivacy();

	load_plugin_textdomain( 'woocommerce-truelayer-gateway', false, trailingslashit( WC_GATEWAY_TRUELAYER_NAME ) );

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_truelayer_add_gateway' );
}

add_action( 'plugins_loaded', 'woocommerce_truelayer_init', 0 );

/**
 * Add plugin links
 *
 * @since 1.0.0
 *
 * @param array $links Array of links.
 */
function woocommerce_truelayer_plugin_links( $links ) {
	$settings_url = add_query_arg(
		array(
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => 'wc_gateway_truelayer',
		),
		admin_url( 'admin.php' )
	);

	$plugin_links = array(
		'<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'woocommerce-truelayer-gateway' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woocommerce_truelayer_plugin_links' );

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 *
 * @param array $methods Existing payment methods.
 */
function woocommerce_truelayer_add_gateway( $methods ) {
	$methods[] = 'Signalfire\Woocommerce\TrueLayer\WCGatewayTrueLayer';
	return $methods;
}
