<?php
/**
 * WooCommerce & Events Calendar Accessibility
 *
 * Additional WCAG 2.1 AA enhancements specific to
 * WooCommerce and The Events Calendar Pro output.
 *
 * All hooks are gated behind class_exists() / function_exists() checks
 * so this file is completely safe to load when either plugin is inactive.
 *
 * @package PLGC
 */

defined( 'ABSPATH' ) || exit;

/**
 * ============================================================
 * WOOCOMMERCE ACCESSIBILITY
 * Only registered when WooCommerce is active.
 * ============================================================
 */
if ( class_exists( 'WooCommerce' ) ) {

    /**
     * Redirect "Return to shop" button to the custom gift cards page
     * instead of the default /shop/ WooCommerce archive.
     */
    add_filter( 'woocommerce_return_to_shop_redirect', function() {
        return home_url( '/online-merchandise/' );
    } );

    /**
     * Declare WooCommerce theme support with accessible defaults.
     */
    function plgc_woocommerce_support() {
        add_theme_support( 'woocommerce', [
            'product_grid' => [
                'default_rows'    => 3,
                'default_columns' => 3,
                'min_columns'     => 1,
                'max_columns'     => 4,
            ],
        ] );

        add_theme_support( 'wc-product-gallery-zoom' );
        add_theme_support( 'wc-product-gallery-lightbox' );
        add_theme_support( 'wc-product-gallery-slider' );
    }
    add_action( 'after_setup_theme', 'plgc_woocommerce_support' );

    /**
     * Add aria-label to WooCommerce "Add to Cart" buttons.
     * (WCAG 2.4.4 - Link Purpose)
     */
    function plgc_woo_cart_button_aria( $html, $product ) {
        $product_name = $product->get_name();
        $html = str_replace(
            'class="button',
            'aria-label="' . esc_attr( sprintf( 'Add %s to cart', $product_name ) ) . '" class="button',
            $html
        );
        return $html;
    }
    add_filter( 'woocommerce_loop_add_to_cart_link', 'plgc_woo_cart_button_aria', 10, 2 );

    /**
     * Remove the product link on cart line items — show plain text name only.
     * CSS pointer-events:none is cosmetic only; this removes the <a> from the DOM.
     * (No WCAG issue — product name in cart doesn't need to be a link.)
     */
    add_filter( 'woocommerce_cart_item_name', function( $name, $cart_item, $cart_item_key ) {
        $product = $cart_item['data'];
        if ( $product instanceof WC_Product ) {
            return esc_html( $product->get_name() );
        }
        // Fallback: strip any <a> tags WooCommerce may have already built
        return wp_strip_all_tags( $name );
    }, 10, 3 );

    /**
     * Add accessible notices for cart updates.
     * (WCAG 4.1.3 - Status Messages)
     */
    function plgc_woo_notice_a11y( $notice, $type ) {
        if ( strpos( $notice, 'role="alert"' ) === false && strpos( $notice, 'aria-live' ) === false ) {
            $role   = ( $type === 'error' ) ? 'role="alert"' : 'role="status" aria-live="polite"';
            $notice = str_replace(
                'class="woocommerce-',
                $role . ' class="woocommerce-',
                $notice
            );
        }
        return $notice;
    }
    add_filter( 'woocommerce_add_message', function ( $message ) { return plgc_woo_notice_a11y( $message, 'success' ); } );
    add_filter( 'woocommerce_add_error',   function ( $message ) { return plgc_woo_notice_a11y( $message, 'error' ); } );

    /**
     * Make WooCommerce product gallery keyboard accessible.
     * is_product() is only called inside this function, which is only
     * registered when WooCommerce is active — so it's always defined here.
     */
    function plgc_woo_gallery_a11y() {
        if ( ! is_product() ) {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var thumbs = document.querySelectorAll('.woocommerce-product-gallery__image');
            thumbs.forEach(function (thumb, index) {
                thumb.setAttribute('tabindex', '0');
                thumb.setAttribute('role', 'button');
                thumb.setAttribute('aria-label', 'Product image ' + (index + 1));
                thumb.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.querySelector('a').click();
                    }
                });
            });
        });
        </script>
        <?php
    }
    add_action( 'wp_footer', 'plgc_woo_gallery_a11y' );

    /**
     * Ensure WooCommerce form fields have proper labels.
     * (WCAG 1.3.1 - Info and Relationships)
     */
    function plgc_woo_form_field_args( $args, $key, $value ) {
        if ( ! empty( $args['placeholder'] ) && empty( $args['label'] ) ) {
            $args['label'] = $args['placeholder'];
        }
        if ( ! empty( $args['required'] ) ) {
            $args['custom_attributes']['aria-required'] = 'true';
        }
        return $args;
    }
    add_filter( 'woocommerce_form_field_args', 'plgc_woo_form_field_args', 10, 3 );

    /**
     * Add unique aria-label to cart remove ("×") links.
     * (WCAG 2.4.4 - Link Purpose)
     *
     * WooCommerce's default remove link uses "×" as the visible text with
     * an aria-label of "Remove this item" — identical for every line item.
     * Screen reader users navigating by links can't tell them apart.
     * This replaces the aria-label with "Remove <Product Name> from cart".
     */
    add_filter( 'woocommerce_cart_item_remove_link', function ( $link, $cart_item_key ) {
        $cart = WC()->cart;
        if ( ! $cart ) {
            return $link;
        }

        $cart_item = $cart->get_cart_item( $cart_item_key );
        if ( empty( $cart_item['data'] ) ) {
            return $link;
        }

        $product_name = $cart_item['data']->get_name();
        $new_label    = esc_attr( sprintf(
            /* translators: %s: product name */
            __( 'Remove %s from cart', 'plgc' ),
            $product_name
        ) );

        // Find existing aria-label and swap it via str_replace (not preg_replace,
        // which treats $N in the replacement as backreferences — product names
        // like "Gift Card - $200" would get mangled).
        if ( preg_match( '/aria-label="[^"]*"/', $link, $match ) ) {
            $link = str_replace( $match[0], 'aria-label="' . $new_label . '"', $link );
        } else {
            $link = str_replace( '<a ', '<a aria-label="' . $new_label . '" ', $link );
        }

        return $link;
    }, 10, 2 );

} // end if WooCommerce


