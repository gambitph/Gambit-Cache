<?php

/**
 * Initializes pointer for the admin license page
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


if ( ! class_exists('GambitAdminPointers') ) {
	
	class GambitAdminPointers {
		
		// Holds the number of pointers currently active
		public static $pointersActive = 0;
		

		function __construct( $settings = array() ) {
			
			// Initialize default settings
			$defaults = array(
				'pointer_name' => 'gambit',
				'header' => __( 'Automatic Updates', 'default' ),
				'body' => __( 'Keep your plugin updated by entering your purchase code here.', 'default' ),
			);
			$this->settings = array_merge( $defaults, $settings );
			
			// Pointers are only allowed to have names in small caps (WP requirement)
			$this->settings['pointer_name'] = strtolower( $this->settings['pointer_name'] );

			// Initialize admin point headers
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueuePointerScript' ) );
			add_action( 'admin_print_footer_scripts', array( $this, 'printPointerScript' ) );
		}


		public function enqueuePointerScript() {
			if ( $this->formAdminPointer() ) {
				wp_enqueue_script( 'wp-pointer' );
				wp_enqueue_style( 'wp-pointer' );
		   }
		}


		public function printPointerScript() {
			if ( ! $this->formAdminPointer() ) {
				return;
			}
			
			// Get the pointer
			$adminPointer = $this->formAdminPointer();


			// Only allow a single pointer to exist
			// If another Gambit pointer has already been displayed, never show ours
			// so we do not clutter the screen. (this might happen if multiple
			// plugins are activated at the same time)
			if ( self::$pointersActive > 0 ) {
				$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
				
				if ( ! in_array( $adminPointer['name'], $dismissed ) ) {
					$dismissed[] = $adminPointer['name'];
					update_user_meta( get_current_user_id(), 'dismissed_wp_pointers', implode( ',', $dismissed ) );
				}
				
				return;
			}
			
			
			// Start the pointer
			?>
			<script type="text/javascript">
				( function($) {
					var $a = $('#menu-plugins');
					if ( $('a[href="plugins.php?page=gambit_plugins"]').length > 0 ) {
						if ( $('a[href="plugins.php?page=gambit_plugins"]').offset().top > 0 ) {
							$a = $('a[href="plugins.php?page=gambit_plugins"]');
						}
					}
					$a.pointer( {
						content: '<?php echo $adminPointer['content'] ?>',
						position: {
							edge: 'left',
							align: 'middle'
						},
						close: function() {
							$.post( ajaxurl, {
								pointer: '<?php echo esc_attr( $adminPointer['name'] ) ?>',
								action: 'dismiss-wp-pointer'
							} );
						}
					} ).pointer( 'open' );
				} )(jQuery);
			</script>
			<?php
			
			
			// Admin pointers displayed + 1
			self::$pointersActive++;
		}

		public function formAdminPointer() {
			$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
			
			if ( in_array( $this->settings['pointer_name'], $dismissed ) ) {
				return false;
			}

			$content = '<h3>' . esc_attr( $this->settings['header'] ) . '</h3>';
			$content .= '<p>' . esc_attr( $this->settings['body'] ) . '</p>';

			return array( 
				'name' => $this->settings['pointer_name'],
				'content' => $content,
			);
		}

	}
	
}