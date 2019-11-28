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
        ];
    }

    public function process_payment( $order_id )
    {
        global $woocommerce;
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
        $payment = $this->get_api_payment($token, $data);

        // Record simp_id somewhere on the order....
        $order->set_transaction_id($this->get_api_payment_id($payment));
        $order->save();

        return [
			'result' => 'success',
			'redirect' => $this->get_api_payment_auth_uri($payment)
        ];
    }

    public function webhook()
    {
        $orders = wc_get_orders([
            'transaction_id' => $_GET['payment_id'],
        ]);

        if (!empty($orders)){

            $order = reset($orders);

            $token = $this->get_api_token();
            
            $status = $this->get_api_payment_status($token, $order->get_transaction_id());

            if (strtolower($status) === 'executed'){
                $order->payment_complete();
                $order->reduce_order_stock();     
                header(sprintf('Location: %s', $this->get_webook_redirect_uri('success')));  
            }else{
                header(sprintf('Location: %s', $this->get_webook_redirect_uri('pending')));  
            }

        }else{
            header(sprintf('Location: %s', $this->get_webook_redirect_uri('failed')));  
        }

    }

    private function get_webook_redirect_uri($status)
    {
        switch (strtolower($status)) {
            case 'success':
                return '/success';
            case 'pending':
                return '/pending';
            default:
                return '/failed';
        }
    }

    private function get_api_redirect_uri()
    {
        // Dynamic this bit....
        return 'http://wp-test.test/wc-api/truelayer';
    }

    private function get_api_urls()
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

    private function get_api_credentials()
    {
        $credentials = new Signalfire\TruePayments\Credentials(
            $this->settings['client_id'],
            $this->settings['client_secret']
        );
        return $credentials;
    }

    private function get_api_request($uri)
    {
        return new Signalfire\TruePayments\Request([
            'base_uri' => $uri,
            'timeout' => 60
        ]);
    }

    private function get_api_auth_request()
    {
        $urls = $this->get_api_urls();
        return $this->get_api_request($urls['auth']);
    }

    private function get_api_payment_request()
    {
        $urls = $this->get_api_urls();
        return $this->get_api_request($urls['payment']);        
    }

    private function get_api_auth()
    {
        $request = $this->get_api_auth_request();
        return new Signalfire\TruePayments\Auth($request, $this->get_api_credentials());
    }

    private function get_api_token()
    {
        $auth = $this->get_api_auth();
        $response = $auth->getAccessToken();
        if (isset($response['body']['access_token'])){
            return $response['body']['access_token'];
        }
    }

    private function get_api_payment($token, $data)
    {
        $request = $this->get_api_payment_request();
        $payment = new Signalfire\TruePayments\Payment($request, $token);  
        return $payment->createPayment($data);
    }

    private function get_api_payment_status($token, $payment_id)
    {
        $request = $this->get_api_payment_request();
        $payment = new Signalfire\TruePayments\Payment($request, $token); 
        $response = $payment->getPaymentStatus($payment_id);
        if (isset($response['body']['results'][0]['status'])){
            return $response['body']['results'][0]['status'];
        }
    }

    private function get_api_payment_auth_uri($payment)
    {
        if (isset($payment['body']['results'][0]['auth_uri']))
        {
            return $payment['body']['results'][0]['auth_uri'];
        }
    }

    private function get_api_payment_id($payment){
        if (isset($payment['body']['results'][0]['simp_id'])){
            return $payment['body']['results'][0]['simp_id'];
        }
    }

}