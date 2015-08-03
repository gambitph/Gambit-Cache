<?php

// if uninstall not called from WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) 
    exit();

// Delete all transients
global $wpdb;
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_cmbntr_%' OR option_name LIKE '_transient_timeout_cmbntr_%'" );

// Delete all generated JS & CSS
require_once( 'class-files.php' );
GambitCombinatorFiles::deleteAllFiles();