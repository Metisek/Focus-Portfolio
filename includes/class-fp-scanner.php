<?php
/**
 * Skaner folderu źródłowego: zamienia drzewo folderów na manifest
 * kategoria → wydarzenie → zdjęcia, z regułami porządkującymi nazwy.
 *
 * Tryby kategorii:
 *  - months   : podfoldery to miesiące („KWIECIEŃ”, „LISTOPAD 2025”, „2026-04 Kwiecień”), w nich wydarzenia
 *  - numbered : edycje konkursu („07 granice 25_”, „2026/03 Ramka”)
 *  - events   : podfoldery to wydarzenia; luźne pliki grupowane po nazwie („Halloween-Opera-01.jpg”)
 */

defined( 'ABSPATH' ) || exit;

class FP_Scanner {

	const OPT_MANIFEST = 'fp_manifest';
	const OPT_SIG      = 'fp_signature';
	const IMG_RE       = '/\.(jpe?g|png|webp)$/i';
	const NO_AUTHOR    = '-';                    // w _portfolio.json → authors: zdjęcie celowo bez autora

	const MONTH_SLUGS = array( 'styczen', 'luty', 'marzec', 'kwiecien', 'maj', 'czerwiec', 'lipiec', 'sierpien', 'wrzesien', 'pazdziernik', 'listopad', 'grudzien' );
	const MONTH_NAMES = array( 'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień' );
	const MONTH_GEN   = array( 'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia' );

	private static $manifest = null;
	private static $prev     = array();
	private static $issues   = array();
	private static $root     = '';
	private static $index    = null;
	private static $variants = array();
	private static $problems = array();
	private static $mode     = 'events';

	/* ---------- dostęp do manifestu ---------- */

	public static function manifest() {
		if ( null === self::$manifest ) {
			$m              = get_option( self::OPT_MANIFEST );
			self::$manifest = is_array( $m ) ? $m : array( 'cats' => array(), 'issues' => array(), 'stats' => array(), 'generated' => 0 );
		}
		return self::$manifest;
	}

	public static function categories( $include_hidden = false ) {
		$m    = self::manifest();
		$cats = array();
		foreach ( $m['cats'] as $c ) {
			$conf = FP_Settings::category( $c['dir'] );
			if ( $conf['hidden'] && ! $include_hidden ) {
				continue;
			}
			$c['title'] = ! empty( $c['mtitle'] ) ? $c['mtitle'] : ( '' !== $conf['title'] ? $conf['title'] : $c['title'] );
			$c['desc']  = ! empty( $c['mdesc'] ) ? $c['mdesc'] : $conf['desc'];
			$c['order'] = (int) $conf['order'];
			$c['other'] = '' !== $conf['other'] ? $conf['other'] : FP_Settings::view( 'label_other' );
			if ( ! $include_hidden ) {
				$c['groups'] = array_values( array_filter( $c['groups'], function ( $g ) { return empty( $g['hidden'] ); } ) );
				if ( ! $c['groups'] ) {
					continue;
				}
			}
			$c['mosaic'] = isset( $c['mosaic'] ) ? $c['mosaic'] : array();
			$cats[]     = $c;
		}
		usort( $cats, function ( $a, $b ) {
			return $a['order'] === $b['order'] ? 0 : ( $a['order'] < $b['order'] ? -1 : 1 );
		} );
		if ( 'oldest' === FP_Settings::view( 'sort' ) ) {
			foreach ( $cats as &$c ) {
				$c['groups'] = self::reverse_keep_other( $c['groups'] );
			}
			unset( $c );
		}
		return $cats;
	}

	private static function reverse_keep_other( $groups ) {
		$main  = array_values( array_filter( $groups, function ( $g ) { return empty( $g['other'] ); } ) );
		$other = array_values( array_filter( $groups, function ( $g ) { return ! empty( $g['other'] ); } ) );
		usort( $main, function ( $a, $b ) {
			return $a['sort'] === $b['sort'] ? strnatcasecmp( $a['title'], $b['title'] ) : ( $a['sort'] < $b['sort'] ? -1 : 1 );
		} );
		return array_merge( $main, $other );
	}

	public static function find_category( $slug, $include_hidden = false ) {
		foreach ( self::categories( $include_hidden ) as $c ) {
			if ( $c['slug'] === $slug ) {
				return $c;
			}
		}
		return null;
	}

	public static function find_group( $cat, $key ) {
		foreach ( $cat['groups'] as $g ) {
			if ( $g['key'] === $key ) {
				return $g;
			}
		}
		return null;
	}

	/** id zdjęcia → [zdjęcie, kategoria-slug, klucz-grupy] */
	public static function photo_by_id( $id ) {
		if ( null === self::$index ) {
			self::$index = array();
			foreach ( self::manifest()['cats'] as $c ) {
				foreach ( $c['groups'] as $g ) {
					foreach ( $g['photos'] as $p ) {
						self::$index[ $p['id'] ] = array( $p, $c['slug'], $g['key'] );
					}
					if ( ! empty( $g['cover'] ) ) {
						self::$index[ $g['cover']['id'] ] = array( $g['cover'], $c['slug'], $g['key'] );
					}
				}
			}
		}
		return isset( self::$index[ $id ] ) ? self::$index[ $id ] : null;
	}

	public static function all_photos() {
		$out = array();
		foreach ( self::manifest()['cats'] as $c ) {
			foreach ( $c['groups'] as $g ) {
				foreach ( $g['photos'] as $p ) {
					$out[ $p['id'] ] = $p;
				}
				if ( ! empty( $g['cover'] ) ) {
					$out[ $g['cover']['id'] ] = $g['cover'];
				}
			}
		}
		return $out;
	}

	/* ---------- wykrywanie zmian ---------- */

	/** Skan tylko gdy zmienił się folder albo ustawienia, które wpływają na wynik. */
	public static function maybe_rescan( $skip_throttle = false ) {
		if ( ! $skip_throttle ) {
			if ( get_transient( 'fp_last_check' ) ) {
				return false;
			}
			set_transient( 'fp_last_check', 1, 5 * MINUTE_IN_SECONDS );
		}
		$sig = self::signature();
		if ( $sig && ( $sig !== get_option( self::OPT_SIG ) || ! self::manifest()['generated'] ) ) {
			self::scan();
			return true;
		}
		return false;
	}

