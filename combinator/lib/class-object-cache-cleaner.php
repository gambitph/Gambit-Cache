<?php
	
if ( ! class_exists( 'GambitCacheObjectCacheCleaner' ) ) {
	
class GambitCacheObjectCacheCleaner {

	function __construct() {
			
		add_action( 'switch_theme', 'wp_cache_flush' );
		add_action( 'customize_save_after', 'wp_cache_flush' );
		
		// Option change
		add_action( 'updated_option', 'wp_cache_flush' );
		add_action( 'added_option', 'wp_cache_flush' );
		add_action( 'delete_option', 'wp_cache_flush' );
		
		if ( is_multisite() ) {
			add_action( 'delete_blog', 'wp_cache_flush' );
			add_action( 'switch_blog', 'wp_cache_flush' );
		}
		
	}
		
}

}