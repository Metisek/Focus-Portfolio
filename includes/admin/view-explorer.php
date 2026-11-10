<?php
/**
 * Eksplorator: kontener aplikacji (assets/js/explorer.js).
 */

defined( 'ABSPATH' ) || exit;

if ( ! FP_Settings::source_path() ) {
	echo '<p class="fp-warn">Katalog źródłowy nie istnieje. Ustaw go w <a href="' . esc_url( FP_Admin::url( 'settings' ) ) . '">Ustawieniach</a>.</p>';
	return;
}
$conf = array(
	'root'     => 'wp-content/' . trim( (string) FP_Settings::get( 'source' ), '/' ),
	'canEdit'  => FP_Caps::can( FP_Caps::EDIT ),
	'canAdmin' => FP_Caps::can( FP_Caps::ADMIN ),
	'start'    => isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : '', // phpcs:ignore
	'path'     => isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '', // phpcs:ignore
	'maxSize'  => wp_max_upload_size(),
	'months'   => FP_Scanner::MONTH_NAMES,
);
?>
<?php FP_Admin::readonly_note(); ?>
<div id="fpx" class="fpx" data-conf="<?php echo esc_attr( wp_json_encode( $conf ) ); ?>">
	<div class="fpx-loading">Wczytuję eksplorator…</div>
</div>
<noscript><p class="fp-warn">Eksplorator wymaga włączonego JavaScriptu.</p></noscript>
