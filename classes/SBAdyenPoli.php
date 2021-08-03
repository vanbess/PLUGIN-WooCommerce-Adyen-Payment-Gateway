<?php

/**
 * Renders POLi Online Banking payment option
 *
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @author Werner C. Bessinger @ Silverback Dev
 */
class SBAdyenPoli extends WC_Payment_Gateway {

    /**
     * Class constructor
     * Here we set the payment gateway id, 
     * gateway title and description in the backend, 
     * tab title, supports and a bunch of other stuff
     */
    public function __construct() {

        /* method id */
        $this->id = 'sb-adyen-poli';

        /* method title */
        $this->method_title = esc_attr__( 'SB Adyen POLi ONLINE BANKING', 'sb-adyen-poli' );

        /* method description */
        $this->method_description = esc_attr__( 'Adyen Poli Online Banking payments.', 'sb-adyen-poli' );

        /* gateway icon */
        $this->icon = plugin_dir_url( __FILE__ ) . 'images/poli-logo.png';

        /* display frontend title */
        $this->title = $this->get_option( 'title' );

        /* gateway has fields */
        $this->has_fields = true;

        /* init gateway form fields */
        $this->settings_fields();

        /* load our settings */
        $this->init_settings();

        /* save gateway settings when save settings button clicked IF user is admin */
        if ( is_admin() ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }
    }

    /**
     * Set up WooCommerce payment gateway settings fields
     */
    public function settings_fields() {

        $this->form_fields = [
                /* enable/disable */
                'enabled' => [
                        'title'   => esc_attr__( 'Enable / Disable', 'sb-adyen-poli' ),
                        'label'   => esc_attr__( 'Enable this payment gateway', 'sb-adyen-poli' ),
                        'type'    => 'checkbox',
                        'default' => 'no',
                ],
                'title'   => [
                        'title'       => __( 'Title', 'sb-adyen-poli' ),
                        'type'        => 'text',
                        'description' => __( 'Add your custom payment method title here if required.', 'sb-adyen-poli' ),
                        'default'     => __( 'POLi', 'sb-adyen-poli' ),
                ]
        ];
    }

    /* process payment */

    public function process_payment($order_id) {

        /* create new customer order */
        $order = new WC_Order( $order_id );

        /* set initial order status */
        $order->update_status( 'pending' );
        $order->add_order_note( __( 'Awaiting Poli Online Banking payment', 'woocommerce' ) );

        /* setup vars for curl request */
        $order_total = $order->get_total();

        /* formatted total */
        $formatted_total = number_format( $order_total, 2, '', '' );

        /* return url */
        $return_url = $this->get_return_url( $order );

        /* start api request */
        $client = new \Adyen\Client();
        $client->setXApiKey( wp_specialchars_decode( ADYEN_API_KEY ) );

        /* deterimine current gateway mode and set environment/currency accordingly */
        if ( ADYEN_GATEWAY_MODE == 'test' ):
            $client->setEnvironment( \Adyen\Environment::TEST );
            $order_currency = 'AUD';
        else:
            $client->setEnvironment( \Adyen\Environment::LIVE, ADYEN_URL_PREFIX );
            $order_currency = alg_get_current_currency_code();
        endif;

        try {
            $checkout = new \Adyen\Service\Checkout( $client );
        } catch ( Exception $ex ) {
            file_put_contents( SB_ADYEN_PATH . 'error-logs/poli/error.log', $_SERVER[ 'REQUEST_TIME' ] . ': ' . $ex->getMessage() . PHP_EOL, FILE_APPEND );
        }

        $payload = [
                "merchantAccount" => ADYEN_MERCHANT,
                "reference"       => $order->get_order_number(),
                "amount"          => [
                        "currency" => $order_currency,
                        "value"    => $formatted_total
                ],
                "paymentMethod"   => [
                        "type" => "poli"
                ],
                "returnUrl"       => $return_url,
        ];

        try {
            $request = $checkout->payments( $payload );
        } catch ( Exception $ex ) {
            wc_add_notice( __( 'There was an error processing your payment: ' . $ex->getMessage(), 'woocommerce' ) );
            $order->add_order_note( __( 'Error processing payment: ' . $ex->getMessage(), 'woocommerce' ) );
        }

        if ( !empty( $request ) ):

            $redirect_url = $request[ 'redirect' ][ 'url' ];

            /* redirect to chosen iDEAL merchant page to complete payment */
            return [
                    'result'   => 'success',
                    'redirect' => $redirect_url
            ];

        endif;
    }

}
