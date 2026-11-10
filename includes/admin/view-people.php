<?php
/**
 * Fotografowie: rejestr osób (IG/FB/strona, konto WordPress, aliasy) i rozpoznawanie podpisów (OCR).
 */

defined( 'ABSPATH' ) || exit;

$people   = FP_People::all();
$counts   = FP_People::from_photos();
$known    = array();
foreach ( $people as $p ) {
	$known[ $p['name'] ] = 1;
}
$unknown  = array_diff_key( $counts, $known );
$can_edit = FP_Caps::can( FP_Caps::EDIT );
$ro       = disabled( ! $can_edit, true, false );
$users    = get_users( array( 'fields' => array( 'ID', 'display_name', 'user_login' ), 'orderby' => 'display_name', 'number' => 2000 ) );
$cats     = FP_Scanner::categories( true );
$ulabel   = array();
foreach ( $users as $u ) {
	$ulabel[ (int) $u->ID ] = $u->display_name . ( $u->display_name !== $u->user_login ? ' (' . $u->user_login . ')' : '' );
}
// konto na stronie: pole z wyszukiwaniem (lista podpowiedzi) + ukryte id
$user_pick = function ( $i, $uid ) use ( $ulabel, $ro ) {
	$uid = isset( $ulabel[ (int) $uid ] ) ? (int) $uid : 0;
	return sprintf(
		'<input type="hidden" name="people[%1$d][user_id]" value="%2$d" class="fp-user-id"><input type="search" class="fp-user-pick" list="fp-users" value="%3$s" placeholder="Szukaj konta…" aria-label="Konto na stronie"%4$s>',
		(int) $i, $uid, esc_attr( $uid ? $ulabel[ $uid ] : '' ), $ro
	);
};
// Instagram, Facebook i strona w jednej kolumnie, z krótkimi etykietami
$profiles = function ( $i, $p ) use ( $ro ) {
	$h = '';
	foreach ( array( 'ig' => array( 'IG', 'text', 'nazwa konta' ), 'fb' => array( 'FB', 'url', 'https://facebook.com/…' ), 'www' => array( 'WWW', 'url', 'https://…' ) ) as $k => $f ) {
		$h .= sprintf( '<label class="fp-pf"><span>%1$s</span><input type="%2$s" name="people[%3$d][%4$s]" value="%5$s" placeholder="%6$s"%7$s></label>', $f[0], $f[1], (int) $i, $k, esc_attr( isset( $p[ $k ] ) ? $p[ $k ] : '' ), esc_attr( $f[2] ), $ro );
	}
	return $h;
};
$total = count( $people ) + count( $unknown );
?>
<p class="fp-intro">Wszyscy podpisani pod zdjęciami są tu od razu: nic nie trzeba dodawać. Uzupełnij tylko to, co potrzebne: profil (Instagram, Facebook, strona) zamienia podpis „fot.” w link,
	aliasy łączą różne zapisy tej samej osoby (np. „@nick”, literówki z OCR), a poprawione imię i nazwisko zastępuje stary zapis na stronie.</p>
