<?php
/**
 * Ustawienia wtyczki: źródło i automatyzacja (fp_settings) oraz wygląd (fp_view).
 */

defined( 'ABSPATH' ) || exit;

class FP_Settings {

	const OPT      = 'fp_settings';
	const OPT_VIEW = 'fp_view';

	private static $cache = array();

	/** Ustawienia nadpisane przez widżet Elementora lub blok na czas jednego renderowania. */
	public static $override = array();

	public static function defaults() {
		return array(
			'source'        => 'uploads/portfolio-focus', // względem wp-content
			'page_id'       => 0,                   // strona, na której wyświetla się portfolio
			'academic_year' => 2025,                // domyślny rok dla katalogów miesięcy bez roku
			'season_start'  => 1,                   // pierwszy miesiąc „roku zdjęć”: 1 = rok kalendarzowy, 10 = akademicki, 9 = szkolny;
			                                        // miesiące od niego do grudnia → domyślny rok, wcześniejsze → rok następny
			'grid_size'     => 1280,
			'large_size'    => 2048,
			'quality'       => 82,
			'cron_batch'    => 20,
			'ondemand'      => 1,                   // generuj brakujące miniatury przy pierwszym wyświetleniu
			'ignore_artists' => '',                 // podpisy w EXIF „Artist” do pominięcia (np. nazwa organizacji), po jednym w linii
			'ocr_skip'      => '',                  // słowa w stopce zdjęcia, które nie są nazwiskiem (np. logo „FOCUS”), po przecinku
			'language'      => '',                  // język portfolio na stronie: '' = jak strona, albo pl | en | de | es | fr | it
		);
	}

	public static function view_defaults() {
		return array(
			'title'         => 'Portfolio',
			'intro'         => '',
			'show_header'   => 1,
			'show_stats'    => 1,
			'variant'       => 'classic',           // classic | tiles (kolory zawsze ze strony)
			'gallery'       => 'masonry',           // masonry | grid | justified
			'cols_mode'     => 'fixed',             // kolumny galerii: fixed (na urządzenie) | auto (min–max wg szerokości zdjęcia)
			'columns'       => 4,
			'columns_t'     => 3,                   // tablet
			'columns_m'     => 2,
			'cols_min'      => 2,
			'cols_max'      => 5,
			'tile_min'      => 260,                 // min. szerokość zdjęcia w trybie auto (px)
			'cards_mode'    => 'fixed',             // kolumny kart wydarzeń: fixed | auto
			'ccols'         => 4,
			'ccols_t'       => 3,
			'ccols_m'       => 2,
			'cards_min'     => 1,
			'cards_max'     => 4,
			'even_cols'     => 1,                   // masonry: wyrównany dół kolumn
			'cover_fit'     => 'cover',             // okładki: cover (wypełnij pole) | contain (całe zdjęcie)
			'cover_count'   => '1',                 // ile okładek na karcie wydarzenia: 1 | 3 | 5
			'per_page'      => '50',                // zdjęć na stronę: 20 | 50 | 100
			'photo_sort'    => 'best',              // domyślne sortowanie „Wszystkie zdjęcia”
			'show_author_filter' => 1,
			'show_sort'     => 1,
			'show_per_page' => 1,
			'gap'           => 10,
			'max_width'     => 1240,                // szerokość treści portfolio (px), 0 = cała szerokość
			'side_pad'      => 24,                  // margines z lewej i prawej (px)
			'row_height'    => 260,                 // dla wariantu justified
			'card_ratio'    => '3/2',
			'card_min'      => 230,
			'sort'          => 'newest',
			'show_all_view' => 1,
			'show_jumps'    => 1,
			'show_authors'  => 1,
			'show_counts'   => 1,
			'show_related'  => 1,
			'lightbox'      => 1,
			'lb_download'   => 1,
			'accent'        => '#1bb0ce',
			'text'          => '',                  // puste = kolor tekstu strony
			'muted'         => '',                  // puste = odcień koloru tekstu
			'line'          => '',
			'soft'          => '',
			'heading_font'  => '',
			'label_events'  => 'Wydarzenia',
			'label_all'     => 'Wszystkie zdjęcia',
			'label_photos'  => 'Zdjęcia',
			'label_other'   => 'Pozostałe',
			'categories'    => array(),             // [dir => [title, desc, mode, order, hidden]]
		);
	}

	public static function all() {
		if ( ! isset( self::$cache['s'] ) ) {
			self::$cache['s'] = wp_parse_args( (array) get_option( self::OPT, array() ), self::defaults() );
		}
		return self::$cache['s'];
	}

	public static function view_all() {
		if ( ! isset( self::$cache['v'] ) ) {
			self::$cache['v'] = wp_parse_args( (array) get_option( self::OPT_VIEW, array() ), self::view_defaults() );
		}
		return self::$override ? array_merge( self::$cache['v'], self::$override ) : self::$cache['v'];
	}

	public static function get( $key ) {
		$a = self::all();
		return isset( $a[ $key ] ) ? $a[ $key ] : null;
	}

