<?php
/**
 * „Focus Portfolio – Zdjęcia”: kilka do kilkunastu zdjęć z portfolio na dowolnej stronie (np. nad blogiem).
 * Widżet Elementora (FP_Photos_Widget), shortcode [focus_photos …] i blok „focus/photos” z tymi samymi ustawieniami.
 * Składany z modułów: źródło (FP_Photos_Source) → wybór i cache (FP_Photos_Picker) → widok (FP_Photos_Views)
 * → kliknięcie i podpis (tutaj, tile()). Domyślne ustawienia: Focus Portfolio → Wygląd → „Zdjęcia na innych stronach”;
 * puste pole w widżecie = wartość stamtąd.
 */

defined( 'ABSPATH' ) || exit;

class FP_Photos {

	const OPT = 'fp_photos';

	/** Licznik widżetów na stronie (unikalne id pojemnika). */
	private static $n = 0;

	public static function init() {
		add_shortcode( 'focus_photos', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 6 );
		add_action( 'elementor/widgets/register', function ( $manager ) {
			require_once FP_DIR . 'includes/class-fp-photos-widget.php';
			$manager->register( new FP_Photos_Widget() );
		} );
		add_action( 'admin_post_fp_save_photos', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_fp_clear_photos', array( __CLASS__, 'clear' ) );
	}

	/* ---------- ustawienia ---------- */

	public static function defaults() {
		return array(
			'mode'       => 'random',   // random | best | events | folder | picked
			'photos'     => array(),    // tryb picked: identyfikatory zdjęć w kolejności
			'source'     => 'all',      // all | cats | folders
			'cats'       => array(),    // katalogi kategorii
			'folders'    => array(),    // FP_Frontend::folder_id(), „KATEGORIA/*” = cała kategoria
			'from'       => '',         // okres od: RRRR albo RRRR-MM
			'to'         => '',         // okres do
			'stars'      => 0,          // tylko zdjęcia z gwiazdką
			'orient'     => 'any',      // any | landscape | portrait | square
			'ex_authors' => array(),    // wykluczeni autorzy (prywatność)
			'ex_events'  => array(),    // wykluczone wydarzenia
			'refresh'    => '6h',       // render | 1h | 6h | 24h | manual
			'bag'        => 1,          // worek: bez powtórek między losowaniami, po wyczerpaniu od nowa
			'shuffle'    => 0,          // tasowanie w przeglądarce (strona z cache, np. LiteSpeed, i tak się zmienia)
			'button'     => 0,          // przycisk „Losuj ponownie” (tasuje w przeglądarce)
			'action'     => 'photo',    // photo | lightbox | event | none
			'caption'    => 'event',    // none | event | event_author
			'view'       => 'rows',
			'rows'       => 2,
			'per_row'    => 5,
			'rows_t'     => 2,
			'per_row_t'  => 4,
			'rows_m'     => 3,
			'per_row_m'  => 2,
			'gap'        => 10,
			'gap_m'      => 8,
			'chaos'      => 50,         // 0–100 %: 50 % = wysokość wiersza ±6 %
			'radius'     => 10,
			'filter'     => 'none',     // none | grayscale | sepia | contrast | warm | cool
			'zoom'       => 1,
			'zoom_scale' => 106,        // % powiększenia po najechaniu
			'strip_h'    => 260,        // wysokość paska (widok „strip”)
		);
	}

	/** Pola wyboru: [klucz => [wartość => etykieta]]. */
	public static function choices() {
		return array(
			'mode'    => array(
				'random' => 'Losowo',
				'best'   => 'Wyróżnione: gwiazdki i okładki, losowo',
				'events' => 'Po jednym z ostatnich wydarzeń',
				'folder' => 'Po kolei, jak w katalogach',
				'picked' => 'Konkretne zdjęcia',
			),
			'source'  => array( 'all' => 'Cała galeria', 'cats' => 'Wybrane kategorie', 'folders' => 'Wybrane katalogi' ),
			'orient'  => array( 'any' => 'Dowolna', 'landscape' => 'Poziome', 'portrait' => 'Pionowe', 'square' => 'Kwadratowe' ),
			'refresh' => array( 'render' => 'Przy każdym wyświetleniu', '1h' => 'Co godzinę', '6h' => 'Co 6 godzin', '24h' => 'Raz na dobę', 'manual' => 'Ręcznie (przycisk „Wyczyść cache”)' ),
			'action'  => array( 'photo' => 'Fotorelacja i to zdjęcie', 'lightbox' => 'Powiększenie na miejscu (z linkiem do fotorelacji)', 'event' => 'Tylko fotorelacja', 'none' => 'Bez linku' ),
			'caption' => array( 'event' => 'Wydarzenie i okres', 'event_author' => 'Wydarzenie, okres i autor', 'none' => 'Bez podpisu' ),
			'filter'  => array( 'none' => 'Bez filtra (kolor)', 'grayscale' => 'Czarno-białe', 'sepia' => 'Sepia', 'contrast' => 'Mocniejszy kontrast', 'warm' => 'Cieplej', 'cool' => 'Chłodniej' ),
			'view'    => FP_Photos_Views::choices(),
		);
	}

	const INTS  = array( 'rows' => array( 1, 4 ), 'per_row' => array( 1, 8 ), 'rows_t' => array( 1, 4 ), 'per_row_t' => array( 1, 8 ), 'rows_m' => array( 1, 6 ), 'per_row_m' => array( 1, 4 ), 'gap' => array( 0, 40 ), 'gap_m' => array( 0, 40 ), 'chaos' => array( 0, 100 ), 'radius' => array( 0, 40 ), 'zoom_scale' => array( 100, 125 ), 'strip_h' => array( 120, 600 ) );
	const BOOLS = array( 'stars', 'bag', 'shuffle', 'button', 'zoom' );
	const LISTS = array( 'photos', 'cats', 'folders', 'ex_authors', 'ex_events' );

	public static function stored() {
		return self::sanitize( (array) get_option( self::OPT, array() ), self::defaults() );
	}

	/** Ustawienia jednego widżetu: puste pola z Wygląd, wszystko sprawdzone i w zakresach. */
	public static function settings( array $args ) {
		return self::sanitize( $args, self::stored() );
	}

	/** $raw na wierzch $base; puste wartości (''/null/[]) zostawiają $base. */
	public static function sanitize( array $raw, array $base ) {
		$out = $base;
		$ch  = self::choices();
		foreach ( self::defaults() as $k => $def ) {
			if ( ! array_key_exists( $k, $raw ) || null === $raw[ $k ] || '' === $raw[ $k ] || array() === $raw[ $k ] ) {
				continue;
			}
			$v = $raw[ $k ];
			if ( in_array( $k, self::LISTS, true ) ) {
				$v       = is_array( $v ) ? $v : explode( ',', (string) $v );
				$out[ $k ] = array_values( array_filter( array_map( function ( $x ) { return sanitize_text_field( trim( (string) $x ) ); }, $v ), 'strlen' ) );
			} elseif ( isset( self::INTS[ $k ] ) ) {
				$out[ $k ] = max( self::INTS[ $k ][0], min( self::INTS[ $k ][1], (int) ( is_array( $v ) && isset( $v['size'] ) ? $v['size'] : $v ) ) );
			} elseif ( in_array( $k, self::BOOLS, true ) ) {
				$out[ $k ] = in_array( (string) $v, array( '1', 'yes', 'true', 'on' ), true ) ? 1 : 0;
			} elseif ( isset( $ch[ $k ] ) ) {
				$out[ $k ] = isset( $ch[ $k ][ (string) $v ] ) ? (string) $v : $base[ $k ];
			} elseif ( in_array( $k, array( 'from', 'to' ), true ) ) {
				$out[ $k ] = preg_match( '/^\d{4}(-\d{1,2})?$/', trim( (string) $v ) ) ? trim( (string) $v ) : '';
			}
		}
		// najwyżej 12 widocznych zdjęć na każdym urządzeniu (twardy limit cache)
		foreach ( array( '', '_t', '_m' ) as $d ) {
			while ( $out[ 'rows' . $d ] * $out[ 'per_row' . $d ] > FP_Photos_Picker::MAX && $out[ 'rows' . $d ] > 1 ) {
				$out[ 'rows' . $d ]--;
			}
		}
		return $out;
	}

	/* ---------- wyświetlanie ---------- */

	public static function register_assets() {
		if ( wp_script_is( 'fp-photos', 'registered' ) ) {
			return;
		}
		wp_register_style( 'fp-photos', FP_URL . 'assets/css/photos.css', array(), fp_ver( 'assets/css/photos.css' ) );
		wp_register_script( 'fp-photos', FP_URL . 'assets/js/photos.js', array(), fp_ver( 'assets/js/photos.js' ), true );
	}

	public static function shortcode( $atts ) {
		return self::render( (array) $atts );
	}

	public static function render( array $args = array() ) {
		FP_Scanner::maybe_rescan();
		self::register_assets();
		$s       = self::settings( $args );
		$entries = FP_Photos_Picker::draw( $s );
		if ( ! $entries ) {
			return current_user_can( 'edit_posts' ) ? '<div class="fpp fpp-empty"><p>Brak zdjęć do pokazania: sprawdź tryb i źródło w ustawieniach widżetu albo wygeneruj miniatury (Focus Portfolio → Pulpit).</p></div>' : '';
		}
		wp_enqueue_style( 'fp-photos' );
		wp_enqueue_script( 'fp-photos' );
		if ( 'lightbox' === $s['action'] ) {
			FP_Frontend::register_assets(); // lightbox z portfolio.js
			wp_enqueue_style( 'focus-portfolio' );
			wp_enqueue_script( 'focus-portfolio' );
		}
		$ttl = FP_Photos_Picker::ttl( $s );
		if ( $ttl ) {
			do_action( 'litespeed_control_set_ttl', $ttl ); // strona w cache LiteSpeed wygasa razem z losowaniem
		}
		$id     = 'fpp-' . ( ++self::$n ) . '-' . substr( md5( FP_Photos_Picker::key( $s ) ), 0, 6 );
		$tiles  = array();
		foreach ( $entries as $i => $e ) {
			$tiles[] = self::tile( $e, $s, $i );
		}
		$counts = array( $s['rows'] * $s['per_row'], $s['rows_t'] * $s['per_row_t'], $s['rows_m'] * $s['per_row_m'] );
		$conf   = array(
			'view'    => $s['view'],
			'counts'  => $counts,
			'rows'    => array( $s['rows'], $s['rows_t'], $s['rows_m'] ),
			'chaos'   => $s['chaos'],
			'shuffle' => $s['shuffle'] && FP_Photos_Picker::is_random( $s ) ? 1 : 0,
		);
		$style  = sprintf(
			'--fpp-cols-d:%d;--fpp-cols-t:%d;--fpp-cols-m:%d;--fpp-gap-d:%dpx;--fpp-gap-m:%dpx;--fpp-radius:%dpx;--fpp-zoom:%s;--fpp-strip-h:%dpx',
			$s['per_row'], $s['per_row_t'], $s['per_row_m'], $s['gap'], $s['gap_m'], $s['radius'], number_format( $s['zoom_scale'] / 100, 2, '.', '' ), $s['strip_h']
		);
		$class  = 'fpp fpp-v-' . sanitize_html_class( $s['view'] ) . ' fpp-f-' . sanitize_html_class( $s['filter'] ) . ( $s['zoom'] ? ' fpp-zoom' : '' );
		$box    = 'fpp-box ' . FP_Photos_Views::css_class( $s['view'] ) . ( 'lightbox' === $s['action'] ? ' fp-gallery' : '' );
		$html   = '<div id="' . esc_attr( $id ) . '" class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '" data-fpp="' . esc_attr( wp_json_encode( $conf ) ) . '">';
		$html  .= '<style>' . self::count_css( $id, $counts ) . '</style>';
		if ( $s['button'] && FP_Photos_Picker::is_random( $s ) && count( $tiles ) > min( $counts ) ) {
			$html .= '<div class="fpp-bar"><button type="button" class="fpp-again">↻ ' . esc_html( FP_I18n::t( 'Losuj ponownie' ) ) . '</button></div>';
		}
		$html .= '<div class="' . esc_attr( $box ) . '">' . FP_Photos_Views::render( $s['view'], $tiles, $s ) . '</div></div>';
		return $html;
	}

	/** Bez JS-a: na każdym urządzeniu tylko tyle kafli, ile wierszy × zdjęć w wierszu (reszta to zapas z cache). */
	private static function count_css( $id, $c ) {
		$k = '#' . $id . ' .fpp-box>.fpp-it';
		return sprintf(
			'%1$s:nth-child(n+%2$d){display:none}@media(max-width:1024px){%1$s:nth-child(-n+%3$d){display:block}%1$s:nth-child(n+%4$d){display:none}}@media(max-width:767px){%1$s:nth-child(-n+%5$d){display:block}%1$s:nth-child(n+%6$d){display:none}}',
			$k, $c[0] + 1, $c[1], $c[1] + 1, $c[2], $c[2] + 1
		);
	}

	/**
	 * Moduł 4: kafel z akcją po kliknięciu i podpisem.
	 * @return array ['html', 'r' (proporcje przycięte do 0,7–1,5), 'rr' (proporcje zdjęcia), 'photo', 'group']
	 */
	public static function tile( array $e, array $s, $i ) {
		list( $p, $g, $c ) = $e;
		$title  = FP_Frontend::group_title( $g, $c );
		$period = FP_I18n::period( $g['period'] );
		$author = FP_Frontend::author_name( $p['a'] );
		$by     = '' !== $author ? sprintf( FP_I18n::t( 'fot. %s' ), $author ) : '';
		$alt    = implode( ', ', array_filter( array( $title, $period, $by ) ) );
		$rr     = $p['h'] ? $p['w'] / $p['h'] : 1.5;
		$r      = max( 0.7, min( 1.5, $rr ) );
		$sc     = min( 1, (int) FP_Settings::get( 'grid_size' ) / max( 1, $p['w'], $p['h'] ) );
		$img    = sprintf(
			'<img src="%s" width="%d" height="%d" alt="%s"%s decoding="async">',
			esc_url( FP_Thumbs::url( $p, 'g' ) ), (int) round( $p['w'] * $sc ), (int) round( $p['h'] * $sc ), esc_attr( $alt ),
			$i >= $s['per_row'] ? ' loading="lazy"' : '' // pierwszy wiersz od razu, reszta leniwie
		);
		$cap = '';
		if ( 'none' !== $s['caption'] ) {
			$cap = '<span class="fpp-cap"><b>' . esc_html( $title ) . '</b>' . ( $period ? '<span>' . esc_html( $period ) . '</span>' : '' )
				. ( 'event_author' === $s['caption'] && '' !== $by ? '<span>' . esc_html( $by ) . '</span>' : '' ) . '</span>';
		}
		$attr = sprintf( ' class="fpp-it%s" style="--r:%s;--rr:%s" data-r="%s"', 'lightbox' === $s['action'] ? ' fp-ph' : '', round( $r, 4 ), round( $rr, 4 ), round( $r, 4 ) );
		switch ( $s['action'] ) {
			case 'event':
				$open = '<a' . $attr . ' href="' . esc_url( FP_Frontend::url( $c['slug'], $g['key'] ) ) . '">';
				$end  = '</a>';
				break;
			case 'lightbox':
				$open = '<a' . $attr . ' href="' . esc_url( FP_Thumbs::original_url( $p ) ) . '" data-fp-src="' . esc_url( FP_Thumbs::url( $p, 'l' ) ) . '" data-fp-cap="' . esc_attr( implode( ' · ', array_filter( array( $title, $period, $by ) ) ) ) . '" data-fp-ev="' . esc_url( FP_Links::photo_url( $p, $g, $c ) ) . '" data-fp-n="' . esc_attr( basename( $p['f'] ) ) . '" data-elementor-open-lightbox="no">';
				$end  = '</a>';
				break;
			case 'none':
				$open = '<span' . $attr . '>';
				$end  = '</span>';
				break;
			default: // fotorelacja i dokładnie to zdjęcie
				$open = '<a' . $attr . ' href="' . esc_url( FP_Links::photo_url( $p, $g, $c ) ) . '" data-elementor-open-lightbox="no">';
				$end  = '</a>';
		}
		return array( 'html' => $open . $img . $cap . $end, 'r' => $r, 'rr' => $rr, 'photo' => $p, 'group' => $g );
	}

	/* ---------- blok Gutenberga ---------- */

	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		self::register_assets();
		wp_register_script( 'fp-photos-block', FP_URL . 'assets/js/block-photos.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ), fp_ver( 'assets/js/block-photos.js' ), true );
		$controls = array();
		foreach ( self::choices() as $k => $opts ) {
			$o = array( array( 'value' => '', 'label' => 'Jak w ustawieniach' ) );
			foreach ( $opts as $val => $label ) {
				$o[] = array( 'value' => (string) $val, 'label' => $label );
			}
			$controls[] = array( 'key' => $k, 'type' => 'select', 'options' => $o );
		}
		foreach ( array_keys( self::INTS ) as $k ) {
			$controls[] = array( 'key' => $k, 'type' => 'number' );
		}
		foreach ( array_merge( self::LISTS, array( 'from', 'to' ) ) as $k ) {
			$controls[] = array( 'key' => $k, 'type' => 'text' );
		}
		wp_localize_script( 'fp-photos-block', 'FP_PHOTOS_BLOCK', array( 'controls' => $controls, 'labels' => self::labels() ) );
		$attributes = array();
		foreach ( array_keys( self::defaults() ) as $k ) {
			$attributes[ $k ] = array( 'type' => 'string', 'default' => '' );
		}
		register_block_type( 'focus/photos', array(
			'api_version'     => 3,
			'title'           => 'Focus Portfolio – Zdjęcia',
			'category'        => 'media',
			'icon'            => 'images-alt2',
			'editor_script'   => 'fp-photos-block',
			'editor_style'    => 'fp-photos',
			'style'           => 'fp-photos',
			'script'          => 'fp-photos',
			'attributes'      => $attributes,
			'supports'        => array( 'align' => array( 'wide', 'full' ), 'html' => false, 'spacing' => array( 'margin' => true, 'padding' => true ) ),
			'render_callback' => function ( $attrs ) {
				$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : 'class="wp-block-focus-photos"';
				return '<div ' . $wrapper . '>' . FP_Photos::render( (array) $attrs ) . '</div>';
			},
		) );
	}

	/** Etykiety ustawień (blok, panel). */
	public static function labels() {
		return array(
			'mode' => 'Wybór zdjęć', 'photos' => 'Konkretne zdjęcia (identyfikatory po przecinku)', 'source' => 'Źródło', 'cats' => 'Kategorie (katalogi po przecinku)',
			'folders' => 'Katalogi (po przecinku)', 'from' => 'Okres od (RRRR-MM)', 'to' => 'Okres do (RRRR-MM)', 'stars' => 'Tylko z gwiazdką', 'orient' => 'Orientacja',
			'ex_authors' => 'Wyklucz autorów', 'ex_events' => 'Wyklucz wydarzenia', 'refresh' => 'Nowe losowanie', 'bag' => 'Bez powtórek między losowaniami (worek)',
			'shuffle' => 'Tasuj w przeglądarce', 'button' => 'Przycisk „Losuj ponownie”', 'action' => 'Kliknięcie', 'caption' => 'Podpis po najechaniu', 'view' => 'Widok',
			'rows' => 'Wiersze: komputer', 'per_row' => 'Zdjęć w wierszu: komputer', 'rows_t' => 'Wiersze: tablet', 'per_row_t' => 'Zdjęć w wierszu: tablet',
			'rows_m' => 'Wiersze: telefon', 'per_row_m' => 'Zdjęć w wierszu: telefon', 'gap' => 'Odstęp (px)', 'gap_m' => 'Odstęp na telefonie (px)',
			'chaos' => 'Chaos wierszy (%)', 'radius' => 'Zaokrąglenie (px)', 'filter' => 'Filtr kolorów', 'zoom' => 'Powiększenie po najechaniu',
			'zoom_scale' => 'Siła powiększenia (%)', 'strip_h' => 'Wysokość paska (px)',
		);
	}

	/* ---------- panel: domyślne ustawienia i cache ---------- */

	private static function guard( $nonce ) {
		if ( ! FP_Caps::can( FP_Caps::ADMIN ) ) {
			wp_die( 'Brak uprawnień.' );
		}
		check_admin_referer( $nonce );
	}

	public static function save() {
		self::guard( 'fp_save_photos' );
		$in = isset( $_POST['fpp'] ) ? (array) wp_unslash( $_POST['fpp'] ) : array(); // phpcs:ignore
		foreach ( self::BOOLS as $b ) {
			$in[ $b ] = empty( $in[ $b ] ) ? '0' : '1'; // odznaczone pole nie przychodzi w formularzu
		}
		update_option( self::OPT, self::sanitize( $in, self::stored() ), false );
		wp_safe_redirect( FP_Admin::url( 'view', array( 'fp_msg' => 'saved' ) ) . '#fp-card-photos' );
		exit;
	}

	public static function clear() {
		self::guard( 'fp_clear_photos' );
		FP_Photos_Picker::clear();
		do_action( 'litespeed_purge_all' ); // strony z widżetem pokażą nowe losowanie od razu
		wp_safe_redirect( FP_Admin::url( 'view', array( 'fp_msg' => 'photos_cleared' ) ) . '#fp-card-photos' );
		exit;
	}
}