	public static function signature() {
		$root = FP_Settings::source_path();
		if ( ! $root ) {
			return '';
		}
		$parts = array( 'v6', FP_Settings::get( 'source' ), FP_Settings::get( 'academic_year' ), FP_Settings::get( 'season_start' ), $root . ':' . filemtime( $root ) );
		foreach ( (array) FP_Settings::structure()['categories'] as $dir => $c ) {
			$parts[] = $dir . '=' . ( isset( $c['mode'] ) ? $c['mode'] : 'auto' );
		}
		try {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $it as $f ) {
				if ( $f->isDir() ) {
					$parts[] = $f->getPathname() . ':' . $f->getMTime();
				}
			}
		} catch ( Exception $e ) {
			return '';
		}
		return md5( implode( '|', $parts ) );
	}

	/* ---------- skan ---------- */

	/** @param bool $fresh odczytaj metadane wszystkich plików od nowa (np. po zmianie pomijanych podpisów EXIF) */
	public static function scan( $fresh = false ) {
		$root = FP_Settings::source_path();
		if ( ! $root ) {
			return new WP_Error( 'fp_source', 'Katalog źródłowy nie istnieje albo leży poza wp-content.' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore
		}
		self::$root   = $root;
		self::$issues   = array();
		self::$prev     = array();
		self::$variants = array();
		self::$problems = array();
		FP_Meta::flush();
		foreach ( $fresh ? array() : self::all_photos() as $p ) {
			self::$prev[ $p['f'] ] = $p;
		}

		list( $dirs, $files ) = self::ls( $root );
		if ( $files ) {
			self::issue( sprintf( '%d plików leży bezpośrednio w folderze źródłowym; wtyczka pokazuje tylko zdjęcia w folderach kategorii.', count( $files ) ) );
		}

		$cats = array();
		foreach ( $dirs as $dir ) {
			$conf  = FP_Settings::category( $dir );
			$mode  = 'auto' !== $conf['mode'] ? $conf['mode'] : self::detect_mode( $root . '/' . $dir );
			$title = self::default_cat_title( $dir );
			$meta  = FP_Meta::get( $dir );
			self::$mode = $mode;
			$cat   = array(
				'dir'      => $dir,
				'slug'     => '' !== $conf['slug'] ? sanitize_title( $conf['slug'] ) : self::slug( $title ),
				'title'    => $title,
				'mtitle'   => isset( $meta['title'] ) ? (string) $meta['title'] : '',
				'mdesc'    => isset( $meta['description'] ) ? (string) $meta['description'] : '',
				'subtitle' => isset( $meta['subtitle'] ) ? (string) $meta['subtitle'] : '',
				'mode'     => $mode,
				'groups'   => array(),
			);
			if ( 'months' === $mode ) {
				$cat['groups'] = self::scan_months( $dir );
			} elseif ( 'numbered' === $mode ) {
				$cat['groups'] = self::scan_numbered( $dir, $dir, null );
			} elseif ( 'years' === $mode ) {
				$cat['groups'] = self::scan_years( $dir );
			} elseif ( 'dated' === $mode ) {
				$cat['groups'] = self::scan_dated( $dir );
			} else {
				$cat['groups'] = self::scan_events( $dir );
			}
			if ( ! $cat['groups'] ) {
				continue;
			}
			// mozaika kategorii: okładki wybrane w katalogu kategorii (ścieżki względem niego)
			$cat['mosaic'] = array();
			foreach ( array_slice( (array) ( isset( $meta['covers'] ) ? $meta['covers'] : array() ), 0, 5 ) as $cf ) {
				foreach ( $cat['groups'] as $g ) {
					foreach ( $g['photos'] as $ph ) {
						if ( $ph['f'] === $dir . '/' . $cf ) {
							$cat['mosaic'][] = $ph;
							continue 3;
						}
					}
				}
			}
			$cats[] = $cat;
		}

		self::relate( $cats );

		$stats = array( 'photos' => 0, 'bytes' => 0, 'big' => 0, 'max' => 0, 'groups' => 0, 'authors' => 0 );
		foreach ( $cats as &$c ) {
			self::sort_groups( $c['groups'] );
			self::unique_keys( $c['groups'] );
			$c['count']       = 0;
			$stats['groups'] += count( $c['groups'] );
			foreach ( $c['groups'] as $g ) {
				$c['count'] += count( $g['photos'] );
				foreach ( $g['photos'] as $p ) {
					$stats['photos']++;
					$stats['bytes'] += $p['s'];
					$stats['max']    = max( $stats['max'], $p['s'] );
					$stats['big']   += $p['s'] > 5 * MB_IN_BYTES ? 1 : 0;
					$stats['authors'] += '' !== $p['a'] ? 1 : 0;
				}
			}
		}
		unset( $c );

		if ( self::$variants ) {
			self::issue( sprintf( 'Pominięto %d kopii rozmiarów utworzonych przez Bibliotekę mediów WordPressa (np. „-150x150”, „-scaled”). Pokazywane są tylko oryginały.', count( self::$variants ) ) );
		}
		if ( $stats['big'] ) {
			self::issue( sprintf( '%d oryginałów ma ponad 5 MB (największy %s); strona używa miniatur WebP, więc to nie spowalnia portfolio.', $stats['big'], size_format( $stats['max'] ) ) );
		}

		self::$manifest = array(
			'generated' => time(),
			'source'    => FP_Settings::get( 'source' ),
			'cats'      => $cats,
			'issues'    => self::$issues,
			'problems'  => self::$problems,
			'stats'     => $stats,
		);
		self::$index    = null;
		update_option( self::OPT_MANIFEST, self::$manifest, false );
		update_option( self::OPT_SIG, self::signature(), false );
		do_action( 'fp_scanned', self::$manifest );
		if ( get_option( 'fp_migrate_meta' ) ) {
			self::migrate_meta();
		}
		return self::$manifest;
	}

	/** Miesiące → wydarzenia; miesiące mogą też leżeć w katalogach lat („2026/04 Kwiecień/Wydarzenie”). */
	private static function scan_months( $catdir, $rel = null, $parent_year = null ) {
		$groups               = array();
		$rel                  = null === $rel ? $catdir : $rel;
		list( $dirs, $files ) = self::ls( self::$root . '/' . $rel );
		self::add_loose( $groups, $rel, $files, null );
		$ay = (int) FP_Settings::get( 'academic_year' );
		foreach ( $dirs as $mname ) {
			$mrel = $rel . '/' . $mname;
			if ( ! $parent_year && preg_match( '/^(20\d\d)$/', $mname ) ) {
				$groups = array_merge( $groups, self::scan_months( $catdir, $mrel, (int) $mname ) );
				continue;
			}
			list( $mon, $yr, $explicit ) = self::parse_month( $mname, $ay, $parent_year );
			$mmeta = FP_Meta::get( $mrel );
			if ( ! empty( $mmeta['date'] ) && preg_match( '/^(20\d\d)-(\d{2})/', $mmeta['date'], $md ) ) {
				list( $mon, $yr, $explicit ) = array( max( 1, min( 12, (int) $md[2] ) ), (int) $md[1], true );
			}
			if ( ! $mon ) {
				self::problem( 'no_date', $mrel, sprintf( 'Katalog „%s” nie wygląda na miesiąc (np. „2026-04 Kwiecień”), więc jest pokazany jako wydarzenie bez daty.', $mname ) );
				self::add_dir_event( $groups, $mrel, null );
				continue;
			}
			$period = array(
				'label' => self::MONTH_NAMES[ $mon - 1 ] . ' ' . $yr,
				'key'   => self::slug( self::MONTH_NAMES[ $mon - 1 ] . ' ' . $yr ),
				'sort'  => $yr * 100 + $mon,
			);
			if ( false !== strpos( $mname, '(' ) ) {
				self::issue( sprintf( 'Notatka w nazwie folderu „%s” jest ukryta w tytule.', $mrel ) );
			}
			if ( ! empty( $mmeta['archive'] ) ) {
				$period = self::archive_period();
			}
			if ( ! $explicit ) {
				self::issue( sprintf( 'Folder „%s” nie ma roku; przyjęto %d (%s).', $mrel, $yr, self::year_rule() ) );
				self::problem( 'no_year', $mrel, sprintf( 'Miesiąc „%s” nie ma roku; przyjęto %d. Wpisz rok albo zmień nazwę na „%d-%02d %s”.', $mname, $yr, $yr, $mon, self::MONTH_NAMES[ $mon - 1 ] ) );
			}
			list( $edirs, $efiles ) = self::ls( self::$root . '/' . $mrel );
			self::add_loose( $groups, $mrel, $efiles, $period );
			foreach ( $edirs as $ename ) {
				self::add_dir_event( $groups, $mrel . '/' . $ename, $period );
			}
		}
		return $groups;
	}

	private static function scan_numbered( $catdir, $rel, $year ) {
		$groups               = array();
		list( $dirs, $files ) = self::ls( self::$root . '/' . $rel );
		self::add_loose( $groups, $rel, $files, null );
		foreach ( $dirs as $name ) {
			$drel = $rel . '/' . $name;
			if ( preg_match( '/^(20\d\d)$/', $name ) ) {
				$groups = array_merge( $groups, self::scan_numbered( $catdir, $drel, (int) $name ) );
				continue;
			}
			if ( ! preg_match( '/^(\d{1,2})[\s._-]+(.+?)(?:\s+(\d{2})_)?$/u', $name, $m ) ) {
				// galeria bez numeru w katalogu roku dostaje ten rok
				self::add_dir_event( $groups, $drel, $year ? array( 'label' => (string) $year, 'key' => (string) $year, 'sort' => $year * 100 ) : null );
				continue;
			}
			$num = (int) $m[1];
			$yr  = ! empty( $m[3] ) ? 2000 + (int) $m[3] : $year;
			$emeta = FP_Meta::get( $drel );
			if ( ! $yr && ! empty( $emeta['date'] ) && preg_match( '/^(20\d\d)/', $emeta['date'], $ey ) ) {
				$yr = (int) $ey[1];
			}
			if ( ! $yr && empty( $emeta['archive'] ) ) {
				self::issue( sprintf( 'Edycja „%s” nie ma roku (np. sufiksu „25_”); trafia do „Archiwum”.', $drel ) );
				self::problem( 'no_year', $drel, sprintf( 'Edycja „%s” nie ma roku i trafia do „Archiwum”. Wpisz rok albo zostaw w archiwum.', $name ) );
			}
			$period = array(
				'label' => $yr ? (string) $yr : 'Archiwum',
				'key'   => $yr ? (string) $yr : 'archiwum',
				'sort'  => ( $yr ? $yr : 2000 ) * 100 + $num,
			);
			$g = self::make_group( self::mb_ucfirst( self::clean_title( $m[2] ) ), self::walk( $drel ), $period, $drel );
			if ( $g ) {
				$g['num'] = $num;
				$g['key'] = $period['key'] . '/' . sprintf( '%02d', $num ) . '-' . $g['slug'];
				$groups[] = $g;
			}
		}
		return $groups;
	}

	private static function scan_events( $catdir ) {
		$groups               = array();
		list( $dirs, $files ) = self::ls( self::$root . '/' . $catdir );
		self::add_loose( $groups, $catdir, $files, null );
		foreach ( $dirs as $name ) {
			self::add_dir_event( $groups, $catdir . '/' . $name, null );
		}
		return $groups;
	}

	/** Lata → wydarzenia („2026/Juwenalia”); wydarzenie z datą w nazwie („2026-05-12 Juwenalia”) traci ją z tytułu. */
	private static function scan_years( $catdir ) {
		$groups               = array();
		list( $dirs, $files ) = self::ls( self::$root . '/' . $catdir );
		self::add_loose( $groups, $catdir, $files, null );
		foreach ( $dirs as $name ) {
			$rel = $catdir . '/' . $name;
			if ( ! preg_match( '/^(20\d\d)$/', $name ) ) {
				self::add_dated_event( $groups, $rel, null );
				continue;
			}
			$period                 = array( 'label' => $name, 'key' => $name, 'sort' => (int) $name * 100 );
			list( $edirs, $efiles ) = self::ls( self::$root . '/' . $rel );
			self::add_loose( $groups, $rel, $efiles, $period );
			foreach ( $edirs as $e ) {
				self::add_dated_event( $groups, $rel . '/' . $e, $period );
			}
		}
		return $groups;
	}

	/** Wydarzenia z datą na początku nazwy: „2026-05-12 Juwenalia”, „2026-05 Juwenalia”, „2026 Juwenalia”. */
	private static function scan_dated( $catdir ) {
		$groups               = array();
		list( $dirs, $files ) = self::ls( self::$root . '/' . $catdir );
		self::add_loose( $groups, $catdir, $files, null );
		foreach ( $dirs as $name ) {
			self::add_dated_event( $groups, $catdir . '/' . $name, null );
		}
		return $groups;
	}

	/** Data z początku nazwy katalogu: [rok, miesiąc albo 0, tytuł bez daty] albo null. */
	public static function parse_dated( $name ) {
		if ( preg_match( '/^(20\d\d)(?:[-_.](\d{1,2})(?:[-_.](\d{1,2}))?)?[\s._-]+(.*\S.*)$/u', $name, $m ) && ( '' === $m[2] || ( (int) $m[2] >= 1 && (int) $m[2] <= 12 ) ) ) {
			return array( (int) $m[1], '' === $m[2] ? 0 : (int) $m[2], trim( $m[4] ) );
		}
		return null;
	}

	/** Katalog wydarzenia, którego okres wynika z daty w nazwie (albo z katalogu roku, w którym leży). */
	private static function add_dated_event( &$groups, $drel, $period ) {
		$d     = self::parse_dated( basename( $drel ) );
		$title = $d ? $d[2] : null;
		if ( $d && ! $period ) {
			if ( 'years' === self::$mode ) {
				$period = array( 'label' => (string) $d[0], 'key' => (string) $d[0], 'sort' => $d[0] * 100 + $d[1] );
			} elseif ( $d[1] ) {
				$label  = self::MONTH_NAMES[ $d[1] - 1 ] . ' ' . $d[0];
				$period = array( 'label' => $label, 'key' => self::slug( $label ), 'sort' => $d[0] * 100 + $d[1] );
			} else {
				$period = array( 'label' => (string) $d[0], 'key' => (string) $d[0], 'sort' => $d[0] * 100 );
			}
		}
		self::add_dir_event( $groups, $drel, $period, $title );
	}

	/** Luźne pliki w folderze → wydarzenia po nazwie przed numerem; pliki z aparatu → „Pozostałe”. */
	private static function add_loose( &$groups, $rel, $files, $period ) {
		if ( ! $files ) {
			return;
		}
		foreach ( self::group_flat( $files ) as $key => $fs ) {
			$paths = array_map( function ( $f ) use ( $rel ) { return $rel . '/' . $f; }, $fs );
			$title = '' === $key ? '' : self::clean_title( $key );
			if ( '' === $key ) {
				self::issue( sprintf( '%d zdjęć w „%s” ma nazwy z aparatu (DSC/IMG) i trafia do grupy „Pozostałe”.', count( $fs ), $rel ) );
				self::problem( 'loose', $rel, sprintf( '%d zdjęć bez nazwy wydarzenia (DSC/IMG) leży luzem w „%s”. Przenieś je do katalogu wydarzenia.', count( $fs ), $rel ), $paths );
			}
			$g = self::make_group( '' === $title ? 'Pozostałe' : $title, $paths, $period, null, $rel, '' === $key ? '*' : $key );
			if ( $g ) {
				$g['auto']  = 1;
				$g['other'] = '' === $key ? 1 : 0;
				$g['src']   = $rel . '/' . ( '' === $key ? '*' : $key . '-NN' );
				$groups[]   = $g;
			}
		}
	}

	private static function add_dir_event( &$groups, $drel, $period, $title = null ) {
		$files = self::walk( $drel );
		if ( ! $files ) {
			$st  = FP_Settings::structure();
			$abs = self::$root . '/' . $drel;
			if ( ! empty( $st['auto_archive'] ) && ! array_diff( (array) @scandir( $abs ), array( '.', '..' ) ) && @rmdir( $abs ) ) { // phpcs:ignore
				self::issue( sprintf( 'Usunięto pusty katalog „%s” (porządkowanie automatyczne).', $drel ) );
				return;
			}
			self::issue( sprintf( 'Pusty folder „%s” został pominięty.', $drel ) );
			self::problem( 'empty', $drel, sprintf( 'Pusty katalog „%s” (nie jest pokazywany).', $drel ) );
			return;
		}
		list( $sub ) = self::ls( self::$root . '/' . $drel );
		if ( $sub ) {
			self::issue( sprintf( 'Podfoldery w „%s” są pokazane jako jedno wydarzenie.', $drel ) );
		}
		if ( ! $period && in_array( self::$mode, array( 'months', 'years', 'dated' ), true ) ) {
			$em = FP_Meta::get( $drel );
			if ( empty( $em['date'] ) && empty( $em['archive'] ) ) {
				$st = FP_Settings::structure();
				if ( ! empty( $st['auto_archive'] ) ) {
					FP_Meta::set( $drel, array( 'archive' => true ) );
				} elseif ( 'archive' === $st['date_policy'] ) {
					$period = self::archive_period();
				} elseif ( 'today' === $st['date_policy'] ) {
					$t      = (int) @filemtime( self::$root . '/' . $drel ); // phpcs:ignore
					$period = self::period_from_date( (int) gmdate( 'Y', $t ), (int) gmdate( 'n', $t ), null );
				} else {
					$where = array( 'months' => 'leży poza katalogiem miesiąca', 'years' => 'leży poza katalogiem roku', 'dated' => 'nazwa nie zaczyna się od daty, np. „2026-05-12 Nazwa”' );
					self::problem( 'no_date', $drel, sprintf( 'Wydarzenie „%s” nie ma daty (%s).', basename( $drel ), $where[ self::$mode ] ) );
				}
			}
		}
		$g = self::make_group( self::clean_title( null === $title ? basename( $drel ) : $title ), $files, $period, $drel );
		if ( $g ) {
			$groups[] = $g;
		}
	}

	/**
	 * @param string|null $dir  katalog wydarzenia (metadane w jego _portfolio.json)
	 * @param string|null $vdir katalog nadrzędny grupy luzem (metadane w jego _portfolio.json → groups[$vkey])
	 */
	private static function make_group( $title, $paths, $period, $dir, $vdir = null, $vkey = null ) {
		$info  = $dir ? self::read_info( $dir ) : array();
		$mdir  = $dir ? $dir : (string) $vdir;
		$pmeta = FP_Meta::get( $mdir );
		$gmeta = $dir ? $pmeta : ( isset( $pmeta['groups'][ $vkey ] ) ? (array) $pmeta['groups'][ $vkey ] : array() );
		$auth  = isset( $pmeta['authors'] ) ? (array) $pmeta['authors'] : array();
		$stars = array_flip( (array) ( isset( $pmeta['stars'] ) ? $pmeta['stars'] : array() ) );
		$def_a = ! empty( $gmeta['author'] ) ? (string) $gmeta['author'] : ( isset( $info['author'] ) ? $info['author'] : '' );
		$photos = array();
		foreach ( $paths as $rel ) {
			$p = self::photo( $rel );
			if ( ! $p ) {
				continue;
			}
			$k       = substr( $rel, strlen( $mdir ) + 1 );
			$p['a']  = isset( $auth[ $k ] ) ? (string) $auth[ $k ] : ( '' !== $p['a0'] ? $p['a0'] : $def_a );
			$p['ax'] = self::NO_AUTHOR === $p['a'] ? 1 : 0; // podpis usunięty ręcznie: nie wraca z EXIF ani z OCR
			if ( $p['ax'] ) {
				$p['a'] = '';
			}
			$p['st'] = isset( $stars[ $k ] ) ? 1 : 0;
			$photos[] = $p;
		}
		if ( ! $photos ) {
			return null;
		}
		if ( ! empty( $gmeta['title'] ) ) {
			$title = (string) $gmeta['title'];
		} elseif ( ! empty( $info['title'] ) ) {
			$title = $info['title'];
		}
		$date = ! empty( $gmeta['date'] ) ? (string) $gmeta['date'] : ( isset( $info['date'] ) ? $info['date'] : '' );
		if ( '' !== $date && preg_match( '/^(20\d\d)-(\d{2})(?:-(\d{2}))?/', $date, $d ) ) {
			$period = self::period_from_date( (int) $d[1], max( 1, min( 12, (int) $d[2] ) ), $period );
		}
		if ( ! empty( $gmeta['archive'] ) ) {
			$period = self::archive_period();
		}

		// kolejność zdjęć
		$order = isset( $gmeta['order'] ) ? (string) $gmeta['order'] : 'auto';
		if ( 'manual' === $order && ! empty( $gmeta['manual'] ) ) {
			$pos = array_flip( (array) $gmeta['manual'] );
			usort( $photos, function ( $a, $b ) use ( $pos, $mdir ) {
				$ka = substr( $a['f'], strlen( $mdir ) + 1 );
				$kb = substr( $b['f'], strlen( $mdir ) + 1 );
				$pa = isset( $pos[ $ka ] ) ? $pos[ $ka ] : PHP_INT_MAX;
				$pb = isset( $pos[ $kb ] ) ? $pos[ $kb ] : PHP_INT_MAX;
				return $pa === $pb ? strnatcasecmp( $a['f'], $b['f'] ) : ( $pa < $pb ? -1 : 1 );
			} );
		} elseif ( 'date' === $order ) {
			usort( $photos, function ( $a, $b ) {
				return $a['d'] === $b['d'] ? strnatcasecmp( $a['f'], $b['f'] ) : ( $a['d'] < $b['d'] ? -1 : 1 );
			} );
		}

		// okładki: do 5 wybranych, w wybranej kolejności; dawny _okladka.jpg jako zapas
		$covers = array();
		foreach ( array_slice( (array) ( isset( $gmeta['covers'] ) ? $gmeta['covers'] : array() ), 0, 5 ) as $cf ) {
			foreach ( $photos as $ph ) {
				if ( $ph['f'] === $mdir . '/' . $cf ) {
					$covers[] = $ph;
					break;
				}
			}
		}
		if ( ! $covers && $dir ) {
			$legacy = self::find_cover( $dir );
			if ( $legacy ) {
				$covers[] = $legacy;
			}
		}

		$slug = self::slug( $title );
		$g    = array(
			'title'    => $title,
			'slug'     => '' === $slug ? 'galeria' : $slug,
			'key'      => ( $period ? $period['key'] . '/' : '' ) . ( '' === $slug ? 'galeria' : $slug ),
			'period'   => $period ? $period['label'] : '',
			'pkey'     => $period ? $period['key'] : '',
			'sort'     => $period ? $period['sort'] : 0,
			'subtitle' => isset( $gmeta['subtitle'] ) ? (string) $gmeta['subtitle'] : '',
			'desc'     => ! empty( $gmeta['description'] ) ? (string) $gmeta['description'] : ( isset( $info['desc'] ) ? $info['desc'] : '' ),
			'dir'      => (string) $dir,
			'mdir'     => $mdir,
			'mkey'     => $dir ? '' : (string) $vkey,
			'src'      => $dir ? $dir . '/' : '',
			'auto'     => 0,
			'other'    => 0,
			'hidden'   => ! empty( $gmeta['hidden'] ) ? 1 : 0,
			'order'    => $order,
			'rel'      => null,
			'photos'   => $photos,
		);
		if ( $covers ) {
			$g['covers']    = $covers;
			$g['cover']     = $covers[0];
			$g['cover_set'] = ! empty( $gmeta['covers'] ) ? 1 : 0;
		}
		return $g;
	}

	private static function period_from_date( $y, $m, $period ) {
		if ( 'numbered' === self::$mode || 'years' === self::$mode ) {
			return array( 'label' => (string) $y, 'key' => (string) $y, 'sort' => $y * 100 + ( $period ? $period['sort'] % 100 : 0 ) );
		}
		$label = self::MONTH_NAMES[ $m - 1 ] . ' ' . $y;
		return array( 'label' => $label, 'key' => self::slug( $label ), 'sort' => $y * 100 + $m );
	}

	private static function archive_period() {
		return array( 'label' => 'Archiwum', 'key' => 'archiwum', 'sort' => 1 );
	}

	private static function photo( $rel ) {
		$abs = self::$root . '/' . $rel;
		$m   = @filemtime( $abs ); // phpcs:ignore
		$s   = @filesize( $abs );  // phpcs:ignore
		if ( ! $m ) {
			return null;
		}
		if ( isset( self::$prev[ $rel ]['a0'] ) && self::$prev[ $rel ]['m'] === $m && self::$prev[ $rel ]['s'] === $s ) {
			return self::$prev[ $rel ];
		}
		$info = @getimagesize( $abs ); // phpcs:ignore
		if ( ! $info || $info[0] < 1 ) {
			self::issue( sprintf( 'Plik „%s” nie jest poprawnym obrazem i został pominięty.', $rel ) );
			return null;
		}
		$w      = (int) $info[0];
		$h      = (int) $info[1];
		$artist = '';
		$taken  = 0;
		if ( IMAGETYPE_JPEG === $info[2] && function_exists( 'exif_read_data' ) ) {
			$ex = @exif_read_data( $abs, 'IFD0' ); // phpcs:ignore
			if ( is_array( $ex ) ) {
				$artist = isset( $ex['Artist'] ) ? (string) $ex['Artist'] : '';
				if ( ! empty( $ex['DateTimeOriginal'] ) && preg_match( '/^(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2})/', $ex['DateTimeOriginal'], $dt ) ) {
					$taken = (int) gmmktime( (int) $dt[4], (int) $dt[5], 0, (int) $dt[2], (int) $dt[3], (int) $dt[1] );
				}
				$o      = isset( $ex['Orientation'] ) ? (int) $ex['Orientation'] : 1;
				if ( $o >= 5 && $o <= 8 ) {
					list( $w, $h ) = array( $h, $w );
				}
			}
		}
		if ( '' !== $artist && ! wp_check_invalid_utf8( $artist ) ) {
			$artist = mb_convert_encoding( $artist, 'UTF-8', 'Windows-1250' );
		}
		$a0 = self::author_from( basename( $rel ), $artist );
		return array(
			'id' => substr( md5( $rel . '|' . $m . '|' . $s ), 0, 12 ),
			'f'  => $rel,
			'w'  => $w,
			'h'  => $h,
			'a0' => $a0,   // autor z nazwy pliku / EXIF
			'a'  => $a0,   // autor po uwzględnieniu _portfolio.json
			'd'  => $taken ? $taken : $m,
			'm'  => $m,
			's'  => $s,
		);
	}

	private static function find_cover( $dir ) {
		foreach ( (array) @scandir( self::$root . '/' . $dir ) as $f ) { // phpcs:ignore
			if ( preg_match( '/^_(okladka|okładka|cover)\.(jpe?g|png|webp)$/iu', $f ) ) {
				return self::photo( $dir . '/' . $f );
			}
		}
		return null;
	}

	private static function read_info( $dir ) {
		$file = self::$root . '/' . $dir . '/_info.txt';
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$map = array(
			'tytul' => 'title', 'title' => 'title', 'nazwa' => 'title',
			'data' => 'date', 'date' => 'date',
			'opis' => 'desc', 'description' => 'desc',
			'autor' => 'author', 'autorzy' => 'author', 'author' => 'author', 'fot' => 'author',
		);
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) file_get_contents( $file ) ) as $line ) { // phpcs:ignore
			if ( preg_match( '/^\s*([\p{L}]+)\s*[:=]\s*(.+?)\s*$/u', $line, $m ) ) {
				$k = self::slug( $m[1] );
				if ( isset( $map[ $k ] ) ) {
					$out[ $map[ $k ] ] = sanitize_text_field( $m[2] );
				}
			}
		}
		return $out;
	}

	/* ---------- powiązania i porządek ---------- */

	/** Wydarzenie o tej samej nazwie w innej kategorii (np. koncert „COŚ” ↔ fotorelacja „COŚ”). */
	private static function relate( &$cats ) {
		$idx = array();
		foreach ( $cats as $ci => $c ) {
			foreach ( $c['groups'] as $gi => $g ) {
				if ( empty( $g['other'] ) ) {
					$idx[ $g['slug'] ][] = array( $ci, $gi );
				}
			}
		}
		foreach ( $cats as $ci => &$c ) {
			foreach ( $c['groups'] as $gi => &$g ) {
				if ( ! empty( $g['other'] ) ) {
					continue;
				}
				$slug = $g['slug'];
				// „COŚK” w koncertach = koncert z wydarzenia „COŚ”
				if ( ! empty( $g['auto'] ) && preg_match( '/^(.+\S)K$/u', $g['title'], $m ) && isset( $idx[ self::slug( $m[1] ) ] ) ) {
					$g['title'] = $m[1];
					$g['slug']  = self::slug( $m[1] );
					$g['key']   = ( $g['pkey'] ? $g['pkey'] . '/' : '' ) . $g['slug'];
					$slug       = $g['slug'];
				}
				if ( empty( $idx[ $slug ] ) ) {
					continue;
				}
				foreach ( $idx[ $slug ] as $hit ) {
					if ( $hit[0] === $ci ) {
						continue;
					}
					$other    = $cats[ $hit[0] ]['groups'][ $hit[1] ];
					$g['rel'] = array( $cats[ $hit[0] ]['slug'], $other['key'], $cats[ $hit[0] ]['title'] );
					if ( '' === $g['period'] && '' !== $other['period'] ) {
						$g['period'] = $other['period'];
						$g['sort']   = $other['sort'];
					}
					break;
				}
			}
			unset( $g );
		}
		unset( $c );
		// powiązania w drugą stronę po ewentualnej zmianie kluczy
		foreach ( $cats as $ci => &$c ) {
			foreach ( $c['groups'] as &$g ) {
				if ( $g['rel'] ) {
					foreach ( $cats as $c2 ) {
						if ( $c2['slug'] === $g['rel'][0] ) {
							foreach ( $c2['groups'] as $g2 ) {
								if ( $g2['slug'] === self::slug( $g['title'] ) && empty( $g2['other'] ) ) {
									$g['rel'][1] = $g2['key'];
								}
							}
						}
					}
				}
			}
			unset( $g );
		}
		unset( $c );
	}

	private static function sort_groups( &$groups ) {
		usort( $groups, function ( $a, $b ) {
			if ( $a['other'] !== $b['other'] ) {
				return $a['other'] ? 1 : -1;
			}
			if ( $a['sort'] !== $b['sort'] ) {
				return $a['sort'] > $b['sort'] ? -1 : 1;
			}
			return strnatcasecmp( $a['title'], $b['title'] );
		} );
	}

	private static function unique_keys( &$groups ) {
		$seen = array();
		foreach ( $groups as &$g ) {
			$k = $g['key'];
			$i = 2;
			while ( isset( $seen[ $k ] ) ) {
				$k = $g['key'] . '-' . $i++;
			}
			$g['key']    = $k;
			$seen[ $k ] = 1;
		}
		unset( $g );
	}

	/* ---------- reguły nazw ---------- */

	public static function detect_mode( $abs ) {
		list( $dirs ) = self::ls( $abs );
		if ( ! $dirs ) {
			return 'events';
		}
		$months = 0;
		$nums   = 0;
		foreach ( $dirs as $d ) {
			list( $mon ) = self::parse_month( $d, 2025 );
			if ( $mon ) {
				$months++;
			} elseif ( preg_match( '/^(\d{1,2}[\s._-]|20\d\d$)/', $d ) ) {
				$nums++;
			}
		}
		$half = count( $dirs ) / 2;
		if ( $months && $months >= $half ) {
			return 'months';
		}
		if ( $nums && $nums >= $half ) {
			return 'numbered';
		}
		return 'events';
	}

	/**
	 * Miesiąc z nazwy katalogu: „2026-04 Kwiecień”, „KWIECIEŃ 2026”, „04 Kwiecień” albo „KWIECIEŃ”.
	 * Bez roku w nazwie rok daje $year (domyślny rok zdjęć) i ustawienie „rok zaczyna się w” (season_start):
	 * miesiące od niego do grudnia → $year, wcześniejsze → $year + 1.
	 *
	 * @param int|null $year_from_parent rok z katalogu nadrzędnego („2026/04 Kwiecień”), traktowany jak rok w nazwie
	 * @return array [miesiąc 1-12 albo 0, rok, czy rok był podany]
	 */
	public static function parse_month( $name, $year, $year_from_parent = null ) {
		if ( preg_match( '/^(20\d\d)[-_. ](\d{1,2})(?!\d)/', $name, $m ) && (int) $m[2] >= 1 && (int) $m[2] <= 12 ) {
			return array( (int) $m[2], (int) $m[1], true );
		}
		$s   = self::slug( $name );
		$mon = 0;
		if ( preg_match( '/^(\d{1,2})(?:-(.*))?$/', $s, $nm ) && (int) $nm[1] >= 1 && (int) $nm[1] <= 12 && ( empty( $nm[2] ) || self::month_slug_at( $nm[2] ) ) ) {
			$mon = (int) $nm[1]; // „04 Kwiecień”, „04”
		} else {
			$mon = self::month_slug_at( $s );
		}
		if ( ! $mon ) {
			return array( 0, 0, false );
		}
		if ( preg_match( '/(20\d\d)/', $name, $y ) ) {
			return array( $mon, (int) $y[1], true );
		}
		if ( $year_from_parent ) {
			return array( $mon, (int) $year_from_parent, true );
		}
		$start = max( 1, min( 12, (int) FP_Settings::get( 'season_start' ) ) );
		return array( $mon, $mon >= $start ? (int) $year : (int) $year + 1, false );
	}

	/** Numer miesiąca, jeśli tekst (slug) zaczyna się od jego nazwy, albo 0. */
	private static function month_slug_at( $s ) {
		foreach ( self::MONTH_SLUGS as $i => $ms ) {
			if ( 0 === strpos( $s, $ms ) && ( strlen( $s ) === strlen( $ms ) || '-' === $s[ strlen( $ms ) ] ) ) {
				return $i + 1;
			}
		}
		return 0;
	}

	/** Opis domyślnego roku do komunikatów: „rok 2025” albo „sezon od października 2025”. */
	public static function year_rule() {
		$start = max( 1, min( 12, (int) FP_Settings::get( 'season_start' ) ) );
		$y     = (int) FP_Settings::get( 'academic_year' );
		return 1 === $start ? 'domyślny rok ' . $y : 'domyślny rok ' . $y . '/' . sprintf( '%02d', ( $y + 1 ) % 100 ) . ' od ' . self::MONTH_GEN[ $start - 1 ];
	}

	/** Tytuł kategorii z nazwy katalogu: „SESJE_” → „Sesje”, krótkie skróty zostają („DKF”). Własny tytuł ustawia Struktura albo Eksplorator. */
	public static function default_cat_title( $dir ) {
		$clean = trim( $dir, "_- \t" );
		$up    = mb_strtoupper( $clean );
		if ( $up === $clean && mb_strlen( $clean ) > 4 ) {
			return self::mb_ucfirst( mb_strtolower( $clean ) );
		}
		return $clean;
	}

	public static function group_flat( $files ) {
		$groups = array();
		foreach ( $files as $f ) {
			$base = self::clean_photo_name( $f );
			if ( preg_match( '/^(\d+[-_])?(DSC|IMG|_?B3A|_?MG_|P\d{7})|^[0-9A-F]{8}-|^\d+_/i', $base ) ) {
				$key = '';
			} elseif ( preg_match( '/^(.*?\p{L}.*?)[-\s]+\d{1,3}$/u', $base, $m ) ) {
				$key = trim( $m[1] );
			} elseif ( preg_match( '/\p{L}{3,}/u', $base ) ) {
				$key = trim( $base );
			} else {
				$key = '';
			}
			if ( '' !== $key && mb_strtolower( $key ) === $key ) {
				$key = self::mb_ucfirst( $key );
			}
			$groups[ $key ][] = $f;
		}
		return $groups;
	}

	public static function clean_title( $s ) {
		$s = preg_replace( '/\(.*?\)/u', '', $s );
		$s = preg_replace( '/^_(.+?)_/u', '„$1”', $s );
		$s = preg_replace( '/\s+\d{2}_$/u', '', $s );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( $s, " _-\t" );
	}

	public static function clean_photo_name( $f ) {
		$base = preg_replace( '/\.[^.]+$/', '', $f );
		return preg_replace( '/^Kopia\s*/iu', '', $base );
	}

	public static function author_from( $file, $artist = '' ) {
		$base = self::clean_photo_name( $file );
		if ( preg_match( '/^\s*\d\s*[._]\s*(.+?)(\s*\(\d+\))?$/u', $base, $m ) ) {
			$name = trim( str_replace( '-', ' ', $m[1] ) );
			if ( preg_match( '/\p{L}{3,}\s+\p{Lu}/u', $name ) ) {
				return $name;
			}
		}
		if ( preg_match( '/@([\p{L}\d_.]+)/u', $base, $m ) ) {
			return '@' . rtrim( $m[1], '.' );
		}
		$a = trim( preg_replace( '/\s+/u', ' ', $artist ) );
		if ( '' !== $a && ! self::ignored_artist( $a ) && ! preg_match( '/^[a-z0-9]{6,}$/', $a ) ) {
			return sanitize_text_field( $a );
		}
		return '';
	}

	/** Podpis w EXIF, który nie jest autorem (np. nazwa organizacji wpisana w aparatach), z Ustawień → Autorzy. */
	private static function ignored_artist( $a ) {
		static $list = null;
		if ( null === $list ) {
			$list = array_filter( array_map( function ( $x ) { return mb_strtolower( trim( $x ) ); }, preg_split( '/[\r\n;]+/', (string) FP_Settings::get( 'ignore_artists' ) ) ) );
		}
		return in_array( mb_strtolower( $a ), $list, true );
	}

	public static function slug( $s ) {
		$s = str_replace( array( 'ł', 'Ł' ), array( 'l', 'L' ), (string) $s );
		$s = remove_accents( $s );
		$s = strtolower( preg_replace( '/[^A-Za-z0-9]+/', '-', $s ) );
		return trim( $s, '-' );
	}

	public static function mb_ucfirst( $s ) {
		return mb_strtoupper( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 );
	}

	/* ---------- system plików ---------- */

	/**
	 * @return array [podfoldery, pliki-zdjęć] posortowane naturalnie.
	 * Pomija ukryte (.coś), foldery „__coś” (ręczne ukrycie) i okładki _okladka/_cover.
	 * Zwykłe nazwy z podkreślnikiem na początku (np. „_MG_0008.jpg” z Canona) zostają.
	 */
	public static function ls( $abs ) {
		$dirs  = array();
		$files = array();
		foreach ( (array) @scandir( $abs ) as $f ) { // phpcs:ignore
			if ( '' === $f || '.' === $f[0] ) {
				continue;
			}
			if ( is_dir( $abs . '/' . $f ) ) {
				if ( 0 !== strpos( $f, '__' ) && FP_Thumbs::CACHE !== $f ) {
					$dirs[] = $f;
				}
			} elseif ( preg_match( self::IMG_RE, $f ) && ! preg_match( '/^_(okladka|okładka|cover)\./iu', $f ) ) {
				$files[] = $f;
			}
		}
		usort( $dirs, 'strnatcasecmp' );
		usort( $files, 'strnatcasecmp' );
		return array( $dirs, self::drop_wp_variants( $files, $abs ) );
	}

	/**
	 * Pomija kopie, które WordPress tworzy po dodaniu zdjęcia do Biblioteki mediów:
	 * „nazwa-150x150.jpg”, „nazwa-1024x683.jpg” i „nazwa-scaled.jpg”, o ile obok leży „nazwa.jpg”.
	 */
	public static function drop_wp_variants( $files, $dir = '' ) {
		$bases = array();
		foreach ( $files as $f ) {
			$bases[ mb_strtolower( preg_replace( '/\.[^.]+$/', '', $f ) ) ] = 1;
		}
		return array_values( array_filter( $files, function ( $f ) use ( $bases, $dir ) {
			if ( preg_match( '/^(.+)-(\d+x\d+|scaled)\.[^.]+$/i', $f, $m ) && isset( $bases[ mb_strtolower( $m[1] ) ] ) ) {
				self::$variants[ $dir . '/' . $f ] = 1;
				return false;
			}
			return true;
		} ) );
	}

	/** Wszystkie zdjęcia w folderze i podfolderach, ścieżki względne. */
	private static function walk( $rel ) {
		$out                  = array();
		list( $dirs, $files ) = self::ls( self::$root . '/' . $rel );
		foreach ( $files as $f ) {
			$out[] = $rel . '/' . $f;
		}
		foreach ( $dirs as $d ) {
			$out = array_merge( $out, self::walk( $rel . '/' . $d ) );
		}
		return $out;
	}

	private static function issue( $msg ) {
		self::$issues[] = $msg;
	}

	/** Wydarzenie, do którego należy plik (ścieżka względem źródła): [kategoria, grupa] albo null. */
	public static function group_of_file( $f ) {
		foreach ( self::categories( true ) as $c ) {
			foreach ( $c['groups'] as $g ) {
				foreach ( $g['photos'] as $p ) {
					if ( $p['f'] === $f ) {
						return array( $c, $g );
					}
				}
			}
		}
		return null;
	}

	/** Zmienia pole metadanych wydarzenia (katalog albo grupa luzem); $fn dostaje obecną wartość i zwraca nową. */
	public static function set_group_meta( $g, $field, $fn ) {
		$m   = FP_Meta::get( $g['mdir'] );
		$cur = empty( $g['mkey'] ) ? ( isset( $m[ $field ] ) ? $m[ $field ] : null ) : ( isset( $m['groups'][ $g['mkey'] ][ $field ] ) ? $m['groups'][ $g['mkey'] ][ $field ] : null );
		$new = is_callable( $fn ) ? $fn( $cur ) : $fn;
		$r   = empty( $g['mkey'] ) ? FP_Meta::set( $g['mdir'], array( $field => $new ) ) : FP_Meta::set_group( $g['mdir'], $g['mkey'], array( $field => $new ) );
		if ( ! is_wp_error( $r ) ) {
			self::scan();
		}
		return $r;
	}

	/** Autor zdjęcia zapisany w _portfolio.json katalogu wydarzenia ('' = usuń, wróć do metadanych pliku). */
	public static function set_author( $f, $author ) {
		$hit = self::group_of_file( $f );
		if ( ! $hit ) {
			return new WP_Error( 'fp_file', 'Nie znaleziono zdjęcia.' );
		}
		$mdir = $hit[1]['mdir'];
		$m    = FP_Meta::get( $mdir );
		$list = isset( $m['authors'] ) ? (array) $m['authors'] : array();
		$k    = substr( $f, strlen( $mdir ) + 1 );
		if ( '' === $author ) {
			unset( $list[ $k ] );
		} else {
			$list[ $k ] = $author;
		}
		return FP_Meta::set( $mdir, array( 'authors' => $list ) );
	}

	/**
	 * Usuwa podpis autora ze wszystkich jego zdjęć (wszystkie zapisy, które wskazują tę osobę, także aliasy i EXIF):
	 * w _portfolio.json katalogu zdjęcie dostaje „bez autora”. Zwraca liczbę zdjęć. Skan po zmianie robi wywołujący.
	 */
	public static function strip_authors( array $names ) {
		$want = array_flip( array_filter( array_map( 'strval', $names ) ) );
		$by   = array();
		foreach ( self::categories( true ) as $c ) {
			foreach ( $c['groups'] as $g ) {
				foreach ( $g['photos'] as $p ) {
					if ( '' !== $p['a'] && isset( $want[ FP_People::canonical( $p['a'] ) ] ) ) {
						$by[ $g['mdir'] ][] = substr( $p['f'], strlen( $g['mdir'] ) + 1 );
					}
				}
			}
		}
		$n = 0;
		foreach ( $by as $mdir => $keys ) {
			$m    = FP_Meta::get( $mdir );
			$list = isset( $m['authors'] ) ? (array) $m['authors'] : array();
			foreach ( $keys as $k ) {
				$list[ $k ] = self::NO_AUTHOR;
				$n++;
			}
			FP_Meta::set( $mdir, array( 'authors' => $list ) );
		}
		return $n;
	}

	/** Problem do skrzynki „Do uporządkowania” w eksploratorze (typ, ścieżka, opis, opcjonalnie lista plików). */
	private static function problem( $type, $path, $msg, $files = array() ) {
		self::$problems[] = array( 'type' => $type, 'path' => $path, 'msg' => $msg, 'files' => array_slice( (array) $files, 0, 200 ) );
	}

	/** Jednorazowo: okładki i autorzy zapisani w bazie (wersja 1.1) → _portfolio.json w katalogach. */
	public static function migrate_meta() {
		if ( ! get_option( 'fp_migrate_meta' ) || ! FP_Settings::source_path() ) {
			return;
		}
		delete_option( 'fp_migrate_meta' );
		$covers  = FP_Settings::covers();
		$authors = (array) get_option( 'fp_authors', array() );
		$cats    = self::manifest()['cats'];
		foreach ( $cats as $c ) {
			foreach ( $c['groups'] as $g ) {
				$mdir = isset( $g['mdir'] ) ? $g['mdir'] : ( $g['dir'] ? $g['dir'] : '' );
				if ( '' === $mdir ) {
					continue;
				}
				$k = $c['slug'] . '/' . $g['key'];
				if ( ! empty( $covers['groups'][ $k ] ) && 0 === strpos( $covers['groups'][ $k ], $mdir . '/' ) ) {
					$rel = substr( $covers['groups'][ $k ], strlen( $mdir ) + 1 );
					empty( $g['mkey'] ) ? FP_Meta::set( $mdir, array( 'covers' => array( $rel ) ) ) : FP_Meta::set_group( $mdir, $g['mkey'], array( 'covers' => array( $rel ) ) );
				}
				$set = array();
				foreach ( $g['photos'] as $p ) {
					if ( isset( $authors[ $p['f'] ] ) ) {
						$set[ substr( $p['f'], strlen( $mdir ) + 1 ) ] = $authors[ $p['f'] ];
					}
				}
				if ( $set ) {
					$old = FP_Meta::get( $mdir );
					FP_Meta::set( $mdir, array( 'authors' => array_merge( isset( $old['authors'] ) ? (array) $old['authors'] : array(), $set ) ) );
				}
			}
			if ( ! empty( $covers['cats'][ $c['slug'] ] ) ) {
				$list = array();
				foreach ( (array) $covers['cats'][ $c['slug'] ] as $gkey ) {
					foreach ( $c['groups'] as $g ) {
						if ( $g['key'] === $gkey ) {
							$ph     = ! empty( $g['cover'] ) ? $g['cover'] : $g['photos'][0];
							$list[] = substr( $ph['f'], strlen( $c['dir'] ) + 1 );
						}
					}
				}
				if ( $list ) {
					FP_Meta::set( $c['dir'], array( 'covers' => $list ) );
				}
			}
		}
		self::scan();
	}
}
