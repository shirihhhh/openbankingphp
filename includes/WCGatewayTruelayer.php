<?php
/**
 * TrueLayer Payment Gateway
 *
 * Provides a TrueLayer Payment Gateway.
 *
 * @category WC_Gateway_TrueLayer
 * @package  woocommerce-truelayer-gateway
 * @author   Robert Coster
 * @license  MIT
 * @link     https://github.com/signalfire/woocommerce-truelayer-gateway
 */

namespace Signalfire\Woocommerce\TrueLayer;

use Exception;

use WC_Admin_Settings;
use WC_Payment_Gateway;

use Signalfire\TruePayments\Credentials;
use Signalfire\TruePayments\Request;
use Signalfire\TruePayments\Auth;
use Signalfire\TruePayments\Payment;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * TrueLayer Payment Gateway
 *
 * Provides a TrueLayer Payment Gateway.
 *
 * @category Class
 * @package  WC_Gateway_TrueLayer
 * @author   Robert Coster
 * @license  MIT
 * @link     https://github.com/signalfire/woocommerce-truelayer-gateway
 */
class WCGatewayTrueLayer extends WC_Payment_Gateway {

	/**
	 * Version
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->version              = WC_GATEWAY_TRUELAYER_VERSION;
		$this->id                   = 'truelayer';
		$this->icon                 = WP_PLUGIN_URL . '/' . WC_GATEWAY_TRUELAYER_NAME . '/assets/images/icon.png';
		$this->has_fields           = false;
		$this->method_title         = __( 'TrueLayer Gateway', 'woocommerce-truelayer-gateway' );
		$this->method_description   = __( 'Take payments using OpenBanking via TrueLayer', 'woocommerce-truelayer-gateway' );
		$this->available_countries  = array( 'GB' );
		$this->available_currencies = (array) apply_filters( 'woocommerce_gateway_truelayer_available_currencies', array( 'GBP' ) );

		$this->supports = array(
			'products',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled                    = $this->get_option( 'enabled' );
		$this->title                      = $this->get_option( 'title' );
		$this->description                = $this->get_option( 'description' );
		$this->testmode                   = $this->get_option( 'testmode' );
		$this->currency                   = $this->get_option( 'currency' );
		$this->remitter_reference         = $this->get_option( 'remitter_reference' );
		$this->client_id                  = $this->get_option( 'client_id' );
		$this->client_secret              = $this->get_option( 'client_secret' );
		$this->beneficiary_name           = $this->get_option( 'beneficiary_name' );
		$this->beneficiary_sort_code      = $this->get_option( 'beneficiary_sort_code' );
		$this->beneficiary_account_number = $this->get_option( 'beneficiary_account_number' );
		$this->beneficiary_reference      = $this->get_option( 'beneficiary_reference' );
		$this->success_uri                = $this->get_option( 'success_uri' );
		$this->pending_uri                = $this->get_option( 'pending_uri' );

		add_action( 'woocommerce_api_truelayer', array( $this, 'webhook' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Create fields for plugin
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                    => array(
				'type'        => 'checkbox',
				'title'       => __( 'Enable/Disable', 'woocommerce-truelayer-gateway' ),
				'label'       => __( 'Enable TrueLayer Gateway', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'Enable or disable the ability to take payments via TrueLayer', 'woocommerce-truelayer-gateway' ),
				'default'     => 'no',
			),
			'title'                      => array(
				'type'        => 'text',
				'title'       => __( 'Title', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-truelayer-gateway' ),
				'default'     => __( 'OpenBanking', 'woocommerce-truelayer-gateway' ),
				'desc_tip'    => true,
			),
			'description'                => array(
				'type'        => 'textarea',
				'title'       => __( 'Description', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-truelayer-gateway' ),
				'default'     => __( 'Pay using OpenBanking via TrueLayer', 'woocommerce-truelayer-gateway' ),
			),
			'testmode'                   => array(
				'type'        => 'checkbox',
				'title'       => __( 'Test mode', 'woocommerce-truelayer-gateway' ),
				'label'       => __( 'Enable Test Mode', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'Place the payment gateway in test mode using test client keys.', 'woocommerce-truelayer-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'currency'                   => array(
				'type'        => 'select',
				'title'       => __( 'Currency', 'woocommerce-truelayer-gateway' ),
				'label'       => __( 'Currency', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'Choose currency to transact in', 'woocommerce-truelayer-gateway' ),
				'default'     => 'GBP',
				'options'     => array(
					'GBP' => 'Pound Sterling',
				),
			),
			'remitter_reference'         => array(
				'type'        => 'text',
				'title'       => __( 'Remitter Reference', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'Enter a reference to uniquely identify this transaction. Include string placeholder once to include Order ID', 'woocommerce-truelayer-gateway' ),
			),
			'client_id'                  => array(
				'type'        => 'password',
				'title'       => __( 'Client ID', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'Enter client id from TrueLayer', 'woocommerce-truelayer-gateway' ),
			),
			'client_secret'              => array(
				'type'        => 'password',
				'title'       => __( 'Client Secret', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'Enter client secret from TrueLayer', 'woocommerce-truelayer-gateway' ),
			),
			'beneficiary_name'           => array(
				'type'        => 'text',
				'title'       => __( 'Beneficiary Name', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'Enter beneficiary bank name', 'woocommerce-truelayer-gateway' ),
			),
			'beneficiary_sort_code'      => array(
				'type'        => 'text',
				'title'       => __( 'Beneficiary Sort Code', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'Enter beneficiary sort code (exclude -)', 'woocommerce-truelayer-gateway' ),
			),
			'beneficiary_account_number' => array(
				'type'        => 'text',
				'title'       => __( 'Beneficiary Account Number', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'Enter beneficiary account number', 'woocommerce-truelayer-gateway' ),
			),
			'beneficiary_reference'      => array(
				'type'        => 'text',
				'title'       => __( 'Beneficiary Reference', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'Enter a reference to be displayed on payees bank statement.', 'woocommerce-truelayer-gateway' ),
			),
			'success_uri'                => array(
				'type'        => 'text',
				'title'       => __( 'Successful Redirect URL', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'Page to redirect to on success', 'woocommerce-truelayer-gateway' ),
			),
			'pending_uri'                => array(
				'type'        => 'text',
				'title'       => __( 'Pending Redirect URL', 'woocommerce-truelayer-gateway' ),
				'description' => __( 'Page to redirect to on pending', 'woocommerce-truelayer-gateway' ),
			),
		);
	}

	/**
	 * Validate text field
	 *
	 * @since 1.0.0
	 *
	 * @throws Exception Exception if validation fails.
	 *
	 * @param string $key   Field key.
	 * @param string $value Field value.
	 *
	 * @return mixed
	 */
	public function validate_text_field( $key, $value ) {
		$message = false;

		switch ( strtolower( $key ) ) {
			case 'title':
				if ( strlen( $value ) <= 3 || strlen( $value ) > 15 ) {
					$message = __( 'Title must be a min 4, max 15 characters', 'woocommerce-truelayer-gateway' );
				}
				break;
			case 'remitter_reference':
				if ( strlen( $value ) <= 3 || strlen( $value ) > 20 ) {
					$message = __( 'Remitter reference must be a min of 4, max 20 characters', 'woocommerce-truelayer-gateway' );
				}
				if ( substr_count( $value, '%s' ) > 1 ) {
					$message = __( 'Remitter reference can include only 1 string placeholder', 'woocommerce-truelayer-gateway' );
				}
				break;
			case 'beneficiary_name':
				if ( strlen( $value ) <= 3 || strlen( $value ) > 40 ) {
					$message = __( 'Beneficiary name must be a min 4, max 40 characters', 'woocommerce-truelayer-gateway' );
				}
				break;
			case 'beneficiary_sort_code':
				if ( ! preg_match( '/^\d{6}$/', $value ) ) {
					$message = __( 'Beneficiary sort code must be 6 digits (no dashes)', 'woocommerce-truelayer-gateway' );
				}
				break;
			case 'beneficiary_account_number':
				if ( ! preg_match( '/^\d{8}$/', $value ) ) {
					$message = __( 'Beneficiary account number must be 8 digits', 'woocommerce-truelayer-gateway' );
				}
				break;
			case 'beneficiary_reference':
				if ( strlen( $value ) <= 3 || strlen( $value ) > 18 ) {
					$message = __( 'Beneficiary reference must be a min 4, max 18 characters', 'woocommerce-truelayer-gateway' );
				}
				break;
			case 'success_uri':
				if ( ! preg_match( '/^\/[A-Za-z0-9_-]*$/', $value ) ) {
					$message = __( 'Success URL must start with / and have characters A-Z, numbers 0-9 and _ (underscore) or - (dash)', 'woocommerce-truelayer-gateway' );
				}
				break;
			case 'pending_uri':
				if ( ! preg_match( '/^\/[A-Za-z0-9_-]*$/', $value ) ) {
					$message = __( 'Pending URL must start with / and have characters A-Z, numbers 0-9 and _ (underscore) or - (dash)', 'woocommerce-truelayer-gateway' );
				}
				break;
		}

		if ( $message ) {
			WC_Admin_Settings::add_error( $message );
			throw new Exception( __( 'Invalid value: {$value}', 'woocommerce-truelayer-gateway' ) );
		}

		return parent::validate_text_field( $key, $value );
	}

