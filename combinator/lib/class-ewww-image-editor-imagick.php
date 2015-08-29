<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'GambitCacheEWWWImageEditorImagick' ) ) {
	
	require_once( ABSPATH . WPINC . "/class-wp-image-editor.php" );
	// require_once( ABSPATH . WPINC . "/class-wp-image-editor-imagick.php" );

	class GambitCacheEWWWImageEditorImagick extends EWWWIO_Imagick_Editor {
		
		public static $trans64Generated = array();
		
		
		/**
		 * Creates a blank image to be used for combining images
		 *
		 * @param	$filePath	String	The path to save the blank image
		 * @param	$imageType	String	The image format of the image to create: png, jpg, gif
		 * @param	$width		Int		The image width of the blank image
		 * @param	$height		Int		The image height of the blank image
		 * @return				String	The path of the created blank image
		 */
		public static function gcCreateBlankImage( $filePath, $imageType, $width = 1000, $height = 1000 ) {
			$image = new Imagick();
			if ( $imageType == 'png' ) {
				$image->newImage( $width, $height, 'none' );
			} else {
				$image->newImage( $width, $height, 'white' );
			}
			$image->setImageFormat( $imageType );
			$image->writeImage( $filePath );
			
			return $filePath;
		}
		
		
		/**
		 * Creates a blank image - base64 encoded
		 *
		 * @param	$width		Int		The image width of the blank image
		 * @param	$height		Int		The image height of the blank image
		 * @return				String	The base64 encoded image, ready for use in the src attribute
		 */
		public static function gcCreateTransBase64( $width, $height ) {
			$key = $width . 'x' . $height;
			if ( empty( self::$trans64Generated[ $key ] ) ) {
				$image = new Imagick();
				$image->newImage( $width, $height, 'none' );
				$image->setImageFormat("png");
				self::$trans64Generated[ $key ] = base64_encode( $image->getImageBlob() );
			}
			return 'data:image/png;base64,' . self::$trans64Generated[ $key ];
		}


		/**
		 * Combines multiple images into this image
		 *
		 * @param	$images	Array	Image data
		 * @return			Boolean	true
		 */
		public function gcCombineImages( $images ) {
			foreach ( $images as $imageData ) {
				$subImage = wp_get_image_editor( $imageData['path'] );
				$this->image->compositeImage( 
					$subImage->image, 
					Imagick::COMPOSITE_COPY, //Imagick::COMPOSITE_DEFAULT, 
					$imageData['x'], 
					$imageData['y']
				);
			}
			return true;
		}

	}
	
}

?>