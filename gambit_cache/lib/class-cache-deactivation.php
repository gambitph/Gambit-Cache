<?php
	
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'GambitCacheDeactivation' ) ) {
	
register_deactivation_hook( GAMBIT_CACHE_PATH, array( 'GambitCacheDeactivation', 'removeChecker' ) );
register_deactivation_hook( GAMBIT_CACHE_PATH, array( 'GambitCacheDeactivation', 'moveCachingFiles' ) );
	
class GambitCacheDeactivation {
	
	public static function removeChecker() {
		delete_option( 'gambit_cache_setup_done' );
		delete_transient( 'gambit_cache_activation_errors' );
	}
	
	public static function moveCachingFiles() {
		global $wp_filesystem;
		WP_Filesystem( $_SERVER['REQUEST_URI'] );
		
		// Move object-cache.php
		$path = $wp_filesystem->wp_content_dir() . 'object-cache.php';
		$pathOut = $wp_filesystem->wp_content_dir() . '_object-cache.php';
		if ( $wp_filesystem->exists( $path ) ) {
			if ( ! $wp_filesystem->is_writable( $path ) ) {
				$wp_filesystem->chmod( $path, 0644 );
			}
			if ( $wp_filesystem->is_writable( $path ) ) {
				$wp_filesystem->move( $path, $pathOut, true );
			}
		}
		
		// Move advanced-cache.php
		$path = $wp_filesystem->wp_content_dir() . 'advanced-cache.php';
		$pathOut = $wp_filesystem->wp_content_dir() . '_advanced-cache.php';
		if ( $wp_filesystem->exists( $path ) ) {
			if ( ! $wp_filesystem->is_writable( $path ) ) {
				$wp_filesystem->chmod( $path, 0644 );
			}
			if ( $wp_filesystem->is_writable( $path ) ) {
				$wp_filesystem->move( $path, $pathOut, true );
			}
		}
		
		// Turn off WP_CACHE
		$path = $wp_filesystem->abspath() . 'wp-config.php';
		if ( $wp_filesystem->exists( $path ) ) {
			if ( ! $wp_filesystem->is_writable( $path ) ) {
				$wp_filesystem->chmod( $path, 0644 );
			}
			if ( $wp_filesystem->is_writable( $path ) ) {
				$content = self::removeWPCacheInConfig( $wp_filesystem->get_contents( $path ) );
				$wp_filesystem->put_contents( $path, $content );
			}
		}
		
		// Remove gambit-cache
		$path = $wp_filesystem->wp_content_dir() . 'gambit-cache';
		if ( $wp_filesystem->exists( $path ) ) {
			if ( ! $wp_filesystem->is_writable( $path ) ) {
				$wp_filesystem->chmod( $path, 0755 );
			}
			if ( $wp_filesystem->is_writable( $path ) ) {
				$wp_filesystem->rmdir( $path, true );
			}
		}

		// Clear transients
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%' AND option_name LIKE '%cmbntr%'" );
	}
	
	public static function removeWPCacheInConfig( $content ) {
		$content = preg_replace( '/(\/\*.*Added by Gambit Cache.*\n.*;)/', '', $content );
		$defineRegex = '/(.*)((\/\/)?)(.*define.*WP_CACHE.*)(false|true)(.*;)/';
		
		if ( preg_match( $defineRegex, $content ) ) {
			return preg_replace( $defineRegex, '$2$3$4false$6', $content );
		}
		return $content;
	}
	
}

}