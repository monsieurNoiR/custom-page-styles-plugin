<?php
/**
 * Uninstall script for Custom Page Styles Manager
 *
 * This file is executed when the plugin is deleted via WordPress admin.
 * It cleans up all plugin data from the database and filesystem.
 *
 * @package CustomPageStyles
 */

// Exit if not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data
 */
function sn_cps_uninstall() {
	global $wpdb;

	// Remove all post meta data
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s OR meta_key = %s",
			'_sn_cps_css',
			'_sn_cps_selected'
		)
	);

	// Remove plugin options
	delete_option( 'sn_cps_enabled_post_types' );

	// Remove CSS files directory
	$upload_dir = wp_upload_dir();
	$css_dir    = trailingslashit( $upload_dir['basedir'] ) . 'sn-cps-styles';

	if ( file_exists( $css_dir ) ) {
		// Use WordPress Filesystem API for safe file deletion
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		if ( $wp_filesystem ) {
			// Delete all files in the directory
			$files = $wp_filesystem->dirlist( $css_dir );
			if ( is_array( $files ) ) {
				foreach ( $files as $file => $file_info ) {
					$file_path = trailingslashit( $css_dir ) . $file;
					$wp_filesystem->delete( $file_path );
				}
			}

			// Delete the directory itself
			$wp_filesystem->delete( $css_dir, true );
		}
	}

	// Clear any cached data
	wp_cache_flush();
}

// Execute uninstall
sn_cps_uninstall();