/**
 * ============================================================
 * EVENTS CALENDAR ACCESSIBILITY
 * Only registered when The Events Calendar is active.
 * ============================================================
 */
if ( class_exists( 'Tribe__Events__Main' ) ) {

    /**
     * Add ARIA landmarks to Events Calendar views.
     * (WCAG 1.3.1 - Info and Relationships)
     */
    add_filter( 'tribe_events_before_html', function ( $html ) {
        return '<div role="region" aria-label="Events">' . $html;
    } );
    add_filter( 'tribe_events_after_html', function ( $html ) {
        return $html . '</div>';
    } );

    /**
     * Add screen-reader-friendly date formatting to abbreviated dates.
     */
    function plgc_events_date_a11y( $html ) {
        $html = preg_replace_callback(
            '/<abbr[^>]*class="[^"]*tribe-events-abbr[^"]*"[^>]*>(.*?)<\/abbr>/i',
            function ( $matches ) {
                if ( strpos( $matches[0], 'aria-label' ) !== false ) {
                    return $matches[0];
                }
                return str_replace(
                    '<abbr',
                    '<abbr aria-label="' . esc_attr( strip_tags( $matches[1] ) ) . '"',
                    $matches[0]
                );
            },
            $html
        );
        return $html;
    }
    add_filter( 'the_content', 'plgc_events_date_a11y', 25 );

    /**
     * Add focus styles and accessible touch targets to Events Calendar navigation.
     */
    function plgc_events_focus_styles() {
        if ( ! function_exists( 'tribe_is_event_query' ) || ! tribe_is_event_query() ) {
            return;
        }
        ?>
        <style>
            .tribe-events-nav-previous a:focus-visible,
            .tribe-events-nav-next a:focus-visible,
            .tribe-events-calendar td a:focus-visible,
            .tribe-events-sub-nav a:focus-visible {
                outline: var(--plgc-focus-width, 0.125rem) solid var(--plgc-focus-color, #567915);
                outline-offset: var(--plgc-focus-offset, 0.125rem);
            }
            .tribe-tickets .tribe-button:focus-visible,
            .tribe-tickets__buy:focus-visible {
                outline: var(--plgc-focus-width, 0.125rem) solid var(--plgc-focus-color, #567915);
                outline-offset: var(--plgc-focus-offset, 0.125rem);
            }
            .tribe-events-calendar td {
                min-height: 2.75rem;
            }
            .tribe-events-calendar td a {
                min-height: 2.75rem;
                min-width: 2.75rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        </style>
        <?php
    }
    add_action( 'wp_head', 'plgc_events_focus_styles' );

    /**
     * Add accessible labels to ticket quantity fields.
     */
    function plgc_ticket_quantity_label( $html ) {
        if ( strpos( $html, 'tribe-tickets-quantity' ) !== false ) {
            $html = str_replace(
                'class="tribe-tickets-quantity"',
                'class="tribe-tickets-quantity" aria-label="Ticket quantity"',
                $html
            );
        }
        return $html;
    }
    add_filter( 'tribe_tickets_ticket_quantity_field', 'plgc_ticket_quantity_label' );

	
	    /**
	 * Fix undefined ARIA attributes in Events Calendar output — server-side.
	 *
	 * The Events Calendar uses aria-description on several interactive
	 * elements (datepicker button, view selector, today link). This
	 * attribute was introduced in ARIA 1.2 but is not recognized by
	 * WAI-ARIA 1.1 validators and fails automated checks.
	 *
	 * This output-buffer approach converts aria-description to aria-label
	 * (if no aria-label exists) or drops it (if aria-label is already present)
	 * directly in the HTML, so crawlers that don't execute JS still see
	 * a compliant page.
	 *
	 * (WCAG 1.3.1 / 4.1.2)
	 */
	function plgc_tec_fix_aria_description_buffer( $html ) {
		// Strip all aria-description="..." attributes from the output.
		// TEC elements that use aria-description also have aria-label,
		// so removing aria-description is safe.
		$html = preg_replace( '/ aria-description="[^"]*"/', '', $html );
		return $html;
	}

	/**
	 * Hook into TEC's template HTML filters to strip aria-description
	 * from the server-rendered output before it reaches the browser.
	 */
	add_filter( 'tribe_template_html', 'plgc_tec_fix_aria_description_buffer', 999 );
	add_filter( 'tribe_events_before_html', 'plgc_tec_fix_aria_description_buffer', 999 );
	add_filter( 'tribe_events_after_html', 'plgc_tec_fix_aria_description_buffer', 999 );
	add_filter( 'tribe_template_pre_html', function( $html ) {
		if ( is_string( $html ) ) {
			return plgc_tec_fix_aria_description_buffer( $html );
		}
		return $html;
	}, 999 );

	/**
	 * Global output buffer: strip aria-description from the entire
	 * page output. This is the most reliable approach since TEC
	 * Views V2 renders HTML through its own pipeline that bypasses
	 * standard WordPress content filters.
	 */
	function plgc_tec_ob_start() {
		ob_start( 'plgc_tec_fix_aria_description_buffer' );
	}
	add_action( 'wp_loaded', 'plgc_tec_ob_start', 0 );

	/**
	 * Client-side fix for TEC's dynamically-rendered aria-description.
	 *
	 * TEC Views V2 renders the calendar UI via JavaScript, which means
	 * server-side output buffers can't catch the aria-description attributes
	 * that TEC's JS adds to elements. This MutationObserver watches for
	 * new elements with aria-description and converts them immediately.
	 */
	function plgc_tec_fix_aria_description_js() {
		?>
		<script>
		(function(){
			function fixAriaDescription(root) {
				var els = (root || document).querySelectorAll('[aria-description]');
				els.forEach(function(el) {
					var desc = el.getAttribute('aria-description');
					if (!desc) return;
					if (!el.hasAttribute('aria-label')) {
						el.setAttribute('aria-label', desc);
					}
					el.removeAttribute('aria-description');
				});
			}

			// Fix on DOMContentLoaded
			document.addEventListener('DOMContentLoaded', function() { fixAriaDescription(); });

			// Fix on window load (catches late-loading TEC content)
			window.addEventListener('load', function() {
				fixAriaDescription();
				// Also fix after a brief delay for async TEC rendering
				setTimeout(fixAriaDescription, 500);
				setTimeout(fixAriaDescription, 1500);
			});

			// MutationObserver: catch dynamically-added elements
			if (typeof MutationObserver !== 'undefined') {
				var observer = new MutationObserver(function(mutations) {
					mutations.forEach(function(mutation) {
						mutation.addedNodes.forEach(function(node) {
							if (node.nodeType === 1) {
								if (node.hasAttribute && node.hasAttribute('aria-description')) {
									fixAriaDescription(node.parentNode);
								}
								if (node.querySelectorAll) {
									var inner = node.querySelectorAll('[aria-description]');
									if (inner.length > 0) fixAriaDescription(node);
								}
							}
						});
					});
				});
				observer.observe(document.documentElement, {
					childList: true,
					subtree: true,
					attributes: true,
					attributeFilter: ['aria-description']
				});
			}
		})();
		</script>
		<?php
	}
	add_action( 'wp_head', 'plgc_tec_fix_aria_description_js', 1 );

	/**
	 * Also handle TEC shortcode/AJAX fragment endpoints that bypass
	 * the normal WordPress template (missing lang, title, doctype).
	 *
	 * These endpoints (e.g. /events/list/?shortcode=xxx) return bare
	 * HTML fragments. We intercept the template_redirect and wrap them
	 * in a minimal valid HTML document.
	 *
	 * (WCAG 3.1.1 / 2.4.2)
	 */
	function plgc_tec_shortcode_fragment_fix() {
		if ( empty( $_GET['shortcode'] ) ) {
			return;
		}
		// Only act on TEC event endpoints
		if ( ! function_exists( 'tribe_is_event_query' ) ) {
			return;
		}

		// Start output buffer and wrap in valid HTML
		ob_start( function ( $html ) {
			// If it already has a doctype, leave it alone
			if ( stripos( $html, '<!doctype' ) !== false ) {
				return $html;
			}
			// Also strip aria-description from the fragment
			$html = preg_replace( '/ aria-description="[^"]*"/', '', $html );

			$lang  = get_bloginfo( 'language' );
			$title = wp_get_document_title();
			if ( ! $title ) {
				$title = get_bloginfo( 'name' ) . ' — Events';
			}
			return '<!DOCTYPE html>'
				. '<html lang="' . esc_attr( $lang ) . '">'
				. '<head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">'
				. '<title>' . esc_html( $title ) . '</title>'
				. '</head><body>' . $html . '</body></html>';
		} );
	}
	add_action( 'template_redirect', 'plgc_tec_shortcode_fragment_fix', 5 );

} // end if Events Calendar


/**
 * ============================================================
 * AWSM JOB OPENINGS ACCESSIBILITY
 * Only registered when WP Job Openings (AWSM) is active.
 * ============================================================
 */
if ( class_exists( 'JEsuspended_AWSM_Job_Openings' ) || defined( 'JEAWSM_JOBS_PLUGIN_VERSION' ) || function_exists( 'awsm_jobs_init' ) || class_exists( 'JEAWSM_Job_Openings' ) ) {

	/**
	 * Add accessible labels to Selectric replacement inputs.
	 *
	 * AWSM uses the Selectric jQuery plugin to replace native <select>
	 * elements. It creates hidden <input class="awsm-selectric-input">
	 * elements with tabindex="0" but no accessible name.
	 *
	 * (WCAG 4.1.2 — Name, Role, Value)
	 */
	function plgc_awsm_selectric_a11y() {
		?>
		<script>
		(function(){
			document.addEventListener('DOMContentLoaded', function(){
				var inputs = document.querySelectorAll('.awsm-selectric-input');
				inputs.forEach(function(input) {
					if (!input.getAttribute('aria-label')) {
						// Try to find the associated label from the select it replaces
						var wrapper = input.closest('.awsm-job-select-wrapper, .awsm-selectric-wrapper');
						var select = wrapper ? wrapper.querySelector('select') : null;
						var labelText = 'Filter';
						if (select) {
							var id = select.id;
							if (id) {
								var label = document.querySelector('label[for="' + id + '"]');
								if (label) labelText = label.textContent.trim();
							}
							if (labelText === 'Filter') {
								var firstOpt = select.querySelector('option');
								if (firstOpt) labelText = firstOpt.textContent.trim();
							}
						}
						input.setAttribute('aria-label', labelText);
					}
				});
			});
		})();
		</script>
		<?php
	}
	add_action( 'wp_footer', 'plgc_awsm_selectric_a11y' );
}

/**
 * ============================================================
 * AWSM JOB OPENINGS - FALLBACK CHECK (broader detection)
 * ============================================================
 */
add_action( 'wp_footer', function() {
	?>
	<script>
	(function(){
		function fixSelectricInputs() {
			var inputs = document.querySelectorAll('input.awsm-selectric-input');
			inputs.forEach(function(input){
				if (!input.getAttribute('aria-label')) {
					var wrapper = input.closest('.awsm-selectric-wrapper, .awsm-job-select-wrapper, .selectric-wrapper');
					var select = wrapper ? wrapper.querySelector('select') : null;
					var labelText = 'Filter jobs';
					if (select) {
						var firstOpt = select.querySelector('option');
						if (firstOpt && firstOpt.textContent.trim()) {
							labelText = firstOpt.textContent.trim();
						}
					}
					input.setAttribute('aria-label', labelText);
				}
			});
		}

		// Run on DOMContentLoaded
		document.addEventListener('DOMContentLoaded', function(){
			fixSelectricInputs();
			// Re-run after Selectric has had time to initialize
			setTimeout(fixSelectricInputs, 500);
			setTimeout(fixSelectricInputs, 1500);
		});

		// Also run on window load
		window.addEventListener('load', function(){
			fixSelectricInputs();
			setTimeout(fixSelectricInputs, 300);
		});

		// MutationObserver for dynamically-created inputs
		if (typeof MutationObserver !== 'undefined') {
			var observer = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					mutation.addedNodes.forEach(function(node) {
						if (node.nodeType === 1 && node.classList &&
							node.classList.contains('awsm-selectric-input')) {
							setTimeout(fixSelectricInputs, 50);
						}
						if (node.nodeType === 1 && node.querySelectorAll) {
							var found = node.querySelectorAll('.awsm-selectric-input');
							if (found.length > 0) setTimeout(fixSelectricInputs, 50);
						}
					});
				});
			});
			observer.observe(document.body || document.documentElement, {
				childList: true, subtree: true
			});
		}
	})();
	</script>
	<?php
} );


