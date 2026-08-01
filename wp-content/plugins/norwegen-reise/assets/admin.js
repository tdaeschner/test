/**
 * Editor helpers for a travel day: repeatable flags plus a map to place them.
 */
( function () {
	'use strict';

	var config = window.nbAdmin || {};
	var list = document.getElementById( 'nb-pins-list' );
	var addButton = document.getElementById( 'nb-pins-add' );
	var template = document.getElementById( 'nb-pin-template' );
	var mapNode = document.getElementById( 'nb-track-map' );

	var map = null;
	var markers = [];
	var pickingRow = null;

	if ( ! list || ! template ) {
		return;
	}

	/**
	 * Next free index for a new row.
	 *
	 * @return {number}
	 */
	function nextIndex() {
		var highest = -1;

		list.querySelectorAll( '.nb-pin' ).forEach( function ( row ) {
			var index = parseInt( row.getAttribute( 'data-index' ), 10 );

			if ( ! isNaN( index ) && index > highest ) {
				highest = index;
			}
		} );

		return highest + 1;
	}

	/**
	 * Renumbers the visible badges after add/remove.
	 */
	function renumber() {
		list.querySelectorAll( '.nb-pin' ).forEach( function ( row, index ) {
			var badge = row.querySelector( '.nb-pin__number' );

			if ( badge ) {
				badge.textContent = index + 1;
			}
		} );
	}

	/**
	 * Reads the current rows as plain objects.
	 *
	 * @return {Array}
	 */
	function readRows() {
		var pins = [];

		list.querySelectorAll( '.nb-pin' ).forEach( function ( row ) {
			var lat = parseFloat( ( row.querySelector( '.nb-pin__lat' ).value || '' ).replace( ',', '.' ) );
			var lng = parseFloat( ( row.querySelector( '.nb-pin__lng' ).value || '' ).replace( ',', '.' ) );

			if ( isNaN( lat ) || isNaN( lng ) ) {
				return;
			}

			pins.push( {
				row: row,
				lat: lat,
				lng: lng,
				title: row.querySelector( 'input[name$="[title]"]' ).value
			} );
		} );

		return pins;
	}

	/**
	 * Adds a new empty row.
	 */
	function addRow() {
		var html = template.innerHTML.replace( /__index__/g, nextIndex() );
		var wrapper = document.createElement( 'div' );

		wrapper.innerHTML = html.trim();

		var row = wrapper.firstElementChild;

		list.appendChild( row );
		renumber();

		var titleField = row.querySelector( 'input[name$="[title]"]' );

		if ( titleField ) {
			titleField.focus();
		}

		// A fresh flag starts in the middle of the current view, so it is
		// immediately visible and can be dragged into place.
		if ( map ) {
			var center = map.getCenter();

			row.querySelector( '.nb-pin__lat' ).value = center.lat.toFixed( 6 );
			row.querySelector( '.nb-pin__lng' ).value = center.lng.toFixed( 6 );
			syncMarkers();
		}
	}

	/**
	 * Handles clicks inside the flag list.
	 *
	 * @param {Event} event Click event.
	 */
	function onListClick( event ) {
		var row = event.target.closest( '.nb-pin' );

		if ( ! row ) {
			return;
		}

		if ( event.target.closest( '.nb-pin__remove' ) ) {
			event.preventDefault();

			if ( window.confirm( config.i18n.confirmRow ) ) {
				row.remove();
				renumber();
				syncMarkers();
			}

			return;
		}

		if ( event.target.closest( '.nb-pin__pick' ) ) {
			event.preventDefault();
			startPicking( row, event.target.closest( '.nb-pin__pick' ) );

			return;
		}

		if ( event.target.closest( '.nb-pin__image-select' ) ) {
			event.preventDefault();
			selectImage( row );

			return;
		}

		if ( event.target.closest( '.nb-pin__image-clear' ) ) {
			event.preventDefault();
			row.querySelector( '.nb-pin__image' ).value = '0';
			row.querySelector( '.nb-pin__preview' ).innerHTML = '';
		}
	}

	/**
	 * Opens the media library for a flag image.
	 *
	 * @param {HTMLElement} row Flag row.
	 */
	function selectImage( row ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		var frame = window.wp.media( {
			title: config.i18n.chooseImage,
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

			row.querySelector( '.nb-pin__image' ).value = attachment.id;
			row.querySelector( '.nb-pin__preview' ).innerHTML = '<img src="' + url + '" alt="" />';
		} );

		frame.open();
	}

	/**
	 * Puts the map into "click to place" mode for one row.
	 *
	 * @param {HTMLElement} row    Flag row.
	 * @param {HTMLElement} button The button that was pressed.
	 */
	function startPicking( row, button ) {
		if ( ! map ) {
			return;
		}

		if ( pickingRow ) {
			stopPicking();
		}

		pickingRow = { row: row, button: button, label: button.textContent };
		button.textContent = config.i18n.pickCancel;
		mapNode.classList.add( 'is-picking' );
		mapNode.scrollIntoView( { behavior: 'smooth', block: 'center' } );

		button.addEventListener( 'click', stopPickingOnce, { once: true } );
	}

	/**
	 * Cancels picking when the same button is pressed again.
	 *
	 * @param {Event} event Click event.
	 */
	function stopPickingOnce( event ) {
		event.preventDefault();
		event.stopPropagation();
		stopPicking();
	}

	/**
	 * Leaves picking mode.
	 */
	function stopPicking() {
		if ( ! pickingRow ) {
			return;
		}

		pickingRow.button.textContent = pickingRow.label;
		mapNode.classList.remove( 'is-picking' );
		pickingRow = null;
	}

	/**
	 * Rebuilds the markers from the current rows.
	 */
	function syncMarkers() {
		if ( ! map ) {
			return;
		}

		markers.forEach( function ( marker ) {
			marker.remove();
		} );
		markers = [];

		readRows().forEach( function ( pin, index ) {
			var element = document.createElement( 'div' );

			element.className = 'nb-admin-marker';
			element.textContent = index + 1;
			element.title = pin.title;

			var marker = new window.maplibregl.Marker( { element: element, draggable: true, anchor: 'bottom' } )
				.setLngLat( [ pin.lng, pin.lat ] )
				.addTo( map );

			marker.on( 'dragend', function () {
				var position = marker.getLngLat();

				pin.row.querySelector( '.nb-pin__lat' ).value = position.lat.toFixed( 6 );
				pin.row.querySelector( '.nb-pin__lng' ).value = position.lng.toFixed( 6 );
				pin.row.classList.add( 'is-touched' );
			} );

			element.addEventListener( 'click', function () {
				pin.row.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				pin.row.classList.add( 'is-highlighted' );
				window.setTimeout( function () {
					pin.row.classList.remove( 'is-highlighted' );
				}, 1200 );
			} );

			markers.push( marker );
		} );
	}

	/**
	 * Boots the preview map.
	 */
	function initMap() {
		if ( ! mapNode || ! window.maplibregl || ! window.NBMapStyle ) {
			return;
		}

		var track = config.track || [];
		var center = track.length ? track[ Math.floor( track.length / 2 ) ] : config.fallback;

		map = window.NBMapStyle.create( mapNode, Object.assign( {}, config.style, { center: center } ), {
			mapOptions: { zoom: track.length ? 8 : 4.4 }
		} );

		map.addControl( new window.maplibregl.NavigationControl( { visualizePitch: true } ), 'top-right' );

		map.on( 'load', function () {
			var segments = config.segments && config.segments.length
				? config.segments
				: ( track.length ? [ { points: track, color: '#4fe0b0', name: '' } ] : [] );

			// Jede Aufzeichnung bekommt ihre eigene Farbe, passend zur Tabelle.
			segments.forEach( function ( segment, index ) {
				if ( ! segment.points || segment.points.length < 2 ) {
					return;
				}

				var id = 'nb-segment-' + index;

				map.addSource( id, {
					type: 'geojson',
					data: window.NBMapStyle.lineFeature( segment.points )
				} );

				map.addLayer( {
					id: id + '-line',
					type: 'line',
					source: id,
					layout: { 'line-cap': 'round', 'line-join': 'round' },
					paint: {
						'line-color': segment.color || '#4fe0b0',
						'line-width': 4,
						'line-opacity': 0.9
					}
				} );
			} );

			if ( track.length ) {
				window.NBMapStyle.fitToPoints( map, track, { padding: 50 } );
			}

			syncMarkers();
		} );

		map.on( 'click', function ( event ) {
			if ( ! pickingRow ) {
				return;
			}

			pickingRow.row.querySelector( '.nb-pin__lat' ).value = event.lngLat.lat.toFixed( 6 );
			pickingRow.row.querySelector( '.nb-pin__lng' ).value = event.lngLat.lng.toFixed( 6 );
			stopPicking();
			syncMarkers();
		} );
	}

	list.addEventListener( 'click', onListClick );
	list.addEventListener( 'change', function ( event ) {
		if ( event.target.classList.contains( 'nb-pin__lat' ) || event.target.classList.contains( 'nb-pin__lng' ) ) {
			syncMarkers();
		}
	} );

	if ( addButton ) {
		addButton.addEventListener( 'click', addRow );
	}

	renumber();
	initMap();
} )();
