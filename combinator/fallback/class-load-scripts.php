<?php

/**
 * Disable error reporting
 *
 * Set this to error_reporting( -1 ) for debugging.
 */
error_reporting(0);

require_once( '../lib/class-js.php' );

define( 'SECRET_KEY', "SuperSecretGambitKey" );

// TODO DO NOT USE wp-load, see http://ottopress.com/2010/dont-include-wp-load-please/
// TODO DO NOT USE file_get_contents, maybe use wp_remote_fopen insead? But we'll need to get the URLs instead of paths
// SUGGESTION pass URLS in the url (there's a 1000-2000 character limit, find a way to make this shorter)

// if(!session_id()) {
//     session_start();
// }
// var_dump($_SESSION);

/**
 * Include a mini version of wp-load
 * @see http://frankiejarrett.com/the-simplest-way-to-require-include-wp-load-php/
 */
// define( 'SHORTINIT', true );

// $parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );
// require_once( $parse_uri[0] . 'wp-load.php' );

// require_once( 'class-wp_remote_fopen.php' );
// var_dump(wp_remote_fopen( 'http://local.wordpress.dev/wp-includes/js/jquery/jquery.js?ver=1.11.2' ) );
// // var_dump( wp_remote_fopen( $_SESSION['scriptHandles']) );
// die();

/**
 * Code is mainly from wp-admin/load-scripts.php
 * @see wp-admin/load-scripts.php
 */
// function get_file($path) {
//
// 	if ( function_exists('realpath') )
// 		$path = realpath($path);
//
// 	if ( ! $path || ! @is_file($path) )
// 		return '';
//
// 	return @file_get_contents($path);
// }


/**
 * Get the stuff to load
 */
$load = $_GET['load'];



try {
	if ( ! empty( $load ) ) {
		
		// Decrypt the filenames
		$load = str_replace( ' ', '+', $load );
		$load = base64_decode( $load );
		$load = mcrypt_decrypt( MCRYPT_RIJNDAEL_256, SECRET_KEY, $load, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND));

		$load = unserialize( gzinflate( $load ) );
		
		$load = array_unique( $load );
	}
} catch ( Exception $e ) {
	exit;
}


if ( empty( $load ) ) {
	exit;
}



/**
 * Header variables from load-scripts.php
 */
$compress = ( isset($_GET['c']) && $_GET['c'] );
$force_gzip = ( $compress && 'gzip' == $_GET['c'] );
$expires_offset = 604800; // 1 year
$out = '';


/**
 * Include each handle src
 */
require_once( 'class-wp_remote_fopen.php' );
$out = GambitCombinatorJS::combineSources( $load );
// foreach( $load as $src ) {
// // var_dump($_SERVER);
// 	// $src = preg_replace( '/[^a-z0-9,_-\?=\&:\/]+/i', '', $src );
//
// 	if ( stripos( $src, '//' ) === 0 ) {
// 		$src = $_SERVER['REQUEST_SCHEME'] . ':' . $src;
// 	}
//
// 	// if ( stripos( $src, $_SERVER['HTTP_HOST'] ) !== false ) {
// 		$out .= wp_remote_fopen( $src ) . "\n;";
// 	// }
// 	// $path = get_option( 'js_combiner_' . $handle, true );
// 	//
// 	// if ( ! empty( $path ) ) {
// 	// 	$out .= get_file( $path ) . "\n;";
// 	// }
// }
// die();


/**
 * Write the combined output
 */
// var_dump($_SERVER['HTTP_IF_NONE_MATCH']);
// var_dump( $_SERVER );
// die();

$gmt_mtime = gmdate('r', time());
$expires_offset = 604800;
$etag = md5( $out . ( ! empty( $_GET['m'] ) ? '1' : '' ) );

header('Content-Type: application/javascript; charset=UTF-8');
header('ETag: "'. $etag .'"');
header('Expires: ' . gmdate( "D, d M Y H:i:s", time() + $expires_offset ) . ' GMT');
header('Last-Modified: '.$gmt_mtime);
header("Cache-Control: public, max-age=$expires_offset");

// }

// caching_headers ( md5( $out ) );//filemtime($_SERVER['SCRIPT_FILENAME']));

// header('Content-Type: application/javascript; charset=UTF-8');
// header('Expires: ' . gmdate( "D, d M Y H:i:s", time() + $expires_offset ) . ' GMT');
// header("Cache-Control: public, max-age=$expires_offset");
// header('ETag: "' . md5( $out ) . '"' );

// if( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) || isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
//     if ( $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == md5($timestamp.$file)) {
//         header('HTTP/1.1 304 Not Modified');
//         exit();
//     }
// }



if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
    if ( $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime 
		 || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == $etag ) {
        header('HTTP/1.1 304 Not Modified');
        exit();
    }
}





if ( ! empty( $_GET['m'] ) ) {
	
	// $out = GambitCombinatorJS::closureCompile( $out, $_GET['m'] );
	
}


if ( $compress && ! ini_get('zlib.output_compression') && 'ob_gzhandler' != ini_get('output_handler') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) ) {
	header('Vary: Accept-Encoding'); // Handle proxies
	if ( false !== stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') && function_exists('gzdeflate') && ! $force_gzip ) {
		header('Content-Encoding: deflate');
		$out = gzdeflate( $out, 3 );
	} elseif ( false !== stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') && function_exists('gzencode') ) {
		header('Content-Encoding: gzip');
		$out = gzencode( $out, 3 );
	}
}


echo $out;
exit;