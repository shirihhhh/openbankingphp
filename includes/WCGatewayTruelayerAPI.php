<?php
/**
 * TrueLayer API
 *
 * Provides methods for interacting with TrueLayer API
 *
 * @category WCGatewayTruelayerAPI
 * @package  woocommerce-truelayer-gateway
 * @author   Robert Coster
 * @license  MIT
 * @link     https://github.com/signalfire/woocommerce-truelayer-gateway
 */

namespace Signalfire\Woocommerce\TrueLayer;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

use Signalfire\TruePayments\Credentials;
use Signalfire\TruePayments\Request;
use Signalfire\TruePayments\Auth;
use Signalfire\TruePayments\Payment;

/**
 * TrueLayer API
 *
 * Provides methods for interacting with TrueLayer API
 *
 * @category Class
 * @package  WCGatewayTrueLayerAPI
 * @author   Robert Coster
 * @license  MIT
 * @link     https://github.com/signalfire/woocommerce-truelayer-gateway
 */
class WCGatewayTrueLayerAPI {

	/**
	 * Get URL to callback to from payment
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Base URL.
	 * @param string $id  Plugin id.
	 *
	 * @return string
	 */
	public function get_redirect_uri( $url, $id ) {
		return $url . '/wc-api/' . $id;
	}

	/**
	 * Get URIs based on testmode of plugin
	 *
	 * @since 1.0.0
	 *
	 * @param bool $testmode Is the gateway in testmode.
	 *
	 * @return array
	 */
	public function get_urls( $testmode ) {
		return array(
			'auth'    => 'yes' === $testmode ?
				'https://auth.truelayer-sandbox.com' :
				'https://auth.truelayer.com',
			'payment' => 'yes' === $testmode ?
				'https://pay-api.truelayer-sandbox.com' :
				'https://pay-api.truelayer.com',
		);
	}

	/**
	 * Get credentials object for API
	 *
	 * @since 1.0.0
	 *
	 * @param string $client_id     TrueLayer Client ID.
	 * @param string $client_secret Truelayer Client Secret.
	 *
	 * @return Credentials
	 */
	public function get_credentials( $client_id, $client_secret ) {
		$credentials = new Credentials(
			$client_id,
			$client_secret
		);
		return $credentials;
	}

	/**
	 * Get Request
	 *
	 * @since 1.0.0
	 *
	 * @param string $uri Base URI to make request to.
	 *
	 * @return Request
	 */
	public function get_request( $uri ) {
		return new Request(
			array(
				'base_uri' => $uri,
				'timeout'  => 60,
			)
		);
	}

	/**
	 * Get Request for Auth
	 *
	 * @since 1.0.0
	 *
	 * @param bool $testmode Is API in testmode.
	 *
	 * @return Request
	 */
	public function get_auth_request( $testmode ) {
		$urls = $this->get_urls( $testmode );
		return $this->get_request( $urls['auth'] );
	}

	/**
	 * Get Request for payment
	 *
	 * @since 1.0.0
	 *
	 * @param bool $testmode Is API in testmode.
	 *
	 * @return Request
	 */
	public function get_payment_request( $testmode ) {
		$urls = $this->get_urls( $testmode );
		return $this->get_request( $urls['payment'] );
	}

	/**
	 * Get API auth object
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $testmode      Is API in testmode.
	 * @param string $client_id     Truelayer client_id.
	 * @param string $client_secret Truelayer client_secret.
	 *
	 * @return Auth
	 */
	public function get_auth( $testmode, $client_id, $client_secret ) {
		$request = $this->get_auth_request( $testmode );
		return new Auth( $request, $this->get_credentials( $client_id, $client_secret ) );
	}

	/**
	 * Get API token
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $testmode        Is API in testmode.
	 * @param string $client_id     Truelayer client_id.
	 * @param string $client_secret Truelayer client_secret.
	 *
	 * @return string
	 */
	public function get_token( $testmode, $client_id, $client_secret ) {
		$auth     = $this->get_auth( $testmode, $client_id, $client_secret );
		$response = $auth->getAccessToken();
		if ( ! isset( $response['error'] ) && isset( $response['body']['access_token'] ) ) {
			return $response['body']['access_token'];
		}
	}

	/**
	 * Create and return payment details
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $testmode Is gateway in testmode.
	 * @param string $token    API Token.
	 * @param array  $data     Payment data.
	 *
	 * @return array
	 */
	public function get_payment( $testmode, $token, $data ) {
		$request  = $this->get_payment_request( $testmode );
		$payment  = new Payment( $request, $token );
		$response = $payment->createPayment( $data );
		if ( ! isset( $response['error'] ) &&
			isset( $response['body']['results'][0]['auth_uri'] ) &&
			isset( $response['body']['results'][0]['simp_id'] ) ) {
			return array(
				'id'  => $response['body']['results'][0]['simp_id'],
				'uri' => $response['body']['results'][0]['auth_uri'],
			);
		}
	}

	/**
	 * Get the status of the payment
	 *
	 * @since 1.0
	 *
	 * @param bool   $testmode   Is gateway in testmode.
	 * @param string $token      API token to use.
	 * @param string $payment_id Payment ID to find status for.
	 *
	 * @return array
	 */
	public function get_payment_status( $testmode, $token, $payment_id ) {
		$request  = $this->get_payment_request( $testmode );
		$payment  = new Payment( $request, $token );
		$response = $payment->getPaymentStatus( $payment_id );
		if ( ! isset( $response['error'] ) && isset( $response['body']['results'][0]['status'] ) ) {
			return $response['body']['results'][0]['status'];
		}
	}
}
