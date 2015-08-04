<?php

// TODO
/**
1. include wp-include scripts
2. include remote scripts
3. add field to exclude scripts
4. add note for secret key
5. add field to exclude page
6. add field to specifically include script
7. Closure compiler (select optimization: whitespace only, simple, advanced)
8. Compress CSS

9. If compile/compress is enabled for JS, do not use class-load-scripts.php, use closure cached instead

admin_url('admin-ajax.php?action=my_css') ????
 */

require_once( 'combinator/lib/class-js.php' );
require_once( 'combinator/lib/class-css.php' );

// Initializes Titan Framework
require_once( 'titan-framework-checker.php' );


if ( ! class_exists( 'GambitCombinator' ) ) {
	
	class GambitCombinator {
	
		const SECRET_KEY = "SuperSecretGambitKey";
	
		public $headScripts = array();
		public $footerScripts = array();
		public $headStyles = array();
		public $footerStyles = array();
	
		public $inHead = true;
		
		public $settings = array(
			'global_enabled' => true,
			'combine_method' => 1,
			'gzip_output' => 1,
			
			'js_enabled' => true,
			'js_compression_level' => 2,
			'js_include_includes' => true,
			'js_include_remote' => false,
			
			'css_compression_level' => 1,
			'css_enabled' => true,
			'css_include_includes' => true,
			'css_include_remotes' => false,
		);
	
		function __construct() {
			// Initializes settings panel
			add_filter( 'plugin_action_links', array( $this, 'pluginSettingsLink' ), 10, 2 );
			add_action( 'tf_create_options', array( $this, 'createAdminOptions' ) );
			add_action( 'tf_done', array( $this, 'gatherSettings' ), 10 );
		
			add_filter( 'script_loader_tag', array( $this, 'gatherEnqueuedScripts' ), 999, 3 );
			add_action( 'wp_footer', array( $this, 'footerScriptLoader' ), 999 );
			add_action( 'wp_head', array( $this, 'headScriptLoader' ), 999 );
	
			add_filter( 'style_loader_tag', array( $this, 'gatherEnqueuedStyles' ), 999, 2 );
			add_action( 'wp_footer', array( $this, 'footerStyleLoader' ), 999 );
			add_action( 'wp_head', array( $this, 'headStyleLoader' ), 999 );
		
			add_action( 'wp_head', array( $this, 'doneWithHead' ), 1000 );

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

			$adminPanel = $titan->createAdminPanel( array(
			    'name' => 'Combinator',
				'id' => GAMBIT_COMBINATOR,
			    'parent' => 'options-general.php',
			) );
			
			// TODO Add Titan Framework options here
			
			$adminPanel->createOption( array(
				'name' => __( 'General Settings', GAMBIT_COMBINATOR ),
				// 'id' => 'my_text_option',
				'type' => 'heading',
				// 'desc' => __( 'This is our option', GAMBIT_COMBINATOR ),
				// 'placeholder' => __( 'Put a value here', GAMBIT_COMBINATOR ),
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Enable Combinator', GAMBIT_COMBINATOR ),
				'id' => 'global_enable',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'You can enable or disable the combining of scripts and stylesheets globally with this setting.', GAMBIT_COMBINATOR ),
				// 'placeholder' => __( 'Put a value here', GAMBIT_COMBINATOR ),
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Combination Method', GAMBIT_COMBINATOR ),
				'id' => 'combine_method',
				'type' => 'select',
				'default' => 1,
				'options' => array(
					'1' => __( 'Generate files & fallback to on-the-fly generation', GAMBIT_COMBINATOR ),
					'2' => __( 'On-the-fly generation', GAMBIT_COMBINATOR ),
				),
				'desc' => __( 'Combinator uses 2 methods to combine scripts and stylesheets:<ol><li><strong>Generate files in the uploads folder</strong><br>Scripts and styles are combined and saved in the <code>combinator</code> subdirectory in your uploads folder. This works in the majority of server setups and results in the fastest loading speeds.</li><li><strong>On-the-fly generation</strong><br>Scripts and stylesheets are combined when needed and no files are generated. You will still get the benefits of fewer server requests, but Javascript compression will be disabled and the performance is slower than the first method.</ol>', GAMBIT_COMBINATOR ),
				// 'placeholder' => __( 'Put a value here', GAMBIT_COMBINATOR ),
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Enable GZIP Compression', GAMBIT_COMBINATOR ),
				'id' => 'gzip_output',
				'type' => 'enable',
				'default' => true,
				'desc' => __( '<strong>[For on-the-fly generation only]</strong><br>Use gzip for Content Encoding the output of combined scripts and stylesheets.', GAMBIT_COMBINATOR ),
				// 'placeholder' => __( 'Put a value here', GAMBIT_COMBINATOR ),
			) );

			$adminPanel->createOption( array(
			    'type' => 'save',
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Javascript Settings', GAMBIT_COMBINATOR ),
				// 'id' => 'my_text_option',
				'type' => 'heading',
				// 'desc' => __( 'This is our option', GAMBIT_COMBINATOR ),
				// 'placeholder' => __( 'Put a value here', GAMBIT_COMBINATOR ),
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Combine Javascripts', GAMBIT_COMBINATOR ),
				'id' => 'js_enable',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'Enable combining of Javascript files', GAMBIT_COMBINATOR ),
				// 'placeholder' => __( 'Put a value here', GAMBIT_COMBINATOR ),
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Compression Level', GAMBIT_COMBINATOR ),
				'id' => 'js_compression_level',
				'type' => 'select',
				'default' => 2,
				'options' => array(
					'0' => __( 'No Compression, Just Combine Scripts', GAMBIT_COMBINATOR ),
					'1' => __( 'White Space Removal', GAMBIT_COMBINATOR ),
					'2' => __( 'Simple Optimizations', GAMBIT_COMBINATOR ),
					'3' => __( 'Advanced Optimizations', GAMBIT_COMBINATOR ),
				),
				'desc' => __( '<strong>[For generate files method only]</strong><br>Combinator uses <a href="https://developers.google.com/closure/compiler/index">Closure Compiler</a> to perform code compression. You can choose from these types of compression:<ul><li><strong>White Space Removal</strong><br>Gives some compression by removing unnecessary spaces from your scripts. <em>(<strong>Recommended</strong> if Simple Optimization fails and produces errors)</em>,</li><li><strong>Simple Optimizations</strong><br>Performs great compression and optimizations that does not interfere with script interactions. <em>(<strong>Recommended</strong> and should work in most setups)</em></li><li><strong>Advanced Optimizations</strong><br>Highest level of compression, but all variables/function names/symbols in your scripts will be renamed. <em>(<strong>Not recommended</strong>, since this will most likely create broken references in your Javascript. Read more on this in the <a href="https://developers.google.com/closure/compiler/docs/api-tutorial3">Closure Compiler docs</a> for more information on how to circumvent this, note that this would entail rewriting your Javascript)</em></li></ul>', GAMBIT_COMBINATOR ),
				// 'placeholder' => __( 'Put a value here', GAMBIT_COMBINATOR ),
			) );

			$adminPanel->createOption( array(
			    'type' => 'save',
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'CSS Settings', GAMBIT_COMBINATOR ),
				// 'id' => 'my_text_option',
				'type' => 'heading',
				// 'desc' => __( 'This is our option', GAMBIT_COMBINATOR ),
				// 'placeholder' => __( 'Put a value here', GAMBIT_COMBINATOR ),
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Combine Stylesheets', GAMBIT_COMBINATOR ),
				'id' => 'css_enable',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'Enable combining of stylesheets', GAMBIT_COMBINATOR ),
				// 'placeholder' => __( 'Put a value here', GAMBIT_COMBINATOR ),
			) );
			
			// $adminPanel->createOption( array(
			// 	'name' => __( 'My Text Option', GAMBIT_COMBINATOR ),
			// 	'id' => 'my_text_option',
			// 	'type' => 'text',
			// 	'desc' => __( 'This is our option', GAMBIT_COMBINATOR ),
			// 	'placeholder' => __( 'Put a value here', GAMBIT_COMBINATOR ),
			// ) );

			$adminPanel->createOption( array(
			    'type' => 'save',
			) );
		}
		
		
		public function gatherSettings() {

			if ( ! class_exists( 'TitanFramework' ) ) {
				return;
			}

			$titan = TitanFramework::getInstance( GAMBIT_COMBINATOR );
			
			$this->settings['global_enabled'] = $titan->getOption( 'global_enable' );
			$this->settings['js_enable'] = $titan->getOption( 'js_enable' );
			$this->settings['css_enable'] = $titan->getOption( 'css_enable' );
			$this->settings['combine_method'] = $titan->getOption( 'combine_method' );
			$this->settings['gzip_output'] = $titan->getOption( 'gzip_output' );
			$this->settings['js_compression_level'] = $titan->getOption( 'js_compression_level' );
			// $titan->setOption( 'global_enable', true );
			// var_dump($titan->getOption( 'global_enable' ) );
			// var_dump($titan->saveOptions());
		}
	
	
		public function deleteAllFiles() {
			GambitCombinatorFiles::deleteAllFiles();
		}
	
	
		public function deleteAllCaches() {
			global $wpdb;

			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_cmbntr_%' OR option_name LIKE '_transient_timeout_cmbntr_%'" );
		}
	
	
		public function isFrontEnd() {
			return is_404() ||
				   is_archive() ||
				   is_attachment() ||
				   is_author() ||
				   is_category() ||
				   is_date() ||
				   is_day() ||
				   is_feed() ||
				   is_front_page() ||
				   is_home() ||
				   is_month() ||
				   is_page() ||
				   is_page_template() ||
				   is_preview() ||
				   is_search() ||
				   is_single() ||
				   is_singular() ||
				   is_tag() ||
				   is_tax() ||
				   is_time() ||
				   is_year();
		}
	
	
		public function encodeLoadParam( $fileArray ) {
			$data = gzdeflate( serialize( $fileArray ) );
	        $data = mcrypt_encrypt( MCRYPT_RIJNDAEL_256, self::SECRET_KEY, $data, MCRYPT_MODE_ECB, mcrypt_create_iv( mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB ), MCRYPT_RAND ) );
			return base64_encode( $data );
		}
	
	
		public function gatherEnqueuedScripts( $tag, $handle, $src ) {
		
			if ( ! $this->settings['js_enable'] || ! $this->settings['global_enabled'] ) {
				return $tag;
			}

			if ( ! $this->isFrontEnd() ) {
				return $tag;
			}
		
			$includeIncludes = true;
			$includeRemote = true;
		
			// Only do this for local wp-content files
			$path = '';
			if ( strpos( $src, content_url() ) !== false ) {
	
				// Get local path
				$path = str_replace( content_url(), WP_CONTENT_DIR, $src );
			
		
			// Only do this for local wp-include files
			} else if ( strpos( $src, includes_url() ) !== false ) {
	
				// Get local path
				if ( ! $includeIncludes ) {
					return $tag;
				}
				$path = str_replace( trim( includes_url(), '/\\' ), ABSPATH . WPINC, $src );

			} else if ( ! $includeRemote ) {
				return $tag;
			}
	
			if ( ! empty( $path ) ) {
		
				// Remove trailing arguments
				$path = preg_replace( '/\?.+$/', '', $path );
		
				// Check if file exists
				$path = realpath( $path );
				if ( ! $path && ! @is_file( $path ) ) {
					return $tag;
				}
			
			}
		
			// Remember the handler
			if ( $this->inHead ) {
				$this->headScripts[] = $src;
			} else {
				$this->footerScripts[] = $src;
			}
		
			// $_SESSION[ 'scriptHandles' ] = wp_remote_fopen( $src );
			// $this->test[] = $src;
			// var_dump($src);
	// 		var_dump(gzdeflate($this->test));
	// 		var_dump(base64_encode(gzdeflate($this->test)));
	// 		var_dump(gzinflate(base64_decode(base64_encode(gzdeflate($this->test)))));

			// Save the path of this file
			// update_option( 'js_combiner_' . $handle, $path );
	
			return '';
		}
	
	
		public function scriptLoader( $scripts ) {
		
			$method = $this->settings['combine_method'];
			$compressionLevel = $this->settings['js_compression_level'];
			$gzip = $this->settings['gzip_output'] ? '1' : '';
		
			if ( ! count( $scripts ) ) {
				return;
			}
		
			$hash = substr( md5( serialize( $scripts ) . $compressionLevel ), 0, 8 );

			global $wp_filesystem;
		
			// delete_transient( 'js_combined_' . $hash );
			$output = get_transient( 'cmbntr_js' . $hash );
			// var_dump('cmbntr_js_' . $hash, 'transient', $output);
			if ( ( $method == 1 && ! $output ) || ( $method == 1 && ! empty( $output['path'] ) && ! $wp_filesystem->is_file( $output['path'] ) ) ) {
		
				$combined = GambitCombinatorJS::combineSources( $scripts );
			
				if ( $compressionLevel ) {
					$combined = GambitCombinatorJS::closureCompile( $combined, $compressionLevel );
				}
			
				$output = GambitCombinatorJS::createFile( 
					$combined, 
					$hash . '.js'
				);
			
				// var_dump('cmbntr_js_' . $hash, $output);
				set_transient( 'cmbntr_js' . $hash, $output, DAY_IN_SECONDS );
				// var_dump('get_transient', get_transient( 'cmbntr_js_' . $hash ));
			}
		
			// var_dump($upload_dir['baseurl'] . 'combinator/' . $filePath );
			if ( $method == 1 && ! empty( $output['path'] ) && $wp_filesystem->is_file( $output['path'] ) ) {
				echo "<script type='text/javascript' src='" . esc_url( $output['url'] ) . "'></script>";
			
			} else {
		
			// if ( count( $this->headScripts ) ) {
				$data = $this->encodeLoadParam( $scripts );
				// echo "<script type='text/javascript' src='" . esc_url( add_query_arg( array( 'c' => 1, 'm' => 0, 'load' => $data ), admin_url( 'admin-ajax.php?action=combinator_scripts' ) ) ) . "'></script>";
				echo "<script type='text/javascript' src='" . esc_url( add_query_arg( array( 'c' => $gzip, 'm' => $compressionLevel, 'load' => $data ), plugins_url( 'combinator/fallback/class-load-scripts.php', __FILE__ ) ) ) . "'></script>";
			// }
			}
		
		}
	
		public function headScriptLoader() {
			$this->scriptLoader( $this->headScripts );
		}
	

		public function footerScriptLoader() {
			$this->scriptLoader( $this->footerScripts );
		}
	
	
		public function gatherEnqueuedStyles( $tag, $handle ) {
		
			if ( ! $this->settings['css_enable'] || ! $this->settings['global_enabled'] ) {
				return $tag;
			}

			if ( ! $this->isFrontEnd() ) {
				return $tag;
			}
		
			$includeIncludes = true;
			$includeRemote = true;
		
			// Do only for stylesheets
			if ( ! preg_match( "/rel=['\"]stylesheet['\"]/", $tag ) ) {
				return $tag;
			}
			if ( ! preg_match( "/media=['\"]all['\"]/", $tag ) ) {
				return $tag;
			}
		
			// Get $src
			preg_match( "/href=['\"]([^'\"]+)['\"]/", $tag, $matches );
			if ( count( $matches ) < 1 ) {
				return $tag;
			}
			$src = $matches[1];
		
			// Only do this for local wp-content files
			$path = '';
			if ( strpos( $src, content_url() ) !== false ) {
	
				// Get local path
				$path = str_replace( content_url(), WP_CONTENT_DIR, $src );
		
			// Only do this for local wp-include files
			} else if ( strpos( $src, includes_url() ) !== false ) {
	
				// Get local path
				if ( ! $includeIncludes ) {
					return $tag;
				}
				$path = str_replace( trim( includes_url(), '/\\' ), ABSPATH . WPINC, $src );
			
			} else if ( ! $includeRemote ) {

				return $tag;
			}
		
			if ( ! empty( $path ) ) {
		
				// Remove trailing arguments
				$path = preg_replace( '/\?.+$/', '', $path );
		
				// Check if file exists
				$path = realpath( $path );
				if ( ! $path && ! @is_file( $path ) ) {
					return $tag;
				}
			
			}
		
			// If no handle, generate one
			// $handle = ! empty( $handle ) ? $handle : substr_compare( md5( $path ), 0, 8 );
		
			// Remember the handler
			if ( $this->inHead ) {
				$this->headStyles[] = $src;
			} else {
				$this->footerStyles[] = $src;
			}
		
			// $_SESSION[ 'styleHandles' ] = $path;

			// Save the path of this file
			// update_option( 'css_combiner_' . $handle, $path );
		
			return '';
		}


	
	
		public function styleLoader( $styles ) {
		
			$method = $this->settings['combine_method'];
			$compressionLevel = 1;
			$gzip = $this->settings['gzip_output'] ? '1' : '';
		
			if ( ! count( $styles ) ) {
				return;
			}
		
			$hash = substr( md5( serialize( $styles ) . $compressionLevel ), 0, 8 );

			global $wp_filesystem;
		
			// delete_transient( 'css_combined_' . $hash );
			$output = get_transient( 'cmbntr_css' . $hash );
			// var_dump($output);
			if ( ( $method == 1 && ! $output ) || ( $method == 1 && ! empty( $output['path'] ) && ! $wp_filesystem->is_file( $output['path'] ) ) ) {
		
				$combined = GambitCombinatorCSS::combineSources( $styles );
			
				if ( $compressionLevel ) {
					$combined = GambitCombinatorCSS::compile( $combined );
				}
			
				$output = GambitCombinatorCSS::createFile( 
					$combined, 
					$hash . '.css'
				);
			
				set_transient( 'cmbntr_css' . $hash, $output, DAY_IN_SECONDS );
			
			}
		
			// var_dump($upload_dir['baseurl'] . 'combinator/' . $filePath );
			if ( $method == 1 && ! empty( $output['path'] ) && $wp_filesystem->is_file( $output['path'] ) ) {
				echo "<link rel='stylesheet' id='css_combinator_" . esc_attr( $hash ) . "-css' href='" . esc_url( $output['url'] ) . "' type='text/css' media='all' />";
				// echo "<script type='text/javascript' src='" . esc_url( $output['url'] ) . "'></script>";
			
			} else {
		
			// if ( count( $this->headScripts ) ) {
				$data = $this->encodeLoadParam( $styles );
				// echo "<script type='text/javascript' src='" . esc_url( add_query_arg( array( 'c' => 1, 'm' => 0, 'load' => $data ), admin_url( 'admin-ajax.php?action=combinator_scripts' ) ) ) . "'></script>";
				// echo "<script type='text/javascript' src='" . esc_url( add_query_arg( array( 'c' => 1, 'm' => $compressionLevel, 'load' => $data ), plugins_url( 'fallback/class-load-scripts.php', __FILE__ ) ) ) . "'></script>";
			// }
				echo "<link rel='stylesheet' id='css_combinator_" . esc_attr( $hash ) . "-css' href='" . esc_url( add_query_arg( array( 'c' => $gzip, 'm' => $compressionLevel, 'load' => $data ), plugins_url( 'combinator/fallback/class-load-styles.php', __FILE__ ) ) ) . "' type='text/css' media='all' />";
		
			}
		
		}
	
	
		public function headStyleLoader() {
			// if ( count( $this->headStyles ) ) {
			// 	$data = $this->encodeLoadParam( $this->headStyles );
			// 	echo "<link rel='stylesheet' id='css_combiner-css' href='" . esc_url( add_query_arg( array( 'c' => 1, 'm' => 0, 'load' => $data ), plugins_url( 'fallback/class-load-styles.php', __FILE__ ) ) ) . "' type='text/css' media='all' />";
			// }
			$this->styleLoader( $this->headStyles );
		}
	

		public function footerStyleLoader() {
			// if ( count( $this->footerStyles ) ) {
			// 	$data = $this->encodeLoadParam( $this->footerStyles );
			// 	echo "<link rel='stylesheet' id='css_combiner-footer-css' href='" . esc_url( add_query_arg( array( 'c' => 1, 'm' => 0, 'load' => $data ), plugins_url( 'fallback/class-load-styles.php', __FILE__ ) ) ) . "' type='text/css' media='all' />";
			// }
			$this->styleLoader( $this->footerStyles );
		}
	
	
		public function doneWithHead() {
			$this->inHead = false;
		}
	}

	new GambitCombinator();
	
}