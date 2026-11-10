<?php
/**
 * Pulpit: stan portfolio, problemy do uporządkowania, szybki import, miniatury.
 *
 * @var FP_Admin $this (kontekst statyczny)
 */

defined( 'ABSPATH' ) || exit;

$path     = FP_Settings::source_path();
$m        = FP_Scanner::manifest();
$st       = isset( $m['stats'] ) ? $m['stats'] : array();
$thumbs   = FP_Thumbs::status();
$next     = wp_next_scheduled( 'fp_cron' );
$problems = isset( $m['problems'] ) ? (array) $m['problems'] : array();
$types    = array(
	'no_year' => 'Miesiące lub edycje bez roku',
	'no_date' => 'Wydarzenia bez daty',
	'loose'   => 'Zdjęcia luzem bez nazwy wydarzenia',
	'empty'   => 'Puste katalogi',
);
$by_type  = array();
foreach ( $problems as $pr ) {
	$by_type[ $pr['type'] ] = isset( $by_type[ $pr['type'] ] ) ? $by_type[ $pr['type'] ] + 1 : 1;
}
$can_edit = FP_Caps::can( FP_Caps::EDIT );
?>
<p class="fp-intro">Portfolio czyta zdjęcia prosto z katalogu
	<code><?php echo esc_html( 'wp-content/' . trim( (string) FP_Settings::get( 'source' ), '/' ) . '/' ); ?></code>.
	Nowe zdjęcia wgrane przez FTP lub w Eksploratorze pojawiają się na stronie same (sprawdzanie co 15 minut albo od razu po „Skanuj teraz”).</p>
<?php FP_Admin::readonly_note(); ?>

<div class="fp-cols">
<div class="fp-col">

<?php FP_Admin::card( 'status', 'Stan portfolio' ); ?>
	<?php if ( ! $path ) : ?>
		<p class="fp-warn">Katalog źródłowy nie istnieje. Ustaw go w <a href="<?php echo esc_url( FP_Admin::url( 'settings' ) ); ?>">Ustawieniach</a>.</p>
	<?php elseif ( ! empty( $m['generated'] ) ) : ?>
		<dl class="fp-kv">
			<div><dt>Zdjęcia</dt><dd><?php echo (int) $st['photos']; ?></dd></div>
			<div><dt>Galerie</dt><dd><?php echo (int) $st['groups']; ?></dd></div>
			<div><dt>Kategorie</dt><dd><?php echo count( $m['cats'] ); ?></dd></div>
			<div><dt>Oryginały</dt><dd><?php echo esc_html( size_format( $st['bytes'] ) ); ?></dd></div>
		</dl>
		<p>Ostatni skan: <strong><?php echo esc_html( wp_date( 'j.m.Y H:i', $m['generated'] ) ); ?></strong>.
			<?php if ( $next ) : ?>Następne sprawdzenie: <?php echo esc_html( wp_date( 'H:i', $next ) ); ?>.<?php endif; ?></p>
	<?php else : ?>
		<p>Katalog nie był jeszcze skanowany.</p>
	<?php endif; ?>
	<div class="fp-progress" id="fp-thumb-progress">
		<div class="fp-progress-bar"><span style="width:<?php echo $thumbs['total'] ? (int) round( 100 * $thumbs['done'] / $thumbs['total'] ) : 0; ?>%"></span></div>
		<span class="fp-progress-label">Miniatury: <b><?php echo (int) $thumbs['done']; ?></b> z <?php echo (int) $thumbs['total']; ?></span>
	</div>
	<?php if ( $can_edit ) : ?>
	<p class="fp-actions">
		<button type="button" class="button" id="fp-scan">Skanuj teraz</button>
		<button type="button" class="button button-primary" id="fp-thumbs">Wygeneruj brakujące miniatury</button>
		<span class="spinner" id="fp-spin"></span>
	</p>
	<?php endif; ?>
	<p class="fp-log" id="fp-log" aria-live="polite"></p>
<?php FP_Admin::card_end(); ?>

