<?php

if ( ! class_exists( 'GambitCombinatorFiles' ) ) {

class GambitCombinatorFiles {
	
	const UPLOADS_SUBDIR = 'combinator';
	
	public static $filesystemInitialized = false;
	
	/**
	 * Convets a relative URL to an absolute URL
	 * @see http://www.gambit.ph/converting-relative-urls-to-absolute-urls-in-php/
	 *
	 * @param	$rel	String	The relative URL
	 * @param	$base	String	The absolute URL to base it on
	 * @return	The absolute URL
	 */
	public static function rel2abs( $rel, $base ) {
		
		/* parse base URL  and convert to local variables: $scheme, $host,  $path */
		extract( parse_url( $base ) );

		if ( strpos( $rel,"//" ) === 0 ) {
			return $scheme . ':' . $rel;
		}
		
		/* return if  already absolute URL */
		if ( parse_url( $rel, PHP_URL_SCHEME ) != '' ) {
			return $rel;
		}
		
		/* queries and  anchors */
		if ( $rel[0] == '#' || $rel[0] == '?' ) {
			return $base . $rel;
		}
		
		/* remove  non-directory element from path */
		$path = preg_replace( '#/[^/]*$#', '', $path );
		
		/* destroy path if  relative url points to root */
		if ( $rel[0] ==  '/' ) {
			$path = '';
		}
		
		/* dirty absolute  URL */
		$abs = $host . $path . "/" . $rel;
		
		/* replace '//' or  '/./' or '/foo/../' with '/' */
		$abs = preg_replace( "/(\/\.?\/)/", "/", $abs );
		$abs = preg_replace( "/\/(?!\.\.)[^\/]+\/\.\.\//", "/", $abs );

		/* absolute URL is  ready! */
		return $scheme . '://' . $abs;
	}
	
	
	/**
	 * Converts all the relative URLs placed inside a "url(...)" into absolute URLs
	 *
	 * @param 	&$content	String	The CSS to parse & modify
	 * @param 	$src		String	The URL of the CSS being parsed
	 * @return	void
	 */
	public static function fixRelativeURLSCSS( &$content, $src ) {

		preg_match_all( "/(url\(~?['\"]?(.*?)['\"]?\))/", $content, $urlMatches );

		if ( ! empty( $urlMatches[2] ) ) {
			foreach ( $urlMatches[2] as $i => $url ) {

				// Absolute URLS are okay already
				if ( preg_match( '/^https?:/', $url ) ) {
					continue;
				}
				// Base 64 encoded stuff are okay already
				if ( stripos( $url, 'data:' ) !== false ) {
					continue;
				}

				$content = str_replace( $urlMatches[0][ $i ], 'url("' . self::rel2abs( $url, $src ) . '")', $content );
				
			}
		}
	}
	
	public static function combineSources( $sources, $type = 'js', $inlineCode = '' ) {
		
		$out = '';
		foreach( $sources as $src ) {
			if ( stripos( $src, '//' ) === 0 ) {
				$src = $_SERVER['REQUEST_SCHEME'] . ':' . $src;
			}
			
			$continueLoad = true;
			$content = '';
			
			if ( function_exists( 'get_transient' ) ) {
				$srcHash = substr( md5( $src . $inlineCode ), 0, 8 );
				$failedBefore = get_transient( 'cmbntr_fail' . $srcHash );
				if ( $failedBefore ) {
					$continueLoad = false;
				}
			}
			
			if ( $continueLoad ) {
				$content = wp_remote_fopen( $src );
			}
			
			if ( $type == 'css' ) {
				self::fixRelativeURLSCSS( $content, $src );
			}
			
			if ( ! empty( $content ) ) {
				$out .= $content . "\n" . ( $type == 'js' ? ';' : '' );
			} else if ( $continueLoad ) {
				if ( function_exists( 'set_transient' ) ) {
					set_transient( 'cmbntr_fail' . $srcHash, '1', DAY_IN_SECONDS );
				}
			}
			
		}
		
		if ( $type == 'js' ) {
			return $inlineCode . ';' . $out;
		} else {
			return $out . $inlineCode;
		}
	}
	
	
	public static function deleteAllFiles() {
		global $wp_filesystem;
		self::initFilesystem();

		$upload_dir = wp_upload_dir(); // Grab uploads folder array
		$dir = trailingslashit( trailingslashit( $upload_dir['basedir'] ) . self::UPLOADS_SUBDIR ); // Set storage directory path
		
		if ( $wp_filesystem->is_dir( $dir ) ) {
			$wp_filesystem->rmdir( $dir, true );
		}
	}
	
	
	public static function initFilesystem() {
		if ( self::$filesystemInitialized ) {
			return;
		}
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
		    require_once ( ABSPATH . '/wp-admin/includes/file.php' );
		    WP_Filesystem();
		}
		self::$filesystemInitialized = true;
	}
	
	
	public static function canWriteCombinatorFiles() {
		
		global $wp_filesystem;
		self::initFilesystem();
		
		$upload_dir = wp_upload_dir(); // Grab uploads folder array
		$combinatorDir = trailingslashit( $upload_dir['basedir'] ) . self::UPLOADS_SUBDIR;
		
		if ( ! $wp_filesystem->is_writable( $upload_dir['basedir'] ) ) {
			return $combinatorDir;
		}
		if ( $wp_filesystem->is_dir( $combinatorDir ) ) {
			return $wp_filesystem->is_writable( $combinatorDir ) ? true : $combinatorDir;
		}
		return true;
	}
	
	
	public static function createFile( $contents, $filename ) {
		
		global $wp_filesystem;
		self::initFilesystem();
		
		$upload_dir = wp_upload_dir(); // Grab uploads folder array
		$dir = trailingslashit( trailingslashit( $upload_dir['basedir'] ) . self::UPLOADS_SUBDIR ); // Set storage directory path

		$filePath = $dir . $filename;
		$fileURL = trailingslashit( trailingslashit( $upload_dir['baseurl'] ) . self::UPLOADS_SUBDIR ) . $filename;

		if ( ! $wp_filesystem->is_dir( $dir ) ) {
			$wp_filesystem->mkdir( $dir ); // Make a new folder for storing our file
		}
		$wp_filesystem->put_contents( $filePath, $contents, 0644 ); // Finally, store the file :)
		
		return array(
			'path' => $filePath,
			'url' => $fileURL,
		);
	}
	
}

}