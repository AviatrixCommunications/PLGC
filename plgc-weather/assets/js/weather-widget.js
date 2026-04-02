/**
 * PLGC Weather Widget — Popup JS
 * Handles open/close, click-outside, Escape key, and focus trap.
 */
( function () {
    'use strict';

    function init() {
        document.querySelectorAll( '.plgc-wx' ).forEach( function ( widget ) {
            var trigger = widget.querySelector( '.plgc-wx__trigger' );
            var popup   = widget.querySelector( '.plgc-wx__popup' );
            var close   = widget.querySelector( '.plgc-wx__close' );

            if ( ! trigger || ! popup ) return;

            function openPopup() {
                popup.removeAttribute( 'hidden' );
                trigger.setAttribute( 'aria-expanded', 'true' );
                if ( close ) close.focus();
            }

            function closePopup() {
                popup.setAttribute( 'hidden', '' );
                trigger.setAttribute( 'aria-expanded', 'false' );
                trigger.focus();
            }

            function togglePopup() {
                if ( popup.hasAttribute( 'hidden' ) ) {
                    openPopup();
                } else {
                    closePopup();
                }
            }

            trigger.addEventListener( 'click', togglePopup );

            if ( close ) {
                close.addEventListener( 'click', closePopup );
            }

            // Escape key closes
            widget.addEventListener( 'keydown', function ( e ) {
                if ( e.key === 'Escape' && ! popup.hasAttribute( 'hidden' ) ) {
                    closePopup();
                }
            } );

            // Click outside closes
            document.addEventListener( 'click', function ( e ) {
                if ( ! popup.hasAttribute( 'hidden' ) && ! widget.contains( e.target ) ) {
                    closePopup();
                }
            } );
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
} )();
