<?php
/**
 * Eksplorator: kontener aplikacji (assets/js/explorer.js).
 */

defined( 'ABSPATH' ) || exit;

FP_Settings::ensure_source_dir(); // domyślny katalog „focus-portfolio” mógł zniknąć (np. usunięty przez FTP)
if ( ! FP_Settings::source_path() ) {
	echo '<p class="fp-warn">Katalog źródłowy nie istnieje. Ustaw go w <a href="' . esc_url( FP_Admin::url( 'settings' ) ) . '">Ustawieniach</a>.</p>';
	return;
}
$conf = array(
	'root'     => 'wp-content/' . trim( (string) FP_Settings::get( 'source' ), '/' ),
	'canEdit'  => FP_Caps::can( FP_Caps::EDIT ),
	'canAdmin' => FP_Caps::can( FP_Caps::ADMIN ),
	'start'    => FP_Admin::qkey( 'view' ),
	'path'     => isset( $_GET['path'] ) && is_string( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '', // phpcs:ignore
	'ibType'   => FP_Admin::qkey( 'type' ), // filtr w „Do uporządkowania” (auto = do sprawdzenia)
	'maxSize'  => wp_max_upload_size(),
	'months'   => FP_Scanner::MONTH_NAMES,
);
?>
<?php FP_Admin::readonly_note(); ?>
<?php $fp_media = FP_Settings::source_media_conflict(); ?>
<?php if ( '' !== $fp_media ) : ?>
	<p class="fp-warn">Katalog źródłowy obejmuje pliki Biblioteki mediów WordPressa (<?php echo esc_html( $fp_media ); ?>). Przenoszenie, zmiana nazw i kosz są wyłączone, żeby nie zepsuć wpisów i stron.
		Ustaw osobny katalog na portfolio w <a href="<?php echo esc_url( FP_Admin::url( 'settings' ) ); ?>">Ustawieniach</a>.</p>
<?php endif; ?>
<div id="fpx" class="fpx" data-conf="<?php echo esc_attr( wp_json_encode( $conf ) ); ?>">
	<div class="fpx-loading">Wczytuję eksplorator…</div>
</div>
<noscript><p class="fp-warn">Eksplorator wymaga włączonego JavaScriptu.</p></noscript>