	/**
	 * Validate textarea field
	 *
	 * @since 1.0.0
	 *
	 * @throws Exception Exception if validation fails.
	 *
	 * @param string $key   Field key.
	 * @param string $value Field value.
	 *
	 * @return mixed
	 */
	public function validate_textarea_field( $key, $value ) {
		if ( strlen( $value ) <= 9 || strlen( $value ) > 200 ) {
			WC_Admin_Settings::add_error( __( 'Please add a description of min 10, max 200 characters', 'woocommerce-truelayer-gateway' ) );
			throw new Exception( __( 'Invalid value: {$value}', 'woocommerce-truelayer-gateway' ) );
		}

		return parent::validate_textarea_field( $key, $value );
	}

	/**
	 * Validate password field
	 *
	 * @since 1.0.0
	 *
	 * @throws Exception Exception if validation fails.
	 *
	 * @param string $key   Field key.
	 * @param string $value Field value.
	 *
	 * @return mixed
	 */
	public function validate_password_field( $key, $value ) {
		$message = false;

		switch ( strtolower( $key ) ) {
			case 'client_id':
				if ( strlen( $value ) === 0 ) {
					$message = __( 'Please add a Truelayer Client ID', 'woocommerce-truelayer-gateway' );
				}
				break;
			case 'client_secret':
				if ( strlen( $value ) === 0 ) {
					$message = __( 'Please add a Truelayer Client Secret', 'woocommerce-truelayer-gateway' );
				}
				break;
		}

		if ( $message ) {
			WC_Admin_Settings::add_error( $message );
			throw new Exception( __( 'Invalid value: {$value}', 'woocommerce-truelayer-gateway' ) );
		}

		return parent::validate_password_field( $key, $value );
	}

