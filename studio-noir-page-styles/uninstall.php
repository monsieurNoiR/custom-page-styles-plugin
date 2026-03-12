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

	// Delete all Style Library (sn_cps_style) posts and their meta
	$library_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'sn_cps_style'"
	);
	foreach ( $library_ids as $post_id ) {
		wp_delete_post( absint( $post_id ), true );
	}

	// Remove all post meta data (v1.x and v2.0 keys)
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s, %s)",
			'_sn_cps_css',
			'_sn_cps_selected',
			'_sn_cps_library_ids',
			'_sn_cps_linked_library_id',
			'_sn_cps_uploaded_files'
		)
	);

	// Remove plugin options
	delete_option( 'sn_cps_enabled_post_types' );
	delete_option( 'sn_cps_db_version' );
	delete_option( 'sn_cps_migration_errors' );

	// Remove CSS files directory
	$upload_dir = wp_upload_dir();
	$css_dir    = trailingslashit( $upload_dir['basedir'] ) . 'sn-cps-styles';

	if ( file_exists( $css_dir ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		if ( $wp_filesystem ) {
			$wp_filesystem->delete( $css_dir, true );
		}
	}

	// Clear any cached data
	wp_cache_flush();
}

// Execute uninstall
sn_cps_uninstall();
