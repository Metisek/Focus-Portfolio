<?php
/**
 * Operacje Eksploratora na katalogu źródłowym (AJAX „fp_fs”): listowanie, tworzenie, zmiana nazwy,
 * przenoszenie, kosz, metadane, gwiazdki, okładki, autorzy, wgrywanie, sprawdzanie nazw.
 *
 * Bezpieczeństwo: każda ścieżka jest względna wobec katalogu źródłowego i sprawdzana przez realpath();
 * nazwy przechodzą przez FP_Importer::clean_segment(); zapis wymaga „Redakcji”, trwałe usuwanie „Administracji”.
 */

defined( 'ABSPATH' ) || exit;

class FP_FS {

	const TRASH = '__kosz';

	public static function init() {
		add_action( 'wp_ajax_fp_fs', array( __CLASS__, 'ajax' ) );
	}

	/* ---------- ścieżki ---------- */

	private static function norm( $rel ) {
		$rel = trim( str_replace( '\\', '/', (string) $rel ), '/' );
		return preg_replace( '#/+#', '/', $rel );
	}

	/** Istniejąca ścieżka w źródle → bezwzględna, albo WP_Error. */
	public static function abs( $rel ) {
		$root = FP_Settings::source_path();
		$rel  = self::norm( $rel );
		if ( ! $root ) {
			return new WP_Error( 'fp_src', 'Katalog źródłowy nie istnieje.' );
		}
		if ( preg_match( '#(^|/)\.\.?(/|$)#', $rel ) ) {
			return new WP_Error( 'fp_path', 'Niepoprawna ścieżka.' );
		}
		$abs = realpath( $root . ( '' === $rel ? '' : '/' . $rel ) );
		$abs = $abs ? wp_normalize_path( $abs ) : '';
		if ( ! $abs || ( $abs !== $root && 0 !== strpos( $abs, $root . '/' ) ) ) {
			return new WP_Error( 'fp_path', 'Nie ma takiego katalogu lub pliku: ' . $rel );
		}
		return $abs;
	}

	private static function rel_of( $abs ) {
		return ltrim( substr( wp_normalize_path( $abs ), strlen( FP_Settings::source_path() ) ), '/' );
	}

	private static function is_img( $name ) {
		return (bool) preg_match( FP_Scanner::IMG_RE, $name );
	}

	/* ---------- interpretacja nazw (jak zobaczy to skaner) ---------- */

