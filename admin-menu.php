<?php
/**
 * FILEPATH: bluelena-connect/admin-menu.php
 * 
 * This file contains the code for adding an admin menu item and handling the sync bulk action for WooCommerce orders.
 * 
 * The `bluelena_connect_menu` function adds a submenu page to the "Tools" menu in the WordPress admin dashboard.
 * The `bluelena_connect_settings_page` function displays a form to input and save the webhook URL, secret token, and enabled/disabled state.
 * The `bluelena_connect_bulk_actions` function adds a "Sync -> BlueLena" option to the "Bulk actions" dropdown in the "All orders" page of WooCommerce.
 * The `bluelena_connect_handle_bulk_action` function handles the "Sync -> BlueLena" bulk action by enqueuing each selected order for sync.
 * The `bluelena_connect_enqueue_sync` function enqueues an order for sync with a delay.
 * The `bluelena_connect_process_sync_queue` function processes the sync queue by sending each order to the webhook.
 * The `bluelena_connect_bulk_action_admin_notice` function displays a notice after syncing orders.
 */
// Add an admin menu item
add_action('admin_menu', 'bluelena_connect_menu');

/**
 * Registers a submenu page under Tools menu for BlueLena Connect Settings.
 *
 * @return void
 */
function bluelena_connect_menu() {
    add_submenu_page(
        'tools.php', // Parent slug
        'BlueLena Connect Settings', // Page title
        'BlueLena Connect', // Menu title
        'manage_options', // Capability
        'bluelena-connect-settings', // Menu slug
        'bluelena_connect_settings_page' // Callback function
    );
}
// Callback function for the admin menu page
/**
 * 
 * Displays the BlueLena Connect settings page and saves the webhook URL, secret token, and enabled/disabled state.
 * Retrieves the current webhook URL, secret token, and enabled/disabled state from the database.
 * 
 * @return void
 */
function bluelena_connect_settings_page() {
    // Check if the form is submitted and save the webhook URL and secret token
    if (isset($_POST['save_settings'])) {
        $webhook_url = sanitize_text_field($_POST['webhook_url']);
        $secret_token = sanitize_text_field($_POST['secret_token']);
        update_option('bluelena_connect_webhook_url', $webhook_url);
        update_option('bluelena_connect_secret_token', $secret_token);
        $enabled = isset($_POST['enabled']) ? 1 : 0; // Check if enabled checkbox is checked
        update_option('bluelena_connect_enabled', $enabled); // Save the enabled/disabled state
        echo '<div class="updated"><p>Settings saved!</p></div>';
    }

    // Retrieve the current webhook URL, secret token, and enabled/disabled state from the database
    $current_webhook_url = get_option('bluelena_connect_webhook_url', '');
    $current_secret_token = get_option('bluelena_connect_secret_token', '');
    $current_enabled = get_option('bluelena_connect_enabled', 1); // Default to enabled

    // Display the form to input and save the webhook URL, secret token, and enabled/disabled state
    ?>
    <div class="wrap">
        <h2>BlueLena Connect Settings</h2>
        <p>This plugin syncs your WooCommerce Orders to the BlueLena platform. For issues email: suppor@bluelena.io.</p>
        <form method="post">
            <label for="webhook_url">Webhook URL:</label>
            <input type="text" name="webhook_url" id="webhook_url" value="<?php echo esc_attr($current_webhook_url); ?>" size="50">
            <p class="description">Enter the URL where post data will be sent.</p>
            
            <label for="secret_token">Secret Token:</label>
            <input type="text" name="secret_token" id="secret_token" value="<?php echo esc_attr($current_secret_token); ?>" size="50">
            <p class="description">Enter the secret token for authorization.</p>

            <label for="enabled">Enable/Disable:</label>
            <input type="checkbox" name="enabled" id="enabled" <?php checked($current_enabled, 1); ?>>
            <p class="description">Enable or disable the bluelena Connect functionality. Uncheck to disable the plugin.</p>

            <input type="submit" name="save_settings" class="button-primary" value="Save Settings">
        </form>
    </div>
    <?php
}

// In the order lists page, "All orders" add sync to "Bulk actions" that will send each order to the webhook
add_filter('bulk_actions-edit-shop_order', 'bluelena_connect_bulk_actions');

function bluelena_connect_bulk_actions($actions) {
    $actions['sync'] = 'Sync -> BlueLena';
    return $actions;
}

// Handle the sync bulk action
add_filter('handle_bulk_actions-edit-shop_order', 'bluelena_connect_handle_bulk_action', 10, 3);

/**
 * Handles bulk actions for syncing orders.
 *
 * @param string $redirect_to The URL to redirect to after the bulk action is performed.
 * @param string $action The bulk action being performed.
 * @param array $order_ids The IDs of the orders being synced.
 * @return string The URL to redirect to after the bulk action is performed.
 */
function bluelena_connect_handle_bulk_action($redirect_to, $action, $order_ids) {
    if ($action !== 'sync') {
        return $redirect_to;
    }

    // Loop through each order ID and enqueue them for sync
    foreach ($order_ids as $order_id) {
        // Enqueue the order for sync with a delay
        bluelena_connect_enqueue_sync($order_id);
    }

    // Redirect back to the orders page
    $redirect_to = add_query_arg('bulk_synced', count($order_ids), $redirect_to);
    return $redirect_to;
}

// Function to enqueue an order for sync with a delay
/**
 * Enqueues an order for syncing with a webhook.
 *
 * @param int $order_id The ID of the order to be synced.
 * @return void
 */
function bluelena_connect_enqueue_sync($order_id) {
    // Get all the currently scheduled events for 'send_order_to_webhook_scheduled'
    $scheduled_events = wp_get_scheduled_event('send_order_to_webhook_scheduled');

    // Calculate a delay based on the presence of scheduled events
    $delay = $scheduled_events ? time() + count($scheduled_events) * 10 : time();

    // Store the order ID in a custom queue
    $queued_orders = get_option('bluelena_connect_queued_orders', array());
    $queued_orders[] = $order_id;
    update_option('bluelena_connect_queued_orders', $queued_orders);

    // Schedule an event to process the queue with the calculated delay
    wp_schedule_single_event($delay, 'process_sync_queue_scheduled');
}

// Process the sync queue
add_action('process_sync_queue_scheduled', 'bluelena_connect_process_sync_queue');

/**
 * Processes the synchronization queue by retrieving queued order IDs and sending them to a webhook for synchronization.
 *
 * @return void
 *
 * @global mixed $wpdb WordPress database abstraction object.
 */
function bluelena_connect_process_sync_queue() {
    // Retrieve the queued order IDs
    $queued_orders = get_option('bluelena_connect_queued_orders', array());

    // Loop through and process each order
    foreach ($queued_orders as $order_id) {
        // Process the order for synchronization
        send_order_to_webhook($order_id);
    }

    // Clear the queue
    update_option('bluelena_connect_queued_orders', array());
}

// Display a notice after syncing orders
add_action('admin_notices', 'bluelena_connect_bulk_action_admin_notice');

/**
 * Displays an admin notice after bulk syncing orders.
 *
 * @return void
 */
function bluelena_connect_bulk_action_admin_notice() {
    if (!empty($_REQUEST['bulk_synced'])) {
        $synced_count = intval($_REQUEST['bulk_synced']);
        printf('<div id="message" class="updated fade"><p>%s %s.</p></div>', $synced_count, _n('order synced', 'orders synced', $synced_count));
    }
}