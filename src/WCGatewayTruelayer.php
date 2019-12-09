<?php
/**
 * TrueLayer Payment Gateway
 *
 * Provides a TrueLayer Payment Gateway.
 *
 * @category WCGatewayTrueLayer
 * @package  woocommerce-truelayer-gateway
 * @author   Robert Coster
 * @license  MIT
 * @link     https://github.com/signalfire/woocommerce-truelayer-gateway
 */

namespace Signalfire\Woocommerce\TrueLayer;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

use Exception;

use WC_Admin_Settings;
use WC_Payment_Gateway;

use Signalfire\Woocommerce\TrueLayer\WCGatewayTrueLayerAPI;
use Signalfire\Woocommerce\TrueLayer\WCGatewayTrueLayerUtils;

/**
 * TrueLayer Payment Gateway
 *
 * Provides a TrueLayer Payment Gateway.
 *
 * @category Class
 * @package  WCGatewayTrueLayer
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
		$this->available_currencies = (array) apply_filters( 'get_woocommerce_currencies', array( 'GBP' ) );

		$this->api   = new WCGatewayTrueLayerAPI();
		$this->utils = new WCGatewayTrueLayerUtils();

		$this->supports = array(
			'products',
		);

		$this->init_form_fields();
		$this->init_settings();

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
		$this->enabled                    = $this->get_option( 'enabled' );

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
	 * Ensure go to settings field before enabling gateway
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function needs_setup() {
		return true;
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

		$field_rules = array(
			'title'                      => array(
				array(
					'method'  => function( $value ) {
						return strlen( $value ) <= 3 || strlen( $value ) > 15; },
					'message' => __( 'Title must be a min 4, max 15 characters', 'woocommerce-truelayer-gateway' ),
				),
			),
			'remitter_reference'         => array(
				array(
					'method'  => function( $value ) {
						return strlen( $value ) <= 3 || strlen( $value ) > 20; },
					'message' => __( 'Remitter reference must be a min of 4, max 20 characters', 'woocommerce-truelayer-gateway' ),
				),
				array(
					'method'  => function( $value ) {
						return substr_count( $value, '%s' ) > 1; },
					'message' => __( 'Remitter reference can include only 1 string placeholder', 'woocommerce-truelayer-gateway' ),
				),
			),
			'beneficiary_name'           => array(
				array(
					'method'  => function( $value ) {
						return strlen( $value ) <= 3 || strlen( $value ) > 40; },
					'message' => __( 'Beneficiary name must be a min 4, max 40 characters', 'woocommerce-truelayer-gateway' ),
				),
			),
			'beneficiary_sort_code'      => array(
				array(
					'method'  => function( $value ) {
						$match = preg_match( '/^\d{6}$/', $value );
						return false === $match || 0 === $match;
					},
					'message' => __( 'Beneficiary sort code must be 6 digits (no dashes)', 'woocommerce-truelayer-gateway' ),
				),
			),
			'beneficiary_account_number' => array(
				array(
					'method'  => function( $value ) {
						$match = preg_match( '/^\d{8}$/', $value );
						return false === $match || 0 === $match;
					},
					'message' => __( 'Beneficiary account number must be 8 digits', 'woocommerce-truelayer-gateway' ),
				),
			),
			'beneficiary_reference'      => array(
				array(
					'method'  => function( $value ) {
						return strlen( $value ) <= 3 || strlen( $value ) > 18; },
					'message' => __( 'Beneficiary reference must be a min 4, max 18 characters', 'woocommerce-truelayer-gateway' ),
				),
			),
			'success_uri'                => array(
				array(
					'method'  => function( $value ) {
						$match = preg_match( '/^\/[A-Za-z0-9_-]*$/', $value );
						return false === $match || 0 === $match;
					},
					'message' => __( 'Success URL must start with / and have characters A-Z, numbers 0-9 and _ (underscore) or - (dash)', 'woocommerce-truelayer-gateway' ),
				),
			),
			'pending_uri'                => array(
				array(
					'method'  => function( $value ) {
						$match = preg_match( '/^\/[A-Za-z0-9_-]*$/', $value );
						return false === $match || 0 === $match;
					},
					'message' => __( 'Pending URL must start with / and have characters A-Z, numbers 0-9 and _ (underscore) or - (dash)', 'woocommerce-truelayer-gateway' ),
				),
			),
		);

		$field_rule = $field_rules[ $key ];

		if ( $field_rule ) {
			foreach ( $field_rule as $rule ) {
				if ( $rule['method']($value) ) {
					$message = $rule['message'];
					break;
				}
			}
		}

		if ( $message ) {
			WC_Admin_Settings::add_error( $message );
			throw new Exception( __( 'Invalid value: {$value}', 'woocommerce-truelayer-gateway' ) );
		}

		return parent::validate_text_field( $key, $value );
	}

	/**
	 * Validate checkbox fields
	 *
	 * @throws Exception Exception if validation fails.
	 *
	 * @param string $key   Field key.
	 * @param string $value Field value.
	 *
	 * @return mixed
	 */
	public function validate_checkbox_field( $key, $value ) {
		if ( 'enabled' === $key && ! $this->is_valid_for_use() ) {
			WC_Admin_Settings::add_error( __( 'Unable to enable gateway, check fields have required configuration values', 'woocommerce-truelayer-gateway' ) );
			throw new Exception( __( 'Invalid value: {$value}', 'woocommerce-truelayer-gateway' ) );
		}
		return parent::validate_checkbox_field( $key, $value );
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
			WC_Admin_Settings::add_error( __( 'Description must be min 10, max 200 characters', 'woocommerce-truelayer-gateway' ) );
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
					$message = __( 'Truelayer Client ID must be provided', 'woocommerce-truelayer-gateway' );
				}
				break;
			case 'client_secret':
				if ( strlen( $value ) === 0 ) {
					$message = __( 'Truelayer Client Secret must be provided', 'woocommerce-truelayer-gateway' );
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
	 * Checks required fields are provided
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function has_required_settings() {
		foreach ( $this->form_fields as $key => $field ) {
			if ( in_array( $field['type'], array( 'text', 'textarea', 'password' ), true ) ) {
				if ( empty( $this->{$key} ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Check if this gateway is enabled and available in the base currency being traded with.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_valid_for_use() {
		$is_available = false;
		$is_currency  = in_array( get_woocommerce_currency(), $this->available_currencies, true );

		if ( $is_currency && $this->has_required_settings() ) {
			$is_available = true;
		}

		return $is_available;
	}

	/**
	 * Admin Panel Options
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		$supported = in_array( get_woocommerce_currency(), $this->available_currencies, true );
		switch ( $supported ) {
			case true:
				parent::admin_options();
				break;
			case false:
				?>
				<h3><?php esc_html_e( 'TrueLayer', 'woocommerce-truelayer-gateway' ); ?></h3>
				<div class="inline error"><p><strong><?php esc_html_e( 'Gateway Disabled', 'woocommerce-truelayer-gateway' ); ?></strong> <?php esc_html_e( 'Choose Pound Sterling as your store currency in General Settings to enable the TrueLayer Gateway.', 'woocommerce-truelayer-gateway' ); ?></p></div>
				<?php
		}
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
			'remitter_reference'         => sanitize_text_field( $this->utils->get_remitter_reference( $this->remitter_reference, $order ) ),
			'beneficiary_name'           => sanitize_text_field( $this->beneficiary_name ),
			'beneficiary_sort_code'      => sanitize_text_field( $this->beneficiary_sort_code ),
			'beneficiary_account_number' => sanitize_text_field( $this->beneficiary_account_number ),
			'beneficiary_reference'      => sanitize_text_field( $this->beneficiary_reference ),
			'redirect_uri'               => $this->api->get_redirect_uri( get_site_url(), $this->id ),
		);

		$token = $this->api->get_token( $this->testmode, $this->client_id, $this->client_secret );

		if ( ! $token ) {
			throw new Exception( __('Unable to auth with TrueLayer API', 'woocommerce-truelayer-gateway' ) );
		}

		$payment = $this->api->get_payment( $this->testmode, $token, $data );

		if ( ! $payment ) {
			throw new Exception( __( 'Unable to create TrueLayer Payment', 'woocommerce-truelayer-gateway' ) );
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
			throw new Exception( __( 'Payment ID not provided', 'woocommerce-truelayer-gateway' ) );
		}

		$orders = wc_get_orders(
			array(
				'transaction_id' => $payment_id,
			)
		);

		if ( empty( $orders ) ) {
			throw new Exception( __( 'Order not found', 'woocommerce-truelayer-gateway' ) );
		}

		$order = reset( $orders );

		$token = $this->api->get_token( $this->testmode, $this->client_id, $this->client_secret );

		if ( ! $token ) {
			throw new Exception( __( 'Unable to auth with TrueLayer API', 'woocommerce-truelayer-gateway' ) );
		}

		$status = $this->api->get_payment_status( $this->testmode, $token, $order->get_transaction_id() );

		if ( ! $status ) {
			throw new Exception( __( 'Unable to get TrueLayer payment status', 'woocommerce-truelayer-gateway' ) );
		}

		if ( ! 'executed' === strtolower( $status ) ) {
			wp_safe_redirect( $this->utils->get_webook_redirect_uri( 'pending', $this->success_uri, $this->pending_uri ) );
			return;
		}

		$order->payment_complete();

		wc_reduce_stock_levels( $order );

		$woocommerce->cart->empty_cart();

		wp_safe_redirect( $this->utils->get_webhook_redirect_uri( 'success', $this->success_uri, $this->pending_uri ) );
	}


}
