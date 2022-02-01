<?php
    /* created by Werner C. Bessinger @ Silverback Dev Studios */

    /* prevent direct access */
    if (!defined('ABSPATH')):
        exit;
    endif;

    /* header scripts */
    add_action('wp_enqueue_scripts', 'sb_adyen_header_scripts');
    function sb_adyen_header_scripts() {
        wp_register_script('adyen-fingerprint-js', 'https://live.adyen.com/hpp/js/df.js?' . date('Ymd'), '', '', true);
        wp_register_style('sb_adyen_front_css', SB_ADYEN_URL . "assets/sbadyen.front.css");

        if (ADYEN_GATEWAY_MODE == 'test'):
            wp_register_script('adyen-checkout-js', 'https://checkoutshopper-test.adyen.com/checkoutshopper/sdk/5.6.1/adyen.js', '', '', false);
            wp_register_style('adyen-checkout-css', 'https://checkoutshopper-test.adyen.com/checkoutshopper/sdk/5.6.1/adyen.css');
        else:
            //  wp_register_script('adyen-checkout-js', SB_ADYEN_URL . 'assets/adyen.js', '', '4.2.0', false);
            
            $adyen_checkout_js_url = "https://checkoutshopper-live-us.adyen.com/checkoutshopper/sdk/5.6.1/adyen.js";
            
            if (isset($_SERVER["HTTP_CF_IPCOUNTRY"])){
                if (in_array($_SERVER["HTTP_CF_IPCOUNTRY"], SB_AU_COUNTRIES)){
                    $adyen_checkout_js_url = "https://checkoutshopper-live-au.adyen.com/checkoutshopper/sdk/5.6.1/adyen.js";
                } elseif (in_array($_SERVER["HTTP_CF_IPCOUNTRY"], SB_EU_COUNTRIES)) {
                    $adyen_checkout_js_url = "https://checkoutshopper-live.adyen.com/checkoutshopper/sdk/5.6.1/adyen.js";
                }
            }

            wp_register_script('adyen-checkout-js', $adyen_checkout_js_url, '', '', false);
            wp_register_style('adyen-checkout-css', 'https://checkoutshopper-live-us.adyen.com/checkoutshopper/sdk/5.6.1/adyen.css');
            
        endif;

        if (is_checkout()){
            wp_enqueue_script('adyen-checkout-js');
            wp_enqueue_script('adyen-fingerprint-js');
            wp_enqueue_script('sb_adyen_dfp_call', SB_ADYEN_URL . "assets/sb_adyen_dfp_call.js", '', '', true);
            wp_enqueue_style('adyen-checkout-css');
            wp_enqueue_style('sb_adyen_front_css');
        }
    }

    /* footer scripts */
    // add_action('wp_footer', 'sb_adyen_footer2_scripts', 999);
    function sb_adyen_footer2_scripts() {
        
        if (is_checkout()){
        ?>

        <!-- sb adyen dfp call -->
        <script type="text/javascript" id="sb_adyen_dfp_call">
            /* <![CDATA[ */
            dfDo('adyen_cc_dfp');
            /* ]]> */

            /* Prevent auto scrolling to error message */
            jQuery(document.body).on('checkout_error', function () {
               jQuery('html, body').stop();
            });
        </script>

        <?php
        }
    }
?>