<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'GambitCacheDebug' ) ) {

	class GambitCacheDebug {
		
		public static $messages = array();
		public $settings = array(
			'debug_enabled' => false,
		);

		function __construct() {
			add_action( 'shutdown', array( $this, 'showDebugInfo' ) );
		}
		
		public function showDebugInfo() {
			if ( ! $this->settings['debug_enabled'] ) {
				return;
			}
			
			if ( self::$messages ) {
				echo "<pre style='display: none'>\n";
			}
			foreach ( self::$messages as $message ) {
				echo $message . "</br>\n";
			}
			if ( self::$messages ) {
				echo "</pre>";
			}
		}

	}
	
}

if ( ! function_exists( 'gambitCache_debug' ) ) {

	function gambitCache_debug( $message ) {
		GambitCacheDebug::$messages[] = $message;
	}	
}