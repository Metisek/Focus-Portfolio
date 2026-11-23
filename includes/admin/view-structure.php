<?php
/**
 * Struktura katalogów (tylko Administracja): schemat nazw per kategoria, filtry dla odwiedzających,
 * polityka dat i instrukcja przygotowania katalogów.
 */

defined( 'ABSPATH' ) || exit;

$st    = FP_Settings::structure();
$cats  = FP_Scanner::categories( true );
$root  = FP_Settings::source_path();
$modes = array(
	'auto'     => 'Automatycznie',
	'months'   => 'Miesiące → wydarzenia',
	'dated'    => 'Wydarzenia z datą w nazwie',
	'years'    => 'Lata → wydarzenia',
	'numbered' => 'Edycje (numer + rok)',
	'events'   => 'Wydarzenia (bez dat)',
);
$schemes = array(
	'months'   => array( 'Miesiące → wydarzenia', 'wiele wydarzeń w roku, grupy po miesiącach', 'KATEGORIA/2026-04 Kwiecień/Bal/' ),
	'dated'    => array( 'Wydarzenia z datą w nazwie', 'płaska lista, data na początku nazwy', 'KATEGORIA/2026-05-12 Juwenalia/' ),
	'years'    => array( 'Lata → wydarzenia', 'kilka galerii w roku, grupy po latach', 'KATEGORIA/2026/Plener/' ),
	'numbered' => array( 'Edycje (numer + rok)', 'konkursy i cykle', 'KATEGORIA/2026/03 Ramka/' ),
	'events'   => array( 'Wydarzenia (bez dat)', 'zbiory bez podziału na czas', 'KATEGORIA/Sesja na dzień kobiet/' ),
);
$doc = FP_Admin::url( 'help' );
?>
<p class="fp-intro">Jak nazywać katalogi w <code><?php echo esc_html( 'wp-content/' . trim( (string) FP_Settings::get( 'source' ), '/' ) . '/' ); ?></code>,
	żeby wtyczka sama poprawnie ułożyła portfolio, i które filtry widzą odwiedzający. Każdy katalog pierwszego poziomu to kategoria.</p>

<?php FP_Admin::card( 'schemes', 'Schematy nazw katalogów' ); ?>
	<p>Pełne opisy schematów z przykładami, zakresy dat (np. <code>2024-10 – 2025-02 Nazwa</code>), podpowiedź „Który schemat wybrać?” i zasady nazw są w
		<a href="<?php echo esc_url( $doc . '#nazwy' ); ?>"><b>Instrukcji, rozdział 3</b></a>. Tu tylko ściąga:</p>
	<table class="widefat striped fp-scheme-mini">
		<thead><tr><th>Schemat</th><th>Kiedy</th><th>Przykład</th></tr></thead>
		<tbody>
		<?php foreach ( $schemes as $sc ) : ?>
			<tr><td><b><?php echo esc_html( $sc[0] ); ?></b></td><td><?php echo esc_html( $sc[1] ); ?></td><td><code><?php echo esc_html( $sc[2] ); ?></code></td></tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<div class="fp-namecheck" id="fp-namecheck">
		<h3>Sprawdź nazwę przed utworzeniem</h3>
		<div class="fp-inline">
			<select id="fp-nc-cat" aria-label="Kategoria"><?php foreach ( $cats as $c ) : ?><option value="<?php echo esc_attr( $c['dir'] ); ?>"><?php echo esc_html( $c['dir'] ); ?></option><?php endforeach; ?></select>
			<input type="text" id="fp-nc-path" class="regular-text" placeholder="np. 2026-11 Listopad/Otrzęsiny WIL" aria-label="Ścieżka w kategorii">
			<button type="button" class="button" id="fp-nc-go">Sprawdź</button>
		</div>
		<p id="fp-nc-out" class="fp-nc-out" aria-live="polite"></p>
	</div>
