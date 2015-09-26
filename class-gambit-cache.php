<?php

require_once( 'gambit_cache/lib/util.php' );
require_once( 'gambit_cache/lib/class-js.php' );
require_once( 'gambit_cache/lib/class-css.php' );
require_once( 'gambit_cache/lib/class-cache-activation.php' );
require_once( 'gambit_cache/lib/class-cache-deactivation.php' );
require_once( 'gambit_cache/lib/class-admin-page.php' );
require_once( 'gambit_cache/lib/class-page-cache.php' );
require_once( 'gambit_cache/lib/class-page-cache-cleaner.php' );
require_once( 'gambit_cache/lib/class-object-cache-cleaner.php' );
require_once( 'gambit_cache/lib/class-minify.php' );
require_once( 'gambit_cache/lib/class-minify-cleaner.php' );
require_once( 'gambit_cache/lib/class-sprite.php' );
require_once( 'gambit_cache/lib/class-debug.php' );

// Initializes Titan Framework
require_once( 'titan-framework-checker.php' );

if ( ! class_exists( 'GambitCache' ) ) {

	class GambitCache {

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
			new GambitCacheSprite();
			new GambitCacheDebug();

		}

	}

	new GambitCache();

}

add_action( 'wp_footer', 'print_queries', 1000 );
function print_queries() {
?>
<!-- <?php echo get_num_queries(); ?> queries. <?php timer_stop( 1 ); ?> seconds. -->
<?php
}
