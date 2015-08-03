<?php

require_once( 'class-http.php' );

define( 'ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/' );
define( 'WPINC', 'wp-includes' );
	
function apply_filters( $arg, $val ) {
	return $val;
}

function mbstring_binary_safe_encoding() {}
	
function do_action_ref_array() {}
		
function do_action() {}
			
function reset_mbstring_encoding() {}
	
function get_bloginfo() {}
	function is_wp_error() {}
		
		function get_option() { return false; }

function wp_parse_args( $args, $defaults = '' ) {
	if ( is_object( $args ) )
		$r = get_object_vars( $args );
	elseif ( is_array( $args ) )
		$r =& $args;
	else
		wp_parse_str( $args, $r );

	if ( is_array( $defaults ) )
		return array_merge( $defaults, $r );
	return $r;
}
	

function wp_remote_fopen( $uri ) {
	$parsed_url = @parse_url( $uri );

	if ( !$parsed_url || !is_array( $parsed_url ) )
		return false;

	$options = array();
	$options['timeout'] = 10;

	$response = wp_safe_remote_get( $uri, $options );

	if ( is_wp_error( $response ) )
		return false;

	return wp_remote_retrieve_body( $response );
}


/**
 * Retrieve the raw response from the HTTP request using the POST method.
 *
 * @since 2.7.0
 *
 * @see wp_remote_request() For more information on the response array format.
 * @see WP_Http::request() For default arguments information.
 *
 * @param string $url  Site URL to retrieve.
 * @param array  $args Optional. Request arguments. Default empty array.
 * @return WP_Error|array The response or WP_Error on failure.
 */
function wp_remote_post($url, $args = array()) {
	$objFetchSite = _wp_http_get_object();
	return $objFetchSite->post($url, $args);
}



/**
 * Returns the initialized WP_Http Object
 *
 * @since 2.7.0
 * @access private
 *
 * @return WP_Http HTTP Transport object.
 */
function _wp_http_get_object() {
	static $http;

	if ( is_null($http) )
		$http = new WP_Http();

	return $http;
}

/**
 * Retrieve the raw response from a safe HTTP request.
 *
 * This function is ideal when the HTTP request is being made to an arbitrary
 * URL. The URL is validated to avoid redirection and request forgery attacks.
 *
 * @since 3.6.0
 *
 * @see wp_remote_request() For more information on the response array format.
 * @see WP_Http::request() For default arguments information.
 *
 * @param string $url  Site URL to retrieve.
 * @param array  $args Optional. Request arguments. Default empty array.
 * @return WP_Error|array The response or WP_Error on failure.
 */
function wp_safe_remote_request( $url, $args = array() ) {
	$args['reject_unsafe_urls'] = true;
	$http = _wp_http_get_object();
	return $http->request( $url, $args );
}

/**
 * Retrieve the raw response from a safe HTTP request using the GET method.
 *
 * This function is ideal when the HTTP request is being made to an arbitrary
 * URL. The URL is validated to avoid redirection and request forgery attacks.
 *
 * @since 3.6.0
 *
 * @see wp_remote_request() For more information on the response array format.
 * @see WP_Http::request() For default arguments information.
 *
 * @param string $url  Site URL to retrieve.
 * @param array  $args Optional. Request arguments. Default empty array.
 * @return WP_Error|array The response or WP_Error on failure.
 */
function wp_safe_remote_get( $url, $args = array() ) {
	$args['reject_unsafe_urls'] = true;
	$http = _wp_http_get_object();
	return $http->get( $url, $args );
}


/**
 * Retrieve only the body from the raw response.
 *
 * @since 2.7.0
 *
 * @param array $response HTTP response.
 * @return string The body of the response. Empty string if no body or incorrect parameter given.
 */
function wp_remote_retrieve_body( $response ) {
	if ( is_wp_error($response) || ! isset($response['body']) )
		return '';

	return $response['body'];
}
