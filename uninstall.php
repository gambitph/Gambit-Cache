<?php

// if uninstall not called from WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) 
    exit();

// NOTE: This should correspond to the pointer_name in the main plugin file
$pointerName = strtolower( 'GambitCombinatorPlugin' );

// Deletes the dismissed admin pointer for this plugin
$dismissedAdminPointers = get_user_meta( get_current_user_id(), 'dismissed_wp_pointers' );
$dismissedAdminPointers = preg_replace( '/' . $pointerName . '(,)?)/', NULL, $dismissedAdminPointers['0'] );
$dismissedAdminPointers = preg_replace( '/(,)$/', NULL, $dismissedAdminPointers );
update_user_meta( get_current_user_id(), 'dismissed_wp_pointers', $dismissedAdminPointers );

// Delete all transients
global $wpdb;
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%' AND option_name LIKE '%cmbntr%'" );

// TODO delete wp-content/gambit-cache directory

delete_option( 'combinator_found_js' );
delete_option( 'combinator_found_css' );