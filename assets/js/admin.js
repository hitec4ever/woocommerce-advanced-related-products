/**
 * WooCommerce Advanced Related Products - Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize admin functionality
    WCAdvancedRelatedProductsAdmin.init();
});

var WCAdvancedRelatedProductsAdmin = {
    
    /**
     * Initialize admin functionality
     */
    init: function() {
        this.setupConditionalFields();
        this.bindEvents();
        this.setupFormSubmission();
    },
    
    /**
     * Setup conditional field visibility
     */
    setupConditionalFields: function() {
        // Filter type change handler
        jQuery(document).on('change', '#filter-type-select', function() {
            WCAdvancedRelatedProductsAdmin.toggleFilterOptions(jQuery(this).val());
        });
        
        // Category method change handler
        jQuery(document).on('change', 'input[name="category_method"]', function() {
            WCAdvancedRelatedProductsAdmin.toggleCategoryMethod(jQuery(this).val());
        });

        // Display mode change handler (generator form)
        jQuery(document).on('change', '#display-mode-select', function() {
            WCAdvancedRelatedProductsAdmin.toggleSliderOptions(jQuery(this).val(), '#slider-options');
        });

        // Initialize on page load
        var initialFilterType = jQuery('#filter-type-select').val();
        if (initialFilterType) {
            this.toggleFilterOptions(initialFilterType);
        }

        var initialCategoryMethod = jQuery('input[name="category_method"]:checked').val();
        if (initialCategoryMethod) {
            this.toggleCategoryMethod(initialCategoryMethod);
        }

        var initialDisplayMode = jQuery('#display-mode-select').val();
        if (initialDisplayMode) {
            this.toggleSliderOptions(initialDisplayMode, '#slider-options');
        }
    },
    
    /**
     * Toggle filter options (category vs attribute)
     */
    toggleFilterOptions: function(filterType) {
        if (filterType === 'category') {
            jQuery('#category-options').show();
            jQuery('#attribute-options').hide();
        } else if (filterType === 'attribute') {
            jQuery('#category-options').hide();
            jQuery('#attribute-options').show();
        }
    },
    
    /**
     * Toggle category method (current vs specific)
     */
    toggleCategoryMethod: function(method) {
        if (method === 'specific') {
            jQuery('#specific-category').show();
        } else {
            jQuery('#specific-category').hide();
        }
    },

    /**
     * Toggle slider options visibility
     */
    toggleSliderOptions: function(displayMode, containerSelector) {
        if (displayMode === 'slider') {
            jQuery(containerSelector).show();
        } else {
            jQuery(containerSelector).hide();
        }
    },
    
    /**
     * Bind other events
     */
    bindEvents: function() {
        var self = this;
        
        // Copy shortcode functionality
        jQuery(document).on('click', '.copy-shortcode-btn', function(e) {
            e.preventDefault();
            var shortcode = jQuery(this).data('shortcode');
            self.copyToClipboard(shortcode);
        });
        
        // Edit shortcode
        jQuery(document).on('click', '.edit-shortcode-btn', function(e) {
            e.preventDefault();
            var shortcodeId = jQuery(this).data('shortcode-id');
            self.editShortcode(shortcodeId);
        });
        
        // Delete shortcode
        jQuery(document).on('click', '.delete-shortcode-btn', function(e) {
            e.preventDefault();
            var shortcodeId = jQuery(this).data('shortcode-id');
            if (confirm(wcAdvancedRelatedProducts.strings.confirmDelete)) {
                self.deleteShortcode(shortcodeId);
            }
        });
        
        // Modal close
        jQuery(document).on('click', '.wc-modal-close', function() {
            jQuery('.wc-modal').hide();
        });
        
        // Update shortcode
        jQuery(document).on('click', '#update-shortcode-btn', function(e) {
            e.preventDefault();
            self.updateShortcode();
        });
    },
    
    /**
     * Setup form submission
     */
    setupFormSubmission: function() {
        var self = this;
        
        jQuery(document).on('submit', '#wc-shortcode-generator-form', function(e) {
            e.preventDefault();
            self.generateShortcode();
        });
    },
    
    /**
     * Generate new shortcode
     */
    generateShortcode: function() {
        var formData = this.getFormData('#wc-shortcode-generator-form');
        
        // Validate
        if (!formData.shortcode_title || formData.shortcode_title.trim() === '') {
            alert(wcAdvancedRelatedProducts.strings.titleRequired);
            return;
        }
        
        // Show loading
        var $btn = jQuery('#wc-shortcode-generator-form .wc-advanced-related-products-save-btn');
        var originalText = $btn.text();
        $btn.text('Generating...').prop('disabled', true);
        
        // AJAX call
        jQuery.ajax({
            url: wcAdvancedRelatedProducts.ajaxUrl,
            type: 'POST',
            data: jQuery.extend(formData, {
                action: 'wc_advanced_related_products_generate_shortcode',
                nonce: wcAdvancedRelatedProducts.nonce
            }),
            success: function(response) {
                if (response.success) {
                    alert(wcAdvancedRelatedProducts.strings.shortcodeGenerated);
                    // Reset form
                    document.getElementById('wc-shortcode-generator-form').reset();
                    // Reset conditional fields
                    WCAdvancedRelatedProductsAdmin.toggleFilterOptions('category');
                    WCAdvancedRelatedProductsAdmin.toggleCategoryMethod('current');
                    jQuery('input[name="category_method"][value="current"]').prop('checked', true);
                    
                    // Redirect to manager tab
                    setTimeout(function() {
                        window.location.href = '?page=wc-advanced-related-products-settings&tab=manager';
                    }, 1500);
                } else {
                    alert('Error: ' + (response.data || wcAdvancedRelatedProducts.strings.error));
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
            }
        });
    },
    
    /**
     * Edit shortcode
     */
    editShortcode: function(shortcodeId) {
        jQuery.ajax({
            url: wcAdvancedRelatedProducts.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wc_advanced_related_products_get_shortcode_data',
                shortcode_id: shortcodeId,
                nonce: wcAdvancedRelatedProducts.nonce
            },
            success: function(response) {
                if (response.success) {
                    WCAdvancedRelatedProductsAdmin.populateEditForm(shortcodeId, response.data);
                    jQuery('#edit-shortcode-modal').show();
                } else {
                    alert('Error: ' + (response.data || wcAdvancedRelatedProducts.strings.error));
                }
            },
            error: function() {
                alert(wcAdvancedRelatedProducts.strings.error);
            }
        });
    },
    
    /**
     * Populate edit form
     */
    populateEditForm: function(shortcodeId, data) {
        jQuery('#edit-shortcode-id').val(shortcodeId);
        
        var formHTML = this.generateEditFormHTML(data);
        jQuery('#edit-form-fields').html(formHTML);
        
        // Setup conditional fields for edit form
        this.setupEditFormConditionals();
    },
    
    /**
     * Setup edit form conditionals
     */
    setupEditFormConditionals: function() {
        // Filter type change
        jQuery(document).on('change', '#edit-filter-type-select', function() {
            var filterType = jQuery(this).val();
            if (filterType === 'category') {
                jQuery('#edit-category-options').show();
                jQuery('#edit-attribute-options').hide();
            } else {
                jQuery('#edit-category-options').hide();
                jQuery('#edit-attribute-options').show();
            }
        });
        
        // Category method change
        jQuery(document).on('change', '#edit-form-fields input[name="category_method"]', function() {
            var method = jQuery(this).val();
            if (method === 'specific') {
                jQuery('#edit-specific-category').show();
            } else {
                jQuery('#edit-specific-category').hide();
            }
        });

        // Display mode change (edit modal)
        jQuery(document).on('change', '#edit-display-mode-select', function() {
            WCAdvancedRelatedProductsAdmin.toggleSliderOptions(jQuery(this).val(), '#edit-slider-options');
        });
    },
    
    /**
     * Generate edit form HTML
     */
    generateEditFormHTML: function(data) {
        var categoriesOptions = this.getCategoriesOptionsHTML(data.specific_category);
        var attributesOptions = this.getAttributesOptionsHTML(data.product_attribute);
        
        return `
            <div class="wc-advanced-related-products-settings-grid">
                <div class="wc-advanced-related-products-setting-row">
                    <div class="setting-label">
                        <label>Shortcode Title *</label>
                    </div>
                    <div class="setting-field">
                        <input type="text" name="shortcode_title" value="${this.escapeHtml(data.title)}" class="wc-input-field" required />
                    </div>
                </div>

                <div class="wc-settings-row-pair">
                    <div class="wc-advanced-related-products-setting-row">
                        <div class="setting-label">
                            <label>Section Title</label>
                        </div>
                        <div class="setting-field">
                            <input type="text" name="section_title" value="${this.escapeHtml(data.section_title)}" class="wc-input-field" />
                        </div>
                    </div>
                    <div class="wc-advanced-related-products-setting-row">
                        <div class="setting-label">
                            <label>Title Alignment</label>
                        </div>
                        <div class="setting-field">
                            <select name="title_alignment" class="wc-select-field">
                                <option value="left" ${data.title_alignment === 'left' ? 'selected' : ''}>Left</option>
                                <option value="center" ${data.title_alignment === 'center' ? 'selected' : ''}>Center</option>
                                <option value="right" ${data.title_alignment === 'right' ? 'selected' : ''}>Right</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="wc-settings-row-pair">
                    <div class="wc-advanced-related-products-setting-row">
                        <div class="setting-label">
                            <label>Filter Type</label>
                        </div>
                        <div class="setting-field">
                            <select name="filter_type" class="wc-select-field" id="edit-filter-type-select">
                                <option value="category" ${data.filter_type === 'category' ? 'selected' : ''}>Filter by Category</option>
                                <option value="attribute" ${data.filter_type === 'attribute' ? 'selected' : ''}>Filter by Attribute</option>
                            </select>
                        </div>
                    </div>
                    <div class="wc-advanced-related-products-setting-row" id="edit-attribute-options" ${data.filter_type !== 'attribute' ? 'style="display:none;"' : ''}>
                        <div class="setting-label">
                            <label>Product Attribute</label>
                        </div>
                        <div class="setting-field">
                            <select name="product_attribute" class="wc-select-field">
                                <option value="">Select an attribute</option>
                                ${attributesOptions}
                            </select>
                        </div>
                    </div>
                </div>

                <div class="wc-advanced-related-products-setting-row" id="edit-category-options" ${data.filter_type !== 'category' ? 'style="display:none;"' : ''}>
                    <div class="setting-label">
                        <label>Category Selection</label>
                    </div>
                    <div class="setting-field">
                        <div class="category-selection">
                            <label class="radio-option">
                                <input type="radio" name="category_method" value="current" ${data.category_method === 'current' ? 'checked' : ''} />
                                Same category as current product
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="category_method" value="specific" ${data.category_method === 'specific' ? 'checked' : ''} />
                                Specific category
                            </label>
                            <div id="edit-specific-category" ${data.category_method !== 'specific' ? 'style="display:none;"' : ''}>
                                <select name="specific_category" class="wc-select-field">
                                    <option value="">Select a category</option>
                                    ${categoriesOptions}
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wc-settings-row-pair">
                    <div class="wc-advanced-related-products-setting-row">
                        <div class="setting-label">
                            <label>Number of Products</label>
                        </div>
                        <div class="setting-field">
                            <input type="number" name="number_of_products" value="${data.number_of_products}" min="1" max="12" class="wc-input-field" />
                        </div>
                    </div>
                    <div class="wc-advanced-related-products-setting-row">
                        <div class="setting-label">
                            <label>Columns per Row</label>
                        </div>
                        <div class="setting-field">
                            <select name="number_of_columns" class="wc-select-field">
                                <option value="2" ${data.number_of_columns == 2 ? 'selected' : ''}>2 Columns</option>
                                <option value="3" ${data.number_of_columns == 3 ? 'selected' : ''}>3 Columns</option>
                                <option value="4" ${data.number_of_columns == 4 ? 'selected' : ''}>4 Columns</option>
                                <option value="6" ${data.number_of_columns == 6 ? 'selected' : ''}>6 Columns</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="wc-settings-row-pair">
                    <div class="wc-advanced-related-products-setting-row">
                        <div class="setting-label">
                            <label>Sort Products By</label>
                        </div>
                        <div class="setting-field">
                            <select name="sort_by" class="wc-select-field">
                                <option value="date" ${data.sort_by === 'date' ? 'selected' : ''}>Date (Newest First)</option>
                                <option value="popularity" ${data.sort_by === 'popularity' ? 'selected' : ''}>Popularity</option>
                                <option value="price_low" ${data.sort_by === 'price_low' ? 'selected' : ''}>Price (Low to High)</option>
                                <option value="price_high" ${data.sort_by === 'price_high' ? 'selected' : ''}>Price (High to Low)</option>
                                <option value="rating" ${data.sort_by === 'rating' ? 'selected' : ''}>Customer Rating</option>
                                <option value="random" ${data.sort_by === 'random' ? 'selected' : ''}>Random</option>
                            </select>
                        </div>
                    </div>
                    <div class="wc-advanced-related-products-setting-row">
                        <div class="setting-label">
                            <label>Display Mode</label>
                        </div>
                        <div class="setting-field">
                            <select name="display_mode" class="wc-select-field" id="edit-display-mode-select">
                                <option value="grid" ${(!data.display_mode || data.display_mode === 'grid') ? 'selected' : ''}>Grid</option>
                                <option value="slider" ${data.display_mode === 'slider' ? 'selected' : ''}>Slider / Carousel</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="wc-advanced-related-products-setting-row">
                    <div class="setting-label">
                        <label>Show Product Price</label>
                    </div>
                    <div class="setting-field">
                        <label class="wc-toggle-switch">
                            <input type="checkbox" name="show_price" value="1" ${data.show_price ? 'checked' : ''} />
                            <span class="wc-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div id="edit-slider-options" ${(!data.display_mode || data.display_mode !== 'slider') ? 'style="display:none;"' : ''}>
                    <div class="wc-settings-row-pair">
                        <div class="wc-advanced-related-products-setting-row">
                            <div class="setting-label">
                                <label>Loop Slider</label>
                            </div>
                            <div class="setting-field">
                                <label class="wc-toggle-switch">
                                    <input type="checkbox" name="slider_loop" value="1" ${data.slider_loop ? 'checked' : ''} />
                                    <span class="wc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="wc-advanced-related-products-setting-row">
                            <div class="setting-label">
                                <label>Show Navigation</label>
                            </div>
                            <div class="setting-field">
                                <label class="wc-toggle-switch">
                                    <input type="checkbox" name="slider_arrows" value="1" ${(data.slider_arrows === undefined || data.slider_arrows) ? 'checked' : ''} />
                                    <span class="wc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="wc-settings-row-pair">
                        <div class="wc-advanced-related-products-setting-row">
                            <div class="setting-label">
                                <label>Auto Slide</label>
                            </div>
                            <div class="setting-field">
                                <label class="wc-toggle-switch">
                                    <input type="checkbox" name="slider_autoplay" value="1" ${data.slider_autoplay ? 'checked' : ''} />
                                    <span class="wc-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="wc-advanced-related-products-setting-row">
                            <div class="setting-label">
                                <label>Slide Interval (ms)</label>
                            </div>
                            <div class="setting-field">
                                <input type="number" name="slider_interval" value="${data.slider_interval || 6000}" min="1000" max="30000" step="500" class="wc-input-field" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },
    
    /**
     * Update shortcode
     */
    updateShortcode: function() {
        var formData = this.getFormData('#edit-shortcode-form');
        
        if (!formData.shortcode_title || formData.shortcode_title.trim() === '') {
            alert(wcAdvancedRelatedProducts.strings.titleRequired);
            return;
        }
        
        var $btn = jQuery('#update-shortcode-btn');
        var originalText = $btn.text();
        $btn.text('Updating...').prop('disabled', true);
        
        jQuery.ajax({
            url: wcAdvancedRelatedProducts.ajaxUrl,
            type: 'POST',
            data: jQuery.extend(formData, {
                action: 'wc_advanced_related_products_update_shortcode',
                nonce: wcAdvancedRelatedProducts.nonce
            }),
            success: function(response) {
                if (response.success) {
                    alert(wcAdvancedRelatedProducts.strings.shortcodeUpdated);
                    jQuery('#edit-shortcode-modal').hide();
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('Error: ' + (response.data || wcAdvancedRelatedProducts.strings.error));
                }
            },
            error: function() {
                alert(wcAdvancedRelatedProducts.strings.error);
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
            }
        });
    },
    
    /**
     * Delete shortcode
     */
    deleteShortcode: function(shortcodeId) {
        jQuery.ajax({
            url: wcAdvancedRelatedProducts.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wc_advanced_related_products_delete_shortcode',
                shortcode_id: shortcodeId,
                nonce: wcAdvancedRelatedProducts.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(wcAdvancedRelatedProducts.strings.shortcodeDeleted);
                    jQuery('tr[data-shortcode-id="' + shortcodeId + '"]').remove();
                    
                    if (jQuery('.wc-shortcodes-table tbody tr').length === 0) {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + (response.data || wcAdvancedRelatedProducts.strings.error));
                }
            },
            error: function() {
                alert(wcAdvancedRelatedProducts.strings.error);
            }
        });
    },
    
    /**
     * Get form data
     */
    getFormData: function(formSelector) {
        var formData = {};
        jQuery(formSelector).find('input, select, textarea').each(function() {
            var $field = jQuery(this);
            var name = $field.attr('name');
            var value = $field.val();
            
            if (!name) return;
            
            if ($field.attr('type') === 'checkbox') {
                if ($field.is(':checked')) {
                    formData[name] = value;
                }
            } else if ($field.attr('type') === 'radio') {
                if ($field.is(':checked')) {
                    formData[name] = value;
                }
            } else {
                formData[name] = value;
            }
        });
        
        return formData;
    },
    
    /**
     * Get categories options HTML
     */
    getCategoriesOptionsHTML: function(selectedValue) {
        var self = this;
        var options = '';
        if (typeof wcAdvancedRelatedProducts.categories !== 'undefined') {
            wcAdvancedRelatedProducts.categories.forEach(function(category) {
                var selected = selectedValue == category.id ? 'selected' : '';
                options += '<option value="' + category.id + '" ' + selected + '>' + self.escapeHtml(category.name) + '</option>';
            });
        }
        return options;
    },

    /**
     * Get attributes options HTML
     */
    getAttributesOptionsHTML: function(selectedValue) {
        var self = this;
        var options = '';
        if (typeof wcAdvancedRelatedProducts.attributes !== 'undefined') {
            wcAdvancedRelatedProducts.attributes.forEach(function(attribute) {
                var selected = selectedValue === attribute.name ? 'selected' : '';
                options += '<option value="' + self.escapeHtml(attribute.name) + '" ' + selected + '>' + self.escapeHtml(attribute.label) + '</option>';
            });
        }
        return options;
    },
    
    /**
     * Escape HTML
     */
    escapeHtml: function(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    },
    
    /**
     * Copy to clipboard
     */
    copyToClipboard: function(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                alert(wcAdvancedRelatedProducts.strings.shortcodeCopied);
            }, function() {
                // Fallback for older browsers
                WCAdvancedRelatedProductsAdmin.fallbackCopyToClipboard(text);
            });
        } else {
            // Fallback for older browsers
            this.fallbackCopyToClipboard(text);
        }
    },
    
    /**
     * Fallback copy to clipboard for older browsers
     */
    fallbackCopyToClipboard: function(text) {
        var $temp = jQuery('<input>');
        jQuery('body').append($temp);
        $temp.val(text).select();
        
        try {
            document.execCommand('copy');
            alert(wcAdvancedRelatedProducts.strings.shortcodeCopied);
        } catch (err) {
            alert('Failed to copy shortcode');
        }
        
        $temp.remove();
    }
};