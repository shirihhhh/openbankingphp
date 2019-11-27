<?php
/**
 * TrueLayer Payment Gateway
 *
 * Provides a TrueLayer Payment Gateway.
 *
 * @package WooCommerce
 * @category Payment Gateways
 * @author Robert Coster
 */
class WC_Gateway_TrueLayer extends WC_Payment_Gateway {

  /**
   * Version
   *
   * @var string
   */
  public $version;

	public function __construct() {
		$this->version = WC_GATEWAY_TRUELAYER_VERSION;
    $this->id = 'truelayer';
    $this->icon = '';
    $this->has_fields = false;
    $this->method_title = 'TrueLayer Gateway';
    $this->method_description = 'Take payments using OpenBanking via TrueLayer';
    $this->supports = [
      'products'
    ];

    $this->init_form_fields();
    $this->init_settings();

    $this->title = $this->get_option('title');
    $this->description = $this->get_option('description');
    $this->enabled = $this->get_option('enabled');
    $this->testmode = $this->get_option('testmode');
    $this->client_id = $this->testmode ?
      $this->get_option('test_client_id') :
      $this->get_option('client_id');
    $this->client_secret = $this->testmode ?
      $this->get_option('test_client_secret') :
      $this->get_option('client_secret');
    $this->beneficiary_name = $this->testmode ?
      $this->get_option('test_beneficiary_name') :
      $this->get_option('beneficiary_name');
    $this->beneficiary_sort_code = $this->testmode ?
      $this->get_option('test_beneficiary_sort_code') :
      $this->get_option('beneficiary_sort_code');
    $this->beneficiary_account_number = $this->testmode ?
      $this->get_option('test_beneficiary_account_number') :
      $this->get_option('beneficiary_account_number');    

    add_action( 'woocommerce_api_truelayer', [ $this, 'webhook' ] );
    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options' ] );
  }

  public function init_form_fields(){
    $this->form_fields = [
      'enabled' => [
        'title'       => 'Enable/Disable',
        'label'       => 'Enable TrueLayer Gateway',
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no'          
      ],
      'title' => [
        'title'       => 'Title',
        'type'        => 'text',
        'description' => 'This controls the title which the user sees during checkout.',
        'default'     => 'OpenBanking',
        'desc_tip'    => true,          
      ],
      'description' => [
        'title'       => 'Description',
        'type'        => 'textarea',
        'description' => 'This controls the description which the user sees during checkout.',
        'default'     => 'Pay directly via OpenBanking',          
      ],
      'testmode' => [
        'title'       => 'Test mode',
        'label'       => 'Enable Test Mode',
        'type'        => 'checkbox',
        'description' => 'Place the payment gateway in test mode using test client keys.',
        'default'     => 'yes',
        'desc_tip'    => true,          
      ],
      'test_client_id' => [
        'title'       => 'Test Client ID',
        'type'        => 'text'
      ],
      'test_client_secret' => [
        'title'       => 'Test Client Secret',
        'type'        => 'password',
      ],
      'client_id' => [
        'title'       => 'Live Client ID',
        'type'        => 'text'
      ],
      'client_secret' => [
        'title'       => 'Live Client Secret',
        'type'        => 'password'
      ],
      'test_beneficiary_name' => [
        'title'       => 'Test Beneficiary Name',
        'type'        => 'text'
      ],
      'test_beneficiary_sort_code' => [
        'title'       => 'Test Beneficiary Sort Code',
        'type'        => 'text'
      ],
      'test_beneficiary_account_number' => [
        'title'       => 'Test Beneficiary Account Number',
        'type'        => 'text'
      ],
      'beneficiary_name' => [
        'title'       => 'Live Beneficiary Name',
        'type'        => 'text'
      ],
      'beneficiary_sort_code' => [
        'title'       => 'Live Beneficiary Sort Code',
        'type'        => 'text'
      ],
      'beneficiary_account_number' => [
        'title'       => 'Live Beneficiary Account Number',
        'type'        => 'text'
      ],        
    ];
  }

  public function process_payment( $order_id ) {
    global $woocommerce;
    $order = wc_get_order( $order_id );
    $total = $order->get_total();
  }

  public function webhook() {
    //$order = wc_get_order( $_GET['id'] );
    //$order->payment_complete();
    //$order->reduce_order_stock();
    die(var_dump($_GET));
  }
}