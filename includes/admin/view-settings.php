<?php
/**
 * Ustawienia (tylko Administracja): źródło i strona, miniatury, uprawnienia.
 */

defined( 'ABSPATH' ) || exit;

$s    = FP_Settings::all();
$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'source'; // phpcs:ignore
$tabs = array( 'source' => 'Źródło i strona', 'thumbs' => 'Miniatury', 'perms' => 'Uprawnienia' );
?>
<nav class="nav-tab-wrapper fp-tabs">
	<?php foreach ( $tabs as $k => $l ) : ?>
		<a class="nav-tab<?php echo $k === $tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( FP_Admin::url( 'settings', array( 'tab' => $k ) ) ); ?>"><?php echo esc_html( $l ); ?></a>
	<?php endforeach; ?>
</nav>

<?php if ( 'source' === $tab ) : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="fp_save_settings">
	<?php wp_nonce_field( 'fp_save_settings' ); ?>

	<?php FP_Admin::card( 'source', 'Katalog źródłowy' ); ?>
		<p class="description">Dowolny katalog w <code>wp-content</code> (poza kodem strony: plugins, themes, mu-plugins, cache…). W środku: katalogi kategorii, np. <code>RELACJE/</code>, <code>SESJE/</code>.
			Osoby z uprawnieniem „Redakcja” mogą wgrywać i porządkować zdjęcia tylko w tym katalogu.</p>
		<div class="fp-field">
			<label for="fp-source">Ścieżka względem wp-content</label>
			<input type="text" id="fp-source" name="fp[source]" value="<?php echo esc_attr( $s['source'] ); ?>" class="regular-text code">
			<?php if ( FP_Settings::source_path() ) : ?>
				<p class="fp-ok">Katalog istnieje · FTP: <code><?php echo esc_html( '/wp-content/' . trim( $s['source'], '/' ) . '/' ); ?></code></p>
			<?php else : ?>
				<p class="fp-warn">Taki katalog nie istnieje albo nie może być źródłem.</p>
			<?php endif; ?>
		</div>
		<div class="fp-dirpick" id="fp-dirpick" data-current="<?php echo esc_attr( $s['source'] ); ?>">
			<div class="fp-dirpick-head"><span class="dashicons dashicons-category"></span> wp-content</div>
			<ul class="fp-dirpick-tree" data-dir=""></ul>
		</div>
		<p class="description">Kliknij katalog w drzewie, żeby go wybrać. Wyszarzone katalogi nie mogą być źródłem. Liczba obok to zdjęcia bezpośrednio w katalogu.</p>
	<?php FP_Admin::card_end(); ?>

	<?php FP_Admin::card( 'page', 'Strona portfolio' ); ?>
		<?php FP_Admin::page_field( 'fp[page_id]', 'fp-page' ); ?>
	<?php FP_Admin::card_end(); ?>

	<?php FP_Admin::card( 'lang', 'Język' ); ?>
		<div class="fp-field">
			<label for="fp-lang">Język portfolio na stronie</label>
			<select id="fp-lang" name="fp[language]">
				<option value=""><?php echo esc_html( sprintf( 'Jak strona (teraz: %s)', FP_I18n::LANGS[ FP_I18n::site_lang() ] ) ); ?></option>
				<?php foreach ( FP_I18n::LANGS as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, $s['language'] ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description">Napisy, które widzą odwiedzający: filtry, sortowanie, liczniki, miesiące, lightbox. Domyślnie język strony (Ustawienia → Ogólne, albo bieżący język z Polylang/WPML); tu możesz go wymusić.
				Tytuły i opisy katalogów zostają takie, jak je wpiszesz. Język strony spoza listy → angielski.</p>
		</div>
	<?php FP_Admin::card_end(); ?>

	<?php FP_Admin::card( 'authors', 'Autorzy i podpisy' ); ?>
		<div class="fp-grid2">
			<div class="fp-field">
				<label for="fp-ia">Podpisy w EXIF, które nie są autorem</label>
				<textarea id="fp-ia" name="fp[ignore_artists]" rows="3" class="large-text" placeholder="np. nazwa organizacji wpisana w aparatach"><?php echo esc_textarea( $s['ignore_artists'] ); ?></textarea>
				<p class="description">Po jednym w linii. Zdjęcie z takim polem „Artist” dostaje autora z nazwy pliku, z katalogu albo z OCR.</p>
			</div>
			<div class="fp-field">
				<label for="fp-os">Słowa w stopce zdjęcia, które nie są nazwiskiem</label>
				<input type="text" id="fp-os" name="fp[ocr_skip]" value="<?php echo esc_attr( $s['ocr_skip'] ); ?>" class="regular-text" placeholder="np. logo w stopce: FOCUS, KLUB">
				<p class="description">Po przecinku. OCR pominie je w stopce typu „LOGO | IMIĘ NAZWISKO” (także z drobnymi literówkami).</p>
			</div>
		</div>
	<?php FP_Admin::card_end(); ?>

	<?php
	FP_Admin::card( 'dates', 'Daty: katalogi miesięcy bez roku' );
	$ss     = max( 1, min( 12, (int) $s['season_start'] ) );
	$starts = array( 1 => 'Styczeń: rok kalendarzowy', 9 => 'Wrzesień: rok szkolny', 10 => 'Październik: rok akademicki' );
	for ( $m = 2; $m <= 12; $m++ ) {
		if ( ! isset( $starts[ $m ] ) ) {
			$starts[ $m ] = FP_Scanner::MONTH_NAMES[ $m - 1 ] . ': sezon od ' . FP_Scanner::MONTH_GEN[ $m - 1 ];
		}
	}
	ksort( $starts );
	?>
		<p class="description">Gdy katalog nazywa się tylko „LISTOPAD” albo „KWIECIEŃ”, wtyczka musi zgadnąć rok. Katalog z rokiem w nazwie albo z datą wpisaną w Eksploratorze ma zawsze pierwszeństwo.</p>
		<div class="fp-grid2">
			<div class="fp-field">
				<label for="fp-ay">Domyślny rok zdjęć</label>
				<input type="number" id="fp-ay" name="fp[academic_year]" value="<?php echo (int) $s['academic_year']; ?>" min="2015" max="2100" class="small-text">
			</div>
			<div class="fp-field">
				<label for="fp-ss">Rok zdjęć zaczyna się w</label>
				<select id="fp-ss" name="fp[season_start]"><?php foreach ( $starts as $m => $l ) : ?><option value="<?php echo (int) $m; ?>" <?php selected( $m, $ss ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			</div>
		</div>
		<p class="description" id="fp-ss-ex">
			<?php
			$y = (int) $s['academic_year'];
			if ( 1 === $ss ) {
				printf( 'Teraz: każdy miesiąc bez roku to %d (np. „LISTOPAD” → listopad %d, „KWIECIEŃ” → kwiecień %d).', $y, $y, $y );
			} else {
				printf(
					'Teraz: od %s do grudnia → %d, od stycznia do %s → %d (np. „LISTOPAD” → listopad %d, „KWIECIEŃ” → kwiecień %d).',
					esc_html( FP_Scanner::MONTH_GEN[ $ss - 1 ] ), $y, esc_html( FP_Scanner::MONTH_GEN[ $ss - 2 ] ), $y + 1,
					11 >= $ss ? $y : $y + 1, 4 >= $ss ? $y : $y + 1
				);
			}
			?>
		</p>
	<?php FP_Admin::card_end(); ?>

	<p><button class="button button-primary button-large">Zapisz</button></p>
</form>

<?php elseif ( 'thumbs' === $tab ) : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="fp_save_thumbs">
	<?php wp_nonce_field( 'fp_save_thumbs' ); ?>
	<?php FP_Admin::card( 'thumbs', 'Miniatury' ); ?>
		<div class="fp-grid4">
			<div class="fp-field"><label for="fp-gs">Siatka (px)</label><input type="number" id="fp-gs" name="fp[grid_size]" value="<?php echo (int) $s['grid_size']; ?>" min="300" max="2400" class="small-text"></div>
			<div class="fp-field"><label for="fp-ls">Powiększenie (px)</label><input type="number" id="fp-ls" name="fp[large_size]" value="<?php echo (int) $s['large_size']; ?>" min="1000" max="4000" class="small-text"></div>
			<div class="fp-field"><label for="fp-q">Jakość (%)</label><input type="number" id="fp-q" name="fp[quality]" value="<?php echo (int) $s['quality']; ?>" min="40" max="95" class="small-text"></div>
			<div class="fp-field"><label for="fp-cb">Zdjęć na przebieg crona</label><input type="number" id="fp-cb" name="fp[cron_batch]" value="<?php echo (int) $s['cron_batch']; ?>" min="1" max="200" class="small-text"></div>
		</div>
		<label class="fp-check"><input type="checkbox" name="fp[ondemand]" value="1" <?php checked( $s['ondemand'] ); ?>> Twórz brakującą miniaturę przy pierwszym wyświetleniu zdjęcia</label>
		<p class="description">Siatka w <?php echo esc_html( strtoupper( FP_Thumbs::ext() ) ); ?>, powiększenie w formacie oryginału (JPG/PNG). Katalog <code>uploads/<?php echo esc_html( FP_Thumbs::CACHE ); ?>/</code>. Po zmianie rozmiaru lub jakości miniatury zrobią się od nowa.</p>
		<p><button class="button button-primary">Zapisz</button></p>
	<?php FP_Admin::card_end(); ?>
</form>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="fp_reset_thumbs">
	<?php wp_nonce_field( 'fp_reset_thumbs' ); ?>
	<?php FP_Admin::card( 'thumbs-reset', 'Miniatury od nowa', false, 'fp-danger' ); ?>
		<p>Usuwa wszystkie miniatury (oryginały zostają). Potem użyj „Wygeneruj brakujące miniatury” na Pulpicie.</p>
		<p><label class="fp-check"><input type="checkbox" name="confirm" value="1" required> Rozumiem, usuń miniatury</label></p>
		<p><button class="button">Usuń miniatury</button></p>
	<?php FP_Admin::card_end(); ?>
</form>

<?php else : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="fp_save_perms">
	<?php wp_nonce_field( 'fp_save_perms' ); ?>
	<?php FP_Admin::card( 'perms', 'Uprawnienia ról' ); ?>
		<p class="description">Poziomy się zawierają: Administracja obejmuje Redakcję, Redakcja obejmuje Podgląd. Administrator ma zawsze wszystkie.
			Konkretnym osobom nadajesz rolę w <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">Użytkownikach</a> (np. „Redaktor portfolio”).</p>
		<div class="fp-table-wrap">
		<table class="widefat striped fp-perms">
			<thead><tr><th>Rola</th>
				<?php foreach ( FP_Caps::labels() as $cap => $l ) : ?><th title="<?php echo esc_attr( $l[1] ); ?>"><?php echo esc_html( $l[0] ); ?></th><?php endforeach; ?>
				<th>Osób</th></tr></thead>
			<tbody>
			<?php
			$counts = count_users();
			foreach ( wp_roles()->roles as $slug => $role ) :
				$r = get_role( $slug );
				?>
				<tr>
					<td><b><?php echo esc_html( translate_user_role( $role['name'] ) ); ?></b><br><code><?php echo esc_html( $slug ); ?></code></td>
					<?php foreach ( array_keys( FP_Caps::labels() ) as $cap ) : ?>
						<td><input type="checkbox" name="caps[<?php echo esc_attr( $slug ); ?>][<?php echo esc_attr( $cap ); ?>]" value="1" <?php checked( $r && $r->has_cap( $cap ) ); ?> <?php disabled( 'administrator', $slug ); ?> aria-label="<?php echo esc_attr( $role['name'] . ': ' . FP_Caps::labels()[ $cap ][0] ); ?>"></td>
					<?php endforeach; ?>
					<td><?php echo (int) ( isset( $counts['avail_roles'][ $slug ] ) ? $counts['avail_roles'][ $slug ] : 0 ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<ul class="fp-perm-legend">
			<?php foreach ( FP_Caps::labels() as $cap => $l ) : ?>
				<li><b><?php echo esc_html( $l[0] ); ?></b>: <?php echo esc_html( $l[1] ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( ! get_role( FP_Caps::ROLE ) ) : ?>
			<label class="fp-check"><input type="checkbox" name="create_role" value="1"> Utwórz rolę „Redaktor portfolio” (Podgląd + Redakcja + wgrywanie plików)</label>
		<?php endif; ?>
		<p><button class="button button-primary">Zapisz uprawnienia</button></p>
	<?php FP_Admin::card_end(); ?>
</form>
<?php endif; ?>
