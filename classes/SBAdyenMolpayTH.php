<?php

/**
 * Renders MOLPAY payment option for THAILAND
 *
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @author Werner C. Bessinger @ Silverback Dev
 * @date 
 */
class SBAdyenMolpayTH extends WC_Payment_Gateway
{

    use SBAGetDecimals;

    /**
     * Payment method constructor
     */
    public function __construct()
    {

        /* method id */
        $this->id = 'sb-adyen-molpay-th';

        /* method title */
        $this->method_title = esc_attr__('SB Adyen Molpay Ebanking Thailand', 'sb-adyen-molpay-th');

        /* method description */
        $this->method_description = esc_attr__('Molpay Ebanking payment option for Thailand Customers.', 'sb-adyen-molpay-th');

        /* gateway icon */
        $this->icon = plugin_dir_url(__FILE__) . 'images/molpay-logo.svg';

        /* display frontend title */
        $this->title = $this->get_option('title');

        /* gateway has fields */
        $this->has_fields = true;

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
     * Payment method backend settings fields
     *
     * @return void
     */
    public function settings_fields()
    {

        $this->form_fields = [
            /* enable/disable */
            'enabled' => [
                'title'   => esc_attr__('Enable / Disable', 'sb-adyen-molpay-th'),
                'label'   => esc_attr__('Enable this payment gateway', 'sb-adyen-molpay-th'),
                'type'    => 'checkbox',
                'default' => 'no',
            ],
            'title'   => [
                'title'       => __('Title', 'sb-adyen-molpay-th'),
                'type'        => 'text',
                'description' => __('Add your custom payment method title here if required.', 'sb-adyen-molpay-th'),
                'default'     => __('Molpay Ebanking (Thailand)', 'sb-adyen-molpay-th'),
            ]
        ];
    }

    /**
     * Render payment fields
     *
     * @return void
     */
    public function payment_fields()
    {
        // setup payment options
        $pmt_methods = [
            'molpay_bangkokbank'        => 'Bangkok Bank',
            'molpay_krungsribank'       => 'Krungsri Bank',
            'molpay_krungthaibank'      => 'Krung Thai Bank',
            'molpay_siamcommercialbank' => 'The Siam Commercial Bank',
            'molpay_kbank'              => 'Kasikorn Bank',
        ]; ?>

        <!-- bank select -->
        <label for="sb-adyen-molpay-th-select">
            <?php _e('Select your bank:', 'woocommmerce'); ?>
        </label>

        <select name="sb-adyen-molpay-th-select" id="sb-adyen-molpay-th-select">
            <?php foreach ($pmt_methods as $id => $name) : ?>
                <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
            <?php endforeach; ?>
        </select>

<?php }

    /**
     * Process payment
     *
     * @param  int $order_id - WC Order ID
     * @return void
     */
    public function process_payment($order_id)
    {

        /* create new customer order */
        $order = new WC_Order($order_id);

        /* set initial order status */
        $order->update_status('pending');
        $order->add_order_note(__('Awaiting Molpay Ebanking (Thailand) payment', 'woocommerce'));

        /* return url */
        $return_url = $this->get_return_url($order);

        /* start api request */
        $client = new \Adyen\Client();
        $client->setXApiKey(wp_specialchars_decode(ADYEN_API_KEY));

        /* deterimine current gateway mode and set environment/currency accordingly */
        if (ADYEN_GATEWAY_MODE == 'test') :
            $client->setEnvironment(\Adyen\Environment::TEST);
            $order_currency = 'THB';
        else :
            $client->setEnvironment(\Adyen\Environment::LIVE, ADYEN_URL_PREFIX);
            $order_currency = alg_get_current_currency_code();
        endif;

        /* setup vars for curl request */
        $order_total = $order->get_total();

        // get correct decimals for currency
        $decimals = self::get_decimals($order_currency);

        /* formatted total */
        $formatted_total = number_format($order_total, $decimals, '', '');

        try {
            $checkout = new \Adyen\Service\Checkout($client);
        } catch (Exception $ex) {
            file_put_contents(SB_ADYEN_PATH . 'error-logs/molpay-thailand-epay/error.log', $_SERVER['REQUEST_TIME'] . ': ' . $ex->getMessage() . PHP_EOL, FILE_APPEND);
        }

        $payload = [
            "amount"          => [
                "currency" => $order_currency,
                "value"    => $formatted_total
            ],
            "reference"       => $order->get_order_number(),
            "paymentMethod"   => [
                "type"         => "molpay_ebanking_TH",
                "issuer"       => $_POST['sb-adyen-molpay-th-select'],
            ],
            "returnUrl"       => $return_url,
            "merchantAccount" => ADYEN_MERCHANT,
        ];

        try {
            $request = $checkout->payments($payload);
            file_put_contents(SB_ADYEN_PATH.'molpay-th-test.txt', print_r($request, true), FILE_APPEND);
        } catch (Exception $ex) {
            wc_add_notice(__('There was an error processing your payment: ' . $ex->getMessage(), 'woocommerce'));
            $order->add_order_note(__('Error processing payment: ' . $ex->getMessage(), 'woocommerce'));
        }

        if (!empty($request)) :

            file_put_contents(SB_ADYEN_PATH.'molpay-th-pmt-request.txt', print_r($request, true), FILE_APPEND);

            $redirect_url = $request['action']['url'];

            /* redirect to chosen Molpay merchant page to complete payment */
            return [
                'result'   => 'success',
                'redirect' => $redirect_url
            ];

        endif;
    }
}
