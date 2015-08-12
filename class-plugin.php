<?php
/*
Plugin Name: Combinator
Description: Automatically combines all your local scripts and styles for less HTTP requests and faster page loading times.
Author: Gambit Technologies
Version: 1.0
Author URI: http://gambit.ph
Plugin URI: http://codecanyon.net/user/gambittech/portfolio
Text Domain: combinator
Domain Path: /languages
SKU: COMBINATOR
*/


/******************************************************************************
 *
 * IMPORTANT: DO NOT PUT SHORTCODE CODE IN THIS FILE, DO THAT INSIDE `class-combinator.php`
 * ALL CODE IN THIS MAIN FILE IS ONLY FOR BASIC PLUGIN FUNCTIONALITY LIKE ADDING
 * ADMIN POINTERS TO SHOW ON ACTIVATION.
 *
 ******************************************************************************/


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


// Identifies the plugin itself. If already existing, it will not redefine itself.
defined( 'VERSION_GAMBIT_COMBINATOR' ) or define( 'VERSION_GAMBIT_COMBINATOR', '1.0' );

// Initializes the plugin translations.
defined( 'GAMBIT_COMBINATOR' ) or define( 'GAMBIT_COMBINATOR', 'combinator' );
defined( 'GAMBIT_COMBINATOR_PATH' ) or define( 'GAMBIT_COMBINATOR_PATH', __FILE__ );

// Plugin automatic updates
require_once( 'class-admin-license.php' );

// This is the main plugin functionality
require_once( 'class-combinator.php' );


// Initializes plugin class.
if ( ! class_exists( 'GambitCombinatorPlugin' ) ) {
	
	class GambitCombinatorPlugin {

		/**
		 * Hook into WordPress
		 *
		 * @return	void
		 * @since	1.0
		 */
		function __construct() {

			// Admin pointer reminders for automatic updates
			require_once( 'class-admin-pointers.php' );
			if ( class_exists( 'GambitAdminPointers' ) ) {
				new GambitAdminPointers( array (
					'pointer_name' => 'GambitCombinatorPlugin', // This should also be placed in uninstall.php
					'header' => __( 'Automatic Updates', GAMBIT_COMBINATOR ),
					'body' => __( 'Keep Combinator updated by entering your purchase code here.', GAMBIT_COMBINATOR ),
				) );
			}

			// Our translations
			add_action( 'plugins_loaded', array( $this, 'loadTextDomain' ), 1 );

			// Gambit links
			add_filter( 'plugin_row_meta', array( $this, 'pluginLinks' ), 10, 2 );
		}


		/**
		 * Loads the translations
		 *
		 * @return	void
		 * @since	1.0
		 */
		public function loadTextDomain() {
			load_plugin_textdomain( GAMBIT_COMBINATOR, false, basename( dirname( __FILE__ ) ) . '/languages/' );
		}


		/**
		 * Adds plugin links
		 *
		 * @access	public
		 * @param	array $plugin_meta The current array of links
		 * @param	string $plugin_file The plugin file
		 * @return	array The current array of links together with our additions
		 * @since	1.0
		 **/
		public function pluginLinks( $plugin_meta, $plugin_file ) {
			if ( $plugin_file == plugin_basename( __FILE__ ) ) {
				$pluginData = get_plugin_data( __FILE__ );

				$plugin_meta[] = sprintf( "<a href='%s' target='_blank'>%s</a>",
					"http://support.gambit.ph?utm_source=" . urlencode( $pluginData['Name'] ) . "&utm_medium=plugin_link",
					__( "Get Customer Support", GAMBIT_COMBINATOR )
				);
				$plugin_meta[] = sprintf( "<a href='%s' target='_blank'>%s</a>",
					"https://gambit.ph/plugins?utm_source=" . urlencode( $pluginData['Name'] ) . "&utm_medium=plugin_link",
					__( "Get More Plugins", GAMBIT_COMBINATOR )
				);
			}
			return $plugin_meta;
		}


	}

	new GambitCombinatorPlugin();
}