	public static function view( $key ) {
		$a = self::view_all();
		return isset( $a[ $key ] ) ? $a[ $key ] : null;
	}

	public static function update( array $values ) {
		update_option( self::OPT, array_merge( self::all(), $values ), false );
		unset( self::$cache['s'], self::$cache['path'] );
	}

	public static function update_view( array $values ) {
		update_option( self::OPT_VIEW, array_merge( self::view_all(), $values ), true );
		unset( self::$cache['v'] );
	}

	/** @return array{groups: array<string,string>, cats: array<string,string[]>} */
	public static function covers() {
		$c = get_option( 'fp_covers', array() );
		return array(
			'groups' => isset( $c['groups'] ) ? (array) $c['groups'] : array(),
			'cats'   => isset( $c['cats'] ) ? (array) $c['cats'] : array(),
		);
	}

	public static function save_covers( $covers ) {
		update_option( 'fp_covers', $covers, false );
	}

	/** Aktualizacje schematu danych między wersjami (uruchamiane raz, przy pierwszym żądaniu po wgraniu). */
	public static function maybe_upgrade() {
		$ver = (string) get_option( 'fp_db_version', '1.0.0' );
		if ( version_compare( $ver, '1.3.0', '>=' ) ) {
			return;
		}
		if ( version_compare( $ver, '1.1.0', '<' ) ) {
			// 1.1: miniatury siatki 2x większe (640 → 1280 px), lepsza jakość
			$s = self::all();
			if ( (int) $s['grid_size'] <= 640 || (int) $s['quality'] < 82 ) {
				self::update( array( 'grid_size' => max( 1280, (int) $s['grid_size'] ), 'quality' => max( 82, (int) $s['quality'] ) ) );
				FP_Thumbs::reset_size( 'g' );
			}
		}
		if ( version_compare( $ver, '1.2.0', '<' ) ) {
			// 1.2: źródło względem wp-content zamiast wp-content/uploads
			$src = trim( (string) self::get( 'source' ), '/' );
			if ( '' !== $src && 0 !== strpos( $src, 'uploads/' ) && ! is_dir( WP_CONTENT_DIR . '/' . $src ) && is_dir( WP_CONTENT_DIR . '/uploads/' . $src ) ) {
				self::update( array( 'source' => 'uploads/' . $src ) );
			}
			FP_Caps::install();
			update_option( 'fp_migrate_meta', 1, false ); // okładki i autorzy z bazy → _portfolio.json (po skanie)
		}
		// 1.3: kolory ze strony zamiast stałych (dawne domyślne usunięte), bez wariantu „ciemny”;
		// katalogi miesięcy bez roku liczone jak dotąd (rok akademicki)
		$v = get_option( self::OPT_VIEW );
		if ( is_array( $v ) ) {
			foreach ( array( 'text' => '#1c1f22', 'muted' => '#66707a', 'line' => '#e4e8eb', 'soft' => '#f4f7f8' ) as $k => $old ) {
				if ( isset( $v[ $k ] ) && strtolower( (string) $v[ $k ] ) === $old ) {
					$v[ $k ] = '';
				}
			}
			if ( isset( $v['variant'] ) && 'dark' === $v['variant'] ) {
				$v['variant'] = 'classic';
			}
			update_option( self::OPT_VIEW, $v, true );
			unset( self::$cache['v'] );
		}
		// zachowanie z wersji ≤1.2, które teraz jest ustawieniem: rok akademicki, pomijany podpis organizacji w EXIF i w stopce
		$s = get_option( self::OPT );
		if ( is_array( $s ) ) {
			$legacy = array( 'season_start' => 10, 'ignore_artists' => 'Klub Filmowo-Fotograficzny Focus', 'ocr_skip' => 'FOCUS, KLUB' );
			foreach ( $legacy as $k => $val ) {
				if ( ! isset( $s[ $k ] ) ) {
					$s[ $k ] = $val;
				}
			}
			update_option( self::OPT, $s, false );
			unset( self::$cache['s'] );
		}
		// ≤1.2 brała tytuły, adresy i kolejność kategorii z wbudowanej listy nazw (FOTORELACJA → „Fotorelacje”);
		// teraz nazwa wynika tylko z katalogu, więc dotychczasowe wartości trafiają do Struktury i adresy się nie zmieniają
		$m = get_option( 'fp_manifest' );
		if ( is_array( $m ) && ! empty( $m['cats'] ) ) {
			$order = array( 'FOTORELACJA' => 1, 'FOTORELACJE' => 1, 'KONCERTOWE' => 2, 'KONCERTY' => 2, 'SESJA' => 3, 'SESJE' => 3, 'DKF' => 4 );
			$cats  = (array) self::structure()['categories'];
			foreach ( $m['cats'] as $c ) {
				$cc  = isset( $cats[ $c['dir'] ] ) ? (array) $cats[ $c['dir'] ] : array();
				$key = mb_strtoupper( trim( $c['dir'], '_- ' ) );
				if ( empty( $cc['slug'] ) ) {
					$cc['slug'] = $c['slug'];
				}
				if ( ( ! isset( $cc['title'] ) || '' === $cc['title'] ) && empty( $c['mtitle'] ) ) {
					$cc['title'] = $c['title'];
				}
				if ( ! isset( $cc['order'] ) ) {
					$cc['order'] = isset( $order[ $key ] ) ? $order[ $key ] : 10;
				}
				$cats[ $c['dir'] ] = $cc;
			}
			self::update_structure( array( 'categories' => $cats ) );
		}
		update_option( 'fp_db_version', '1.3.0', true );
		do_action( 'litespeed_purge_all' ); // nowy wygląd od razu, bez starych kopii stron w pamięci podręcznej
	}


