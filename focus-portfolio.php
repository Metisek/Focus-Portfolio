<?php
/**
 * Plugin Name:       Focus Portfolio
 * Plugin URI:        https://github.com/Metisek/Focus-Portfolio
 * Description:       Portfolio zdjęć czytane prosto z folderu na hostingu (kategorie → wydarzenia → zdjęcia), z automatycznym skanowaniem, miniaturami WebP i importem zdjęć.
 * Version:           1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Klub Filmowo-Fotograficzny Focus
 * Text Domain:       focus-portfolio
 * License:           GPL-2.0-or-later
 * Update URI:        https://github.com/Metisek/Focus-Portfolio
 */

defined( 'ABSPATH' ) || exit;

define( 'FP_VERSION', '1.1' );
define( 'FP_FILE', __FILE__ );
define( 'FP_DIR', plugin_dir_path( __FILE__ ) );
define( 'FP_URL', plugin_dir_url( __FILE__ ) );
define( 'FP_REPO', 'https://github.com/Metisek/Focus-Portfolio/' );
define( 'FP_TIP_URL', 'https://buycoffee.to/metis' );

/** Wersja pliku CSS/JS dla przeglądarki: wersja wtyczki + data zmiany pliku (nowy plik = brak starej kopii w pamięci podręcznej). */
function fp_ver( $rel ) {
	$t = @filemtime( FP_DIR . $rel ); // phpcs:ignore
	return FP_VERSION . ( $t ? '.' . $t : '' );
}

require_once FP_DIR . 'includes/class-fp-caps.php';
require_once FP_DIR . 'includes/class-fp-settings.php';
require_once FP_DIR . 'includes/class-fp-i18n.php';
require_once FP_DIR . 'includes/class-fp-meta.php';
require_once FP_DIR . 'includes/class-fp-people.php';
require_once FP_DIR . 'includes/class-fp-scanner.php';
require_once FP_DIR . 'includes/class-fp-thumbs.php';
require_once FP_DIR . 'includes/class-fp-links.php';
require_once FP_DIR . 'includes/class-fp-frontend.php';
require_once FP_DIR . 'includes/class-fp-embeds.php';
require_once FP_DIR . 'includes/class-fp-photos-source.php';
require_once FP_DIR . 'includes/class-fp-photos-picker.php';
require_once FP_DIR . 'includes/class-fp-photos-views.php';
require_once FP_DIR . 'includes/class-fp-photos.php';
require_once FP_DIR . 'includes/class-fp-updates.php';

if ( is_admin() ) {
	require_once FP_DIR . 'includes/class-fp-importer.php';
	require_once FP_DIR . 'includes/class-fp-admin.php';
	require_once FP_DIR . 'includes/class-fp-fs.php';
	FP_Admin::init();
	FP_FS::init();
}

FP_Thumbs::init();
add_action( 'init', array( 'FP_Settings', 'maybe_upgrade' ), 5 );
FP_Frontend::init();
FP_Embeds::init();
FP_Photos::init();
FP_Updates::init();

add_filter( 'cron_schedules', function ( $s ) {
	$s['fp_quarter'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Co 15 minut (Focus Portfolio)' );
	return $s;
} );

add_action( 'fp_cron', function () {
	FP_Scanner::maybe_rescan( true );
	FP_Thumbs::process_batch( (int) FP_Settings::get( 'cron_batch' ), 40 );
} );

register_activation_hook( __FILE__, function () {
	FP_Settings::ensure_defaults();
	FP_Caps::install();
	if ( ! wp_next_scheduled( 'fp_cron' ) ) {
		wp_schedule_event( time() + 60, 'fp_quarter', 'fp_cron' );
	}
	update_option( 'fp_flush_rewrite', 1 );
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'fp_cron' );
	flush_rewrite_rules();
} );
