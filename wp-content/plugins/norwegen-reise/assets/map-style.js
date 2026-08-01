/**
 * Builds MapLibre styles from the plugin settings and creates map instances.
 *
 * Shared by the admin picker and the theme so both always look the same.
 */
( function ( window ) {
	'use strict';

	var NBMapStyle = {};
	var STORAGE_KEY = 'nbBasemap';

	/**
	 * The base maps a config offers, always as a list.
	 *
	 * @param {Object} config Style configuration from PHP.
	 * @return {Array}
	 */
	function basemapsOf( config ) {
		if ( config.basemaps && config.basemaps.length ) {
			return config.basemaps;
		}

		// Ältere Konfiguration ohne Liste: der eine Stil als Liste verpackt.
		return [
			{
				key: config.basemap || 'dark',
				label: '',
				tiles: config.tiles || [],
				tileSize: config.tileSize || 256,
				maxzoom: config.maxzoom || 19,
				attribution: config.attribution || '',
				dark: !! config.dark
			}
		];
	}

	/**
	 * The base map to start with: the visitor's last choice, else the default.
	 *
	 * @param {Object} config Style configuration.
	 * @return {string} Base map key.
	 */
	NBMapStyle.activeKey = function ( config ) {
		var maps = basemapsOf( config );
		var stored = null;

		try {
			stored = window.localStorage.getItem( STORAGE_KEY );
		} catch ( error ) {
			// Privater Modus o. Ä. - dann eben ohne Erinnerung.
			stored = null;
		}

		for ( var i = 0; i < maps.length; i++ ) {
			if ( maps[ i ].key === stored ) {
				return stored;
			}
		}

		return config.basemap || maps[ 0 ].key;
	};

	/**
	 * Returns either a style URL (custom vector style) or a raster style object.
	 *
	 * Every offered base map becomes its own source and layer. Switching only
	 * toggles visibility, so the route and its markers stay untouched.
	 *
	 * @param {Object} config Style configuration from PHP.
	 * @return {Object|string}
	 */
	NBMapStyle.build = function ( config ) {
		config = config || {};

		if ( config.styleUrl ) {
			return config.styleUrl;
		}

		var maps = basemapsOf( config );
		var active = NBMapStyle.activeKey( config );
		var sources = {};
		var layers = [
			{
				id: 'background',
				type: 'background',
				paint: { 'background-color': backgroundFor( maps, active ) }
			}
		];

		maps.forEach( function ( basemap ) {
			sources[ 'basemap-' + basemap.key ] = {
				type: 'raster',
				tiles: basemap.tiles,
				tileSize: basemap.tileSize || 256,
				maxzoom: basemap.maxzoom || 19,
				attribution: basemap.attribution || ''
			};

			layers.push( {
				id: 'basemap-' + basemap.key,
				type: 'raster',
				source: 'basemap-' + basemap.key,
				// Unsichtbare Ebenen laden keine Kacheln und tauchen auch nicht
				// im Quellennachweis auf.
				layout: { visibility: basemap.key === active ? 'visible' : 'none' },
				paint: { 'raster-opacity': 1 }
			} );
		} );

		return {
			version: 8,
			sources: sources,
			layers: layers
		};
	};

	/**
	 * Backdrop colour shown while tiles are still loading.
	 *
	 * @param {Array}  maps Base maps.
	 * @param {string} key  Active key.
	 * @return {string}
	 */
	function backgroundFor( maps, key ) {
		for ( var i = 0; i < maps.length; i++ ) {
			if ( maps[ i ].key === key ) {
				return maps[ i ].dark ? '#070b12' : '#eef2f7';
			}
		}

		return '#070b12';
	}

	/**
	 * Switches the visible base map.
	 *
	 * @param {maplibregl.Map} map    Map instance.
	 * @param {Object}         config Style configuration.
	 * @param {string}         key    Base map key.
	 */
	NBMapStyle.setBasemap = function ( map, config, key ) {
		var maps = basemapsOf( config );
		var active = null;

		maps.forEach( function ( basemap ) {
			var layer = 'basemap-' + basemap.key;

			if ( ! map.getLayer( layer ) ) {
				return;
			}

			map.setLayoutProperty( layer, 'visibility', basemap.key === key ? 'visible' : 'none' );

			if ( basemap.key === key ) {
				active = basemap;
			}
		} );

		if ( ! active ) {
			return;
		}

		if ( map.getLayer( 'background' ) ) {
			map.setPaintProperty( 'background', 'background-color', active.dark ? '#070b12' : '#eef2f7' );
		}

		// Die noch nicht gefahrene Strecke muss sich gegen den jeweiligen
		// Untergrund behaupten: dezent auf ruhigen Karten, deutlich auf
		// Luftbildern und topografischen Karten.
		var baseColor = active.dark ? ( active.busy ? '#dce6f2' : '#5c7a99' ) : '#2b3a4a';
		var baseOpacity = active.busy ? 0.55 : ( active.dark ? 0.28 : 0.5 );

		if ( map.getLayer( 'nb-route-base' ) ) {
			map.setPaintProperty( 'nb-route-base', 'line-color', baseColor );
			map.setPaintProperty( 'nb-route-base', 'line-opacity', baseOpacity );
			map.setPaintProperty( 'nb-route-base', 'line-width', active.busy ? 2.8 : 2 );
		}

		if ( map.getLayer( 'nb-planned-line' ) ) {
			map.setPaintProperty( 'nb-planned-line', 'line-color', baseColor );
			map.setPaintProperty( 'nb-planned-line', 'line-opacity', Math.min( 0.6, baseOpacity + 0.07 ) );
		}

		[ 'nb-start-dot', 'nb-day-start-dot', 'nb-day-end-dot' ].forEach( function ( layer ) {
			if ( map.getLayer( layer ) ) {
				map.setPaintProperty( layer, 'circle-stroke-color', active.dark ? 'rgba(7, 11, 18, 0.85)' : 'rgba(255, 255, 255, 0.9)' );
			}
		} );

		map.getContainer().classList.toggle( 'nb-map-is-light', ! active.dark );

		try {
			window.localStorage.setItem( STORAGE_KEY, key );
		} catch ( error ) {
			// Nicht schlimm - dann merkt sich der Browser die Wahl eben nicht.
		}
	};

	/**
	 * A MapLibre control that lets visitors pick the base map.
	 *
	 * @param {Object}   config   Style configuration.
	 * @param {Object}   labels   UI labels.
	 * @param {Function} onChange Called with the chosen key.
	 * @return {Object} MapLibre IControl.
	 */
	NBMapStyle.basemapControl = function ( config, labels, onChange ) {
		labels = labels || {};

		var maps = basemapsOf( config );

		return {
			onAdd: function ( map ) {
				var container = document.createElement( 'div' );

				container.className = 'maplibregl-ctrl maplibregl-ctrl-group nb-basemap-ctrl';

				var toggle = document.createElement( 'button' );

				toggle.type = 'button';
				toggle.className = 'nb-basemap-ctrl__toggle';
				toggle.setAttribute( 'aria-expanded', 'false' );
				toggle.setAttribute( 'aria-label', labels.title || 'Kartenstil' );
				toggle.title = labels.title || 'Kartenstil';
				toggle.innerHTML =
					'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" ' +
					'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
					'<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/></svg>';

				var panel = document.createElement( 'div' );

				panel.className = 'nb-basemap-ctrl__panel';
				panel.hidden = true;

				var heading = document.createElement( 'p' );

				heading.className = 'nb-basemap-ctrl__heading';
				heading.textContent = labels.title || 'Kartenstil';
				panel.appendChild( heading );

				var active = NBMapStyle.activeKey( config );

				maps.forEach( function ( basemap ) {
					var option = document.createElement( 'button' );

					option.type = 'button';
					option.className = 'nb-basemap-ctrl__option';
					option.dataset.basemap = basemap.key;
					option.setAttribute( 'aria-pressed', basemap.key === active ? 'true' : 'false' );
					option.innerHTML =
						'<span class="nb-basemap-ctrl__swatch" style="background:' +
						( basemap.swatch || '#333' ) +
						'"></span><span></span>';
					option.lastChild.textContent = basemap.label || basemap.key;

					option.addEventListener( 'click', function () {
						NBMapStyle.setBasemap( map, config, basemap.key );

						panel.querySelectorAll( '.nb-basemap-ctrl__option' ).forEach( function ( other ) {
							other.setAttribute( 'aria-pressed', other === option ? 'true' : 'false' );
						} );

						close();

						if ( onChange ) {
							onChange( basemap.key );
						}
					} );

					panel.appendChild( option );
				} );

				/**
				 * Closes the panel.
				 */
				function close() {
					panel.hidden = true;
					toggle.setAttribute( 'aria-expanded', 'false' );
					container.classList.remove( 'is-open' );
				}

				toggle.addEventListener( 'click', function ( event ) {
					event.stopPropagation();

					var open = panel.hidden;

					panel.hidden = ! open;
					toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
					container.classList.toggle( 'is-open', open );
				} );

				document.addEventListener( 'click', function ( event ) {
					if ( ! container.contains( event.target ) ) {
						close();
					}
				} );

				document.addEventListener( 'keydown', function ( event ) {
					if ( 'Escape' === event.key ) {
						close();
					}
				} );

				container.appendChild( toggle );
				container.appendChild( panel );

				this._container = container;

				return container;
			},

			onRemove: function () {
				if ( this._container && this._container.parentNode ) {
					this._container.parentNode.removeChild( this._container );
				}
			}
		};
	};

	/**
	 * Creates a map instance with sane defaults for this project.
	 *
	 * @param {HTMLElement} container Target element.
	 * @param {Object}      config    Style configuration.
	 * @param {Object}      options   Extra MapLibre options.
	 * @return {maplibregl.Map}
	 */
	NBMapStyle.create = function ( container, config, options ) {
		options = options || {};
		config = config || {};

		var map = new window.maplibregl.Map(
			Object.assign(
				{
					container: container,
					style: NBMapStyle.build( config ),
					center: config.center || [ 8.4689, 62.472 ],
					zoom: config.zoom || 4.4,
					attributionControl: false,
					cooperativeGestures: !! options.cooperativeGestures,
					maxPitch: 70,
					dragRotate: true,
					hash: false
				},
				options.mapOptions || {}
			)
		);

		map.addControl(
			new window.maplibregl.AttributionControl( { compact: true } ),
			'bottom-right'
		);

		var maps = basemapsOf( config );
		var active = NBMapStyle.activeKey( config );

		for ( var i = 0; i < maps.length; i++ ) {
			if ( maps[ i ].key === active && ! maps[ i ].dark ) {
				container.classList.add( 'nb-map-is-light' );
			}
		}

		if ( config.terrain && config.maptilerKey ) {
			map.on( 'load', function () {
				if ( map.getSource( 'nb-terrain' ) ) {
					return;
				}

				map.addSource( 'nb-terrain', {
					type: 'raster-dem',
					url:
						'https://api.maptiler.com/tiles/terrain-rgb-v2/tiles.json?key=' +
						encodeURIComponent( config.maptilerKey ),
					tileSize: 256
				} );

				map.setTerrain( { source: 'nb-terrain', exaggeration: 1.25 } );
			} );
		}

		return map;
	};

	/**
	 * Adds the base map switcher when more than one style is on offer.
	 *
	 * @param {maplibregl.Map} map      Map instance.
	 * @param {Object}         config   Style configuration.
	 * @param {Object}         labels   UI labels.
	 * @param {string}         position Control position.
	 */
	NBMapStyle.addBasemapControl = function ( map, config, labels, position ) {
		if ( config.styleUrl || basemapsOf( config ).length < 2 ) {
			return;
		}

		map.addControl( NBMapStyle.basemapControl( config, labels ), position || 'top-right' );

		// Die gespeicherte Wahl auch auf die Routenfarben anwenden.
		map.on( 'load', function () {
			NBMapStyle.setBasemap( map, config, NBMapStyle.activeKey( config ) );
		} );
	};

	/**
	 * Turns a list of [lng, lat] pairs into a GeoJSON LineString feature.
	 *
	 * @param {Array} coordinates Track points.
	 * @return {Object}
	 */
	NBMapStyle.lineFeature = function ( coordinates ) {
		return {
			type: 'Feature',
			properties: {},
			geometry: {
				type: 'LineString',
				coordinates: coordinates && coordinates.length ? coordinates : []
			}
		};
	};

	/**
	 * Fits the map to a list of points with a comfortable padding.
	 *
	 * @param {maplibregl.Map} map     Map instance.
	 * @param {Array}          points  List of [lng, lat] pairs.
	 * @param {Object}         options Fit options.
	 */
	NBMapStyle.fitToPoints = function ( map, points, options ) {
		if ( ! points || ! points.length ) {
			return;
		}

		var bounds = points.reduce( function ( acc, point ) {
			return acc.extend( point );
		}, new window.maplibregl.LngLatBounds( points[ 0 ], points[ 0 ] ) );

		map.fitBounds(
			bounds,
			Object.assign( { padding: 60, duration: 0, maxZoom: 13 }, options || {} )
		);
	};

	window.NBMapStyle = NBMapStyle;
} )( window );
