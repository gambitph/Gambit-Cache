<?php

require_once( 'combinator/lib/class-js.php' );
require_once( 'combinator/lib/class-css.php' );
require_once( 'combinator/lib/class-cache-activation.php' );
require_once( 'combinator/lib/class-cache-deactivation.php' );
require_once( 'combinator/lib/class-admin-page.php' );
require_once( 'combinator/lib/class-page-cache.php' );
require_once( 'combinator/lib/class-page-cache-cleaner.php' );
require_once( 'combinator/lib/class-object-cache-cleaner.php' );
require_once( 'combinator/lib/class-minify.php' );
require_once( 'combinator/lib/class-minify-cleaner.php' );

// Initializes Titan Framework
require_once( 'titan-framework-checker.php' );

if ( ! class_exists( 'GambitCombinator' ) ) {
	
	class GambitCombinator {
		
		/**
		 * Hook into WordPress
		 */
		function __construct() {
			
			new GambitCacheAdminPage();
			new GambitCacheActivation();
			new GambitCacheDeactivation();
			new GambitCachePageCache();
			new GambitCacheMinify();
			new GambitCachePageCacheCleaner();
			new GambitCacheObjectCacheCleaner();
			new GambitCacheMinifyCleaner();
			
		}
		
	}

	new GambitCombinator();
	
}