<?php

/**
 * Class to setup Adyen Ideal payment gateway
 *
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @author Werner C. Bessinger @ Silverback Dev
 */
class SBAdyenIdeal extends WC_Payment_Gateway {

    /**
     * Class constructor
     * Here we set the payment gateway id, 
     * gateway title and description in the backend, 
     * tab title, supports and a bunch of other stuff
     */
    public function __construct() {

        /* method id */
        $this->id = 'sb-adyen-ideal';

        /* method title */
        $this->method_title = esc_attr__( 'SB Adyen iDEAL', 'sb-adyen-ideal' );

        /* method description */
        $this->method_description = 'Adyen iDEAL payments.';

        /* gateway icon */
        $this->icon = plugin_dir_url( __FILE__ ) . 'images/iDEAL-klein.gif';

        /* display frontend title */
        $this->title = $this->get_option( 'title' );

        /* add support for refunds */
        $this->supports = [ 'refunds' ];

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
                        'title'   => esc_attr__( 'Enable / Disable', 'sb-adyen-ideal' ),
                        'label'   => esc_attr__( 'Enable this payment gateway', 'sb-adyen-ideal' ),
                        'type'    => 'checkbox',
                        'default' => 'no',
                ],
                'title'   => [
                        'title'       => __( 'Title', 'sb-adyen-ideal' ),
                        'type'        => 'text',
                        'description' => __( 'Add your custom payment method title here if required.', 'sb-adyen-ideal' ),
                        'default'     => __( 'Finnish Online Banking', 'sb-adyen-ideal' ),
                ]
        ];
    }

    /**
     * Render ideal payment fields
     */
    public function payment_fields() {

        /* set ideal merchant list based on gateway mode */
        if ( ADYEN_GATEWAY_MODE == 'test' ):

            $ideal_merchants = [
                    '1121' => 'Test Issuer 1',
                    '1151' => 'Test Issuer 2',
                    '1152' => 'Test Issuer 3',
                    '1153' => 'Test Issuer 4',
                    '1154' => 'Test Issuer 5',
                    '1155' => 'Test Issuer 6',
                    '1156' => 'Test Issuer 7',
                    '1157' => 'Test Issuer 8',
                    '1158' => 'Test Issuer 9',
                    '1159' => 'Test Issuer 10',
                    '1160' => 'Test Issuer Refused',
                    '1161' => 'Test Issuer Pending',
                    '1162' => 'Test Issuer Cancelled',
            ];

        elseif ( ADYEN_GATEWAY_MODE == 'live' ):

            $ideal_merchants = [
                    '0031' => 'ABN AMRO',
                    '0761' => 'ASN Bank',
                    '0802' => 'bunq',
                    '0804' => 'Handelsbanken',
                    '0721' => 'ING Bank',
                    '0801' => 'Knab',
                    '0803' => 'Moneyou',
                    '0021' => 'Rabobank',
                    '0771' => 'Regiobank',
                    '0751' => 'SNS Bank',
                    '0511' => 'Triodos Bank',
                    '0161' => 'Van Lanschot Bankiers',
            ];

        endif;

        /* render select/radios for each iDEAL merchant */
        ?>
        <!-- ideal merchant cont -->
        <div id="ideal_merchant_cont">

            <select id="ideal_merchant" name="ideal_merchant" class="form-control">
                <option value="">Selecteer je bank</option>

                <?php foreach ( $ideal_merchants as $id => $name ):
                    ?>

                    <option value="<?php echo $id; ?>"><?php echo $name; ?></option>

                <?php endforeach; ?>
            </select>
        </div>

        <?php
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
        $order->update_status( 'pending', __( 'Awaiting iDEAL payment', 'woocommerce' ) );

        /* setup vars for curl request */
        $order_total = $order->get_total();

        /* formatted total */
        $formatted_total = number_format( $order_total, 2, '', '' );

        $return_url         = $this->get_return_url( $order );
        $idealmerchant      = $_POST[ 'ideal_merchant' ];
        $adyen_api_key      = wp_specialchars_decode( ADYEN_API_KEY );
        $adyen_merchant_acc = ADYEN_MERCHANT;
        $adyen_url_prefix   = ADYEN_URL_PREFIX;

        /* start api request */
        $client = new \Adyen\Client();
        $client->setXApiKey( $adyen_api_key );

        /* deterimine current gateway mode and set environment accordingly */
        if ( ADYEN_GATEWAY_MODE == 'test' ):
            $client->setEnvironment( \Adyen\Environment::TEST );
        else:
            $client->setEnvironment( \Adyen\Environment::LIVE, $adyen_url_prefix );
        endif;

        try {
            $checkout = new \Adyen\Service\Checkout( $client );
        } catch ( Exception $ex ) {
            file_put_contents( SB_ADYEN_PATH . 'error-logs/ideal/error.log', $_SERVER[ 'REQUEST_TIME' ] . ': ' . $ex->getMessage() . PHP_EOL, FILE_APPEND );
        }

        $payload = [
                "amount"             => [
                        "currency" => alg_get_current_currency_code(),
                        "value"    => $formatted_total
                ],
                "reference"          => $order->get_order_number(),
                "paymentMethod"      => [
                        "type"   => "ideal",
                        "issuer" => $idealmerchant
                ],
                "returnUrl"          => $return_url,
                "merchantAccount"    => $adyen_merchant_acc,
                "shopperReference"   => $order->get_user_id(),
                "storePaymentMethod" => true
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