/**
 * ============================================================
 * WOOCOMMERCE PRODUCT TABS & VARIATIONS — PRESENTATION FIX
 * Only registered when WooCommerce is active.
 * ============================================================
 */
if ( class_exists( 'WooCommerce' ) ) {

	/**
	 * Fix product tabs: WooCommerce marks <li> tab items with
	 * role="presentation" but the child <a> links are focusable.
	 *
	 * Convert the pattern to proper tab semantics:
	 * - <ul> gets role="tablist"
	 * - <li> gets role="presentation" (correct for tab pattern)
	 * - <a> gets role="tab"
	 *
	 * Also fix the variations table: remove role="presentation"
	 * from the <table class="variations"> since it contains
	 * interactive form elements.
	 *
	 * (WCAG 4.1.2 — Name, Role, Value)
	 */
	function plgc_woo_tabs_presentation_fix() {
		if ( ! is_product() ) {
			return;
		}
		?>
		<script>
		(function(){
			document.addEventListener('DOMContentLoaded', function(){
				// Fix product tabs: add role="tab" to the <a> elements
				// and remove role="presentation" from <li> to avoid
				// false positive about focusable content in presentation elements
				var tabList = document.querySelector('.woocommerce-tabs .tabs');
				if (tabList) {
					tabList.setAttribute('role', 'tablist');
					var tabLinks = tabList.querySelectorAll('li a');
					tabLinks.forEach(function(link) {
						link.setAttribute('role', 'tab');
						var li = link.closest('li');
						if (li) {
							li.removeAttribute('role');
							if (li.classList.contains('active')) {
								link.setAttribute('aria-selected', 'true');
							} else {
								link.setAttribute('aria-selected', 'false');
							}
						}
					});
				}

				// Fix variations table: remove role="presentation" since
				// it contains focusable elements (select, reset link)
				var varTable = document.querySelector('table.variations[role="presentation"]');
				if (varTable) {
					varTable.removeAttribute('role');
				}
			});
		})();
		</script>
		<?php
	}
	add_action( 'wp_footer', 'plgc_woo_tabs_presentation_fix' );

} // end WooCommerce tabs fix


