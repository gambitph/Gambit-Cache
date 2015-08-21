<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'GambitCacheEWWWImageEditorGD' ) ) {
	
	require_once( ABSPATH . WPINC . "/class-wp-image-editor.php" );
	// require_once( ABSPATH . WPINC . "/class-wp-image-editor-gd.php" );

	class GambitCacheEWWWImageEditorGD extends EWWWIO_GD_Editor {
		
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
			
			$image = imagecreatetruecolor( $width, $height );
			if ( $imageType == 'png' ) {
				
				// From http://webcodingeasy.com/PHP/Create-blank-transparent-PNG-images-using-PHP-GD-functions
			    imagesavealpha( $image, true );
			    $color = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
			    imagefill( $image, 0, 0, $color );
				imagepng( $image, $filePath );
				
			} else {

				$color = imagecolorallocate( $image, 255, 255, 255 );
				imagefilledrectangle( $image, 0, 0, $width, $height, $color);
				imagejpeg( $image, $filePath, 100 );

			}
			
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
				
				// From http://webcodingeasy.com/PHP/Create-blank-transparent-PNG-images-using-PHP-GD-functions
				$image = imagecreatetruecolor( $width, $height );
				imagesavealpha( $image, true );
				$color = imagecolorallocatealpha( $image, 255, 255, 255, 127 );
				imagefill( $image, 0, 0, $color );
				
				ob_start();
				imagepng( $image );
				$imageData = ob_get_contents(); 
				ob_end_clean(); 
				
				self::$trans64Generated[ $key ] = base64_encode( $imageData );
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
				
				$subImageSize = $subImage->get_size();
				imagecopyresampled( 
					$this->image, 
					$subImage->image,
					$imageData['x'], $imageData['y'],
					0, 0,
					$subImageSize['width'], $subImageSize['height'],
					$subImageSize['width'], $subImageSize['height']
				);

			}
			return true;
		}

	}
	
}

?>