	/**
	 * Create payment, set transaction id and redirect to payment page
	 *
	 * @since 1.0.0
	 *
	 * @throws Exception Thrown on unable to proceed.
	 *
	 * @param int $order_id Order ID to lookup.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		$data = array(
			'amount'                     => (int) floor( $order->get_total() * 100 ),
			'currency'                   => sanitize_text_field( $this->currency ),
			'remitter_reference'         => $this->get_remitter_reference( $order ),
			'beneficiary_name'           => sanitize_text_field( $this->beneficiary_name ),
			'beneficiary_sort_code'      => sanitize_text_field( $this->beneficiary_sort_code ),
			'beneficiary_account_number' => sanitize_text_field( $this->beneficiary_account_number ),
			'beneficiary_reference'      => $this->beneficiary_reference,
			'redirect_uri'               => $this->get_api_redirect_uri(),
		);

		$token = $this->get_api_token();

		if ( ! $token ) {
			throw new Exception( 'Unable to auth with TrueLayer API' );
		}

		$payment = $this->get_api_payment( $token, $data );

		if ( ! $payment ) {
			throw new Exception( 'Unable to create TrueLayer Payment' );
		}

		$order->set_transaction_id( $payment['id'] );

		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $payment['uri'],
		);
	}

	/**
	 * Handle callback from payment provider
	 *
	 * @since 1.0.0
	 *
	 * @throws Exception On Error finding order or payment.
	 */
	public function webhook() {
		global $woocommerce;

		$payment_id = filter_input( INPUT_GET, 'payment_id', FILTER_SANITIZE_STRING );

		if ( empty( $payment_id ) ) {
			throw new Exception( 'Payment ID not provided' );
		}

		$orders = wc_get_orders(
			array(
				'transaction_id' => $payment_id,
			)
		);

		if ( empty( $orders ) ) {
			throw new Exception( 'Order not found' );
		}

		$order = reset( $orders );

		$token = $this->get_api_token();

		if ( ! $token ) {
			throw new Exception( 'Unable to auth with TrueLayer API' );
		}

		$status = $this->get_api_payment_status( $token, $order->get_transaction_id() );

		if ( ! $status ) {
			throw new Exception( 'Unable to get TrueLayer payment status' );
		}

		if ( ! 'executed' === strtolower( $status ) ) {
			wp_safe_redirect( $this->get_webook_redirect_uri( 'pending' ) );
			exit();
		}

		$order->payment_complete();

		wc_reduce_stock_levels( $order );

		$woocommerce->cart->empty_cart();

		wp_safe_redirect( $this->get_webook_redirect_uri( 'success' ) );
		exit();
	}

