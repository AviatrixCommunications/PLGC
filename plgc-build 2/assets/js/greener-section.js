/**
 * PLGC Greener Section — Testimonial Slider
 *
 * Manual-advance only (no autoplay) — WCAG 2.1 AA compliant.
 * Prev/next arrows flank the quote. No dot navigation.
 *
 * Keyboard: left/right arrow keys while focus is inside the slider.
 * Screen reader: aria-live region announces each slide change.
 * Reduced motion: fade animation disabled via CSS; JS still functions.
 *
 * @package PLGC
 * @since   1.6.1
 */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-greener-slider]' ).forEach( initSlider );
	} );

	function initSlider( slider ) {
		const slides     = Array.from( slider.querySelectorAll( '.plgc-greener__slide' ) );
		const prevBtn    = slider.querySelector( '[data-greener-prev]' );
		const nextBtn    = slider.querySelector( '[data-greener-next]' );
		const liveRegion = slider.querySelector( '.plgc-greener__sr-live' );

		if ( slides.length < 2 ) return;

		let current = 0;

		function goTo( index ) {
			index = ( ( index % slides.length ) + slides.length ) % slides.length;

			slides[ current ].classList.remove( 'is-active' );
			slides[ current ].setAttribute( 'aria-hidden', 'true' );

			current = index;

			slides[ current ].classList.add( 'is-active' );
			slides[ current ].removeAttribute( 'aria-hidden' );

			if ( liveRegion ) {
				liveRegion.textContent = '';
				setTimeout( function () {
					liveRegion.textContent =
						'Testimonial ' + ( current + 1 ) + ' of ' + slides.length;
				}, 50 );
			}
		}

		if ( prevBtn ) prevBtn.addEventListener( 'click', function () { goTo( current - 1 ); } );
		if ( nextBtn ) nextBtn.addEventListener( 'click', function () { goTo( current + 1 ); } );

		slider.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowLeft' )  { goTo( current - 1 ); e.preventDefault(); }
			if ( e.key === 'ArrowRight' ) { goTo( current + 1 ); e.preventDefault(); }
		} );
	}

} () );
