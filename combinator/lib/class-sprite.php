<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'GambitCacheSprite' ) ) {

	class GambitCacheSprite {
		
		public $gatheringStarted = false;
		public static $stats = array(
			'sprites' => 0,
			'images' => 0,
		);
		
		public $settings = array(
			'sprite_enabled' => true,
			'sprite_include_remotes' => true,
			'sprite_quality' => '60',
			'sprite_size' => '1000',
			'sprite_combine_max' => '30',
			'sprite_exclude' => '',
			'sprite_seconds_redownload_images' => 345600,
		);
		
		function __construct() {
			
			// Use our image editors instead of the default ones
			add_filter( 'wp_image_editors', array( $this, 'addOurImageEditors' ), 999 );

			add_action( 'tf_done', array( $this, 'gatherSettings' ), 10 );
			
			add_action( 'wp_head', array( $this, 'startGatheringContent' ), 9999 ); // Needs to be 9999 since minify ends in 9998
			add_action( 'wp_footer', array( $this, 'endGatheringContent' ), 0 ); // Needs to be 0 since minify starts in 1
			
		}
		
		public function gatherSettings() {

			$titan = TitanFramework::getInstance( GAMBIT_COMBINATOR );
			
			$this->settings['sprite_enabled'] = $titan->getOption( 'sprite_enabled' );
			$this->settings['sprite_include_remotes'] = $titan->getOption( 'sprite_include_remotes' );
			$this->settings['sprite_quality'] = $titan->getOption( 'sprite_quality' );
			$this->settings['sprite_size'] = $titan->getOption( 'sprite_size' );
			$this->settings['sprite_combine_max'] = $titan->getOption( 'sprite_combine_max' );
			$this->settings['sprite_exclude'] = $titan->getOption( 'sprite_exclude' );
			$this->settings['sprite_seconds_redownload_images'] = $titan->getOption( 'sprite_seconds_redownload_images' );
		}
		
		public function addOurImageEditors( $editors ) {

			foreach ( $editors as $i => $editor ) {
				if ( $editor == 'WP_Image_Editor_Imagick' ) {
					require_once( 'class-wp-image-editor-imagick.php' );
					$editors[ $i ] = 'GambitCacheImageEditorImagick';
				} else if ( $editor == 'WP_Image_Editor_GD' ) {
					require_once( 'class-wp-image-editor-gd.php' );
					$editors[ $i ] = 'GambitCacheImageEditorGD';
					
				// Support for EWWW Image Optimizer
				} else if ( $editor == 'EWWWIO_Imagick_Editor' ) {
					require_once( 'class-ewww-image-editor-imagick.php' );
					$editors[ $i ] = 'GambitCacheEWWWImageEditorImagick';
				} else if ( $editor == 'EWWWIO_GD_Editor' ) {
					require_once( 'class-ewww-image-editor-gd.php' );
					$editors[ $i ] = 'GambitCacheEWWWImageEditorGD';
				}
			}

			return $editors;
		}
		
	
		public static function clearCache() {
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
			    require_once ( ABSPATH . '/wp-admin/includes/file.php' );
			    WP_Filesystem();
			}

			$upload_dir = wp_upload_dir(); // Grab uploads folder array
			$dir = trailingslashit( $wp_filesystem->wp_content_dir() . 'gambit-cache' ) . 'sprite-cache';
		
			if ( $wp_filesystem->is_dir( $dir ) ) {
				if ( $wp_filesystem->is_writable( $dir ) ) {
					if ( $wp_filesystem->rmdir( $dir, true ) ) {
						$wp_filesystem->mkdir( $dir );
						return true;
					}
				}
			}
			
			return false;
		}
		
		public function startGatheringContent() {
			if ( ! $this->settings['sprite_enabled'] ) {
				return;
			}
			if ( ! gambitCache_isFrontEnd() ) {
				return;
			}
			$this->gatheringStarted = true;

			ob_start();
		}
		
		public function endGatheringContent() {
			if ( ! $this->gatheringStarted ) {
				return;
			}
			
			$this->gatheringStarted = false;
			
			$content = ob_get_contents();
			ob_end_clean();
			
			// Gather all images
			if ( $this->settings['sprite_enabled'] ) {
				$this->gatherAllImages( $content );
			}
			
			// Output the head/footer content
			echo $content;
		}
		
		
		public function createBlankSprite( $imageType, $filename ) {
			$implementation = _wp_image_editor_choose();
			
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
			    require_once ( ABSPATH . '/wp-admin/includes/file.php' );
			    WP_Filesystem();
			}
			
			$dir = trailingslashit( $wp_filesystem->wp_content_dir() . 'gambit-cache' ) . 'sprite-cache';
			$subdir = substr( $filename, 0, 2 );
			$filePath = trailingslashit( trailingslashit( $dir ) . $subdir ) . $filename . '.' . $imageType;

			if ( ! $wp_filesystem->exists( trailingslashit( trailingslashit( $dir ) . $subdir ) ) ) {
				$wp_filesystem->mkdir( trailingslashit( trailingslashit( $dir ) . $subdir ), 0755 );
			}
			if ( ! $wp_filesystem->is_writable( trailingslashit( trailingslashit( $dir ) . $subdir ) ) ) {
				$wp_filesystem->chmod( trailingslashit( trailingslashit( $dir ) . $subdir ), 0755 );
			}
			if ( ! $wp_filesystem->is_writable( trailingslashit( trailingslashit( $dir ) . $subdir ) ) ) {
				return false;
			}
			
			if ( method_exists( $implementation, 'gcCreateBlankImage' ) ) {
				return call_user_func_array( 
					array( $implementation, 'gcCreateBlankImage' ), 
					array( $filePath, $imageType, (int) $this->settings['sprite_size'], (int) $this->settings['sprite_size'] )
				);
			}
			
			return false;
		}
		
		public function combineImages( $imagesFound ) {
			
			// Segregate images by type
			$imageTypes = array();
			foreach ( $imagesFound as $image ) {
				if ( empty( $imageTypes[ $image['type'] ] ) ) {
					$imageTypes[ $image['type'] ] = array();
				}
				$imageTypes[ $image['type'] ][] = $image;
			}

			// Sort images from largest to smallest
			foreach ( $imageTypes as $i => $imageType ) {
				usort( $imageTypes[ $i ], array( $this, 'sortBySize' ) );
				$imageType = $imageTypes[ $i ];
				
			}

			// Pack the images inside sprite containers
			$imagePacks = array();
			foreach ( $imageTypes as $type => $imageArray ) {
				
				$numPacked = 0;
				while ( $numPacked < count( $imageArray ) ) {
					
					$imagePack = new GambitCacheSpritePacker( (int) $this->settings['sprite_size'], (int) $this->settings['sprite_size'], $type );
					$imagePacks[] = $imagePack;
					
					foreach ( $imageArray as $i => $imageData ) {
						
						if ( isset( $imageData['done'] ) ) {
							continue;
						}
						
						// +2 for gap
						$coords = $imagePack->findCoords( $imageData['width'] + 5, $imageData['height'] + 5, $imageData );
						if ( $coords ) {
							$imageArray[ $i ]['done'] = true; // Note that original array is retained
							$numPacked++;
						}
						
					}
					
				}
			}
			
			// Create the combined images
			$newImagesFound = array();
			foreach ( $imagePacks as $imagePack ) {
				
				$timeStart = microtime( true );
				
				$allUrls = array();

				foreach ( $imagePack->images as $image ) {
					$allUrls[] = $image['url'];
					self::$stats['images']++;
				}
				sort( $allUrls );
			
				$hash = substr( md5( serialize( $allUrls ) ), 0, 16 );
				
				
				global $wp_filesystem;
				$dir = trailingslashit( $wp_filesystem->wp_content_dir() . 'gambit-cache' ) . 'sprite-cache';
				$subdir = substr( $hash, 0, 2 );
				$filePath = trailingslashit( trailingslashit( $dir ) . $subdir ) . $hash . '.' . $imagePack->imageType;
				$fileURL = trailingslashit( trailingslashit( trailingslashit( content_url( 'gambit-cache' ) ) . 'sprite-cache' ) . $subdir ) . $hash . '.' . $imagePack->imageType;
				
				$saved = false;
				if ( ! $wp_filesystem->exists( $filePath ) ) {
					foreach ( $imagePack->images as $image ) {
						$image['combined_path'] = $filePath;
						$image['combined_hash'] = $hash;
						$image['combined_url'] = $fileURL;
						$newImagesFound[] = $image;
					}
				
					$filePath = $this->createBlankSprite( $imagePack->imageType, $hash );
					if ( ! $filePath ) {
						gambitCache_debug( sprintf( __( 'Sprite: [ERROR] Could not create blank sprite %s', GAMBIT_COMBINATOR ), $hash ) );
						continue;
					}
					gambitCache_debug( sprintf( __( 'Sprite: Successfully created blank sprite %s', GAMBIT_COMBINATOR ), $filePath ) );
				
					$imageEditor = wp_get_image_editor( $filePath );
					$imageEditor->gcCombineImages( $imagePack->images );
					$imageEditor->set_quality( $this->settings['sprite_quality'] );
					$saved = $imageEditor->save( $filePath );
				}
				
				if ( ! is_wp_error( $saved ) ) {
					gambitCache_debug( sprintf( __( 'Sprite: Successfully combined %d images into %s', GAMBIT_COMBINATOR ), count( $imagePack->images ), $filePath ) );
					foreach ( $imagePack->images as $image ) {
						$image['combined_path'] = $filePath;
						$image['combined_hash'] = $hash;
						$image['combined_url'] = $fileURL;
						$newImagesFound[] = $image;
					}
				} else {
					gambitCache_debug( sprintf( __( 'Sprite: [ERROR] Could not combine %d images into %s', GAMBIT_COMBINATOR ), count( $imagePack->images ), $filePath ) );
				}
				
				gambitCache_debug( ( microtime( true ) - $timeStart ) . ' Sprite: combine into sprite ' . $hash );
				self::$stats['sprites']++;
			}
			
			return $newImagesFound;
			
		}
		
		public function sortBySize( $a, $b ) {
			return $a['height'] < $b['height'];
		}
		
		
		public function saveCachedImageCopy( $url ) {

			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
			    require_once ( ABSPATH . '/wp-admin/includes/file.php' );
			    WP_Filesystem();
			}
			
			$hash = substr( md5( $url ), 0, 16 );
			$hashMini = substr( $hash, 0, 8 );
			
			$upload_dir = wp_upload_dir(); // Grab uploads folder array
			$dir = trailingslashit( $wp_filesystem->wp_content_dir() . 'gambit-cache' ) . 'sprite-cache';
			$subDir = substr( $hash, 0, 2 );
			$filePath = trailingslashit( trailingslashit( $dir ) . $subDir ) . $hash;
			$imageType = '';
			
			$exists = false;
			
			// No need to download locally uploaded files
			$isInUploadsDir = false;
			$uploadDir = wp_upload_dir();
			if ( stripos( $url, $upload_dir['baseurl'] ) !== false ) {
				$localPath = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
				$localPath = str_replace( '/', DIRECTORY_SEPARATOR, $localPath );
				if ( $wp_filesystem->exists( $localPath ) ) {
					$isInUploadsDir = true;
					$exists = true;
					$filePath = $localPath;
					if ( stripos( $filePath, '.png' ) !== false ) {
						$imageType = 'png';
					} else if ( stripos( $filePath, '.gif' ) !== false ) {
						$imageType = 'gif';
					} else {
						$imageType = 'jpg';
					}
				}
			}
				
			if ( ! $exists && $wp_filesystem->exists( $filePath . '.jpg' ) ) {
				$exists = true;
				$filePath .= '.jpg';
				$imageType = 'jpg';
			}
			if ( ! $exists && $wp_filesystem->exists( $filePath . '.png' ) ) {
				$exists = true;
				$filePath .= '.png';
				$imageType = 'png';
			}
			if ( ! $exists && $wp_filesystem->exists( $filePath . '.gif' ) ) {
				$exists = true;
				$filePath .= '.gif';
				$imageType = 'gif';
			}
			
			// Check if transient expired
			if ( ! $isInUploadsDir && $exists && get_transient( 'gc_sprt_' . $hashMini ) ) {
				gambitCache_debug( sprintf( __( 'Sprite: Image already old, try and delete %s', GAMBIT_COMBINATOR ), $filePath ) );
				if ( $wp_filesystem->is_writable( $filePath ) ) {
					if ( $wp_filesystem->delete( $filePath, false, 'f' ) ) {
						$exists = false;
						gambitCache_debug( sprintf( __( 'Sprite: Successfully deleted old mimage %s', GAMBIT_COMBINATOR ), $filePath ) );
					}
				}
				if ( $exists ) {
					gambitCache_debug( sprintf( __( 'Sprite: [ERROR] Could not delete image, will still use old image in sprite %s', GAMBIT_COMBINATOR ), $filePath ) );
				}
			}
			
			$data = false;
			if ( $exists ) {
				gambitCache_debug( sprintf( __( 'Sprite: Image already exists in %s', GAMBIT_COMBINATOR ), $filePath ) );
				$data = array(
					'url' => $url,
					'hash' => $hash,
					'path' => $filePath,
					'type' => $imageType
				);
				
			} else if ( ! $wp_filesystem->exists( $filePath ) ) {
			
				$response = wp_remote_get( $url );
				if ( is_wp_error( $response ) ) {
					gambitCache_debug( sprintf( __( 'Sprite: [ERROR] Could not download image for caching %s', GAMBIT_COMBINATOR ), $url ) );
					return false;
				}
				gambitCache_debug( sprintf( __( 'Sprite: Successfully downloaded image for caching %s', GAMBIT_COMBINATOR ), $url ) );
				
				if ( ! preg_match( '/\/(png|jpeg|gif)$/', $response['headers']['content-type'] ) ) {
					return false;
				}
				
				// Set transient so we can re-download the image if necessary
				if ( ! $isInUploadsDir ) {
					set_transient( 'gc_sprt_' . $hashMini, 1, $this->settings['sprite_seconds_redownload_images'] );
				}
				
				if ( $response['headers']['content-type'] == 'image/gif' ) {
					$filePath .= '.gif';
					$imageType = 'gif';
				} else if ( $response['headers']['content-type'] == 'image/png' ) {
					$filePath .= '.png';
					$imageType = 'png';
				} else {
					$filePath .= '.jpg';
					$imageType = 'jpg';
				}
				
				if ( ! $wp_filesystem->exists( trailingslashit( $dir ) . $subDir ) ) {
					$wp_filesystem->mkdir( trailingslashit( $dir ) . $subDir, 0755 );
				}
				if ( ! $wp_filesystem->is_writable( trailingslashit( $dir ) . $subDir ) ) {
					$wp_filesystem->chmod( trailingslashit( $dir ) . $subDir, 0755 );
				}
				if ( $wp_filesystem->is_writable( trailingslashit( $dir ) . $subDir ) ) {
					if ( $wp_filesystem->put_contents( $filePath, $response['body'], 0644 ) ) {
						gambitCache_debug( sprintf( __( 'Sprite: Image saved in %s', GAMBIT_COMBINATOR ), $filePath ) );
					} else {
						gambitCache_debug( sprintf( __( 'Sprite: [ERROR] Could not save image at %s', GAMBIT_COMBINATOR ), $filePath ) );
					}
				}
				
				$data = array(
					'url' => $url,
					'hash' => $hash,
					'path' => $filePath,
					'type' => $imageType,
				);
				
			}
			
			// Get the image actual dimensions
			if ( ! empty( $data ) ) {
				$implementation = _wp_image_editor_choose();
				
				if ( $implementation == 'GambitCacheImageEditorImagick' ) {
					$image = new Imagick( $data['path'] ); 
					$dim = $image->getImageGeometry();
					$data['width'] = $dim['width'];
					$data['height'] = $dim['height'];
				} else {
					$dim = getimagesize( $data['path'] );
					$data['width'] = $dim[0];
					$data['height'] = $dim[1];
				}
			}
			
			return $data;
		}
		
		public function gatherAllImages( &$content ) {
			
			$imagesFound = array();
			
			// Remove commented out stuff since we don't want to include those
			$cleanedContent = preg_replace( "/<!--.*?-->/s", "", $content );
			
			preg_match_all( "/<img[^>]+>/", $cleanedContent, $imageTags );
			if ( empty( $imageTags[0] ) ) {
				return;
			}
			
			// Form exclude regex
			$excludeURLs = '';
			if ( ! empty( $this->settings['sprite_exclude'] ) ) {
				$excludeURLs = preg_replace( "/\n/", "|", $this->settings['sprite_exclude'] );
				$excludeURLs = preg_replace( "/ /", "", $excludeURLs );
			}
			
			foreach ( $imageTags[0] as $i => $imageTag ) {
				
				if ( $i >= $this->settings['sprite_combine_max'] ) {
					break;
				}
				
				// Only do this to images with width & heights
				if ( ! preg_match( "/(width=['\"]\d+['\"].*height=['\"]\d+['\"]|height=['\"]\d+['\"].*width=['\"]\d+['\"])/", $imageTag ) ) {
					continue;
				}
				
				// Get image url
				preg_match( "/src=['\"]([^'\"]+)['\"]/", $imageTag, $matches );
				if ( empty( $matches[1] ) ) {
					continue;
				}
				$url = $matches[1];
				
				// Do not include remote images if set
				if ( stripos( $url, get_site_url() ) === false && ! $this->settings['sprite_include_remotes'] ) {
					continue;
				}
				
				// Do not include excluded urls
				if ( ! empty( $excludeURLs ) ) {
					try {
						if ( preg_match( '/(' . $excludeURLs . ')/', $url ) ) {
							continue;
						}
					} catch ( Exception $e ) {
						// regex failed, ignore
					}
				}
				
				// Get image dimensions
				preg_match( "/width=['\"](\d+)['\"]/", $imageTag, $matches );
				$width = (int) $matches[1];
				preg_match( "/height=['\"](\d+)['\"]/", $imageTag, $matches );
				$height = (int) $matches[1];
				
				// Only entertain small images
				if ( $width > (int) $this->settings['sprite_size'] / 2.5 || $height > (int) $this->settings['sprite_size'] / 2.5 ) {
					continue;
				}
				
				// Check whether we already have the image downloaded in sprite-cache
				$timeStart = microtime( true );
				$imageData = $this->saveCachedImageCopy( $url );
				if ( empty( $imageData ) ) {
					continue;
				}
				gambitCache_debug( ( microtime( true ) - $timeStart ) . ' Sprite: download ' . $url . ' to ' . $imageData['path'] );
				
				$imageData['tag_width'] = $width;
				$imageData['tag_height'] = $height;
				$imageData['tag'] = $imageTag;
				$imagesFound[] = $imageData;
				
			}
			
			$imagesFound = $this->combineImages( $imagesFound );
			
			// $cssStyles = array();
			foreach ( $imagesFound as $imageFound ) {
				if ( ! empty( $imageFound['combined_hash'] ) ) {
					
					$newTag = $this->formNewTag( $imageFound );
					
					$content = str_replace( $imageFound['tag'], $newTag, $content );
					// $imageFound['tag']
					// foreach ( $imageFound['script_file_tags'] as $i => $tag ) {
					// 	$content = str_replace( $tag, '', $content );
					// }
										//
					// if ( empty( $cssStyles[ $imageFound['combined_hash'] ] ) ) {
					// 	$cssStyles[ $imageFound['combined_hash'] ] = ".gc_sprite_" . $imageFound['combined_hash'] . " {\n" .
					// 		"background: url(" . esc_url( $imageFound['combined_hash'] ) . ");\n"
					// 		"}";
					// }
					
				}
			}
			
		}
		
		
		public function formNewTag( $imageData ) {
			$newTag = $imageData['tag'];

			// Get the smallest fraction			
			$smallestFraction = $this->farey( $imageData['width'] / $imageData['height'], 10 );
			
			// Change the src to a transparent pixel that has similar (but minimized dimensions)
			$implementation = _wp_image_editor_choose();

			if ( method_exists( $implementation, 'gcCreateTransBase64' ) ) {
				$transBase64Data = call_user_func_array( array( $implementation, 'gcCreateTransBase64' ), array( $smallestFraction[0], $smallestFraction[1] ) );
			
				$newTag = preg_replace( "/(src=['\"])([^'\"]+)(['\"])/", "$1" . $transBase64Data . "$3", $newTag );
			}
			
			// Change the src to a transparent pixel
			// $newTag = preg_replace( "/(src=['\"])([^'\"]+)(['\"])/", "$1" . $transparentImageData . "$3", $newTag );
			
			// Add the gc_sprite class
			if ( preg_match( "/(class=['\"])([^'\"]*)(['\"])/", $newTag ) ) {
				$newTag = preg_replace( "/(class=['\"])([^'\"]*)(['\"])/", "$1$2 gc_sprite$3", $newTag );
			} else {
				$newTag = preg_replace( "/(<img)/", "$1 class=\"gc_sprite\" ", $newTag );
			}
			
			// Create our styles
			$styles = '';
			
			$styles .= 'background-image: url(' . esc_url( $imageData['combined_url'] ) . ');';
			
			// [height] / [width] * 100 %
			// $styles .= 'padding-bottom: ' . ( $imageData['tag_height'] / $imageData['tag_width'] * 100 ) . '%;';
			
			// [sprite-width] / [single-img-width-in-sprite] * 100 %
			$styles .= 'background-size: ' . ( (int) $this->settings['sprite_size'] / $imageData['width'] * 100 ) . '%;';
			
			// [image-offset-in-sprite] / ([sprite-width] - [single-image-width-in-sprite]) * 100
			$styles .= 'background-position: ' . ( $imageData['x'] / ( (int) $this->settings['sprite_size'] - $imageData['width'] ) * 100 ) . '% ';
			$styles .= ( $imageData['y'] / ( (int) $this->settings['sprite_size'] - $imageData['height'] ) * 100 ) . '%;';
			
			// Add our styles
			if ( preg_match( "/(style=['\"])([^'\"]*)(['\"])/", $newTag ) ) {
				$newTag = preg_replace( "/(style=['\"])([^'\"]*)(['\"])/", "$1$2;" . esc_attr( $styles ) . "$3", $newTag );
			} else {
				$newTag = preg_replace( "/(<img)/", "$1 style=\"" . esc_attr( $styles ) . "\" ", $newTag );
			}
			
			return $newTag;
		}
		
		
		/**
		 * @see http://stackoverflow.com/questions/14330713/converting-float-decimal-to-fraction
		 */
		public function farey($v, $lim) {
		    // No error checking on args.  lim = maximum denominator.
		    // Results are array(numerator, denominator); array(1, 0) is 'infinity'.
		    if($v < 0) {
		        list($n, $d) = farey(-$v, $lim);
		        return array(-$n, $d);
		    }
		    $z = $lim - $lim;   // Get a "zero of the right type" for the denominator
		    list($lower, $upper) = array(array($z, $z+1), array($z+1, $z));
		    while(true) {
		        $mediant = array(($lower[0] + $upper[0]), ($lower[1] + $upper[1]));
		        if($v * $mediant[1] > $mediant[0]) {
		            if($lim < $mediant[1]) 
		                return $upper;
		            $lower = $mediant;
		        }
		        else if($v * $mediant[1] == $mediant[0]) {
		            if($lim >= $mediant[1])
		                return $mediant;
		            if($lower[1] < $upper[1])
		                return $lower;
		            return $upper;
		        }
		        else {
		            if($lim < $mediant[1])
		                return $lower;
		            $upper = $mediant;
		        }
		    }
		}
	}
	
	
	/**
	 * @see https://gist.github.com/drslump/8127717
	 */
	class GambitCacheSpritePacker {

	    private $root = array();
	    private $usedHeight = 0;
	    private $usedWidth = 0;
		public $images = array();
		public $imageType = '';


	    function __construct($width, $height, $imageType ) {
			$this->imageType = $imageType;
	        $this->reset($width, $height);
	    }

	    function reset($width, $height) {
	        $this->root['x'] = 0;
	        $this->root['y'] = 0;
	        $this->root['w'] = $width;
	        $this->root['h'] = $height;
	        $this->root['lft'] = null;
	        $this->root['rgt'] = null;
   
	        $this->usedWidth = 0;
	        $this->usedHeight = 0;
	    }

	    function getDimensions() {
	        return array(
	            'w' => $this->usedWidth,
	            'h' => $this->usedHeight
	        );
	    }

	    function cloneNode($node) {
	        return array(
	            'x' => $node['x'],
	            'y' => $node['y'],
	            'w' => $node['w'],
	            'h' => $node['h']
	        );
	    }              

	    function recursiveFindCoords(&$node, $w, $h) {
	        if (isset($node['lft']) && is_array($node['lft'])) {
	            $coords = $this->recursiveFindCoords($node['lft'], $w, $h);
	            if (!$coords && isset($node['rgt']) && is_array($node['rgt'])) {
	                $coords = $this->recursiveFindCoords($node['rgt'], $w, $h);
	            }
	            return $coords;
	        }
	        else
	        {
	            if (isset($node['used']) && $node['used'] || $w > $node['w'] || $h > $node['h'])
	                return null;
               
	            if ( $w == $node['w'] && $h == $node['h'] ) {
	                $node['used'] = true;
	                return array(
	                    'x' => $node['x'],
	                    'y' => $node['y']
	                );
	            }
       
	            $node['lft'] = $this->cloneNode($node);
	            $node['rgt'] = $this->cloneNode($node);
       
	            if ( $node['w'] - $w > $node['h'] - $h ) {
	                $node['lft']['w'] = $w;
	                $node['rgt']['x'] = $node['x'] + $w;
	                $node['rgt']['w'] = $node['w'] - $w;   
	            } else {
	                $node['lft']['h'] = $h;
	                $node['rgt']['y'] = $node['y'] + $h;
	                $node['rgt']['h'] = $node['h'] - $h;                                                   
	            }
       
	            return $this->recursiveFindCoords($node['lft'], $w, $h);
	        }
	    }

	    function findCoords( $w, $h, $dataToKeep ) {
	        $coords = $this->recursiveFindCoords($this->root, $w, $h);

	        if ($coords) {
	            if ( $this->usedWidth < $coords['x'] + $w )
	                $this->usedWidth = $coords['x'] + $w;
	            if ( $this->usedHeight < $coords['y'] + $h )
	                $this->usedHeight = $coords['y'] + $h;
	        }
			
			if ( $coords ) {
				$dataToKeep['x'] = $coords['x'];
				$dataToKeep['y'] = $coords['y'];
				$this->images[] = $dataToKeep;
			}

	        return $coords;
	    }
		
	}
	
}

?>