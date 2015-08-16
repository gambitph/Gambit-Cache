<?php

require_once( 'combinator/lib/class-js.php' );
require_once( 'combinator/lib/class-css.php' );
require_once( 'combinator/lib/class-cache-activation.php' );
require_once( 'combinator/lib/class-cache-deactivation.php' );
require_once( 'combinator/lib/class-admin-page.php' );
require_once( 'combinator/lib/class-page-cache.php' );
require_once( 'combinator/lib/class-page-cache-cleaner.php' );
require_once( 'combinator/lib/class-object-cache-cleaner.php' );

// Initializes Titan Framework
require_once( 'titan-framework-checker.php' );

// ob_start();

if ( ! class_exists( 'GambitCombinator' ) ) {
	
	class GambitCombinator {
	
		public $headScripts = array();
		public $footerScripts = array();
		public $headStyles = array();
		public $footerStyles = array();
	
		public $inHead = true;
		
		/**
		 * These plugins have been proven to be NOT working when combined
		 */
		public $blackListedPlugins = array(
			'Facebook Like Box',
			'PopTrends',
			'Smooth MouseWheel',
		);
		
		public $settings = array(
			'global_enabled' => true,
			'exclude_plugins' => array(),
			'exclude_found_js' => array(),
			'exclude_found_css' => array(),
			
			'js_enabled' => true,
			'js_compression_level' => 2,
			'js_include_includes' => true,
			'js_include_remote' => true,
			'js_include_inline' => false,
			'js_exclude' => 'jquery.js',
			
			'css_compression_level' => 1,
			'css_enabled' => true,
			'css_include_includes' => true,
			'css_include_remote' => true,
			'css_include_inline' => false,
			'css_exclude' => 'googleapis',
		);
		
		
		/**
		 * Hook into WordPress
		 */
		function __construct() {
			
			new GambitCacheAdminPage();
			new GambitCacheActivation();
			new GambitCacheDeactivation();
			new GambitCachePageCache();
			new GambitCachePageCacheCleaner();
			new GambitCacheObjectCacheCleaner();
			
			add_action( 'admin_enqueue_scripts', array( $this, 'adminEnqueueScripts' ) );
			
			// Admin notice for when the uploads folder is unwritable
			add_action( 'admin_notices', array( $this, 'adminNotices' ) );
			add_action( 'wp_ajax_combinator_notice_dismiss', array( $this, 'dismissAdminNotice' ) );
			
			add_action( 'wp_ajax_combinator_clear_cache', array( $this, 'clearCache' ) );

			// Initializes settings panel
			add_action( 'tf_done', array( $this, 'gatherSettings' ), 10 );
			
			apply_filters( 'gc_blacklisted_plugins', function( $plugins ) {
				$plugins = array_merge( $plugins, $this->blackListedPlugins );
				return array_unique( $plugins );
			} );
				
				

			
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				return;
			} 
			
			// add_action( 'plugins_loaded', array( $this, 'test' ), -1 );
			// add_action( 'shutdown', array( $this, 'test2' ), 0 );

			add_action( 'wp_head', array( $this, 'startGatheringOutput' ), 0 );
			add_action( 'wp_head', array( $this, 'endGatheringOutput' ), 9999 );

			add_action( 'wp_footer', array( $this, 'startGatheringOutput' ), 0 );
			add_action( 'wp_footer', array( $this, 'endGatheringOutput' ), 9999 );
			

			
			
			// add_action( 'init', array( $this, 'setLoggedInCookie' ) );
			// add_action( 'wp_logout', array( $this, 'removeLoggedInCookie' ) );
			
				///updated_{$post_type}_meta
				

		}
		
		public function adminEnqueueScripts() {
			wp_enqueue_style( 'gambit_cache_admin', plugins_url( 'combinator/css/admin.css', GAMBIT_COMBINATOR_PATH ) );
		}
		
		
		
		public function removeLoggedInCookie() {
		}
		
		
		
	    /**
	      * Get the current Url taking into account Https and Port
	      * @link http://css-tricks.com/snippets/php/get-current-page-url/
	      * @version Refactored by @AlexParraSilva
	      */
	     public static function getCurrentUrl() {
	         $url  = isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http';
	         $url .= '://' . $_SERVER['SERVER_NAME'];
	         $url .= in_array( $_SERVER['SERVER_PORT'], array('80', '443') ) ? '' : ':' . $_SERVER['SERVER_PORT'];
	         $url .= $_SERVER['REQUEST_URI'];
	         return $url;
	     }
		
		 public $firstCalled = false;
		 public $pageToCache = '';
		 public $cachingStarted = false;
		
		
		/**
		 * Starts gathering outputted data. To be used in conjunction with $this->endGatheringOutput()
		 * If $this->endGatheringOutput() isn't called, no HTML will be rendered in the page.
		 *
		 * @return	void
		 */
		public function startGatheringOutput() {
			if ( ! $this->settings['global_enabled'] ) {
				return;
			}
			if ( ! $this->settings['js_enabled'] && ! $this->settings['css_enabled'] ) {
				return;
			}
			if ( ! $this->isFrontEnd() ) {
				return;
			}

			ob_start();
			// $content = ob_get_contents();
			// $this->pageToCache .= $content;
			// ob_flush();
		}
		
		
		/**
		 * Stops the current ob_start performed by $this->startGatheringOutput()
		 * and processes the data gathered; then proceeds to generate the combined files
		 *
		 * @return	void
		 */
		public function endGatheringOutput() {
			if ( ! $this->settings['global_enabled'] ) {
				return;
			}
			if ( ! $this->settings['js_enabled'] && ! $this->settings['css_enabled'] ) {
				return;
			}
			if ( ! $this->isFrontEnd() ) {
				return;
			}
			
			$content = ob_get_contents();//ob_get_contents();
			ob_end_clean();
			// ob_flush();
			
			
			// Get the scripts & output
			$scriptsStyles = $this->getAllScriptsStyles( $content );
			$output = $this->scriptStlyesLoader( $content, $scriptsStyles );
			
			
			// Output the head/footer content
			echo $content;
			// $this->pageToCache .= $content;
			
			// Output the compressed stuff
			if ( ! empty( $output['js']['url'] ) ) {
				echo "<script type='text/javascript' src='" . esc_url( $output['js']['url'] ) . "'></script>";
			}
			if ( ! empty( $output['css']['url'] ) ) {
				echo "<link rel='stylesheet' id='css_combinator_" . esc_attr( $output['css']['hash'] ) . "-css' href='" . esc_url( $output['css']['url'] ) . "' type='text/css' media='all' />";
			}

		}
		
		
		/**
		 * Generates a combined version of the scripts and styles $scriptsStyles and
		 * removes the replaced scripts from $content
		 *
		 * @param	&$content		String	The head or footer content to look for the scripts & styles
		 * @param	$scriptsStlyes	Array	The collection of scripts & styles as outputted
		 * 									by $this->getAllScriptsStyles()
		 * @return					Array	The URL, path and hash generated for the combined files
		 */
		public function scriptStlyesLoader( &$content, $scriptsStyles ) {

			
			/**
			 * JS
			 */
		
			$outputJS = '';
			if ( $this->settings['js_enabled'] ) {
			
				$compressionLevel = $this->settings['js_compression_level'];
			
			
				// Create hash
				$hash = serialize( $scriptsStyles['script_files'] );
				$hash .= serialize( $scriptsStyles['script_codes'] );
				$hash .= $compressionLevel;
				$hash = substr( md5( $hash ), 0, 8 );
			
			
				$files = $scriptsStyles['script_files'];
				$inline = implode( ';', $scriptsStyles['script_codes'] );
			
				$outputJS = get_transient( 'cmbntr_js' . $hash );
				if ( empty( $outputJS ) && ( count( $files ) || ! empty( $inline ) ) ) {
				
					$combined = GambitCombinatorJS::combineSources( $files, null, $inline );
				
					if ( $compressionLevel ) {
						$combined = GambitCombinatorJS::closureCompile( $combined, $compressionLevel );
					}
				
					$outputJS = GambitCombinatorJS::createFile(
						$combined,
						$hash . '.js'
					);
					$outputJS['hash'] = $hash;
					
					set_transient( 'cmbntr_js' . $hash, $outputJS, WEEK_IN_SECONDS );
				
				}
				
				// Adjust the content to remove the combined stuff
				foreach ( $scriptsStyles['script_file_tags'] as $i => $tag ) {
					$content = str_replace( $tag, '', $content );
				}
				foreach ( $scriptsStyles['script_code_tags'] as $i => $tag ) {
					$content = str_replace( $tag, '', $content );
				}
			
			}
			
			
			/**
			 * CSS
			 */
			
			$outputCSS = '';
			if ( $this->settings['css_enabled'] ) {
				
				$compressionLevel = $this->settings['css_compression_level'];
			
				// Create hash
				$hash = serialize( $scriptsStyles['style_files'] );
				$hash .= serialize( $scriptsStyles['style_codes'] );
				$hash .= $compressionLevel;
				$hash = substr( md5( $hash ), 0, 8 );
				
				
				$files = $scriptsStyles['style_files'];
				$inline = implode( '', $scriptsStyles['style_codes'] );
				
			
				$outputCSS = get_transient( 'cmbntr_css' . $hash );
				if ( empty( $outputCSS ) && ( count( $files ) || ! empty( $inline ) ) ) {
					$combined = GambitCombinatorCSS::combineSources( $files, null, $inline );
					
					if ( $compressionLevel ) {
						$combined = GambitCombinatorCSS::compile( $combined );
					}
			
					$outputCSS = GambitCombinatorCSS::createFile( 
						$combined, 
						$hash . '.css'
					);
					$outputCSS['hash'] = $hash;
					
					set_transient( 'cmbntr_css' . $hash, $outputCSS, WEEK_IN_SECONDS );
					
				}
				
				
				// Adjust the content to remove the combined stuff
				foreach ( $scriptsStyles['style_file_tags'] as $i => $tag ) {
					$content = str_replace( $tag, '', $content );
				}
				foreach ( $scriptsStyles['style_code_tags'] as $i => $tag ) {
					$content = str_replace( $tag, '', $content );
				}
			}
			
			
			return array(
				'js' => $outputJS,
				'css' => $outputCSS,
			);
			
		}
		
		
		/**
		 * Parses the given $content and gets all the scripts and styles from it
		 * 
		 * @param	$content	String	The content to parse
		 * @return				Array	The scripts and styles gathered
		 */
		public function getAllScriptsStyles( $content ) {
			
			$foundJSScripts = array();
			$foundCSSStyles = array();
			
			
			/**
			 * Blacklisted plugins
			 */
			
			// Check if get_plugins() function exists. This is required on the front end of the
			// site, since it is in a file that is normally only loaded in the admin.
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			
			// Get list of active plugins
			$activePluginSlugs = get_option( 'active_plugins' );
			$allPlugins = get_plugins();
			
			$blacklistedPluginSlugs = array();
			foreach ( $activePluginSlugs as $slug ) {
				if ( empty( $allPlugins[ $slug ] ) ) {
					continue;
				}
				
				if ( stripos( $allPlugins[ $slug ]['Name'], 'combinator' ) !== false ) {
					continue;
				}
				
				if ( in_array( $allPlugins[ $slug ]['Name'], $this->blackListedPlugins ) ) {
					$blacklistedPluginSlugs[] = $slug;
				}
			}
			
			// Add the black listed plugins to the excluded list
			$this->settings['exclude_plugins'] = array_merge( $this->settings['exclude_plugins'], $blacklistedPluginSlugs );
			$this->settings['exclude_plugins'] = array_unique( $this->settings['exclude_plugins'] );
			
			
			
			/**
			 * Excludes
			 */

			$excludesJS = '';
			if ( ! empty( $this->settings['js_exclude'] ) ) {
				$excludesJS = preg_replace( "/\n/", "|", $this->settings['js_exclude'] );
				$excludesJS = preg_replace( "/ /", "", $excludesJS );
			}
			if ( ! empty( $this->settings['exclude_plugins'] ) ) {
				foreach ( $this->settings['exclude_plugins'] as $slug ) {
					$excludesJS .= ! empty( $excludesJS ) ? '|' : '';
					$excludesJS .= str_replace( '/', '\\/', trim( trailingslashit( dirname( $slug ) ) ) );
				}
			}
			
			$excludesCSS = '';
			if ( ! empty( $this->settings['css_exclude'] ) ) {
				$excludesCSS = preg_replace( "/\n/", "|", $this->settings['css_exclude'] );
				$excludesCSS = preg_replace( "/ /", "", $excludesCSS );
			}
			if ( ! empty( $this->settings['exclude_plugins'] ) ) {
				foreach ( $this->settings['exclude_plugins'] as $slug ) {
					$excludesCSS .= ! empty( $excludesCSS ) ? '|' : '';
					$excludesCSS .= str_replace( '/', '\\/', trim( trailingslashit( dirname( $slug ) ) ) );
				}
			}
								
			
			// Remove commented out stuff since we don't want to include those
			$cleanedContent = preg_replace( "/<!--.*?-->/s", "", $content );
			
			
			/**
			 * JS files
			 */
			$scriptTagsToReplace = array();
			$scriptSrcs = array();
			$inlineScriptTagsToReplace = array();
			$inlineScriptCodes = array();
			
			preg_match_all( "/<script.*?\/script>\s?/s", $cleanedContent, $match );
			
			if ( count( $match ) ) {

				foreach ( $match[0] as $scriptTag ) {
					
					// Do not include backbone templates
					if ( preg_match( "/id=['\"]tmpl-/s", $scriptTag ) ) {
						continue;
					}
					
					// Get the URL
					preg_match( "/src=['\"](.*?)['\"]/s", $scriptTag, $src );
					if ( ! empty( $src[1] ) ) {
						$src = $src[1];
						
						// Keep note of what we found
						$foundJSScripts[] = preg_replace( "/(^.*:|\?.*$)/", "", $src );
					
						// Excludes
						if ( $excludesJS ) {
							try {
								if ( preg_match( '/(' . $excludesJS . ')/', $src ) ) {
									continue;
								}
							} catch ( Exception $e ) {
								// regex failed, ignore
							}
						}
						
						// Exclude scripts
						$excludeIt = false;
						foreach ( $this->settings['exclude_found_js'] as $excludeSrc ) {
							if ( stripos( $src, $excludeSrc ) !== false ) {
								$excludeIt = true;
								break;
							}
						}
						if ( $excludeIt ) {
							continue;
						}
						
				
						// Filter depending on settings
						if ( strpos( $src, content_url() ) ) {
						} else if ( strpos( $src, includes_url() ) !== false ) {
							if ( ! $this->settings['js_include_includes'] ) {
								continue;
							}
						} else if ( ! $this->settings['js_include_remote'] ) {
							continue;
						}
						
						$scriptTagsToReplace[] = $scriptTag;
						$scriptSrcs[] = $src;
						continue;
					}
					
			
					if ( $this->settings['js_include_inline'] ) {
						preg_match( "/<script.*?>(.*?)<\/script>/s", $scriptTag, $code );
						if ( ! empty( $code[1] ) ) {
							$inlineScriptTagsToReplace[] = $scriptTag;
							$inlineScriptCodes[] = trim( $code[1] );
						}
					}
					
				}

			}
			
			
			
			/**
			 * CSS files
			 */
			$linkTagsToReplace = array();
			$linkSrcs = array();
			
			preg_match_all( "/<link.*?href=.*?\/>\s?/s", $cleanedContent, $match );
			
			if ( count( $match ) ) {

				foreach ( $match[0] as $linkTag ) {
					if ( ! preg_match( "/rel=['\"]stylesheet['\"]/s", $linkTag ) ) {
						continue;
					}
					if ( ! preg_match( "/media=['\"]all['\"]/s", $linkTag ) ) {
						continue;
					}
					if ( ! preg_match( "/href=['\"](.*?)['\"]/s", $linkTag, $src ) ) {
						continue;
					}
					
					// Get the URL
					if ( ! empty( $src[1] ) ) {
						$src = $src[1];
						
						// Keep note of what we found
						$foundCSSStyles[] = preg_replace( "/(^.*:|\?.*$)/", "", $src );

						// Excludes
						if ( $excludesCSS ) {
							try {
								if ( preg_match( '/(' . $excludesCSS . ')/', $src ) ) {
									continue;
								}
							} catch ( Exception $e ) {
								// regex failed, ignore
							}
						}
						
						// Exclude scripts
						$excludeIt = false;
						foreach ( $this->settings['exclude_found_css'] as $excludeSrc ) {
							if ( stripos( $src, $excludeSrc ) !== false ) {
								$excludeIt = true;
								break;
							}
						}
						if ( $excludeIt ) {
							continue;
						}
						
						// Filter depending on settings
						if ( strpos( $src, content_url() ) ) {
						} else if ( strpos( $src, includes_url() ) !== false ) {
							if ( ! $this->settings['css_include_includes'] ) {
								continue;
							}
						} else if ( ! $this->settings['css_include_remote'] ) {
							continue;
						}
						
						$linkTagsToReplace[] = $linkTag;
						$linkSrcs[] = $src;
					}
				}
			}
			
			
			/**
			 * CSS style tags
			 */
			$styleTagsToReplace = array();
			$styleCodes = array();
			
			if ( $this->settings['css_include_inline'] ) {
				
				preg_match_all( "/<style.*?>.*?<\/style>\s?/s", $cleanedContent, $match );
			
				if ( count( $match ) ) {

					foreach ( $match[0] as $styleTag ) {
						if ( ! preg_match( "/<style.*?>(.*?)<\/style>/s", $styleTag, $code ) ) {
							continue;
						}
						if ( ! empty( $code[1] ) ) {
							$styleTagsToReplace[] = $styleTag;
							$styleCodes[] = trim( $code[1] );
						}
					}
				}
				
			}
			
			
			
			/**
			 * Merge the scripts & styles we found
			 */
			$currentFoundJS = get_option( 'combinator_found_js' );
			if ( empty( $currentFoundJS ) ) {
				$currentFoundJS = array();
			} else if ( is_serialized( $currentFoundJS ) ) {
				$currentFoundJS = unserialize( $currentFoundJS );
			}
			$currentFoundJS = array_merge( $currentFoundJS, $foundJSScripts );
			$currentFoundJS = array_unique( $currentFoundJS );
			update_option( 'combinator_found_js', serialize( $currentFoundJS ) );

			$currentFoundCSS = get_option( 'combinator_found_css' );
			if ( empty( $currentFoundCSS ) ) {
				$currentFoundCSS = array();
			} else if ( is_serialized( $currentFoundCSS ) ) {
				$currentFoundCSS = unserialize( $currentFoundCSS );
			}
			$currentFoundCSS = array_merge( $currentFoundCSS, $foundCSSStyles );
			$currentFoundCSS = array_unique( $currentFoundCSS );
			update_option( 'combinator_found_css', serialize( $currentFoundCSS ) );
			
			
			return array(
				'script_file_tags' => $scriptTagsToReplace,
				'script_files' => $scriptSrcs,
				'script_code_tags' => $inlineScriptTagsToReplace,
				'script_codes' => $inlineScriptCodes,
				'style_file_tags' => $linkTagsToReplace,
				'style_files' => $linkSrcs,
				'style_code_tags' => $styleTagsToReplace,
				'style_codes' => $styleCodes,
			);
		}
		
		
		/**
		 * Populates the $this->settings variable with all the settings from the admin panel
		 *
		 * @return	void
		 */
		public function gatherSettings() {

			if ( ! class_exists( 'TitanFramework' ) ) {
				return;
			}
			
			$this->blackListedPlugins = apply_filters( 'gc_blacklisted_plugins', array() );

			$titan = TitanFramework::getInstance( GAMBIT_COMBINATOR );
			
			$this->settings['global_enabled'] = $titan->getOption( 'global_enabled' );
			$this->settings['js_enabled'] = $titan->getOption( 'js_enabled' );
			$this->settings['css_enabled'] = $titan->getOption( 'css_enabled' );
			$this->settings['exclude_plugins'] = $titan->getOption( 'exclude_plugins' );
			$this->settings['exclude_found_js'] = $titan->getOption( 'exclude_found_js' );
			$this->settings['exclude_found_css'] = $titan->getOption( 'exclude_found_css' );
			$this->settings['js_compression_level'] = $titan->getOption( 'js_compression_level' );
			$this->settings['css_compression_level'] = $titan->getOption( 'css_compression_level' );
			
			if ( is_array( $titan->getOption( 'js_includes' ) ) ) {
				$this->settings['js_include_includes'] = in_array( 'includes', $titan->getOption( 'js_includes' ) );
				$this->settings['js_include_remote'] = in_array( 'remote', $titan->getOption( 'js_includes' ) );
				$this->settings['js_include_inline'] = in_array( 'inline', $titan->getOption( 'js_includes' ) );
			}
			$this->settings['js_exclude'] = $titan->getOption( 'js_exclude' );
			
			if ( is_array( $titan->getOption( 'css_includes' ) ) ) {
				$this->settings['css_include_includes'] = in_array( 'includes', $titan->getOption( 'css_includes' ) );
				$this->settings['css_include_remote'] = in_array( 'remote', $titan->getOption( 'css_includes' ) );
				$this->settings['css_include_inline'] = in_array( 'inline', $titan->getOption( 'css_includes' ) );
			}
			$this->settings['css_exclude'] = $titan->getOption( 'css_exclude' );
			
			
			/**
			 * Blacklisted plugins are always included
			 */
			
			// Check if get_plugins() function exists. This is required on the front end of the
			// site, since it is in a file that is normally only loaded in the admin.
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			
			// Get list of active plugins
			$activePluginSlugs = get_option( 'active_plugins' );
			$allPlugins = get_plugins();
			
			// $blacklistedPluginSlugs = array();
			foreach ( $activePluginSlugs as $slug ) {
				if ( empty( $allPlugins[ $slug ] ) ) {
					continue;
				}
				
				if ( stripos( $allPlugins[ $slug ]['Name'], 'combinator' ) !== false ) {
					continue;
				}
				
				if ( in_array( $allPlugins[ $slug ]['Name'], $this->blackListedPlugins ) ) {
					$this->settings['exclude_plugins'][] = $slug;
				}
			}
			$this->settings['exclude_plugins'] = array_unique( $this->settings['exclude_plugins'] );
			
			if ( $this->settings['exclude_plugins'] != $titan->getOption( 'exclude_plugins' ) ) {
				$titan->setOption( 'exclude_plugins', $this->settings['exclude_plugins'] );
				$titan->saveOptions();
			}
		}
		
		
		/**
		 * Clears the generated files and transients
		 * Ajax handler for the settings
		 *
		 * @return	void
		 */
		public function clearCache() {
			if ( empty( $_REQUEST['nonce'] ) ) {
				wp_send_json_error();
			}
			
			if ( wp_verify_nonce( $_REQUEST['nonce'], 'combinator_clear_cache' ) ) {
			    $this->deleteAllCaches();
				$this->deleteAllCaches();
			}
			
			wp_send_json_success();
		}
		
		
		/**
		 * Delete all the uploads folder files
		 *
		 * @return	void
		 */
		public function deleteAllFiles() {
			GambitCombinatorFiles::deleteAllFiles();
		}
		
		
		/**
		 * Deletes all the transient database data
		 *
		 * @return	void
		 */
		public function deleteAllCaches() {
			global $wpdb;

			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%' AND option_name LIKE '%cmbntr%'" );
		}
		
		
		/**
		 * Check whether we are currently in the frontend
		 * Simply doing ! is_admin() doesn't cut it
		 *
		 * @return	void
		 */
		public function isFrontEnd() {
			if ( is_admin() ) {
				return false;
			}
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
		
		
		/**
		 * Displays an admin notice when the uploads folder is unwritable
		 *
		 * @return	void
		 */
		public function adminNotices() {
			$isWritable = GambitCombinatorFiles::canWriteCombinatorFiles();

			if ( $isWritable !== true && get_option( 'combinator_notice_dismiss1' ) === false ) {
				echo "<div id='combinator_notice_1' class='error notice is-dismissible'><p><strong>" . __( "Combinator Error", GAMBIT_COMBINATOR ) . ":</strong> " . __( "We have a problem. We generate files and put them in our own subdirectory in your <code>uploads</code> folder for caching. Since it is unwritable, we cannot create our subdirectory on our own. Please make sure that this directory exists, and that it is writable by giving it permissions of <code>755</code>:<br>", GAMBIT_COMBINATOR ) . "<code>{$isWritable}</code></p></div>";
				echo "<script>jQuery(document).ready(function($) {
					$('body').on('click', '#combinator_notice_1 .notice-dismiss', function() {
						wp.ajax.send( 'combinator_notice_dismiss', {
							data: {
								nonce: '" . wp_create_nonce( 'combinator_notice_dismiss' ) . "'
							}
						}); 
					});
				});</script>";
			}
		}
		
		
		/**
		 * Dismiss handler for the admin notice
		 *
		 * @return	void
		 */
		public function dismissAdminNotice() {
			if ( empty( $_REQUEST['nonce'] ) ) {
				wp_send_json_error();
			}
			
			if ( wp_verify_nonce( $_REQUEST['nonce'], 'combinator_notice_dismiss' ) ) {
			    update_option( 'combinator_notice_dismiss1', 1 );
			}
			
			wp_send_json_success();
		}
	
	}

	new GambitCombinator();
	
}