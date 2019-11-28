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
class WC_Gateway_TrueLayer extends WC_Payment_Gateway 
{
    /**
     * Version
     *
     * @var string
     */
    public $version;

    public function __construct()
    {
        $this->version = WC_GATEWAY_TRUELAYER_VERSION;
        $this->id = 'truelayer';
		$this->icon = WP_PLUGIN_URL . '/' . plugin_basename( dirname( dirname( __FILE__ ) ) ) . '/assets/images/icon.png';
        $this->has_fields = false;
        $this->method_title = 'TrueLayer Gateway';
        $this->method_description = 'Take payments using OpenBanking via TrueLayer';
		$this->available_countries  = ['GB'];
		$this->available_currencies = (array)apply_filters('woocommerce_gateway_payfast_available_currencies', ['GBP'] );

        $this->supports = [
            'products'
        ];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = $this->get_option('testmode');
        $this->client_id = $this->get_option('client_id');
        $this->client_secret = $this->get_option('client_secret');
        $this->beneficiary_name = $this->get_option('beneficiary_name');
        $this->beneficiary_sort_code = $this->get_option('beneficiary_sort_code');
        $this->beneficiary_account_number = $this->get_option('beneficiary_account_number');    

        add_action( 'woocommerce_api_truelayer', [ $this, 'webhook' ] );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options' ] );
    }

    public function init_form_fields()
    {
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
                'default'     => 'Pay using OpenBanking via TrueLayer',          
            ],
            'testmode' => [
                'title'       => 'Test mode',
                'label'       => 'Enable Test Mode',
                'type'        => 'checkbox',
                'description' => 'Place the payment gateway in test mode using test client keys.',
                'default'     => 'yes',
                'desc_tip'    => true,          
            ],
            'client_id' => [
                'title'       => 'Client ID',
                'type'        => 'password'
            ],
            'client_secret' => [
                'title'       => 'Client Secret',
                'type'        => 'password'
            ],
            'beneficiary_name' => [
                'title'       => 'Beneficiary Name',
                'type'        => 'text'
            ],
            'beneficiary_sort_code' => [
                'title'       => 'Beneficiary Sort Code',
                'type'        => 'text'
            ],
            'beneficiary_account_number' => [
                'title'       => 'Beneficiary Account Number',
                'type'        => 'text'
            ],     
            'success_uri' => [
                'title' => 'Successful Redirect URL',
                'type' => 'text'
            ],     
            'pending_uri' => [
                'title' => 'Pending Redirect URL',
                'type' => 'text'
            ]
        ];
    }

    public function process_payment( $order_id )
    {
        $order = wc_get_order( $order_id );

        $data = [
            'amount' => (int)floor($order->get_total() * 100),
            'currency' => 'GBP',
            'remitter_reference' => $order->get_order_number(),
            'beneficiary_name' => $this->settings['beneficiary_name'],
            'beneficiary_sort_code' => $this->settings['beneficiary_sort_code'],
            'beneficiary_account_number' => $this->settings['beneficiary_account_number'],
            'beneficiary_reference' => $order->get_order_number(),
            'redirect_uri' => $this->get_api_redirect_uri()
        ];

        $token = $this->get_api_token();

        if (!$token){
            throw new Exception('Unable to auth with TrueLayer API');
        }

        $payment = $this->get_api_payment($token, $data);

        if (!$payment){
            throw new Exception('Unable to create TrueLayer Payment');
        }

        $order->set_transaction_id($payment['id']);

        $order->save();

        return [
			'result' => 'success',
			'redirect' => $payment['uri']
        ];
    }

    public function webhook()
    {
        global $woocommerce;

        if (empty($_GET['payment_id'])){
            $this->not_found_exit();
        }

        $orders = wc_get_orders([
            'transaction_id' => $_GET['payment_id'],
        ]);

        if (empty($orders)){
            $this->not_found_exit();
        }

        $order = reset($orders);

        $token = $this->get_api_token();

        if (!$token){
            throw new Exception('Unable to auth with TrueLayer API');
        }

        $status = $this->get_api_payment_status($token, $order->get_transaction_id());

        if (!$status){
            throw new Exception('Unable to get TrueLayer payment status');
        }

        if (strtolower($status) === 'executed'){
            $order->payment_complete();
            $order->reduce_order_stock();    
            $woocommerce->cart->empty_cart();  
            header(sprintf('Location: %s', $this->get_webook_redirect_uri('success')));  
        }else{
            header(sprintf('Location: %s', $this->get_webook_redirect_uri('pending')));  
        }
    }

    protected function get_webook_redirect_uri($status)
    {
        switch (strtolower($status)) {
            case 'success':
                return $this->settings['success_uri'];
            default:
                return $this->settings['pending_uri'];
        }
    }

    protected function get_api_redirect_uri()
    {
        return get_site_url() . '/wc-api/' . $this->id;
    }

    protected function get_api_urls()
    {
        return [
            'auth' => $this->settings['testmode'] === 'yes' ?
                'https://auth.truelayer-sandbox.com' :
                'https://auth.truelayer.com',
            'payment' => $this->settings['testmode'] === 'yes' ?
                'https://pay-api.truelayer-sandbox.com' :
                'https://pay-api.truelayer.com'
        ];
    }

    protected function get_api_credentials()
    {
        $credentials = new Signalfire\TruePayments\Credentials(
            $this->settings['client_id'],
            $this->settings['client_secret']
        );
        return $credentials;
    }

    protected function get_api_request($uri)
    {
        return new Signalfire\TruePayments\Request([
            'base_uri' => $uri,
            'timeout' => 60
        ]);
    }

    protected function get_api_auth_request()
    {
        $urls = $this->get_api_urls();
        return $this->get_api_request($urls['auth']);
    }

    protected function get_api_payment_request()
    {
        $urls = $this->get_api_urls();
        return $this->get_api_request($urls['payment']);        
    }

    protected function get_api_auth()
    {
        $request = $this->get_api_auth_request();
        return new Signalfire\TruePayments\Auth($request, $this->get_api_credentials());
    }

    protected function get_api_token()
    {
        $auth = $this->get_api_auth();
        $response = $auth->getAccessToken();
        if (!isset($response['error']) && isset($response['body']['access_token'])){
            return $response['body']['access_token'];
        }
    }

    protected function get_api_payment($token, $data)
    {
        $request = $this->get_api_payment_request();
        $payment = new Signalfire\TruePayments\Payment($request, $token);  
        $response = $payment->createPayment($data);
        $this->write_log($response);
        if (!isset($response['error']) &&
            isset($response['body']['results'][0]['auth_uri']) &&
            isset($response['body']['results'][0]['simp_id'])){
            return [
                'id' => $response['body']['results'][0]['simp_id'],
                'uri' => $response['body']['results'][0]['auth_uri']
            ];
        }
    }

    protected function get_api_payment_status($token, $payment_id)
    {
        $request = $this->get_api_payment_request();
        $payment = new Signalfire\TruePayments\Payment($request, $token); 
        $response = $payment->getPaymentStatus($payment_id);
        if (!isset($response['error']) && isset($response['body']['results'][0]['status'])){
            return $response['body']['results'][0]['status'];
        }
    }

    protected function not_found_exit(){
        status_header( 404 );
        nocache_headers();
        exit();   
    }

    protected function write_log ( $log )
    {
        if ( is_array( $log ) || is_object( $log ) ) {
           error_log( print_r( $log, true ) );
        } else {
           error_log( $log );
        }
     }
}