<?php

  /* created by Werner C. Bessinger @ Silverback Dev Studios */

  /**
   * Shows/hides payment gateways based on user location (specifically country)
   */
  /* prevent direct access */
  if (!defined('ABSPATH')):
      exit;
  endif;
  
  function sb_adyen_filter_gateways($available_gateways) {

      if (is_admin()):
          return $available_gateways;
      endif;

	if (isset(WC()->customer)){
      /* get user billing country */
      $billing_country = WC()->customer->get_billing_country();
	  $currency = alg_get_current_currency_code();

      /* list of Sofort supported countries */
      $sofort_countries = ['DE', 'AT', 'BE', 'CH'];

      /* iDEAL conditional display (NL only) */
      if (isset($available_gateways['sb-adyen-ideal']) && ($billing_country != 'NL' || $currency != "EUR")) :
          unset($available_gateways['sb-adyen-ideal']);
      endif;

      /* Sofort conditional display */
      if (isset($available_gateways['sb-adyen-sofort']) && !in_array($billing_country, $sofort_countries)) :
          unset($available_gateways['sb-adyen-sofort']);
      endif;
      
      /* Bancontact conditional display */
      if (isset($available_gateways['sb-adyen-bancontact']) && $billing_country != 'BE') :
          unset($available_gateways['sb-adyen-bancontact']);
      endif;
      
      /* Multibanco conditional display */
      if (isset($available_gateways['sb-adyen-multibanco']) && $billing_country != 'PT') :
          unset($available_gateways['sb-adyen-multibanco']);
      endif;
      
      /* Finnish Online Banking conditional display */
      if (isset($available_gateways['sb-adyen-fob']) && $billing_country != 'FI') :
          unset($available_gateways['sb-adyen-fob']);
      endif;
      
      /* Poli Online Banking conditional display */
      if (isset($available_gateways['sb-adyen-poli']) && !in_array($billing_country,['AU', 'NZ'] )) :
          unset($available_gateways['sb-adyen-poli']);
      endif;
      
      /* EPS Online Banking conditional display */
      if (isset($available_gateways['sb-adyen-eps']) && $billing_country != 'AT') :
          unset($available_gateways['sb-adyen-eps']);
      endif;
      
      /* Korean Credit Card conditional display */
      if (isset($available_gateways['sb-adyen-korean-cc']) && $billing_country != 'KR') :
          unset($available_gateways['sb-adyen-korean-cc']);
      endif;
      
      /* Korean Online Banking conditional display */
      if (isset($available_gateways['sb-adyen-kcp']) && $billing_country != 'KR') :
          unset($available_gateways['sb-adyen-kcp']);
      endif;
      
      /* Boleto conditional display */
      if (isset($available_gateways['sb-adyen-boleto']) && $billing_country != 'BR') :
          unset($available_gateways['sb-adyen-boleto']);
      endif;
      
      /* GrabPay conditional display */
      if (isset($available_gateways['sb-adyen-gpay']) && !in_array($billing_country, ['MY', 'PH', 'SG', 'TH'])) :
          unset($available_gateways['sb-adyen-gpay']);
      endif;
      
      /* Molpay ebanking conditional display for Thailand */
      if (isset($available_gateways['sb-adyen-molpay-th']) && $billing_country != 'TH') :
          unset($available_gateways['sb-adyen-molpay-th']);
      endif;
      
      /* Molpay ebanking conditional display for Malaysia */
      if (isset($available_gateways['sb-adyen-molpay-my']) && $billing_country != 'MY') :
          unset($available_gateways['sb-adyen-molpay-my']);
      endif;
      
      /* Molpay 7-Eleven conditional display */
      if (isset($available_gateways['sb-adyen-mcash']) && $billing_country != 'MY') :
          unset($available_gateways['sb-adyen-mcash']);
      endif;
      
      /* Afterpay Touch conditional display */
      if (isset($available_gateways['sb-adyen-apt']) && !in_array($billing_country, ['AU', 'NZ'])) :
          unset($available_gateways['sb-adyen-apt']);
      endif;
      
      /* Yandex Money conditional display */
      if (isset($available_gateways['sb-adyen-yamon']) && $billing_country != 'RU') :
          unset($available_gateways['sb-adyen-yamon']);
      endif;
      
      /* Qiwi Wallet conditional display */
      if (isset($available_gateways['sb-adyen-qiwi']) && $billing_country != 'RU') :
          unset($available_gateways['sb-adyen-qiwi']);
      endif;
      
      /* Swish conditional display */
      if (isset($available_gateways['sb-adyen-swish']) && $billing_country != 'SE') :
          unset($available_gateways['sb-adyen-swish']);
      endif;
	  
	}
    
	return $available_gateways;
	
  }
  
  add_filter('woocommerce_available_payment_gateways', 'sb_adyen_filter_gateways');