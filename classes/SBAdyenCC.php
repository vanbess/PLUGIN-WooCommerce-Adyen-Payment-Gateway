<?php
/*
 * Renders BANCONTACT payment option
 *
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @author Werner C. Bessinger @ Silverback Dev
 */

class SBAdyenCC extends WC_Payment_Gateway_CC {
    /*
     * Class constructor
     * Here we set the payment gateway id, 
     * gateway title and description in the backend, 
     * tab title, supports and a bunch of other stuff
     */

    public function __construct() {

        // method id
        $this->id = 'sb-adyen-cc';

        // method title
        $this->method_title = 'SB Adyen CREDIT CARD';

        // method description
        $this->method_description = esc_attr__( 'Adyen Credit Card payments.', 'sb-adyen-cc' );

        // gateway icon
        $this->icon = plugin_dir_url( __FILE__ ) . 'images/cc-logo.png';

        // display frontend title
        $this->title = $this->get_option( 'title' );

        // add refund support
        $this->supports = [ 'refunds' ];

        // gateway has fields
        $this->has_fields = true;

        // init gateway form fields
        $this->settings_fields();

        // load our settings
        $this->init_settings();

        // save gateway settings when save settings button clicked IF user is admin
        if ( is_admin() ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        // register polylang strings
        pll_register_string( "adyen_cvc_declined", SBA_CVC_DECLINED, "sb-adyen-cc" );
        pll_register_string( "adyen_expired_card", SBA_EXPIRED_CARD, "sb-adyen-cc" );
        pll_register_string( "adyen_invalid_card_number", SBA_INVALID_CARD_NO, "sb-adyen-cc" );
        pll_register_string( "adyen_unknown", SBA_UNKNOWN, "sb-adyen-cc" );
        pll_register_string( "adyen_refused", SBA_REFUSED, "sb-adyen-cc" );
        pll_register_string( "adyen_transaction_not_permitted", SBA_TRANS_NOT_PERM, "sb-adyen-cc" );
        pll_register_string( "adyen_fraud", SBA_FRAUD, "sb-adyen-cc" );
        pll_register_string( "adyen_fraud_cancelled", SBA_FRAUD_CANCELLED, "sb-adyen-cc" );
        pll_register_string( "adyen_declined", SBA_DECLINED, "sb-adyen-cc" );
        pll_register_string( "adyen_validation", SBA_VALIDATION, "sb-adyen-cc" );
        pll_register_string( "adyen_validation_2", SBA_VALIDATION_2, "sb-adyen-cc" );
    }

    // Set up WooCommerce payment gateway settings fields
    public function settings_fields() {

        $this->form_fields = [
                // enable/disable
                'enabled' => [
                        'title'   => esc_attr__( 'Enable / Disable', 'sb-adyen-cc' ),
                        'label'   => esc_attr__( 'Enable this payment gateway', 'sb-adyen-cc' ),
                        'type'    => 'checkbox',
                        'default' => 'no',
                ],
                'title'   => [
                        'title'       => __( 'Title', 'sb-adyen-adyen-cc' ),
                        'type'        => 'text',
                        'description' => __( 'Add your custom payment method title here if required.', 'sb-adyen-adyen-cc' ),
                        'default'     => __( 'Adyen Credit Card', 'sb-adyen-adyen-cc' ),
                ]
        ];
    }

    // render payment fields

    public function payment_fields() {

        // *******************
        // FRAUD CHECK STARTS
        // *******************
        $cart_hash = $_COOKIE[ 'woocommerce_cart_hash' ];

        // get user ip and user id
        $user_ip = $_COOKIE[ 'adyen_cc_block_user_ip' ];
        $user_id = $_COOKIE[ 'adyen_cc_block_user_id' ];

        // hide adyen cc if cookies are set
        if ( $user_ip || $user_id ):
            wp_enqueue_script( 'sbadyen_af', SB_ADYEN_URL . 'assets/hide.cc.js', [ 'jquery' ], '1.0.0', true );
        endif;

        // check current order fraud check score
        $orderq = new WP_Query( [
                'post_type'      => 'shop_order',
                'post_status'    => 'wc-pending',
                'posts_per_page' => -1,
                'meta_key'       => '_cart_hash',
                'meta_value'     => $cart_hash
            ] );

        if ( $orderq->have_posts() ):
            while ( $orderq->have_posts() ):$orderq->the_post();
                $curr_order_fraud_score = get_post_meta( get_the_ID(), '_adyen_cc_fraud_check', true );
                $order_id               = get_the_ID();
            endwhile;
        endif;

        // hide adyen cc for 24 hours if fraud score is met
        if ( $curr_order_fraud_score == 5 ):

            // ip based cookie
            $user_location = new WC_Geolocation();
            $user_ip       = $user_location->get_ip_address();
            setcookie( 'adyen_cc_block_user_ip', $user_ip, time() + 86400, '/' );

            // user based cookie
            $user_id = get_current_user_id();
            setcookie( 'adyen_cc_block_user_id', $user_id, time() + 86400, '/' );

            // reset order fraud score
            update_post_meta( $order_id, '_adyen_cc_fraud_check', 0 );

        endif;

        // ******************
        // FRAUD CHECK ENDS
        // ******************

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
            file_put_contents( SB_ADYEN_PATH . 'error-logs/cc/error.log', $_SERVER[ 'REQUEST_TIME' ] . ': ' . $ex->getMessage() . PHP_EOL, FILE_APPEND );
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
            file_put_contents( SB_ADYEN_PATH . 'error-logs/cc/error.log', $_SERVER[ 'REQUEST_TIME' ] . ': ' . $ex->getMessage() . PHP_EOL, FILE_APPEND );
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

        <div id="cc-card"></div>

        <input id="ccstateIsValid" type="hidden" name="stateIsValid" type="hidden">
        <input id="ccstateData" name="stateDataTEST" type="hidden"/>

        <!-- device fingerprint -->
        <input id="adyen_cc_dfp" type="hidden" name="adfp" />

        <input id="adyen-cc-encryptedCardNumber" type="hidden" name="stateData[encryptedCardNumber]" />
        <input id="adyen-cc-encryptedExpiryMonth" type="hidden" name="stateData[encryptedExpiryMonth]" />
        <input id="adyen-cc-encryptedExpiryYear" type="hidden" name="stateData[encryptedExpiryYear]" />
        <input id="adyen-cc-encryptedSecurityCode" type="hidden" name="stateData[encryptedSecurityCode]" />
        <input id="adyen-cc-type" name="stateData[type]" type="hidden"/>

        <script type="text/javascript">

            function handleOnChange( state, component ) {

                if ( state.data.paymentMethod !== undefined ) {

                    if ( "encryptedCardNumber" in state.data.paymentMethod ) {
                        document.getElementById( "adyen-cc-encryptedCardNumber" ).value = state.data.paymentMethod.encryptedCardNumber;
                    }

                    if ( "encryptedExpiryMonth" in state.data.paymentMethod ) {
                        document.getElementById( "adyen-cc-encryptedExpiryMonth" ).value = state.data.paymentMethod.encryptedExpiryMonth;
                    }

                    if ( "encryptedExpiryYear" in state.data.paymentMethod ) {
                        document.getElementById( "adyen-cc-encryptedExpiryYear" ).value = state.data.paymentMethod.encryptedExpiryYear;
                    }

                    if ( "encryptedSecurityCode" in state.data.paymentMethod ) {
                        document.getElementById( "adyen-cc-encryptedSecurityCode" ).value = state.data.paymentMethod.encryptedSecurityCode;
                    }

                    if ( "type" in state.data.paymentMethod ) {
                        document.getElementById( "adyen-cc-type" ).value = state.data.paymentMethod.type;
                    }
                }

                document.getElementById( "ccstateData" ).value = JSON.stringify( state.data.paymentMethod );
                document.getElementById( "ccstateIsValid" ).value = state.isValid;
            }

            cc_configuration = {
                locale: "<?php echo pll_current_language( 'locale' ); ?>",
                environment: "<?php echo $cc_environment; ?>",
                clientKey: "<?php echo ADYEN_ORIGIN_KEY; ?>",
                paymentMethodsResponse: <?php echo json_encode( $payment_methods ); ?>,
                onChange: handleOnChange
            };

            cc_checkout = new AdyenCheckout( cc_configuration );
            card = cc_checkout.create( "card" ).mount( "#cc-card" );

        </script>

        <?php
    }

    // process payment

    public function process_payment($order_id) {

        // wc global
        global $woocommerce;

        // create new customer order
        $order = new WC_Order( $order_id );

        $this->can_refund_order( $order );

        // add device fingerprint
        $order->add_order_note( 'Device fingerprint: ' . $_POST[ 'adfp' ] );

        // check whether user has filled out all data and then handle the rest if true
        if ( isset( $_REQUEST[ 'stateData' ] ) && !empty( $_REQUEST[ 'stateData' ] ) && !empty( $_REQUEST[ 'stateData' ][ 'type' ] ) ) {

            // instantiate adyen class
            $client = new \Adyen\Client();
            $client->setXApiKey( wp_specialchars_decode( ADYEN_API_KEY ) );

            // set environment
            if ( ADYEN_GATEWAY_MODE == 'test' ):
                $client->setEnvironment( \Adyen\Environment::TEST );
            else:
                $client->setEnvironment( \Adyen\Environment::LIVE, ADYEN_URL_PREFIX );
            endif;

            // initiate checkout service
            try {
                $checkout = new \Adyen\Service\Checkout( $client );
            } catch ( Exception $ex ) {
                wc_add_notice( __( 'Unfortunately an error occured: ' . $ex->getMessage(), 'woocommerce' ), 'error' );
                $order->add_order_note( __( 'Adyen Credit Card payment error: ' . $ex->getMessage(), 'woocommerce' ) );
            }

            // set initial order status
            $order->update_status( 'pending', 'Awaiting Credit Card payment', true );

            // setup vars for curl request
            $order_currency = alg_get_current_currency_code();

            // get order total correct decimal count depending on currency used
            $cart_total_decimals = sb_adyen_set_currency_decimal( $order_currency );

            // ensure order total is correctly formatted before sending to Adyen (no decimal points or nuttin')
            $order_total = number_format( $order->get_total(), $cart_total_decimals, '', '' );

            $return_url = $this->get_return_url( $order );

            $address_line_1 = $order->get_billing_address_1();
            $address_line_2 = $order->get_billing_address_2();
            $city           = $order->get_billing_city();

            if ( !$address_line_1 ):
                $address_line_1 = 'Not supplied';
            endif;

            if ( !$address_line_2 ):
                $address_line_2 = 'Not supplied';
            endif;

            if ( !$city && $order->get_billing_country() == "SG" ):
                $city = 'Singapore';
            endif;

            if ( empty( $city ) ):
                $city = $order->get_billing_state();
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
                            "city"              => $city,
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
                    "shopperReference"   => $order->get_order_number(),
                    "storePaymentMethod" => true
            ];

            $payload[ "paymentMethod" ] = $_REQUEST[ 'stateData' ];

            // process payment
            try {
                $payment_request = $checkout->payments( $payload );

                // check if appropriate response was received
                if ( isset( $payment_request[ 'resultCode' ] ) && $payment_request[ 'resultCode' ] == 'Authorised' ):

                    // check if CC fraud check is set to yes
                    if ( get_option( 'sb_adyen_cc_fraud_check' ) == 'yes' ):

                        // get card issuing country from transaction
                        $card_issuing_country = $payment_request[ 'additionalData' ][ 'cardIssuingCountry' ];

                        // get cloudflare user country
                        $cloudflare_user_country = $_SERVER[ 'HTTP_CF_IPCOUNTRY' ];

                        if ( $card_issuing_country != $cloudflare_user_country ):
                            // Payment successful BUT possible fraud
                            $order->add_order_note( esc_attr__( 'Payment completed: ***POSSIBLE FRAUD***. Adyen Reference: ' . $payment_request[ 'pspReference' ] . ". Card Issuing Country: " . $payment_request[ 'additionalData' ][ 'cardIssuingCountry' ] . " User Country: $cloudflare_user_country", 'sb-adyen-cc' ) );
                            $order->add_order_note( json_encode( $payment_request ) );
                            $order->payment_complete( $payment_request[ 'pspReference' ] );
                        endif;
                    else:
                        // Payment successful
                        $order->add_order_note( esc_attr__( 'Payment completed. Adyen Reference: ' . $payment_request[ 'pspReference' ] . ". Card Issuing Country: " . $payment_request[ 'additionalData' ][ 'cardIssuingCountry' ], 'sb-adyen-cc' ) );
                        $order->add_order_note( json_encode( $payment_request ) );
                        $order->payment_complete( $payment_request[ 'pspReference' ] );
                    endif;

                    // add order payment method
                    update_post_meta( $order_id, '_payment_method_title', 'Credit Card' );

                    $order->add_order_note( 'Card Issuing Country: ' . $card_issuing_country . ', cvcResult: ' . $payment_request[ 'additionalData' ][ 'cardIssuingBank' ] . ', avsResult: ' . $payment_request[ 'additionalData' ][ 'avsResult' ] . ', Browser Language: ' . $_SERVER[ 'HTTP_ACCEPT_LANGUAGE' ] );

                    // capture payment
                    sb_adyen_capture_payment( $order, $payment_request[ 'pspReference' ] );

                    // empty cart
                    $woocommerce->cart->empty_cart();

                    // cc fraud check - set final status to followup if conditional met
                    if ( (get_option( 'sb_adyen_cc_fraud_check' ) == 'yes' && $card_issuing_country != $cloudflare_user_country) || $card_issuing_country == "BR" ):
                        wp_update_post( [ 'ID' => $order->get_id(), 'post_status' => 'wc-followup' ] );
                        $order->add_order_note( __( 'Order status changed from Processing to Followup due to possible fraud', 'woocommerce' ) );
                    endif;

                    // return user to order success page
                    return [
                            'result'   => 'success',
                            'redirect' => $return_url
                    ];

                else:

                    // **********************
                    // ADD FRAUD CHECK META
                    // **********************

                    if ( get_post_meta( $order_id, '_adyen_cc_fraud_check', true ) ):
                        $fcincrement  = get_post_meta( $order_id, '_adyen_cc_fraud_check', true );
                        settype( $fcincrement, 'int' );
                        $newincrement = $fcincrement  += 1;
                        update_post_meta( $order_id, '_adyen_cc_fraud_check', $newincrement );
                    else:
                        update_post_meta( $order_id, '_adyen_cc_fraud_check', 1 );
                    endif;

                    // **************************
                    // ADD FRAUD CHECK META ENDS
                    // **************************

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
                        $refusalReason = "There was an error processing your credit card. Please check your card number, expiration date, and CVC code and try again.";
                    endif;

                    // if transaction failed
                    wc_add_notice( __( $refusalReason ), 'error' );
                    $order->add_order_note( __( 'Error: Payment Failure. Result code: ' . $payment_request[ 'resultCode' ], 'woocommerce' ) );
                    $order->add_order_note( __( 'Adyen Error: ' . $refusalReason . " (" . $payment_request[ 'errorType' ] . ")" . ', Code: ' . $payment_request[ 'resultCode' ] . " | Refusal  Reason: " . $payment_request[ 'refusalReason' ], 'woocommerce' ) );
                endif;

                // catch error if our payment request fails for some reason    
            } catch ( Exception $ex ) {
                wc_add_notice( __( "There was an error processing your credit card. Please check your card number, expiration date, and CVC code and try again.", 'woocommerce' ), 'error' );
                $order->add_order_note( __( 'Adyen Credit Card payment error: ' . $ex->getMessage(), 'woocommerce' ) );
            }
        } else {
            wc_add_notice( __( "There was an error processing your credit card. Please check your card number, expiration date, and CVC code and try again.", 'woocommerce' ), 'error' );
            $order->add_order_note( __( 'Error: no Adyen CC credit card number, expiration date or CVC', 'woocommerce' ) );
        }
    }

