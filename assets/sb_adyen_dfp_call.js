
/* <![CDATA[ */ 
dfDo('adyen_cc_dfp');
/* ]]> */

/* Prevent auto scrolling to error message */
jQuery(document.body).on('checkout_error', function () {
    jQuery('html, body').stop();
});
