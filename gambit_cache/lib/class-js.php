<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once( 'class-files.php' );
	
if ( ! class_exists( 'GambitCacheJS' ) ) {
	
class GambitCacheJS extends GambitCacheFiles {
	
	public static function closureCompile( $code, $level ) {
	
		$codeHash = substr( md5( $code ), 0, 8 );
		$failedBefore = get_transient( 'cmbntr_fail' . $codeHash );
		if ( $failedBefore ) {
			gambitCache_debug( sprintf( __( 'Minify: Closure Compile previously failed, skipping %s', GAMBIT_CACHE ), $codeHash ) );
			return $code;
		}
	
		$compilationLevel = 'WHITESPACE_ONLY';
		if ( $level == 2 ) {
			$compilationLevel = 'SIMPLE_OPTIMIZATIONS'; // default 2
		} else if ( $level == 3 ) {
			$compilationLevel = 'ADVANCED_OPTIMIZATIONS';
		}
	
		$response = wp_remote_post( 'http://closure-compiler.appspot.com/compile', array(
			'method' => 'POST',
			'timeout' => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(
				"Content-type" => "application/x-www-form-urlencoded",
			),
			'body' => array( 
				'js_code' => $code, 
				'compilation_level' => $compilationLevel,
				'output_info' => 'compiled_code',
			),
			'cookies' => array()
		    )
		);
		
		// if ( class_exists( 'WP_Error' ) && function_exists( 'is_wp_error' ) ) {
			if ( is_wp_error( $response ) ) {
				set_transient( 'cmbntr_fail' . $codeHash, '1', MINUTE_IN_SECONDS );
				gambitCache_debug( sprintf( __( 'Minify: [ERROR] Closure Compile post failed %s', GAMBIT_CACHE ), $codeHash ) );
				return $code;
			} else {
				gambitCache_debug( sprintf( __( 'Minify: Successfully compiled with Closure Compile %s', GAMBIT_CACHE ), $codeHash ) );
			}
		// }

		if ( is_array( $response ) && ! empty( $response['response']['code'] ) && ! empty( $response['body'] ) ) {
			if ( $response['response']['code'] == 200 
				 && stripos( $response['body'], 'Error(22): Too many compiles performed recently' ) === false ) {
				$code = $response['body'];
			} else {
				set_transient( 'cmbntr_fail' . $codeHash, '1', MINUTE_IN_SECONDS );
				gambitCache_debug( sprintf( __( 'Minify: [ERROR] Closure Compile post failed: Too many compiles performed recently %s', GAMBIT_CACHE ), $codeHash ) );
			}
		} else {
			set_transient( 'cmbntr_fail' . $codeHash, '1', MINUTE_IN_SECONDS );
		}
		
		return $code;
	}
	
	public static function combineSources( $sources, $type = 'js', $inlineCode = '' ) {
		return parent::combineSources( $sources, 'js', $inlineCode );
	}
	
}

}