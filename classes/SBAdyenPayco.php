<?php

/**
 * Class to setup Adyen Payco payment gateway
 *
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @author Werner C. Bessinger @ Silverback Dev
 */
class SBAdyenPayco extends WC_Payment_Gateway {

    /**
     * Class constructor
     * Here we set the payment gateway id, 
     * gateway title and description in the backend, 
     * tab title, supports and a bunch of other stuff
     */
    public function __construct() {

        /* method id */
        $this->id = 'sb-adyen-payco';

        /* method title */
        $this->method_title = esc_attr__( 'SB Adyen PAYCO', 'sb-adyen-payco' );

        /* method description */
        $this->method_description = 'Adyen Payco payments. <a href="https://dev.nordace.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=sb-adyen-payco">';

        /* gateway icon */
        $this->icon = plugin_dir_url( __FILE__ ) . 'images/payco-logo.png';

        /* display frontend title */
        $this->title = $this->get_option( 'title' );

        /* gateway has fields */
        $this->has_fields = true;

        /* add refund support */
        $this->supports = [ 'refunds' ];

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
                        'title'   => esc_attr__( 'Enable / Disable', 'sb-adyen-payco' ),
                        'label'   => esc_attr__( 'Enable this payment gateway', 'sb-adyen-payco' ),
                        'type'    => 'checkbox',
                        'default' => 'no',
                ],
                'title'   => [
                        'title'       => __( 'Title', 'sb-adyen-payco' ),
                        'type'        => 'text',
                        'description' => __( 'Add your custom payment method title here if required.', 'sb-adyen-payco' ),
                        'default'     => __( 'Payco', 'sb-adyen-payco' ),
                ]
        ];
    }

    /**
     * Process iDEAL payment
     * 
     * @param int $order_id - numeric order id
     */
    public function process_payment($order_id) {

        /* create new customer order */
        $order = new WC_Order( $order_id );

        /* set initial order status */
        $order->update_status( 'pending', __( 'Awaiting Payco payment', 'sb-adyen-payco' ) );

        /* setup vars for curl request */
        $return_url         = $this->get_return_url( $order );
        $adyen_api_key      = wp_specialchars_decode( ADYEN_API_KEY );
        $adyen_merchant_acc = ADYEN_MERCHANT;
        $adyen_url_prefix   = ADYEN_URL_PREFIX;

        /* start api request */
        $client = new \Adyen\Client();
        $client->setXApiKey( $adyen_api_key );

        /* deterimine current gateway mode and set environment accordingly */
        if ( ADYEN_GATEWAY_MODE == 'test' ):
            $client->setEnvironment( \Adyen\Environment::TEST );
            $order_currency = 'KRW';
        else:
            $client->setEnvironment( \Adyen\Environment::LIVE, $adyen_url_prefix );
            $order_currency = alg_get_current_currency_code();
        endif;

        /* get order total correct decimal count depending on currency used */
        $cart_total_decimals = sb_adyen_set_currency_decimal( $order_currency );

        /* ensure order total is correctly formatted before sending to Adyen (no decimal points or nuttin') */
        $order_total = number_format( $order->get_total(), $cart_total_decimals, '', '' );

        try {
            $checkout = new \Adyen\Service\Checkout( $client );
        } catch ( Exception $ex ) {
            file_put_contents( SB_ADYEN_PATH . 'error-logs/payco/error.log', $_SERVER[ 'REQUEST_TIME' ] . ': ' . $ex->getMessage() . PHP_EOL, FILE_APPEND );
        }

        $payload = [
                "amount"          => [
                        "currency" => $order_currency,
                        "value"    => $order_total
                ],
                "reference"       => $order->get_order_number(),
                "paymentMethod"   => [
                        "type" => "kcp_paygo",
                ],
                "returnUrl"       => $return_url,
                "merchantAccount" => $adyen_merchant_acc,
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

    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
     *
     * @param  int    $order_id Order ID.
     * @param  float  $amount Refund amount.
     * @param  string $reason Refund reason.
     * @return boolean True or false based on success, or a WP_Error object.
     */
    public function process_refund($order_id, $amount = null, $reason = null) {

        $order = new WC_Order( $order_id );

        if ( !$order ) {
            return new WP_Error( 'sb_adyen_refund_error', __( 'Order not valid', 'woocommerce' ) );
        }

        $psp_ref = get_post_meta( $order_id, '_adyen_psp_ref', true );

        if ( !$psp_ref || empty( $psp_ref ) ) {
            return new WP_Error( 'sb_adyen_refund_error', __( 'No valid Transaction ID found', 'woocommerce' ) );
        }

        if ( is_null( $amount ) || $amount <= 0 ) {
            return new WP_Error( 'sb_adyen_refund_error', __( 'Amount not valid', 'woocommerce' ) );
        }

        if ( is_null( $reason ) || '' == $reason ) {
            $reason = sprintf( __( 'Refund for Order %s', 'woocommerce' ), $order->get_order_number() );
        }

        /* SETUP REFUND PARAMS */
        // order currency
        $order_currency = $order->get_currency();

        // currency decimals
        $decimals = sb_adyen_set_currency_decimal( $order_currency );

        // formatted refund amount
        $refund_amount = number_format( $amount, $decimals, '', '' );

        /* setup refund payload */
        $payload = [
                "merchantAccount"    => ADYEN_MERCHANT,
                "modificationAmount" => [
                        "value"    => $refund_amount,
                        "currency" => $order_currency
                ],
                "originalReference"  => $psp_ref,
                "reference"          => $order->get_order_number(),
                "browserInfo"        => [
                        "acceptHeader" => $_SERVER[ 'HTTP_USER_AGENT' ],
                        "userAgent"    => $_SERVER[ 'HTTP_ACCEPT' ]
                ]
        ];

        try {

            /* INIT CURL REQUEST */

            // url prefix
            $url_prefix = ADYEN_URL_PREFIX;

            // setup request link
            if ( ADYEN_GATEWAY_MODE == 'test' ):
                $curl_url = "https://pal-test.adyen.com/pal/servlet/Payment/v51/refund";
            else:
                $curl_url = "https://$url_prefix-pal-live.adyenpayments.com/pal/servlet/Payment/v51/refund";
            endif;

            // api key
            $x_api_key = wp_specialchars_decode( ADYEN_API_KEY );

            $curl = curl_init();

            curl_setopt_array( $curl, array(
                    CURLOPT_URL            => $curl_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING       => "",
                    CURLOPT_MAXREDIRS      => 10,
                    CURLOPT_TIMEOUT        => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST  => "POST",
                    CURLOPT_POSTFIELDS     => json_encode( $payload ),
                    CURLOPT_HTTPHEADER     => array(
                            "Content-Type: application/json",
                            "X-API-Key: $x_api_key",
                    ),
            ) );

            try {
                $response         = curl_exec( $curl );
                $result           = json_decode( $response );
                if ( isset( $result ) && !empty( $result->response = '[refund-received]' ) ):
                    $order->add_order_note( __( 'Adyen refund successfully processed. PSP Ref: ' . $result->pspReference, 'woocommerce' ) );
                    return true;
                endif;
            } catch ( Exception $ex ) {
                $order->add_order_note( __( 'Curl request failed with: ' . $ex->getMessage(), 'woocommerce' ) );
            }
            curl_close( $curl );
        } catch ( Exception $ex ) {
            $order->add_order_note( __( 'Refund processing error: ' . $ex->getMessage(), 'woocommerce' ) );
        }
    }

}
