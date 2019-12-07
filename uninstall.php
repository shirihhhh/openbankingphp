<?php
/**
 * TrueLayer Payment Gateway
 *
 * Uninstalls plugin
 *
 * @package  woocommerce-truelayer-gateway
 * @author   Robert Coster
 * @license  MIT
 * @link     https://github.com/signalfire/woocommerce-truelayer-gateway
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	return;
}

delete_option( 'woocommerce_truelayer_settings' );