	/**
	 * Jak skaner potraktuje ścieżkę: rola (kategoria, miesiąc, rok, edycja, wydarzenie, podkatalog),
	 * okres i ewentualne ostrzeżenie. Działa także dla ścieżek, których jeszcze nie ma.
	 */
	public static function interpret( $rel ) {
		$rel   = self::norm( $rel );
		$parts = '' === $rel ? array() : explode( '/', $rel );
		$out   = array( 'role' => 'root', 'label' => 'Katalog źródłowy', 'period' => '', 'ok' => true, 'warn' => '', 'mode' => '' );
		if ( ! $parts ) {
			return $out;
		}
		if ( 0 === strpos( $parts[0], '__' ) ) {
			return array_merge( $out, array( 'role' => 'hidden', 'label' => 'Pomijany (nazwa zaczyna się od „__”)' ) );
		}
		$root = FP_Settings::source_path();
		$conf = FP_Settings::category( $parts[0] );
		$mode = 'auto' !== $conf['mode'] ? $conf['mode'] : ( is_dir( $root . '/' . $parts[0] ) ? FP_Scanner::detect_mode( $root . '/' . $parts[0] ) : 'events' );
		$out['mode'] = $mode;
		if ( 1 === count( $parts ) ) {
			return array_merge( $out, array( 'role' => 'category', 'label' => 'Kategoria „' . FP_Scanner::default_cat_title( $parts[0] ) . '” (' . self::mode_label( $mode ) . ')' ) );
		}
		$name = $parts[1];
		if ( 'months' === $mode && preg_match( '/^(20\d\d)$/', $name ) ) {
			// katalog roku z miesiącami w środku: „2026/04 Kwiecień/Wydarzenie”
			if ( 2 === count( $parts ) ) {
				return array_merge( $out, array( 'role' => 'year', 'label' => 'Rok ' . $name . ' (w środku miesiące)', 'period' => $name ) );
			}
			// jak miesiąc bezpośrednio w kategorii, ale z rokiem z katalogu nadrzędnego
			$sub         = self::interpret( $parts[0] . '/' . implode( '/', array_slice( $parts, 2 ) ) );
			list( $mon ) = FP_Scanner::parse_month( $parts[2], (int) $name, (int) $name );
			if ( $mon ) {
				$period       = FP_Scanner::MONTH_NAMES[ $mon - 1 ] . ' ' . $name;
				$sub['label'] = '' !== $sub['period'] ? str_replace( $sub['period'], $period, $sub['label'] ) : $sub['label'];
				$sub          = array_merge( $sub, array( 'period' => $period, 'ok' => true, 'warn' => '' ) );
			}
			return $sub;
		}
		if ( 'years' === $mode || 'dated' === $mode ) {
			$i    = 1;
			$year = '';
			if ( 'years' === $mode && preg_match( '/^(20\d\d)$/', $name ) ) {
				if ( 2 === count( $parts ) ) {
					return array_merge( $out, array( 'role' => 'year', 'label' => 'Rok ' . $name, 'period' => $name ) );
				}
				$year = $name;
				$i    = 2;
			}
			$d     = FP_Scanner::parse_dated( $parts[ $i ] );
			$title = FP_Scanner::clean_title( $d ? $d[2] : $parts[ $i ] );
			$per   = $year ? $year : ( $d ? ( $d[1] && 'dated' === $mode ? FP_Scanner::MONTH_NAMES[ $d[1] - 1 ] . ' ' . $d[0] : (string) $d[0] ) : '' );
			if ( count( $parts ) > $i + 1 ) {
				return array_merge( $out, array( 'role' => 'sub', 'label' => 'Podkatalog wydarzenia (zdjęcia dołączą do „' . $title . '”)', 'period' => $per ) );
			}
			return array_merge( $out, array(
				'role'   => 'event',
				'label'  => 'Wydarzenie „' . $title . '”' . ( $per ? ' · ' . $per : ' bez daty' ),
				'period' => $per,
				'ok'     => '' !== $per,
				'warn'   => '' !== $per ? '' : ( 'years' === $mode ? 'Wydarzenie leży poza katalogiem roku (np. „2026/Juwenalia”) i nie ma daty w nazwie.' : 'Nazwa nie zaczyna się od daty (np. „2026-05-12 Juwenalia” albo „2026-05 Juwenalia”).' ) . ' Trafi do „Do uporządkowania” albo do Archiwum.',
			) );
		}
		if ( 'months' === $mode ) {
			list( $mon, $yr, $explicit ) = FP_Scanner::parse_month( $name, (int) FP_Settings::get( 'academic_year' ) );
			$mm = FP_Meta::get( $parts[0] . '/' . $name );
			if ( ! empty( $mm['date'] ) && preg_match( '/^(20\d\d)-(\d{2})/', $mm['date'], $d ) ) {
				list( $mon, $yr, $explicit ) = array( (int) $d[2], (int) $d[1], true );
			}
			if ( $mon ) {
				$period = FP_Scanner::MONTH_NAMES[ $mon - 1 ] . ' ' . $yr;
				if ( 2 === count( $parts ) ) {
					return array_merge( $out, array(
						'role'   => 'month',
						'label'  => 'Miesiąc: ' . $period,
						'period' => $period,
						'ok'     => $explicit,
						'warn'   => $explicit ? '' : sprintf( 'Brak roku w nazwie: przyjęto %d (%s, Ustawienia → Daty). Bezpieczniej: „%d-%02d %s”.', $yr, FP_Scanner::year_rule(), $yr, $mon, FP_Scanner::MONTH_NAMES[ $mon - 1 ] ),
					) );
				}
				return array_merge( $out, array(
					'role'   => 3 === count( $parts ) ? 'event' : 'sub',
					'label'  => 3 === count( $parts ) ? 'Wydarzenie „' . FP_Scanner::clean_title( $parts[2] ) . '” · ' . $period : 'Podkatalog wydarzenia (zdjęcia dołączą do „' . FP_Scanner::clean_title( $parts[2] ) . '”)',
					'period' => $period,
				) );
			}
			if ( 2 === count( $parts ) ) {
				return array_merge( $out, array(
					'role'  => 'event',
					'label' => 'Wydarzenie bez daty „' . FP_Scanner::clean_title( $name ) . '”',
					'ok'    => false,
					'warn'  => 'Nazwa nie wygląda na miesiąc (np. „2026-04 Kwiecień”), więc katalog będzie wydarzeniem bez daty: trafi do „Do uporządkowania” albo do Archiwum (zależnie od Struktury). Jeśli to miesiąc, popraw nazwę.',
				) );
			}
			return array_merge( $out, array( 'role' => 'sub', 'label' => 'Podkatalog wydarzenia bez daty' ) );
		}
		if ( 'numbered' === $mode ) {
			$i    = 1;
			$year = null;
			if ( preg_match( '/^(20\d\d)$/', $parts[1] ) ) {
				$year = (int) $parts[1];
				if ( 2 === count( $parts ) ) {
					return array_merge( $out, array( 'role' => 'year', 'label' => 'Rok ' . $year, 'period' => (string) $year ) );
				}
				$i = 2;
			}
			if ( preg_match( '/^(\d{1,2})[\s._-]+(.+?)(?:\s+(\d{2})_)?$/u', $parts[ $i ], $m ) ) {
				$yr = ! empty( $m[3] ) ? 2000 + (int) $m[3] : $year;
				return array_merge( $out, array(
					'role'   => count( $parts ) === $i + 1 ? 'edition' : 'sub',
					'label'  => 'Edycja ' . (int) $m[1] . ': „' . FP_Scanner::mb_ucfirst( FP_Scanner::clean_title( $m[2] ) ) . '”' . ( $yr ? ' · ' . $yr : ' · Archiwum' ),
					'period' => $yr ? (string) $yr : 'Archiwum',
					'ok'     => (bool) $yr,
					'warn'   => $yr ? '' : 'Brak roku: edycja trafi do „Archiwum”. Dodaj rok (katalog „2026/…” albo sufiks „ 26_”) lub wpisz go w Eksploratorze.',
				) );
			}
			return array_merge( $out, array( 'role' => 'event', 'label' => 'Galeria bez numeru edycji', 'ok' => false, 'warn' => 'Nazwa nie ma numeru edycji (np. „03 Ramka”). Katalog będzie galerią bez roku.' ) );
		}
		return array_merge( $out, array(
			'role'  => 2 === count( $parts ) ? 'event' : 'sub',
			'label' => 2 === count( $parts ) ? 'Galeria „' . FP_Scanner::clean_title( $name ) . '”' : 'Podkatalog galerii',
		) );
	}

	private static function mode_label( $mode ) {
		$l = array( 'months' => 'miesiące → wydarzenia', 'numbered' => 'edycje z numerem i rokiem', 'events' => 'wydarzenia', 'years' => 'lata → wydarzenia', 'dated' => 'wydarzenia z datą w nazwie' );
		return isset( $l[ $mode ] ) ? $l[ $mode ] : $mode;
	}

	/* ---------- dane do widoku ---------- */

	/** Indeks zdjęć z manifestu: ścieżka → [zdjęcie, kategoria, grupa]. */
	private static function photo_index() {
		static $idx = null;
		if ( null === $idx ) {
			$idx = array();
			foreach ( FP_Scanner::categories( true ) as $c ) {
				foreach ( $c['groups'] as $g ) {
					foreach ( $g['photos'] as $p ) {
						$idx[ $p['f'] ] = array( $p, $c, $g );
					}
				}
			}
		}
		return $idx;
	}

