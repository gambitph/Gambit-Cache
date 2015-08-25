<?php
	
if ( ! class_exists( 'GambitCacheObjectCacheCleaner' ) ) {
	
class GambitCacheObjectCacheCleaner {
	
	public static $alreadyFlushed = false;

	function __construct() {
			
		add_action( 'switch_theme', array( $this, 'flushCache' ) );
		add_action( 'customize_save_after', array( $this, 'flushCache' ) );
		
		// Option change
		add_action( 'updated_option', array( $this, 'flushCache' ) );
		add_action( 'added_option', array( $this, 'flushCache' ) );
		add_action( 'delete_option', array( $this, 'flushCache' ) );
		
		if ( is_multisite() ) {
			add_action( 'delete_blog', array( $this, 'flushCache' ) );
			add_action( 'switch_blog', array( $this, 'flushCache' ) );
		}
		
	}
	
	public function flushCache() {
		if ( ! self::$alreadyFlushed ) {
			wp_cache_flush();
			self::$alreadyFlushed = true;
		}
	}
		
}

}