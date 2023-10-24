<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// if uninstall not called from WordPress exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Only remove ALL product and page data if WC_REMOVE_ALL_DATA constant is set to true in user's
 * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 */
if ( defined( 'WC_REMOVE_ALL_DATA' ) && true === WC_REMOVE_ALL_DATA ) {
	// Pages.
	wp_trash_post( get_option( 'airwallex_payment_method_card_page_id' ) );
	wp_trash_post( get_option( 'airwallex_payment_method_wechat_page_id' ) );
	wp_trash_post( get_option( 'airwallex_payment_method_all_page_id' ) );

	// Delete options.
	delete_option( 'airwallex_client_id' );
	delete_option( 'airwallex_api_key' );
	delete_option( 'airwallex_webhook_secret' );
	delete_option( 'airwallex_enable_sandbox' );
	delete_option( 'airwallex_temporary_order_status_after_decline' );
	delete_option( 'airwallex_order_status_pending' );
	delete_option( 'airwallex_order_status_authorized' );
	delete_option( 'airwallex_cronjob_interval' );
	delete_option( 'airwallex_do_js_logging' );
	delete_option( 'airwallex_merchant_country' );
	delete_option( 'airwallex-online-payments-gatewayairwallex_main_settings' );
	delete_option( 'airwallex-online-payments-gatewayairwallex_card_settings' );
	delete_option( 'airwallex-online-payments-gatewayairwallex_wechat_settings' );
	delete_option( 'airwallex_do_remote_logging' );

	// Clear any cached data that has been removed.
	wp_cache_flush();
}
