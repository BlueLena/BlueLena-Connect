# Sync and Integration tool for WordPress by BlueLena
contact: waqas@bluelena.io


## Documentation
### Main Plugin
The selected code is a WordPress plugin named "BlueLena Connect". This plugin is designed to send WordPress post data to a specified webhook URL. It does this by scheduling the sending of order data to the webhook when WooCommerce order creation and update events occur.

The plugin begins by including two PHP files: custom-meta-box.php and admin-menu.php. These files contain additional functionality related to the plugin's operation.

The plugin then adds two actions using the add_action function. These actions are tied to the woocommerce_new_order and woocommerce_order_status_changed events. When these events occur, the schedule_order_data_sending function is triggered.

The schedule_order_data_sending function checks if the BlueLena Connect functionality is enabled. If it is, it schedules a single event to send the order data to the webhook. This is done using the wp_schedule_single_event function, which schedules a one-time event in the WordPress cron system.

The plugin also adds an action for the send_order_to_webhook_scheduled event. When this event is triggered, the send_order_to_webhook function is called.

The send_order_to_webhook function retrieves the webhook URL and secret token from the WordPress options. It then gets the order object using the wc_get_order function. If the order is valid, it prepares the order data to be sent. This includes the order's data, UTM parameters, and product information. The function then sends a POST request to the webhook URL with the order data in JSON format. If the request fails, an error message is logged. If the request is successful, the response is processed.

In summary, this plugin allows WooCommerce order data to be sent to a specified webhook URL when an order is created or updated. This can be useful for integrating WooCommerce with external systems or services.

 send_order_to_webhook send_order_to_webhook

 ### Admin Menu
'admin-menu.php' file is a part of "BlueLena Connect". This file is designed to add an admin menu item in the WordPress dashboard, handle the bulk sync action for WooCommerce orders, and manage the settings for the plugin.

The bluelena_connect_menu function adds a submenu page to the "Tools" menu in the WordPress admin dashboard. This is done using the add_submenu_page function, which takes parameters for the parent slug, page title, menu title, capability, menu slug, and callback function.

The bluelena_connect_settings_page function is the callback function for the admin menu page. It displays a form to input and save the webhook URL, secret token, and enabled/disabled state. If the form is submitted, it sanitizes and saves the inputted values using the update_option function. It also retrieves the current values from the database using the get_option function.

The bluelena_connect_bulk_actions function adds a "Sync -> BlueLena" option to the "Bulk actions" dropdown in the "All orders" page of WooCommerce. This is done by adding a filter to the bulk_actions-edit-shop_order hook.

The bluelena_connect_handle_bulk_action function handles the "Sync -> BlueLena" bulk action. It loops through each selected order and enqueues them for sync using the bluelena_connect_enqueue_sync function.

The bluelena_connect_enqueue_sync function enqueues an order for sync with a delay. It stores the order ID in a custom queue and schedules an event to process the queue.

The bluelena_connect_process_sync_queue function processes the sync queue. It retrieves the queued order IDs and sends them to the webhook for synchronization. After processing, it clears the queue.

Finally, the bluelena_connect_bulk_action_admin_notice function displays a notice after syncing orders. It adds an action to the admin_notices hook and displays a message indicating the number of orders synced.

### How to use
#### Install the plugin by uploading the zipped plugin folder to wordpress.
After installing the plugin, in the tools menu in wordpress, set up your webhook endpoint and secret token provided by BlueLena. After enabling the plugin, the data flow will be ready and you dont have to do anything.
To sync previous WooCommerce Order data:
- Go to 'Orders' in the WooCommerce Menu
- Select all orders you want to sync
- In 'Bulk Actions' (in top right) open the dropdown menu and click on "Sync -> BlueLena"
- Click 'Apply'
- All orders will now start syncing one by one
