<?php

/**
 * Disable error reporting
 *
 * Set this to error_reporting( -1 ) for debugging.
 */
error_reporting(-1);

define( 'SECRET_KEY', "SuperSecretGambitKey" );

// TODO DO NOT USE wp-load, see http://ottopress.com/2010/dont-include-wp-load-please/
// TODO DO NOT USE file_get_contents, maybe use wp_remote_fopen insead? But we'll need to get the URLs instead of paths
// SUGGESTION pass URLS in the url (there's a 1000-2000 character limit, find a way to make this shorter)


/**
 * Include a mini version of wp-load
 * @see http://frankiejarrett.com/the-simplest-way-to-require-include-wp-load-php/
 */
// define( 'SHORTINIT', true );
//
// $parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );
// require_once( $parse_uri[0] . 'wp-load.php' );


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

// var_dump($_SERVER);
// var_dump($load);
// die();


/**
 * Header variables from load-scripts.php
 */
$compress = ( isset($_GET['c']) && $_GET['c'] );
$force_gzip = ( $compress && 'gzip' == $_GET['c'] );
$expires_offset = 31536000; // 1 year
$out = '';


/**
 * Include each handle src
 */
require_once( 'class-wp_remote_fopen.php' );
foreach( $load as $src ) {

	if ( stripos( $src, '//' ) === 0 ) {
		$src = $_SERVER['REQUEST_SCHEME'] . ':' . $src;
	}
	// $out .= $src . "\n";
	// if ( stripos( $src, $_SERVER['HTTP_HOST'] ) !== false ) {
		 $out .= wp_remote_fopen( $src ) . "\n";
	// }
	// $path = get_option( 'css_combiner_' . $handle, true );
	//
	// if ( ! empty( $path ) ) {
	// 	$out .= get_file( $path ) . "\n;";
	// }
}


/**
 * Compress CSS
 * does not merge or dissolve stuff so as not to possibly destroy the output
 * @see https://gist.github.com/manastungare/2625128#file-css-compress-php
 */

if ( ! empty( $_GET['m'] ) ) {

	// Remove comments
	$out = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $out );

	// Remove space after colons
	$out = str_replace( ': ', ':', $out );

	// Remove whitespace
	$out = preg_replace( '/[\t\r\n]+/', '', $out );
	$out = preg_replace('/[\s]{2,}/', ' ', $out );

	// Remove spaces that might still be left where we know they aren't needed
	$out = preg_replace( "/\s*([\{\}>~:;,])\s*/", "$1", $out );
	
	// Remove last semi-colon in blocks
	$out = preg_replace( "/;\}/", "}", $out );

	// Shorten colors
	$out = preg_replace( "/#([0-9a-fA-F])\\1([0-9a-fA-F])\\2([0-9a-fA-F])\\3/", "#$1$2$3", $out );

	// Convert content CSS to glyphs
    function css_content_hex_to_glyph( $matches ) {
		return html_entity_decode( '&#x' . trim( $matches[1], '\\' ) . ';', 0, 'UTF-8' );
    }
	$out = preg_replace_callback( "/(?<=content:[\"'])(\\\[0-9a-fA-F]+)/", 'css_content_hex_to_glyph', $out );
	
}

/**
 * Write the combined output
 */

$gmt_mtime = gmdate('r', time());
$expires_offset = 604800;
$etag = md5( $out );
header('Content-Type: text/css; charset=UTF-8');
header('ETag: "'. $etag .'"');
header('Expires: ' . gmdate( "D, d M Y H:i:s", time() + $expires_offset ) . ' GMT');
header('Last-Modified: '.$gmt_mtime);
header("Cache-Control: public, max-age=$expires_offset");
// header('Content-Type: text/css; charset=UTF-8');
// header('Expires: ' . gmdate( "D, d M Y H:i:s", time() + $expires_offset ) . ' GMT');
// header("Cache-Control: public, max-age=$expires_offset");

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

if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
    if ( $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime 
		 || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == $etag ) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }
}

echo $out;
exit;