<?php
/**
 * FILEPATH: bluelena-connect/admin-menu.php
 * 
 * This file contains the code for adding an admin menu item and handling the sync bulk action for WooCommerce orders.
 * 
 * The `bluelena_connect_menu` function adds a submenu page to the "Tools" menu in the WordPress admin dashboard.
 * The `bluelena_connect_settings_page` function displays a form to input and save the webhook URL, secret token, and enabled/disabled state.
 * The `bluelena_connect_enqueue_sync` function enqueues an order for sync with a delay.
 * The `bluelena_connect_process_sync_queue` function processes the sync queue by sending each order to the webhook.
 * The `bluelena_connect_bulk_action_admin_notice` function displays a notice after syncing orders.
 */
// Add an admin menu item
add_action('admin_menu', 'bluelena_connect_menu');
add_action('admin_menu', 'bluelena_connect_menu_resync');

/**
 * Registers a submenu page under Tools menu for BlueLena Connect Settings.
 *
 * @return void
 */
function bluelena_connect_menu() {
    add_submenu_page(
        'tools.php', // Parent slug
        'BlueLena Connect - Settings', // Page title
        'BlueLena Connect - Settings', // Menu title
        'manage_options', // Capability
        'bluelena-connect-settings', // Menu slug
        'bluelena_connect_settings_page' // Callback function
    );
}

function bluelena_connect_menu_resync() {
    add_submenu_page(
        'tools.php', // Parent slug
        'BlueLena Connect - Resync', // Page title
        'BlueLena Connect - Resync', // Menu title
        'manage_options', // Capability
        'bluelena-connect-resync', // Menu slug
        'bluelena_resync_menu_subitem' // Callback function
    );
    //remove_submenu_page('tools.php', 'bluelena-connect-resync');
}

// Callback function for the admin menu page
/**
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
        <h1>BlueLena Connect</h1>
        <p>This plugin syncs your WooCommerce Orders to the BlueLena platform. For issues email: support@bluelena.io.</p>
        <div class="wp-menu">
            <ul class="wp-submenu wp-submenu-wrap">
                <li class="wp-first-item"><a href="tools.php?page=bluelena-connect-settings"><?php esc_html_e('Settings', 'text-domain'); ?></a></li>
                <li><a href="tools.php?page=bluelena-connect-resync"><?php esc_html_e('Resync', 'text-domain'); ?></a></li>
            </ul>
        </div>
        <hr>
        <h2>Settings</h2>
        <form method="post">
            <label for="webhook_url">Webhook URL:</label>
            <input type="text" name="webhook_url" id="webhook_url" value="<?php echo esc_attr($current_webhook_url); ?>" size="50">
            <p class="description">Enter the URL where post data will be sent.</p>

            <label for="secret_token">Secret Token:</label>
            <input type="text" name="secret_token" id="secret_token" value="<?php echo esc_attr($current_secret_token); ?>" size="50">
            <p class="description">Enter the secret token for authorization.</p>

            <label for="enabled">Enable/Disable:</label>
            <input type="checkbox" name="enabled" id="enabled" <?php checked($current_enabled, 1); ?>>
            <p class="description">Enable or disable the BlueLena Connect auto-sync. Uncheck to disable the plugin.</p>

            <input type="submit" name="save_settings" class="button-primary" value="Save Settings">
        </form>
    </div>
    <?php
}

// Function to enqueue an order for sync with a delay
/**
 * Enqueues an order for syncing with a webhook.
 *
 * @param int $order_id The ID of the order to be synced.
 * @return void
 */
function bluelena_connect_enqueue_sync($order_id) {
    // Get all the currently scheduled events for 'bluelena_send_order_to_webhook_scheduled'
    $scheduled_events = wp_get_scheduled_event('bluelena_send_order_to_webhook_scheduled');

    // Calculate a delay based on the presence of scheduled events
    $delay = $scheduled_events ? time() + count($scheduled_events) * 2 : time();

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
        bluelena_send_order_to_webhook($order_id, '');
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

// Callback function for the admin menu page
/**
 * Displays the BlueLena Connect settings page and saves the webhook URL, secret token, and enabled/disabled state.
 * Retrieves the current webhook URL, secret token, and enabled/disabled state from the database.
 *
 * @return void
 */
function bluelena_resync_menu_subitem() {
    $ids_received = "";
    if (isset($_POST['export_orders'])) {
        $ids_received = sanitize_text_field($_POST['order_ids']);
        $ids_received = array_map('intval', explode(",", $ids_received));
        $ids_received = array_filter($ids_received, 'is_numeric');
        foreach ($ids_received as $order_id) {
            // Enqueue the order for sync with a delay
            bluelena_connect_enqueue_sync($order_id);
        }
        $ids_received = implode(",", $ids_received);
        echo '<div class="updated"><p>Orders sent for resync: ' . esc_html($ids_received) . ' </p></div>';
    }
    ?>
    <div class="wrap">
        <h1>BlueLena Connect</h1>
        <div class="wp-menu">
            <ul class="wp-submenu wp-submenu-wrap">
                <li class="wp-first-item"><a href="tools.php?page=bluelena-connect-settings"><?php esc_html_e('Settings', 'text-domain'); ?></a></li>
                <li><a href="tools.php?page=bluelena-connect-resync"><?php esc_html_e('Resync', 'text-domain'); ?></a></li>
            </ul>
        </div>
        <h2>Resync Orders</h2>
        <p>This plugin syncs your WooCommerce Orders to the BlueLena platform. Put order IDs in a csv format to sync those orders. E.g: 123, 124, 125 ...</p>
        <form method="post">
            <label for="order_ids">Order IDs</label><br>
            <textarea name="order_ids" id="order_ids" cols="70" rows="10"></textarea>
            <br>
            <br>
            <input type="submit" name="export_orders" class="button-primary" value="Resync Orders">
        </form>
    </div>
    <?php
}
