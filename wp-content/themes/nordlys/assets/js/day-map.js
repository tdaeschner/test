/**
 * The small map on a single travel day: the day's track plus its flags.
 */
( function () {
	'use strict';

	var data = window.nordlysDay;
	var node = document.getElementById( 'nb-day-map' );

	if ( ! data || ! node || ! window.maplibregl || ! window.NBMapStyle ) {
		return;
	}

	var track = data.track || [];
	var pins = data.pins || [];

	if ( ! track.length && ! pins.length ) {
		var wrap = node.closest( '.nb-day-map-wrap' );

		// A day without any geo data simply hides its map section.
		if ( wrap ) {
			wrap.hidden = true;
		}

		return;
	}

	var reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var points = track.length ? track.slice() : [];

	pins.forEach( function ( pin ) {
		points.push( pin.lngLat );
	} );

	var map = window.NBMapStyle.create( node, data.style, {
		cooperativeGestures: true,
		mapOptions: {
			center: points[ 0 ],
			zoom: 8
		}
	} );

	map.addControl( new window.maplibregl.NavigationControl( { visualizePitch: false } ), 'top-right' );
	map.addControl( new window.maplibregl.FullscreenControl(), 'top-right' );

	map.on( 'load', function () {
		if ( track.length > 1 ) {
			map.addSource( 'nb-day', {
				type: 'geojson',
				data: window.NBMapStyle.lineFeature( track ),
				lineMetrics: true
			} );

			map.addLayer( {
				id: 'nb-day-glow',
				type: 'line',
				source: 'nb-day',
				layout: { 'line-cap': 'round', 'line-join': 'round' },
				paint: {
					'line-color': '#39a0ff',
					'line-width': 14,
					'line-blur': 12,
					'line-opacity': 0.4
				}
			} );

			map.addLayer( {
				id: 'nb-day-line',
				type: 'line',
				source: 'nb-day',
				layout: { 'line-cap': 'round', 'line-join': 'round' },
				paint: {
					'line-width': [ 'interpolate', [ 'linear' ], [ 'zoom' ], 5, 2.4, 10, 4, 14, 5.5 ],
					'line-gradient': [
						'interpolate',
						[ 'linear' ],
						[ 'line-progress' ],
						0,
						'#4fe0b0',
						0.5,
						'#39a0ff',
						1,
						'#a86bff'
					]
				}
			} );

			addEndpoint( track[ 0 ], 'start' );
			addEndpoint( track[ track.length - 1 ], 'end' );
		}

		pins.forEach( function ( pin ) {
			var element = document.createElement( 'button' );

			element.type = 'button';
			element.className = 'nb-flag nb-flag--' + ( pin.icon || 'flag' );
			element.setAttribute( 'aria-label', pin.title || '' );
			element.innerHTML = '<span class="nb-flag__pole"></span><span class="nb-flag__body">' + glyph( pin.icon ) + '</span>';

			new window.maplibregl.Marker( { element: element, anchor: 'bottom' } )
				.setLngLat( pin.lngLat )
				.setPopup(
					new window.maplibregl.Popup( {
						offset: 26,
						maxWidth: '260px',
						className: 'nb-popup'
					} ).setHTML( popupHtml( pin ) )
				)
				.addTo( map );
		} );

		window.NBMapStyle.fitToPoints( map, points, {
			padding: 60,
			maxZoom: 12,
			duration: reduceMotion ? 0 : 800
		} );
	} );

	/**
	 * Adds a small circle at the start or the end of the track.
	 *
	 * @param {Array}  point Coordinate.
	 * @param {string} kind  start|end.
	 */
	function addEndpoint( point, kind ) {
		var id = 'nb-day-' + kind;

		map.addSource( id, {
			type: 'geojson',
			data: { type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: point } }
		} );

		map.addLayer( {
			id: id + '-dot',
			type: 'circle',
			source: id,
			paint: {
				'circle-radius': 5,
				'circle-color': 'start' === kind ? '#4fe0b0' : '#a86bff',
				'circle-stroke-width': 2,
				'circle-stroke-color': 'rgba(7, 11, 18, 0.85)'
			}
		} );
	}

	/**
	 * Popup markup for a flag.
	 *
	 * @param {Object} pin Pin data.
	 * @return {string}
	 */
	function popupHtml( pin ) {
		var parts = [];

		if ( pin.image ) {
			parts.push( '<img class="nb-popup__image" src="' + escapeAttr( pin.image ) + '" alt="" loading="lazy" />' );
		}

		if ( pin.title ) {
			parts.push( '<h3 class="nb-popup__title">' + escapeHtml( pin.title ) + '</h3>' );
		}

		if ( pin.text ) {
			parts.push( '<p class="nb-popup__text">' + escapeHtml( pin.text ) + '</p>' );
		}

		return '<div class="nb-popup__inner">' + parts.join( '' ) + '</div>';
	}

	/**
	 * Inline icon for a flag.
	 *
	 * @param {string} icon Icon key.
	 * @return {string}
	 */
	function glyph( icon ) {
		var glyphs = {
			flag: '<path d="M6 3v18M6 4h11l-2.2 3.4L17 11H6z"/>',
			view: '<path d="M3 17 9 8l4 5 2.5-3L21 17z"/><circle cx="17" cy="6" r="2"/>',
			hike: '<circle cx="13" cy="4.5" r="2"/><path d="m9 21 3-6-2.5-3L7 15H4m8-6 3 2 1 4 3 6"/>',
			water: '<path d="M12 3c3 4 5 6.5 5 9a5 5 0 0 1-10 0c0-2.5 2-5 5-9Z"/>',
			wildlife: '<path d="M5 10c0-3 2-5 4-5s3 1 3 3-1 3-3 3-4-1-4-1Zm14 0c0-3-2-5-4-5m-3 8v6m-4 0h8"/>',
			food: '<path d="M6 3v8a2 2 0 0 0 4 0V3M8 11v10M17 3c-1.5 1-2 3-2 5s.5 3 2 3v10"/>',
			camp: '<path d="m12 4 8 16H4zM12 4v16"/>',
			photo: '<path d="M3 7h4l1.5-2h7L17 7h4v12H3z"/><circle cx="12" cy="13" r="3.5"/>',
			star: '<path d="m12 3 2.7 5.6 6.3.9-4.5 4.3 1 6.2-5.5-3-5.5 3 1-6.2L3 9.5l6.3-.9z"/>'
		};

		return (
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" ' +
			'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
			( glyphs[ icon ] || glyphs.flag ) +
			'</svg>'
		);
	}

	/**
	 * Escapes text for HTML output.
	 *
	 * @param {string} value Raw value.
	 * @return {string}
	 */
	function escapeHtml( value ) {
		var span = document.createElement( 'span' );

		span.textContent = value == null ? '' : String( value );

		return span.innerHTML;
	}

	/**
	 * Escapes a value for an attribute.
	 *
	 * @param {string} value Raw value.
	 * @return {string}
	 */
	function escapeAttr( value ) {
		return escapeHtml( value ).replace( /"/g, '&quot;' );
	}

	// Jumping from the highlight list to the matching flag.
	document.querySelectorAll( '[data-nb-focus-pin]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var index = parseInt( button.getAttribute( 'data-nb-focus-pin' ), 10 );
			var pin = pins[ index ];

			if ( ! pin ) {
				return;
			}

			node.scrollIntoView( { behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' } );

			map.easeTo( {
				center: pin.lngLat,
				zoom: Math.max( map.getZoom(), 11 ),
				duration: reduceMotion ? 0 : 900
			} );
		} );
	} );
} )();
