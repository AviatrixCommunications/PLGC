/**
 * PLGC Two-Column Accordion — Interaction Script
 *
 * Handles expand/collapse toggling with full ARIA management.
 *
 * WCAG 2.1 AA coverage:
 *  SC 4.1.2  — aria-expanded toggled on every state change
 *  SC 2.1.1  — Enter/Space on button (native button behavior, no extra handling needed)
 *              Tab moves into open panel content automatically
 *  SC 2.2.2  — No auto-advancing behavior
 *
 * Re-initializes on Elementor frontend/element_ready so the widget works
 * correctly inside the Elementor live editor preview.
 *
 * @package PLGC
 * @since   1.6.30
 */

( function () {
	'use strict';

	/**
	 * Initialize all .plgc-two-col-accordion containers found in the given root element.
	 *
	 * @param {Element} root  The element to search within. Defaults to document.
	 */
	function initAccordions( root ) {
		root = root || document;

		root.querySelectorAll( '.plgc-two-col-accordion' ).forEach( function ( container ) {
			// Avoid double-binding if already initialized
			if ( container.dataset.plgcAccordionInit === 'true' ) {
				return;
			}
			container.dataset.plgcAccordionInit = 'true';

			var allowMultiple = container.dataset.allowMultiple === 'true';

			container.querySelectorAll( '.plgc-accordion-trigger' ).forEach( function ( trigger ) {
				trigger.addEventListener( 'click', function () {
					toggleItem( trigger, container, allowMultiple );
				} );
			} );
		} );
	}

	/**
	 * Toggle a single accordion item open or closed.
	 *
	 * @param {HTMLButtonElement} trigger        The clicked trigger button.
	 * @param {Element}           container      The parent .plgc-two-col-accordion.
	 * @param {boolean}           allowMultiple  Whether multiple items can be open simultaneously.
	 */
	function toggleItem( trigger, container, allowMultiple ) {
		var isExpanded = trigger.getAttribute( 'aria-expanded' ) === 'true';
		var panelId    = trigger.getAttribute( 'aria-controls' );
		var panel      = document.getElementById( panelId );
		var item       = trigger.closest( '.plgc-accordion-item' );

		if ( ! item || ! panel ) {
			return;
		}

		// If allow_multiple is off, close all other open items first
		if ( ! allowMultiple && ! isExpanded ) {
			container.querySelectorAll( '.plgc-accordion-trigger[aria-expanded="true"]' ).forEach( function ( otherTrigger ) {
				if ( otherTrigger === trigger ) {
					return;
				}
				var otherPanelId = otherTrigger.getAttribute( 'aria-controls' );
				var otherPanel   = document.getElementById( otherPanelId );
				var otherItem    = otherTrigger.closest( '.plgc-accordion-item' );

				otherTrigger.setAttribute( 'aria-expanded', 'false' );
				if ( otherItem )  { otherItem.classList.remove( 'is-open' ); }
				if ( otherPanel ) { otherPanel.hidden = true; }
			} );
		}

		// Toggle current item
		var newExpanded = ! isExpanded;
		trigger.setAttribute( 'aria-expanded', newExpanded.toString() );
		item.classList.toggle( 'is-open', newExpanded );
		panel.hidden = ! newExpanded;
	}

	// ── Bootstrap ──────────────────────────────────────────────────────────

	// Standard DOM-ready init
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAccordions( document );
		} );
	} else {
		initAccordions( document );
	}

	// Elementor frontend hook — fires whenever a widget is rendered/re-rendered
	// in the live editor preview. $scope is a jQuery object wrapping the widget.
	if ( window.elementorFrontend ) {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/plgc_two_col_accordion.default',
			function ( $scope ) {
				// $scope[0] is the underlying DOM element
				var el = ( $scope && $scope[0] ) ? $scope[0] : document;
				// Reset init flag so we can re-bind after Elementor re-renders
				var containers = el.querySelectorAll( '.plgc-two-col-accordion' );
				containers.forEach( function ( c ) {
					delete c.dataset.plgcAccordionInit;
				} );
				initAccordions( el );
			}
		);
	}

} )();
