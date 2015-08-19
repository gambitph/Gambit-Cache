<?php

// TODO minify, combine Google Font css with pipe
// <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,600,700,700italic|Roboto:400,500' rel='stylesheet' type='text/css'>
	
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'GambitCacheMinify' ) ) {

	class GambitCacheMinify {
	
		public $gatheringStarted = false;
	
		/**
		 * These plugins have been proven to be NOT working when combined
		 */
		public $blackListedPlugins = array(
			'Facebook Like Box',
			'PopTrends',
			'Smooth MouseWheel',
		);
		
		public $settings = array(
			'minify_enabled' => true,
			'exclude_plugins' => array(),
			'exclude_found_js' => array(),
			'exclude_found_css' => array(),
			
			'js_enabled' => true,
			'js_compression_level' => 2,
			'js_include_theme' => false,
			'js_include_includes' => true,
			'js_include_remote' => true,
			'js_include_inline' => false,
			'js_exclude' => 'jquery.js',
			
			'css_compression_level' => 1,
			'css_enabled' => true,
			'css_include_theme' => false,
			'css_include_includes' => true,
			'css_include_remote' => true,
			'css_include_inline' => false,
			'css_exclude' => 'googleapis',
		);

		function __construct() {
			add_action( 'tf_done', array( $this, 'gatherSettings' ), 10 );

			apply_filters( 'gc_blacklisted_plugins', array( $this, 'addBlackListedPlugins' ), 1 );
				

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				return;
			}


			add_action( 'wp_head', array( $this, 'startGatheringOutput' ), 0 );
			add_action( 'wp_head', array( $this, 'endGatheringOutput' ), 9999 );

			add_action( 'wp_footer', array( $this, 'startGatheringOutput' ), 0 );
			add_action( 'wp_footer', array( $this, 'endGatheringOutput' ), 9999 );
		}
		
		public function addBlackListedPlugins( $plugins ) {
			$plugins = array_merge( $plugins, $this->blackListedPlugins );
			return array_unique( $plugins );
		}
		
		
	
		public static function clearMinifyCache() {
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
			    require_once ( ABSPATH . '/wp-admin/includes/file.php' );
			    WP_Filesystem();
			}

			$upload_dir = wp_upload_dir(); // Grab uploads folder array
			$dir = trailingslashit( $wp_filesystem->wp_content_dir() . 'gambit-cache' ) . 'minify-cache';
		
			if ( $wp_filesystem->is_dir( $dir ) ) {
				if ( $wp_filesystem->is_writable( $dir ) ) {
					if ( $wp_filesystem->rmdir( $dir, true ) ) {
						$wp_filesystem->mkdir( $dir );
						return true;
					}
				}
			}
			
			return false;
		}
		
		
		/**
		 * Populates the $this->settings variable with all the settings from the admin panel
		 *
		 * @return	void
		 */
		public function gatherSettings() {
			
			$this->blackListedPlugins = apply_filters( 'gc_blacklisted_plugins', array() );

			$titan = TitanFramework::getInstance( GAMBIT_COMBINATOR );
			
			$this->settings['minify_enabled'] = $titan->getOption( 'minify_enabled' );
			$this->settings['js_enabled'] = $titan->getOption( 'js_enabled' );
			$this->settings['css_enabled'] = $titan->getOption( 'css_enabled' );
			$this->settings['exclude_plugins'] = $titan->getOption( 'exclude_plugins' );
			$this->settings['exclude_found_js'] = $titan->getOption( 'exclude_found_js' );
			$this->settings['exclude_found_css'] = $titan->getOption( 'exclude_found_css' );
			$this->settings['js_compression_level'] = $titan->getOption( 'js_compression_level' );
			$this->settings['css_compression_level'] = $titan->getOption( 'css_compression_level' );
			
			if ( is_array( $titan->getOption( 'js_includes' ) ) ) {
				$this->settings['js_include_theme'] = in_array( 'theme', $titan->getOption( 'js_includes' ) );
				$this->settings['js_include_includes'] = in_array( 'includes', $titan->getOption( 'js_includes' ) );
				$this->settings['js_include_remote'] = in_array( 'remote', $titan->getOption( 'js_includes' ) );
				$this->settings['js_include_inline'] = in_array( 'inline', $titan->getOption( 'js_includes' ) );
			}
			$this->settings['js_exclude'] = $titan->getOption( 'js_exclude' );
			
			if ( is_array( $titan->getOption( 'css_includes' ) ) ) {
				$this->settings['css_include_theme'] = in_array( 'theme', $titan->getOption( 'js_includes' ) );
				$this->settings['css_include_includes'] = in_array( 'includes', $titan->getOption( 'css_includes' ) );
				$this->settings['css_include_remote'] = in_array( 'remote', $titan->getOption( 'css_includes' ) );
				$this->settings['css_include_inline'] = in_array( 'inline', $titan->getOption( 'css_includes' ) );
			}
			$this->settings['css_exclude'] = $titan->getOption( 'css_exclude' );
			$this->settings['css_exclude'] = trim( (string) $this->settings['css_exclude'] );
			
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
		 * Starts gathering outputted data. To be used in conjunction with $this->endGatheringOutput()
		 * If $this->endGatheringOutput() isn't called, no HTML will be rendered in the page.
		 *
		 * @return	void
		 */
		public function startGatheringOutput() {
			if ( ! $this->settings['minify_enabled'] ) {
				return;
			}
			if ( ! $this->settings['js_enabled'] && ! $this->settings['css_enabled'] ) {
				return;
			}
			if ( ! $this->isFrontEnd() ) {
				return;
			}
			$this->gatheringStarted = true;

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
			if ( ! $this->gatheringStarted ) {
				return;
			}
			
			$this->gatheringStarted = false;
			
			$content = ob_get_contents();//ob_get_contents();
			ob_end_clean();
			// ob_flush();
			
			
			// Get the scripts & output
			$scriptsStyles = $this->getAllScriptsStyles( $content );
			$output = $this->scriptStlyesLoader( $content, $scriptsStyles );

			// Combine Google Fonts
			$combinedGoogleFonts = $this->combineGoogleFonts( $content );
			
			// Output the head/footer content
			echo $content;
			
			// Output the combined Google Fonts
			if ( ! empty( $combinedGoogleFonts ) ) {
				// Need to encode the space into + or %20 since esc_url removes spaces.
				// @see bug https://core.trac.wordpress.org/ticket/23605
				echo "<link rel='stylesheet' id='gambit-cache-google-font-css' href='" . esc_url( str_replace( ' ', '+', $combinedGoogleFonts ) ) . "' type='text/css' media='all' />";
			}
			
			// Output the compressed stuff
			if ( ! empty( $output['js']['url'] ) ) {
				echo "<script type='text/javascript' src='" . esc_url( $output['js']['url'] ) . "'></script>";
			}
			if ( ! empty( $output['css']['url'] ) ) {
				echo "<link rel='stylesheet' id='css_combinator_" . esc_attr( $output['css']['hash'] ) . "-css' href='" . esc_url( $output['css']['url'] ) . "' type='text/css' media='all' />";
			}

		}
		
		
		/**
		 * Generates a combined url of all the Google Fonts in the $content, also
		 * removes the replaced stylesheets from $content
		 *
		 * @param	&$content	String	The head or footer content to look for the scripts & styles
		 * @return				String	The URL of the combined Google Font stylesheet
		 */
		public function combineGoogleFonts( &$content ) {
			
			// Remove commented out stuff since we don't want to include those
			$cleanedContent = preg_replace( "/<!--.*?-->/s", "", $content );
			
			$googleFontArgs = array(
				'family' => array(),
				'subset' => array()
			);
			
			$googleFontsJoined = array();

			preg_match_all( "/<link[^>]+fonts\.googleapis\.com[^>]+>/s", $cleanedContent, $matches );
			if ( ! empty( $matches[0] ) ) {
				foreach ( $matches[0] as $match ) {

					$cleanedMatch = html_entity_decode( urldecode( $match ) );
					preg_match( "/href=['\"][^\?'\"]+\?([^'\"]+)['\"]/", $cleanedMatch, $fontArgs );

					if ( ! empty( $fontArgs[1] ) ) {
						$fontArgs = $fontArgs[1];
						
						$fontArgs = wp_parse_args( $fontArgs );
						
						foreach ( $fontArgs as $key => $fontArg ) {
							if ( $key == 'family' ) {
								
								$googleFontArgs[ $key ][] = $fontArg;
								// Remember that we combined this family
								$googleFontsJoined[] = $match;
								
							} else if ( $key == 'subset' ) {
								
								$fontArg = trim( $fontArg );
								if ( ! empty( $fontArg ) ) {
									$googleFontArgs[ $key ] = array_merge( explode( ',', $fontArg ), $googleFontArgs[ $key ] );
								}
								
							} else if ( $key == 'ver' ) {
								// ignore ver param

							} else {
								if ( empty( $googleFontArgs[ $key ] ) ) {
									$googleFontArgs[ $key ] = $fontArg;
								} else {
									$googleFontArgs[ $key ] .= ',' . $fontArg;
								}
							}
						}
						
					}
				}
			}
			
			// If no font families were joined, do nothing
			if ( empty( $googleFontArgs['family'] ) ) {
				return false;
			}

			$googleFontArgs['family'] = array_unique( $googleFontArgs['family'] );
			$googleFontArgs['subset'] = array_unique( $googleFontArgs['subset'] );
			
			$googleFontArgs['family'] = implode( '|', $googleFontArgs['family'] );
			$googleFontArgs['subset'] = implode( ',', $googleFontArgs['subset'] );
			
			// Adjust the content to remove the combined stuff
			foreach ( $googleFontsJoined as $i => $tag ) {
				$content = str_replace( $tag, '', $content );
			}
			
			if ( empty( $googleFontArgs['subset'] ) ) {
				unset( $googleFontArgs['subset'] );
			}
			
			// Return the combined Google Font URL
			return add_query_arg( $googleFontArgs, '//fonts.googleapis.com/css' );
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
						if ( strpos( $src, get_theme_root_uri() ) !== false ) {
							if ( ! $this->settings['js_include_theme'] ) {
								continue;
							}
						} else if ( strpos( $src, content_url() ) ) {
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
						if ( strpos( $src, get_theme_root_uri() ) !== false ) {
							if ( ! $this->settings['css_include_theme'] ) {
								continue;
							}
						} else if ( strpos( $src, content_url() ) ) {
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
		
	}

}