<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'GambitCacheActivation' ) ) {

class GambitCacheActivation {
	
	public $missing = array();
	public $filesystemPermissionAction = '';
	public $uploadMethod = '';
	
	function __construct() {
		
		add_action( 'admin_init', array( $this, 'performFileSystemActions' ), 2 );
		add_action( 'admin_init', array( $this, 'checkStatus' ), 1 );
		add_action( 'admin_notices', array( $this, 'setupNotice' ) );
		add_action( 'admin_notices', array( $this, 'setupDoneNotice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueScripts' ) );
		
		// If our Object Cache isn't initialized, warn
		if ( ! class_exists( 'GambitObjectCache' ) ) {
			return;
		}
		
	}
	
	public function enqueueScripts() {
		if ( count( $this->missing ) == 0 ) {
			return;
		}
		wp_enqueue_style( 'gambit_cache_admin', plugins_url( 'combinator/css/admin.css', GAMBIT_COMBINATOR_PATH ) );
	}
	
	public function performFileSystemActions() {
		if ( empty( $_GET['gambit_cache_check'] ) ) {
			return;
		}
		if ( ! empty( $_POST ) && check_admin_referer( 'gambitCachePerformFSActions' ) ) {
			
			global $wp_filesystem;
			
			$in = true;
			$url = wp_nonce_url( $_SERVER['REQUEST_URI'], 'gambitCachePerformFSActions' );
	        if (false === ($creds = request_filesystem_credentials( $url, $this->uploadMethod, false, false, array('SaveCBESettings')) ) ) {
	            $in = false;
	        }
	        if ( ! $in || ! WP_Filesystem( $creds ) ) {
				return;
	        }
			
			$creationErrors = array();
			
			foreach ( $this->missing as $missing ) {
				if ( $missing['who'] == 'me' ) {
					
					// Update, delete the gambit-cache folder
					if ( $missing['what'] == 'gambit-cache' && $missing['why'] == 'update' ) {
						
						$dest = $wp_filesystem->wp_content_dir() . 'gambit-cache';
 						if ( $wp_filesystem->exists( $dest ) ) {
 							if ( ! $wp_filesystem->rmdir( $dest, true ) ) {
								$creationErrors[] = new WP_Error( 'cannotdelete', sprintf( __( "Could not replace %s, please perform this manually", GAMBIT_COMBINATOR ), '<code>' . $dest . '</code>' ) );
								continue;
 							}
 						}
						
						// Set the why to notfound so that we trigger a folder write
						$missing['why'] = 'notfound';
					}
					
					
					// Create gambit-cache
					if ( $missing['what'] == 'gambit-cache' && $missing['why'] == 'notfound' ) {

						$dest = $wp_filesystem->wp_content_dir() . 'gambit-cache';
						$src = trailingslashit( trailingslashit( plugin_dir_path( GAMBIT_COMBINATOR_PATH ) . 'combinator' ) . 'wp-content' ) . 'gambit-cache';
						
						if ( ! $wp_filesystem->exists( $dest ) ) {
							$wp_filesystem->mkdir( $dest, 0755 );
						}
						
						$result = copy_dir( $src, $dest );
						if ( is_wp_error( $result ) ) {
							$creationErrors[] = new WP_Error( 'cannotcopy', sprintf( __( "Could not copy %s to %s, please perform this manually via FTP", GAMBIT_COMBINATOR ), '<code>' . $src . '</code>', '<code>' . $dest . '</code>' ) );
						}
						
						if ( ! $wp_filesystem->exists( trailingslashit( $dest ) . 'minify-cache' ) ) {
							$wp_filesystem->mkdir( trailingslashit( $dest ) . 'minify-cache', 0755 );
						}
						if ( ! $wp_filesystem->exists( trailingslashit( $dest ) . 'object-cache' ) ) {
							$wp_filesystem->mkdir( trailingslashit( $dest ) . 'object-cache', 0755 );
						}
						if ( ! $wp_filesystem->exists( trailingslashit( $dest ) . 'page-cache' ) ) {
							$wp_filesystem->mkdir( trailingslashit( $dest ) . 'page-cache', 0755 );
						}
						
					// advanced-cache.php & object-cache.php
					} else if ( $missing['what'] == 'advanced-cache.php' || $missing['what'] == 'object-cache.php' ) {
						
						$parts = explode( '.', $missing['what'] );
						$bak = $wp_filesystem->wp_content_dir() . $parts[0] . '-' . substr( md5( microtime() ), 0, 8 ) . '.' . $parts[1] . '.bak';
						$dest = $wp_filesystem->wp_content_dir() . $missing['what'];
						$src = trailingslashit( trailingslashit( plugin_dir_path( GAMBIT_COMBINATOR_PATH ) . 'combinator' ) . 'wp-content' ) . $missing['what'];
						
						// If the file already exists, move it to a random filename
						if ( $missing['why'] == 'exists' ) {
							if ( ! $wp_filesystem->move( $dest, $bak, false ) ) {
								$creationErrors[] = new WP_Error( 'cannotcopy', sprintf( __( "Could not copy %s to %s, please perform this manually via FTP", GAMBIT_COMBINATOR ), '<code>' . $src . '</code>', '<code>' . $dest . '</code>' ) );
							}
						}
						
						// Copy the file
						if ( ! $wp_filesystem->copy( $src, $dest, false, 0644 ) ) {
							$creationErrors[] = new WP_Error( 'cannotcopy', sprintf( __( "Could not copy %s to %s, please perform this manually via FTP", GAMBIT_COMBINATOR ), '<code>' . $src . '</code>', '<code>' . $dest . '</code>' ) );
						}
						
					} else if ( $missing['what'] == 'WP_CACHE' || $missing['why'] == 'false' ) {
			
						$src = $wp_filesystem->abspath() . 'wp-config.php';
						$content = $this->addWPCacheInConfig( $wp_filesystem->get_contents( $src ) );

						if ( ! $wp_filesystem->put_contents( $src, $content ) ) {
							$creationErrors[] = new WP_Error( 'cannotcopy', sprintf( __( "Could not edit %s, please turn on caching in WordPress by adding %s in this file", GAMBIT_COMBINATOR ), '<code>' . $src . '</code>', '<code>define( \'WP_CACHE\', true );</code>' ) );
						}

					}
				}
			}
			
			update_option( 'gambit_cache_setup_done', VERSION_GAMBIT_COMBINATOR );
			
			if ( ! empty( $creationErrors ) ) {
				set_transient( 'gambit_cache_activation_errors', serialize( $creationErrors ), MINUTE_IN_SECONDS * 5 );
			} else {
				wp_redirect( add_query_arg( array( 'gambit_cache_activation' => 1 ), remove_query_arg( '_wpnonce', $_SERVER['REQUEST_URI'] ) ) );
				exit();
			}
			
		}
	}
	
	
	public function setupDoneNotice() {
		$activationErrors = maybe_unserialize( get_transient( 'gambit_cache_activation_errors' ) );
		if ( ! empty( $activationErrors ) ) {
			
			// add notice for errors or if completed
			delete_option( 'gambit_cache_setup_done' );
			
			echo "<div class='error gambitcache_notice'><h3>Gambit Cache Activation Errors</h3>";
			echo "<p>We encountered some errors while setting up, you will have to perform these to continue:";
			echo "<ol>";
			foreach ( $activationErrors as $error ) {
				echo "<li>" . $error->get_error_message() . ",</li>";
			}
			echo "</ol></p></div>";
			
			delete_transient( 'gambit_cache_activation_errors' );
			
		}
	}
	
	
	public function checkStatus() {
		$cacheSetupDone = get_option( 'gambit_cache_setup_done' );
		if ( $cacheSetupDone == VERSION_GAMBIT_COMBINATOR || get_transient( 'gambit_cache_activation_errors' ) ) {
			return;
		}
		
		$update = ! empty( $cacheSetupDone ) ? $cacheSetupDone != VERSION_GAMBIT_COMBINATOR : false;

		$this->checkObjectCache( $update );
		$this->checkAdvancedCache( $update );
		$this->checkWPCache();
		$this->checkCacheDir( $update );
		
		$this->checkFileSystemCredentials();
		
		if ( ! count( $this->missing ) ) {
			update_option( 'gambit_cache_setup_done', VERSION_GAMBIT_COMBINATOR );
		}
	}
	
	public function addWPCacheInConfig( $content ) {
		$defineRegex = '/(.*)((\/\/)?)(.*define.*WP_CACHE.*)(false|true)(.*;)/';
		$phpRegex = '/(<\?php)/';
		
		if ( preg_match( $defineRegex, $content ) ) {
			return preg_replace( $defineRegex, '$2$3$4true$6', $content );
		} else {
			return preg_replace( $phpRegex, "$1\n/* Added by Gambit Cache */\ndefine( 'WP_CACHE', true );", $content );
		}

	}
	
	public function checkWPCache() {
		if ( ! WP_CACHE && empty( $_GET['gambit_cache_activation'] ) ) {
			
			global $wp_filesystem;
			WP_Filesystem( $_SERVER['REQUEST_URI'] );
			$configPath = $wp_filesystem->abspath() . 'wp-config.php';
			
			if ( $wp_filesystem->exists( $configPath ) ) {

				if ( ! $wp_filesystem->is_writable( $configPath ) ) {
				
					if ( ! $wp_filesystem->chmod( $configPath, 0644 ) ) {

						$this->missing[] = array(
							'what' => 'WP_CACHE',
							'why' => 'false',
							'how' => 'Caching in WordPress is currently <strong>disabled</strong>, please turn it on using <code>define( \'WP_CACHE\', true );</code> inside your <strong><em>wp-config.php</em></strong> to enable caching,',
							'who' => 'user',
						);
						return false;
						
					}
				}

				$this->missing[] = array(
					'what' => 'WP_CACHE',
					'why' => 'false',
					'how' => 'Caching in WordPress is currently <strong>disabled</strong>, we need to add <code>define( \'WP_CACHE\', true );</code> inside your <strong><em>wp-config.php</em></strong> to enable caching,',
					'who' => 'me',
				);
				return false;
			}

			$this->missing[] = array(
				'what' => 'WP_CACHE',
				'why' => 'false',
				'how' => 'Caching in WordPress is currently <strong>disabled</strong>, please turn it on using <code>define( \'WP_CACHE\', true );</code> inside your <strong><em>wp-config.php</em></strong> to enable caching,',
				'who' => 'user',
			);
			return false;
			
		}
		return true;
	}
	
	public function checkFileSystemCredentials() {
		$willDoSomething = false;
		foreach ( $this->missing as $missing ) {
			if ( $missing['who'] == 'me' ) {
				$willDoSomething = true;
			}
		}
		
		if ( ! $willDoSomething ) {
			return;
		}
		
		ob_start();
		$in = true;
		// $url = wp_nonce_url( $_SERVER['REQUEST_URI'], 'gambitCachePerformFSActions' );
		$url = wp_nonce_url( add_query_arg( array( 'gambit_cache_check' => 1 ), $_SERVER['REQUEST_URI'] ), 'gambitCachePerformFSActions' );
        if (false === ($creds = request_filesystem_credentials( $url, $this->uploadMethod, false, false, array('SaveCBESettings')) ) ) {
            $in = false;
        }
        if ( $in && ! WP_Filesystem( $creds ) ) {
            // our credentials were no good, ask the user for them again
            request_filesystem_credentials($url, $this->uploadMethod, true, false, array('SaveCBESettings'));
            $in = false;
        }
		if ( ! $in ) {
			$this->filesystemPermissionAction = ob_get_contents();
		}
		ob_end_clean();

		if ( $in ) {
			$this->filesystemPermissionAction = '<form method="POST" action="' . esc_url( $url ) .'">';
			$this->filesystemPermissionAction .= '<input type="submit" name="upgrade" id="upgrade" class="button" value="Proceed">';
			$this->filesystemPermissionAction .= '</form>';
		}
	}
	
	
	public function checkCacheDir( $update = false ) {
		
		global $wp_filesystem;
		WP_Filesystem( $_SERVER['REQUEST_URI'] );
		$cachePath = $wp_filesystem->wp_content_dir() . 'gambit-cache';
		$parentDir = $wp_filesystem->wp_content_dir();

		global $wp_filesystem;

		if ( ! $wp_filesystem->exists( $cachePath ) ) {

			if ( ! $wp_filesystem->is_writable( $parentDir ) ) {
				
				if ( ! $wp_filesystem->chmod( $parentDir, 0755 ) ) {
				
					$this->missing[] = array(
						'what' => 'gambit-cache',
						'why' => 'unwritable',
						'how' => 'We cannot create the folder <strong><em>gambit-cache</em></strong> inside your contents folder, you will have to make the directory <code>' . $parentDir . '</code> writable first by setting its permissions to <strong>755</strong>, then refresh this page,',
						'who' => 'user',
					);
					return false;
				}
			}
			
			$this->missing[] = array(
				'what' => 'gambit-cache',
				'why' => 'notfound',
				'how' => 'We need to create a folder called <strong><em>gambit-cache</em></strong> in your contents folder, this will contain your cached data along with some stuff that we need to work,',
				'who' => 'me',
			);
			return false;
		}
		if ( ! $wp_filesystem->is_writable( $cachePath ) ) {
			if ( ! $wp_filesystem->chmod( $cachePath, 0755 ) ) {
			
				if ( ! $update ) {
					$this->missing[] = array(
						'what' => 'gambit-cache',
						'why' => 'unwritable',
						'how' => 'The folder <code>' . $cachePath . '</code> is unwritable, you will have to change its permissions to <strong>755</strong> so we can write to it,',
						'who' => 'user',
					);
				} else {
					$this->missing[] = array(
						'what' => 'gambit-cache',
						'why' => 'unwritable',
						'how' => 'The folder <code>' . $cachePath . '</code> is unwritable, you will have to change its permissions to <strong>755</strong> so we can update it,',
						'who' => 'user',
					);
				}
				return false;
			}
		}
		if ( $update ) {
			$this->missing[] = array(
				'what' => 'gambit-cache',
				'why' => 'update',
				'how' => 'We need to update the folder <strong><em>gambit-cache</em></strong> in your contents folder, we will replace all of its contents with the newer one,',
				'who' => 'me',
			);
			return false;
		}
		return true;
	}
	
	// public function checkWPCache() {
	// 	if ( $this->advancedCacheEnabled && ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) ) {
	// 		$this->missing[] = array(
	// 			'what' => 'WP_CACHE',
	// 			'why' => 'false',
	// 			'how' => 'Caching in WordPress is currently <strong>disabled</strong>, please turn it on using <code>define( \'WP_CACHE\', true );</code> inside your <strong><em>wp-config.php</em></strong> to enable caching,',
	// 			'who' => 'user',
	// 		);
	// 		return;
	// 	}
	// }
	
	public function checkAdvancedCache( $update = false ) {
		global $wp_filesystem;
		WP_Filesystem( $_SERVER['REQUEST_URI'] );
		$cachePath = $wp_filesystem->wp_content_dir() . 'advanced-cache.php';
		
		if ( ! $wp_filesystem->exists( $cachePath ) || $update ) {
			
			// global $wp_filesystem;
			// WP_Filesystem( $_SERVER['REQUEST_URI'] );
			// $cachePath = $wp_filesystem->wp_content_dir() . 'advanced-cache.php';
			
			// if ( ! WP_CACHE && $wp_filesystem->exists( $cachePath ) ) {
			// 	return true;
			// }
			// if ( WP_CACHE && class_exists( 'GambitAdvancedCache' ) ) {
			// 	return true;
			// }
			
			// Check if there is an object-cache.php
			if ( ! $wp_filesystem->exists( $cachePath ) ) {
				
				if ( ! $wp_filesystem->is_writable( $wp_filesystem->wp_content_dir() ) ) {
					if ( ! $wp_filesystem->chmod( $wp_filesystem->wp_content_dir(), 0755 ) ) {
						$this->missing[] = array(
							'what' => 'advanced-cache.php',
							'why' => 'unwritable',
							'how' => 'We cannot create the file <strong><em>advanced-cache.php</em></strong> inside your contents folder, you will have to make the directory <code>' . $wp_filesystem->wp_content_dir() . '</code> writable first by setting its permissions to <strong>755</strong>, then refresh this page,',
							'who' => 'user',
						);
						return false;
					}
				}
				
				$this->missing[] = array(
					'what' => 'advanced-cache.php',
					'why' => 'notfound',
					'how' => 'We need to create a file called <strong><em>advanced-cache.php</em></strong> in your contents folder,',
					'who' => 'me',
				);
				return false;
			}
			
			// If it exists, and not writable
			if ( ! $wp_filesystem->is_writable( $cachePath ) ) {
				if ( ! $wp_filesystem->chmod( $cachePath, 0644 ) ) {
					
					if ( ! $update ) {
						$this->missing[] = array(
							'what' => 'advanced-cache.php',
							'why' => 'unwritable',
							'how' => 'An existing <strong><em>advanced-cache.php</em></strong> was found in your contents folder, but we cannot remove it, please remove it manually.',
							'who' => 'user',
						);
					} else {
						$this->missing[] = array(
							'what' => 'advanced-cache.php',
							'why' => 'unwritable',
							'how' => 'We need to update your <strong><em>advanced-cache.php</em></strong>, but we cannot remove it, please remove it manually.',
							'who' => 'user',
						);
					}
					return false;
				}
			}
			
			if ( ! $wp_filesystem->is_writable( $wp_filesystem->wp_content_dir() ) ) {
				if ( ! $wp_filesystem->chmod( $wp_filesystem->wp_content_dir(), 0755 ) ) {
					
					if ( ! $update ) {
						$this->missing[] = array(
							'what' => 'advanced-cache.php',
							'why' => 'unwritable',
							'how' => 'An existing <strong><em>advanced-cache.php</em></strong> was found, but we cannot write files to the content directory so we cannot back it up, please change the permissions of <code>' . $wp_filesystem->wp_content_dir() . '</code> to <strong>755</strong>, then refresh this page.',
							'who' => 'user',
						);
					} else {
						$this->missing[] = array(
							'what' => 'advanced-cache.php',
							'why' => 'unwritable',
							'how' => 'We need to update your <strong><em>advanced-cache.php</em></strong>, but we cannot write files to the content directory so we cannot back it up, please change the permissions of <code>' . $wp_filesystem->wp_content_dir() . '</code> to <strong>755</strong>, then refresh this page.',
							'who' => 'user',
						);
					}
					return false;
				}
			}
			
			// If it exists, then it's not ours
			if ( ! $update ) {
				$this->missing[] = array(
					'what' => 'advanced-cache.php',
					'why' => 'exists',
					'how' => 'An existing <strong><em>advanced-cache.php</em></strong> was found in your contents folder, we will back this up and add our own,',
					'who' => 'me',
				);
			} else {
				$this->missing[] = array(
					'what' => 'advanced-cache.php',
					'why' => 'exists',
					'how' => 'We need to update your <strong><em>advanced-cache.php</em></strong>, we will back this up and add the newer one,',
					'who' => 'me',
				);
			}
			return false;
		}
		return true;
	}
	
	public function checkObjectCache( $update = false ) {
		if ( ! class_exists( 'GambitObjectCache' ) || $update ) {
			
			global $wp_filesystem;
			WP_Filesystem( $_SERVER['REQUEST_URI'] );
			$cachePath = $wp_filesystem->wp_content_dir() . 'object-cache.php';
			
			// Check if there is an object-cache.php
			if ( ! $wp_filesystem->exists( $cachePath ) ) {
				
				if ( ! $wp_filesystem->is_writable( $wp_filesystem->wp_content_dir() ) ) {
					if ( ! $wp_filesystem->chmod( $wp_filesystem->wp_content_dir(), 0755 ) ) {
					
						$this->missing[] = array(
							'what' => 'object-cache.php',
							'why' => 'unwritable',
							'how' => 'We cannot create the file <strong><em>object-cache.php</em></strong> inside your contents folder, you will have to make the directory <code>' . $wp_filesystem->wp_content_dir() . '</code> writable first by setting its permissions to <strong>755</strong>, then refresh this page,',
							'who' => 'user',
						);
						return false;
						
					}
				}
				
				$this->missing[] = array(
					'what' => 'object-cache.php',
					'why' => 'notfound',
					'how' => 'We need to create a file called <strong><em>object-cache.php</em></strong> in your contents folder,',
					'who' => 'me',
				);
				return false;;
			}
			
			// If it exists, and not writable
			if ( ! $wp_filesystem->is_writable( $cachePath ) ) {
				if ( ! $wp_filesystem->chmod( $cachePath, 0644 ) ) {
					
					if ( ! $update ) {
						$this->missing[] = array(
							'what' => 'object-cache.php',
							'why' => 'unwritable',
							'how' => 'An existing <strong><em>object-cache.php</em></strong> was found, but we cannot remove it, please remove it manually.',
							'who' => 'user',
						);
					} else {
						$this->missing[] = array(
							'what' => 'object-cache.php',
							'why' => 'unwritable',
							'how' => 'We need to update your <strong><em>object-cache.php</em></strong>, but we cannot remove it, please remove it manually.',
							'who' => 'user',
						);
					}
					return false;
				}
			}
			
			if ( ! $wp_filesystem->is_writable( $wp_filesystem->wp_content_dir() ) ) {
				if ( ! $wp_filesystem->chmod( $wp_filesystem->wp_content_dir(), 0755 ) ) {
					
					if ( ! $update ) {
						$this->missing[] = array(
							'what' => 'object-cache.php',
							'why' => 'unwritable',
							'how' => 'An existing <strong><em>object-cache.php</em></strong> was found, but we cannot write files to the content directory so we cannot back it up, please change the permissions of <code>' . $wp_filesystem->wp_content_dir() . '</code> to <strong>755</strong>, then refresh this page.',
							'who' => 'user',
						);
					} else {
						$this->missing[] = array(
							'what' => 'object-cache.php',
							'why' => 'unwritable',
							'how' => 'We need to update your <strong><em>object-cache.php</em></strong>, but we cannot write files to the content directory so we cannot back it up, please change the permissions of <code>' . $wp_filesystem->wp_content_dir() . '</code> to <strong>755</strong>, then refresh this page.',
							'who' => 'user',
						);
					}
					return false;
				}
			}
			
			// If it exists, then it's not ours
			if ( ! $update ) {
				$this->missing[] = array(
					'what' => 'object-cache.php',
					'why' => 'exists',
					'how' => 'An existing <strong><em>object-cache.php</em></strong> was found, we will back this up and add our own,',
					'who' => 'me',
				);
			} else {
				$this->missing[] = array(
					'what' => 'object-cache.php',
					'why' => 'exists',
					'how' => 'We need to update your <strong><em>object-cache.php</em></strong>, we will back this up and add the newer one,',
					'who' => 'me',
				);
			}
			return false;
		}
		return true;
	}
	
	public function setupNotice() {
		if ( count( $this->missing ) == 0 || get_transient( 'gambit_cache_activation_errors' ) ) {
			return;
		}
		
		echo "<div class='error gambitcache_notice'><h3>Gambit Cache Message</h3>";

		$list = '';
		foreach ( $this->missing as $missing ) {
			if ( $missing['who'] == 'user' && $missing['what'] != 'WP_CACHE' ) {
				$list .= "<li>" . $missing['how'] . "</li>";
			}
		}
		if ( $list ) {
			echo "<p>In order for Gambit Cache to work, we first need you to do some things for us so we can continue:";
			echo "<ol>" . $list . "</ol>";
			echo "After you have done the above, refresh this page so we can check :)";
			echo "</p></div>";
			return;
		}


		$list = '';
		foreach ( $this->missing as $missing ) {
			if ( $missing['who'] == 'me' ) {
				$list .= "<li>" . $missing['how'] . "</li>";
			}
		}
		if ( $list ) {
			echo "</p>Gambit Cache needs to perform a few things in order to get started with speeding up your site:";
			echo "<ol>" . $list . "</ol>";
			echo $this->filesystemPermissionAction;
			echo "</p></div>";
			return;
		}
		
		// echo "Lastly, we need you";
		$list = '';
		foreach ( $this->missing as $missing ) {
			if ( $missing['who'] == 'user' && $missing['what'] == 'WP_CACHE' ) {
				$list .= "<li>" . $missing['how'] . "</li>";
			}
		}
		if ( $list ) {
			echo "</p>We've done setting up, one last thing..";
			echo "<ol>" . $list . "</ol>";
			echo "</p></div>";
		}
			
		
	}
	
	public function setupObjectCacheFiles() {
		if ( ! is_admin() ) {
			return false;
		}
		
		$hasObjectCache = class_exists( 'GambitObjectCache' );
		
		
		// Try and copy the object-cache.php file
		$SaveCBESettings = 1;
        $in = true;
		$url = $_SERVER['REQUEST_URI'];
		
        // $url = wp_nonce_url( 'options-general.php?page=filewriting', 'cbe-nonce' );
		ob_start();
        if (false === ($creds = request_filesystem_credentials( $url, 'ftp', false, false, null) ) ) {
            $in = false;
        }
        if ($in && ! WP_Filesystem($creds) ) {
            // our credentials were no good, ask the user for them again
            request_filesystem_credentials($url, 'ftp', true, false,null);
            $in = false;
        }
		$form = ob_get_contents();
		ob_end_clean();
		
		if ( ! empty( $form ) ) {
			echo "<div class='error gambitcache_notice'><h3>Gambit Cache Message</h3><p>In order for Gambit Cache to work, we will need to add a few files into </p>" . $form ."</div>";
		}
		
        if($in)
        {
        // by this point, the $wp_filesystem global should be working, so let's use it to create a file
        global $wp_filesystem;
        $contentdir = trailingslashit( $wp_filesystem->wp_content_dir() ); 
        $wp_filesystem->mkdir( $contentdir. 'combinator-blah' );
        if ( ! $wp_filesystem->put_contents(  $contentdir . 'combinator-blah/test.txt', 'Test file contents', FS_CHMOD_FILE) ) 
        {
            echo "error saving file!";
        }
            unset($_POST);
        }
		
		return true;
	}
	
}

}