	private static function photo_row( $rel, $abs ) {
		$idx = self::photo_index();
		$row = array(
			'type'  => 'file',
			'name'  => basename( $rel ),
			'path'  => $rel,
			'size'  => (int) @filesize( $abs ), // phpcs:ignore
			'mtime' => (int) @filemtime( $abs ), // phpcs:ignore
			'img'   => self::is_img( $rel ),
		);
		if ( isset( $idx[ $rel ] ) ) {
			list( $p, $c, $g ) = $idx[ $rel ];
			$k      = substr( $rel, strlen( $g['mdir'] ) + 1 );
			$covers = array_map( function ( $x ) { return $x['f']; }, isset( $g['covers'] ) && ! empty( $g['cover_set'] ) ? $g['covers'] : array() );
			$row    = array_merge( $row, array(
				'id'     => $p['id'],
				'thumb'  => FP_Thumbs::url( $p, 'g' ),
				'large'  => FP_Thumbs::url( $p, 'l' ),
				'w'      => $p['w'],
				'h'      => $p['h'],
				'author' => $p['a'],
				'auto_a' => isset( $p['a0'] ) ? $p['a0'] : '',
				'star'   => ! empty( $p['st'] ),
				'cover'  => array_search( $rel, $covers, true ),
				'taken'  => isset( $p['d'] ) ? (int) $p['d'] : 0,
				'event'  => FP_Frontend::group_title( $g, $c ),
				'gkey'   => $c['slug'] . '/' . $g['key'],
				'mkey'   => $k,
			) );
			if ( false === $row['cover'] ) {
				$row['cover'] = null;
			}
		} elseif ( $row['img'] ) {
			$row['wpcopy']  = self::is_wp_copy( $rel );
			$row['skipped'] = $row['wpcopy'] ? 'Kopia rozmiaru z Biblioteki mediów WordPressa (pomijana)' : 'Nie jest wyświetlane (sprawdź nazwę katalogu)';
			if ( $row['wpcopy'] ) {
				$row['thumb'] = self::source_file_url( $rel ); // kopie są małe, więc podgląd prosto z pliku
			}
		}
		return $row;
	}

	private static function source_file_url( $rel ) {
		return FP_Settings::source_url() . '/' . implode( '/', array_map( 'rawurlencode', explode( '/', $rel ) ) );
	}

	/** Kopie WordPressa pod ścieżką (do podglądu zbiorczego z zaznaczonym „Pokaż kopie WordPressa”). */
	private static function wp_copies( $abs, $rel ) {
		$out = array();
		foreach ( (array) @scandir( $abs ) as $f ) { // phpcs:ignore
			if ( '' === $f || '.' === $f[0] ) {
				continue;
			}
			$r = ( '' === $rel ? '' : $rel . '/' ) . $f;
			if ( is_dir( $abs . '/' . $f ) ) {
				if ( 0 !== strpos( $f, '__' ) && FP_Thumbs::CACHE !== $f ) {
					$out = array_merge( $out, self::wp_copies( $abs . '/' . $f, $r ) );
				}
			} elseif ( self::is_img( $f ) && self::is_wp_copy( $f ) ) {
				$out[] = $r;
			}
		}
		return $out;
	}

	/** Kopia rozmiaru z Biblioteki mediów („-150x150”, „-scaled”). */
	private static function is_wp_copy( $name ) {
		return (bool) preg_match( '/-(\d+x\d+|scaled)\.[^.]+$/i', $name );
	}

	/** Zdjęcia w katalogu i wszystkich podkatalogach (bez kopii WordPressa i katalogów „__”). */
	private static function count_deep( $abs ) {
		$n = 0;
		foreach ( (array) @scandir( $abs ) as $f ) { // phpcs:ignore
			if ( '' === $f || '.' === $f[0] ) {
				continue;
			}
			if ( is_dir( $abs . '/' . $f ) ) {
				if ( 0 !== strpos( $f, '__' ) && FP_Thumbs::CACHE !== $f ) {
					$n += self::count_deep( $abs . '/' . $f );
				}
			} elseif ( self::is_img( $f ) && ! self::is_wp_copy( $f ) ) {
				$n++;
			}
		}
		return $n;
	}

	private static function dir_row( $rel, $abs ) {
		$imgs = 0;
		$subs = 0;
		foreach ( (array) @scandir( $abs ) as $f ) { // phpcs:ignore
			if ( '' === $f || '.' === $f[0] ) {
				continue;
			}
			if ( is_dir( $abs . '/' . $f ) ) {
				$subs++;
			} elseif ( self::is_img( $f ) && ! self::is_wp_copy( $f ) ) {
				$imgs++;
			}
		}
		$in   = self::interpret( $rel );
		$meta = FP_Meta::get( $rel );
		return array(
			'type'    => 'dir',
			'name'    => basename( $rel ),
			'path'    => $rel,
			'mtime'   => (int) @filemtime( $abs ), // phpcs:ignore
			'images'  => $imgs,
			'total'   => $subs ? self::count_deep( $abs ) : $imgs, // razem z podkatalogami
			'subs'    => $subs,
			'role'    => $in['role'],
			'label'   => $in['label'],
			'period'  => $in['period'],
			'ok'      => $in['ok'],
			'warn'    => $in['warn'],
			'title'   => isset( $meta['title'] ) ? $meta['title'] : '',
			'hidden'  => ! empty( $meta['hidden'] ),
			'archive' => ! empty( $meta['archive'] ),
			'skip'    => 0 === strpos( basename( $rel ), '__' ),
		);
	}

