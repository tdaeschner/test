/**
 * Small site-wide behaviours: sticky header state and reveal on scroll.
 */
( function () {
	'use strict';

	var header = document.getElementById( 'nb-header' );

	if ( header ) {
		var onScroll = function () {
			header.classList.toggle( 'is-scrolled', ( window.pageYOffset || document.documentElement.scrollTop ) > 24 );
		};

		window.addEventListener( 'scroll', onScroll, { passive: true } );
		onScroll();
	}

	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches || ! window.IntersectionObserver ) {
		document.querySelectorAll( '[data-nb-reveal]' ).forEach( function ( node ) {
			node.classList.add( 'is-revealed' );
		} );

		return;
	}

	var observer = new window.IntersectionObserver(
		function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) {
					return;
				}

				entry.target.classList.add( 'is-revealed' );
				observer.unobserve( entry.target );
			} );
		},
		{ rootMargin: '0px 0px -12% 0px', threshold: 0.08 }
	);

	document.querySelectorAll( '[data-nb-reveal]' ).forEach( function ( node ) {
		observer.observe( node );
	} );
} )();
