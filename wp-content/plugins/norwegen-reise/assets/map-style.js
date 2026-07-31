/**
 * Builds MapLibre styles from the plugin settings and creates map instances.
 *
 * Shared by the admin picker and the theme so both always look the same.
 */
( function ( window ) {
	'use strict';

	var NBMapStyle = {};

	/**
	 * Returns either a style URL (custom vector style) or a raster style object.
	 *
	 * @param {Object} config Style configuration from PHP.
	 * @return {Object|string}
	 */
	NBMapStyle.build = function ( config ) {
		config = config || {};

		if ( config.styleUrl ) {
			return config.styleUrl;
		}

		var tileSize = config.tileSize || 256;

		return {
			version: 8,
			sources: {
				basemap: {
					type: 'raster',
					tiles: config.tiles || [],
					tileSize: tileSize,
					maxzoom: config.maxzoom || 19,
					attribution: config.attribution || ''
				}
			},
			layers: [
				{
					id: 'background',
					type: 'background',
					paint: { 'background-color': config.dark ? '#070b12' : '#eef2f7' }
				},
				{
					id: 'basemap',
					type: 'raster',
					source: 'basemap',
					paint: { 'raster-opacity': 1 }
				}
			]
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
