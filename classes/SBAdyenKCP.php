<?php

/**
 * Renders KOREAN ONLINE BANKING payment option
 *
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @author Werner C. Bessinger @ Silverback Dev
 */
class SBAdyenKCP extends WC_Payment_Gateway
{

    use SBAGetDecimals;

    /**
     * Class constructor
     * Here we set the payment gateway id, 
     * gateway title and description in the backend, 
     * tab title, supports and a bunch of other stuff
     */
    public function __construct()
    {

        /* method id */
        $this->id = 'sb-adyen-kcp';

        /* method title */
        $this->method_title = esc_attr__('SB Adyen KOREAN ONLINE BANKING (KCP)', 'sb-adyen-kcp');

        /* method description */
        $this->method_description = esc_attr__('Adyen Korean Online Banking payments.', 'sb-adyen-kcp');

        /* gateway icon */
        $this->icon = plugin_dir_url(__FILE__) . 'images/korean-online-banking.png';

        /* display frontend title */
        $this->title = $this->get_option('title');

        /* gateway has fields */
        $this->has_fields = true;

        /* add refund support */
        $this->supports = ['refunds'];

        /* init gateway form fields */
        $this->settings_fields();

        /* load our settings */
        $this->init_settings();

        /* save gateway settings when save settings button clicked IF user is admin */
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }
    }

    /**
     * Set up WooCommerce payment gateway settings fields
     */
    public function settings_fields()
    {

        $this->form_fields = [
            /* enable/disable */
            'enabled' => [
                'title'   => esc_attr__('Enable / Disable', 'sb-adyen-kcp'),
                'label'   => esc_attr__('Enable this payment gateway', 'sb-adyen-kcp'),
                'type'    => 'checkbox',
                'default' => 'no',
            ],
            'title'   => [
                'title'       => __('Title', 'sb-adyen-kcp'),
                'type'        => 'text',
                'description' => __('Add your custom payment method title here if required.', 'sb-adyen-kcp'),
                'default'     => __('Korean Online Banking', 'sb-adyen-kcp'),
            ]
        ];
    }

    /* process payment */

    public function process_payment($order_id)
    {

        /* create new customer order */
        $order = new WC_Order($order_id);

        /* set initial order status */
        $order->update_status('pending');
        $order->add_order_note(__('Awaiting Korean Online Banking payment', 'woocommerce'));

        /* return url */
        $return_url = $this->get_return_url($order);

        /* start api request */
        $client = new \Adyen\Client();
        $client->setXApiKey(wp_specialchars_decode(ADYEN_API_KEY));

        /* deterimine current gateway mode and set environment/currency accordingly */
        if (ADYEN_GATEWAY_MODE == 'test') :
            $client->setEnvironment(\Adyen\Environment::TEST);
            $order_currency = 'KRW';
        else :
            $client->setEnvironment(\Adyen\Environment::LIVE, ADYEN_URL_PREFIX);
            $order_currency = alg_get_current_currency_code();
        endif;

        try {
            $checkout = new \Adyen\Service\Checkout($client);
        } catch (Exception $ex) {
            file_put_contents(SB_ADYEN_PATH . 'error-logs/kcp/error.log', $_SERVER['REQUEST_TIME'] . ': ' . $ex->getMessage() . PHP_EOL, FILE_APPEND);
        }

        /* get order total correct decimal count depending on currency used */
        $cart_total_decimals = self::get_decimals($order_currency);

        /* ensure order total is correctly formatted before sending to Adyen (no decimal points or nuttin') */
        $order_total = number_format($order->get_total(), $cart_total_decimals, '', '');

        $payload = [
            "merchantAccount" => ADYEN_MERCHANT,
            "reference"       => $order->get_order_number(),
            "amount"          => [
                "currency" => $order_currency,
                "value"    => $order_total
            ],
            "paymentMethod"   => [
                "type" => "kcp_banktransfer"
            ],
            "returnUrl"       => $return_url,
        ];

        try {
            $request = $checkout->payments($payload);
        } catch (Exception $ex) {
            wc_add_notice(__('There was an error processing your payment: ' . $ex->getMessage(), 'woocommerce'), 'error');
            $order->add_order_note(__('Error processing payment: ' . $ex->getMessage(), 'woocommerce'));
        }

        if (!empty($request)) :

            $redirect_url = $request['redirect']['url'];

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
    public function process_refund($order_id, $amount = null, $reason = null)
    {

        $order = new WC_Order($order_id);

        if (!$order) {
            return new WP_Error('sb_adyen_refund_error', __('Order not valid', 'woocommerce'));
        }

        $psp_ref = get_post_meta($order_id, '_adyen_psp_ref', true);

        if (!$psp_ref || empty($psp_ref)) {
            return new WP_Error('sb_adyen_refund_error', __('No valid Transaction ID found', 'woocommerce'));
        }

        if (is_null($amount) || $amount <= 0) {
            return new WP_Error('sb_adyen_refund_error', __('Amount not valid', 'woocommerce'));
        }

        if (is_null($reason) || '' == $reason) {
            $reason = sprintf(__('Refund for Order %s', 'woocommerce'), $order->get_order_number());
        }

        /* SETUP REFUND PARAMS */
        // order currency
        $order_currency = $order->get_currency();

        // currency decimals
        $decimals = self::get_decimals($order_currency);

        // formatted refund amount
        $refund_amount = number_format($amount, $decimals, '', '');

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
                "acceptHeader" => $_SERVER['HTTP_USER_AGENT'],
                "userAgent"    => $_SERVER['HTTP_ACCEPT']
            ]
        ];

        try {

            /* INIT CURL REQUEST */

            // url prefix
            $url_prefix = ADYEN_URL_PREFIX;

            // setup request link
            if (ADYEN_GATEWAY_MODE == 'test') :
                $curl_url = "https://pal-test.adyen.com/pal/servlet/Payment/v51/refund";
            else :
                $curl_url = "https://$url_prefix-pal-live.adyenpayments.com/pal/servlet/Payment/v51/refund";
            endif;

            // api key
            $x_api_key = wp_specialchars_decode(ADYEN_API_KEY);

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
                $response         = curl_exec($curl);
                $result           = json_decode($response);
                if (isset($result) && !empty($result->response = '[refund-received]')) :
                    $order->add_order_note(__('Adyen refund successfully processed. PSP Ref: ' . $result->pspReference, 'woocommerce'));
                    return true;
                endif;
            } catch (Exception $ex) {
                $order->add_order_note(__('Curl request failed with: ' . $ex, 'woocommerce'));
            }
            curl_close($curl);
        } catch (Exception $ex) {
            $order->add_order_note(__('Refund processing error: ' . $ex, 'woocommerce'));
        }
    }
}
