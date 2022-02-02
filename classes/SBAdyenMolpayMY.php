<?php

/**
 * Renders MOLPAY payment option for MALAYSIA
 *
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @author Werner C. Bessinger @ Silverback Dev
 */
class SBAdyenMolpayMY extends WC_Payment_Gateway
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
        $this->id = 'sb-adyen-molpay-my';

        /* method title */
        $this->method_title = esc_attr__('SB Adyen Molpay Ebanking Malaysia', 'sb-adyen-molpay-my');

        /* method description */
        $this->method_description = esc_attr__('Molpay Ebanking payment option for Malaysian Customers.', 'sb-adyen-molpay-my');

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
     * Render payment fields
     *
     * @return void
     */
    public function payment_fields()
    {

        // setup payment options
        $pmt_methods = [
            'fpx_abb'        => 'Affin Bank',
            'fpx_agrobank'   => 'Agro Bank',
            'fpx_abmb'       => 'Alliance Bank',
            'fpx_amb'        => 'Am Bank',
            'fpx_bimb'       => 'Bank Islam',
            'fpx_bmmb'       => 'Bank Muamalat',
            'fpx_bkrm'       => 'Bank Rakyat',
            'fpx_bsn'        => 'Bank Simpanan Nasional',
            'fpx_cimbclicks' => 'CIMB Bank',
            'fpx_hlb'        => 'Hong Leong Bank',
            'fpx_hsbc'       => 'HSBC Bank',
            'fpx_kfh'        => 'Kuwait Finance House',
            'fpx_mb2u'       => 'Maybank',
            'fpx_ocbc'       => 'OCBC Bank',
            'fpx_pbb'        => 'Public Bank',
            'fpx_rhb'        => 'RHB Bank',
            'fpx_scb'        => 'Standard Chartered Bank',
            'fpx_uob'        => 'UOB Bank',
        ]; ?>

        <!-- bank select -->
        <label for="sb-adyen-molpay-my-select">
            <?php _e('Select your bank:', 'woocommmerce'); ?>
        </label>

        <select name="sb-adyen-molpay-my-select" id="sb-adyen-molpay-my-select">
            <?php foreach ($pmt_methods as $id => $name) : ?>
                <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
            <?php endforeach; ?>
        </select>

<?php }

    /**
     * Set up WooCommerce payment gateway settings fields
     */
    public function settings_fields()
    {

        $this->form_fields = [
            /* enable/disable */
            'enabled' => [
                'title'   => esc_attr__('Enable / Disable', 'sb-adyen-molpay-my'),
                'label'   => esc_attr__('Enable this payment gateway', 'sb-adyen-molpay-my'),
                'type'    => 'checkbox',
                'default' => 'no',
            ],
            'title'   => [
                'title'       => __('Title', 'sb-adyen-molpay-my'),
                'type'        => 'text',
                'description' => __('Add your custom payment method title here if required.', 'sb-adyen-molpay-my'),
                'default'     => __('Molpay Ebanking', 'sb-adyen-molpay-my'),
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
        $order->add_order_note(__('Awaiting Molpay Ebanking (Malaysia) payment', 'woocommerce'));

        /* return url */
        $return_url = $this->get_return_url($order);

        /* start api request */
        $client = new \Adyen\Client();
        $client->setXApiKey(wp_specialchars_decode(ADYEN_API_KEY));

        /* deterimine current gateway mode and set environment/currency accordingly */
        if (ADYEN_GATEWAY_MODE == 'test') :
            $client->setEnvironment(\Adyen\Environment::TEST);
            $order_currency = 'MYR';
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
            file_put_contents(SB_ADYEN_PATH . 'error-logs/molpay-malaysia-epay/error.log', $_SERVER['REQUEST_TIME'] . ': ' . $ex->getMessage() . PHP_EOL, FILE_APPEND);
        }

        $payload = [
            "merchantAccount" => ADYEN_MERCHANT,
            "reference"       => $order->get_order_number() ? $order->get_order_number() : $order->get_id(),
            "amount"          => [
                "currency" => $order_currency,
                "value"    => $formatted_total
            ],
            "paymentMethod"   => [
                "type"         => "molpay_ebanking_fpx_MY",
                "issuer"       => $_POST['sb-adyen-molpay-my-select'],
            ],
            "returnUrl"       => $return_url,
        ];

        try {
            $request = $checkout->payments($payload);
        } catch (Exception $ex) {
            wc_add_notice(__('There was an error processing your payment: ' . $ex->getMessage(), 'woocommerce'));
            $order->add_order_note(__('Error processing payment: ' . $ex->getMessage(), 'woocommerce'));
        }

        if (!empty($request)) :

            $redirect_url = $request['action']['url'];

            /* redirect to chosen iDEAL merchant page to complete payment */
            return [
                'result'   => 'success',
                'redirect' => $redirect_url
            ];

        endif;
    }
}
