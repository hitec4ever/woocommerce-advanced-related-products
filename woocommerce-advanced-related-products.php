<?php
/**
 * Plugin Name: WooCommerce Advanced Related Products
 * Plugin URI: https://jaapdewit.com
 * Description: Display WooCommerce related products by category with customizable shortcode and advanced settings.
 * Version: 2.3.3
 * Author: <a href="https://jaapdewit.com" target="_blank">Jaap de Wit</a>
 * Author URI: https://jaapdewit.com
 * Text Domain: wc-advanced-related-products
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 9.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Define plugin constants
define('WC_ADVANCED_RELATED_PRODUCTS_VERSION', '2.3.3');
define('WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN', 'wc-advanced-related-products');

/**
 * Plugin Update Checker - checks GitHub for new releases
 */
require_once WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$wc_arp_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/hitec4ever/WooCommerce-Advanced-Related-Products/',
    __FILE__,
    'woocommerce-advanced-related-products'
);
$wc_arp_update_checker->getVcsApi()->enableReleaseAssets();

/**
 * Main plugin class
 */
class WC_Advanced_Related_Products {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Declare HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));

        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Include required files
        $this->include_files();
    }

    /**
     * Declare compatibility with WooCommerce HPOS (Custom Order Tables)
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
    
    /**
     * Include required files
     */
    private function include_files() {
        require_once WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_DIR . 'includes/class-shortcode.php';
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize admin
        if (is_admin()) {
            WC_Advanced_Related_Products_Admin::get_instance();
        }
        
        // Initialize shortcode
        WC_Advanced_Related_Products_Shortcode::get_instance();
        
        // WPML/Polylang compatibility
        add_action('wpml_loaded', array($this, 'wpml_compatibility'));
        add_action('plugins_loaded', array($this, 'polylang_compatibility'));
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $default_settings = array(
            'number_of_products' => 4,
            'number_of_columns' => 4,
            'show_by_category' => 1,
            'sort_by' => 'date',
            'related_products_title' => __('Related Products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            'title_alignment' => 'left',
            'show_price' => 1,
            'show_add_to_cart' => 0,
            'container_class' => 'related-products-container'
        );
        
        if (!get_option('wc_advanced_related_products_settings')) {
            add_option('wc_advanced_related_products_settings', $default_settings);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * WPML compatibility
     */
    public function wpml_compatibility() {
        if (function_exists('icl_register_string')) {
            $settings = get_option('wc_advanced_related_products_settings', array());
            if (isset($settings['related_products_title'])) {
                icl_register_string(
                    WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN,
                    'Related Products Title',
                    $settings['related_products_title']
                );
            }
        }
    }
    
    /**
     * Polylang compatibility
     */
    public function polylang_compatibility() {
        if (function_exists('pll_register_string')) {
            $settings = get_option('wc_advanced_related_products_settings', array());
            if (isset($settings['related_products_title'])) {
                pll_register_string(
                    'Related Products Title',
                    $settings['related_products_title'],
                    WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN
                );
            }
        }
    }
}

// Initialize the plugin
WC_Advanced_Related_Products::get_instance();