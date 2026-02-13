<?php
/**
 * Shortcode functionality for WooCommerce Advanced Related Products
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WC_Advanced_Related_Products_Shortcode {
    
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
        
        // Register shortcode
        add_shortcode('related_products_by_category', array($this, 'shortcode_handler'));
        
        // Enqueue minimal frontend styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
    }
    
    /**
     * Enqueue minimal frontend styles and register slider assets
     */
    public function enqueue_frontend_styles() {
        // Always enqueue grid styles
        wp_enqueue_style(
            'wc-advanced-related-products-frontend',
            WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            WC_ADVANCED_RELATED_PRODUCTS_VERSION
        );

        // Register slider assets (only enqueued when a slider shortcode is rendered)
        wp_register_style(
            'splide-core',
            WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_URL . 'assets/css/splide-core.min.css',
            array(),
            '4.1.4'
        );

        wp_register_style(
            'wc-advanced-related-products-slider',
            WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_URL . 'assets/css/frontend-slider.css',
            array('splide-core'),
            WC_ADVANCED_RELATED_PRODUCTS_VERSION
        );

        wp_register_script(
            'splide',
            WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_URL . 'assets/js/splide.min.js',
            array(),
            '4.1.4',
            true
        );

        wp_register_script(
            'wc-advanced-related-products-slider',
            WC_ADVANCED_RELATED_PRODUCTS_PLUGIN_URL . 'assets/js/frontend.js',
            array('splide'),
            WC_ADVANCED_RELATED_PRODUCTS_VERSION,
            true
        );
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
            'show_add_to_cart' => 0,
            'container_class' => 'related-products-container'
        );
    }
    
    /**
     * Shortcode handler
     */
    public function shortcode_handler($atts) {
        if (!is_product()) {
            return '';
        }
        
        global $product;
        
        if (!$product) {
            return '';
        }
        
        // Check if this is a custom shortcode with ID
        if (isset($atts['id'])) {
            return $this->handle_custom_shortcode($atts, $product);
        }
        
        // Default shortcode behavior
        $atts = shortcode_atts(array(
            'limit' => $this->settings['number_of_products'],
            'columns' => $this->settings['number_of_columns'],
            'orderby' => $this->settings['sort_by'],
            'title' => $this->settings['related_products_title']
        ), $atts, 'related_products_by_category');
        
        // Get query args
        $query_args = $this->get_query_args($product, $atts);
        
        $related_products = new WP_Query($query_args);
        
        if (!$related_products->have_posts()) {
            return '';
        }
        
        ob_start();
        
        // Set WooCommerce loop properties for theme compatibility
        global $woocommerce_loop;
        $woocommerce_loop['is_shortcode'] = true;
        $woocommerce_loop['columns'] = $atts['columns'];
        $woocommerce_loop['name'] = 'related';
        
        // Also set modern loop properties
        wc_set_loop_prop('is_shortcode', true);
        wc_set_loop_prop('columns', $atts['columns']);
        wc_set_loop_prop('name', 'related');
        
        // Force theme to recognize this as a shop loop for proper styling
        wc_set_loop_prop('is_paginated', false);
        wc_set_loop_prop('total', $related_products->found_posts);
        wc_set_loop_prop('current_page', 1);
        
        ?>
        <section class="related products">
            <?php if (!empty($atts['title'])) : ?>
                <h2 style="text-align: <?php echo esc_attr($this->settings['title_alignment']); ?>">
                    <?php echo esc_html($atts['title']); ?>
                </h2>
            <?php endif; ?>
            
            <?php
            woocommerce_product_loop_start();
            
            while ($related_products->have_posts()) : 
                $related_products->the_post();
                
                // Remove hooks based on settings
                if (isset($this->settings['show_price']) && !$this->settings['show_price']) {
                    remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
                }
                
                if (isset($this->settings['show_add_to_cart']) && !$this->settings['show_add_to_cart']) {
                    remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
                }
                
                // Use WooCommerce template
                wc_get_template_part('content', 'product');
                
                // Re-add hooks for other instances
                if (isset($this->settings['show_price']) && !$this->settings['show_price']) {
                    add_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
                }
                
                if (isset($this->settings['show_add_to_cart']) && !$this->settings['show_add_to_cart']) {
                    add_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
                }
                
            endwhile;
            
            woocommerce_product_loop_end();
            ?>
            
        </section>
        <?php
        
        wp_reset_postdata();
        wc_reset_loop();
        
        return ob_get_clean();
    }
    
    /**
     * Handle custom shortcode with ID
     */
    private function handle_custom_shortcode($atts, $product) {
        $shortcode_id = sanitize_text_field($atts['id']);
        $shortcodes = get_option('wc_advanced_related_products_shortcodes', array());
        
        if (!isset($shortcodes[$shortcode_id])) {
            return '<!-- Advanced Related Products Shortcode: ID not found -->';
        }
        
        $config = $shortcodes[$shortcode_id];
        
        // Prepare attributes for this custom shortcode
        $custom_atts = array(
            'limit' => $config['number_of_products'],
            'columns' => $config['number_of_columns'],
            'orderby' => $config['sort_by'],
            'title' => $config['section_title']
        );
        
        // Get query args for custom shortcode
        $query_args = $this->get_custom_query_args($product, $custom_atts, $config);
        
        return $this->render_products($query_args, $custom_atts, $config);
    }
    
    /**
     * Render products output
     */
    private function render_products($query_args, $atts, $config) {
        $related_products = new WP_Query($query_args);

        if (!$related_products->have_posts()) {
            return '';
        }

        // Determine if slider mode is active
        $is_slider = isset($config['display_mode']) && $config['display_mode'] === 'slider';

        // Enqueue slider assets when needed
        if ($is_slider) {
            wp_enqueue_style('wc-advanced-related-products-slider');
            wp_enqueue_script('wc-advanced-related-products-slider');
        }

        ob_start();

        // Set WooCommerce loop properties for theme compatibility
        global $woocommerce_loop;
        $woocommerce_loop['is_shortcode'] = true;
        $woocommerce_loop['columns'] = $atts['columns'];
        $woocommerce_loop['name'] = 'related';

        // Also set modern loop properties
        wc_set_loop_prop('is_shortcode', true);
        wc_set_loop_prop('columns', $atts['columns']);
        wc_set_loop_prop('name', 'related');

        // Force theme to recognize this as a shop loop for proper styling
        wc_set_loop_prop('is_paginated', false);
        wc_set_loop_prop('total', $related_products->found_posts);
        wc_set_loop_prop('current_page', 1);

        // Add temporary WC filters for Splide classes
        if ($is_slider) {
            add_filter('woocommerce_product_loop_start', array($this, 'filter_loop_start_splide'));
            add_filter('wc_product_class', array($this, 'filter_product_class_splide'));
        }

        ?>
        <section class="related products">
            <?php if (!empty($atts['title'])) : ?>
                <h2 style="text-align: <?php echo esc_attr($config['title_alignment']); ?>">
                    <?php echo esc_html($atts['title']); ?>
                </h2>
            <?php endif; ?>

            <?php if ($is_slider) : ?>
            <div class="wc-arp-slider splide"
                 data-wc-arp-slider
                 data-per-page="<?php echo esc_attr($atts['columns']); ?>"
                 data-loop="<?php echo esc_attr(!empty($config['slider_loop']) ? '1' : '0'); ?>"
                 data-autoplay="<?php echo esc_attr(!empty($config['slider_autoplay']) ? '1' : '0'); ?>"
                 data-interval="<?php echo esc_attr(isset($config['slider_interval']) ? intval($config['slider_interval']) : 6000); ?>"
                 data-arrows="<?php echo esc_attr(isset($config['slider_arrows']) ? ($config['slider_arrows'] ? '1' : '0') : '1'); ?>">
                <div class="splide__track">
            <?php endif; ?>

            <?php
            woocommerce_product_loop_start();

            while ($related_products->have_posts()) :
                $related_products->the_post();

                // Remove hooks based on settings (use global settings for consistency)
                if (isset($this->settings['show_price']) && !$this->settings['show_price']) {
                    remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
                }

                if (isset($this->settings['show_add_to_cart']) && !$this->settings['show_add_to_cart']) {
                    remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
                }

                // Use WooCommerce template
                wc_get_template_part('content', 'product');

                // Re-add hooks for other instances
                if (isset($this->settings['show_price']) && !$this->settings['show_price']) {
                    add_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
                }

                if (isset($this->settings['show_add_to_cart']) && !$this->settings['show_add_to_cart']) {
                    add_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
                }

            endwhile;

            woocommerce_product_loop_end();
            ?>

            <?php if ($is_slider) : ?>
                </div>
            </div>
            <?php endif; ?>

        </section>
        <?php

        // Remove temporary Splide filters
        if ($is_slider) {
            remove_filter('woocommerce_product_loop_start', array($this, 'filter_loop_start_splide'));
            remove_filter('wc_product_class', array($this, 'filter_product_class_splide'));
        }

        wp_reset_postdata();
        wc_reset_loop();

        return ob_get_clean();
    }

    /**
     * Filter WooCommerce product loop start to add splide__list class
     */
    public function filter_loop_start_splide($html) {
        return str_replace('class="products', 'class="products splide__list', $html);
    }

    /**
     * Filter WooCommerce product class to add splide__slide class
     */
    public function filter_product_class_splide($classes) {
        $classes[] = 'splide__slide';
        return $classes;
    }
    
    /**
     * Get query arguments
     */
    private function get_query_args($product, $atts) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => intval($atts['limit']),
            'post__not_in' => array($product->get_id()),
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_stock_status',
                    'value' => 'instock',
                    'compare' => '='
                )
            )
        );
        
        // Category filtering
        if ($this->settings['show_by_category']) {
            $category_id = $this->get_primary_category_id($product);
            if ($category_id) {
                $args['tax_query'] = array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $category_id
                    )
                );
            }
        }
        
        // Sorting
        switch ($atts['orderby']) {
            case 'popularity':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'total_sales';
                $args['order'] = 'DESC';
                break;
            case 'price_low':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_price';
                $args['order'] = 'ASC';
                break;
            case 'price_high':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_price';
                $args['order'] = 'DESC';
                break;
            case 'rating':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_wc_average_rating';
                $args['order'] = 'DESC';
                break;
            case 'random':
                $args['orderby'] = 'rand';
                break;
            case 'date':
            default:
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
        }
        
        return $args;
    }
    
    /**
     * Get custom query arguments
     */
    private function get_custom_query_args($product, $atts, $config) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => intval($atts['limit']),
            'post__not_in' => array($product->get_id()),
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_stock_status',
                    'value' => 'instock',
                    'compare' => '='
                )
            )
        );
        
        // Apply custom filtering
        $this->apply_custom_filtering($args, $product, $config);
        
        // Sorting
        switch ($atts['orderby']) {
            case 'popularity':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'total_sales';
                $args['order'] = 'DESC';
                break;
            case 'price_low':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_price';
                $args['order'] = 'ASC';
                break;
            case 'price_high':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_price';
                $args['order'] = 'DESC';
                break;
            case 'rating':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_wc_average_rating';
                $args['order'] = 'DESC';
                break;
            case 'random':
                $args['orderby'] = 'rand';
                break;
            case 'date':
            default:
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
        }
        
        return $args;
    }
    
    /**
     * Apply custom filtering to query args
     */
    private function apply_custom_filtering(&$args, $product, $config) {
        if ($config['filter_type'] === 'category') {
            if ($config['category_method'] === 'current') {
                // Same category as current product
                $category_id = $this->get_primary_category_id($product);
                if ($category_id) {
                    $args['tax_query'] = array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field' => 'term_id',
                            'terms' => $category_id
                        )
                    );
                }
            } elseif ($config['category_method'] === 'specific' && !empty($config['specific_category'])) {
                // Specific category
                $args['tax_query'] = array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $config['specific_category']
                    )
                );
            }
        } elseif ($config['filter_type'] === 'attribute' && !empty($config['product_attribute'])) {
            // Filter by attribute
            $this->apply_attribute_filtering($args, $product, $config['product_attribute']);
        }
    }
    
    /**
     * Apply attribute filtering
     */
    private function apply_attribute_filtering(&$args, $product, $attribute_name) {
        $taxonomy = 'pa_' . $attribute_name;
        
        // Get current product's attribute values
        $current_terms = wp_get_post_terms($product->get_id(), $taxonomy, array('fields' => 'ids'));
        
        if (!empty($current_terms) && !is_wp_error($current_terms)) {
            if (!isset($args['tax_query'])) {
                $args['tax_query'] = array();
            }
            
            $args['tax_query'][] = array(
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $current_terms,
                'operator' => 'IN'
            );
        }
    }
    
    /**
     * Get primary category ID
     */
    private function get_primary_category_id($product) {
        $primary_category_id = false;
        
        // Try Yoast SEO primary category
        if (class_exists('WPSEO_Primary_Term')) {
            $primary_category_id = get_post_meta($product->get_id(), '_yoast_wpseo_primary_product_cat', true);
        }
        
        // Fallback to first category
        if (!$primary_category_id) {
            $categories = wp_get_post_terms($product->get_id(), 'product_cat');
            if (!empty($categories) && !is_wp_error($categories)) {
                $primary_category_id = $categories[0]->term_id;
            }
        }
        
        return $primary_category_id;
    }
}