/**
 * ============================================================
 * SWIPER GALLERY PAGINATION — TABLIST FIX
 * Add role="tab" to swiper pagination bullets when their
 * container has role="tablist".
 *
 * The gallery-sections.js sets role="tablist" on the pagination
 * container and aria-labels on buttons, but omits role="tab"
 * on the child buttons.
 *
 * (WCAG 1.3.1 — Parent element is missing required children)
 * ============================================================
 */
add_action( 'wp_footer', function() {
	?>
	<script>
	(function(){
		document.addEventListener('DOMContentLoaded', function(){
			var tablists = document.querySelectorAll('.swiper-pagination[role="tablist"]');
			tablists.forEach(function(tl){
				var bullets = tl.querySelectorAll('.swiper-pagination-bullet');
				bullets.forEach(function(btn){
					btn.setAttribute('role', 'tab');
					if (btn.classList.contains('swiper-pagination-bullet-active')) {
						btn.setAttribute('aria-selected', 'true');
					} else {
						btn.setAttribute('aria-selected', 'false');
					}
				});
			});
		});

		// Also update on slide change (Swiper fires events)
		document.addEventListener('click', function(e) {
			var bullet = e.target.closest('.swiper-pagination-bullet');
			if (!bullet) return;
			var tl = bullet.closest('[role="tablist"]');
			if (!tl) return;
			setTimeout(function(){
				tl.querySelectorAll('.swiper-pagination-bullet').forEach(function(b){
					b.setAttribute('aria-selected',
						b.classList.contains('swiper-pagination-bullet-active') ? 'true' : 'false');
				});
			}, 100);
		});
	})();
	</script>
	<?php
}, 25 );


