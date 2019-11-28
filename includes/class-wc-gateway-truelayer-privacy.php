<?php
if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
	return;
}

class WC_Gateway_TrueLayer_Privacy extends WC_Abstract_Privacy {
	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		parent::__construct( __( 'TrueLayer', 'woocommerce-gateway-truelayer' ) );
		$this->add_exporter( 'woocommerce-gateway-truelayer-order-data', __( 'WooCommerce TrueLayer Order Data', 'woocommerce-gateway-truelayer' ), array( $this, 'order_data_exporter' ) );
		$this->add_eraser( 'woocommerce-gateway-truelayer-order-data', __( 'WooCommerce TrueLayer Data', 'woocommerce-gateway-truelayer' ), array( $this, 'order_data_eraser' ) );
	}

	/**
	 * Returns a list of orders that are using one of PayFast's payment methods.
	 *
	 * @param string  $email_address
	 * @param int     $page
	 *
	 * @return array WP_Post
	 */
    protected function get_truelayer_orders( $email_address, $page )
    {
		$user = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.

		$order_query = [
			'payment_method' => 'truelayer',
			'limit' => 10,
			'page' => $page,
        ];

		if ( $user instanceof WP_User ) {
			$order_query['customer_id'] = (int) $user->ID;
		} else {
			$order_query['billing_email'] = $email_address;
		}

		return wc_get_orders( $order_query );
	}

	/**
	 * Gets the message of the privacy to display.
	 *
	 */
    public function get_privacy_message()
    {
		return wpautop( sprintf( __( 'By using this extension, you may be storing personal data or sharing data with an external service. <a href="%s" target="_blank">Learn more about how this works, including what you may want to include in your privacy policy.</a>', 'woocommerce-gateway-truelayer' ), 'https://docs.woocommerce.com/document/privacy-payments/#woocommerce-gateway-truelayer' ) );
	}

	/**
	 * Handle exporting data for Orders.
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
    public function order_data_exporter( $email_address, $page = 1 )
    {
		$done = false;
		$data_to_export = [];
		$orders = $this->get_truelayer_orders( $email_address, (int) $page );
		$done = true;

		if ( 0 < count( $orders ) ) {
			foreach ( $orders as $order ) {
				$data_to_export[] = [
					'group_id' => 'woocommerce_orders',
					'group_label' => __( 'Orders', 'woocommerce-gateway-truelayer' ),
					'item_id' => 'order-' . $order->get_id(),
                ];
			}

			$done = 10 > count( $orders );
		}

		return [
			'data' => $data_to_export,
			'done' => $done,
        ];
	}

	/**
	 * Finds and erases order data by email address.
	 *
	 * @since 3.4.0
	 * @param string $email_address The user email address.
	 * @param int    $page  Page.
	 * @return array An array of personal data in name value pairs
	 */
    public function order_data_eraser( $email_address, $page )
    {
		$orders = $this->get_truelayer_orders( $email_address, (int) $page );
		$items_removed = false;
		$items_retained = false;

		// Tell core if we have more orders to work on still
		$done = count( $orders ) < 10;

		return [
			'items_removed' => $items_removed,
			'items_retained' => $items_retained,
			'messages' => [],
			'done' => $done,
        ];
	}
}

new WC_Gateway_TrueLayer_Privacy();