    /*
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

        $transaction_id = $order->get_transaction_id();

        if ( !$transaction_id || empty( $transaction_id ) ) {
            return new WP_Error( 'sb_adyen_refund_error', __( 'No valid Transaction ID found', 'woocommerce' ) );
        }

        if ( is_null( $amount ) || $amount <= 0 ) {
            return new WP_Error( 'sb_adyen_refund_error', __( 'Amount not valid', 'woocommerce' ) );
        }

        if ( is_null( $reason ) || '' == $reason ) {
            $reason = sprintf( __( 'Refund for Order %s', 'woocommerce' ), $order->get_order_number() );
        }

        // SETUP REFUND PARAMS
        // order currency
        $order_currency = $order->get_currency();

        // currency decimals
        $decimals = sb_adyen_set_currency_decimal( $order_currency );

        // formatted refund amount
        $refund_amount = number_format( $amount, $decimals, '', '' );

        // setup refund payload
        $payload = [
                "merchantAccount"    => ADYEN_MERCHANT,
                "modificationAmount" => [
                        "value"    => $refund_amount,
                        "currency" => $order_currency
                ],
                "originalReference"  => $transaction_id,
                "reference"          => $order->get_order_number(),
                "browserInfo"        => [
                        "acceptHeader" => $_SERVER[ 'HTTP_USER_AGENT' ],
                        "userAgent"    => $_SERVER[ 'HTTP_ACCEPT' ]
                ]
        ];

        try {

            // INIT CURL REQUEST
            // url prefix
            $url_prefix = ADYEN_URL_PREFIX;

            // setup request link
            if ( ADYEN_GATEWAY_MODE == 'test' ):
                $curl_url = "https://pal-test.adyen.com/pal/servlet/Payment/v64/refund";
            else:
                $curl_url = "https://$url_prefix-pal-live.adyenpayments.com/pal/servlet/Payment/v64/refund";
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
