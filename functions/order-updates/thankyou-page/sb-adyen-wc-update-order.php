<?php
/* created by Werner C. Bessinger @ Silverback Dev Studios */

/* prevent direct access */
if ( !defined( 'ABSPATH' ) ):
    exit;
endif;

add_action( 'woocommerce_thankyou', 'sb_adyen_custom_ty_page' );

function sb_adyen_custom_ty_page() {

    // check for resultCode key in URL - only present for Adyen based payment methods
    if ( !empty( $_GET[ 'resultCode' ] ) ):

        // get result code
        $resultCode = $_GET[ 'resultCode' ];

        // get wc order key
        $wc_order_key = $_GET[ 'key' ];

        // get wc order id by wc order key
        $order_id = wc_get_order_id_by_order_key( $wc_order_key );

        // get order data
        $order_data = new WC_Order( $order_id );

        // define adyen payment methods array
        $adyen_payment_methods = [
                'sb-adyen-ideal'      => 'iDEAL',
                'sb-adyen-sofort'     => 'Klarna/Sofort',
                'sb-adyen-bancontact' => 'Bancontact',
                'sb-adyen-cc'         => 'Credit Card',
                'sb-adyen-multibanco' => 'Mulitbanco',
                'sb-adyen-fob'        => 'Finnish Online Banking',
                'sb-adyen-poli'       => 'Poli',
                'sb-adyen-eps'        => 'EPS',
                'sb-adyen-korean-cc'  => 'Korean CC',
                'sb-adyen-payco'      => 'PayCo',
                'sb-adyen-kcp'        => 'Korean Online Banking',
                'sb-adyen-boleto'     => 'Boleto',
                'sb-adyen-gpay'       => 'GPay',
                'sb-adyen-mepay'      => 'Molpay Epay',
                'sb-adyen-mcash'      => 'Molpay Cash (7-Eleven MY)',
                'sb-adyen-apt'        => 'Afterpay Touch',
                'sb-adyen-yamon'      => 'Yandex Money',
                'sb-adyen-swish'      => 'Swish',
                'sb-adyen-qiwi'       => 'Qiwi'
        ];

        // get order payment method
        $pmt_method        = $order_data->get_payment_method();
        $pmt_method_purrty = $adyen_payment_methods[ $pmt_method ];

        // get checkout page url
        $checkout_page_url = wc_get_checkout_url();

        // check if payment method matches array key in adyen payment methods array
        if ( array_key_exists( $pmt_method, $adyen_payment_methods ) ):

            // if resultcode is not authorised, replace thank you page content with custom content
            if ( $resultCode !== 'authorised' ):
                ?>

                <!-- custom adyen transaction error -->
                <script type="text/javascript" id="custom-adyen-transaction-error">
                    ( function ( $ ) {

                        $( '#content > div > div.woocommerce > div > div.large-5.col' ).css( 'visibility', 'hidden' );
                        $( '#content > div > div.woocommerce > div > div.large-7.col' ).addClass( 'large-12' );

                        $( '#content > div > div.cart-header.text-left.medium-text-center > nav > a.no-click.current' ).text( '<?php _e( 'Order Failed', 'woocommerce' ); ?>' );
                        $( '#content > div > div.cart-header.text-left.medium-text-center > nav > a.no-click.current' ).css( 'color', 'red' );

                        $( '#content > div > div.woocommerce' ).prepend( '<span id="sb-adyen-order-failed"><?php
                _e( 'Unfortunately your payment via <b>' . $pmt_method_purrty . '</b> was unsuccessful. '
                    . 'Please return to the <a href="' . $checkout_page_url . '"><u>checkout page</u></a> and try again, or use a different payment method.', 'woocommerce' );
                ?></span>' );

                    } )( jQuery );
                </script>

                <!-- error message style -->
                <style>
                    span#sb-adyen-order-failed {
                        display: block;
                        margin-bottom: 30px;
                        border: 1px dashed #ff00001c;
                        padding: 15px;
                        text-align: center;
                        font-weight: 500;
                        box-shadow: 0px 2px 5px #0000001c;
                        margin-top: 10px;
                        color: #6f6f6f;
                        background: #ff00001c;
                    }

                    #content > div > div.woocommerce > div > div.large-5.col{
                        display: none !important;
                    }
                </style>

                <?php
                // mark order as cancelled
                $order_data->update_status( 'pending' );

                // update relevant order meta
                update_post_meta( $order_id, '_payment_method_title', $adyen_payment_methods[ $pmt_method ] );
                update_post_meta( $order_id, '_adyen_payload', $_GET[ 'payload' ] );

                // get psp ref from Adyen
                $psp_ref = sb_adyen_retrieve_psp_ref( $_GET[ 'payload' ] );

                // add psp ref to db
                update_post_meta( $order_id, '_adyen_psp_ref', $psp_ref );

            // handle multibanco voucher payments
            elseif ( !empty( $_GET[ 'mb_voucher_ref' ] ) ):

                function sb_adyen_mb_change_order_received_text($str, $order) {
                    $new_str = $str . ' <br><br> <span style="color: black; font-weight: normal;">- Multibanco ref: </span>'
                        . '<span style="color: black;">' . $_GET[ 'mb_voucher_ref' ] . '<br>'
                        . '<span style="color: black; font-weight: normal;">- Please pay by: </span>'
                        . '<span style="color: black;">' . date( 'j F Y', strtotime( '+' . $_GET[ 'mb_voucher_deadline' ] . ' days' ) ) . '</span>';
                    return $new_str;
                }

                add_filter( 'woocommerce_thankyou_order_received_text', 'sb_adyen_mb_change_order_received_text', 10, 2 );

                update_post_meta( $order_id, '_payment_method_title', 'Multibanco' );
                update_post_meta( $order_id, '_adyen_payload', $_GET[ 'payload' ] );

                // get psp ref from Adyen
                $psp_ref = sb_adyen_retrieve_psp_ref( $_GET[ 'payload' ] );

                // add psp ref to db
                update_post_meta( $order_id, '_adyen_psp_ref', $psp_ref );

                // capture payment on Adyen's side
                sb_adyen_capture_payment( $order_data, $psp_ref );

            // handle successful payments
            elseif ( $resultCode == 'authorised' ):

                // mark order as payment complete
                if ( $pmt_method != "sb-adyen-cc" ):
                    $order_data->payment_complete();
                endif;

                // empty wc cart
                wc()->cart->empty_cart();

                // update relevant order meta
                update_post_meta( $order_id, '_payment_method_title', $adyen_payment_methods[ $pmt_method ] );
                update_post_meta( $order_id, '_adyen_payload', $_GET[ 'payload' ] );

                // get psp ref from Adyen
                $psp_ref = sb_adyen_retrieve_psp_ref( $_GET[ 'payload' ] );

                if ( $psp_ref ):
                    // add psp ref to db
                    update_post_meta( $order_id, '_adyen_psp_ref', $psp_ref );

                    // capture payment on Adyen's side
                    sb_adyen_capture_payment( $order_data, $psp_ref );
                endif;

            /* NOTE: CREDIT CARD CAPTURES ARE HANDLED IN PAYMENT GATEWAY CLASS DUE TO IMPLEMENTATION METHOD USED */

            endif;
        endif;
    endif;

    // *****************************************************
    // UPDATE FOR ALIPAY PAYMENT METHOD - ADDED 2 AUG 2021
    // *****************************************************
    if ( isset( $_GET[ 'redirectResult' ] ) ):

        // retrieve redirect result
        $redirect_result = $_GET[ 'redirectResult' ];

        // get wc order key
        $wc_order_key = $_GET[ 'key' ];

        // get wc order id by wc order key
        $order_id = wc_get_order_id_by_order_key( $wc_order_key );

        // retrieve order
        $order = wc_get_order( $order_id );

        // retrieve payment method
        $pmt_method = $order->get_payment_method();

        // retrieve payment result via curl request
        try {

            // url prefix
            $url_prefix = ADYEN_URL_PREFIX;

            // setup request link
            if ( ADYEN_GATEWAY_MODE == 'test' ):
                $curl_url = "https://checkout-test.adyen.com/v67/payments/details";
            else:
                $curl_url = "https://$url_prefix-checkout-live.adyenpayments.com/checkout/v67/payments/details";
            endif;

            // setup payload
            $payload = [
                    'details' => [
                            'redirectResult' => $redirect_result
                    ]
            ];

            // retrieve api key
            $x_api_key = wp_specialchars_decode( ADYEN_API_KEY );

            // init curl
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

                // retrieve result
                $result = json_decode( $response, true );

                // if result returned
                if ( isset( $result ) ):

                    // retrieve psp ref and result code
                    $psp_ref     = $result[ 'pspReference' ];
                    $result_code = $result[ 'resultCode' ];

                    // update post meta with psp ref
                    update_post_meta( $order_id, '_adyen_psp_ref', $psp_ref );

                    // add order note with above details
                    if ( $pmt_method === 'sb-adyen-alipay-hk' ):
                        $order->add_order_note( 'Alipay HK payment status: ' . $result_code . ' <br>PSP Ref: ' . $psp_ref );
                    elseif ( $pmt_method === 'sb-adyen-alipay' ):
                        $order->add_order_note( 'Alipay payment status: ' . $result_code . ' <br>PSP Ref: ' . $psp_ref );
                    endif;

                    // if payment authorised, update order status
                    if ( $result_code === 'Authorised' ):

                        // empty wc cart
                        wc()->cart->empty_cart();

                        // update order payment status
                        $order->payment_complete( $psp_ref );

                        // capture payment
                        sb_adyen_capture_payment( $order, $psp_ref );

                    endif;

                    return true;
                endif;
            } catch ( Exception $ex ) {
                $order->add_order_note( 'Curl request failed with: ' . $ex->getMessage() );
            }
            curl_close( $curl );
        } catch ( Exception $ex ) {
            $order->add_order_note( 'Failed to retrieve AliPay payment data. Error returned: ' . $ex->getMessage() );
        }

    endif;
}
