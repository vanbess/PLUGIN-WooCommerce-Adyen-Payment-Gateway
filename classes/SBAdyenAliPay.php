<?php

/**
 * Renders AliPay payment option
 *
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @author Werner C. Bessinger @ Silverback Dev
 */
class SBAdyenAliPay extends WC_Payment_Gateway {

    use SBAGetDecimals;

    /**
     * Class constructor
     * Here we set the payment gateway id, 
     * gateway title and description in the backend, 
     * tab title, supports and a bunch of other stuff
     */
    public function __construct() {

        /* method id */
        $this->id = 'sb-adyen-alipay';

        /* method title */
        $this->method_title = esc_attr__( 'SB Adyen AliPay', 'sb-adyen-alipay' );

        /* method description */
        $this->method_description = esc_attr__( 'Adyen AliPay payments.', 'sb-adyen-alipay' );

        /* gateway icon */
        $this->icon = plugin_dir_url( __FILE__ ) . 'images/alipay.svg';

        /* display frontend title */
        $this->title = $this->get_option( 'title' );

        /* gateway has fields */
        $this->has_fields = false;

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
                        'title'   => esc_attr__( 'Enable / Disable', 'sb-adyen-alipay' ),
                        'label'   => esc_attr__( 'Enable this payment gateway', 'sb-adyen-alipay' ),
                        'type'    => 'checkbox',
                        'default' => 'no',
                ],
                'title'   => [
                        'title'       => __( 'Title', 'sb-adyen-alipay' ),
                        'type'        => 'text',
                        'description' => __( 'Add your custom payment method title here if required.', 'sb-adyen-alipay' ),
                        'default'     => __( 'AliPay', 'sb-adyen-alipay' ),
                ]
        ];
    }

    /* process payment */

    public function process_payment($order_id) {

        /* create new customer order */
        $order = new WC_Order( $order_id );

        /* set initial order status */
        $order->update_status( 'pending', __( 'Awaiting AliPay payment', 'woocommerce' ) );

        /* setup vars for curl request */
        $order_total = $order->get_total();

        $return_url = $this->get_return_url( $order );

        $order_no = $order->get_order_number();

        /* formatted total */
        $formatted_total = number_format( $order_total, 2, '', '' );

        $payload = [
                "amount"           => [
                        "currency" => alg_get_current_currency_code(),
                        "value"    => $formatted_total
                ],
                "reference"        => $order_no,
                "paymentMethod"    => [
                        "type" => "alipay",
                ],
                "returnUrl"        => $return_url,
//                "merchantAccount"    => 'SilverbackDevStudiosPtyLtd045ECOM',
                "merchantAccount"  => ADYEN_MERCHANT,
                "shopperReference" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        ];

        try {

            /* INIT CURL REQUEST */

            // url prefix
            $url_prefix = ADYEN_URL_PREFIX;

            // setup request link
            if ( ADYEN_GATEWAY_MODE == 'test' ):
                $curl_url = "https://checkout-test.adyen.com/v67/payments";
            else:
                $curl_url = "https://$url_prefix-checkout-live.adyenpayments.com/checkout/v67/payments";
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

                // execute request
                $response = curl_exec( $curl );

                // parse response
                $result = json_decode( $response, true );

                // if resultCode is set and equal to RedirectShopper
                if ( isset( $result[ 'resultCode' ] ) && $result[ 'resultCode' ] === 'RedirectShopper' ) :

                    // grab redirect url
                    $redirect_url = $result[ 'action' ][ 'url' ];

                    // redirect to AliPay payment completion page
                    return [
                            'result'   => 'success',
                            'redirect' => $redirect_url
                    ];

                endif;
            } catch ( Exception $ex ) {
                $order->add_order_note( 'Curl request failed with: ' . $ex->getMessage() );
            }
            curl_close( $curl );
        } catch ( Exception $ex ) {
            $order->add_order_note( 'Payment processing error: ' . $ex->getMessage() );
        }
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
        $decimals = self::get_decimals( $order_currency );

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
                    $order->add_order_note( 'Adyen refund successfully processed. PSP Ref: ' . $result->pspReference );
                    return true;
                endif;
            } catch ( Exception $ex ) {
                $order->add_order_note( 'Curl request failed with: ' . $ex->getMessage() );
            }
            curl_close( $curl );
        } catch ( Exception $ex ) {
            $order->add_order_note( 'Refund processing error: ' . $ex->getMessage() );
        }
    }

}
