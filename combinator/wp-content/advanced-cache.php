<?php

if ( ! defined( 'ABSPATH' ) ) die();


// global $wpdb;

// $options = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'combinator_options'" );
// $options = maybe_unserialize( maybe_unserialize( $options ) );
// $pageCacheEnabled = ! empty( $options['page_cache_enabled'] ) ? $options['page_cache_enabled'] : false;
//
// if ( $pageCacheEnabled ) {

	class GambitAdvancedCache { }

	// return;
	require_once( 'gambit-cache/lib/phpfastcache.php' );

	$config = array(
		'default_chmod' => 0755,
		"storage" => 'auto',
		"fallback" => "files", // Doesn't work anymore, see if statement below

		"securityKey" => "auto",
		"htaccess" => true,
		"path" => ABSPATH . "wp-content/gambit-cache/page-cache",
	
		// "memcache" => array(
		// 	array( "127.0.0.1", 11211, 1 ),
		// ),
		//
		// "redis" => array(
		// 	"host" => "127.0.0.1",
		// 	"port" => "",
		// 	"password" => "",
		// 	"database" => "",
		// 	"timeout" => ""
		// ),
	);
	
	global $gambitPageCache;
	phpFastCache::setup( $config );
	$gambitPageCache = phpFastCache( "files" );
	// $gambitPageCache->option( 'path', ABSPATH . "wp-content/gambit-cache/page-cache" );


	$wpLoggedInCookie = false;
	foreach ( $_COOKIE as $key => $cookie ) {
		if ( preg_match( "/^wordpress_logged_in/", $key ) ) {
			$wpLoggedInCookie = true;
			break;
		}
	}
	if ( $wpLoggedInCookie || ! empty( $_POST ) ) {
		return;
	}

	$url  = isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http';
	$url .= '://' . $_SERVER['SERVER_NAME'];
	$url .= in_array( $_SERVER['SERVER_PORT'], array('80', '443') ) ? '' : ':' . $_SERVER['SERVER_PORT'];
	$url .= $_SERVER['REQUEST_URI'];

	if ( preg_match( '/\/wp\-/', $url ) ) {
		return;
	}

	$url = preg_replace( '/\#.*$/', '', $url );
	$pageHash = substr( md5( $url ), 0, 8 );

	$html = $gambitPageCache->get( $pageHash );

	if ( $html ) {
		// $headers = $gambitPageCache->get( $pageHash . '_headers' );
		
		
		// Enable gzip compression
		// if ( ! empty( $_SERVER['HTTP_ACCEPT_ENCODING'] ) && substr_count( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) ) {
// 			if ( ob_start( 'ob_gzhandler' ) ) {
// 				ob_start();
// 			}
// 		} else {
// 			ob_start();
// 		}
		header( "Content-type: text/html; charset=UTF-8" );
		header( "Vary: Accept-Encoding, Cookie" );
		header( "Cache-Control: max-age=3, must-revalidate" );
		

		$size = function_exists( 'mb_strlen' ) ? mb_strlen( $html, '8bit' ) : strlen( $html );
		if ( function_exists( 'gzencode' ) ) {
			header( 'Content-Encoding: gzip' );
		}
		header( 'Content-Length: ' . $size );
		
		
		// SUPER CACHE DOES THIS

		// don't try to match modified dates if using dynamic code.
		// if ( $wp_cache_mfunc_enabled == 0 && $wp_supercache_304 ) {
// 			if ( function_exists( 'apache_request_headers' ) ) {
// 				$request = apache_request_headers();
// 				$remote_mod_time = $request[ 'If-Modified-Since' ];
// 			} else {
// 				if ( isset( $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] ) )
// 					$remote_mod_time = $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ];
// 				else
// 					$remote_mod_time = 0;
// 			}
// 			$local_mod_time = gmdate("D, d M Y H:i:s",filemtime( $file )).' GMT';
// 			if ( $remote_mod_time != 0 && $remote_mod_time == $local_mod_time ) {
// 				header("HTTP/1.0 304 Not Modified");
// 				exit();
// 			}
// 			header( 'Last-Modified: ' . $local_mod_time );
// 		}
		
		
		// if ( is_array( $headers ) ) {
		// 	foreach ( $headers as $header ) {
		// 		header( $header );
		// 	}
		// }
		echo $html;
		// var_dump(function_exists( 'gzencode' ), $pageHash);
		// echo "<!-- Cached by Combinator -->";
		die();
	}
	
// } else {
//
// 	class GambitAdvancedCache { } // Dummy, used for checking whether advanced-cache.php is ours
//
// }