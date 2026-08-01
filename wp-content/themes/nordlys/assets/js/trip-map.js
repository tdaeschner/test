/**
 * The big trip map: one line that fills up while scrolling, flags that pop in
 * where something happened, and a camera that follows the current day.
 */
( function () {
	'use strict';

	var data = window.nordlysTrip;
	var mapNode = document.getElementById( 'nb-trip-map' );
	var scroller = document.getElementById( 'nb-trip-scroller' );

	if ( ! data || ! data.days || ! data.days.length || ! mapNode || ! scroller || ! window.maplibregl ) {
		return;
	}

	var reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	var hud = {
		badge: document.getElementById( 'nb-hud-badge' ),
		title: document.getElementById( 'nb-hud-title' ),
		bar: document.getElementById( 'nb-hud-bar' ),
		distance: document.getElementById( 'nb-hud-distance' ),
		follow: document.getElementById( 'nb-hud-follow' )
	};

	var cards = Array.prototype.slice.call( scroller.querySelectorAll( '.nb-day-card' ) );

	// ---------------------------------------------------------------------
	// Geometry: one continuous line, plus where each day sits on it.
	// ---------------------------------------------------------------------

	var route = buildRoute( data.days );

	if ( route.coordinates.length < 2 ) {
		mapNode.classList.add( 'is-empty' );
	}

	/**
	 * Concatenates all day tracks and measures where every day and every flag
	 * sits along the resulting line, as a fraction between 0 and 1.
	 *
	 * @param {Array} days Day payloads.
	 * @return {Object}
	 */
	function buildRoute( days ) {
		var coordinates = [];
		var segments = [];
		var cumulative = [];
		var total = 0;

		days.forEach( function ( day ) {
			var start = coordinates.length;

			( day.track || [] ).forEach( function ( point ) {
				if ( coordinates.length ) {
					total += haversine( coordinates[ coordinates.length - 1 ], point );
				}

				coordinates.push( point );
				cumulative.push( total );
			} );

			segments.push( {
				day: day,
				startIndex: start,
				endIndex: Math.max( start, coordinates.length - 1 ),
				hasTrack: coordinates.length > start
			} );
		} );

		// cumulative[i] is the distance travelled up to coordinates[i].
		var fractions = cumulative.map( function ( value ) {
			return total > 0 ? value / total : 0;
		} );

		segments.forEach( function ( segment, index ) {
			if ( ! segment.hasTrack ) {
				// A day without a track keeps the progress where the last one ended.
				var previous = index > 0 ? segments[ index - 1 ].to : 0;

				segment.from = previous;
				segment.to = previous;

				return;
			}

			segment.from = fractions[ segment.startIndex ] || 0;
			segment.to = fractions[ segment.endIndex ] || 0;
		} );

		var pins = [];

		segments.forEach( function ( segment, dayIndex ) {
			( segment.day.pins || [] ).forEach( function ( pin ) {
				pins.push( {
					data: pin,
					day: segment.day,
					dayIndex: dayIndex,
					progress: segment.hasTrack
						? fractions[ nearestIndex( pin.lngLat, coordinates, segment.startIndex, segment.endIndex ) ]
						: segment.from
				} );
			} );
		} );

		return {
			coordinates: coordinates,
			fractions: fractions,
			segments: segments,
			pins: pins,
			totalKm: total
		};
	}

	/**
	 * Rough great circle distance in kilometres.
	 *
	 * @param {Array} a Point as [lng, lat].
	 * @param {Array} b Point as [lng, lat].
	 * @return {number}
	 */
	function haversine( a, b ) {
		var toRad = Math.PI / 180;
		var lat1 = a[ 1 ] * toRad;
		var lat2 = b[ 1 ] * toRad;
		var dLat = lat2 - lat1;
		var dLng = ( b[ 0 ] - a[ 0 ] ) * toRad;
		var h =
			Math.sin( dLat / 2 ) * Math.sin( dLat / 2 ) +
			Math.cos( lat1 ) * Math.cos( lat2 ) * Math.sin( dLng / 2 ) * Math.sin( dLng / 2 );

		return 12742.0176 * Math.asin( Math.min( 1, Math.sqrt( h ) ) );
	}

	/**
	 * Index of the track vertex closest to a point, within a range.
	 *
	 * @param {Array}  point       Point as [lng, lat].
	 * @param {Array}  coordinates Track.
	 * @param {number} from        First index.
	 * @param {number} to          Last index.
	 * @return {number}
	 */
	function nearestIndex( point, coordinates, from, to ) {
		var best = from;
		var bestDistance = Infinity;

		for ( var i = from; i <= to && i < coordinates.length; i++ ) {
			var dx = coordinates[ i ][ 0 ] - point[ 0 ];
			var dy = coordinates[ i ][ 1 ] - point[ 1 ];
			var distance = dx * dx + dy * dy;

			if ( distance < bestDistance ) {
				bestDistance = distance;
				best = i;
			}
		}

		return best;
	}

	/**
	 * Position on the route at a given fraction.
	 *
	 * @param {number} progress Fraction between 0 and 1.
	 * @return {Array} Point as [lng, lat].
	 */
	function pointAt( progress ) {
		var coordinates = route.coordinates;

		if ( ! coordinates.length ) {
			return null;
		}

		if ( progress <= 0 ) {
			return coordinates[ 0 ];
		}

		if ( progress >= 1 ) {
			return coordinates[ coordinates.length - 1 ];
		}

		for ( var i = 1; i < coordinates.length; i++ ) {
			if ( route.fractions[ i ] >= progress ) {
				var span = route.fractions[ i ] - route.fractions[ i - 1 ];
				var t = span > 0 ? ( progress - route.fractions[ i - 1 ] ) / span : 0;

				return [
					coordinates[ i - 1 ][ 0 ] + ( coordinates[ i ][ 0 ] - coordinates[ i - 1 ][ 0 ] ) * t,
					coordinates[ i - 1 ][ 1 ] + ( coordinates[ i ][ 1 ] - coordinates[ i - 1 ][ 1 ] ) * t
				];
			}
		}

		return coordinates[ coordinates.length - 1 ];
	}

	// ---------------------------------------------------------------------
	// Map
	// ---------------------------------------------------------------------

	var map = window.NBMapStyle.create( mapNode, data.style, {
		mapOptions: {
			center: route.coordinates.length ? route.coordinates[ 0 ] : data.style.center,
			zoom: data.style.zoom,
			pitch: reduceMotion ? 0 : 35,
			bearing: 0
		}
	} );

	map.addControl( new window.maplibregl.NavigationControl( { visualizePitch: true, showCompass: true } ), 'top-right' );
	map.addControl( new window.maplibregl.ScaleControl( { unit: 'metric' } ), 'bottom-left' );
	map.addControl( new window.maplibregl.FullscreenControl( { container: document.getElementById( 'nb-trip' ) } ), 'top-right' );

	window.NBMapStyle.addBasemapControl( map, data.style, { title: data.i18n.basemap }, 'top-right' );

	var followCamera = true;
	var activeIndex = -1;
	var progress = 0;
	var markers = [];
	var headMarker = null;
	var ready = false;

	map.on( 'load', function () {
		addLayers();
		addMarkers();
		ready = true;

		fitAll( 0 );
		update( true );
	} );

	/**
	 * Adds every source and layer the route needs.
	 */
	function addLayers() {
		if ( data.planned && data.planned.length > 1 ) {
			map.addSource( 'nb-planned', {
				type: 'geojson',
				data: window.NBMapStyle.lineFeature( data.planned )
			} );

			map.addLayer( {
				id: 'nb-planned-line',
				type: 'line',
				source: 'nb-planned',
				layout: { 'line-cap': 'round', 'line-join': 'round' },
				paint: {
					'line-color': '#8fa3bd',
					'line-width': 1.6,
					'line-opacity': 0.35,
					'line-dasharray': [ 1.5, 2.5 ]
				}
			} );
		}

		if ( route.coordinates.length < 2 ) {
			return;
		}

		var feature = window.NBMapStyle.lineFeature( route.coordinates );

		map.addSource( 'nb-route', { type: 'geojson', data: feature, lineMetrics: true } );

		map.addLayer( {
			id: 'nb-route-base',
			type: 'line',
			source: 'nb-route',
			layout: { 'line-cap': 'round', 'line-join': 'round' },
			paint: {
				'line-color': '#5c7a99',
				'line-width': 2,
				'line-opacity': 0.28
			}
		} );

		map.addLayer( {
			id: 'nb-route-glow',
			type: 'line',
			source: 'nb-route',
			layout: { 'line-cap': 'round', 'line-join': 'round' },
			paint: {
				'line-width': 16,
				'line-blur': 14,
				'line-opacity': 0.45,
				'line-gradient': gradient( 0 )
			}
		} );

		map.addLayer( {
			id: 'nb-route-progress',
			type: 'line',
			source: 'nb-route',
			layout: { 'line-cap': 'round', 'line-join': 'round' },
			paint: {
				'line-width': [ 'interpolate', [ 'linear' ], [ 'zoom' ], 4, 2.6, 9, 4.2, 14, 6 ],
				'line-gradient': gradient( 0 )
			}
		} );

		// Start dot.
		map.addSource( 'nb-start', {
			type: 'geojson',
			data: {
				type: 'Feature',
				properties: {},
				geometry: { type: 'Point', coordinates: route.coordinates[ 0 ] }
			}
		} );

		map.addLayer( {
			id: 'nb-start-dot',
			type: 'circle',
			source: 'nb-start',
			paint: {
				'circle-radius': 5,
				'circle-color': '#4fe0b0',
				'circle-stroke-width': 2,
				'circle-stroke-color': 'rgba(7, 11, 18, 0.85)'
			}
		} );

		var head = document.createElement( 'div' );
		head.className = 'nb-head' + ( reduceMotion ? ' nb-head--still' : '' );
		head.innerHTML = '<span class="nb-head__pulse"></span><span class="nb-head__dot"></span>';

		headMarker = new window.maplibregl.Marker( { element: head } )
			.setLngLat( route.coordinates[ 0 ] )
			.addTo( map );
	}

	/**
	 * Builds the line-gradient expression for a given progress.
	 *
	 * Everything past the progress point is fully transparent, which is what
	 * makes the route look like it is being drawn.
	 *
	 * @param {number} value Progress between 0 and 1.
	 * @return {Array}
	 */
	function gradient( value ) {
		var p = Math.min( 0.9995, Math.max( 0.0005, value ) );

		return [
			'interpolate',
			[ 'linear' ],
			[ 'line-progress' ],
			0,
			'#4fe0b0',
			p * 0.45,
			'#39a0ff',
			p * 0.9,
			'#a86bff',
			p * 0.995,
			'rgba(168, 107, 255, 0.75)',
			p,
			'rgba(168, 107, 255, 0)',
			1,
			'rgba(168, 107, 255, 0)'
		];
	}

	/**
	 * Creates one marker per flag; they stay hidden until the route reaches them.
	 */
	function addMarkers() {
		route.pins.forEach( function ( pin ) {
			var element = document.createElement( 'button' );

			element.type = 'button';
			element.className = 'nb-flag nb-flag--' + ( pin.data.icon || 'flag' );
			element.setAttribute( 'aria-label', pin.data.title || data.i18n.day );
			element.innerHTML =
				'<span class="nb-flag__pole"></span>' +
				'<span class="nb-flag__body">' + glyph( pin.data.icon ) + '</span>';

			var marker = new window.maplibregl.Marker( { element: element, anchor: 'bottom' } )
				.setLngLat( pin.data.lngLat )
				.setPopup(
					new window.maplibregl.Popup( {
						offset: 26,
						closeButton: true,
						maxWidth: '280px',
						className: 'nb-popup'
					} ).setHTML( popupHtml( pin ) )
				)
				.addTo( map );

			pin.marker = marker;
			pin.element = element;
			pin.visible = false;
			element.classList.add( 'is-hidden' );
		} );
	}

	/**
	 * Popup markup for a flag.
	 *
	 * @param {Object} pin Prepared pin.
	 * @return {string}
	 */
	function popupHtml( pin ) {
		var parts = [];

		if ( pin.data.image ) {
			parts.push( '<img class="nb-popup__image" src="' + escapeAttr( pin.data.image ) + '" alt="" loading="lazy" />' );
		}

		parts.push( '<p class="nb-popup__day">' + escapeHtml( data.i18n.day + ' ' + ( pin.day.number || pin.dayIndex + 1 ) ) + '</p>' );

		if ( pin.data.title ) {
			parts.push( '<h3 class="nb-popup__title">' + escapeHtml( pin.data.title ) + '</h3>' );
		}

		if ( pin.data.text ) {
			parts.push( '<p class="nb-popup__text">' + escapeHtml( pin.data.text ) + '</p>' );
		}

		parts.push(
			'<a class="nb-popup__link" href="' + escapeAttr( pin.day.permalink ) + '">' +
			escapeHtml( data.i18n.readMore ) +
			'</a>'
		);

		return '<div class="nb-popup__inner">' + parts.join( '' ) + '</div>';
	}

	/**
	 * Small inline icon set for the flags.
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
		var node = document.createElement( 'span' );

		node.textContent = value == null ? '' : String( value );

		return node.innerHTML;
	}

	/**
	 * Escapes a value for an HTML attribute.
	 *
	 * @param {string} value Raw value.
	 * @return {string}
	 */
	function escapeAttr( value ) {
		return escapeHtml( value ).replace( /"/g, '&quot;' );
	}

	// ---------------------------------------------------------------------
	// Scroll handling
	// ---------------------------------------------------------------------

	var cardTops = [];

	/**
	 * Caches the vertical position of every card.
	 */
	function measure() {
		var offset = window.pageYOffset || document.documentElement.scrollTop;

		cardTops = cards.map( function ( card ) {
			return card.getBoundingClientRect().top + offset;
		} );
	}

	/**
	 * Turns the scroll position into a progress value along the route.
	 *
	 * @return {Object} Active index and progress.
	 */
	function readScroll() {
		if ( ! cardTops.length ) {
			return { index: 0, progress: 0 };
		}

		var offset = window.pageYOffset || document.documentElement.scrollTop;
		var anchor = offset + window.innerHeight * 0.55;
		var index = 0;

		for ( var i = 0; i < cardTops.length; i++ ) {
			if ( cardTops[ i ] <= anchor ) {
				index = i;
			}
		}

		var start = cardTops[ index ];
		var end = index + 1 < cardTops.length
			? cardTops[ index + 1 ]
			: start + cards[ index ].offsetHeight;

		var local = end > start ? ( anchor - start ) / ( end - start ) : 0;

		local = Math.min( 1, Math.max( 0, local ) );

		// Before the first card the route stays at its starting point.
		if ( anchor < cardTops[ 0 ] ) {
			return { index: 0, progress: 0 };
		}

		var segment = route.segments[ index ];

		if ( ! segment ) {
			return { index: index, progress: 1 };
		}

		return {
			index: index,
			progress: segment.from + ( segment.to - segment.from ) * local
		};
	}

	/**
	 * Applies the current scroll state to map, flags and HUD.
	 *
	 * @param {boolean} force Run even when nothing changed.
	 */
	function update( force ) {
		if ( ! ready ) {
			return;
		}

		var state = readScroll();
		var changedDay = state.index !== activeIndex;

		if ( ! force && ! changedDay && Math.abs( state.progress - progress ) < 0.0004 ) {
			return;
		}

		progress = state.progress;

		if ( map.getLayer( 'nb-route-progress' ) ) {
			var expression = gradient( progress );

			map.setPaintProperty( 'nb-route-progress', 'line-gradient', expression );
			map.setPaintProperty( 'nb-route-glow', 'line-gradient', expression );
		}

		var head = pointAt( progress );

		if ( headMarker && head ) {
			headMarker.setLngLat( head );
		}

		updateFlags();

		if ( changedDay ) {
			activeIndex = state.index;
			updateHud();
			highlightCard();

			if ( followCamera ) {
				focusDay( activeIndex );
			}
		}
	}

	/**
	 * Shows every flag the route has already reached.
	 */
	function updateFlags() {
		route.pins.forEach( function ( pin ) {
			var shouldShow = progress >= pin.progress - 0.001;

			if ( shouldShow === pin.visible ) {
				return;
			}

			pin.visible = shouldShow;
			pin.element.classList.toggle( 'is-hidden', ! shouldShow );

			if ( ! shouldShow && pin.marker.getPopup().isOpen() ) {
				pin.marker.togglePopup();
			}
		} );
	}

	/**
	 * Writes the current day into the overlay.
	 */
	function updateHud() {
		var segment = route.segments[ activeIndex ];

		if ( ! segment ) {
			return;
		}

		var day = segment.day;

		if ( hud.badge ) {
			hud.badge.textContent = data.i18n.day + ' ' + ( day.number || activeIndex + 1 );
		}

		if ( hud.title ) {
			hud.title.textContent = day.place || day.title;
		}

		if ( hud.bar ) {
			hud.bar.style.transform = 'scaleX(' + Math.max( 0.004, progress ).toFixed( 4 ) + ')';
		}

		if ( hud.distance ) {
			// Gezählt wird die tatsächlich gefahrene Strecke, nicht die Länge
			// der gezeichneten Linie - die überbrückt auch Lücken zwischen
			// zwei Aufzeichnungen eines Tages.
			var total = data.trip && data.trip.totalKm ? data.trip.totalKm : route.totalKm;

			hud.distance.textContent = Math.round( total * progress ) + ' ' + data.i18n.km;
		}
	}

	/**
	 * Marks the card that belongs to the current day.
	 */
	function highlightCard() {
		cards.forEach( function ( card, index ) {
			card.classList.toggle( 'is-active', index === activeIndex );
		} );
	}

	/**
	 * Moves the camera to a day.
	 *
	 * @param {number} index Day index.
	 */
	function focusDay( index ) {
		var segment = route.segments[ index ];

		if ( ! segment ) {
			return;
		}

		var points = segment.day.track && segment.day.track.length
			? segment.day.track
			: ( segment.day.pins || [] ).map( function ( pin ) {
				return pin.lngLat;
			} );

		if ( ! points.length ) {
			return;
		}

		var bounds = points.reduce( function ( acc, point ) {
			return acc.extend( point );
		}, new window.maplibregl.LngLatBounds( points[ 0 ], points[ 0 ] ) );

		map.fitBounds( bounds, {
			padding: padding(),
			maxZoom: 11.5,
			duration: reduceMotion ? 0 : 1600,
			essential: true
		} );
	}

	/**
	 * Fits the whole trip into view.
	 *
	 * @param {number} duration Animation duration.
	 */
	function fitAll( duration ) {
		var points = route.coordinates.length
			? route.coordinates
			: ( data.bounds || [] );

		if ( ! points.length ) {
			return;
		}

		var bounds = points.reduce( function ( acc, point ) {
			return acc.extend( point );
		}, new window.maplibregl.LngLatBounds( points[ 0 ], points[ 0 ] ) );

		map.fitBounds( bounds, {
			padding: padding(),
			duration: reduceMotion ? 0 : duration || 0,
			maxZoom: 9
		} );
	}

	/**
	 * Keeps the route clear of the overlay and, on desktop, of the text column.
	 *
	 * @return {Object}
	 */
	function padding() {
		var wide = window.matchMedia( '(min-width: 1024px)' ).matches;

		return {
			top: wide ? 90 : 70,
			right: wide ? 90 : 40,
			bottom: wide ? 140 : 120,
			left: wide ? 90 : 40
		};
	}

	// ---------------------------------------------------------------------
	// Interaction
	// ---------------------------------------------------------------------

	/**
	 * Hands the camera to the visitor as soon as they touch the map.
	 */
	function releaseCamera() {
		if ( ! followCamera ) {
			return;
		}

		followCamera = false;
		syncFollowButton();
	}

	/**
	 * Reflects the follow state in the button.
	 */
	function syncFollowButton() {
		if ( ! hud.follow ) {
			return;
		}

		var label = hud.follow.querySelector( 'span' );

		hud.follow.classList.toggle( 'is-active', followCamera );
		hud.follow.setAttribute( 'aria-pressed', followCamera ? 'true' : 'false' );

		if ( label ) {
			label.textContent = followCamera ? data.i18n.following : data.i18n.free;
		}
	}

	[ 'dragstart', 'zoomstart', 'rotatestart', 'pitchstart' ].forEach( function ( event ) {
		map.on( event, function ( payload ) {
			// Only visitor gestures release the camera, not our own animations.
			if ( payload && payload.originalEvent ) {
				releaseCamera();
			}
		} );
	} );

	if ( hud.follow ) {
		hud.follow.addEventListener( 'click', function () {
			followCamera = ! followCamera;
			syncFollowButton();

			if ( followCamera ) {
				focusDay( activeIndex );
			}
		} );
	}

	cards.forEach( function ( card, index ) {
		card.addEventListener( 'mouseenter', function () {
			if ( followCamera || index === activeIndex ) {
				return;
			}

			card.classList.add( 'is-hovered' );
		} );

		card.addEventListener( 'mouseleave', function () {
			card.classList.remove( 'is-hovered' );
		} );
	} );

	var ticking = false;

	/**
	 * Throttles scroll work to one update per frame.
	 */
	function onScroll() {
		if ( ticking ) {
			return;
		}

		ticking = true;

		window.requestAnimationFrame( function () {
			update( false );
			ticking = false;
		} );
	}

	window.addEventListener( 'scroll', onScroll, { passive: true } );

	window.addEventListener( 'resize', function () {
		measure();
		update( true );
	} );

	window.addEventListener( 'load', function () {
		measure();
		update( true );
	} );

	if ( window.ResizeObserver ) {
		var observer = new window.ResizeObserver( function () {
			measure();
		} );

		observer.observe( scroller );
	}

	measure();
	syncFollowButton();
} )();
