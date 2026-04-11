=== WooCommerce Advanced Related Products ===
Contributors: jaapdewit
Tags: woocommerce, related products, category, shortcode, products
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.3.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display WooCommerce related products by category with customizable shortcode and advanced settings.

== Description ==

WooCommerce Advanced Related Products is a powerful plugin that allows you to display related products based on categories with full customization control. Perfect for increasing cross-sells and improving user experience on your WooCommerce store.

**Key Features:**

* **Advanced shortcode generator** - Create multiple custom shortcodes with different settings
* **Shortcode manager** - Edit, delete and manage all your shortcodes from one place
* **Category-based filtering** - Show products from the same category as the current product
* **Attribute-based filtering** - Show products with matching attributes
* **Flexible display options** - Choose from 2, 3, 4, or 6 columns layout
* **Multiple sorting options** - Sort by date, popularity, price, rating, or random
* **Customizable appearance** - Control title, alignment, and product information display
* **Responsive design** - Works perfectly on all devices and screen sizes
* **Theme compatibility** - Works with any WordPress theme
* **WPML & Polylang ready** - Full multilingual support
* **Easy shortcode** - Simple [related_products_by_category] shortcode
* **Beautiful admin interface** - Modern, intuitive design with tabs and styling

**Sorting Options:**
* Date (Newest First)
* Popularity (Best Sellers)
* Price (Low to High)
* Price (High to Low)
* Customer Rating
* Random

**Display Options:**
* Number of products to show (1-12)
* Column layout (2, 3, 4, or 6 columns)
* Show/hide product prices
* Show/hide add to cart buttons
* Custom section title
* Title alignment (left, center, right)

**Multilingual Support:**
* WPML compatible
* Polylang compatible
* Polylang Pro compatible
* Polylang for WooCommerce compatible

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/woocommerce-advanced-related-products` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to WooCommerce > Advanced Related Products to configure the settings
4. Use the shortcode `[related_products_by_category]` anywhere you want to display related products

== Frequently Asked Questions ==

= Does this plugin work with any theme? =

Yes! This plugin is designed to work with any WordPress theme. It uses WooCommerce's standard product templates and adds responsive styling.

= Can I customize the appearance? =

Absolutely! The plugin provides extensive customization options through the settings panel, including layout, sorting, and display options.

= Is it compatible with WPML and Polylang? =

Yes, the plugin is fully compatible with WPML, Polylang, Polylang Pro, and Polylang for WooCommerce.

= Can I use multiple shortcodes on the same page? =

Yes, you can use the shortcode multiple times on the same page with different settings by creating custom shortcodes with unique IDs.

= Does it work with variable products? =

Yes, the plugin works with all WooCommerce product types including simple, variable, grouped, and external products.

= How does the category filtering work? =

The plugin first tries to use the primary category set by Yoast SEO (if available), otherwise it uses the first category assigned to the product.

== Screenshots ==

1. Beautiful admin settings panel with modern design and tabs
2. Shortcode generator with advanced filtering options
3. Manage shortcodes interface with edit and delete options
4. Related products display with 4-column layout
5. Related products display with 2-column layout
6. Mobile responsive design
7. Global settings page with all customization options

== Changelog ==

= 2.3.3 =
* Performance: Added `no_found_rows` to related-products query to skip costly SQL_CALC_FOUND_ROWS subquery
* Performance: Added 1-hour transient caching for related products on product pages
* Cache automatically flushes when products are saved, updated, or deleted

= 2.3.0 =
* Rebranded plugin to Jaap de Wit (jaapdewit.com)
* Restyled admin interface to Flatsome Add-ons design system
* New color scheme with red/orange accent color
* White header layout with title, tabs, and logo
* Updated cards, toggles, buttons, and typography
* Added GitHub update checker for automatic plugin updates

= 2.2.0 =
* Added slider/carousel display mode for related products
* Loop slider, navigation arrows, and auto-slide options
* Integrated Splide.js for lightweight carousel functionality

= 2.1.1 =
* Minor bug fixes and improvements

= 2.1.0 =
* Updated plugin name to "WooCommerce Advanced Related Products"
* Improved tab navigation styling and layout
* Fixed category selection display (now properly stacked vertically)
* Fixed Generate shortcode button styling and white text color
* Fixed copy to clipboard functionality for shortcodes
* Fixed edit and delete buttons functionality in manage shortcodes
* Improved manage shortcodes tab styling and layout
* Fixed global settings tab layout and styling issues
* Corrected margins and padding throughout the interface
* Enhanced responsive design for all screen sizes
* Updated text domain and all references
* Improved JavaScript functionality and error handling
* Better form validation and user feedback

= 2.0.0 =
* Complete rewrite as single PHP class
* Improved admin interface with modern styling
* Added more sorting options (price, rating, random)
* Added option to show/hide prices and add to cart buttons
* Added 3-column layout option
* Enhanced responsive design with mobile-first approach
* Theme-agnostic design - works with any WordPress theme
* Settings link added to plugins page for easy access

IMPROVEMENTS:
* Removed all Flatsome theme dependencies
* Better WPML compatibility with string registration
* Enhanced Polylang compatibility (Pro and WooCommerce versions)
* Improved security with proper input sanitization and validation
* Better error handling and fallback mechanisms
* Performance optimizations and code efficiency
* Cleaner HTML output with proper CSS classes
* More flexible shortcode with customizable attributes
* Better product visibility filtering
* Enhanced primary category detection with Yoast SEO integration

TECHNICAL CHANGES:
* Singleton pattern implementation for better performance
* Proper text domain and internationalization support
* WordPress coding standards compliance
* Improved database queries with better caching
* Better hook usage and WordPress integration
* Enhanced plugin activation/deactivation handling
* Proper enqueue of admin scripts and styles
* Better option handling with default values

BUG FIXES:
* Fixed column layout issues on different screen sizes
* Fixed category filtering edge cases
* Fixed shortcode output buffering issues
* Fixed admin interface styling conflicts
* Fixed translation string issues
* Fixed product query optimization

SECURITY:
* Enhanced input sanitization for all settings
* Proper nonce verification for admin forms
* Escaped output for better XSS protection
* Validated user capabilities for admin access

= 1.1 =
* Added custom title option for related products section
* Added title alignment settings (left, center, right)
* Improved admin interface layout

IMPROVEMENTS:
* Better settings organization
* Enhanced user experience in admin panel
* Code optimization and cleanup

BUG FIXES:
* Fixed minor styling issues
* Improved compatibility with some themes

= 1.0 =
* Initial release
* Basic related products by category functionality
* Shortcode support [related_products_by_category]
* Admin settings page
* Integration with Flatsome theme
* Configurable number of products and columns
* Category-based product filtering
* Date-based sorting
* Basic responsive design

== Upgrade Notice ==

= 2.3.0 =
Rebranded to Jaap de Wit with new Flatsome-style admin design and automatic GitHub updates.

= 2.2.0 =
Added slider/carousel display mode for related products.

= 2.1.0 =
Major interface improvements with better styling, fixed functionality, and enhanced user experience. All buttons and features now work properly.

= 2.0.0 =
Major update with improved design, new features, and better compatibility. Backup your site before updating.