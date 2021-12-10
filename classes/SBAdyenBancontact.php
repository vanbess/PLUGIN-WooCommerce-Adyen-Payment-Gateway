<?php

/**
 * Renders BANCONTACT payment option
 *
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @author Werner C. Bessinger @ Silverback Dev
 */
class SBAdyenBancontact extends WC_Payment_Gateway_CC {

    use SBAGetDecimals;

    /**
     * Class constructor
     * Here we set the payment gateway id, 
     * gateway title and description in the backend, 
     * tab title, supports and a bunch of other stuff
     */
    public function __construct() {

        /* method id */
        $this->id = 'sb-adyen-bancontact';

        /* method title */
        $this->method_title = esc_attr__( 'SB Adyen BANCONTACT', 'sb-adyen-bancontact' );

        /* method description */
        $this->method_description = esc_attr__( 'Adyen Bancontact payments.', 'sb-adyen-bancontact' );

        /* gateway icon */
        $this->icon = plugin_dir_url( __FILE__ ) . 'images/bancontact.svg';

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
                        'title'   => esc_attr__( 'Enable / Disable', 'sb-adyen-bancontact' ),
                        'label'   => esc_attr__( 'Enable this payment gateway', 'sb-adyen-bancontact' ),
                        'type'    => 'checkbox',
                        'default' => 'no',
                ],
                'title'   => [
                        'title'       => __( 'Title', 'sb-adyen-bancontact' ),
                        'type'        => 'text',
                        'description' => __( 'Add your custom payment method title here if required.', 'sb-adyen-bancontact' ),
                        'default'     => __( 'Bancontact', 'sb-adyen-bancontact' ),
                ]
        ];
    }

    /* render payment fields */

    public function payment_fields() {

        $client = new \Adyen\Client();

        $client->setXApiKey( wp_specialchars_decode( ADYEN_API_KEY ) );

        if ( ADYEN_GATEWAY_MODE == 'test' ):
            $client->setEnvironment( \Adyen\Environment::TEST );
        else:
            $client->setEnvironment( \Adyen\Environment::LIVE, ADYEN_URL_PREFIX );
        endif;

        try {
            $checkout = new \Adyen\Service\Checkout( $client );
        } catch ( Exception $ex ) {
            file_put_contents( SB_ADYEN_PATH . 'error-logs/bancontact/error.log', $_SERVER[ 'REQUEST_TIME' ] . ': ' . $ex->getMessage() . PHP_EOL, FILE_APPEND );
        }

        $user_country = WC()->customer->get_billing_country();

        $payload = [
                "merchantAccount" => ADYEN_MERCHANT,
                "countryCode"     => $user_country,
                "channel"         => "Web"
        ];

        try {
            $payment_methods = $checkout->paymentMethods( $payload );
        } catch ( Exception $ex ) {
            echo $ex;
        }

        // retrieve gateway mode
        $cc_environment = ADYEN_GATEWAY_MODE;

        // set live modes depending on locale
        if ( isset( $_SERVER[ "HTTP_CF_IPCOUNTRY" ] ) && $cc_environment !== 'test' ) {

            // if oz countries
            if ( in_array( $_SERVER[ "HTTP_CF_IPCOUNTRY" ], SB_AU_COUNTRIES ) ) {
                $cc_environment = "live-au";

                // if EU countries
            } elseif ( in_array( $_SERVER[ "HTTP_CF_IPCOUNTRY" ], SB_EU_COUNTRIES ) ) {
                $cc_environment = "live";

                // else set to US locale
            } else {
                $cc_environment = "live-us";
            }

            // else set test mode so that we are able to test the implementation successfully
        } else {
            $cc_environment = 'test';
        }
        ?>

        <div id="bc-card"></div>

        <input id="bcstateIsValid" type="hidden" name="bcstateIsValid" type="hidden">
        <input id="bcstateData" name="bcstateDataTEST" type="hidden"/>

        <!-- device fingerprint -->
        <input id="adyen_bc_dfp" type="hidden" name="adfp" />

        <input id="adyen-bc-encryptedCardNumber" type="hidden" name="bcstateData[encryptedCardNumber]" />
        <input id="adyen-bc-encryptedExpiryMonth" type="hidden" name="bcstateData[encryptedExpiryMonth]" />
        <input id="adyen-bc-encryptedExpiryYear" type="hidden" name="bcstateData[encryptedExpiryYear]" />
        <input id="adyen-bc-encryptedSecurityCode" type="hidden" name="bcstateData[encryptedSecurityCode]" />
        <input id="adyen-bc-type" name="bcstateData[type]" type="hidden"/>



        <script type="text/javascript">

            function handleOnChange( state, component ) {
                if ( state.data.paymentMethod != undefined ) {

                    if ( "encryptedCardNumber" in state.data.paymentMethod ) {
                        document.getElementById( "adyen-bc-encryptedCardNumber" ).value = state.data.paymentMethod.encryptedCardNumber;
                    }

                    if ( "encryptedExpiryMonth" in state.data.paymentMethod ) {
                        document.getElementById( "adyen-bc-encryptedExpiryMonth" ).value = state.data.paymentMethod.encryptedExpiryMonth;
                    }

                    if ( "encryptedExpiryYear" in state.data.paymentMethod ) {
                        document.getElementById( "adyen-bc-encryptedExpiryYear" ).value = state.data.paymentMethod.encryptedExpiryYear;
                    }

                    if ( "encryptedSecurityCode" in state.data.paymentMethod ) {
                        document.getElementById( "adyen-bc-encryptedSecurityCode" ).value = state.data.paymentMethod.encryptedSecurityCode;
                    }

                    if ( "type" in state.data.paymentMethod ) {
                        document.getElementById( "adyen-bc-type" ).value = state.data.paymentMethod.type;
                    }
                }

                document.getElementById( "bcstateData" ).value = JSON.stringify( state.data.paymentMethod );
                document.getElementById( "bcstateIsValid" ).value = state.isValid;
            }

            const cc_configuration = {
                locale: "<?php echo pll_current_language( 'locale' ); ?>",
                environment: "<?php echo $cc_environment; ?>",
                originKey: "<?php echo ADYEN_ORIGIN_KEY; ?>",
                paymentMethodsResponse: <?php echo json_encode( $payment_methods ); ?>,
                onChange: handleOnChange
            };

            const bc_checkout = new AdyenCheckout( bc_configuration );
            const card = bc_checkout.create( "card", {
                paymentMethodsConfiguration: {
                    card: {
                        hideCVC: true
                    }
                } } ).mount( "#bc-card" );

        </script>
        <?php
    }

    /* process payment */

    public function process_payment($order_id) {

        /* wc global */
        global $woocommerce;

        /* create new customer order */
        $order = new WC_Order( $order_id );

        $this->can_refund_order( $order );

        /* add device fingerprint */
        $order->add_order_note( __( 'Device fingerprint: ' . $_POST[ 'adfp' ], 'woocommerce' ) );

        /* check whether user has filled out all data and then handle the rest if true */
        if ( isset( $_REQUEST[ 'bcstateData' ] ) && !empty( $_REQUEST[ 'bcstateData' ] ) ) {

            /* instantiate adyen class */
            $client = new \Adyen\Client();
            $client->setXApiKey( wp_specialchars_decode( ADYEN_API_KEY ) );

            /* set environment */
            if ( ADYEN_GATEWAY_MODE == 'test' ):
                $client->setEnvironment( \Adyen\Environment::TEST );
            else:
                $client->setEnvironment( \Adyen\Environment::LIVE, ADYEN_URL_PREFIX );
            endif;

            /* initiate checkout service */
            try {
                $checkout = new \Adyen\Service\Checkout( $client );
            } catch ( Exception $ex ) {
                wc_add_notice( __( 'There was an error processing your Bancontact payment: ' . $ex->getMessage(), 'woocommerce' ), 'error' );
                $order->add_order_note( __( 'Bancontact payment error: ' . $ex->getMessage(), 'woocommerce' ) );
            }

            /* set initial order status */
            $order->update_status( 'pending', 'Awaiting Bancontact Card payment', true );

            /* setup vars for curl request */
            $order_currency = alg_get_current_currency_code();

            /* get order total correct decimal count depending on currency used */
            $cart_total_decimals = self::get_decimals( $order_currency );

            // ensure order total is correctly formatted before sending to Adyen (no decimal points or nuttin')
            $order_total = number_format( $order->get_total(), $cart_total_decimals, '', '' );

            $return_url = $this->get_return_url( $order );
        }

        $address_line_1 = $order->get_billing_address_1();
        $address_line_2 = $order->get_billing_address_2();

        if ( !$address_line_1 ):
            $address_line_1 = 'Not supplied';
        endif;

        if ( !$address_line_2 ):
            $address_line_2 = 'Not supplied';
        endif;

        $payload = [
                "merchantAccount"    => ADYEN_MERCHANT,
                "reference"          => $order->get_order_number(),
                "amount"             => [
                        "currency" => $order_currency,
                        "value"    => $order_total
                ],
                "returnUrl"          => $return_url,
                "fraudOffset"        => "0",
                "additionalData"     => [
                        "card.encrypted.json" => $_REQUEST[ 'adyen-encrypted-data' ],
                        "executeThreeD"       => false,
                ],
                "deviceFingerprint"  => $_POST[ 'adfp' ],
                "billingAddress"     => [
                        "city"              => $order->get_billing_city(),
                        "country"           => $order->get_billing_country(),
                        "houseNumberOrName" => $address_line_1,
                        "postalCode"        => $order->get_billing_postcode(),
                        "stateOrProvince"   => $order->get_billing_state(),
                        "street"            => $address_line_2,
                ],
                "browserInfo"        => [
                        "acceptHeader" => $_SERVER[ 'HTTP_ACCEPT' ],
                        "userAgent"    => $_SERVER[ 'HTTP_USER_AGENT' ]
                ],
                "shopperName"        => [
                        "firstName" => $order->get_billing_first_name(),
                        "gender"    => "UNKNOWN",
                        "lastName"  => $order->get_billing_last_name(),
                ],
                "shopperEmail"       => $order->get_billing_email(),
                "shopperIP"          => $order->get_customer_ip_address(),
                "telephoneNumber"    => trim( $order->get_billing_phone() ),
                "shopperReference"   => $order->get_user_id(),
                "storePaymentMethod" => true
        ];

        $payload[ "paymentMethod" ] = $_REQUEST[ 'bcstateData' ];

        file_put_contents( SB_ADYEN_PATH . 'tests/bancontact_statedata.txt', print_r( $_REQUEST[ 'bcstateData' ], true ) );

        /* process payment */
        try {
            $payment_request = $checkout->payments( $payload );

            /* check if appropriate response was received */
            if ( isset( $payment_request[ 'resultCode' ] ) && $payment_request[ 'resultCode' ] == 'Authorised' ):

                //$order->add_order_note('Request data: ' . json_encode($payment_request));
                // Payment successful
                $order->add_order_note( esc_attr__( 'Payment completed. Adyen Reference: ' . $payment_request[ 'pspReference' ] . ". Card Issuing Country: " . $payment_request[ 'additionalData' ][ 'cardIssuingCountry' ], 'sb-adyen-cc' ) );
                $order->add_order_note( json_encode( $payment_request ) );
                $order->payment_complete( $payment_request[ 'pspReference' ] );

                // add order payment method
                update_post_meta( $order_id, '_payment_method_title', 'Bancontact Card' );

                // capture payment
                sb_adyen_capture_payment( $order, $payment_request[ 'pspReference' ] );

                // empty cart
                $woocommerce->cart->empty_cart();

                // return user to order success page
                return [
                        'result'   => 'success',
                        'redirect' => $return_url
                ];

            else:
                $refusalReason = $payment_request[ 'refusalReason' ];

                $error_messages[ "CVC Declined" ]              = SBA_CVC_DECLINED;
                $error_messages[ "Expired Card" ]              = SBA_EXPIRED_CARD;
                $error_messages[ "Invalid Card Number" ]       = SBA_INVALID_CARD_NO;
                $error_messages[ "Unknown" ]                   = SBA_UNKNOWN;
                $error_messages[ "Refused" ]                   = SBA_REFUSED;
                $error_messages[ "Transaction Not Permitted" ] = SBA_TRANS_NOT_PERM;
                $error_messages[ "FRAUD" ]                     = SBA_FRAUD;
                $error_messages[ "FRAUD-CANCELLED" ]           = SBA_FRAUD_CANCELLED;
                $error_messages[ "Declined Non Generic" ]      = SBA_VALIDATION;
                $error_messages[ "Not enough balance" ]        = SBA_VALIDATION_2;

                if ( isset( $payment_request[ 'refusalReason' ] ) && isset( $error_messages[ $payment_request[ 'refusalReason' ] ] ) ):
                    $refusalReason = $error_messages[ $payment_request[ 'refusalReason' ] ];
                else:
                    $refusalReason = "There was an error processing your credit card. Please try again with PayPal.";
                endif;

                if ( isset( $payment_request[ 'errorType' ] ) && $payment_request[ 'errorType' ] == "validation" ):
                    $refusalReason = "There was an error processing your credit card. Please check your card number and expiration date and try again.";
                endif;

                // if transaction failed
                wc_add_notice( pll__( $refusalReason ), 'error' );
                $order->add_order_note( 'Error: Payment Failure. Result code: ' . $payment_request[ 'resultCode' ] );
                $order->add_order_note( 'Adyen Error: ' . $refusalReason . " (" . $payment_request[ 'errorType' ] . ")" . ', Code: ' . $payment_request[ 'resultCode' ] . " | Refusal  Reason: " . $payment_request[ 'refusalReason' ] );
            endif;

            // catch error if our payment request fails for some reason    
        } catch ( Exception $ex ) {
            wc_add_notice( pll__( "There was an error processing your credit card. Please check your card number and expiration date and try again. Error: " . $ex->getMessage() ), 'error' );
            $order->add_order_note( 'Error: ' . $ex->getMessage() );
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