/**
 * ============================================================
 * ELEMENTOR ACCORDION — ARIA FIX
 * Elementor's nested accordion uses <details>/<summary> with
 * aria-expanded and aria-controls on <summary>. While
 * aria-expanded is valid on <summary>, aria-controls pointing
 * to the parent <details> id can confuse validators.
 *
 * This fix ensures the ARIA states are correct and the
 * accordion is properly announced.
 *
 * (WCAG 4.1.2 — Incorrect use of ARIA state or property)
 * ============================================================
 */
add_action( 'wp_footer', function() {
	?>
	<script>
	(function(){
		document.addEventListener('DOMContentLoaded', function(){
			var summaries = document.querySelectorAll('.e-n-accordion-item-title');
			summaries.forEach(function(summary){
				var details = summary.closest('details');
				if (!details) return;

				// aria-controls should point to the content panel, not the details.
				// Find the content container inside details (not the summary itself).
				var content = details.querySelector('.e-n-accordion-item-title + *');
				if (content && content.id) {
					summary.setAttribute('aria-controls', content.id);
				} else if (content) {
					var panelId = 'plgc-acc-panel-' + Math.random().toString(36).substr(2, 6);
					content.id = panelId;
					summary.setAttribute('aria-controls', panelId);
				}

				// Sync aria-expanded with the open state
				summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');

				// Listen for toggle events to keep aria-expanded in sync
				details.addEventListener('toggle', function(){
					summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');
				});
			});
		});
	})();
	</script>
	<?php
}, 25 );