<?php FP_Admin::card_end(); ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="fp_save_structure">
	<?php wp_nonce_field( 'fp_save_structure' ); ?>

	<?php FP_Admin::card( 'cats', 'Kategorie i filtry' ); ?>
		<?php if ( ! $cats ) : ?>
			<p>Brak kategorii. Sprawdź katalog źródłowy w <a href="<?php echo esc_url( FP_Admin::url( 'settings' ) ); ?>">Ustawieniach</a>.</p>
		<?php else : ?>
		<p class="description">Zmiana schematu przeskanuje katalog po zapisaniu. Tytuł i opis kategorii zmienisz też w Eksploratorze (mają tam pierwszeństwo).</p>
		<div class="fp-table-wrap">
		<table class="widefat striped fp-cats-table">
			<thead><tr><th>Katalog</th><th>Schemat</th><th>Filtry dla odwiedzających</th><th>Nazwa</th><th>Opis</th><th>Grupa bez nazwy</th><th>Kolejność</th><th>Ukryj</th></tr></thead>
			<tbody>
			<?php
			foreach ( $cats as $c ) :
				$conf = FP_Settings::category( $c['dir'] );
				$n    = 'fs[categories][' . esc_attr( $c['dir'] ) . ']';
				?>
				<tr>
					<td><code><?php echo esc_html( $c['dir'] ); ?>/</code><br><span class="description"><?php echo esc_html( FP_Frontend::photos_word( $c['count'] ) . ' · ' . count( $c['groups'] ) . ' galerii' ); ?></span></td>
					<td><select name="<?php echo $n; // phpcs:ignore ?>[mode]">
						<?php foreach ( $modes as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, $conf['mode'] ); ?>><?php echo esc_html( 'auto' === $k ? $l . ' (' . $modes[ $c['mode'] ] . ')' : $l ); ?></option>
						<?php endforeach; ?>
					</select></td>
					<td class="fp-filters">
						<label><input type="checkbox" name="<?php echo $n; // phpcs:ignore ?>[f_period]" value="1" <?php checked( $conf['filters']['period'] ); ?>> miesiące / lata</label>
						<label><input type="checkbox" name="<?php echo $n; // phpcs:ignore ?>[f_author]" value="1" <?php checked( $conf['filters']['author'] ); ?>> autorzy</label>
						<label><input type="checkbox" name="<?php echo $n; // phpcs:ignore ?>[f_sort]" value="1" <?php checked( $conf['filters']['sort'] ); ?>> sortowanie</label>
						<label><input type="checkbox" name="<?php echo $n; // phpcs:ignore ?>[f_search]" value="1" <?php checked( $conf['filters']['search'] ); ?>> wyszukiwarka wydarzeń</label>
					</td>
					<td><input type="text" name="<?php echo $n; // phpcs:ignore ?>[title]" value="<?php echo esc_attr( $conf['title'] ); ?>" placeholder="<?php echo esc_attr( FP_Scanner::default_cat_title( $c['dir'] ) ); ?>"></td>
					<td><input type="text" name="<?php echo $n; // phpcs:ignore ?>[desc]" value="<?php echo esc_attr( $conf['desc'] ); ?>" class="fp-wide"></td>
					<td><input type="text" name="<?php echo $n; // phpcs:ignore ?>[other]" value="<?php echo esc_attr( $conf['other'] ); ?>" placeholder="<?php echo esc_attr( FP_Settings::view( 'label_other' ) ); ?>"></td>
					<td><input type="number" name="<?php echo $n; // phpcs:ignore ?>[order]" value="<?php echo (int) $conf['order']; ?>" class="small-text"></td>
					<td><input type="checkbox" name="<?php echo $n; // phpcs:ignore ?>[hidden]" value="1" <?php checked( $conf['hidden'] ); ?> aria-label="Ukryj <?php echo esc_attr( $c['dir'] ); ?>"></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php endif; ?>
	<?php FP_Admin::card_end(); ?>

	<?php FP_Admin::card( 'datepolicy', 'Wydarzenia bez daty i błędne nazwy' ); ?>
		<p class="description">Co robić z katalogami, z których nie da się odczytać daty (np. miesiąc bez roku, wydarzenie poza katalogiem miesiąca, edycja bez roku).</p>
		<fieldset class="fp-field">
			<label><input type="radio" name="fs[date_policy]" value="inbox" <?php checked( 'inbox', $st['date_policy'] ); ?>> <b>Zgłoś w „Do uporządkowania”</b> (zalecane): katalog jest widoczny, a w Eksploratorze wybierasz datę, Archiwum albo przeniesienie.</label>
			<label><input type="radio" name="fs[date_policy]" value="archive" <?php checked( 'archive', $st['date_policy'] ); ?>> Pokazuj w „Archiwum”, dopóki ktoś nie poda daty.</label>
			<label><input type="radio" name="fs[date_policy]" value="today" <?php checked( 'today', $st['date_policy'] ); ?>> Przyjmij datę wgrania katalogu (z serwera).</label>
		</fieldset>
		<label class="fp-check"><input type="checkbox" name="fs[auto_archive]" value="1" <?php checked( $st['auto_archive'] ); ?>> Porządkuj automatycznie przy skanowaniu</label>
		<p class="description fp-warn-text">Przy tej opcji skaner sam: usuwa puste katalogi (tylko całkiem puste) i oznacza wydarzenia bez daty jako „Archiwum” (zapis w <code>_portfolio.json</code>, pliki zostają na miejscu).
			Domyślnie wyłączone: bezpieczniej porządkować ręcznie w Eksploratorze.</p>
	<?php FP_Admin::card_end(); ?>

	<p><button class="button button-primary button-hero">Zapisz strukturę</button></p>
</form>
