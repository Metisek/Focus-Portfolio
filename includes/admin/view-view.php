<?php
/**
 * Wygląd (tylko Administracja). Widżet Elementora / blok mogą nadpisać te wartości na swojej stronie.
 */

defined( 'ABSPATH' ) || exit;

$v         = FP_Settings::view_all();
$variants  = array(
	'classic' => array( 'Klasyczny', 'Karty z podpisem pod zdjęciem. Kolory i czcionki z motywu strony.' ),
	'tiles'   => array( 'Kafelki', 'Duże zdjęcia bez odstępów, tytuły na zdjęciach; po najechaniu przyciemnienie i autorzy.' ),
);
$num = function ( $k, $label, $min, $max, $hint = '' ) use ( $v ) {
	printf(
		'<div class="fp-field"><label for="fv-%1$s">%2$s</label><input type="number" id="fv-%1$s" name="fv[%1$s]" min="%3$d" max="%4$d" value="%5$d" class="small-text">%6$s</div>',
		esc_attr( $k ), esc_html( $label ), (int) $min, (int) $max, (int) $v[ $k ], $hint ? '<span class="description">' . esc_html( $hint ) . '</span>' : ''
	);
};
$mode = function ( $k ) use ( $v ) {
	echo '<div class="fp-seg-choice">';
	foreach ( array( 'fixed' => 'Stała liczba na urządzenie', 'auto' => 'Automatycznie: od min. do maks., wg szerokości' ) as $val => $l ) {
		printf( '<label><input type="radio" name="fv[%s]" value="%s" data-fp-mode="%s"%s> %s</label>', esc_attr( $k ), esc_attr( $val ), esc_attr( $k ), checked( $val, $v[ $k ], false ), esc_html( $l ) );
	}
	echo '</div>';
};
$galleries = array(
	'masonry'   => array( 'Masonry', 'Kolumny, zdjęcia w pełnych proporcjach.' ),
	'justified' => array( 'Wyrównane rzędy', 'Rzędy równej wysokości, jak w Google Photos.' ),
	'grid'      => array( 'Siatka', 'Równe kwadraty, zdjęcia przycięte.' ),
);
?>
<p class="fp-intro">Domyślny wygląd portfolio. Jeśli na stronie jest widżet <b>Focus Portfolio</b> w Elementorze, jego ustawienia (z zakładek Treść, Układ i Styl) mają pierwszeństwo.</p>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="fp_save_view">
	<?php wp_nonce_field( 'fp_save_view' ); ?>

	<?php FP_Admin::card( 'variant', 'Wariant i układ galerii' ); ?>
		<h3>Wariant</h3>
		<div class="fp-choices">
			<?php foreach ( $variants as $k => $d ) : ?>
			<label class="fp-choice">
				<input type="radio" name="fv[variant]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $k, $v['variant'] ); ?>>
				<span class="fp-swatch fp-swatch-<?php echo esc_attr( $k ); ?>" aria-hidden="true"><i></i><i></i><i></i></span>
				<b><?php echo esc_html( $d[0] ); ?></b><span><?php echo esc_html( $d[1] ); ?></span>
			</label>
			<?php endforeach; ?>
		</div>
		<h3>Układ galerii</h3>
		<div class="fp-choices">
			<?php foreach ( $galleries as $k => $d ) : ?>
			<label class="fp-choice">
				<input type="radio" name="fv[gallery]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $k, $v['gallery'] ); ?>>
				<span class="fp-swatch fp-gal-<?php echo esc_attr( $k ); ?>" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
				<b><?php echo esc_html( $d[0] ); ?></b><span><?php echo esc_html( $d[1] ); ?></span>
			</label>
			<?php endforeach; ?>
		</div>
	<?php FP_Admin::card_end(); ?>

	<?php FP_Admin::card( 'columns', 'Kolumny' ); ?>
		<div class="fp-cols fp-cols-tight">
			<div class="fp-col">
				<h3>Galeria zdjęć</h3>
				<?php $mode( 'cols_mode' ); ?>
				<div class="fp-grid4" data-fp-show="cols_mode=fixed">
					<?php $num( 'columns', 'Komputer', 1, 8 ); ?>
					<?php $num( 'columns_t', 'Tablet (do 1024 px)', 1, 6 ); ?>
					<?php $num( 'columns_m', 'Telefon (do 767 px)', 1, 4 ); ?>
				</div>
				<div class="fp-grid4" data-fp-show="cols_mode=auto">
					<?php $num( 'cols_min', 'Najmniej kolumn', 1, 8 ); ?>
					<?php $num( 'cols_max', 'Najwięcej kolumn', 1, 8 ); ?>
					<?php $num( 'tile_min', 'Min. szerokość zdjęcia (px)', 120, 600, 'kolumn przybywa, gdy się mieszczą' ); ?>
				</div>
				<label class="fp-check"><input type="checkbox" name="fv[even_cols]" value="1" <?php checked( $v['even_cols'] ); ?>> Wyrównaj dół galerii (masonry): kolumny kończą się na jednej wysokości, zdjęcia przycięte najwyżej o 20%</label>
			</div>
			<div class="fp-col">
				<h3>Karty wydarzeń</h3>
				<?php $mode( 'cards_mode' ); ?>
				<div class="fp-grid4" data-fp-show="cards_mode=fixed">
					<?php $num( 'ccols', 'Komputer', 1, 6 ); ?>
					<?php $num( 'ccols_t', 'Tablet (do 1024 px)', 1, 5 ); ?>
					<?php $num( 'ccols_m', 'Telefon (do 767 px)', 1, 3 ); ?>
				</div>
				<div class="fp-grid4" data-fp-show="cards_mode=auto">
					<?php $num( 'cards_min', 'Najmniej kolumn', 1, 6 ); ?>
					<?php $num( 'cards_max', 'Najwięcej kolumn', 1, 6 ); ?>
					<?php $num( 'card_min', 'Min. szerokość karty (px)', 140, 420 ); ?>
				</div>
				<p class="description">Strona główna z kategoriami układa się sama (karty min. 260 px).</p>
			</div>
		</div>
	<?php FP_Admin::card_end(); ?>

	<?php FP_Admin::card( 'layout', 'Układ' ); ?>
		<div class="fp-grid4">
			<div class="fp-field"><label for="fv-gap">Odstęp między zdjęciami (px)</label><input type="number" id="fv-gap" name="fv[gap]" min="0" max="40" value="<?php echo (int) $v['gap']; ?>" class="small-text"></div>
			<div class="fp-field"><label for="fv-maxw">Szerokość treści (px)</label><input type="number" id="fv-maxw" name="fv[max_width]" min="0" max="2400" step="10" value="<?php echo (int) $v['max_width']; ?>" class="small-text"><span class="description">0 = cała szerokość</span></div>
			<div class="fp-field"><label for="fv-pad">Margines boczny (px)</label><input type="number" id="fv-pad" name="fv[side_pad]" min="0" max="120" value="<?php echo (int) $v['side_pad']; ?>" class="small-text"></div>
			<div class="fp-field"><label for="fv-rh">Wysokość rzędu (px)</label><input type="number" id="fv-rh" name="fv[row_height]" min="120" max="480" value="<?php echo (int) $v['row_height']; ?>" class="small-text"><span class="description">dla „Wyrównanych rzędów”</span></div>
			<div class="fp-field"><label for="fv-fit">Zdjęcia na okładkach</label>
				<select id="fv-fit" name="fv[cover_fit]"><option value="cover" <?php selected( 'cover', $v['cover_fit'] ); ?>>Wypełnij pole (przytnij)</option><option value="contain" <?php selected( 'contain', $v['cover_fit'] ); ?>>Całe zdjęcie (z tłem)</option></select></div>
			<div class="fp-field"><label for="fv-ratio">Proporcje okładek</label>
				<select id="fv-ratio" name="fv[card_ratio]"><?php foreach ( array( '3/2' => '3:2', '4/3' => '4:3', '1/1' => '1:1', '16/9' => '16:9', '4/5' => '4:5 (pion)' ) as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, $v['card_ratio'] ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></div>
			<div class="fp-field"><label for="fv-cc">Okładek na karcie wydarzenia</label>
				<select id="fv-cc" name="fv[cover_count]"><?php foreach ( array( '1' => '1 zdjęcie', '3' => '3 (mozaika)', '5' => '5 (mozaika)' ) as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, $v['cover_count'] ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
				<span class="description">Brakujące uzupełnia automat.</span></div>
			<div class="fp-field"><label for="fv-sort">Kolejność wydarzeń</label>
				<select id="fv-sort" name="fv[sort]"><option value="newest" <?php selected( 'newest', $v['sort'] ); ?>>Najnowsze najpierw</option><option value="oldest" <?php selected( 'oldest', $v['sort'] ); ?>>Najstarsze najpierw</option></select></div>
		</div>
	<?php FP_Admin::card_end(); ?>

	<?php FP_Admin::card( 'browse', 'Przeglądanie zdjęć' ); ?>
		<div class="fp-grid4">
			<div class="fp-field"><label for="fv-pp">Zdjęć na stronę (domyślnie)</label>
				<select id="fv-pp" name="fv[per_page]"><?php foreach ( array( '20', '50', '100' ) as $k ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, $v['per_page'] ); ?>><?php echo esc_html( $k ); ?></option><?php endforeach; ?></select></div>
			<div class="fp-field"><label for="fv-ps">Sortowanie „Wszystkie zdjęcia”</label>
				<select id="fv-ps" name="fv[photo_sort]"><?php foreach ( FP_Frontend::photo_sorts() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, $v['photo_sort'] ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></div>
		</div>
		<?php
		$checks = array(
			'show_per_page'      => 'Odwiedzający może zmienić liczbę zdjęć na stronie (20 / 50 / 100)',
			'show_sort'          => 'Odwiedzający może zmienić sortowanie (najlepsze, najnowsze, autor…)',
			'show_author_filter' => 'Filtr autorów (kilku naraz, z wyszukiwarką)',
			'show_jumps'         => 'Filtr miesięcy i lat (kilku naraz)',
			'show_all_view'      => 'Przełącznik „Wszystkie zdjęcia” w kategoriach',
		);
		foreach ( $checks as $k => $l ) :
			?>
			<label class="fp-check"><input type="checkbox" name="fv[<?php echo esc_attr( $k ); ?>]" value="1" <?php checked( $v[ $k ] ); ?>> <?php echo esc_html( $l ); ?></label>
		<?php endforeach; ?>
		<p class="description">Które filtry działają w danej kategorii, ustawisz w <a href="<?php echo esc_url( FP_Admin::url( 'structure' ) ); ?>">Strukturze</a>.</p>
	<?php FP_Admin::card_end(); ?>

	<div class="fp-cols">
	<div class="fp-col">
	<?php FP_Admin::card( 'content', 'Treść' ); ?>
		<div class="fp-field"><label for="fv-title">Tytuł strony głównej portfolio</label><input type="text" id="fv-title" name="fv[title]" value="<?php echo esc_attr( $v['title'] ); ?>" class="regular-text"></div>
		<div class="fp-field"><label for="fv-intro">Wstęp</label><textarea id="fv-intro" name="fv[intro]" rows="3" class="large-text"><?php echo esc_textarea( $v['intro'] ); ?></textarea></div>
		<p class="description">Tytuły i opisy kategorii oraz wydarzeń zmienisz w Eksploratorze (panel „Właściwości”).</p>
		<?php
		$checks = array(
			'show_header'  => 'Nagłówek z tytułem i wstępem (wyłącz, jeśli motyw już go pokazuje)',
			'show_stats'   => 'Liczby zdjęć i galerii pod nagłówkiem',
			'show_counts'  => 'Liczniki zdjęć na kartach',
			'show_authors' => 'Autorzy zdjęć',
			'show_related' => 'Link „Zobacz też” do wydarzenia o tej samej nazwie w innej kategorii',
			'lightbox'     => 'Powiększanie zdjęć w lightboxie',
			'lb_download'  => 'Przycisk „Pobierz oryginał” w lightboxie',
		);
		foreach ( $checks as $k => $l ) :
			?>
			<label class="fp-check"><input type="checkbox" name="fv[<?php echo esc_attr( $k ); ?>]" value="1" <?php checked( $v[ $k ] ); ?>> <?php echo esc_html( $l ); ?></label>
		<?php endforeach; ?>
	<?php FP_Admin::card_end(); ?>
	</div>
	<div class="fp-col">
	<?php FP_Admin::card( 'colors', 'Kolory i napisy' ); ?>
		<p class="description">Tekst, tło i czcionki są brane ze strony (motywu), więc portfolio pasuje do jasnego i ciemnego wyglądu. Wpisz kolor tylko, jeśli chcesz go nadpisać; puste pole = z motywu.</p>
		<div class="fp-grid2">
			<?php foreach ( array( 'accent' => 'Akcent (przyciski, aktywne filtry)', 'text' => 'Tekst', 'muted' => 'Tekst pomocniczy', 'line' => 'Linie', 'soft' => 'Tło pustych pól' ) as $k => $l ) : ?>
			<div class="fp-field"><label for="fv-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></label><input type="text" id="fv-<?php echo esc_attr( $k ); ?>" name="fv[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $v[ $k ] ); ?>" class="fp-color" data-default-color="<?php echo esc_attr( FP_Settings::view_defaults()[ $k ] ); ?>" placeholder="z motywu"></div>
			<?php endforeach; ?>
		</div>
		<div class="fp-field"><label for="fv-hf">Czcionka nagłówków</label><input type="text" id="fv-hf" name="fv[heading_font]" value="<?php echo esc_attr( $v['heading_font'] ); ?>" placeholder="z motywu, np. Poppins" class="regular-text"></div>
		<div class="fp-grid2">
			<?php foreach ( array( 'label_events' => 'Etykieta „Wydarzenia”', 'label_all' => 'Etykieta „Wszystkie zdjęcia”', 'label_photos' => 'Etykieta autorów', 'label_other' => 'Grupa zdjęć bez nazwy' ) as $k => $l ) : ?>
			<div class="fp-field"><label for="fv-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></label><input type="text" id="fv-<?php echo esc_attr( $k ); ?>" name="fv[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $v[ $k ] ); ?>"></div>
			<?php endforeach; ?>
		</div>
	<?php FP_Admin::card_end(); ?>
	</div>
	</div>

	<p><button class="button button-primary button-hero">Zapisz wygląd</button></p>
</form>

<?php
// domyślne ustawienia widżetu „Focus Portfolio – Zdjęcia”, shortcode [focus_photos] i bloku (puste pola w widżecie = te wartości)
$ph  = FP_Photos::stored();
$phc = FP_Photos::choices();
$phl = FP_Photos::labels();
$sel = function ( $k ) use ( $ph, $phc, $phl ) {
	echo '<div class="fp-field"><label for="fpp-' . esc_attr( $k ) . '">' . esc_html( $phl[ $k ] ) . '</label><select id="fpp-' . esc_attr( $k ) . '" name="fpp[' . esc_attr( $k ) . ']">';
	foreach ( $phc[ $k ] as $val => $l ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $val, $ph[ $k ], false ), esc_html( $l ) );
	}
	echo '</select></div>';
};
$int = function ( $k ) use ( $ph, $phl ) {
	printf( '<div class="fp-field"><label for="fpp-%1$s">%2$s</label><input type="number" id="fpp-%1$s" name="fpp[%1$s]" value="%3$d" min="%4$d" max="%5$d" class="small-text"></div>', esc_attr( $k ), esc_html( $phl[ $k ] ), (int) $ph[ $k ], FP_Photos::INTS[ $k ][0], FP_Photos::INTS[ $k ][1] );
};
$chk = function ( $k ) use ( $ph, $phl ) {
	printf( '<label class="fp-check"><input type="checkbox" name="fpp[%s]" value="1"%s> %s</label>', esc_attr( $k ), checked( $ph[ $k ], 1, false ), esc_html( $phl[ $k ] ) );
};
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="fp_save_photos">
	<?php wp_nonce_field( 'fp_save_photos' ); ?>
	<?php FP_Admin::card( 'photos', 'Zdjęcia na innych stronach (widżet „Focus Portfolio – Zdjęcia”)' ); ?>
		<p class="description">Domyślne ustawienia widżetu Elementora <b>Focus Portfolio – Zdjęcia</b>, bloku o tej samej nazwie i shortcode <code>[focus_photos]</code>, np. kilka zdjęć nad blogiem na stronie głównej. Pole „Jak w ustawieniach” w widżecie bierze wartość stąd.
			Źródło (kategorie, katalogi, okres, wykluczenia) i konkretne zdjęcia wybierasz w samym widżecie.</p>
		<div class="fp-grid4">
			<?php $sel( 'mode' ); $sel( 'view' ); $sel( 'action' ); $sel( 'caption' ); $sel( 'refresh' ); $sel( 'filter' ); $sel( 'orient' ); ?>
		</div>
		<h3>Ile zdjęć: wiersze × zdjęcia w wierszu</h3>
		<table class="fp-devices">
			<thead><tr><th></th><th>Wiersze</th><th></th><th>Zdjęć w wierszu</th><th>Razem</th></tr></thead>
			<tbody>
			<?php
			foreach ( array( '' => 'Komputer', '_t' => 'Tablet (do 1024 px)', '_m' => 'Telefon (do 767 px)' ) as $d => $l ) :
				$rk = 'rows' . $d;
				$pk = 'per_row' . $d;
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $l ); ?></th>
					<td><input type="number" name="fpp[<?php echo esc_attr( $rk ); ?>]" value="<?php echo (int) $ph[ $rk ]; ?>" min="<?php echo (int) FP_Photos::INTS[ $rk ][0]; ?>" max="<?php echo (int) FP_Photos::INTS[ $rk ][1]; ?>" class="small-text" aria-label="<?php echo esc_attr( $l . ': wiersze' ); ?>"></td>
					<td aria-hidden="true">×</td>
					<td><input type="number" name="fpp[<?php echo esc_attr( $pk ); ?>]" value="<?php echo (int) $ph[ $pk ]; ?>" min="<?php echo (int) FP_Photos::INTS[ $pk ][0]; ?>" max="<?php echo (int) FP_Photos::INTS[ $pk ][1]; ?>" class="small-text" aria-label="<?php echo esc_attr( $l . ': zdjęć w wierszu' ); ?>"></td>
					<td class="fp-devices-sum"><?php echo (int) ( $ph[ $rk ] * $ph[ $pk ] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">Najwyżej 12 na każdym urządzeniu (tyle zdjęć trzyma cache); więcej wierszy zostanie przycięte przy zapisie.</p>
		<h3>Wygląd</h3>
		<div class="fp-grid4">
			<?php $int( 'gap' ); $int( 'gap_m' ); $int( 'chaos' ); $int( 'radius' ); $int( 'zoom_scale' ); $int( 'strip_h' ); ?>
		</div>
		<div class="fp-checks">
			<?php $chk( 'bag' ); $chk( 'shuffle' ); $chk( 'button' ); $chk( 'zoom' ); $chk( 'stars' ); ?>
		</div>
		<p><button class="button button-primary">Zapisz ustawienia zdjęć</button></p>
	<?php FP_Admin::card_end(); ?>
</form>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fp-inline-form">
	<input type="hidden" name="action" value="fp_clear_photos">
	<?php wp_nonce_field( 'fp_clear_photos' ); ?>
	<p><button class="button">Wyczyść cache zdjęć (nowe losowanie)</button>
		<span class="description">Usuwa zapamiętane losowania i „worki” wszystkich widżetów oraz czyści cache stron LiteSpeed. Nowy skan katalogów robi to sam.</span></p>
</form>
