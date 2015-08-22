<?php
	
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'GambitCacheAdminPage' ) ) {

class GambitCacheAdminPage {

	function __construct() {
		
		add_action( 'admin_enqueue_scripts', array( $this, 'adminEnqueueScripts' ) );

		// Initializes settings panel
		add_filter( 'plugin_action_links', array( $this, 'pluginSettingsLink' ), 10, 2 );
		add_action( 'tf_create_options', array( $this, 'createAdminOptions' ) );
		
		add_action( 'wp_ajax_user_clear_all_caches', array( $this, 'ajaxClearAllCaches' ) );
		add_action( 'tf_save_options_' . GAMBIT_COMBINATOR, array( $this, 'clearAllCaches' ) );


		// EWWW Image Optimizer compatibility, clear the cache when settings are saved
		if ( ! empty( $_POST['option_page'] ) ) {
			if ( $_POST['option_page'] == 'ewww_image_optimizer_options' ) {
				add_action( 'admin_init', array( $this, 'clearImageCaches' ) );
			}
		}
		
	}
	
	public function adminEnqueueScripts() {
		wp_enqueue_style( __CLASS__, plugins_url( 'combinator/css/admin.css', GAMBIT_COMBINATOR_PATH ) );
	}
	
	public function ajaxClearAllCaches() {
		if ( ! $this->clearAllCaches() ) {
			wp_send_json_error( __( 'Could not clear all caches', GAMBIT_COMBINATOR ) );
		}
		wp_send_json_success( __( 'All caches cleared', GAMBIT_COMBINATOR ) );
	}
	
	
	public function clearImageCaches() {
		$hasError = false;
		
		// Clear page cache
		if ( ! GambitCachePageCache::clearPageCache() ) {
			$hasError = true;
		}
		
		// Clear sprite cache
	    if ( ! GambitCacheSprite::clearCache() ) {
	    	$hasError = true;
	    }
		
		return ! $hasError;
		
	}
	
	public function clearAllCaches() {
		
		$hasError = false;
		
		// Clear object cache
		wp_cache_flush();
		
		// Clear page cache
		if ( ! GambitCachePageCache::clearPageCache() ) {
			$hasError = true;
		}
		
		// Clear minify cache
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%' AND option_name LIKE '%cmbntr%'" );
	    if ( ! GambitCacheMinify::clearMinifyCache() ) {
	    	$hasError = true;
	    }
		
		// Clear sprite cache
	    if ( ! GambitCacheSprite::clearCache() ) {
	    	$hasError = true;
	    }
		
		return ! $hasError;
	}

	/**
	 * Adds plugin settings link
	 *
	 * @access	public
	 * @param	array $links The current set of links
	 * @since	1.0
	 **/
	public function pluginSettingsLink( $links, $pluginFile ) {
		
		if ( ! class_exists( 'TitanFramework' ) ) {
			return $links;
		}
	
		// Get this plugin's base folder
		static $plugin;
		if ( ! isset( $plugin ) ) {
			$plugin = plugin_basename( __FILE__ );
			$plugin = trailingslashit( dirname( $plugin ) );
		}
		
		// If we are in the links of our plugin, add the settings link
		if ( stripos( $pluginFile, $plugin ) !== false ) {
		
			$settingsURL = admin_url( 'options-general.php?page=' . GAMBIT_COMBINATOR );

			array_unshift( $links, '<a href="' . $settingsURL . '">' . __( 'Settings', GAMBIT_COMBINATOR ) . '</a>' );
		
		}
	
		return $links;
	}


		/**
		 * Create the Titan admin panel and other settings
		 *
		 * @return	void
		 * @since	1.0
		 */
		public function createAdminOptions() {
			$titan = TitanFramework::getInstance( GAMBIT_COMBINATOR );
			
			// Check if get_plugins() function exists. This is required on the front end of the
			// site, since it is in a file that is normally only loaded in the admin.
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			
			/**
			 * Get list of active plugins
			 */
			$activePluginSlugs = get_option( 'active_plugins' );
			$allPlugins = get_plugins();
			
			$blacklistedPlugins = apply_filters( 'gc_blacklisted_plugins', array() );
			
			$blackListDefault = array();
			$pluginOptions = array();
			foreach ( $activePluginSlugs as $slug ) {
				if ( empty( $allPlugins[ $slug ] ) ) {
					continue;
				}
				
				if ( stripos( $allPlugins[ $slug ]['Name'], 'combinator' ) !== false ) {
					continue;
				}
				
				if ( in_array( $allPlugins[ $slug ]['Name'], $blacklistedPlugins ) ) {
					$blackListDefault[] = $slug;
				}
				$pluginOptions[ $slug ] = $allPlugins[ $slug ]['Name'];
			}


			$adminPanel = $titan->createAdminPanel( array(
			    'name' => 'Combinator',
				'id' => GAMBIT_COMBINATOR,
			    'parent' => 'options-general.php',
			) );
			
			$cachingTab = $adminPanel->createTab( array(
			    'name' => __( 'Caching Settings', GAMBIT_COMBINATOR ),
			) );

			$cachingTab->createOption( array(
				'name' => __( 'Cache Control', GAMBIT_COMBINATOR ),
				'type' => 'heading',
				'desc' => __( 'Clear, enable and disable caches & minification from here.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
				'name' => __( 'Clear Page Cache', GAMBIT_COMBINATOR ),
				'type' => 'ajax-button',
				'label' => array(
					__( 'Clear All Caches', GAMBIT_COMBINATOR ),
				),
				'action' => array( 'user_clear_all_caches' ),
				'class' => array( 'button-default' ),
				'desc' => __( 'Empty the whole page cache.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
				'name' => __( 'Page Caching', GAMBIT_COMBINATOR ),
				'id' => 'page_cache_enabled',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'Whole pages are kept for a short while so we can serve them faster to users who visit within a few minutes of each other.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
				'name' => __( 'Object Caching', GAMBIT_COMBINATOR ),
				'id' => 'object_cache_enabled',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'WordPress performs a lot of computationally expensive processes per page load. Object caching enables the caching of these heavy results.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
				'name' => __( 'CSS & JS Minify', GAMBIT_COMBINATOR ),
				'id' => 'minify_enabled',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'If you have a lot of plugins, then your site most likely loads a lot of different Javascript and Stylesheet files. Minify combines these files together and makes the filesize smaller for less browser requests.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
				'name' => __( 'Sprite Conversion', GAMBIT_COMBINATOR ),
				'id' => 'sprite_enabled',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'Small to medium sized images are automatically combined into larger sprite images. This lessens the number of browser requests since multiple image requests are replaced with one.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
			    'type' => 'save',
			) );
			
			

			$cachingTab->createOption( array(
				'name' => __( 'Page Caching Settings', GAMBIT_COMBINATOR ),
				'type' => 'heading',
				'desc' => __( 'Whole pages are kept for a short while so we can serve them faster to users who visit within a few minutes of each other.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
				'name' => __( 'Expiration', GAMBIT_COMBINATOR ),
				'id' => 'page_cache_expiration',
				'type' => 'number',
				'default' => '86400',
				'size' => 'medium',
				'max' => '604800',
				'min' => '0',
				'step' => '1',
				'unit' => 'seconds',
				'desc' => __( 'The amount of time to keep whole cached pages before rebuilding them.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
			    'type' => 'save',
			) );
			
			
			
			$cachingTab->createOption( array(
				'name' => __( 'Object Caching Settings', GAMBIT_COMBINATOR ),
				'type' => 'heading',
				'desc' => __( 'WordPress performs a lot of computationally expensive processes per page load. Object caching enables the caching of these heavy results.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
				'name' => __( 'Expiration', GAMBIT_COMBINATOR ),
				'id' => 'object_cache_expiration',
				'type' => 'number',
				'default' => '18000',
				'size' => 'medium',
				'max' => '86400',
				'min' => '0',
				'step' => '1',
				'unit' => 'seconds',
				'desc' => __( 'The amount of time to keep objects are cached before rebuilding them.', GAMBIT_COMBINATOR ),
			) );
			
			$cachingTab->createOption( array(
				'name' => __( 'Object Caching Server Settings', GAMBIT_COMBINATOR ),
				'type' => 'heading',
				'desc' => __( 'These are the settings used to connect to your caching servers. The caching setup is auto-detected depending on what is available from your setup. You normally would not have to adjust these since these are usually the default connection details.', GAMBIT_COMBINATOR ),
			) );
			global $wp_object_cache;
			$cachingTab->createOption( array(
				'name' => __( 'Connection Log', GAMBIT_COMBINATOR ),
				'paragraph' => false,
				'type' => 'note',
				'desc' => '<pre class="gc-conn-log">' . ( method_exists( $wp_object_cache, 'getLog' ) ? $wp_object_cache->getLog() : __( 'No logs available, object caching is disabled', GAMBIT_COMBINATOR ) ) . '</pre>',
			) );
			$cachingTab->createOption( array(
				'name' => __( 'Memcache Host', GAMBIT_COMBINATOR ),
				'id' => 'memcache_host',
				'type' => 'text',
				'default' => '127.0.0.1',
				'desc' => __( 'The IP address or host name of your Memcache server. If you have multiple Memcached servers, you can enter multiple comma-separated host names.', GAMBIT_COMBINATOR ) .
					'<br>' .
					__( 'Gambit Cache will try to connect to Memcache if it is installed, make this field blank if you want us to stop connecting to Memcache.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
				'name' => __( 'Memcache Port', GAMBIT_COMBINATOR ),
				'id' => 'memcache_port',
				'type' => 'text',
				'default' => '11211',
				'desc' => __( 'The port of your Memcache server, the default is 11211. If you have multiple Memcached servers, you can enter multiple comma-separated ports', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
				'name' => __( 'Redis Host', GAMBIT_COMBINATOR ),
				'id' => 'redis_host',
				'type' => 'text',
				'default' => '127.0.0.1',
				'desc' => __( 'The IP address or host name of your Redis server.', GAMBIT_COMBINATOR ) .
					'<br>' .
					__( 'Gambit Cache will try to connect to Redis if it is installed, make this field blank if you want us to stop connecting to Redis.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
				'name' => __( 'Redis Port', GAMBIT_COMBINATOR ),
				'id' => 'redis_port',
				'type' => 'text',
				'default' => '',
				'desc' => __( 'The port of your Redis server.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
				'name' => __( 'Redis Database', GAMBIT_COMBINATOR ),
				'id' => 'redis_database',
				'type' => 'text',
				'default' => '',
				'desc' => __( 'If you have a specific database name, enter it here.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
				'name' => __( 'Redis Password', GAMBIT_COMBINATOR ),
				'id' => 'redis_password',
				'type' => 'text',
				'is_password' => true,
				'default' => '',
				'desc' => __( 'If you are required a password in order to connect to your Redis server, enter it here.', GAMBIT_COMBINATOR ),
			) );
			$cachingTab->createOption( array(
			    'type' => 'save',
			) );
			
			
			
			$minifyTab = $adminPanel->createTab( array(
			    'name' => __( 'Minify Settings', GAMBIT_COMBINATOR ),
			) );
			
			
			$minifyTab->createOption( array(
				'name' => __( 'General Settings', GAMBIT_COMBINATOR ),
				'type' => 'heading',
			) );
			
			$minifyTab->createOption( array(
				'name' => sprintf( __( 'Remove %s from URLs', GAMBIT_COMBINATOR ), '<code>ver</code> arg' ),
				'id' => 'remove_ver_from_urls',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'Remove the version parameter from scripts and styles. This is to allow some proxy caching servers to cache your scripts and stylesheets. These are only removed if <code>ver</code> is the only parameter in the URL, this is to be safe so we do not affect other parameters.', GAMBIT_COMBINATOR ),
			) );

			$minifyTab->createOption( array(
			    'type' => 'save',
			) );
			
			
			$minifyTab->createOption( array(
				'name' => __( 'Javascript Settings', GAMBIT_COMBINATOR ),
				'type' => 'heading',
				'desc' => __( 'These settings are specific to minifying your Javascript files.', GAMBIT_COMBINATOR ),
			) );
			
			$minifyTab->createOption( array(
				'name' => __( 'Combine Javascripts', GAMBIT_COMBINATOR ),
				'id' => 'js_enabled',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'Enable combining of Javascript files', GAMBIT_COMBINATOR ),
			) );
			
			$minifyTab->createOption( array(
				'name' => __( 'Compression Level', GAMBIT_COMBINATOR ),
				'id' => 'js_compression_level',
				'type' => 'select',
				'default' => 2,
				'options' => array(
					'0' => __( 'No Compression, Just Combine Scripts', GAMBIT_COMBINATOR ),
					'1' => __( 'White Space Removal', GAMBIT_COMBINATOR ),
					'2' => __( 'Simple Optimizations (Recommended)', GAMBIT_COMBINATOR ),
					'3' => __( 'Advanced Optimizations (NOT Recommended)', GAMBIT_COMBINATOR ),
				),
				'desc' => __( 'Combinator uses <a href="https://developers.google.com/closure/compiler/index">Closure Compiler</a> to perform code compression. You can choose from these types of compression:<ul><li><strong>White Space Removal</strong><br>Gives some compression by removing unnecessary spaces from your scripts. <em>(<strong>Recommended</strong> if Simple Optimization fails and produces errors)</em>,</li><li><strong>Simple Optimizations</strong><br>Performs great compression and optimizations that does not interfere with script interactions. <em>(<strong>Recommended</strong> and should work in most setups)</em></li><li><strong>Advanced Optimizations</strong><br>Highest level of compression, but all variables/function names/symbols in your scripts will be renamed. <em>(<strong>Not recommended</strong>, since this will most likely create broken references in your Javascript. Read more on this in the <a href="https://developers.google.com/closure/compiler/docs/api-tutorial3">Closure Compiler docs</a> for more information on how to circumvent this, note that this would entail rewriting your Javascript)</em></li></ul>', GAMBIT_COMBINATOR ),
			) );
			
			$minifyTab->createOption( array(
				'name' => __( 'What to Combine', GAMBIT_COMBINATOR ),
				'id' => 'js_includes',
				'type' => 'multicheck',
				'default' => array( 'includes', 'remote' ),
				'options' => array(
					'includes' => __( 'WordPress wp-include files', GAMBIT_COMBINATOR ),
					'remote' => __( 'Remote scripts', GAMBIT_COMBINATOR ),
					'theme' => __( 'Theme scripts (sometimes distrupts theme behavior)', GAMBIT_COMBINATOR ),
					'inline' => __( 'Script tags (small chance to give errors)', GAMBIT_COMBINATOR ),
				),
				'desc' => __( 'Check the types of scripts to combine. Scripts from plugins are always combined.', GAMBIT_COMBINATOR ),
			) );
			
			$minifyTab->createOption( array(
				'name' => __( 'Exclude these Domains', GAMBIT_COMBINATOR ),
				'id' => 'js_exclude',
				'type' => 'textarea',
				'default' => 'jquery.js',
				'desc' => __( 'Enter a domain or part of a URL (one per line) that you want to exclude from the combination process.', GAMBIT_COMBINATOR ),
				'placeholder' => __( 'Enter a domain or part of a URL (one per line)', GAMBIT_COMBINATOR ),
			) );

			$minifyTab->createOption( array(
			    'type' => 'save',
			) );
			
			
			
			$minifyTab->createOption( array(
				'name' => __( 'CSS Settings', GAMBIT_COMBINATOR ),
				'type' => 'heading',
				'desc' => __( 'These settings are specific to minifying your stylesheets.', GAMBIT_COMBINATOR ),
			) );
			
			$minifyTab->createOption( array(
				'name' => __( 'Combine Stylesheets', GAMBIT_COMBINATOR ),
				'id' => 'css_enabled',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'Enable combining of stylesheets', GAMBIT_COMBINATOR ),
			) );
			
			$minifyTab->createOption( array(
				'name' => __( 'Compression Level', GAMBIT_COMBINATOR ),
				'id' => 'css_compression_level',
				'type' => 'select',
				'default' => 1,
				'options' => array(
					'0' => __( 'No Compression, Just Combine Styles', GAMBIT_COMBINATOR ),
					'1' => __( 'Simple Optimizations (Recommended)', GAMBIT_COMBINATOR ),
				),
				'desc' => __( 'Choose the compression level for CSS stylesheets.', GAMBIT_COMBINATOR ),
			) );
			
			$minifyTab->createOption( array(
				'name' => __( 'What to Combine', GAMBIT_COMBINATOR ),
				'id' => 'css_includes',
				'type' => 'multicheck',
				'default' => array( 'includes', 'remote' ),
				'options' => array(
					'includes' => __( 'WordPress wp-include files', GAMBIT_COMBINATOR ),
					'remote' => __( 'Remote stylesheets', GAMBIT_COMBINATOR ),
					'theme' => __( 'Theme scripts (sometimes distrupts theme behavior)', GAMBIT_COMBINATOR ),
					'inline' => __( 'Style tags (high chance to disrupt page styles)', GAMBIT_COMBINATOR ),
				),
				'desc' => __( 'Check the types of styles to combine. Styles from plugins are always combined.', GAMBIT_COMBINATOR ),
			) );
			
			$minifyTab->createOption( array(
				'name' => __( 'Exclude these Domains', GAMBIT_COMBINATOR ),
				'id' => 'css_exclude',
				'type' => 'textarea',
				'default' => 'googleapis',
				'desc' => __( 'Enter a domain or part of a URL (one per line) that you want to exclude from the combination process.', GAMBIT_COMBINATOR ),
				'placeholder' => __( 'Enter a domain or part of a URL (one per line)', GAMBIT_COMBINATOR ),
			) );

			$minifyTab->createOption( array(
			    'type' => 'save',
			) );
			
			
			
			$minifyTab->createOption( array(
				'name' => __( 'Exclusion Settings', GAMBIT_COMBINATOR ),
				'type' => 'heading',
				'desc' => __( 'If some parts of your site stop working, or if you encounter Javascript errors, you can exclude parts of your site here from the minification process. This applies for both Javascript and stylesheets.', GAMBIT_COMBINATOR ),
			) );
			
			$minifyTab->createOption( array(
				'name' => __( 'Exclude these Plugins', GAMBIT_COMBINATOR ),
				'id' => 'exclude_plugins',
				'type' => 'multicheck',
				'default' => $blackListDefault,
				'options' => $pluginOptions,
				'desc' => __( 'Combinator combines all scripts and styles it can find. If a plugin stops working because of the combination process, <strong>check the plugin here to exclude it</strong>.', GAMBIT_COMBINATOR ) . 
					'<div style="border-left: 4px solid #dd3d36; box-shadow: 0 1px 1px 0 rgba(0,0,0,.1); margin: 5px 0 15px; padding: 1px 12px;"><p style="margin: .5em 0;padding: 2px;"><strong>' .
					__( 'If you have found a plugin to be not working when included with Combinator, please let us know by commenting it in our CodeCanyon page so we can include it in our internal blacklist.', GAMBIT_COMBINATOR ) . 
					'</strong></p></div>',
			) );
			
			
			$foundJS = get_option( 'combinator_found_js' );
			$jsOptions = array();
			if ( empty( $foundJS ) ) {
				$foundJS = array();
			} else if ( is_serialized( $foundJS ) ) {
				$foundJS = unserialize( $foundJS );
			}
			foreach ( $foundJS as $src ) {
				$jsOptions[ $src ] = '<code>' . $src . '</code>';
			}
			
			$minifyTab->createOption( array(
				'name' => __( 'Exclude Found Javascript', GAMBIT_COMBINATOR ),
				'id' => 'exclude_found_js',
				'type' => 'multicheck',
				'default' => array(),
				'options' => $jsOptions,
				'desc' => __( 'Here is a list of all the Javascript URLs Combinator has found. If the list below is empty, visit your site to populate it.<br><strong>Check the URL here to exclude it.</strong>', GAMBIT_COMBINATOR ),
			) );
			
			
			$foundCSS = get_option( 'combinator_found_css' );
			$cssOptions = array();
			if ( empty( $foundCSS ) ) {
				$foundCSS = array();
			} else if ( is_serialized( $foundCSS ) ) {
				$foundCSS = unserialize( $foundCSS );
			}
			foreach ( $foundCSS as $src ) {
				$cssOptions[ $src ] = '<code>' . $src . '</code>';
			}
			
			$minifyTab->createOption( array(
				'name' => __( 'Exclude Found Stylesheets', GAMBIT_COMBINATOR ),
				'id' => 'exclude_found_css',
				'type' => 'multicheck',
				'default' => array(),
				'options' => $cssOptions,
				'desc' => __( 'Here is a list of all the Stylesheet URLs Combinator has found. If the list below is empty, visit your site to populate it.<br><strong>Check the URL here to exclude it.</strong>', GAMBIT_COMBINATOR ),
			) );

			$minifyTab->createOption( array(
			    'type' => 'save',
			) );
			
			
			
			$spriteTab = $adminPanel->createTab( array(
			    'name' => __( 'Sprite Settings', GAMBIT_COMBINATOR ),
			) );
			$spriteTab->createOption( array(
				'name' => __( 'Sprite Settings', GAMBIT_COMBINATOR ),
				'type' => 'heading',
				'desc' => __( '', GAMBIT_COMBINATOR ),
			) );
			
			$spriteTab->createOption( array(
				'name' => __( 'Include Remote Images', GAMBIT_COMBINATOR ),
				'id' => 'sprite_include_remotes',
				'type' => 'checkbox',
				'default' => true,
				'desc' => __( 'Create sprites for remote images', GAMBIT_COMBINATOR ),
			) );
			
			$spriteTab->createOption( array(
				'name' => __( 'Sprite JPG Quality', GAMBIT_COMBINATOR ),
				'id' => 'sprite_quality',
				'type' => 'number',
				'default' => '60',
				'min' => '10',
				'max' => '100',
				'step' => '1',
				'desc' => __( 'The quality of the image produced. Only applies for JPG sprites.', GAMBIT_COMBINATOR ),
			) );
			
			$spriteTab->createOption( array(
				'name' => __( 'Sprite Size', GAMBIT_COMBINATOR ),
				'id' => 'sprite_size',
				'type' => 'number',
				'default' => '1000',
				'unit' => 'px',
				'min' => '1000',
				'max' => '2000',
				'step' => '100',
				'desc' => __( 'The size (width & height) of sprites to create. A larger sprite can contain more images, but also requires more server memory.', GAMBIT_COMBINATOR ) .
					'<br>' .
					'<em><strong>' . __( 'WARNING:', GAMBIT_COMBINATOR ) . '</strong> ' . __( 'If you increase this and run out of memory, lower this value then clear the cache from the Caching Settings tab.', GAMBIT_COMBINATOR ) . '</em>',
			) );

			$spriteTab->createOption( array(
			    'type' => 'save',
			) );

		}

}

}