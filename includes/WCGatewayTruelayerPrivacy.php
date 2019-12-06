<?php

namespace Signalfire\Woocommerce\TrueLayer;

use WC_Abstract_Privacy;

/**
 * TrueLayer Payment Gateway Privacy
 *
 * Provides for TrueLayer Payment Gateway Privacy.
 *
 * @category WC_Gateway_TrueLayer_Privacy
 * @package  woocommerce-truelayer-gateway
 * @author   Robert Coster
 * @license  MIT
 * @link     https://github.com/signalfire/woocommerce-truelayer-gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * TrueLayer Payment Gateway
 *
 * Provides for TrueLayer Payment Gateway Privacy.
 *
 * @category Class
 * @package  WC_Gateway_TrueLayer_Privacy
 * @author   Robert Coster
 * @license  MIT
 * @link     https://github.com/signalfire/woocommerce-truelayer-gateway
 */
class WCGatewayTrueLayerPrivacy extends WC_Abstract_Privacy {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( __( 'TrueLayer', 'woocommerce-truelayer-gateway' ) );
		$this->add_exporter( 'woocommerce-truelayer-gateway-order-data', __( 'WooCommerce TrueLayer Order Data', 'woocommerce-truelayer-gateway' ), array( $this, 'order_data_exporter' ) );
		$this->add_eraser( 'woocommerce-truelayer-gateway-order-data', __( 'WooCommerce TrueLayer Data', 'woocommerce-truelayer-gateway' ), array( $this, 'order_data_eraser' ) );
	}

	/**
	 * Returns a list of orders that are using one of PayFast's payment methods.
	 *
	 * @param string $email_address Email address to get orders for.
	 * @param int    $page          Page to get of orders.
	 *
	 * @return array WP_Post
	 */
	protected function get_truelayer_orders( $email_address, $page ) {
		$user = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.

		$order_query = array(
			'payment_method' => 'truelayer',
			'limit'          => 10,
			'page'           => $page,
		);

		if ( $user instanceof WP_User ) {
			$order_query['customer_id'] = (int) $user->ID;
		}

		if ( ! $user instanceof WP_User ) {
			$order_query['billing_email'] = $email_address;
		}

		return wc_get_orders( $order_query );
	}

	/**
	 * Gets the message of the privacy to display.
	 */
	public function get_privacy_message() {
		/* translators: %s: url */
		return wpautop( sprintf( __( 'By using this extension, you may be storing personal data or sharing data with an external service. <a href="%s" target="_blank">Learn more about how this works, including what you may want to include in your privacy policy.</a>', 'woocommerce-truelayer-gateway' ), 'https://docs.woocommerce.com/document/privacy-payments/#woocommerce-truelayer-gateway' ) );
	}

	/**
	 * Handle exporting data for Orders.
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
	public function order_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$data_to_export = array();
		$orders         = $this->get_truelayer_orders( $email_address, (int) $page );
		$done           = true;

		if ( 0 < count( $orders ) ) {
			foreach ( $orders as $order ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_orders',
					'group_label' => __( 'Orders', 'woocommerce-truelayer-gateway' ),
					'item_id'     => 'order-' . $order->get_id(),
				);
			}

			$done = 10 > count( $orders );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Finds and erases order data by email address.
	 *
	 * @since 3.4.0
	 * @param string $email_address The user email address.
	 * @param int    $page  Page.
	 * @return array An array of personal data in name value pairs
	 */
	public function order_data_eraser( $email_address, $page ) {
		$orders         = $this->get_truelayer_orders( $email_address, (int) $page );
		$items_removed  = false;
		$items_retained = false;

		$done = count( $orders ) < 10;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => array(),
			'done'           => $done,
		);
	}
}

new WCGatewayTrueLayerPrivacy();
