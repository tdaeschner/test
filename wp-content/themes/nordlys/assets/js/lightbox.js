/**
 * A small lightbox for the photos inside a travel day.
 */
( function () {
	'use strict';

	var article = document.querySelector( '.nb-article' );

	if ( ! article ) {
		return;
	}

	var images = Array.prototype.slice.call(
		article.querySelectorAll( 'figure img, .wp-block-gallery img, .nb-gallery img' )
	).filter( function ( image ) {
		return ! image.closest( '.nb-no-zoom' );
	} );

	if ( ! images.length ) {
		return;
	}

	var overlay = null;
	var current = 0;
	var lastFocus = null;

	/**
	 * Full size source of an image, falling back to the displayed one.
	 *
	 * @param {HTMLImageElement} image Image element.
	 * @return {string}
	 */
	function fullSource( image ) {
		var link = image.closest( 'a' );

		if ( link && /\.(jpe?g|png|gif|webp|avif)$/i.test( link.getAttribute( 'href' ) || '' ) ) {
			return link.getAttribute( 'href' );
		}

		return image.currentSrc || image.src;
	}

	/**
	 * Caption belonging to an image.
	 *
	 * @param {HTMLImageElement} image Image element.
	 * @return {string}
	 */
	function caption( image ) {
		var figure = image.closest( 'figure' );
		var element = figure ? figure.querySelector( 'figcaption' ) : null;

		return element ? element.textContent.trim() : ( image.getAttribute( 'alt' ) || '' );
	}

	/**
	 * Builds the overlay once.
	 */
	function build() {
		overlay = document.createElement( 'div' );
		overlay.className = 'nb-lightbox';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.innerHTML =
			'<button type="button" class="nb-lightbox__close" aria-label="Schließen">&times;</button>' +
			'<button type="button" class="nb-lightbox__nav nb-lightbox__nav--prev" aria-label="Vorheriges Bild">&#8249;</button>' +
			'<figure class="nb-lightbox__figure">' +
			'<img class="nb-lightbox__image" alt="" />' +
			'<figcaption class="nb-lightbox__caption"></figcaption>' +
			'</figure>' +
			'<button type="button" class="nb-lightbox__nav nb-lightbox__nav--next" aria-label="Nächstes Bild">&#8250;</button>';

		document.body.appendChild( overlay );

		overlay.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '.nb-lightbox__close' ) || event.target === overlay ) {
				close();

				return;
			}

			if ( event.target.closest( '.nb-lightbox__nav--prev' ) ) {
				show( current - 1 );
			}

			if ( event.target.closest( '.nb-lightbox__nav--next' ) ) {
				show( current + 1 );
			}
		} );
	}

	/**
	 * Shows an image by index, wrapping around.
	 *
	 * @param {number} index Image index.
	 */
	function show( index ) {
		current = ( index + images.length ) % images.length;

		var image = images[ current ];
		var target = overlay.querySelector( '.nb-lightbox__image' );
		var text = overlay.querySelector( '.nb-lightbox__caption' );

		target.src = fullSource( image );
		target.alt = image.getAttribute( 'alt' ) || '';
		text.textContent = caption( image );
		text.hidden = ! text.textContent;

		overlay.classList.toggle( 'is-single', images.length < 2 );
	}

	/**
	 * Opens the lightbox.
	 *
	 * @param {number} index Image index.
	 */
	function open( index ) {
		if ( ! overlay ) {
			build();
		}

		lastFocus = document.activeElement;
		show( index );
		overlay.classList.add( 'is-open' );
		document.body.classList.add( 'nb-lightbox-open' );
		overlay.querySelector( '.nb-lightbox__close' ).focus();
	}

	/**
	 * Closes the lightbox.
	 */
	function close() {
		if ( ! overlay ) {
			return;
		}

		overlay.classList.remove( 'is-open' );
		document.body.classList.remove( 'nb-lightbox-open' );

		if ( lastFocus ) {
			lastFocus.focus();
		}
	}

	images.forEach( function ( image, index ) {
		image.classList.add( 'nb-zoomable' );

		image.addEventListener( 'click', function ( event ) {
			// Links to the media file are replaced by the lightbox.
			var link = image.closest( 'a' );

			if ( link && ! /\.(jpe?g|png|gif|webp|avif)$/i.test( link.getAttribute( 'href' ) || '' ) ) {
				return;
			}

			event.preventDefault();
			open( index );
		} );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( ! overlay || ! overlay.classList.contains( 'is-open' ) ) {
			return;
		}

		if ( 'Escape' === event.key ) {
			close();
		}

		if ( 'ArrowLeft' === event.key ) {
			show( current - 1 );
		}

		if ( 'ArrowRight' === event.key ) {
			show( current + 1 );
		}
	} );
} )();
