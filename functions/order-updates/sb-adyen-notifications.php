<?php

    /**
     * Handles order updates via notifications sent from Adyen, in particular for voucher based methods
     */
    /* prevent direct access */
    if (!defined('ABSPATH')):
        exit;
    endif;

    /* add custom REST API route */
    add_action('rest_api_init', function () {
        register_rest_route('sb-adyen-notifications', 'update-order', array(
            'methods'  => 'POST',
            'callback' => 'sb_adyen_update_order_status'
        ));
    });

    /* update validate comms from adyen and update order if needed */
    function sb_adyen_update_order_status($request) {

        /* required to echo string [accepted] so that adyen knows the notifications endpoint is legit */
        print '[accepted]';

        /* since we're receiving a JSON object via HTTP Post we will need to get its contents using file_get_contents */
        $data = file_get_contents('php://input');

        /* now that the JSON object has been correctly parsed to a string we can decode it */
        $data_arr = json_decode($data, true);

        /* setup our required variables post-decoding */
        $order_number     = $data_arr['notificationItems'][0]['NotificationRequestItem']['merchantReference'];
        $psp_ref          = $data_arr['notificationItems'][0]['NotificationRequestItem']['pspReference'];
        $reason           = $data_arr['notificationItems'][0]['NotificationRequestItem']['reason'];
        $trans_successful = $data_arr['notificationItems'][0]['NotificationRequestItem']['success'];

        $order_id = wc_seq_order_number_pro()->find_order_by_order_number($order_number);

        if ($order_id):

            $captured = FALSE;
            $args     = array('order_id' => $order_id);
            $notes    = wc_get_order_notes($args);
            if ($notes) {
                foreach ($notes as $note) {
                    //if ((stripos($note->content, "Cloned") !== FALSE) || (stripos($note->content, "Adyen") !== FALSE)){
                    if ((stripos($note->content, "capture-received") !== FALSE) || (stripos($note->content, "payment successful") !== FALSE)) {
                        $captured = TRUE;
                        break;
                    }
                }
            }

            if (!$captured):
                /* get order object */
                $order_data = wc_get_order($order_id);
            
                /* if adyen success status is true, update order to processing, else update to cancelled with reason */
                if ($trans_successful == 'true'):
					
                    $order_data->add_order_note('Notification of payment received from Adyen with the following reason data: payment successful (' . $reason . ')', 0, false);
                    $order_data->add_order_note('Adyen PSP Ref: ' . $psp_ref, 0, false);
					
					
					$pmt_method = $order_data->get_payment_method();
                    //$order_data->add_order_note('Raw data: ' . $request, 0, false);
					//file_put_contents('/home/nordace/web/nordace.com/public_html/debug_woothumb.log', "Adyen status, ID:" . $order_id . " - " .  $order_data->get_status() . "\n", FILE_APPEND);

                    update_post_meta($order_id, '_adyen_reason_data_raw', $reason);
					
					if ($pmt_method != "sb-adyen-cc"):
						if ( ! $order_data->has_status( array( 'processing', 'completed', 'followup' ) ) ):
							$order_data->update_status('processing');
						endif;
					endif;

                elseif ($trans_successful == 'false'):

                    //$order_data->update_status('cancelled');
                    $order_data->add_order_note('Notification of payment failure received from Adyen with the following reason data: payment failed (' . $reason . ')', 0, false);
                    $order_data->add_order_note('Adyen PSP Ref: ' . $psp_ref, 0, false);

                    update_post_meta($order_id, '_adyen_reason_data_raw', $reason);

                endif;
            endif;
        endif;
    }
    