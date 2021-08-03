<?php

  /* created by Werner C. Bessinger @ Silverback Dev Studios */

  /* prevent direct access */
  if (!defined('ABSPATH')):
      exit;
  endif;
  /**
   * Retrieves transaction PSP ref from Adyen via payload string
   * 
   * @param string $payload - payload string returned from successful or failed Adyen transaction
   */
  function sb_adyen_retrieve_psp_ref($payload) {

      $curl = curl_init();

      $payload_data = [
          "payload" => $payload
      ];

      $x_api_key       = wp_specialchars_decode(ADYEN_API_KEY);
      $live_url_prefix = ADYEN_URL_PREFIX;

      if (ADYEN_GATEWAY_MODE == 'test'):
          $curl_url = "https://checkout-test.adyen.com/v51/payments/result";
      else:
          $curl_url = "https://$live_url_prefix-checkout-live.adyenpayments.com/checkout/v51/payments/result";
      endif;

      curl_setopt_array($curl, array(
          CURLOPT_URL            => $curl_url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING       => "",
          CURLOPT_MAXREDIRS      => 10,
          CURLOPT_TIMEOUT        => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST  => "POST",
          CURLOPT_POSTFIELDS     => json_encode($payload_data),
          CURLOPT_HTTPHEADER     => array(
              "Content-Type: application/json",
              "X-API-Key: $x_api_key",
          ),
      ));

      try {
          $response = curl_exec($curl);
          $response_arr = json_decode($response, true);
          
          return $response_arr['pspReference'];
          
      } catch (Exception $ex) {
          echo $ex;
      }
      curl_close($curl);
  }
  