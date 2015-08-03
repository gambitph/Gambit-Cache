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

class ScriptCombiner {
	
	const SECRET_KEY = "SuperSecretGambitKey";
	
	public $headScripts = array();
	public $footerScripts = array();
	public $headStyles = array();
	public $footerStyles = array();
	public $inHead = true;
	public $test = array();
	
	function __construct() {
		
		add_filter( 'script_loader_tag', array( $this, 'gatherEnqueuedScripts' ), 999, 3 );
		add_action( 'wp_footer', array( $this, 'footerScriptLoader' ), 999 );
		add_action( 'wp_head', array( $this, 'headScriptLoader' ), 999 );
	
		add_filter( 'style_loader_tag', array( $this, 'gatherEnqueuedStyles' ), 999, 2 );
		add_action( 'wp_footer', array( $this, 'footerStyleLoader' ), 999 );
		add_action( 'wp_head', array( $this, 'headStyleLoader' ), 999 );
		
		// add_action( 'wp_ajax_combinator_scripts', array( $this, 'combineScripts' ) );
		// add_action( 'wp_ajax_nopriv_combinator_scripts', array( $this, 'combineScripts' ) );
		
		
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
        $data = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, self::SECRET_KEY, $data, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND));
		return base64_encode( $data );
	}
	
	
	public function gatherEnqueuedScripts( $tag, $handle, $src ) {

		if ( ! $this->isFrontEnd() ) {
			return $tag;
		}
		
		// Only do this for local wp-content files
		$path = '';
		if ( strpos( $src, content_url() ) !== false ) {
	
			// Get local path
			$path = str_replace( content_url(), WP_CONTENT_DIR, $src );
			
		
		// Only do this for local wp-include files
		} else if ( strpos( $src, includes_url() ) !== false ) {
	
			// Get local path
			$path = str_replace( trim( includes_url(), '/\\' ), ABSPATH . WPINC, $src );

		} else {
			// return $tag;
		}
	
		
		// Remove trailing arguments
		$path = preg_replace( '/\?.+$/', '', $path );
		
		// Check if file exists
		$path = realpath( $path );
		if ( ! $path && ! @is_file( $path ) ) {
			// return $tag;
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
	
	
	public function headScriptLoader() {
		if ( count( $this->headScripts ) ) {
			$data = $this->encodeLoadParam( $this->headScripts );
			// echo "<script type='text/javascript' src='" . esc_url( add_query_arg( array( 'c' => 1, 'm' => 2, 'load' => $data ), admin_url( 'admin-ajax.php?action=combinator_scripts' ) ) ) . "'></script>";
			echo "<script type='text/javascript' src='" . esc_url( add_query_arg( array( 'c' => 1, 'm' => 2, 'load' => $data ), plugins_url( 'class-load-scripts.php', __FILE__ ) ) ) . "'></script>";
		}
		$this->inHead = false;
	}
	

	public function footerScriptLoader() {
		if ( count( $this->footerScripts ) ) {
			$data = $this->encodeLoadParam( $this->footerScripts );
			echo "<script type='text/javascript' src='" . esc_url( add_query_arg( array( 'c' => 1, 'm' => 2, 'load' => $data ), plugins_url( 'class-load-scripts.php', __FILE__ ) ) ) . "'></script>";
		}
	}
	
	
	public function gatherEnqueuedStyles( $tag, $handle ) {

		if ( ! $this->isFrontEnd() ) {
			return $tag;
		}
		
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
			$path = str_replace( trim( includes_url(), '/\\' ), ABSPATH . WPINC, $src );
			
		} else {

			// return $tag;
		}
		
		// Remove trailing arguments
		$path = preg_replace( '/\?.+$/', '', $path );
		
		// Check if file exists
		$path = realpath( $path );
		if ( ! $path && ! @is_file( $path ) ) {
			// return $tag;
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

	
	
	public function headStyleLoader() {
		if ( count( $this->headStyles ) ) {
			$data = $this->encodeLoadParam( $this->headStyles );
			echo "<link rel='stylesheet' id='css_combiner-css' href='" . esc_url( add_query_arg( array( 'c' => 1, 'm' => 2, 'load' => $data ), plugins_url( 'class-load-styles.php', __FILE__ ) ) ) . "' type='text/css' media='all' />";
		}
		$this->inHead = false;
	}
	

	public function footerStyleLoader() {
		if ( count( $this->footerStyles ) ) {
			$data = $this->encodeLoadParam( $this->footerStyles );
			echo "<link rel='stylesheet' id='css_combiner-footer-css' href='" . esc_url( add_query_arg( array( 'c' => 1, 'm' => 2, 'load' => $data ), plugins_url( 'class-load-styles.php', __FILE__ ) ) ) . "' type='text/css' media='all' />";
		}
	}
}

new ScriptCombiner();