<?php

/* created by Werner C. Bessinger @ Silverback Dev Studios */

/**
 * Initiates/activates our plugin and loads all required classes and functions
 */
// prevent direct access
if (!defined('ABSPATH')) :
    exit;
endif;
// plugin init
function sb_adyen_init()
{

    // globals
    define('ADYEN_API_KEY', get_option('sb_adyen_api_key', true));
    define('ADYEN_MERCHANT', get_option('sb_adyen_merchant_account'));
    define('ADYEN_GATEWAY_MODE', get_option('sb_adyen_gateway_mode'));
    define('ADYEN_ORIGIN_KEY', get_option('sb_adyen_origin_key'));
    define('ADYEN_URL_PREFIX', get_option('sb_adyen_url_prefix'));

    /* conditional display of gateways */
    require_once SB_ADYEN_PATH . 'functions/checkout/sb-adyen-conditional-gateways.php';

    /* currency decimal check function */
    require_once SB_ADYEN_PATH . 'functions/checkout/sb-adyen-set-currency-decimal.php';

    /* checkout scripts */
    require_once SB_ADYEN_PATH . 'functions/checkout/sb-adyen-checkout-scripts.php';

    /* retrieve adyen psp ref post transaction */
    require_once SB_ADYEN_PATH . 'functions/order-updates/sb-adyen-retrieve-psp-ref.php';

    /* order update via thank you page */
    require_once SB_ADYEN_PATH . 'functions/order-updates/thankyou-page/sb-adyen-wc-update-order.php';

    /* notifications received from adyen */
    require_once SB_ADYEN_PATH . 'functions/order-updates/sb-adyen-notifications.php';

    /* capture payment */
    require_once SB_ADYEN_PATH . 'functions/order-updates/sb-adyen-capture-payment.php';

    /* check of WooCommerce payment gateway class exists before doing anything else */
    if (!class_exists('WC_Payment_Gateway')) :
        return;
    endif;

    /* include core adyen php class */
    require SB_ADYEN_PATH . 'vendor/autoload.php';

    /* include core SB Adyen classes */
    require SB_ADYEN_PATH . 'classes/traits/SBAGetDecimals.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenAliPay.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenAliPayHK.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenIdeal.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenSofort.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenBancontact.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenCC.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenMultibanco.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenFOB.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenPoli.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenEPS.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenKoreanCC.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenPayco.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenKCP.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenBoleto.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenGrabPay.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenMolpayEpay.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenMolpayCash.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenAfterpayTouch.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenYandexMoney.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenSwish.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenQiwi.php';
    require SB_ADYEN_PATH . 'classes/SBAdyenWCSettings.php';

    /* add each core SB Adyen class to WooCommerce */
    function register_sb_adyen_gateway($methods)
    {
        $methods[] = 'SBAdyenAliPay';
        $methods[] = 'SBAdyenAliPayHK';
        $methods[] = 'SBAdyenIdeal';
        $methods[] = 'SBAdyenSofort';
        $methods[] = 'SBAdyenBancontact';
        $methods[] = 'SBAdyenCC';
        $methods[] = 'SBAdyenMultibanco';
        $methods[] = 'SBAdyenFOB';
        $methods[] = 'SBAdyenPoli';
        $methods[] = 'SBAdyenEPS';
        $methods[] = 'SBAdyenKoreanCC';
        $methods[] = 'SBAdyenPayco';
        $methods[] = 'SBAdyenKCP';
        $methods[] = 'SBAdyenBoleto';
        $methods[] = 'SBAdyenGrabPay';
        $methods[] = 'SBAdyenMolpayEpay';
        $methods[] = 'SBAdyenMolpayCash';
        $methods[] = 'SBAdyenAfterpayTouch';
        $methods[] = 'SBAdyenYandexMoney';
        $methods[] = 'SBAdyenSwish';
        $methods[] = 'SBAdyenQiwi';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'register_sb_adyen_gateway');

    /* add custom SB Adyen WooCommerce payment gateways settings link */
    function sb_adyen_settings_link($links)
    {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-alipay') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-alipay-hk') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-ideal') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-sofort') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-bancontact') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-cc') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-multibanco') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-fob') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-poli') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-eps') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-korean-cc') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-payco') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-kcp') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-boleto') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-gpay') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-mepay') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-mcash') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-apt') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-yamon') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-swish') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . esc_attr__('Settings', 'sb-adyen-qiwi') . '</a>',
        ];

        return array_merge($plugin_links, $links);
    }

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'sb_adyen_settings_link');
}

add_action('plugins_loaded', 'sb_adyen_init', 0);
