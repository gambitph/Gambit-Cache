<?php

require_once( 'class-files.php' );
	
if ( ! class_exists( 'GambitCombinatorJS' ) ) {
	
class GambitCombinatorJS extends GambitCombinatorFiles {
	
	public static function closureCompile( $code, $level ) {
	
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

		if ( ! empty( $response['response']['code'] ) && ! empty( $response['body'] ) ) {
			if ( $response['response']['code'] == 200 ) {
				$code = $response['body'];
			}
		}
		
		return $code;
	}
	
	public static function combineSources( $sources, $type = 'js' ) {
		return parent::combineSources( $sources, 'js' );
	}
	
}

}