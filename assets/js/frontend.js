/**
 * WooCommerce Advanced Related Products - Slider Initialization
 *
 * Finds all slider instances on the page and initializes Splide
 * with configuration from data attributes.
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var sliders = document.querySelectorAll('[data-wc-arp-slider]');

        sliders.forEach(function(el) {
            var perPage  = parseInt(el.getAttribute('data-per-page')) || 4;
            var loop     = el.getAttribute('data-loop') === '1';
            var autoplay = el.getAttribute('data-autoplay') === '1';
            var interval = parseInt(el.getAttribute('data-interval')) || 6000;
            var arrows   = el.getAttribute('data-arrows') !== '0';

            new Splide(el, {
                type:       loop ? 'loop' : 'slide',
                perPage:    perPage,
                autoplay:   autoplay,
                interval:   interval,
                arrows:     arrows,
                pagination: false,
                gap:        '1em',
                breakpoints: {
                    768: { perPage: 2 },
                    480: { perPage: 2 }
                }
            }).mount();
        });
    });
})();
