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
	'months'   => array(
		'Miesiące → wydarzenia',
		'Fotorelacje z wielu wydarzeń w roku. Na stronie pogrupowane po miesiącach, z filtrem miesięcy i lat.',
		"KATEGORIA/\n  2026-04 Kwiecień/          ← rok-miesiąc i nazwa miesiąca\n    Bal Tysiąca Świateł/      ← 1 katalog = 1 wydarzenie\n      DSC_0001.jpg\n    Juwenalia-01.jpg          ← zdjęcia luzem „Nazwa-NN” też tworzą wydarzenie\n  2025/                       ← albo lata, a w nich miesiące\n    11 Listopad/\n      Otrzęsiny/",
		'Akceptowane też nazwy „KWIECIEŃ”, „LISTOPAD 2025”, „04 Kwiecień”. Bez roku wtyczka przyjmie domyślny rok z Ustawień → Daty i zgłosi to w „Do uporządkowania”.',
	),
	'dated'    => array(
		'Wydarzenia z datą w nazwie',
		'Płaska lista wydarzeń, każde z datą na początku (jak eksport z Lightrooma). Na stronie pogrupowane po miesiącach.',
		"KATEGORIA/\n  2026-05-12 Juwenalia/       ← data i nazwa (data znika z tytułu)\n  2026-04 Bal Tysiąca Świateł/ ← sam miesiąc też wystarczy\n  2025 Plener w Kazimierzu/   ← albo sam rok",
		'Najprostszy schemat do sortowania w systemie plików: katalogi same układają się po dacie. Wydarzenie bez daty trafi do „Do uporządkowania”.',
	),
	'years'    => array(
		'Lata → wydarzenia',
		'Sesje, wyjazdy, projekty: mniej wydarzeń w roku. Na stronie pogrupowane po latach, z filtrem lat.',
		"KATEGORIA/\n  2026/\n    Sesja na dzień kobiet/    ← 1 katalog = 1 galeria\n    2026-03 Plener/           ← data w nazwie zostaje tylko do sortowania\n  2025/\n    Studniówka/",
		'Wydarzenie poza katalogiem roku, bez daty w nazwie, trafi do „Do uporządkowania”.',
	),
	'numbered' => array(
		'Edycje (numer + rok)',
		'Konkursy i cykle tematyczne (np. konkurs miesięczny). Na stronie pogrupowane po latach.',
		"KATEGORIA/\n  2026/\n    03 Ramka/                 ← numer edycji i temat\n      Imię Nazwisko.jpg       ← nazwa pliku = autor\n  07 granice 25_/             ← stary zapis: numer, temat, rok jako „25_”",
		'Edycja bez roku trafia do „Archiwum”. Rok wpiszesz w Eksploratorze albo w nazwie.',
	),
	'events'   => array(
		'Wydarzenia (bez dat)',
		'Sesje, koncerty i inne zbiory bez podziału na miesiące.',
		"KATEGORIA/\n  Sesja na dzień kobiet/      ← 1 katalog = 1 galeria\n  eliminacje varso-01.jpg     ← zdjęcia luzem „Nazwa-NN” tworzą galerię\n  DSC_1234.jpg                ← bez nazwy: grupa „Pozostałe”",
		'Datę wydarzenia (do filtrów i sortowania) ustawisz w Eksploratorze.',
	),
);
?>
<p class="fp-intro">Jak nazywać katalogi w <code><?php echo esc_html( 'wp-content/' . trim( (string) FP_Settings::get( 'source' ), '/' ) . '/' ); ?></code>,
	żeby wtyczka sama poprawnie ułożyła portfolio, i które filtry widzą odwiedzający. Każdy katalog pierwszego poziomu to kategoria.</p>

<?php FP_Admin::card( 'schemes', 'Schematy nazw katalogów' ); ?>
	<div class="fp-schemes">
	<?php foreach ( $schemes as $k => $sc ) : ?>
		<div class="fp-scheme">
			<h3><?php echo esc_html( $sc[0] ); ?></h3>
			<p><?php echo esc_html( $sc[1] ); ?></p>
			<pre class="fp-tree"><?php echo esc_html( $sc[2] ); ?></pre>
			<p class="description"><?php echo esc_html( $sc[3] ); ?></p>
		</div>
	<?php endforeach; ?>
	</div>
	<details class="fp-which">
		<summary><b>Który schemat wybrać?</b></summary>
		<ul class="fp-rules">
			<li>Dużo wydarzeń co miesiąc (fotorelacje) → <b>Miesiące → wydarzenia</b>; jeśli wolisz nazywać katalogi datą wydarzenia → <b>Wydarzenia z datą w nazwie</b>.</li>
			<li>Kilka–kilkanaście galerii w roku (sesje, wyjazdy, projekty) → <b>Lata → wydarzenia</b>.</li>
			<li>Konkurs albo cykl z numerami edycji → <b>Edycje</b>.</li>
			<li>Zbiór bez dat (portfolio osoby, „najlepsze”, archiwum) → <b>Wydarzenia (bez dat)</b>.</li>
			<li>Podział na fotografów albo klientów: zrób kategorię na osobę (np. <code>JAN KOWALSKI/</code>) z dowolnym schematem; autora zdjęć ustawisz w Eksploratorze („Domyślny autor zdjęć” katalogu).</li>
		</ul>
	</details>
	<h3>Zasady dla wszystkich kategorii</h3>
	<ul class="fp-rules">
		<li>Pliki i katalogi zaczynające się od <code>__</code> (np. <code>__kosz</code>, <code>__robocze</code>) są pomijane.</li>
		<li>Kopie, które tworzy Biblioteka mediów (<code>-150x150</code>, <code>-scaled</code>), są pomijane.</li>
		<li>Notatki w nawiasach w nazwie katalogu, np. „GRUDZIEŃ (bez ślizgawki)”, nie trafiają na stronę.</li>
		<li>Prefiks „Kopia ” w nazwach plików jest usuwany z tytułów; autor jest odczytywany z EXIF, z nazwy pliku („1_Imię Nazwisko”, „@nick”) albo ustawiany w Eksploratorze.</li>
		<li>Dane katalogu (tytuł, opis, data, okładki, kolejność, gwiazdki, autorzy) są w pliku <code>_portfolio.json</code> w tym katalogu i przenoszą się razem z nim, także przez FTP.</li>
	</ul>
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
