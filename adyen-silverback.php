<?php

  /*
   * Plugin Name: SBWC Adyen Payment Gateway
   * Plugin URI: https://silverbackdev.co.za
   * Description: Custom Adyen Payment gateway implementation for WooCommerce
   * Author: Werner C. Bessinger
   * Version: 1.2.0
   * Author URI: https://silverbackdev.co.za
   */

  /* PREVENT DIRECT ACCESS */
  if (!defined('ABSPATH')):
      exit;
  endif;

  // define plugin path constant
  define('SB_ADYEN_PATH', plugin_dir_path(__FILE__));
  define('SB_ADYEN_URL', plugin_dir_url(__FILE__));
  define('SB_ADYEN_FILE', __FILE__);
  define('SB_AU_COUNTRIES', ["AU", "NZ", "SG", "MY"]);
  define('SB_EU_COUNTRIES', ["DE", "UK", "FR", "NL", "CH", "AT", "IT", "BE", "FI", "HU", "SE", "ES", "NO", "DK", "CZ", "IE", "RU", "PO", "LT", "GR", "HR", "EE", "SK", "SI", "IS", "PT", "LV", "SA", "LU", "RO", "JE", "BG", "CY", "RS"]);
  define('SB_US_COUNTRIES', ["US", "CA", "JP", "MX", "HK", "TW", "MO", "KO", "PR"]);

  /* PLUGIN INIT */
  require_once SB_ADYEN_PATH . '/functions/sb-adyen-init.php';