	/** Katalogi w wp-content, których nie można wybrać jako źródła (kod strony, kopie, pamięć podręczna). */
	public static function forbidden_dirs() {
		return array( 'plugins', 'themes', 'mu-plugins', 'upgrade', 'upgrade-temp-backup', 'cache', 'languages', 'wflogs', 'ai1wm-backups', 'backups', 'backup', 'updraft', 'uploads/' . FP_Thumbs::CACHE );
	}

	/** Czy ścieżka (względem wp-content) może być źródłem zdjęć. */
	public static function source_allowed( $rel ) {
		$rel = trim( str_replace( '\\', '/', (string) $rel ), '/' );
		if ( '' === $rel || false !== strpos( $rel, '..' ) ) {
			return false;
		}
		foreach ( self::forbidden_dirs() as $f ) {
			if ( $rel === $f || 0 === strpos( $rel . '/', $f . '/' ) ) {
				return false;
			}
		}
		return true;
	}

	public static function ensure_defaults() {
		if ( false === get_option( self::OPT ) ) {
			$d = self::defaults();
			$p = get_page_by_path( 'portfolio' );
			if ( $p ) {
				$d['page_id'] = $p->ID;
			}
			add_option( self::OPT, $d, '', false );
		}
		if ( false === get_option( self::OPT_VIEW ) ) {
			add_option( self::OPT_VIEW, self::view_defaults() );
		}
	}

	/** Bezwzględna ścieżka katalogu źródłowego (bez końcowego ukośnika) albo '' gdy niepoprawna. */
	public static function source_path() {
		if ( isset( self::$cache['path'] ) ) {
			return self::$cache['path'];
		}
		$base = realpath( WP_CONTENT_DIR );
		$rel  = trim( str_replace( '\\', '/', (string) self::get( 'source' ) ), '/' );
		$path = self::source_allowed( $rel ) ? realpath( WP_CONTENT_DIR . '/' . $rel ) : false;
		if ( ! $base || ! $path || 0 !== strpos( $path, $base ) || ! is_dir( $path ) ) {
			return self::$cache['path'] = '';
		}
		return self::$cache['path'] = untrailingslashit( wp_normalize_path( $path ) );
	}

	public static function source_url() {
		$rel = trim( str_replace( '\\', '/', (string) self::get( 'source' ) ), '/' );
		return set_url_scheme( content_url( implode( '/', array_map( 'rawurlencode', explode( '/', $rel ) ) ) ) );
	}

	/** Struktura katalogów: ustawienia kategorii (grupowanie, filtry, kolejność) i polityka dat. */
	public static function structure() {
		if ( ! isset( self::$cache['st'] ) ) {
			$st = get_option( 'fp_structure', null );
			if ( ! is_array( $st ) ) {
				// z wersji 1.x: kategorie były w ustawieniach wyglądu
				$v  = (array) get_option( self::OPT_VIEW, array() );
				$st = array( 'categories' => isset( $v['categories'] ) ? (array) $v['categories'] : array() );
			}
			self::$cache['st'] = wp_parse_args( $st, array( 'categories' => array(), 'auto_archive' => 0, 'date_policy' => 'inbox' ) );
		}
		return self::$cache['st'];
	}

	public static function update_structure( array $values ) {
		update_option( 'fp_structure', array_merge( self::structure(), $values ), false );
		unset( self::$cache['st'] );
	}

	/** Ustawienia kategorii ze Struktury, scalone z wartościami wykrytymi przez skaner. */
	public static function category( $dir ) {
		$st   = self::structure();
		$cats = (array) $st['categories'];
		$c    = isset( $cats[ $dir ] ) ? (array) $cats[ $dir ] : array();
		// slug: adres kategorii zapisany na stałe (zmiana tytułu nie zmienia adresu); pusty = z nazwy katalogu
		$c = wp_parse_args( $c, array( 'title' => '', 'slug' => '', 'desc' => '', 'mode' => 'auto', 'order' => 10, 'hidden' => 0, 'other' => '' ) );
		$c['filters'] = wp_parse_args( isset( $c['filters'] ) ? (array) $c['filters'] : array(), array( 'period' => 1, 'author' => 1, 'sort' => 1, 'search' => 0 ) );
		return $c;
	}
}