<?php FP_Admin::readonly_note(); ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fp-people-form">
	<input type="hidden" name="action" value="fp_save_people">
	<?php wp_nonce_field( 'fp_save_people' ); ?>
	<?php FP_Admin::card( 'people', 'Fotografowie (' . $total . ')' ); ?>
		<p class="fp-p-sum"><b><?php echo (int) $total; ?></b> osób · <b><?php echo (int) count( $people ); ?></b> z uzupełnionymi danymi (profil, konto, aliasy)</p>
		<div class="fp-ptools">
			<input type="search" id="fp-p-q" placeholder="Szukaj osoby, aliasu, konta…" aria-label="Szukaj osoby">
			<select id="fp-p-filter" aria-label="Pokaż">
				<option value="">Wszyscy (<?php echo (int) $total; ?>)</option>
				<option value="saved">Z danymi (<?php echo (int) count( $people ); ?>)</option>
				<option value="new">Tylko podpis na zdjęciach (<?php echo (int) count( $unknown ); ?>)</option>
				<option value="nouser">Bez konta na stronie</option>
				<option value="noprofile">Bez profilu (IG / FB / WWW)</option>
				<option value="hidden">Ukryci w filtrze autorów</option>
			</select>
			<select id="fp-p-sort" aria-label="Sortuj">
				<option value="n-desc">Najwięcej zdjęć</option>
				<option value="n-asc">Najmniej zdjęć</option>
				<option value="last">Nazwisko A–Ż</option>
				<option value="first">Imię A–Ż</option>
			</select>
			<span class="fp-p-shown" aria-live="polite"></span>
			<?php if ( $can_edit ) : ?>
				<span class="fp-ptools-r">
					<button type="button" class="button" id="fp-person-add" title="Osoba, której jeszcze nie ma na zdjęciach">Dodaj osobę</button>
					<button type="submit" name="match_users" value="1" class="button" title="Łączy osoby bez konta z kontami na stronie po imieniu i nazwisku (tolerancja 2 znaki)">Dopasuj konta</button>
					<button class="button button-primary">Zapisz</button>
				</span>
			<?php endif; ?>
		</div>
		<datalist id="fp-users"><?php foreach ( $ulabel as $uid => $l ) : ?><option value="<?php echo esc_attr( $l ); ?>" data-id="<?php echo (int) $uid; ?>"></option><?php endforeach; ?></datalist>
		<div class="fp-table-wrap">
		<table class="widefat fp-people" id="fp-people">
			<thead><tr><th class="fp-p-name">Imię i nazwisko</th><th>Aliasy <span class="fp-opt">po przecinku</span></th><th>Profile</th><th>Konto na stronie</th><th class="num">Zdjęć</th><th title="Ukryj w filtrze autorów">Ukryj</th><th></th></tr></thead>
			<tbody>
			<?php
			$i   = 0;
			$row = function ( $i, $p, $n, $is_new ) use ( $profiles, $user_pick, $can_edit, $ro ) {
				$search = mb_strtolower( $p['name'] . ' ' . implode( ' ', $p['aliases'] ) . ' ' . $p['ig'] );
				printf(
					'<tr data-state="%s" data-n="%d" data-q="%s" data-name="%s">',
					$is_new ? 'new' : 'saved', (int) $n, esc_attr( $search ), esc_attr( $p['name'] )
				);
				// orig: zapis sprzed zmiany (poprawione imię → stary zapis zostaje aliasem); saved: czy osoba ma już zapisane dane
				printf(
					'<td class="fp-p-name"><input type="hidden" name="people[%1$d][orig]" value="%2$s"><input type="hidden" name="people[%1$d][saved]" value="%3$d"><input type="text" name="people[%1$d][name]" value="%2$s" aria-label="Imię i nazwisko"%4$s>',
					(int) $i, esc_attr( $p['name'] ), $is_new ? 0 : 1, $ro
				);
				echo $is_new ? '' : '<span class="fp-p-badge">dane zapisane</span>';
				echo '</td>';
				printf( '<td><input type="text" name="people[%d][aliases]" value="%s" class="fp-wide" placeholder="%s"%s></td>', (int) $i, esc_attr( implode( ', ', $p['aliases'] ) ), $is_new ? 'np. inne zapisy tej osoby' : '', $ro );
				echo '<td class="fp-p-prof">' . $profiles( $i, $p ) . '</td>'; // phpcs:ignore
				echo '<td>' . $user_pick( $i, $p['user_id'] ) . '</td>'; // phpcs:ignore
				echo '<td class="num">' . (int) $n . '</td>';
				printf( '<td><input type="checkbox" name="people[%d][hidden]" value="1"%s aria-label="Ukryj w filtrze autorów"%s></td>', (int) $i, checked( $p['hidden'], true, false ), $ro );
				echo '<td class="fp-p-act">';
				if ( $can_edit && ( ! $is_new || $n ) ) {
					// usuń: dane osoby (podpisy zostają) albo także podpis autora na jej zdjęciach
					printf( '<input type="hidden" name="people[%d][del]" value="0" class="fp-p-del"><button type="button" class="button-link fp-p-delbtn">Usuń</button>', (int) $i );
					echo '<select class="fp-p-delmode" hidden aria-label="Co usunąć">';
					if ( ! $is_new ) {
						echo '<option value="1">tylko dane osoby (podpisy zostają)</option>';
					}
					if ( $n ) {
						printf( '<option value="2">%s ze zdjęć (%d)</option>', $is_new ? 'podpis autora' : 'dane i podpis autora', (int) $n );
					}
					echo '</select>';
				}
				echo '</td></tr>';
			};
			foreach ( $people as $p ) {
				$row( $i++, $p, isset( $counts[ $p['name'] ] ) ? $counts[ $p['name'] ] : 0, false );
			}
			foreach ( $unknown as $name => $n ) {
				$row( $i++, array( 'name' => $name, 'aliases' => array(), 'ig' => preg_match( '/@([\w.]+)/', $name, $mm ) ? $mm[1] : '', 'fb' => '', 'www' => '', 'user_id' => 0, 'hidden' => 0 ), $n, true );
			}
			?>
			</tbody>
		</table>
		</div>
		<p class="fp-p-empty" hidden>Nikt nie pasuje do wyszukiwania.</p>
		<?php if ( $can_edit ) : ?>
			<p class="fp-actions"><button class="button button-primary">Zapisz</button></p>
			<p class="description">„Usuń” pyta, co usunąć: same dane osoby (profil, konto, aliasy; podpisy na zdjęciach zostają) albo także podpis autora na jej zdjęciach.
				Usunięty podpis przywrócisz w Eksploratorze, wpisując autora zdjęcia. Żeby scalić dwa zapisy jednej osoby, wpisz drugi w jej aliasach (albo popraw imię i nazwisko: stary zapis sam stanie się aliasem).
				Konto na stronie wybierasz, wpisując fragment imienia albo loginu.</p>
		<?php endif; ?>
	<?php FP_Admin::card_end(); ?>
