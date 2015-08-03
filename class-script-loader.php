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

require_once( 'class-js.php' );
require_once( 'class-css.php' );

class ScriptCombiner {
	
	const SECRET_KEY = "SuperSecretGambitKey";
	
	public $headScripts = array();
	public $footerScripts = array();
	public $headStyles = array();
	public $footerStyles = array();
	
	public $inHead = true;
	
	function __construct() {
		
		add_filter( 'script_loader_tag', array( $this, 'gatherEnqueuedScripts' ), 999, 3 );
		add_action( 'wp_footer', array( $this, 'footerScriptLoader' ), 999 );
		add_action( 'wp_head', array( $this, 'headScriptLoader' ), 999 );
	
		add_filter( 'style_loader_tag', array( $this, 'gatherEnqueuedStyles' ), 999, 2 );
		add_action( 'wp_footer', array( $this, 'footerStyleLoader' ), 999 );
		add_action( 'wp_head', array( $this, 'headStyleLoader' ), 999 );
		
		add_action( 'wp_head', array( $this, 'doneWithHead' ), 1000 );

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
		
		$enabled = true;
		
		if ( ! $enabled ) {
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
		
		$method = 1;
		$compressionLevel = 2;
		$gzip = 1;
		
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
			echo "<script type='text/javascript' src='" . esc_url( add_query_arg( array( 'c' => $gzip, 'm' => $compressionLevel, 'load' => $data ), plugins_url( 'fallback/class-load-scripts.php', __FILE__ ) ) ) . "'></script>";
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
		
		$enabled = true;
		
		if ( ! $enabled ) {
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
		
		$method = 1;
		$compressionLevel = 1;
		$gzip = 0;
		
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
			echo "<link rel='stylesheet' id='css_combinator_" . esc_attr( $hash ) . "-css' href='" . esc_url( add_query_arg( array( 'c' => $gzip, 'm' => $compressionLevel, 'load' => $data ), plugins_url( 'fallback/class-load-styles.php', __FILE__ ) ) ) . "' type='text/css' media='all' />";
		
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

new ScriptCombiner();