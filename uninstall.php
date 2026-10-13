<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * @package PlaylistListBlock
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'plb_api_key' );

global $wpdb;
$like         = $wpdb->esc_like( '_transient_plb_' ) . '%';
$like_timeout = $wpdb->esc_like( '_transient_timeout_plb_' ) . '%';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$like,
		$like_timeout
	)
);
