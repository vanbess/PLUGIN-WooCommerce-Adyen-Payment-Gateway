<?php

/**
 * Renders SOFORT payment option
 *
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @author Werner C. Bessinger @ Silverback Dev
 */
class SBAdyenMultibanco extends WC_Payment_Gateway {

    /**
     * Class constructor
     * Here we set the payment gateway id, 
     * gateway title and description in the backend, 
     * tab title, supports and a bunch of other stuff
     */
    public function __construct() {

        /* method id */
        $this->id = 'sb-adyen-multibanco';

        /* method title */
        $this->method_title = esc_attr__( 'SB Adyen MULTIBANCO', 'sb-adyen-multibanco' );

        /* method description */
        $this->method_description = esc_attr__( 'Adyen Multibanco payments.', 'sb-adyen-multibanco' );

        /* gateway icon */
        $this->icon = plugin_dir_url( __FILE__ ) . 'images/multibanco.png';

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
                        'title'   => esc_attr__( 'Enable / Disable', 'sb-adyen-multibanco' ),
                        'label'   => esc_attr__( 'Enable this payment gateway', 'sb-adyen-multibanco' ),
                        'type'    => 'checkbox',
                        'default' => 'no',
                ],
                'title'   => [
                        'title'       => __( 'Title', 'sb-adyen-multibanco' ),
                        'type'        => 'text',
                        'description' => __( 'Add your custom payment method title here if required.', 'sb-adyen-multibanco' ),
                        'default'     => __( 'Multibanco', 'sb-adyen-multibanco' ),
                ]
        ];
    }

    /* process payment */

    public function process_payment($order_id) {

        /* create new customer order */
        $order = new WC_Order( $order_id );

        /* set initial order status */
        $order->update_status( 'pending' );
        $order->add_order_note( __( 'Awaiting payment via MULTIBANCO', 'woocommerce' ) );

        /* setup vars for curl request */
        $order_total = $order->get_total();

        /* formatted total */
        $formatted_total = number_format( $order_total, 2, '', '' );

        $return_url = $this->get_return_url( $order );

        /* start api request */
        $client = new \Adyen\Client();
        $client->setXApiKey( wp_specialchars_decode( ADYEN_API_KEY ) );

        if ( ADYEN_GATEWAY_MODE == "test" ) :
            $client->setEnvironment( \Adyen\Environment::TEST );
            $order_currency = 'EUR';
        else:
            $client->setEnvironment( \Adyen\Environment::LIVE, ADYEN_URL_PREFIX );
            $order_currency = alg_get_current_currency_code();
        endif;

        try {
            $checkout = new \Adyen\Service\Checkout( $client );
        } catch ( Exception $ex ) {
            file_put_contents( SB_ADYEN_PATH . 'error-logs/multibanco/error.log', $_SERVER[ 'REQUEST_TIME' ] . ': ' . $ex->getMessage() . PHP_EOL, FILE_APPEND );
        }

        $payload = [
                "amount"          => [
                        "currency" => $order_currency,
                        "value"    => $formatted_total
                ],
                "reference"       => $order->get_order_number(),
                "paymentMethod"   => [
                        "type" => "multibanco"
                ],
                "countryCode"     => "PT",
                "merchantAccount" => ADYEN_MERCHANT,
        ];

        try {
            $payment_request = $checkout->payments( $payload );
        } catch ( Exception $ex ) {
            wc_add_notice( __( 'There was an error processing your payment: ' . $ex->getMessage(), 'woocommerce' ) );
            $order->add_order_note( __( 'Error processing payment: ' . $ex->getMessage(), 'woocommerce' ) );
        }

        if ( isset( $payment_request ) && $payment_request[ 'resultCode' ] == 'Received' ) :

            // get returned multibanco ref data to add to our return url
            $mb_voucher_ref      = $payment_request[ 'additionalData' ][ 'comprafacil.reference' ];
            $mb_voucher_deadline = $payment_request[ 'additionalData' ][ 'comprafacil.deadline' ];
            $mb_pspRef           = $payment_request[ 'pspReference' ];

            // add order notes for each of the above
            $order->add_order_note( "Multibanco voucher number: " . $mb_voucher_ref, 0, false );
            $order->add_order_note( "Multibanco voucher deadline: " . $mb_voucher_deadline . " days", 0, false );
            $order->add_order_note( "Multibanco PSP Ref: " . $mb_pspRef, 0, false );

            // add order payment method
            update_post_meta( $order_id, '_payment_method_title', 'Multibanco' );

            // empty cart
            WC()->cart->empty_cart();

            // set status and redirect
            return [
                    'result'   => 'success',
                    'redirect' => $return_url . '&mb_voucher_ref=' . urlencode( $mb_voucher_ref ) . '&mb_voucher_deadline=' . $mb_voucher_deadline
            ];

        endif;
    }

}
