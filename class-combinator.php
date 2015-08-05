<?php

// TODO
/**
8. Compress CSS

ADd checker for readability
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
			'exclude_plugins' => array(),
			'exclude_found_js' => array(),
			'exclude_found_css' => array(),
			
			'js_enabled' => true,
			'js_compression_level' => 2,
			'js_include_includes' => true,
			'js_include_remote' => true,
			'js_include_inline' => false,
			'js_exclude' => '',
			
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
			// Initializes settings panel
			add_filter( 'plugin_action_links', array( $this, 'pluginSettingsLink' ), 10, 2 );
			add_action( 'tf_create_options', array( $this, 'createAdminOptions' ) );
			add_action( 'tf_done', array( $this, 'gatherSettings' ), 10 );
			
			add_action( 'wp_head', array( $this, 'startGatheringOutput' ), 0 );
			add_action( 'wp_head', array( $this, 'endGatheringOutput' ), 9999 );
			
			add_action( 'wp_footer', array( $this, 'startGatheringOutput' ), 0 );
			add_action( 'wp_footer', array( $this, 'endGatheringOutput' ), 9999 );
			
			add_action( 'wp_ajax_combinator_clear_cache', array( $this, 'clearCache' ) );
			
			// Admin notice for when the uploads folder is unwritable
			add_action( 'admin_notices', array( $this, 'adminNotices' ) );
			add_action( 'wp_ajax_combinator_notice_dismiss', array( $this, 'dismissAdminNotice' ) );
		}
		
		
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
			
			$content = ob_get_contents();
			ob_end_clean();
			
			
			// Get the scripts & output
			$scriptsStyles = $this->getAllScriptsStyles( $content );
			$output = $this->scriptStlyesLoader( $content, $scriptsStyles );
			
			
			// Output the head/footer content
			echo $content;
			
			
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
			
			$pluginOptions = array();
			foreach ( $activePluginSlugs as $slug ) {
				if ( empty( $allPlugins[ $slug ] ) ) {
					continue;
				}
				
				if ( stripos( $allPlugins[ $slug ]['Name'], 'combinator' ) !== false ) {
					continue;
				}
				
				$pluginOptions[ $slug ] = $allPlugins[ $slug ]['Name'];
			}



			$adminPanel = $titan->createAdminPanel( array(
			    'name' => 'Combinator',
				'id' => GAMBIT_COMBINATOR,
			    'parent' => 'options-general.php',
			) );
			
			
			$adminPanel->createOption( array(
				'name' => __( 'General Settings', GAMBIT_COMBINATOR ),
				'type' => 'heading',
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Enable Combinator', GAMBIT_COMBINATOR ),
				'id' => 'global_enabled',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'You can enable or disable the combining of scripts and stylesheets globally with this setting.', GAMBIT_COMBINATOR ),
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Cache Control', GAMBIT_COMBINATOR ),
				'type' => 'note',
				'desc' => '<button id="combinator_cache_btn" name="action" class="button button-secondary" onclick="
					var t = jQuery(this);
  				wp.ajax.send( \'combinator_clear_cache\', {
					success: function() { t.text(\'' . __( 'Cleared Generated Files & Database Caches', GAMBIT_COMBINATOR ) . '\'); },
				    error:   function() { t.text(\'' . __( 'Something went wrong, try again', GAMBIT_COMBINATOR ) . '\'); },
  				    data: {
  				      nonce: \'' . wp_create_nonce( 'combinator_clear_cache' ) . '\'
  				    }
  				  }); 
				  jQuery(this).text(\'' . __( 'Clearing...', GAMBIT_COMBINATOR ) . '\');
    jQuery(this).blur(); return false;">' . __( 'Clear Generated Files & Database Caches', GAMBIT_COMBINATOR ) . '</button>
	<p class="description">If you are getting <code>Uncaught SyntaxError: Unexpected token :</code> errors in Javascript, this can usually be fixed by clearing the cache with this button.</p>'
			) );

			$adminPanel->createOption( array(
			    'type' => 'save',
			) );
			
			
			
			$adminPanel->createOption( array(
				'name' => __( 'Javascript Settings', GAMBIT_COMBINATOR ),
				'type' => 'heading',
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Combine Javascripts', GAMBIT_COMBINATOR ),
				'id' => 'js_enabled',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'Enable combining of Javascript files', GAMBIT_COMBINATOR ),
			) );
			
			$adminPanel->createOption( array(
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
			
			$adminPanel->createOption( array(
				'name' => __( 'What to Combine', GAMBIT_COMBINATOR ),
				'id' => 'js_includes',
				'type' => 'multicheck',
				'default' => array( 'includes', 'remote' ),
				'options' => array(
					'includes' => __( 'WordPress wp-include files', GAMBIT_COMBINATOR ),
					'remote' => __( 'Remote scripts', GAMBIT_COMBINATOR ),
					'inline' => __( 'Script tags (small chance to give errors)', GAMBIT_COMBINATOR ),
				),
				'desc' => __( 'Check the types of scripts to combine. Scripts from plugins & themes are always combined.', GAMBIT_COMBINATOR ),
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Exclude these Domains', GAMBIT_COMBINATOR ),
				'id' => 'js_exclude',
				'type' => 'textarea',
				'default' => '',
				'desc' => __( 'Enter a domain or part of a URL (one per line) that you want to exclude from the combination process.', GAMBIT_COMBINATOR ),
				'placeholder' => __( 'Enter a domain or part of a URL (one per line)', GAMBIT_COMBINATOR ),
			) );

			$adminPanel->createOption( array(
			    'type' => 'save',
			) );
			
			
			
			$adminPanel->createOption( array(
				'name' => __( 'CSS Settings', GAMBIT_COMBINATOR ),
				'type' => 'heading',
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Combine Stylesheets', GAMBIT_COMBINATOR ),
				'id' => 'css_enabled',
				'type' => 'enable',
				'default' => true,
				'desc' => __( 'Enable combining of stylesheets', GAMBIT_COMBINATOR ),
			) );
			
			$adminPanel->createOption( array(
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
			
			$adminPanel->createOption( array(
				'name' => __( 'What to Combine', GAMBIT_COMBINATOR ),
				'id' => 'css_includes',
				'type' => 'multicheck',
				'default' => array( 'includes', 'remote' ),
				'options' => array(
					'includes' => __( 'WordPress wp-include files', GAMBIT_COMBINATOR ),
					'remote' => __( 'Remote stylesheets', GAMBIT_COMBINATOR ),
					'inline' => __( 'Style tags (high chance to disrupt page styles)', GAMBIT_COMBINATOR ),
				),
				'desc' => __( 'Check the types of styles to combine. Styles from plugins & themes are always combined.', GAMBIT_COMBINATOR ),
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Exclude these Domains', GAMBIT_COMBINATOR ),
				'id' => 'css_exclude',
				'type' => 'textarea',
				'default' => 'googleapis',
				'desc' => __( 'Enter a domain or part of a URL (one per line) that you want to exclude from the combination process.', GAMBIT_COMBINATOR ),
				'placeholder' => __( 'Enter a domain or part of a URL (one per line)', GAMBIT_COMBINATOR ),
			) );

			$adminPanel->createOption( array(
			    'type' => 'save',
			) );
			
			
			
			$adminPanel->createOption( array(
				'name' => __( 'Exclusion Settings', GAMBIT_COMBINATOR ),
				'type' => 'heading',
			) );
			
			$adminPanel->createOption( array(
				'name' => __( 'Exclude these Plugins', GAMBIT_COMBINATOR ),
				'id' => 'exclude_plugins',
				'type' => 'multicheck',
				'default' => array(),
				'options' => $pluginOptions,
				'desc' => __( 'Combinator combines all scripts and styles it can find. If a plugin stops working because of the combination process, <strong>check the plugin here to exclude it</strong>.', GAMBIT_COMBINATOR ),
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
			
			$adminPanel->createOption( array(
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
			
			$adminPanel->createOption( array(
				'name' => __( 'Exclude Found Stylesheets', GAMBIT_COMBINATOR ),
				'id' => 'exclude_found_css',
				'type' => 'multicheck',
				'default' => array(),
				'options' => $cssOptions,
				'desc' => __( 'Here is a list of all the Stylesheet URLs Combinator has found. If the list below is empty, visit your site to populate it.<br><strong>Check the URL here to exclude it.</strong>', GAMBIT_COMBINATOR ),
			) );

			$adminPanel->createOption( array(
			    'type' => 'save',
			) );
			
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

			$titan = TitanFramework::getInstance( GAMBIT_COMBINATOR );
			
			$this->settings['global_enabled'] = $titan->getOption( 'global_enabled' );
			$this->settings['js_enabled'] = $titan->getOption( 'js_enabled' );
			$this->settings['css_enabled'] = $titan->getOption( 'css_enabled' );
			$this->settings['exclude_plugins'] = $titan->getOption( 'exclude_plugins' );
			$this->settings['exclude_found_js'] = $titan->getOption( 'exclude_found_js' );
			$this->settings['exclude_found_css'] = $titan->getOption( 'exclude_found_css' );
			$this->settings['js_compression_level'] = $titan->getOption( 'js_compression_level' );
			$this->settings['css_compression_level'] = $titan->getOption( 'css_compression_level' );
			
			$this->settings['js_include_includes'] = in_array( 'includes', $titan->getOption( 'js_includes' ) );
			$this->settings['js_include_remote'] = in_array( 'remote', $titan->getOption( 'js_includes' ) );
			$this->settings['js_include_inline'] = in_array( 'inline', $titan->getOption( 'js_includes' ) );
			$this->settings['js_exclude'] = $titan->getOption( 'js_exclude' );
			
			$this->settings['css_include_includes'] = in_array( 'includes', $titan->getOption( 'css_includes' ) );
			$this->settings['css_include_remote'] = in_array( 'remote', $titan->getOption( 'css_includes' ) );
			$this->settings['css_include_inline'] = in_array( 'inline', $titan->getOption( 'css_includes' ) );
			$this->settings['css_exclude'] = $titan->getOption( 'css_exclude' );
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