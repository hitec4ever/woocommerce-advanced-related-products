<?php
/**
 * Admin functionality for WooCommerce Advanced Related Products
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WC_Advanced_Related_Products_Admin {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Plugin settings
     */
    private $settings = array();
    
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
        $this->settings = get_option('wc_advanced_related_products_settings', $this->get_default_settings());
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('plugin_action_links_' . plugin_basename(WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_DIR . 'woocommerce-advanced-related-products.php'), array($this, 'add_settings_link'));
        
        // Hide admin notices on our settings page
        add_action('admin_head', array($this, 'hide_admin_notices'));
        
        // Handle AJAX requests
        add_action('wp_ajax_wc_advanced_related_products_generate_shortcode', array($this, 'ajax_generate_shortcode'));
        add_action('wp_ajax_wc_advanced_related_products_delete_shortcode', array($this, 'ajax_delete_shortcode'));
        add_action('wp_ajax_wc_advanced_related_products_update_shortcode', array($this, 'ajax_update_shortcode'));
        add_action('wp_ajax_wc_advanced_related_products_get_shortcode_data', array($this, 'ajax_get_shortcode_data'));
    }
    
    /**
     * Get default settings
     */
    private function get_default_settings() {
        return array(
            'number_of_products' => 4,
            'number_of_columns' => 4,
            'show_by_category' => 1,
            'sort_by' => 'date',
            'related_products_title' => __('Related Products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            'title_alignment' => 'left',
            'show_price' => 1,
            'container_class' => 'related-products-container'
        );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Advanced Related Products Settings', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            __('Advanced Related Products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            'manage_options',
            'wc-advanced-related-products-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wc_advanced_related_products_group',
            'wc_advanced_related_products_settings',
            array($this, 'sanitize_settings')
        );
        
        // Register shortcodes option
        register_setting(
            'wc_advanced_related_products_shortcodes_group',
            'wc_advanced_related_products_shortcodes',
            array($this, 'sanitize_shortcodes')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_wc-advanced-related-products-settings') {
            return;
        }
        
        wp_enqueue_style(
            'wc-advanced-related-products-admin',
            WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WC_ADVANCED_RELATED_PRODUCTS_VERSION
        );
        
        wp_enqueue_script(
            'wc-advanced-related-products-admin',
            WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WC_ADVANCED_RELATED_PRODUCTS_VERSION,
            true
        );
        
        // Get categories for JavaScript
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));
        
        $categories_data = array();
        if (!is_wp_error($categories)) {
            foreach ($categories as $category) {
                $categories_data[] = array(
                    'id' => $category->term_id,
                    'name' => $category->name
                );
            }
        }
        
        // Get attributes for JavaScript
        $attributes = wc_get_attribute_taxonomies();
        $attributes_data = array();
        foreach ($attributes as $attribute) {
            $attributes_data[] = array(
                'name' => $attribute->attribute_name,
                'label' => $attribute->attribute_label
            );
        }
        
        // Localize script for AJAX
        wp_localize_script('wc-advanced-related-products-admin', 'wcAdvancedRelatedProducts', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_advanced_related_products_nonce'),
            'categories' => $categories_data,
            'attributes' => $attributes_data,
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this shortcode?', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
                'shortcodeGenerated' => __('Shortcode generated successfully!', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
                'shortcodeUpdated' => __('Shortcode updated successfully!', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
                'shortcodeDeleted' => __('Shortcode deleted successfully!', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
                'shortcodeCopied' => __('Shortcode copied to clipboard!', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
                'error' => __('An error occurred. Please try again.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
                'titleRequired' => __('Shortcode title is required.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN)
            )
        ));
    }
    
    /**
     * Add settings link to plugin actions
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-advanced-related-products-settings'),
            __('Settings', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN)
        );
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Hide third-party admin notices on our settings page
     */
    public function hide_admin_notices() {
        global $pagenow;

        if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'wc-advanced-related-products-settings') {
            // Hide notices via CSS rather than removing all actions,
            // so security-critical notices from WordPress core are still processed
            echo '<style>.notice:not(.wc-advanced-related-products-notice) { display: none !important; }</style>';
        }
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'generator';
        ?>
        <div class="wrap wc-product-sets-wrap">
            <div class="wc-product-sets-header">
                <div class="header-left">
                    <h1 class="header-title"><?php echo esc_html__('Advanced Related Products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></h1>
                    <p class="header-subtitle"><?php echo esc_html__('Advanced shortcode generator and manager for related products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                    <div class="header-tabs" style="margin-top: 15px;">
                        <a href="?page=wc-advanced-related-products-settings&tab=generator" class="tab-button <?php echo $active_tab === 'generator' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                            </svg>
                            <?php echo esc_html__('Generator', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>
                        </a>
                        <a href="?page=wc-advanced-related-products-settings&tab=manager" class="tab-button <?php echo $active_tab === 'manager' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M12,15.5A3.5,3.5 0 0,1 8.5,12A3.5,3.5 0 0,1 12,8.5A3.5,3.5 0 0,1 15.5,12A3.5,3.5 0 0,1 12,15.5M19.43,12.97C19.47,12.65 19.5,12.33 19.5,12C19.5,11.67 19.47,11.34 19.43,11L21.54,9.37C21.73,9.22 21.78,8.95 21.66,8.73L19.66,5.27C19.54,5.05 19.27,4.96 19.05,5.05L16.56,6.05C16.04,5.66 15.5,5.32 14.87,5.07L14.5,2.42C14.46,2.18 14.25,2 14,2H10C9.75,2 9.54,2.18 9.5,2.42L9.13,5.07C8.5,5.32 7.96,5.66 7.44,6.05L4.95,5.05C4.73,4.96 4.46,5.05 4.34,5.27L2.34,8.73C2.22,8.95 2.27,9.22 2.46,9.37L4.57,11C4.53,11.34 4.5,11.67 4.5,12C4.5,12.33 4.53,12.65 4.57,12.97L2.46,14.63C2.27,14.78 2.22,15.05 2.34,15.27L4.34,18.73C4.46,18.95 4.73,19.03 4.95,18.95L7.44,17.94C7.96,18.34 8.5,18.68 9.13,18.93L9.5,21.58C9.54,21.82 9.75,22 10,22H14C14.25,22 14.46,21.82 14.5,21.58L14.87,18.93C15.5,18.68 16.04,18.34 16.56,17.94L19.05,18.95C19.27,19.03 19.54,18.95 19.66,18.73L21.66,15.27C21.78,15.05 21.73,14.78 21.54,14.63L19.43,12.97Z"/>
                            </svg>
                            <?php echo esc_html__('Manager', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>
                        </a>
                        <a href="?page=wc-advanced-related-products-settings&tab=settings" class="tab-button <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8M12,10A2,2 0 0,0 10,12A2,2 0 0,0 12,14A2,2 0 0,0 14,12A2,2 0 0,0 12,10M10,22C9.75,22 9.54,21.82 9.5,21.58L9.13,18.93C8.5,18.68 7.96,18.34 7.44,17.94L4.95,18.95C4.73,19.03 4.46,18.95 4.34,18.73L2.34,15.27C2.22,15.05 2.27,14.78 2.46,14.63L4.57,12.97C4.53,12.65 4.5,12.33 4.5,12C4.5,11.67 4.53,11.34 4.57,11L2.46,9.37C2.27,9.22 2.22,8.95 2.34,8.73L4.34,5.27C4.46,5.05 4.73,4.96 4.95,5.05L7.44,6.05C7.96,5.66 8.5,5.32 9.13,5.07L9.5,2.42C9.54,2.18 9.75,2 10,2H14C14.25,2 14.46,2.18 14.5,2.42L14.87,5.07C15.5,5.32 16.04,5.66 16.56,6.05L19.05,5.05C19.27,4.96 19.54,5.05 19.66,5.27L21.66,8.73C21.78,8.95 21.73,9.22 21.54,9.37L19.43,11C19.47,11.34 19.5,11.67 19.5,12C19.5,12.33 19.47,12.65 19.43,12.97L21.54,14.63C21.73,14.78 21.78,15.05 21.66,15.27L19.66,18.73C19.54,18.95 19.27,19.03 19.05,18.95L16.56,17.94C16.04,18.34 15.5,18.68 14.87,18.93L14.5,21.58C14.46,21.82 14.25,22 14,22H10M11.25,4L10.88,6.61C9.68,6.86 8.62,7.5 7.85,8.39L5.44,7.35L4.69,8.65L6.8,10.2C6.4,11.37 6.4,12.64 6.8,13.8L4.68,15.36L5.43,16.66L7.86,15.62C8.63,16.5 9.68,17.14 10.87,17.38L11.24,20H12.76L13.13,17.39C14.32,17.14 15.37,16.5 16.14,15.62L18.57,16.66L19.32,15.36L17.2,13.81C17.6,12.64 17.6,11.37 17.2,10.2L19.31,8.65L18.56,7.35L16.15,8.39C15.38,7.5 14.32,6.86 13.12,6.62L12.75,4H11.25Z"/>
                            </svg>
                            <?php echo esc_html__('Global shortcode', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>
                        </a>
                    </div>
                </div>
                <div class="header-right">
                    <img src="<?php echo WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_URL . 'assets/images/logo.png'; ?>" alt="Jaap de Wit" class="logo" />
                </div>
            </div>
            
            <div class="wc-product-sets-container">
                <div class="settings-container">
                    <?php if ($active_tab === 'generator'): ?>
                        <div class="generator-layout">
                            <div class="generator-form-column">
                                <?php $this->render_generator_tab(); ?>
                            </div>
                            
                            <div class="generator-features-column">
                                <div class="feature-grid">
                                    <div class="feature-card">
                                        <div class="feature-icon">
                                            <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor">
                                                <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                                            </svg>
                                        </div>
                                        <h3><?php esc_html_e('Shortcode Generator', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></h3>
                                        <p><?php esc_html_e('Create custom shortcodes with advanced filtering and display options.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="feature-card">
                                        <div class="feature-icon">
                                            <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor">
                                                <path d="M12,15.5A3.5,3.5 0 0,1 8.5,12A3.5,3.5 0 0,1 12,8.5A3.5,3.5 0 0,1 15.5,12A3.5,3.5 0 0,1 12,15.5M19.43,12.97C19.47,12.65 19.5,12.33 19.5,12C19.5,11.67 19.47,11.34 19.43,11L21.54,9.37C21.73,9.22 21.78,8.95 21.66,8.73L19.66,5.27C19.54,5.05 19.27,4.96 19.05,5.05L16.56,6.05C16.04,5.66 15.5,5.32 14.87,5.07L14.5,2.42C14.46,2.18 14.25,2 14,2H10C9.75,2 9.54,2.18 9.5,2.42L9.13,5.07C8.5,5.32 7.96,5.66 7.44,6.05L4.95,5.05C4.73,4.96 4.46,5.05 4.34,5.27L2.34,8.73C2.22,8.95 2.27,9.22 2.46,9.37L4.57,11C4.53,11.34 4.5,11.67 4.5,12C4.5,12.33 4.53,12.65 4.57,12.97L2.46,14.63C2.27,14.78 2.22,15.05 2.34,15.27L4.34,18.73C4.46,18.95 4.73,19.03 4.95,18.95L7.44,17.94C7.96,18.34 8.5,18.68 9.13,18.93L9.5,21.58C9.54,21.82 9.75,22 10,22H14C14.25,22 14.46,21.82 14.5,21.58L14.87,18.93C15.5,18.68 16.04,18.34 16.56,17.94L19.05,18.95C19.27,19.03 19.54,18.95 19.66,18.73L21.66,15.27C21.78,15.05 21.73,14.78 21.54,14.63L19.43,12.97Z"/>
                                            </svg>
                                        </div>
                                        <h3><?php esc_html_e('Shortcode Manager', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></h3>
                                        <p><?php esc_html_e('Manage all your created shortcodes in one central location.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="feature-card">
                                        <div class="feature-icon">
                                            <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor">
                                                <path d="M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8M12,10A2,2 0 0,0 10,12A2,2 0 0,0 12,14A2,2 0 0,0 14,12A2,2 0 0,0 12,10M10,22C9.75,22 9.54,21.82 9.5,21.58L9.13,18.93C8.5,18.68 7.96,18.34 7.44,17.94L4.95,18.95C4.73,19.03 4.46,18.95 4.34,18.73L2.34,15.27C2.22,15.05 2.27,14.78 2.46,14.63L4.57,12.97C4.53,12.65 4.5,12.33 4.5,12C4.5,11.67 4.53,11.34 4.57,11L2.46,9.37C2.27,9.22 2.22,8.95 2.34,8.73L4.34,5.27C4.46,5.05 4.73,4.96 4.95,5.05L7.44,6.05C7.96,5.66 8.5,5.32 9.13,5.07L9.5,2.42C9.54,2.18 9.75,2 10,2H14C14.25,2 14.46,2.18 14.5,2.42L14.87,5.07C15.5,5.32 16.04,5.66 16.56,6.05L19.05,5.05C19.27,4.96 19.54,5.05 19.66,5.27L21.66,8.73C21.78,8.95 21.73,9.22 21.54,9.37L19.43,11C19.47,11.34 19.5,11.67 19.5,12C19.5,12.33 19.47,12.65 19.43,12.97L21.54,14.63C21.73,14.78 21.78,15.05 21.66,15.27L19.66,18.73C19.54,18.95 19.27,19.03 19.05,18.95L16.56,17.94C16.04,18.34 15.5,18.68 14.87,18.93L14.5,21.58C14.46,21.82 14.25,22 14,22H10M11.25,4L10.88,6.61C9.68,6.86 8.62,7.5 7.85,8.39L5.44,7.35L4.69,8.65L6.8,10.2C6.4,11.37 6.4,12.64 6.8,13.8L4.68,15.36L5.43,16.66L7.86,15.62C8.63,16.5 9.68,17.14 10.87,17.38L11.24,20H12.76L13.13,17.39C14.32,17.14 15.37,16.5 16.14,15.62L18.57,16.66L19.32,15.36L17.2,13.81C17.6,12.64 17.6,11.37 17.2,10.2L19.31,8.65L18.56,7.35L16.15,8.39C15.38,7.5 14.32,6.86 13.12,6.62L12.75,4H11.25Z"/>
                                            </svg>
                                        </div>
                                        <h3><?php esc_html_e('Global Settings', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></h3>
                                        <p><?php esc_html_e('Configure default settings and global preferences for all shortcodes.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="feature-card donation-card">
                                        <div class="feature-icon">
                                            <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor">
                                                <path d="M12,2C6.48,2 2,6.48 2,12C2,17.52 6.48,22 12,22C17.52,22 22,17.52 22,12C22,6.48 17.52,2 12,2M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M16.5,10.5L15.09,9.09L10.5,13.68L8.91,12.09L7.5,13.5L10.5,16.5L16.5,10.5Z"/>
                                            </svg>
                                        </div>
                                        <h3><?php esc_html_e('Support Development', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></h3>
                                        <p><?php esc_html_e('Help us improve this plugin with your support. Every contribution makes a difference!', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                        <div class="donation-button-wrapper">
                                            <div id="donate-button-container-arp">
                                                <div id="donate-button-arp"></div>
                                                <script src="https://www.paypalobjects.com/donate/sdk/donate-sdk.js" charset="UTF-8"></script>
                                                <script>
                                                PayPal.Donation.Button({
                                                env:'production',
                                                hosted_button_id:'DFJBYBCN94MAU',
                                                image: {
                                                src:'https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif',
                                                alt:'Donate with PayPal button',
                                                title:'PayPal - The safer, easier way to pay online!',
                                                }
                                                }).render('#donate-button-arp');
                                                </script>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($active_tab === 'manager'): ?>
                        <?php $this->render_manager_tab(); ?>
                    <?php elseif ($active_tab === 'settings'): ?>
                        <?php $this->render_settings_tab(); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render shortcode generator tab
     */
    private function render_generator_tab() {
        ?>
        <div class="wc-shortcode-generator">
            <div class="wc-generator-header">
                <h3><?php echo esc_html__('Generate New Shortcode', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></h3>
            </div>
            
            <form id="wc-shortcode-generator-form">
                <div class="wc-advanced-related-products-settings-grid">
                    
                    <!-- Shortcode Title -->
                    <div class="wc-advanced-related-products-setting-row">
                        <div class="setting-label">
                            <label><?php echo esc_html__('Shortcode Title', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?> *</label>
                            <p class="setting-description"><?php echo esc_html__('Unique name for this shortcode configuration', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                        </div>
                        <div class="setting-field">
                            <input type="text" name="shortcode_title" class="wc-input-field" required />
                        </div>
                    </div>

                    <!-- Section Title and Title Alignment -->
                    <div class="wc-settings-row-pair">
                        <div class="wc-advanced-related-products-setting-row">
                            <div class="setting-label">
                                <label><?php echo esc_html__('Section Title', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                <p class="setting-description"><?php echo esc_html__('The heading text displayed above products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                            </div>
                            <div class="setting-field">
                                <input type="text" name="section_title" value="<?php echo esc_attr__('Related Products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>" class="wc-input-field" />
                            </div>
                        </div>

                        <div class="wc-advanced-related-products-setting-row">
                            <div class="setting-label">
                                <label><?php echo esc_html__('Title Alignment', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                <p class="setting-description"><?php echo esc_html__('Alignment of the section title', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                            </div>
                            <div class="setting-field">
                                <select name="title_alignment" class="wc-select-field">
                                    <option value="left" selected><?php echo esc_html__('Left', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                    <option value="center"><?php echo esc_html__('Center', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                    <option value="right"><?php echo esc_html__('Right', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Number of Products and Columns -->
                    <div class="wc-settings-row-pair">
                        <div class="wc-advanced-related-products-setting-row">
                            <div class="setting-label">
                                <label><?php echo esc_html__('Number of Products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                <p class="setting-description"><?php echo esc_html__('Maximum number of products to display (1-12)', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                            </div>
                            <div class="setting-field">
                                <input type="number" name="number_of_products" value="4" min="1" max="12" class="wc-input-field" />
                            </div>
                        </div>

                        <div class="wc-advanced-related-products-setting-row">
                            <div class="setting-label">
                                <label><?php echo esc_html__('Columns per Row', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                <p class="setting-description"><?php echo esc_html__('Number of product columns to display per row', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                            </div>
                            <div class="setting-field">
                                <select name="number_of_columns" class="wc-select-field">
                                    <option value="2"><?php echo esc_html__('2 Columns', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                    <option value="3"><?php echo esc_html__('3 Columns', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                    <option value="4" selected><?php echo esc_html__('4 Columns', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                    <option value="6"><?php echo esc_html__('6 Columns', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Type -->
                    <div class="wc-advanced-related-products-setting-row">
                        <div class="setting-label">
                            <label><?php echo esc_html__('Filter Type', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                            <p class="setting-description"><?php echo esc_html__('Choose how to filter related products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                        </div>
                        <div class="setting-field">
                            <select name="filter_type" class="wc-select-field" id="filter-type-select">
                                <option value="category"><?php echo esc_html__('Filter by Category', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                <option value="attribute"><?php echo esc_html__('Filter by Attribute', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Category Options -->
                    <div class="wc-advanced-related-products-setting-row" id="category-options">
                        <div class="setting-label">
                            <label><?php echo esc_html__('Category Selection', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                            <p class="setting-description"><?php echo esc_html__('Choose category filtering method', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                        </div>
                        <div class="setting-field">
                            <div class="category-selection">
                                <label class="radio-option">
                                    <input type="radio" name="category_method" value="current" checked />
                                    <?php echo esc_html__('Same category as current product', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="category_method" value="specific" />
                                    <?php echo esc_html__('Specific category', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>
                                </label>
                                <div id="specific-category" style="display: none;">
                                    <select name="specific_category" class="wc-select-field">
                                        <option value=""><?php echo esc_html__('Select a category', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                        <?php echo $this->get_categories_options(); ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attribute Options -->
                    <div class="wc-advanced-related-products-setting-row" id="attribute-options" style="display: none;">
                        <div class="setting-label">
                            <label><?php echo esc_html__('Product Attribute', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                            <p class="setting-description"><?php echo esc_html__('Select attribute to match with current product', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                        </div>
                        <div class="setting-field">
                            <select name="product_attribute" class="wc-select-field">
                                <option value=""><?php echo esc_html__('Select an attribute', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                <?php echo $this->get_attributes_options(); ?>
                            </select>
                        </div>
                    </div>

                    <!-- Sort By -->
                    <div class="wc-advanced-related-products-setting-row">
                        <div class="setting-label">
                            <label><?php echo esc_html__('Sort Products By', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                            <p class="setting-description"><?php echo esc_html__('How to sort the related products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                        </div>
                        <div class="setting-field">
                            <select name="sort_by" class="wc-select-field">
                                <option value="date" selected><?php echo esc_html__('Date (Newest First)', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                <option value="popularity"><?php echo esc_html__('Popularity', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                <option value="price_low"><?php echo esc_html__('Price (Low to High)', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                <option value="price_high"><?php echo esc_html__('Price (High to Low)', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                <option value="rating"><?php echo esc_html__('Customer Rating', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                <option value="random"><?php echo esc_html__('Random', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Show Price -->
                    <div class="wc-advanced-related-products-setting-row">
                        <div class="setting-label">
                            <label><?php echo esc_html__('Show Product Price', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                            <p class="setting-description"><?php echo esc_html__('Display product prices', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                        </div>
                        <div class="setting-field">
                            <label class="wc-toggle-switch">
                                <input type="checkbox" name="show_price" value="1" checked />
                                <span class="wc-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Display Mode -->
                    <div class="wc-advanced-related-products-setting-row">
                        <div class="setting-label">
                            <label><?php echo esc_html__('Display Mode', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                            <p class="setting-description"><?php echo esc_html__('Show products in a grid or as a slider/carousel', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                        </div>
                        <div class="setting-field">
                            <select name="display_mode" class="wc-select-field" id="display-mode-select">
                                <option value="grid"><?php echo esc_html__('Grid', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                                <option value="slider"><?php echo esc_html__('Slider / Carousel', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Slider Options (shown only when display_mode is slider) -->
                    <div id="slider-options" style="display: none;">
                        <div class="wc-settings-row-pair">
                            <div class="wc-advanced-related-products-setting-row">
                                <div class="setting-label">
                                    <label><?php echo esc_html__('Loop Slider', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                    <p class="setting-description"><?php echo esc_html__('Continuously loop through products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                </div>
                                <div class="setting-field">
                                    <label class="wc-toggle-switch">
                                        <input type="checkbox" name="slider_loop" value="1" />
                                        <span class="wc-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="wc-advanced-related-products-setting-row">
                                <div class="setting-label">
                                    <label><?php echo esc_html__('Show Navigation', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                    <p class="setting-description"><?php echo esc_html__('Show previous/next navigation arrows', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                </div>
                                <div class="setting-field">
                                    <label class="wc-toggle-switch">
                                        <input type="checkbox" name="slider_arrows" value="1" checked />
                                        <span class="wc-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="wc-settings-row-pair">
                            <div class="wc-advanced-related-products-setting-row">
                                <div class="setting-label">
                                    <label><?php echo esc_html__('Auto Slide', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                    <p class="setting-description"><?php echo esc_html__('Automatically advance slides', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                </div>
                                <div class="setting-field">
                                    <label class="wc-toggle-switch">
                                        <input type="checkbox" name="slider_autoplay" value="1" />
                                        <span class="wc-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="wc-advanced-related-products-setting-row">
                                <div class="setting-label">
                                    <label><?php echo esc_html__('Slide Interval (ms)', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                    <p class="setting-description"><?php echo esc_html__('Time between slides in milliseconds (1000-30000)', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                </div>
                                <div class="setting-field">
                                    <input type="number" name="slider_interval" value="6000" min="1000" max="30000" step="500" class="wc-input-field" />
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                
                <div class="wc-advanced-related-products-save-section">
                    <button type="submit" class="wc-advanced-related-products-save-btn">
                        <?php echo esc_html__('Generate Shortcode', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render manager tab
     */
    private function render_manager_tab() {
        $shortcodes = get_option('wc_advanced_related_products_shortcodes', array());
        ?>
        <div class="wc-shortcode-manager">
            <div class="wc-manager-header">
                <h3><?php echo esc_html__('Manage Shortcodes', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></h3>
            </div>
            
            <?php if (empty($shortcodes)): ?>
                <div class="wc-no-shortcodes">
                    <p><?php echo esc_html__('No custom shortcodes created yet.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                    <a href="?page=wc-advanced-related-products-settings&tab=generator" class="wc-advanced-related-products-save-btn">
                        <?php echo esc_html__('Create Your First Shortcode', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="wc-shortcodes-table-container">
                    <table class="wc-shortcodes-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Title', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></th>
                                <th><?php echo esc_html__('Shortcode', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></th>
                                <th><?php echo esc_html__('Filter Type', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></th>
                                <th><?php echo esc_html__('Products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></th>
                                <th><?php echo esc_html__('Columns', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></th>
                                <th><?php echo esc_html__('Actions', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shortcodes as $id => $shortcode): ?>
                                <tr data-shortcode-id="<?php echo esc_attr($id); ?>">
                                    <td>
                                        <strong><?php echo esc_html($shortcode['title']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="shortcode-display">
                                            <code class="shortcode-code" data-shortcode="[related_products_by_category id=&quot;<?php echo esc_attr($id); ?>&quot;]">
                                                [related_products_by_category id="<?php echo esc_attr($id); ?>"]
                                            </code>
                                            <button type="button" class="copy-shortcode-btn" data-shortcode="[related_products_by_category id=&quot;<?php echo esc_attr($id); ?>&quot;]" title="<?php echo esc_attr__('Copy to clipboard', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>">
                                                📋
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($shortcode['filter_type'] === 'category') {
                                            if ($shortcode['category_method'] === 'current') {
                                                echo esc_html__('Same Category', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN);
                                            } else {
                                                $category = get_term($shortcode['specific_category'], 'product_cat');
                                                echo esc_html__('Category: ', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN) . ($category ? esc_html($category->name) : esc_html__('Unknown', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
                                            }
                                        } else {
                                            echo esc_html__('Attribute: ', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN) . esc_html($shortcode['product_attribute']);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($shortcode['number_of_products']); ?></td>
                                    <td><?php echo esc_html($shortcode['number_of_columns']); ?></td>
                                    <td class="actions-cell">
                                        <button type="button" class="edit-shortcode-btn wc-btn-secondary" data-shortcode-id="<?php echo esc_attr($id); ?>">
                                            <?php echo esc_html__('Edit', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>
                                        </button>
                                        <button type="button" class="delete-shortcode-btn wc-btn-danger" data-shortcode-id="<?php echo esc_attr($id); ?>">
                                            <?php echo esc_html__('Delete', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Edit Shortcode Modal -->
        <div id="edit-shortcode-modal" class="wc-modal" style="display: none;">
            <div class="wc-modal-content">
                <div class="wc-modal-header">
                    <h3><?php echo esc_html__('Edit Shortcode', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></h3>
                    <button type="button" class="wc-modal-close">&times;</button>
                </div>
                <div class="wc-modal-body">
                    <form id="edit-shortcode-form">
                        <input type="hidden" id="edit-shortcode-id" name="shortcode_id" />
                        <div id="edit-form-fields"></div>
                    </form>
                </div>
                <div class="wc-modal-footer">
                    <button type="button" class="wc-advanced-related-products-save-btn" id="update-shortcode-btn">
                        <?php echo esc_html__('Update Shortcode', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="wc-btn-secondary wc-modal-close">
                        <?php echo esc_html__('Cancel', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        ?>
        <div class="wc-global-settings">
            <div class="wc-settings-header">
                <h3><?php echo esc_html__('Global Shortcode Settings', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></h3>
                <p><?php echo esc_html__('Configure default settings for the main shortcode [related_products_by_category] (without ID parameter).', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
            </div>
            
            <form method="post" action="options.php" class="wc-advanced-related-products-form">
                <?php settings_fields('wc_advanced_related_products_group'); ?>
                
                <div class="wc-settings-grid">
                    <div class="wc-settings-column">
                        <div class="wc-settings-section">
                            <h4><?php echo esc_html__('Display Settings', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></h4>
                            
                            <div class="wc-settings-row-pair">
                                <div class="wc-advanced-related-products-setting-row">
                                    <div class="setting-label">
                                        <label><?php echo esc_html__('Section Title', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                        <p class="setting-description"><?php echo esc_html__('The heading text for the related products section', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="setting-field">
                                        <?php $this->render_title_field(); ?>
                                    </div>
                                </div>

                                <div class="wc-advanced-related-products-setting-row">
                                    <div class="setting-label">
                                        <label><?php echo esc_html__('Title Alignment', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                        <p class="setting-description"><?php echo esc_html__('Alignment of the section title', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="setting-field">
                                        <?php $this->render_alignment_field(); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="wc-settings-row-pair">
                                <div class="wc-advanced-related-products-setting-row">
                                    <div class="setting-label">
                                        <label><?php echo esc_html__('Number of Products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                        <p class="setting-description"><?php echo esc_html__('Maximum number of related products to display (1-12)', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="setting-field">
                                        <?php $this->render_number_field(); ?>
                                    </div>
                                </div>

                                <div class="wc-advanced-related-products-setting-row">
                                    <div class="setting-label">
                                        <label><?php echo esc_html__('Columns per Row', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                        <p class="setting-description"><?php echo esc_html__('Number of product columns to display per row', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="setting-field">
                                        <?php $this->render_columns_field(); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="wc-shortcode-usage">
                                <h5><?php echo esc_html__('Default Shortcode Usage', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></h5>
                                <p><?php echo esc_html__('Use this shortcode with the global settings above:', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                <div class="wc-shortcode-code">
                                    <code>[related_products_by_category]</code>
                                </div>
                                <p><?php echo esc_html__('You can also override settings with attributes:', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                <div class="wc-shortcode-code">
                                    <code>[related_products_by_category limit="6" columns="3" orderby="popularity" title="You May Also Like"]</code>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="wc-settings-column">
                        <div class="wc-settings-section">
                            <h4><?php echo esc_html__('Filter & Sort Settings', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></h4>
                            
                            <div class="wc-advanced-related-products-setting-row">
                                <div class="setting-label">
                                    <label><?php echo esc_html__('Filter by Category', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                    <p class="setting-description"><?php echo esc_html__('Show products from the same category as the current product', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                </div>
                                <div class="setting-field">
                                    <?php $this->render_toggle_field('show_by_category'); ?>
                                </div>
                            </div>

                            <div class="wc-advanced-related-products-setting-row">
                                <div class="setting-label">
                                    <label><?php echo esc_html__('Sort Products By', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                    <p class="setting-description"><?php echo esc_html__('How to sort the related products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                </div>
                                <div class="setting-field">
                                    <?php $this->render_sort_field(); ?>
                                </div>
                            </div>

                            <div class="wc-advanced-related-products-setting-row">
                                <div class="setting-label">
                                    <label><?php echo esc_html__('Show Product Price', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></label>
                                    <p class="setting-description"><?php echo esc_html__('Show or hide product prices in the related products display', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?></p>
                                </div>
                                <div class="setting-field">
                                    <?php $this->render_toggle_field('show_price'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="wc-settings-footer">
                    <button type="submit" class="large-button">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M17,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H21A2,2 0 0,0 23,19V7L17,3M19,19H5V5H16V9H19V19Z"/>
                        </svg>
                        <?php echo esc_html__('Save Settings', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Get categories options HTML
     */
    private function get_categories_options() {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));
        
        $options = '';
        if (!is_wp_error($categories)) {
            foreach ($categories as $category) {
                $options .= sprintf(
                    '<option value="%s">%s</option>',
                    esc_attr($category->term_id),
                    esc_html($category->name)
                );
            }
        }
        
        return $options;
    }
    
    /**
     * Get attributes options HTML
     */
    private function get_attributes_options() {
        $attributes = wc_get_attribute_taxonomies();
        
        $options = '';
        foreach ($attributes as $attribute) {
            $options .= sprintf(
                '<option value="%s">%s</option>',
                esc_attr($attribute->attribute_name),
                esc_html($attribute->attribute_label)
            );
        }
        
        return $options;
    }
    
    /**
     * AJAX: Generate shortcode
     */
    public function ajax_generate_shortcode() {
        // Set proper headers
        header('Content-Type: application/json');
        
        // Check nonce
        if (!check_ajax_referer('wc_advanced_related_products_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions to access this page.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }
        
        // Sanitize and validate data
        $data = array(
            'title' => isset($_POST['shortcode_title']) ? sanitize_text_field($_POST['shortcode_title']) : '',
            'filter_type' => isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : 'category',
            'category_method' => isset($_POST['category_method']) ? sanitize_text_field($_POST['category_method']) : 'current',
            'specific_category' => isset($_POST['specific_category']) ? absint($_POST['specific_category']) : 0,
            'product_attribute' => isset($_POST['product_attribute']) ? sanitize_text_field($_POST['product_attribute']) : '',
            'number_of_products' => isset($_POST['number_of_products']) ? absint($_POST['number_of_products']) : 4,
            'number_of_columns' => isset($_POST['number_of_columns']) ? absint($_POST['number_of_columns']) : 4,
            'sort_by' => isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'date',
            'section_title' => isset($_POST['section_title']) ? sanitize_text_field($_POST['section_title']) : 'Related Products',
            'title_alignment' => isset($_POST['title_alignment']) ? sanitize_text_field($_POST['title_alignment']) : 'left',
            'show_price' => isset($_POST['show_price']) ? 1 : 0,
            'display_mode' => isset($_POST['display_mode']) && $_POST['display_mode'] === 'slider' ? 'slider' : 'grid',
            'slider_loop' => isset($_POST['slider_loop']) ? 1 : 0,
            'slider_autoplay' => isset($_POST['slider_autoplay']) ? 1 : 0,
            'slider_interval' => isset($_POST['slider_interval']) ? min(30000, max(1000, absint($_POST['slider_interval']))) : 6000,
            'slider_arrows' => isset($_POST['slider_arrows']) ? 1 : 0,
            'created' => current_time('mysql')
        );

        // Validate required fields
        if (empty($data['title'])) {
            wp_send_json_error(__('Shortcode title is required.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }

        // Validate number ranges
        if ($data['number_of_products'] < 1 || $data['number_of_products'] > 12) {
            $data['number_of_products'] = 4;
        }

        if (!in_array($data['number_of_columns'], array(2, 3, 4, 6))) {
            $data['number_of_columns'] = 4;
        }

        try {
            // Get existing shortcodes
            $shortcodes = get_option('wc_advanced_related_products_shortcodes', array());

            // Generate unique ID
            $id = uniqid('wc_arp_');
            
            // Save shortcode
            $shortcodes[$id] = $data;
            $result = update_option('wc_advanced_related_products_shortcodes', $shortcodes);
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'id' => $id,
                    'shortcode' => '[related_products_by_category id="' . $id . '"]',
                    'message' => __('Shortcode generated successfully!', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN)
                ));
            } else {
                wp_send_json_error(__('Failed to save shortcode. Please try again.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('An error occurred while generating the shortcode: ', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN) . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Delete shortcode
     */
    public function ajax_delete_shortcode() {
        header('Content-Type: application/json');
        
        if (!check_ajax_referer('wc_advanced_related_products_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions to access this page.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }
        
        $id = isset($_POST['shortcode_id']) ? sanitize_text_field($_POST['shortcode_id']) : '';
        
        if (empty($id)) {
            wp_send_json_error(__('Invalid shortcode ID.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }
        
        try {
            $shortcodes = get_option('wc_advanced_related_products_shortcodes', array());
            
            if (isset($shortcodes[$id])) {
                unset($shortcodes[$id]);
                $result = update_option('wc_advanced_related_products_shortcodes', $shortcodes);
                
                if ($result !== false) {
                    wp_send_json_success(__('Shortcode deleted successfully!', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
                } else {
                    wp_send_json_error(__('Failed to delete shortcode.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
                }
            } else {
                wp_send_json_error(__('Shortcode not found.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('An error occurred: ', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN) . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Get shortcode data for editing
     */
    public function ajax_get_shortcode_data() {
        header('Content-Type: application/json');
        
        if (!check_ajax_referer('wc_advanced_related_products_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions to access this page.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }
        
        $id = isset($_POST['shortcode_id']) ? sanitize_text_field($_POST['shortcode_id']) : '';
        
        if (empty($id)) {
            wp_send_json_error(__('Invalid shortcode ID.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }
        
        try {
            $shortcodes = get_option('wc_advanced_related_products_shortcodes', array());
            
            if (isset($shortcodes[$id])) {
                wp_send_json_success($shortcodes[$id]);
            } else {
                wp_send_json_error(__('Shortcode not found.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('An error occurred: ', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN) . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Update shortcode
     */
    public function ajax_update_shortcode() {
        header('Content-Type: application/json');
        
        if (!check_ajax_referer('wc_advanced_related_products_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions to access this page.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }
        
        $id = isset($_POST['shortcode_id']) ? sanitize_text_field($_POST['shortcode_id']) : '';
        
        if (empty($id)) {
            wp_send_json_error(__('Invalid shortcode ID.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }
        
        $data = array(
            'title' => isset($_POST['shortcode_title']) ? sanitize_text_field($_POST['shortcode_title']) : '',
            'filter_type' => isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : 'category',
            'category_method' => isset($_POST['category_method']) ? sanitize_text_field($_POST['category_method']) : 'current',
            'specific_category' => isset($_POST['specific_category']) ? absint($_POST['specific_category']) : 0,
            'product_attribute' => isset($_POST['product_attribute']) ? sanitize_text_field($_POST['product_attribute']) : '',
            'number_of_products' => isset($_POST['number_of_products']) ? absint($_POST['number_of_products']) : 4,
            'number_of_columns' => isset($_POST['number_of_columns']) ? absint($_POST['number_of_columns']) : 4,
            'sort_by' => isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'date',
            'section_title' => isset($_POST['section_title']) ? sanitize_text_field($_POST['section_title']) : 'Related Products',
            'title_alignment' => isset($_POST['title_alignment']) ? sanitize_text_field($_POST['title_alignment']) : 'left',
            'show_price' => isset($_POST['show_price']) ? 1 : 0,
            'display_mode' => isset($_POST['display_mode']) && $_POST['display_mode'] === 'slider' ? 'slider' : 'grid',
            'slider_loop' => isset($_POST['slider_loop']) ? 1 : 0,
            'slider_autoplay' => isset($_POST['slider_autoplay']) ? 1 : 0,
            'slider_interval' => isset($_POST['slider_interval']) ? min(30000, max(1000, absint($_POST['slider_interval']))) : 6000,
            'slider_arrows' => isset($_POST['slider_arrows']) ? 1 : 0,
            'updated' => current_time('mysql')
        );

        // Validate required fields
        if (empty($data['title'])) {
            wp_send_json_error(__('Shortcode title is required.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
        }

        // Validate number ranges
        if ($data['number_of_products'] < 1 || $data['number_of_products'] > 12) {
            $data['number_of_products'] = 4;
        }

        if (!in_array($data['number_of_columns'], array(2, 3, 4, 6))) {
            $data['number_of_columns'] = 4;
        }

        try {
            $shortcodes = get_option('wc_advanced_related_products_shortcodes', array());

            if (isset($shortcodes[$id])) {
                // Preserve creation date
                if (isset($shortcodes[$id]['created'])) {
                    $data['created'] = $shortcodes[$id]['created'];
                }
                
                $shortcodes[$id] = $data;
                $result = update_option('wc_advanced_related_products_shortcodes', $shortcodes);
                
                if ($result !== false) {
                    wp_send_json_success(__('Shortcode updated successfully!', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
                } else {
                    wp_send_json_error(__('Failed to update shortcode.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
                }
            } else {
                wp_send_json_error(__('Shortcode not found.', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('An error occurred: ', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN) . $e->getMessage());
        }
    }
    
    /**
     * Render number field
     */
    private function render_number_field() {
        $value = isset($this->settings['number_of_products']) ? $this->settings['number_of_products'] : 4;
        printf(
            '<input type="number" name="wc_advanced_related_products_settings[number_of_products]" value="%s" min="1" max="12" class="wc-input-field" />',
            esc_attr($value)
        );
    }
    
    /**
     * Render columns field
     */
    private function render_columns_field() {
        $value = isset($this->settings['number_of_columns']) ? $this->settings['number_of_columns'] : 4;
        $options = array(
            2 => __('2 Columns', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            3 => __('3 Columns', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            4 => __('4 Columns', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            6 => __('6 Columns', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN)
        );
        
        printf('<select name="wc_advanced_related_products_settings[number_of_columns]" class="wc-select-field">');
        foreach ($options as $key => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($key),
                selected($value, $key, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }
    
    /**
     * Render toggle field
     */
    private function render_toggle_field($field_name) {
        $value = isset($this->settings[$field_name]) ? $this->settings[$field_name] : 1;
        printf(
            '<label class="wc-toggle-switch">
                <input type="checkbox" name="wc_advanced_related_products_settings[%s]" value="1" %s />
                <span class="wc-toggle-slider"></span>
            </label>',
            esc_attr($field_name),
            checked($value, 1, false)
        );
    }
    
    /**
     * Render sort field
     */
    private function render_sort_field() {
        $value = isset($this->settings['sort_by']) ? $this->settings['sort_by'] : 'date';
        $options = array(
            'date' => __('Date (Newest First)', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            'popularity' => __('Popularity', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            'price_low' => __('Price (Low to High)', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            'price_high' => __('Price (High to Low)', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            'rating' => __('Customer Rating', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            'random' => __('Random', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN)
        );
        
        printf('<select name="wc_advanced_related_products_settings[sort_by]" class="wc-select-field">');
        foreach ($options as $key => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($key),
                selected($value, $key, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }
    
    /**
     * Render title field
     */
    private function render_title_field() {
        $value = isset($this->settings['related_products_title']) ? $this->settings['related_products_title'] : __('Related Products', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN);
        printf(
            '<input type="text" name="wc_advanced_related_products_settings[related_products_title]" value="%s" class="wc-input-field" />',
            esc_attr($value)
        );
    }
    
    /**
     * Render alignment field
     */
    private function render_alignment_field() {
        $value = isset($this->settings['title_alignment']) ? $this->settings['title_alignment'] : 'left';
        $options = array(
            'left' => __('Left', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            'center' => __('Center', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN),
            'right' => __('Right', WC_ADVANCED_RELATED_PRODUCTS_TEXT_DOMAIN)
        );
        
        printf('<select name="wc_advanced_related_products_settings[title_alignment]" class="wc-select-field">');
        foreach ($options as $key => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($key),
                selected($value, $key, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['number_of_products'] = absint($input['number_of_products']);
        if ($sanitized['number_of_products'] < 1 || $sanitized['number_of_products'] > 12) {
            $sanitized['number_of_products'] = 4;
        }
        
        $sanitized['number_of_columns'] = absint($input['number_of_columns']);
        if (!in_array($sanitized['number_of_columns'], array(2, 3, 4, 6))) {
            $sanitized['number_of_columns'] = 4;
        }
        
        $sanitized['show_by_category'] = isset($input['show_by_category']) ? 1 : 0;
        
        $allowed_sort_options = array('date', 'popularity', 'price_low', 'price_high', 'rating', 'random');
        $sanitized['sort_by'] = in_array($input['sort_by'], $allowed_sort_options) ? $input['sort_by'] : 'date';
        
        $sanitized['related_products_title'] = sanitize_text_field($input['related_products_title']);
        
        $allowed_alignments = array('left', 'center', 'right');
        $sanitized['title_alignment'] = in_array($input['title_alignment'], $allowed_alignments) ? $input['title_alignment'] : 'left';
        
        $sanitized['show_price'] = isset($input['show_price']) ? 1 : 0;
        
        return $sanitized;
    }
    
    /**
     * Sanitize shortcodes
     */
    public function sanitize_shortcodes($input) {
        if (!is_array($input)) {
            return array();
        }

        $sanitized = array();
        $allowed_sort_options = array('date', 'popularity', 'price_low', 'price_high', 'rating', 'random');
        $allowed_alignments = array('left', 'center', 'right');
        $allowed_filter_types = array('category', 'attribute');
        $allowed_category_methods = array('current', 'specific');
        $allowed_display_modes = array('grid', 'slider');

        foreach ($input as $id => $shortcode) {
            $sanitized_id = sanitize_key($id);
            $sanitized[$sanitized_id] = array(
                'title'              => sanitize_text_field($shortcode['title'] ?? ''),
                'filter_type'        => in_array($shortcode['filter_type'] ?? '', $allowed_filter_types) ? $shortcode['filter_type'] : 'category',
                'category_method'    => in_array($shortcode['category_method'] ?? '', $allowed_category_methods) ? $shortcode['category_method'] : 'current',
                'specific_category'  => absint($shortcode['specific_category'] ?? 0),
                'product_attribute'  => sanitize_text_field($shortcode['product_attribute'] ?? ''),
                'number_of_products' => min(12, max(1, absint($shortcode['number_of_products'] ?? 4))),
                'number_of_columns'  => in_array(absint($shortcode['number_of_columns'] ?? 4), array(2, 3, 4, 6)) ? absint($shortcode['number_of_columns']) : 4,
                'sort_by'            => in_array($shortcode['sort_by'] ?? '', $allowed_sort_options) ? $shortcode['sort_by'] : 'date',
                'section_title'      => sanitize_text_field($shortcode['section_title'] ?? ''),
                'title_alignment'    => in_array($shortcode['title_alignment'] ?? '', $allowed_alignments) ? $shortcode['title_alignment'] : 'left',
                'show_price'         => isset($shortcode['show_price']) ? absint($shortcode['show_price']) : 0,
                'display_mode'       => in_array($shortcode['display_mode'] ?? '', $allowed_display_modes) ? $shortcode['display_mode'] : 'grid',
                'slider_loop'        => isset($shortcode['slider_loop']) ? absint($shortcode['slider_loop']) : 0,
                'slider_autoplay'    => isset($shortcode['slider_autoplay']) ? absint($shortcode['slider_autoplay']) : 0,
                'slider_interval'    => min(30000, max(1000, absint($shortcode['slider_interval'] ?? 6000))),
                'slider_arrows'      => isset($shortcode['slider_arrows']) ? absint($shortcode['slider_arrows']) : 1,
                'created'            => sanitize_text_field($shortcode['created'] ?? ''),
            );
            if (isset($shortcode['updated'])) {
                $sanitized[$sanitized_id]['updated'] = sanitize_text_field($shortcode['updated']);
            }
        }

        return $sanitized;
    }
}