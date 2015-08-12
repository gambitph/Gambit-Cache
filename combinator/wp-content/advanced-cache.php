<?php

if ( ! defined( 'ABSPATH' ) ) die();


class GambitAdvancedCache {

}



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
// return;
require_once( 'gambit-cache/lib/phpfastcache.php' );
phpFastCache::setup("storage","auto");

$url  = isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http';
$url .= '://' . $_SERVER['SERVER_NAME'];
$url .= in_array( $_SERVER['SERVER_PORT'], array('80', '443') ) ? '' : ':' . $_SERVER['SERVER_PORT'];
$url .= $_SERVER['REQUEST_URI'];

if ( preg_match( '/wp\-.*\.php/', $url ) ) {
	return;
}

$url = preg_replace( '/\#.*$/', '', $url );

$pageHash = substr( md5( $url ), 0, 8 );

$html = __c("files")->get( $pageHash );

if ( $html ) {
	echo $html;
	echo "<!-- Cached by Combinator -->";
	die();
}