<?php
/**
 * Plugin Name: BlueLena Connect
 * Description: Sends WordPress post data to a webhook URL.
 * Version: 1.0
 * Author: BlueLena
 * Author URI: https://bluelena.io
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bluelena-connect
 * Version: 1.0
 * Requires at least: 5.0
 */

require_once(plugin_dir_path(__FILE__) . 'custom-meta-box.php');
require_once(plugin_dir_path(__FILE__) . 'admin-menu.php');

/**
 * Sends WordPress post data to a webhook URL.
 *
 * This plugin schedules the sending of order data to the webhook on WooCommerce order creation and update events.
 * It also creates a new action that will handle the sending of order data to the webhook.
 *
 */

// Schedule the sending of order data to the webhook on WooCommerce order creation and update events
add_action('woocommerce_new_order', 'schedule_order_data_sending', 1, 1);
add_action('woocommerce_order_status_changed', 'schedule_order_data_sending', 1, 1);

/**
 * Schedules the sending of order data to Bluelena Connect.
 *
 * This function checks if the Bluelena Connect functionality is enabled and schedules a single event to send the order data to the webhook.
 *
 * @param int $order_id The ID of the order to send.
 * @return void
 */
function schedule_order_data_sending($order_id) {
    // Check if the bluelena Connect functionality is enabled
    $bluelena_connect_enabled = get_option('bluelena_connect_enabled', 1); // Default to enabled

    if (!$bluelena_connect_enabled) {
        // If bluelena Connect is disabled, return early
        return;
    }
    // adding a random string to bypass this issue:
    // Note that scheduling an event to occur within 10 minutes of an 
    // existing event with the same action hook will be ignored unless
    //  you pass unique $args values for each scheduled event.
    $random_string = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
    
    wp_schedule_single_event(time(), 'bluelena_send_order_to_webhook_scheduled', array($order_id, $random_string));
}

// Create a new action that will handle the sending of order data to the webhook
add_action('bluelena_send_order_to_webhook_scheduled', 'bluelena_send_order_to_webhook', 1, 2);

/**
 * Sends an order to a webhook URL with the order data and utm parameters.
 *
 * @param int $order_id The order object to send.
 * @param string $random_string The random string to send.
 * @return void
 */
function bluelena_send_order_to_webhook($order_id, $random_string) {
    $webhook_url = get_option('bluelena_connect_webhook_url', '');
    $secret_token = get_option('bluelena_connect_secret_token', '');

    $order = wc_get_order($order_id);
    if (!$order) {
        // If the order is not found, return early
        return;
    }
    
    // Prepare the order data to send
    $order_data = $order->get_data();

    # get utm_campaign=701Du0000008wo0IAA from current url
    $url = $_SERVER['REQUEST_URI'];
    $url_components = parse_url($url);
    parse_str($url_components['query'], $params);
    if (isset($params['utm_campaign'])) {
        $utm_campaign = $params['utm_campaign'];
    } else {
        $utm_campaign = '';
    }
    if (isset($params['utm_source'])) {
        $utm_source = $params['utm_source'];
    } else {
        $utm_source = '';
    }
    if (isset($params['utm_medium'])) {
        $utm_medium = $params['utm_medium'];
    } else {
        $utm_medium = '';
    }
    if (isset($params['utm_term'])) {
        $utm_term = $params['utm_term'];
    } else {
        $utm_term = '';
    }
    $order_data['utm'] = array(
        'utm_campaign' => $utm_campaign,
        'utm_source' => $utm_source,
        'utm_medium' => $utm_medium,
        'utm_term' => $utm_term,
    );

    $products = $order->get_items();
    $product_info = array();
    foreach ($products as $product) {
        $product_name = $product->get_name();
        $product_id = $product->get_product_id();
        $product_info[] = array(
            'name' => $product_name,
            'id' => $product_id,
        );
    }
    $order_data['products'] = $product_info;
    // Create a JSON representation of the order data
    $json_data = json_encode($order_data);
	
    // Set up the request to send the order data to the webhook URL
    $args = array(
        'body'        => $json_data,
        'headers'     => array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $secret_token
		),
        'timeout'     => 40,
        'redirection' => 5,
    );

    // Send the request
    $response = wp_safe_remote_post($webhook_url, $args);

    // Check for errors
    if (is_wp_error($response)) {
        error_log('Webhook request failed: ' . $response->get_error_message());
        update_post_meta($order_id, 'bluelena_connect_error', $response->get_error_message());
    } else {
        // Request was successful, handle the response here
        $response_body = $response['body'];
        // response status code
        $response_code = $response['response']['code'];
        // save response as custom field
        update_post_meta($order_id, 'bluelena_connect_response_code', $response_code);
        update_post_meta($order_id, 'bluelena_connect_response_body', $response_body);
    }
}