<?php FP_Admin::card( 'inbox', 'Do uporządkowania' . ( $problems ? ' (' . count( $problems ) . ')' : '' ) ); ?>
	<?php if ( ! $problems ) : ?>
		<p class="fp-ok">Wszystkie katalogi mają poprawne nazwy i daty.</p>
	<?php else : ?>
		<ul class="fp-inbox-sum">
			<?php foreach ( $by_type as $t => $n ) : ?>
				<li><b><?php echo (int) $n; ?></b> · <?php echo esc_html( isset( $types[ $t ] ) ? $types[ $t ] : $t ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p><a class="button button-primary" href="<?php echo esc_url( FP_Admin::url( 'explorer', array( 'view' => 'inbox' ) ) ); ?>">Uporządkuj w Eksploratorze</a></p>
		<p class="description">Wtyczka niczego sama nie przenosi. W Eksploratorze przy każdym problemie wybierasz: wpisz datę, dzisiejsza data, Archiwum albo przenieś do katalogu.</p>
	<?php endif; ?>
<?php FP_Admin::card_end(); ?>

<?php FP_Admin::card( 'issues', 'Co wtyczka poprawiła sama' . ( ! empty( $m['issues'] ) ? ' (' . count( $m['issues'] ) . ')' : '' ), false ); ?>
	<?php if ( empty( $m['issues'] ) ) : ?>
		<p>Nic do zgłoszenia.</p>
	<?php else : ?>
		<ul class="fp-issues-list"><?php foreach ( $m['issues'] as $i ) : ?><li><?php echo esc_html( $i ); ?></li><?php endforeach; ?></ul>
	<?php endif; ?>
<?php FP_Admin::card_end(); ?>

</div>
<div class="fp-col">

<?php if ( $can_edit && $path ) : ?>
<?php FP_Admin::card( 'import', 'Szybki import zdjęć' ); ?>
	<p class="description">Więcej możliwości (przeciąganie do katalogu, przenoszenie, zmiana nazw) masz w <a href="<?php echo esc_url( FP_Admin::url( 'explorer' ) ); ?>">Eksploratorze</a>.</p>
	<form id="fp-import" class="fp-import">
		<div class="fp-field">
			<label for="fp-imp-cat">Kategoria</label>
			<select id="fp-imp-cat"></select>
			<input type="text" id="fp-imp-cat-new" placeholder="Nazwa nowej kategorii, np. SPORT" hidden>
		</div>
		<div class="fp-field">
			<label for="fp-imp-sub">Podkatalog <span class="fp-opt">miesiąc albo edycja, opcjonalnie</span></label>
			<input type="text" id="fp-imp-sub" list="fp-imp-sub-list" placeholder="np. 2026-11 Listopad">
			<datalist id="fp-imp-sub-list"></datalist>
		</div>
		<div class="fp-field">
			<label for="fp-imp-ev">Wydarzenie <span class="fp-opt">nowe albo istniejące, opcjonalnie</span></label>
			<input type="text" id="fp-imp-ev" list="fp-imp-ev-list" placeholder="np. Bal Tysiąca Świateł">
			<datalist id="fp-imp-ev-list"></datalist>
		</div>
		<p class="fp-target">Zdjęcia trafią do: <code id="fp-imp-target"></code></p>
		<label class="fp-drop" id="fp-drop" for="fp-imp-files">
			<input type="file" id="fp-imp-files" multiple accept=".jpg,.jpeg,.png,.webp,.zip,image/jpeg,image/png,image/webp,application/zip">
			<strong>Przeciągnij zdjęcia albo plik ZIP</strong>
			<span>lub kliknij, żeby wybrać. JPG, PNG, WEBP, ZIP · maks. <?php echo esc_html( size_format( wp_max_upload_size() ) ); ?> na plik. Katalogi w ZIP-ie stają się wydarzeniami.</span>
		</label>
		<ul class="fp-queue" id="fp-queue"></ul>
		<p><button type="submit" class="button button-primary" id="fp-imp-go" disabled>Wgraj</button>
			<span class="fp-hint">Po wgraniu wtyczka przeskanuje katalog i zrobi miniatury.</span></p>
	</form>
<?php FP_Admin::card_end(); ?>
<?php endif; ?>

<?php FP_Admin::card( 'links', 'Na skróty' ); ?>
	<ul class="fp-links">
		<li><a href="<?php echo esc_url( FP_Admin::url( 'explorer' ) ); ?>">Eksplorator</a>: katalogi, zdjęcia, okładki, kolejność, gwiazdki</li>
		<li><a href="<?php echo esc_url( FP_Admin::url( 'people' ) ); ?>">Fotografowie</a>: rejestr autorów, Instagram, rozpoznawanie podpisów (OCR)</li>
		<?php if ( FP_Caps::can( FP_Caps::ADMIN ) ) : ?>
		<li><a href="<?php echo esc_url( FP_Admin::url( 'structure' ) ); ?>">Struktura</a>: jak nazywać katalogi, filtry dla odwiedzających</li>
		<li><a href="<?php echo esc_url( FP_Admin::url( 'view' ) ); ?>">Wygląd</a>: warianty, kolumny, kolory, stronicowanie</li>
		<?php endif; ?>
		<li><a href="<?php echo esc_url( FP_Admin::url( 'help' ) ); ?>">Instrukcja</a> (PDF do pobrania)</li>
	</ul>
<?php FP_Admin::card_end(); ?>

</div>
</div>
