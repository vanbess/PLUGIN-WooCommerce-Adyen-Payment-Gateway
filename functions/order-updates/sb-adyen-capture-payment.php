<?php

/* created by Werner C. Bessinger @ Silverback Dev Studios */

/* prevent direct access */
if (!defined('ABSPATH')) :
    exit;
endif;

/**
 * Captures adyen payments on successful transaction completion; used in sb-adyen-notifications.php
 * 
 * @param type $order_data -> data object for which payment should be captured
 */
function sb_adyen_capture_payment($order_data, $psp_ref)
{

    // get order currency
    $order_currency = $order_data->get_currency();

    // perform currency decimal check
    $order_total_decimals = sb_adyen_get_currency_decimal($order_currency);

    // format final order total for use with Adyen
    $order_total = number_format($order_data->get_total(), $order_total_decimals, '', '');

    // get order number
    $order_number = $order_data->get_order_number();

    // capture payload
    $payload = [
        "originalReference"  => $psp_ref,
        "modificationAmount" => [
            "value"    => $order_total,
            "currency" => $order_currency
        ],
        "reference"          => $order_number,
        "merchantAccount"    => ADYEN_MERCHANT
    ];

    // adyen vars
    $x_api_key  = wp_specialchars_decode(ADYEN_API_KEY);
    $url_prefix = ADYEN_URL_PREFIX;

    // determine correct cURL URL
    if (ADYEN_GATEWAY_MODE == 'test') :
        $curl_url = "https://pal-test.adyen.com/pal/servlet/Payment/v51/capture";
    else :
        $curl_url = "https://$url_prefix-pal-live.adyenpayments.com/pal/servlet/Payment/v51/capture";
    endif;

    // initiate cURL request
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL            => $curl_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => "",
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => "POST",
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => array(
            "Content-Type: application/json",
            "X-API-Key: $x_api_key",
        ),
    ));

    try {

        // execute request
        $response = curl_exec($curl);

        // retrieve and parse response to array
        $response_arr = json_decode($response, true);

        file_put_contents(SB_ADYEN_PATH . 'logs/adyen-capture-data.txt', print_r($response_arr, true), FILE_APPEND);

        // retrieve reference data
        $merchantRef     = $response_arr['additionalData']['merchantReference'] ? $response_arr['additionalData']['merchantReference'] : __('Merchant ref not returned', 'woocommerce');
        $capturePSPref   = $response_arr['pspReference'];
        $captureResponse = $response_arr['response'];

        // Add order note to order with reference data
        $order_data->add_order_note('Capture successful - response: ' . $captureResponse . ' <br>Order number: ' . $merchantRef . ' <br>Capture PSP Ref: ' . $capturePSPref);

        // update order payment status to complete
        if (!$order_data->has_status(array('processing', 'completed', 'followup'))) :
            $order_data->payment_complete($psp_ref);
        endif;

        // catch exception if present
    } catch (Exception $ex) {
        $order_data->add_order_note("Capture failed: " . $ex->getMessage());
    }

    curl_close($curl);
}