	/**
	 * @param bool $copies pokaż też kopie rozmiarów z Biblioteki mediów (domyślnie ukryte; są pomijane na stronie)
	 */
	public static function listing( $rel, $flat = false, $year = '', $copies = false ) {
		$abs = self::abs( $rel );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		$rel    = self::rel_of( $abs );
		$items  = array();
		$hidden = 0;
		if ( $flat ) {
			// podgląd: wszystkie zdjęcia pod tą ścieżką (np. cały miesiąc albo rok kategorii)
			foreach ( self::photo_index() as $f => $hit ) {
				if ( '' !== $rel && 0 !== strpos( $f, $rel . '/' ) ) {
					continue;
				}
				if ( '' !== $year && ! preg_match( '/' . preg_quote( $year, '/' ) . '$/', $hit[2]['period'] ) ) {
					continue;
				}
				$items[] = self::photo_row( $f, FP_Settings::source_path() . '/' . $f );
			}
			if ( '' === $year ) {
				$list = self::wp_copies( $abs, $rel );
				if ( $copies ) {
					foreach ( $list as $f ) {
						$items[] = self::photo_row( $f, FP_Settings::source_path() . '/' . $f );
					}
				} else {
					$hidden = count( $list );
				}
			}
		} else {
			foreach ( (array) @scandir( $abs ) as $f ) { // phpcs:ignore
				if ( '' === $f || '.' === $f[0] || FP_Meta::FILE === $f || preg_match( '/^_portfolio\.json\.tmp/', $f ) ) {
					continue;
				}
				$r = ( '' === $rel ? '' : $rel . '/' ) . $f;
				if ( is_dir( $abs . '/' . $f ) ) {
					if ( '' === $rel && self::TRASH === $f ) {
						continue; // kosz ma własny widok
					}
					$items[] = self::dir_row( $r, $abs . '/' . $f );
				} elseif ( ! $copies && self::is_img( $f ) && self::is_wp_copy( $f ) ) {
					$hidden++;
				} else {
					$items[] = self::photo_row( $r, $abs . '/' . $f );
				}
			}
		}
		usort( $items, function ( $a, $b ) {
			if ( $a['type'] !== $b['type'] ) {
				return 'dir' === $a['type'] ? -1 : 1;
			}
			return strnatcasecmp( $a['name'], $b['name'] );
		} );
		// kolejność ręczna wydarzenia: zdjęcia w zapisanej kolejności
		$meta = FP_Meta::get( $rel );
		if ( ! $flat && ! empty( $meta['manual'] ) && 'manual' === ( isset( $meta['order'] ) ? $meta['order'] : '' ) ) {
			$pos = array_flip( (array) $meta['manual'] );
			usort( $items, function ( $a, $b ) use ( $pos ) {
				if ( $a['type'] !== $b['type'] ) {
					return 'dir' === $a['type'] ? -1 : 1;
				}
				$pa = isset( $pos[ $a['name'] ] ) ? $pos[ $a['name'] ] : PHP_INT_MAX;
				$pb = isset( $pos[ $b['name'] ] ) ? $pos[ $b['name'] ] : PHP_INT_MAX;
				return $pa === $pb ? strnatcasecmp( $a['name'], $b['name'] ) : ( $pa < $pb ? -1 : 1 );
			} );
		}
		$in = self::interpret( $rel );
		return array(
			'path'     => $rel,
			'items'    => $items,
			'meta'     => $meta,
			'info'     => $in,
			'url'      => self::public_url( $rel ),
			'problems' => self::problems_for( $rel ),
			'groups'   => self::loose_groups( $rel ),
			'copies'   => $hidden, // ukryte kopie WordPressa
		);
	}

	/** Problemy do „Do uporządkowania” z podglądem: kilka miniatur i liczba zdjęć, których dotyczą. */
	private static function inbox() {
		$idx = self::photo_index();
		$out = array();
		foreach ( (array) ( FP_Scanner::manifest()['problems'] ?? array() ) as $p ) {
			$files = ! empty( $p['files'] ) ? (array) $p['files'] : array();
			if ( ! $files ) {
				foreach ( $idx as $f => $hit ) {
					if ( 0 === strpos( $f, $p['path'] . '/' ) ) {
						$files[] = $f;
					}
				}
			}
			$p['count']  = count( $files );
			$p['thumbs'] = array();
			foreach ( $files as $f ) {
				if ( isset( $idx[ $f ] ) ) {
					$p['thumbs'][] = array( 'name' => basename( $f ), 'thumb' => FP_Thumbs::url( $idx[ $f ][0], 'g' ), 'large' => FP_Thumbs::url( $idx[ $f ][0], 'l' ) );
				}
				if ( count( $p['thumbs'] ) >= 8 ) {
					break;
				}
			}
			$out[] = $p;
		}
		return $out;
	}

	/** Grupy luzem w tym katalogu (zdjęcia „Nazwa-NN.jpg”): nazwa → liczba, do edycji ich metadanych. */
	private static function loose_groups( $rel ) {
		$out = array();
		foreach ( FP_Scanner::categories( true ) as $c ) {
			foreach ( $c['groups'] as $g ) {
				if ( ! empty( $g['mkey'] ) && $g['mdir'] === $rel ) {
					$out[] = array( 'key' => $g['mkey'], 'title' => FP_Frontend::group_title( $g, $c ), 'count' => count( $g['photos'] ), 'url' => FP_Frontend::url( $c['slug'], $g['key'] ) );
				}
			}
		}
		return $out;
	}

	/** Adres na stronie dla kategorii lub wydarzenia (jeśli katalog jest wyświetlany). */
	private static function public_url( $rel ) {
		if ( '' === $rel ) {
			return FP_Frontend::url();
		}
		foreach ( FP_Scanner::categories( true ) as $c ) {
			if ( $c['dir'] === $rel ) {
				return FP_Frontend::url( $c['slug'] );
			}
			foreach ( $c['groups'] as $g ) {
				if ( $g['dir'] === $rel ) {
					return FP_Frontend::url( $c['slug'], $g['key'] );
				}
			}
		}
		return '';
	}

