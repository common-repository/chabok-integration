<?php
/**
 * Tracks users behaviors on woocommerece.
 *
 * @package ChabokIO
 * @subpackage Woo
 */

/**
 * Tracks when no product found and send an event to chabok.
 *
 * @return void
 */
function chabok_on_woocommerce_no_products_found()
{
    global $chabok_options;
    if (!isset($chabok_options['no_products_found']) || 'on' !== $chabok_options['no_products_found']) {
        return;
    }

    $current_data = get_queried_object();
    $_name = sanitize_text_field($current_data->name);
    $_id = get_queried_object_id();
    $_search = sanitize_text_field(get_search_query());

    $event_name = 'woocommerce_no_products_found';
    $event_data = array('name' => $_name,
        'id' => $_id,
        'search' => $_search,
    );
    _chabok_woo_send_event($event_name, $event_data);

}
add_action('woocommerce_no_products_found', 'chabok_on_woocommerce_no_products_found');

/**
 * Tracks when no product found and send an event to chabok.
 *
 * @param integer $order_id
 * @return void
 */

function chabok_on_woocommerce_thankyou($order_id)
{
    global $chabok_options;
    if (!isset($chabok_options['order_thankyou']) || 'on' !== $chabok_options['order_thankyou']) {
        return;
    }
    if (!$order_id) {
        return;
    }
    // Allow code execution only once
    if (!get_post_meta($order_id, '_chabok_thankyou_action_done', true)) {
        $paid = false;
        if (isset($payment_date) && !empty($payment_date)) {
            $paid = true;
        }
        //list of products. can be send with events but event object become too large
        // foreach ( $order->get_items() as $item_id => $item ) {
        //     $product = $item->get_product();
        //     $product_id = $product->get_id();
        //     $product_id = $item->get_name();
        // }

        $event_name = 'chabok_on_woocommerce_thankyou';
        $event_data = _chabok_get_order_data($order_id);
        $_r = _chabok_woo_send_event($event_name, $event_data);
        $r = array();
        if (isset($_r['body']) && !empty($_r['body'])) {
            $__r = $_r['body'];
            $r = json_decode($__r, true);
        }
        if (isset($r['status']) && $r['status'] == 'queued') {
            $order = wc_get_order($order_id);
            //when chabok event is success save the meta data
            $order->update_meta_data('_chabok_thankyou_action_done', true);
            $order->save();
        }
    }
}
add_action('woocommerce_thankyou', 'chabok_on_woocommerce_thankyou', 10, 1);

/**
 * check when order is processed and needed status occured.
 * @param integer $order_id
 * @param array $post_data
 * @param object $order
 * @return void
 */
function chabok_on_woocommerce_checkout_order_processed($order_id, $posted_data, $order)
{
    $order_status = $order->get_status();
    if ($order_status === 'failed' || $order_status === 'pending') {
        _chabok_send_order_event($order, $order_status);
    }
}
add_action('woocommerce_checkout_order_processed', 'chabok_on_woocommerce_checkout_order_processed', 10, 3);

/**
 * order completed hook
 * @param integer $order_id
 * @param object $order
 * @return void
 */
function chabok_on_woocommerce_action_payment_complete($order_id, $order)
{
    $order_status = $order->get_status();
    _chabok_send_order_event($order, $order_status);

}
add_action('woocommerce_payment_complete', 'chabok_on_woocommerce_action_payment_complete', 10, 2);

function chabok_on_woocommerce_order_status_changed($order_id, $old_status, $new_status, $order)
{
    // // 1. For Bank wire and cheque payments
    $send_event = false;
    if (in_array($order->get_payment_method(), array('bacs', 'cheque')) &&
        in_array($new_status, array( /*'processing',*/'completed')) &&
        !$order->get_date_paid('edit')) {
        $send_event = true;
    }
    // // 2. For Cash on delivery payments
    if ('cod' === $order->get_payment_method() && 'completed' === $new_status) {
        $send_event = true;
    }
    //other case
    if ($old_status != $new_status) {
        $send_event = true; //we true it here, but for event the next function will decide
    }

    if ($send_event) {
        _chabok_send_order_event($order, $new_status);
    }

}
add_action('woocommerce_order_status_changed', 'chabok_on_woocommerce_order_status_changed', 10, 4);

/**
 * send order event base on order_status and admin selected options.
 * @param object $order
 * @param string $status
 * @return void
 */
function _chabok_send_order_event($order, $status)
{
    $send_event = false;
    global $chabok_options;
    if ('failed' === $status) {
        if (isset($chabok_options['order_failed']) || 'on' === $chabok_options['order_failed']) {
            $send_event = true;
        }
    }
    if ('pending' === $status) {
        if (isset($chabok_options['order_pending']) || 'on' === $chabok_options['order_pending']) {
            $send_event = true;
        }
    }
    if ('cancelled' === $status) {
        if (isset($chabok_options['order_cancelled']) || 'on' === $chabok_options['order_cancelled']) {
            $send_event = true;
        }
    }
    if ('completed' === $status) {
        if (isset($chabok_options['order_completed']) || 'on' === $chabok_options['order_completed']) {
            $send_event = true;
        }
    }

    if ($send_event) {
        $order_id = $order->get_id();
        $event_name = 'woocommerce_order_status_changed';
        $event_data = _chabok_get_order_data($order_id);
        _chabok_woo_send_event($event_name, $event_data);
    }
}

/**
 * send woo events to chabok
 * @param string $event_name
 * @param array $event_data
 * @return array
 */
function _chabok_woo_send_event($event_name, $event_data)
{
    list($user_id, $installation_id) = chabok_user_data();
    return chabok_io()->api->track_event(
        $event_name,
        $user_id,
        $installation_id,
        $event_data
    );
}

/**
 * return order needed data for events
 * @param integer $order_id
 * @return array
 */
function _chabok_get_order_data($order_id)
{
    $order = wc_get_order($order_id);
    $order_key = $order->get_order_key();
    $order_status = $order->get_status();
    $order_amount = intval($order->get_total());
    $order_payment_method = $order->get_payment_method();
    $payment_date = $order->get_date_paid();
    return array('order_id' => $order_id,
        'order_key' => $order_key,
        'order_status' => $order_status,
        'order_amount' => $order_amount,
        'order_payment_method' => $order_payment_method,
        'payment_date' => $payment_date,
    );
}