	/**
	 * Get URI to redirect to based on webhook outcome
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Status of webhook.
	 *
	 * @return string
	 */
	protected function get_webook_redirect_uri( $status ) {
		switch ( strtolower( $status ) ) {
			case 'success':
				return $this->success_uri;
			default:
				return $this->pending_uri;
		}
	}

	/**
	 * Get remitter reference
	 *
	 * @since 1.0.0
	 *
	 * @param object $order The Order.
	 *
	 * @return string
	 */
	protected function get_remitter_reference( $order ) {
		if ( strpos( $this->remitter_reference, '%s' ) !== false ) {
			return sprintf( $this->remitter_reference, $order->get_order_number() );
		}

		return $this->remitter_reference;
	}

	/**
	 * Get URL to callback to from payment
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_api_redirect_uri() {
		return get_site_url() . '/wc-api/' . $this->id;
	}

	/**
	 * Get URIs based on testmode of plugin
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	protected function get_api_urls() {
		return array(
			'auth'    => 'yes' === $this->testmode ?
				'https://auth.truelayer-sandbox.com' :
				'https://auth.truelayer.com',
			'payment' => 'yes' === $this->testmode ?
				'https://pay-api.truelayer-sandbox.com' :
				'https://pay-api.truelayer.com',
		);
	}

	/**
	 * Get credentials object for API
	 *
	 * @since 1.0.0
	 *
	 * @return Credentials
	 */
	protected function get_api_credentials() {
		$credentials = new Credentials(
			$this->client_id,
			$this->client_secret
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
	protected function get_api_request( $uri ) {
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
	 * @return Request
	 */
	protected function get_api_auth_request() {
		$urls = $this->get_api_urls();
		return $this->get_api_request( $urls['auth'] );
	}

	/**
	 * Get Request for payment
	 *
	 * @since 1.0.0
	 *
	 * @return Request
	 */
	protected function get_api_payment_request() {
		$urls = $this->get_api_urls();
		return $this->get_api_request( $urls['payment'] );
	}

	/**
	 * Get API auth object
	 *
	 * @since 1.0.0
	 *
	 * @return Auth
	 */
	protected function get_api_auth() {
		$request = $this->get_api_auth_request();
		return new Auth( $request, $this->get_api_credentials() );
	}

	/**
	 * Get API token
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_api_token() {
		$auth     = $this->get_api_auth();
		$response = $auth->getAccessToken();
		if ( ! isset( $response['error'] ) && isset( $response['body']['access_token'] ) ) {
			return $response['body']['access_token'];
		}
	}

	/**
	 * Create and return payment details
	 *
	 * @since 1.0.0
	 * @param string $token API Token.
	 * @param array  $data  Payment data.
	 *
	 * @return array
	 */
	protected function get_api_payment( $token, $data ) {
		$request  = $this->get_api_payment_request();
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
	 * @param string $token      API token to use.
	 * @param string $payment_id Payment ID to find status for.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	protected function get_api_payment_status( $token, $payment_id ) {
		$request  = $this->get_api_payment_request();
		$payment  = new Payment( $request, $token );
		$response = $payment->getPaymentStatus( $payment_id );
		if ( ! isset( $response['error'] ) && isset( $response['body']['results'][0]['status'] ) ) {
			return $response['body']['results'][0]['status'];
		}
	}
}
