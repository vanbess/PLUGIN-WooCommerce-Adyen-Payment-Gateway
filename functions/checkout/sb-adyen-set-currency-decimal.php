<?php

  /* created by Werner C. Bessinger @ Silverback Dev Studios */

  /* prevent direct access */
  if (!defined('ABSPATH')):
      exit;
  endif;

  // setup order currency decimal setings
  function sb_adyen_set_currency_decimal($order_currency) {

      $decimal_0 = array('XPF', 'XOF', 'XAF', 'VUV', 'VND', 'UGX', 'RWF', 'PYG', 'KRW', 'KMF', 'JPY', 'IDR', 'GNF', 'DJF', 'CVE');
      $decimal_3 = array('BHD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND');

      if (in_array($order_currency, $decimal_0)) {
          $currency_decimal = 0;
      } else if (in_array($order_currency, $decimal_3)) {
          $currency_decimal = 3;
      } else {
          $currency_decimal = 2;
      }
      return $currency_decimal;
  }