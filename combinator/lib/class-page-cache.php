<?php
	
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'GambitCachePageCache' ) ) {

	class GambitCachePageCache {
		
		public $pageCacheEnabled = true;
		public $expiration = 86400;

		function __construct() {
			
			add_action( 'wp_ajax_user_clear_page_cache', array( __CLASS__, 'ajaxClearPageCache' ) );
			
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				return;
			} 
			
			add_action( 'plugins_loaded', array( $this, 'startRecordingPage' ), -1 );
			add_action( 'shutdown', array( $this, 'endRecordingPage' ), 0 );
			
			add_action( 'tf_done', array( $this, 'gatherSettings' ), 10 );
			
		}
		
		public function gatherSettings() {
			$titan = TitanFramework::getInstance( GAMBIT_COMBINATOR );
			
			$this->pageCacheEnabled = $titan->getOption( 'page_cache_enabled' );
			$this->expiration = $titan->getOption( 'page_cache_expiration' );

			global $gambitPageCache;
			if ( ! $this->pageCacheEnabled ) {
				$gambitPageCache->clean();
			}
		}
		
		public static function clearPageCache() {
			if ( self::ajaxClearPageCache() ) {
				wp_send_json_success( __( 'Page cache cleared', GAMBIT_COMBINATOR ) );
			}
			wp_send_json_error( __( 'Could not clear the page cache', GAMBIT_COMBINATOR ) );
		}
		
		public static function ajaxClearPageCache() {
			global $gambitPageCache;
			if ( ! empty( $gambitPageCache ) ) {
				$gambitPageCache->clean();
				return true;
			}
			return false;
		}
		
		public $cachingStarted = false;
		public $pageToCache = '';
		
	    /**
	      * Get the current Url taking into account Https and Port
	      * @link http://css-tricks.com/snippets/php/get-current-page-url/
	      * @version Refactored by @AlexParraSilva
	      */
		public static function getCurrentUrlHash() {
			$url  = isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http';
			$url .= '://' . $_SERVER['SERVER_NAME'];
			$url .= in_array( $_SERVER['SERVER_PORT'], array('80', '443') ) ? '' : ':' . $_SERVER['SERVER_PORT'];
			$url .= $_SERVER['REQUEST_URI'];
			
			$url = preg_replace( '/\#.*$/', '', $url );
			return substr( md5( $url ), 0, 8 );
		}
		
		
		public function startRecordingPage() {

			if ( is_admin() ) {
				return;
			}
			if ( is_user_logged_in() ) {
				return;
			}
			
			if ( $this->cachingStarted ) {
				return;
			}
			
			$this->cachingStarted = true;
			ob_start();

		}
	
		public function endRecordingPage() {
			
			if ( ! $this->cachingStarted ) {
				return;
			}
			
			$this->pageToCache = '';

		    // We'll need to get the number of ob levels we're in, so that we can iterate over each, collecting
		    // that buffer's output into the final output.
		    $levels = ob_get_level();

			// @see http://stackoverflow.com/questions/772510/wordpress-filter-to-modify-final-html-output/22818089#22818089
		    for ( $i = 0; $i < $levels; $i++ ) {
				$currentOb = ob_get_clean();
		        $this->pageToCache .= $currentOb;
		    }
			echo $this->pageToCache;
			
			if ( is_404() ) {
				return;
			}
			
			global $gambitPageCache;
			if ( ! empty( $gambitPageCache ) && $this->pageCacheEnabled ) {
				$pageHash = $this->getCurrentUrlHash();
		        $gambitPageCache->set( $pageHash, $this->pageToCache, $this->expiration );
			}
			
		}
	}

}	
	// global $gambitPageCache;
?>