	private static function problems_for( $rel ) {
		$out = array();
		foreach ( (array) ( FP_Scanner::manifest()['problems'] ?? array() ) as $p ) {
			if ( $p['path'] === $rel ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/** Wszystkie katalogi źródła (do okna „Przenieś do…” z wyszukiwarką). */
	public static function all_dirs() {
		$root = FP_Settings::source_path();
		$out  = array();
		if ( ! $root ) {
			return $out;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		$it->setMaxDepth( 4 );
		foreach ( $it as $f ) {
			if ( $f->isDir() ) {
				$rel = self::rel_of( $f->getPathname() );
				if ( 0 === strpos( $rel, self::TRASH ) ) {
					continue;
				}
				$out[] = array( 'path' => $rel, 'label' => self::interpret( $rel )['label'] );
			}
		}
		usort( $out, function ( $a, $b ) { return strnatcasecmp( $a['path'], $b['path'] ); } );
		return $out;
	}

	/* ---------- zmiany w plikach ---------- */

	/** Unikalna nazwa w katalogu docelowym („nazwa (2).jpg”). */
	private static function unique( $dir_abs, $name ) {
		if ( ! file_exists( $dir_abs . '/' . $name ) ) {
			return $name;
		}
		$ext  = pathinfo( $name, PATHINFO_EXTENSION );
		$base = '' === $ext ? $name : substr( $name, 0, -strlen( $ext ) - 1 );
		for ( $i = 2; $i < 1000; $i++ ) {
			$n = $base . ' (' . $i . ')' . ( '' === $ext ? '' : '.' . $ext );
			if ( ! file_exists( $dir_abs . '/' . $n ) ) {
				return $n;
			}
		}
		return $base . '-' . wp_generate_password( 4, false ) . ( '' === $ext ? '' : '.' . $ext );
	}

	/** Po zmianie ścieżki popraw odwołania (okładki, gwiazdki, kolejność, autorzy) w _portfolio.json katalogów nadrzędnych. */
	private static function fix_refs( $old_rel, $new_rel ) {
		$dirs = array();
		$p    = $old_rel;
		while ( false !== ( $pos = strrpos( $p, '/' ) ) ) {
			$p      = substr( $p, 0, $pos );
			$dirs[] = $p;
		}
		$dirs[] = '';
		foreach ( $dirs as $d ) {
			$m = FP_Meta::get( $d );
			if ( ! $m ) {
				continue;
			}
			$old_k   = '' === $d ? $old_rel : substr( $old_rel, strlen( $d ) + 1 );
			$new_k   = null;
			if ( null !== $new_rel && ( '' === $d || 0 === strpos( $new_rel, $d . '/' ) ) ) {
				$new_k = '' === $d ? $new_rel : substr( $new_rel, strlen( $d ) + 1 );
			}
			$map     = function ( $k ) use ( $old_k, $new_k ) {
				if ( $k === $old_k ) {
					return $new_k;
				}
				if ( 0 === strpos( $k, $old_k . '/' ) ) {
					return null === $new_k ? null : $new_k . substr( $k, strlen( $old_k ) );
				}
				return $k;
			};
			$changes = array();
			foreach ( array( 'covers', 'stars', 'manual' ) as $f ) {
				if ( ! empty( $m[ $f ] ) ) {
					$changes[ $f ] = array_values( array_filter( array_map( $map, (array) $m[ $f ] ), 'strlen' ) );
				}
			}
			if ( ! empty( $m['authors'] ) ) {
				$a = array();
				foreach ( (array) $m['authors'] as $k => $v ) {
					$nk = $map( (string) $k );
					if ( null !== $nk ) {
						$a[ $nk ] = $v;
					}
				}
				$changes['authors'] = $a;
			}
			if ( $changes ) {
				FP_Meta::set( $d, $changes );
			}
		}
	}

	/** Dane zdjęcia zapisane w metadanych jego wydarzenia (autor, gwiazdka), żeby przenieść je razem z plikiem. */
	private static function photo_meta( $rel ) {
		$idx = self::photo_index();
		if ( ! isset( $idx[ $rel ] ) ) {
			return array();
		}
		list( $p, $c, $g ) = $idx[ $rel ];
		$m = FP_Meta::get( $g['mdir'] );
		$k = substr( $rel, strlen( $g['mdir'] ) + 1 );
		return array(
			'author' => isset( $m['authors'][ $k ] ) ? $m['authors'][ $k ] : '',
			'star'   => in_array( $k, (array) ( isset( $m['stars'] ) ? $m['stars'] : array() ), true ),
		);
	}

	private static function apply_photo_meta( $dest_dir_rel, $name, $pm ) {
		if ( ! $pm || ( '' === $pm['author'] && ! $pm['star'] ) ) {
			return;
		}
		$m = FP_Meta::get( $dest_dir_rel );
		$c = array();
		if ( '' !== $pm['author'] ) {
			$a          = isset( $m['authors'] ) ? (array) $m['authors'] : array();
			$a[ $name ] = $pm['author'];
			$c['authors'] = $a;
		}
		if ( $pm['star'] ) {
			$c['stars'] = array_values( array_unique( array_merge( (array) ( isset( $m['stars'] ) ? $m['stars'] : array() ), array( $name ) ) ) );
		}
		FP_Meta::set( $dest_dir_rel, $c );
	}

	public static function mkdir( $parent, $name ) {
		$pabs = self::abs( $parent );
		if ( is_wp_error( $pabs ) || ! is_dir( $pabs ) ) {
			return is_wp_error( $pabs ) ? $pabs : new WP_Error( 'fp_dir', 'To nie jest katalog.' );
		}
		$name = FP_Importer::clean_segment( $name );
		if ( '' === $name || '.' === $name[0] ) {
			return new WP_Error( 'fp_name', 'Podaj nazwę katalogu.' );
		}
		if ( file_exists( $pabs . '/' . $name ) ) {
			return new WP_Error( 'fp_exists', 'Katalog o tej nazwie już istnieje.' );
		}
		if ( ! wp_mkdir_p( $pabs . '/' . $name ) ) {
			return new WP_Error( 'fp_mkdir', 'Nie udało się utworzyć katalogu (uprawnienia na serwerze).' );
		}
		return self::rel_of( $pabs . '/' . $name );
	}

	public static function rename( $rel, $name ) {
		$abs = self::abs( $rel );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		$rel  = self::rel_of( $abs );
		if ( '' === $rel ) {
			return new WP_Error( 'fp_root', 'Nie można zmienić nazwy katalogu źródłowego.' );
		}
		$name = FP_Importer::clean_segment( $name );
		if ( is_file( $abs ) ) {
			$ext  = strtolower( pathinfo( $abs, PATHINFO_EXTENSION ) );
			$name = preg_replace( '/\.[^.]*$/', '', $name ) . '.' . $ext; // rozszerzenie zostaje
		}
		if ( '' === $name || '.' === $name[0] ) {
			return new WP_Error( 'fp_name', 'Niepoprawna nazwa.' );
		}
		$dest = dirname( $abs ) . '/' . $name;
		if ( $dest === $abs ) {
			return $rel;
		}
		if ( file_exists( $dest ) && strtolower( $dest ) !== strtolower( $abs ) ) {
			return new WP_Error( 'fp_exists', 'W tym katalogu jest już „' . $name . '”.' );
		}
		if ( ! @rename( $abs, $dest ) ) { // phpcs:ignore
			return new WP_Error( 'fp_rename', 'Nie udało się zmienić nazwy (uprawnienia na serwerze).' );
		}
		$new_rel = self::rel_of( $dest );
		FP_Meta::flush();
		self::fix_refs( $rel, $new_rel );
		return $new_rel;
	}

	/** Przenosi pliki i katalogi do katalogu docelowego; autor i gwiazdka zdjęcia idą razem z nim. */
	public static function move( array $items, $dest ) {
		$dabs = self::abs( $dest );
		if ( is_wp_error( $dabs ) || ! is_dir( $dabs ) ) {
			return is_wp_error( $dabs ) ? $dabs : new WP_Error( 'fp_dir', 'Cel nie jest katalogiem.' );
		}
		$dest  = self::rel_of( $dabs );
		$moved = array();
		$errs  = array();
		foreach ( $items as $rel ) {
			$abs = self::abs( $rel );
			if ( is_wp_error( $abs ) ) {
				$errs[] = $abs->get_error_message();
				continue;
			}
			$rel = self::rel_of( $abs );
			if ( '' === $rel || $dabs === $abs || 0 === strpos( $dabs . '/', $abs . '/' ) ) {
				$errs[] = 'Nie można przenieść „' . $rel . '” do samego siebie.';
				continue;
			}
			if ( dirname( $abs ) === $dabs ) {
				continue; // już tam jest
			}
			$pm   = is_file( $abs ) ? self::photo_meta( $rel ) : array();
			$name = self::unique( $dabs, basename( $abs ) );
			if ( ! @rename( $abs, $dabs . '/' . $name ) ) { // phpcs:ignore
				$errs[] = 'Nie udało się przenieść „' . $rel . '”.';
				continue;
			}
			FP_Meta::flush();
			self::fix_refs( $rel, null );
			self::apply_photo_meta( $dest, $name, $pm );
			$moved[] = ( '' === $dest ? '' : $dest . '/' ) . $name;
		}
		return array( 'moved' => $moved, 'errors' => $errs );
	}

	/** Do kosza: __kosz/RRRRMMDD-GGMMSS/<pierwotna ścieżka>; lista usuniętych w _kosz.json tej partii. Nic nie jest kasowane. */
	public static function trash( array $items ) {
		$root  = FP_Settings::source_path();
		$stamp = gmdate( 'Ymd-His' );
		$done  = array();
		$errs  = array();
		foreach ( $items as $rel ) {
			$abs = self::abs( $rel );
			if ( is_wp_error( $abs ) ) {
				$errs[] = $abs->get_error_message();
				continue;
			}
			$rel = self::rel_of( $abs );
			if ( '' === $rel || 0 === strpos( $rel, self::TRASH ) ) {
				$errs[] = 'Tego nie można przenieść do kosza.';
				continue;
			}
			$pm = is_file( $abs ) ? self::photo_meta( $rel ) : array(); // autor i gwiazdka wracają przy przywróceniu
			$to = $root . '/' . self::TRASH . '/' . $stamp . '/' . $rel;
			wp_mkdir_p( dirname( $to ) );
			if ( ! @rename( $abs, $to ) ) { // phpcs:ignore
				$errs[] = 'Nie udało się przenieść do kosza „' . $rel . '”.';
				continue;
			}
			FP_Meta::flush();
			self::fix_refs( $rel, null );
			$done[ $rel ] = $pm;
		}
		if ( $done ) {
			$list = self::batch( $stamp );
			foreach ( $done as $rel => $pm ) {
				$list[] = array( 'orig' => $rel, 'user' => get_current_user_id(), 'pm' => $pm );
			}
			self::batch( $stamp, $list );
		}
		$done = array_keys( $done );
		return array( 'trashed' => $done, 'errors' => $errs );
	}

	/** Lista elementów partii kosza (odczyt albo zapis). */
	private static function batch( $stamp, $set = null ) {
		$f = FP_Settings::source_path() . '/' . self::TRASH . '/' . $stamp . '/_kosz.json';
		if ( null === $set ) {
			$d = is_readable( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : array(); // phpcs:ignore
			return is_array( $d ) ? $d : array();
		}
		if ( $set ) {
			file_put_contents( $f, wp_json_encode( array_values( $set ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore
		} elseif ( file_exists( $f ) ) {
			wp_delete_file( $f );
		}
		return $set;
	}

	public static function trash_list() {
		$base = FP_Settings::source_path() . '/' . self::TRASH;
		$out  = array();
		foreach ( (array) @scandir( $base ) as $stamp ) { // phpcs:ignore
			if ( ! preg_match( '/^\d{8}-\d{6}$/', $stamp ) ) {
				continue;
			}
			foreach ( self::batch( $stamp ) as $it ) {
				$abs = $base . '/' . $stamp . '/' . $it['orig'];
				if ( ! file_exists( $abs ) ) {
					continue;
				}
				$u     = ! empty( $it['user'] ) ? get_userdata( (int) $it['user'] ) : null;
				$out[] = array(
					'trash' => self::TRASH . '/' . $stamp . '/' . $it['orig'],
					'orig'  => $it['orig'],
					'when'  => (int) strtotime( substr( $stamp, 0, 4 ) . '-' . substr( $stamp, 4, 2 ) . '-' . substr( $stamp, 6, 2 ) . ' ' . substr( $stamp, 9, 2 ) . ':' . substr( $stamp, 11, 2 ) . ':' . substr( $stamp, 13, 2 ) . ' UTC' ),
					'type'  => is_dir( $abs ) ? 'dir' : 'file',
					'size'  => is_file( $abs ) ? (int) filesize( $abs ) : 0,
					'who'   => $u ? $u->display_name : '',
				);
			}
		}
		usort( $out, function ( $a, $b ) { return $b['when'] - $a['when']; } );
		return $out;
	}

	public static function restore( array $items ) {
		$root = FP_Settings::source_path();
		$done = array();
		$errs = array();
		foreach ( $items as $trel ) {
			$trel = self::norm( $trel );
			if ( ! preg_match( '#^' . self::TRASH . '/(\d{8}-\d{6})/(.+)$#', $trel, $m ) ) {
				continue;
			}
			$abs = self::abs( $trel );
			if ( is_wp_error( $abs ) ) {
				$errs[] = $abs->get_error_message();
				continue;
			}
			$dest_dir = $root . ( '.' === dirname( $m[2] ) ? '' : '/' . dirname( $m[2] ) );
			wp_mkdir_p( $dest_dir );
			$name = self::unique( $dest_dir, basename( $m[2] ) );
			$entry = null;
			foreach ( self::batch( $m[1] ) as $it ) {
				if ( $it['orig'] === $m[2] ) {
					$entry = $it;
				}
			}
			if ( @rename( $abs, $dest_dir . '/' . $name ) ) { // phpcs:ignore
				$done[] = self::rel_of( $dest_dir . '/' . $name );
				if ( ! empty( $entry['pm'] ) ) {
					FP_Meta::flush();
					self::apply_photo_meta( self::rel_of( $dest_dir ), $name, $entry['pm'] );
				}
				self::batch( $m[1], array_values( array_filter( self::batch( $m[1] ), function ( $it ) use ( $m ) { return $it['orig'] !== $m[2]; } ) ) );
			} else {
				$errs[] = 'Nie udało się przywrócić „' . $m[2] . '”.';
			}
		}
		self::cleanup_trash_dirs();
		return array( 'restored' => $done, 'errors' => $errs );
	}

	/** Trwałe usunięcie z kosza (tylko Administracja). */
	public static function purge( array $items ) {
		$n = 0;
		foreach ( $items as $trel ) {
			$trel = self::norm( $trel );
			if ( 0 !== strpos( $trel, self::TRASH . '/' ) ) {
				continue;
			}
			$abs = self::abs( $trel );
			if ( ! is_wp_error( $abs ) ) {
				self::rrm( $abs );
				$n++;
				if ( preg_match( '#^' . self::TRASH . '/(\d{8}-\d{6})/(.+)$#', $trel, $m ) ) {
					self::batch( $m[1], array_values( array_filter( self::batch( $m[1] ), function ( $it ) use ( $m ) { return $it['orig'] !== $m[2]; } ) ) );
				}
			}
		}
		self::cleanup_trash_dirs();
		return $n;
	}

	public static function empty_trash() {
		$abs = self::abs( self::TRASH );
		if ( ! is_wp_error( $abs ) ) {
			self::rrm( $abs );
		}
	}

	private static function rrm( $abs ) {
		if ( is_file( $abs ) ) {
			wp_delete_file( $abs );
			return;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $abs, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() ? @rmdir( $f->getPathname() ) : wp_delete_file( $f->getPathname() ); // phpcs:ignore
		}
		@rmdir( $abs ); // phpcs:ignore
	}

	private static function cleanup_trash_dirs() {
		$base = FP_Settings::source_path() . '/' . self::TRASH;
		if ( ! is_dir( $base ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			if ( $f->isDir() ) {
				@rmdir( $f->getPathname() ); // phpcs:ignore -- usuwa tylko puste
			}
		}
	}

	/* ---------- metadane ---------- */

	/** Dozwolone pola metadanych katalogu i ich czyszczenie. */
	public static function clean_meta( array $in ) {
		$out = array();
		foreach ( array( 'title', 'subtitle', 'author' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$out[ $k ] = sanitize_text_field( (string) $in[ $k ] );
			}
		}
		if ( array_key_exists( 'description', $in ) ) {
			$out['description'] = sanitize_textarea_field( (string) $in['description'] );
		}
		if ( array_key_exists( 'date', $in ) ) {
			$d           = (string) $in['date'];
			$out['date'] = preg_match( '/^20\d\d-\d{2}(-\d{2})?$/', $d ) ? $d : '';
		}
		foreach ( array( 'archive', 'hidden' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$out[ $k ] = ! empty( $in[ $k ] ) && 'false' !== $in[ $k ] ? true : null;
			}
		}
		if ( array_key_exists( 'order', $in ) ) {
			$out['order'] = in_array( $in['order'], array( 'date', 'manual', 'balanced' ), true ) ? $in['order'] : null;
		}
		foreach ( array( 'covers' => 5, 'manual' => 5000, 'stars' => 5000 ) as $k => $max ) {
			if ( array_key_exists( $k, $in ) ) {
				$list      = array_values( array_unique( array_filter( array_map( function ( $x ) {
					$x = str_replace( '\\', '/', (string) $x );
					return false !== strpos( $x, '..' ) ? '' : ltrim( $x, '/' );
				}, (array) $in[ $k ] ), 'strlen' ) ) );
				$out[ $k ] = array_slice( $list, 0, $max );
			}
		}
		return $out;
	}

	/* ---------- AJAX ---------- */

	public static function ajax() {
		check_ajax_referer( 'fp_admin', 'nonce' );
		$op   = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : ''; // phpcs:ignore
		$read = array( 'list', 'check', 'dirs', 'trash_list', 'inbox' );
		$adm  = array( 'purge', 'empty_trash' );
		$need = in_array( $op, $read, true ) ? FP_Caps::VIEW : ( in_array( $op, $adm, true ) ? FP_Caps::ADMIN : FP_Caps::EDIT );
		if ( ! FP_Caps::can( $need ) ) {
			wp_send_json_error( array( 'message' => 'Brak uprawnień do tej operacji.' ), 403 );
		}
		$p     = wp_unslash( $_POST ); // phpcs:ignore
		$path  = isset( $p['path'] ) ? (string) $p['path'] : '';
		$items = isset( $p['items'] ) ? array_map( 'strval', (array) $p['items'] ) : array();
		$r     = null;
		$rescan = true;
		switch ( $op ) {
			case 'list':
				$r      = self::listing( $path, ! empty( $p['flat'] ), isset( $p['year'] ) ? preg_replace( '/\D/', '', $p['year'] ) : '', ! empty( $p['copies'] ) );
				$rescan = false;
				break;
			case 'check':
				$r      = self::interpret( $path );
				$r['text'] = $r['label'] . ( $r['warn'] ? ' · Uwaga: ' . $r['warn'] : ' · Nazwa poprawna.' );
				$rescan = false;
				break;
			case 'dirs':
				$r      = array( 'dirs' => self::all_dirs(), 'people' => wp_list_pluck( FP_People::all(), 'name' ) );
				$rescan = false;
				break;
			case 'inbox':
				$r      = array( 'problems' => self::inbox() );
				$rescan = false;
				break;
			case 'trash_list':
				$r      = array( 'items' => self::trash_list() );
				$rescan = false;
				break;
			case 'mkdir':
				$r = self::mkdir( $path, isset( $p['name'] ) ? $p['name'] : '' );
				$r = is_wp_error( $r ) ? $r : array( 'path' => $r, 'info' => self::interpret( $r ) );
				break;
			case 'rename':
				$r = self::rename( $path, isset( $p['name'] ) ? $p['name'] : '' );
				$r = is_wp_error( $r ) ? $r : array( 'path' => $r );
				break;
			case 'move':
				$r = self::move( $items, isset( $p['dest'] ) ? $p['dest'] : '' );
				break;
			case 'trash':
				$r = self::trash( $items );
				break;
			case 'restore':
				$r = self::restore( $items );
				break;
			case 'purge':
				$r = array( 'purged' => self::purge( $items ) );
				break;
			case 'empty_trash':
				self::empty_trash();
				$r = array( 'ok' => 1 );
				break;
			case 'meta':
				$abs = self::abs( $path );
				if ( is_wp_error( $abs ) || ! is_dir( $abs ) ) {
					$r = is_wp_error( $abs ) ? $abs : new WP_Error( 'fp_dir', 'To nie jest katalog.' );
					break;
				}
				$fields = self::clean_meta( isset( $p['fields'] ) ? (array) $p['fields'] : array() );
				$group  = isset( $p['group'] ) ? sanitize_text_field( $p['group'] ) : '';
				$r      = '' === $group ? FP_Meta::set( self::rel_of( $abs ), $fields ) : FP_Meta::set_group( self::rel_of( $abs ), $group, $fields );
				$r      = is_wp_error( $r ) ? $r : array( 'meta' => FP_Meta::get( self::rel_of( $abs ) ) );
				break;
			case 'photo':
				$r = self::photo_op( $items, $p );
				break;
			case 'upload':
				$abs = self::abs( $path );
				if ( is_wp_error( $abs ) || ! is_dir( $abs ) || empty( $_FILES['file'] ) ) {
					$r = is_wp_error( $abs ) ? $abs : new WP_Error( 'fp_up', 'Brak pliku albo katalogu docelowego.' );
					break;
				}
				$r      = FP_Importer::import_upload( $_FILES['file'], $abs ); // phpcs:ignore
				$rescan = ! empty( $p['last'] ); // przy wielu plikach skan dopiero po ostatnim
				break;
			default:
				$r = new WP_Error( 'fp_op', 'Nieznana operacja.' );
		}
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		if ( $rescan ) {
			FP_Meta::flush();
			FP_Scanner::scan();
		}
		wp_send_json_success( $r );
	}

	/** Zmiany na zaznaczonych zdjęciach: autor, gwiazdka, okładka (w metadanych ich wydarzenia). */
	private static function photo_op( array $items, array $p ) {
		$idx    = self::photo_index();
		$action = isset( $p['action2'] ) ? sanitize_key( $p['action2'] ) : '';
		$by_dir = array();
		foreach ( $items as $rel ) {
			$rel = self::norm( $rel );
			if ( ! isset( $idx[ $rel ] ) ) {
				continue;
			}
			$g                                  = $idx[ $rel ][2];
			$key                                = $g['mdir'] . '|' . ( empty( $g['mkey'] ) ? '' : $g['mkey'] );
			$by_dir[ $key ]['g']                = $g;
			$by_dir[ $key ]['k'][]              = substr( $rel, strlen( $g['mdir'] ) + 1 );
		}
		if ( ! $by_dir ) {
			return new WP_Error( 'fp_sel', 'Zaznacz zdjęcia wyświetlane w portfolio.' );
		}
		foreach ( $by_dir as $set ) {
			$g = $set['g'];
			$m = FP_Meta::get( $g['mdir'] );
			switch ( $action ) {
				case 'author':
					$a = isset( $m['authors'] ) ? (array) $m['authors'] : array();
					$v = sanitize_text_field( isset( $p['value'] ) ? $p['value'] : '' );
					foreach ( $set['k'] as $k ) {
						if ( '' === $v ) {
							unset( $a[ $k ] );
						} else {
							$a[ $k ] = $v;
						}
					}
					FP_Meta::set( $g['mdir'], array( 'authors' => $a ) );
					break;
				case 'star':
				case 'unstar':
					$s = (array) ( isset( $m['stars'] ) ? $m['stars'] : array() );
					$s = 'star' === $action ? array_values( array_unique( array_merge( $s, $set['k'] ) ) ) : array_values( array_diff( $s, $set['k'] ) );
					FP_Meta::set( $g['mdir'], array( 'stars' => $s ) );
					break;
				case 'cover':
				case 'uncover':
					// okładki wydarzenia z katalogu albo grupy luzem; nowe dochodzą na koniec, najstarsze wypadają przy 5+
					$cur  = empty( $g['mkey'] ) ? ( isset( $m['covers'] ) ? (array) $m['covers'] : array() ) : (array) ( isset( $m['groups'][ $g['mkey'] ]['covers'] ) ? $m['groups'][ $g['mkey'] ]['covers'] : array() );
					$list = array_values( array_diff( $cur, $set['k'] ) );
					if ( 'cover' === $action ) {
						$list = array_slice( array_merge( $list, $set['k'] ), -5 );
					}
					empty( $g['mkey'] ) ? FP_Meta::set( $g['mdir'], array( 'covers' => $list ) ) : FP_Meta::set_group( $g['mdir'], $g['mkey'], array( 'covers' => $list ) );
					break;
				default:
					return new WP_Error( 'fp_op', 'Nieznana operacja.' );
			}
		}
		return array( 'ok' => 1 );
	}
}
