<?php
/**
 * Usunięcie wtyczki: kasuje ustawienia, manifest i cache widżetu „Zdjęcia”. Zdjęcia w folderze źródłowym zostają;
 * folder miniatur uploads/portfolio-focus-cache można usunąć ręcznie przez FTP.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'fp_settings', 'fp_view', 'fp_manifest', 'fp_signature', 'fp_thumbs_done', 'fp_thumb_ext', 'fp_flush_rewrite', 'fp_covers', 'fp_db_version', 'fp_authors', 'fp_structure', 'fp_people', 'fp_migrate_meta', 'fp_photos' ) as $opt ) {
	delete_option( $opt );
}
// cache i „worki” widżetu „Focus Portfolio – Zdjęcia” (fp_ph_<hash>)
global $wpdb;
foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'fp_ph_' ) . '%' ) ) as $opt ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	delete_option( $opt );
}
delete_transient( 'fp_last_check' );
wp_clear_scheduled_hook( 'fp_cron' );