</form>

<?php if ( $can_edit ) : ?>
<?php FP_Admin::card( 'ocr', 'Rozpoznaj autorów ze stopki (OCR)' ); ?>
	<p class="description">Odczytuje podpis wtopiony w zdjęcie, np. „LOGO | IMIĘ NAZWISKO” (słowa z logo pominie, jeśli wpiszesz je w Ustawienia → Autorzy i podpisy). Działa w tej przeglądarce: zdjęcia nie są nigdzie wysyłane, pobierany jest tylko słownik języka polskiego (ok. 3 MB). Wyniki poprawiasz przed zapisem.</p>
	<div class="fp-ocr-opts">
		<div class="fp-field">
			<label>Katalogi</label>
			<div class="fp-ocr-dirs" id="fp-ocr-dirs">
				<label class="fp-check"><input type="checkbox" value="" class="fp-ocr-dir-all" checked> <b>Wszystkie</b></label>
				<?php foreach ( $cats as $c ) : ?>
					<details><summary><label><input type="checkbox" value="<?php echo esc_attr( $c['dir'] ); ?>" class="fp-ocr-dir"> <?php echo esc_html( $c['title'] ); ?></label> <span class="fp-opt"><?php echo (int) $c['count']; ?></span></summary>
					<?php foreach ( $c['groups'] as $g ) : ?>
						<label class="fp-check fp-ocr-sub"><input type="checkbox" value="<?php echo esc_attr( $g['dir'] ? $g['dir'] : $g['mdir'] ); ?>" class="fp-ocr-dir"> <?php echo esc_html( FP_Frontend::group_title( $g, $c ) . ( $g['period'] ? ' · ' . $g['period'] : '' ) ); ?> <span class="fp-opt"><?php echo count( $g['photos'] ); ?></span></label>
					<?php endforeach; ?>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="fp-field">
			<label>Gdzie szukać podpisu</label>
			<div class="fp-grid2">
				<label>Szerokość pasa <input type="number" id="fp-ocr-w" value="50" min="20" max="100" class="small-text"> %</label>
				<label>Wysokość pasa <input type="number" id="fp-ocr-h" value="15" min="5" max="40" class="small-text"> %</label>
			</div>
			<select id="fp-ocr-align" aria-label="Położenie podpisu"><option value="left">Lewy dolny róg</option><option value="right">Prawy dolny róg</option><option value="full">Cała szerokość u dołu</option></select>
			<p class="description">Domyślnie: lewa połowa, dolne 15% zdjęcia. Mniejszy wycinek = mniej pomyłek.</p>
			<select id="fp-ocr-scope" aria-label="Zakres"><option value="missing">Tylko zdjęcia bez autora</option><option value="all">Wszystkie zdjęcia</option></select>
		</div>
	</div>
	<p class="fp-actions">
		<button type="button" class="button button-primary" id="fp-ocr-run">Rozpoznaj autorów ze stopki</button>
		<button type="button" class="button" id="fp-ocr-stop" hidden>Zatrzymaj</button>
		<span class="fp-ocr-progress" id="fp-ocr-progress" aria-live="polite"></span>
	</p>
	<div class="fp-table-wrap"><table class="widefat striped fp-ocr-table" id="fp-ocr-table" hidden>
		<thead><tr><th><input type="checkbox" id="fp-ocr-all" checked aria-label="Zaznacz wszystkie"></th><th>Stopka</th><th>Rozpoznany autor</th><th>Zdjęcie</th></tr></thead>
		<tbody></tbody>
	</table></div>
	<p><button type="button" class="button button-primary" id="fp-ocr-save" hidden>Zapisz zaznaczonych autorów</button></p>
<?php FP_Admin::card_end(); ?>
<?php endif; ?>
