<?php
/**
 * TrueLayer Utils
 *
 * Provides utility methods
 *
 * @category WCGatewayTruelayerUtils
 * @package  woocommerce-truelayer-gateway
 * @author   Robert Coster
 * @license  MIT
 * @link     https://github.com/signalfire/woocommerce-truelayer-gateway
 */

namespace Signalfire\Woocommerce\TrueLayer;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * TrueLayer Utils
 *
 * Provides utility methods
 *
 * @category Class
 * @package  WCGatewayTrueLayerUtils
 * @author   Robert Coster
 * @license  MIT
 * @link     https://github.com/signalfire/woocommerce-truelayer-gateway
 */
class WCGatewayTrueLayerUtils {

	/**
	 * Get URI to redirect to based on webhook outcome
	 *
	 * @since 1.0.0
	 *
	 * @param string $status      Status of webhook.
	 * @param string $success_uri URI on success.
	 * @param string $pending_uri URI on pending.
	 *
	 * @return string
	 */
	public function get_webhook_redirect_uri( $status, $success_uri, $pending_uri ) {
		switch ( strtolower( $status ) ) {
			case 'success':
				return $success_uri;
			default:
				return $pending_uri;
		}
	}

	/**
	 * Get remitter reference
	 *
	 * @since 1.0.0
	 *
	 * @param string $remitter_reference Remitter reference string.
	 * @param object $order              The Order.
	 *
	 * @return string
	 */
	public function get_remitter_reference( $remitter_reference, $order ) {
		if ( strpos( $remitter_reference, '%s' ) !== false ) {
			return sprintf( $remitter_reference, $order->get_order_number() );
		}
		return $remitter_reference;
	}

}
