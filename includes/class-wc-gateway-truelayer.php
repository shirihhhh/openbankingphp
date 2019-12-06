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
class WC_Gateway_TrueLayer extends WC_Payment_Gateway {

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
		$this->icon                 = WP_PLUGIN_URL . '/' . plugin_basename( dirname( dirname( __FILE__ ) ) ) . '/assets/images/icon.png';
		$this->has_fields           = false;
		$this->method_title         = 'TrueLayer Gateway';
		$this->method_description   = 'Take payments using OpenBanking via TrueLayer';
		$this->available_countries  = array( 'GB' );
		$this->available_currencies = (array) apply_filters( 'woocommerce_gateway_payfast_available_currencies', array( 'GBP' ) );

		$this->supports = array(
			'products',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->title                      = $this->get_option( 'title' );
		$this->description                = $this->get_option( 'description' );
		$this->enabled                    = $this->get_option( 'enabled' );
		$this->testmode                   = $this->get_option( 'testmode' );
		$this->client_id                  = $this->get_option( 'client_id' );
		$this->client_secret              = $this->get_option( 'client_secret' );
		$this->beneficiary_name           = $this->get_option( 'beneficiary_name' );
		$this->beneficiary_sort_code      = $this->get_option( 'beneficiary_sort_code' );
		$this->beneficiary_account_number = $this->get_option( 'beneficiary_account_number' );

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
				'title'       => 'Enable/Disable',
				'label'       => 'Enable TrueLayer Gateway',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                      => array(
				'title'       => 'Title',
				'type'        => 'text',
				'description' => 'This controls the title which the user sees during checkout.',
				'default'     => 'OpenBanking',
				'desc_tip'    => true,
			),
			'description'                => array(
				'title'       => 'Description',
				'type'        => 'textarea',
				'description' => 'This controls the description which the user sees during checkout.',
				'default'     => 'Pay using OpenBanking via TrueLayer',
			),
			'testmode'                   => array(
				'title'       => 'Test mode',
				'label'       => 'Enable Test Mode',
				'type'        => 'checkbox',
				'description' => 'Place the payment gateway in test mode using test client keys.',
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'client_id'                  => array(
				'title' => 'Client ID',
				'type'  => 'password',
			),
			'client_secret'              => array(
				'title' => 'Client Secret',
				'type'  => 'password',
			),
			'beneficiary_name'           => array(
				'title' => 'Beneficiary Name',
				'type'  => 'text',
			),
			'beneficiary_sort_code'      => array(
				'title' => 'Beneficiary Sort Code',
				'type'  => 'text',
			),
			'beneficiary_account_number' => array(
				'title' => 'Beneficiary Account Number',
				'type'  => 'text',
			),
			'success_uri'                => array(
				'title' => 'Successful Redirect URL',
				'type'  => 'text',
			),
			'pending_uri'                => array(
				'title' => 'Pending Redirect URL',
				'type'  => 'text',
			),
		);
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
			'currency'                   => 'GBP',
			'remitter_reference'         => $order->get_order_number(),
			'beneficiary_name'           => $this->settings['beneficiary_name'],
			'beneficiary_sort_code'      => $this->settings['beneficiary_sort_code'],
			'beneficiary_account_number' => $this->settings['beneficiary_account_number'],
			'beneficiary_reference'      => $order->get_order_number(),
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
			$this->not_found_exit();
		}

		$orders = wc_get_orders(
			array(
				'transaction_id' => $payment_id,
			)
		);

		if ( empty( $orders ) ) {
			$this->not_found_exit();
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
			wp_redirect($this->get_webook_redirect_uri( 'pending' ));
			exit();
		}

		$order->payment_complete();
		$order->reduce_order_stock();
		$woocommerce->cart->empty_cart();

		wp_redirect($this->get_webook_redirect_uri( 'success' ));
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
				return $this->settings['success_uri'];
			default:
				return $this->settings['pending_uri'];
		}
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
			'auth'    => 'yes' === $this->settings['testmode'] ?
				'https://auth.truelayer-sandbox.com' :
				'https://auth.truelayer.com',
			'payment' => 'yes' === $this->settings['testmode'] ?
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
		$credentials = new Signalfire\TruePayments\Credentials(
			$this->settings['client_id'],
			$this->settings['client_secret']
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
		return new Signalfire\TruePayments\Request(
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
		return new Signalfire\TruePayments\Auth( $request, $this->get_api_credentials() );
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
		$payment  = new Signalfire\TruePayments\Payment( $request, $token );
		$response = $payment->createPayment( $data );
		$this->write_log( $response );
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
		$payment  = new Signalfire\TruePayments\Payment( $request, $token );
		$response = $payment->getPaymentStatus( $payment_id );
		if ( ! isset( $response['error'] ) && isset( $response['body']['results'][0]['status'] ) ) {
			return $response['body']['results'][0]['status'];
		}
	}

	/**
	 * Throw a 404 and exit
	 *
	 * @since 1.0.0
	 */
	protected function not_found_exit() {
		status_header( 404 );
		nocache_headers();
		exit();
	}

	/**
	 * Write error log
	 *
	 * @since 1.0.0
	 *
	 * @param object $log Data to log.
	 */
	protected function write_log( $log ) {
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( $log, true ) );
		} else {
			error_log( $log );
		}
	}
}
