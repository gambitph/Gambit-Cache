<?php
/**
 * The main plugin file
 *
 * @package	Gambit Cache
 */

/**
Plugin Name: Gambit Cache
Description: All the perks of caching plus more.. pain-free
Author: Gambit Technologies
Version: 0.1
Author URI: http://gambit.ph
Plugin URI: https://wordpress.org/plugins/gambit-cache/
Text Domain: gambit_cache
Domain Path: /languages
 */

/**
 * ***************************************************************************
 *
 * IMPORTANT: DO NOT PUT SHORTCODE CODE IN THIS FILE, DO THAT INSIDE `class-gambit_cache.php`
 * ALL CODE IN THIS MAIN FILE IS ONLY FOR BASIC PLUGIN FUNCTIONALITY LIKE ADDING
 * ADMIN POINTERS TO SHOW ON ACTIVATION.
 ******************************************************************************/


if ( ! defined( 'ABSPATH' ) ) { exit; // Exit if accessed directly.
}

// Identifies the plugin itself. If already existing, it will not redefine itself.
defined( 'VERSION_GAMBIT_CACHE' ) or define( 'VERSION_GAMBIT_CACHE', '0.1' );

// Initializes the plugin translations.
defined( 'GAMBIT_CACHE' ) or define( 'GAMBIT_CACHE', 'gambit_cache' );
defined( 'GAMBIT_CACHE_PATH' ) or define( 'GAMBIT_CACHE_PATH', __FILE__ );

// This is the main plugin functionality.
require_once( 'class-gambit-cache.php' );


// Initializes plugin class.
if ( ! class_exists( 'GambitCachePlugin' ) ) {

	/**
	 * Main plugin class
	 */
	class GambitCachePlugin {

		/**
		 * Hook into WordPress
		 *
		 * @return	void
		 * @since	0.1
		 */
		function __construct() {

			// Our translations.
			add_action( 'plugins_loaded', array( $this, 'load_text_domain' ), 1 );

			// Gambit links.
			add_filter( 'plugin_row_meta', array( $this, 'plugin_links' ), 10, 2 );
		}


		/**
		 * Loads the translations
		 *
		 * @return	void
		 * @since	0.1
		 */
		public function load_text_domain() {
			load_plugin_textdomain( GAMBIT_CACHE, false, basename( dirname( __FILE__ ) ) . '/languages/' );
		}


		/**
		 * Adds plugin links
		 *
		 * @access	public
		 * @param	array  $plugin_meta The current array of links.
		 * @param	string $plugin_file The plugin file.
		 * @return	array The current array of links together with our additions
		 * @since	0.1
		 **/
		public function plugin_links( $plugin_meta, $plugin_file ) {
			if ( plugin_basename( __FILE__ ) === $plugin_file ) {
				$pluginData = get_plugin_data( __FILE__ );

				// $plugin_meta[] = sprintf( "<a href='%s' target='_blank'>%s</a>",
				// 	'http://support.gambit.ph?utm_source=' . urlencode( $pluginData['Name'] ) . '&utm_medium=plugin_link',
				// 	__( 'Get Customer Support', GAMBIT_CACHE )
				// );
				$plugin_meta[] = sprintf( "<a href='%s' target='_blank'>%s</a>",
					'https://gambit.ph/plugins?utm_source=' . urlencode( $pluginData['Name'] ) . '&utm_medium=plugin_link',
					__( 'Get More Plugins', GAMBIT_CACHE )
				);
			}
			return $plugin_meta;
		}


	}

	new GambitCachePlugin();
}
