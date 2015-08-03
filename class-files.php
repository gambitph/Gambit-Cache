<?php

if ( ! class_exists( 'GambitCombinatorFiles' ) ) {

class GambitCombinatorFiles {
	
	const UPLOADS_SUBDIR = 'combinator';
	
	public static function combineSources( $sources, $type = 'js' ) {
		
		$out = '';
		foreach( $sources as $src ) {
			if ( stripos( $src, '//' ) === 0 ) {
				$src = $_SERVER['REQUEST_SCHEME'] . ':' . $src;
			}
			
			$out .= wp_remote_fopen( $src ) . "\n" . ( $type == 'js' ? ';' : '' );
		}
		
		return $out;
	}
	
	
	public static function deleteAllFiles() {
		global $wp_filesystem;

		$upload_dir = wp_upload_dir(); // Grab uploads folder array
		$dir = trailingslashit( trailingslashit( $upload_dir['basedir'] ) . self::UPLOADS_SUBDIR ); // Set storage directory path

		WP_Filesystem(); // Initial WP file system
		
		if ( $wp_filesystem->is_dir( $dir ) ) {
			$wp_filesystem->rmdir( $dir, true );
		}
	}
	
	
	public static function createFile( $contents, $filename ) {
		
		global $wp_filesystem;
		$upload_dir = wp_upload_dir(); // Grab uploads folder array
		$dir = trailingslashit( trailingslashit( $upload_dir['basedir'] ) . self::UPLOADS_SUBDIR ); // Set storage directory path

		// $hash = $type . '_' . md5( serialize( $this->headScripts ) );
		$filePath = $dir . $filename;
		$fileURL = trailingslashit( trailingslashit( $upload_dir['baseurl'] ) . self::UPLOADS_SUBDIR ) . $filename;
		// $css = 'body { background: red }';

		WP_Filesystem(); // Initial WP file system
		
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