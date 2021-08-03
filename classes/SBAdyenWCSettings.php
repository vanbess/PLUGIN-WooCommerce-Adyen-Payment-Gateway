<?php

    /**
     * Renders Adyen settings page under WooCommerce settings tab
     *
     * @extends N/A
     * @version 1.0.0
     * @author Werner C. Bessinger @ Silverback Dev
     */
    class SBAdyenWCSettings {
        // init settings tab/page
        public static function init() {

            //actions and filters
            add_filter('woocommerce_settings_tabs_array', __CLASS__ . '::add_sb_adyen_settings_tab', 50);
            add_action('woocommerce_settings_tabs_sb_adyen_settings', __CLASS__ . '::sb_adyen_settings_tab');
            add_action('woocommerce_update_options_sb_adyen_settings', __CLASS__ . '::update_sb_adyen_settings');

            // define error message globals
            define('SBA_CVC_DECLINED', get_option('sb_adyen_cvc_declined_msg'));
            define('SBA_EXPIRED_CARD', get_option('sb_adyen_expired_card_msg'));
            define('SBA_INVALID_CARD_NO', get_option('sb_adyen_invalid_card_msg'));
            define('SBA_UNKNOWN', get_option('sb_adyen_unknown_error_msg'));
            define('SBA_REFUSED', get_option('sb_adyen_refused_msg'));
            define('SBA_TRANS_NOT_PERM', get_option('sb_adyen_not_permitted_msg'));
            define('SBA_FRAUD', get_option('sb_adyen_fraud_msg'));
            define('SBA_FRAUD_CANCELLED', get_option('sb_adyen_fraud_cancelled_msg'));
            define('SBA_DECLINED', get_option('sb_adyen_transaction_declined_msg'));
            define('SBA_VALIDATION', get_option('sb_adyen_validation_message_one'));
            define('SBA_VALIDATION_2', get_option('sb_adyen_validation_message_TWO'));

        }

        // add sb adyen settings tab
        public static function add_sb_adyen_settings_tab($settings_tabs) {
            $settings_tabs['sb_adyen_settings'] = __('Adyen Settings', 'woocommerce-sb-adyen-settings');
            self::sb_adyen_settings_css_js();
            return $settings_tabs;
        }

        // output settings using WC admin fields API
        public static function sb_adyen_settings_tab() {
            woocommerce_admin_fields(self::get_sb_adyen_settings());
        }

        // update adyen settings on save using WC options API
        public static function update_sb_adyen_settings() {
            woocommerce_update_options(self::get_sb_adyen_settings());
        }

        // css + js
        public static function sb_adyen_settings_css_js() {
            wp_register_style('sbadyen_admin', SB_ADYEN_URL . 'assets/sbadyen.admin.css');
            wp_enqueue_style('sbadyen_admin');
        }

        // get all settings for SB Adyen plugin
        public static function get_sb_adyen_settings() {
            $settings = [
                /* adyen api key */
                [
                    'title' => esc_attr__('Your Adyen LIVE or SANDBOX API key', 'sb-adyen'),
                    'type'  => 'text',
                    'desc'  => '<b><u>IMPORTANT:</u></b> Make sure your API key matches your gateway mode setting!<br>'
                    . ' For example, if the gateway is set to TEST/SANDBOX mode be sure you\'re using your TEST/SANDBOX API key here, and vice versa.', 'sb-adyen',
                    'id'    => 'sb_adyen_api_key'
                ],
                /* adyen merchant account */
                [
                    'title' => esc_attr__('Your Adyen Merchant Account name', 'sb-adyen'),
                    'type'  => 'text',
                    'desc'  => 'Your Adyen Merchant Account name for performing LIVE or SANDBOX/TEST transactions. <br>'
                    . 'It should be the same for both LIVE and SANDBOX/TEST purposes, but if not<br> please enter the correct merchant account for the gateway mode you wish to use.', 'sb-adyen',
                    'id'    => 'sb_adyen_merchant_account'
                ],
                /* adyen origin key */
                [
                    'title' => esc_attr__('Your Adyen Origin Key', 'sb-adyen'),
                    'type'  => 'text',
                    'desc'  => 'Your Adyen Origin Key - required for processing card transactions', 'sb-adyen',
                    'id'    => 'sb_adyen_origin_key'
                ],
                /* adyen gateway mode */
                [
                    'title'   => esc_attr__('Select whether you want to run Adyen in TEST/SANDBOX or LIVE mode', 'sb-adyen'),
                    'type'    => 'select',
                    'options' => [
                        'live' => 'LIVE',
                        'test' => 'TEST/SANDBOX'
                    ],
                    'desc'    => 'Specify whether or not you want to run the gateway in LIVE or TEST mode.<br> <b><u>IMPORTANT:</u></b> Be sure to provide the correct keys for the mode you select here!', 'sb-adyen',
                    'id'      => 'sb_adyen_gateway_mode'
                ],
                /* adyen live URL */
                [
                    'title'       => esc_attr__('Your Adyen URL prefix - required for LIVE transactions', 'sb-adyen'),
                    'type'        => 'text',
                    'placeholder' => 'e.g. 1797a841fbb37ca7-AdyenDemo',
                    'desc'        => '<b><u>According to Adyen</u></b>: When using our libraries for live transactions, you need to pass the live url prefix to the library.<br> '
                    . 'This prefix is the combination of the [random] and [company name] from the live endpoint.<br>'
                    . 'For example, if this was your live URL:<br>'
                    . '<b> https://1797a841fbb37ca7-AdyenDemo-checkout-live.adyenpayments.com/checkout/v50/payments </b><br>'
                    . 'Then the live URL prefix would be <b>1797a841fbb37ca7-AdyenDemo</b>', 'sb-adyen',
                    'id'          => 'sb_adyen_url_prefix'
                ],
                /* adyen cc fraud check */
                [
                    'title'   => esc_attr__('Enable Credit Card fraud check mode?', 'sb-adyen'),
                    'type'    => 'select',
                    'options' => [
                        'yes' => 'YES',
                        'no' => 'NO'
                    ],
                    'desc'    => 'If YES will check user\'s IP address country against card issuing country and flag all orders with status of \'Follow Up\' if there is a mismatch.', 'sb-adyen',
                    'id'      => 'sb_adyen_cc_fraud_check'
                ],
                /* ERROR MESSAGES */
                [
                    'title' => esc_attr__('CVC declined message:', 'sb-adyen'),
                    'type'  => 'text',
                    'id'    => 'sb_adyen_cvc_declined_msg',
                    'class' => 'sb_adyen_error_msgs'
                ],
                [
                    'title' => esc_attr__('Expired card message:', 'sb-adyen'),
                    'type'  => 'text',
                    'id'    => 'sb_adyen_expired_card_msg',
                    'class' => 'sb_adyen_error_msgs'
                ],
                [
                    'title' => esc_attr__('Invalid card number message:', 'sb-adyen'),
                    'type'  => 'text',
                    'id'    => 'sb_adyen_invalid_card_msg',
                    'class' => 'sb_adyen_error_msgs'
                ],
                [
                    'title' => esc_attr__('Unknown error message:', 'sb-adyen'),
                    'type'  => 'text',
                    'id'    => 'sb_adyen_unknown_error_msg',
                    'class' => 'sb_adyen_error_msgs'
                ],
                [
                    'title' => esc_attr__('Transaction refused message:', 'sb-adyen'),
                    'type'  => 'text',
                    'id'    => 'sb_adyen_refused_msg',
                    'class' => 'sb_adyen_error_msgs'
                ],
                [
                    'title' => esc_attr__('Transaction not permitted message:', 'sb-adyen'),
                    'type'  => 'text',
                    'id'    => 'sb_adyen_not_permitted_msg',
                    'class' => 'sb_adyen_error_msgs'
                ],
                [
                    'title' => esc_attr__('Fraudulent transaction message:', 'sb-adyen'),
                    'type'  => 'text',
                    'id'    => 'sb_adyen_fraud_msg',
                    'class' => 'sb_adyen_error_msgs'
                ],
                [
                    'title' => esc_attr__('Fraudulent transaction cancelled message:', 'sb-adyen'),
                    'type'  => 'text',
                    'id'    => 'sb_adyen_fraud_cancelled_msg',
                    'class' => 'sb_adyen_error_msgs'
                ],
                [
                    'title' => esc_attr__('Transaction declined message:', 'sb-adyen'),
                    'type'  => 'text',
                    'id'    => 'sb_adyen_transaction_declined_msg',
                    'class' => 'sb_adyen_error_msgs'
                ],
                [
                    'title' => esc_attr__('Adyen validation message (1):', 'sb-adyen'),
                    'type'  => 'text',
                    'id'    => 'sb_adyen_validation_message_one',
                    'class' => 'sb_adyen_error_msgs'
                ],
                [
                    'title' => esc_attr__('Adyen validation message (2):', 'sb-adyen'),
                    'type'  => 'text',
                    'id'    => 'sb_adyen_validation_message_two',
                    'class' => 'sb_adyen_error_msgs'
                ],
            ];

            return apply_filters('wc_settings_tab_sb_adyen_settings', $settings);
        }

    }

    // init
    SBAdyenWCSettings::init();
    