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

	if ( preg_match( '/wp\-.*\.php/', $url ) ) {
		return;
	}

	$url = preg_replace( '/\#.*$/', '', $url );
	$pageHash = substr( md5( $url ), 0, 8 );

	$html = $gambitPageCache->get( $pageHash );

	if ( $html ) {
		echo $html;
		echo "<!-- Cached by Combinator -->";
		die();
	}
	
// } else {
//
// 	class GambitAdvancedCache { } // Dummy, used for checking whether advanced-cache.php is ours
//
// }