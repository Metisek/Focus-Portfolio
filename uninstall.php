<?php
/**
 * Usunięcie wtyczki: kasuje ustawienia, manifest i cache widżetu „Zdjęcia”, rolę „Redaktor portfolio” i uprawnienia
 * fp_view / fp_edit / fp_admin oraz bloki „Focus Portfolio” w plikach .htaccess katalogu źródłowego.
 * Zdjęcia w folderze źródłowym zostają; folder miniatur uploads/portfolio-focus-cache można usunąć ręcznie przez FTP.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// blokady dostępu (.htaccess) zapisane przez FP_Protect: zdejmowany tylko blok wtyczki, reszta pliku zostaje
$fp_prot = get_option( 'fp_protected' );
if ( is_array( $fp_prot ) && ! empty( $fp_prot['root'] ) && is_dir( $fp_prot['root'] ) ) {
	$fp_root = untrailingslashit( wp_normalize_path( $fp_prot['root'] ) );
	$fp_dirs = array_merge( array( '' ), isset( $fp_prot['dirs'] ) ? (array) $fp_prot['dirs'] : array() );
	foreach ( $fp_dirs as $fp_rel ) {
		$fp_abs = realpath( $fp_root . ( '' === $fp_rel ? '' : '/' . $fp_rel ) );
		$fp_abs = $fp_abs ? wp_normalize_path( $fp_abs ) : '';
		$fp_ht  = $fp_abs . '/.htaccess';
		if ( ! $fp_abs || 0 !== strpos( $fp_abs . '/', $fp_root . '/' ) || ! is_file( $fp_ht ) ) {
			continue;
		}
		$fp_old  = (string) @file_get_contents( $fp_ht ); // phpcs:ignore
		$fp_rest = (string) preg_replace( '/^# BEGIN Focus Portfolio\R.*?^# END Focus Portfolio\R?/ms', '', $fp_old );
		if ( $fp_rest === $fp_old ) {
			continue;
		}
		if ( '' === trim( $fp_rest ) ) {
			wp_delete_file( $fp_ht );
		} else {
			@file_put_contents( $fp_ht, ltrim( $fp_rest, "\r\n" ), LOCK_EX ); // phpcs:ignore
		}
	}
}

foreach ( array( 'fp_settings', 'fp_view', 'fp_manifest', 'fp_signature', 'fp_thumbs_done', 'fp_thumb_ext', 'fp_flush_rewrite', 'fp_covers', 'fp_db_version', 'fp_authors', 'fp_structure', 'fp_people', 'fp_migrate_meta', 'fp_photos', 'fp_protected' ) as $opt ) {
	delete_option( $opt );
}
// cache i „worki” widżetu „Focus Portfolio – Zdjęcia” (fp_ph_<hash>)
global $wpdb;
foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'fp_ph_' ) . '%' ) ) as $opt ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	delete_option( $opt );
}
// zapamiętane sprawdzenia „źródło w Bibliotece mediów” (fp_media_<md5>)
foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_fp_media_' ) . '%' ) ) as $opt ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	delete_transient( substr( $opt, strlen( '_transient_' ) ) );
}
delete_transient( 'fp_last_check' );
delete_transient( 'fp_photos_gc' );
wp_clear_scheduled_hook( 'fp_cron' );

// rola „Redaktor portfolio” i uprawnienia wtyczki we wszystkich rolach (także nadane w Ustawieniach → Uprawnienia)
// osoby z tą rolą dostają rolę domyślną (zwykle Subskrybent), żeby nie zostały z rolą, której już nie ma
foreach ( get_users( array( 'role' => 'fp_editor', 'blog_id' => get_current_blog_id() ) ) as $fp_user ) {
	$fp_user->remove_role( 'fp_editor' );
	if ( ! $fp_user->roles ) {
		$fp_user->set_role( get_option( 'default_role', 'subscriber' ) );
	}
}
remove_role( 'fp_editor' );
foreach ( array_keys( wp_roles()->roles ) as $fp_slug ) {
	$fp_role = get_role( $fp_slug );
	if ( $fp_role ) {
		foreach ( array( 'fp_view', 'fp_edit', 'fp_admin' ) as $fp_cap ) {
			$fp_role->remove_cap( $fp_cap );
		}
	}
}
