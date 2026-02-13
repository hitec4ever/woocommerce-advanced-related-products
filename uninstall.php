<?php
/**
 * Uninstall script for WooCommerce Advanced Related Products
 *
 * This file is called when the plugin is uninstalled via WordPress admin.
 * It cleans up all plugin data from the database.
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check - only administrators can uninstall
if (!current_user_can('activate_plugins')) {
    exit;
}

// Remove plugin options
delete_option('wc_advanced_related_products_settings');
delete_option('wc_advanced_related_products_shortcodes');

// Remove any transients with our prefix
global $wpdb;
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_wc_arp_%'));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_wc_arp_%'));

// Remove WPML strings if they exist
if (function_exists('icl_unregister_string')) {
    icl_unregister_string('wc-advanced-related-products', 'Related Products Title');
}

// Clear any cached data
wp_cache_flush();