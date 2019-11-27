<?php
/**
 * Plugin Name: WooCommerce TrueLayer Gateway
 * Plugin URI: https://github.com/signalfire/woocommerce-truelayer-gateway
 * Description: Receive payments using the TrueLayer OpenBanking API
 * Author: Robert Coster
 * Author URI: https://signalfire.co.uk
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;
/**
 * Initialize the gateway.
 * @since 1.0.0
 */
function woocommerce_truelayer_init()
{
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	define( 'WC_GATEWAY_TRUELAYER_VERSION', '1.0.0' );

	require_once( plugin_basename( 'includes/class-wc-gateway-truelayer.php' ) );
	#require_once( plugin_basename( 'includes/class-wc-gateway-payfast-privacy.php' ) );
	load_plugin_textdomain( 'woocommerce-truelayer-gateway', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_truelayer_add_gateway' );
}

add_action( 'plugins_loaded', 'woocommerce_truelayer_init', 0 );

/**
 * Add plugin links
 * @since 1.0.0
 */
function woocommerce_truelayer_plugin_links( $links )
{
	$settings_url = add_query_arg(
		[
			'page' => 'wc-settings',
			'tab' => 'checkout',
			'section' => 'wc_gateway_truelayer',
        ],
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
 * @since 1.0.0
 */
function woocommerce_truelayer_add_gateway( $methods )
{
	$methods[] = 'WC_Gateway_TrueLayer';
	return $methods;
}
