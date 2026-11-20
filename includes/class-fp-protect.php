<?php
/**
 * Blokada bezpośredniego dostępu do tego, czego odwiedzający nie powinni widzieć, w publicznym katalogu źródłowym:
 *  - _portfolio.json i _kosz.json (metadane, lista usuniętych),
 *  - kosz __kosz i każdy inny katalog „__coś” (ręcznie ukryty),
 *  - ukryte kategorie (Struktura) i ukryte wydarzenia (Eksplorator): całe katalogi albo pojedyncze zdjęcia luzem.
 *
 * Działa przez pliki .htaccess (Apache, LiteSpeed) w blokach „# BEGIN/END Focus Portfolio”; reszta pliku zostaje.
 * Synchronizacja po każdym skanie: katalogi, które przestały być ukryte, tracą blokadę.
 * Na nginx .htaccess nie działa: Ustawienia pokazują regułę do wklejenia w konfiguracji serwera.
 */

defined( 'ABSPATH' ) || exit;

class FP_Protect {

	const MARKER = 'Focus Portfolio';
	const OPT    = 'fp_protected';

	public static function init() {
		add_action( 'fp_scanned', array( __CLASS__, 'sync' ) );
	}

	/** Czy serwer czyta .htaccess (Apache albo LiteSpeed). */
	public static function htaccess_works() {
		global $is_apache;
		return ! empty( $is_apache );
	}

	private static function deny( $indent = '' ) {
		return array(
			$indent . '<IfModule mod_authz_core.c>',
			$indent . "\tRequire all denied",
			$indent . '</IfModule>',
			$indent . '<IfModule !mod_authz_core.c>',
			$indent . "\tOrder deny,allow",
			$indent . "\tDeny from all",
			$indent . '</IfModule>',
		);
	}

	private static function root_lines() {
		return array_merge(
			array( '# Metadane portfolio i listy kosza nie są dla odwiedzających. Blok zarządzany przez wtyczkę Focus Portfolio.', '<FilesMatch "^(_portfolio\.json.*|_kosz\.json)$">' ),
			self::deny( "\t" ),
			array( '</FilesMatch>' )
		);
	}

	private static function dir_lines() {
		return array_merge( array( '# Ukryty katalog (kosz, „__coś”, ukryta kategoria albo wydarzenie). Blok zarządzany przez wtyczkę Focus Portfolio.' ), self::deny() );
	}

	private static function files_lines( array $names ) {
		$out = array( '# Zdjęcia ukrytych wydarzeń luzem w tym katalogu. Blok zarządzany przez wtyczkę Focus Portfolio.' );
		foreach ( $names as $n ) {
			$out[] = '<Files "' . $n . '">';
			$out   = array_merge( $out, self::deny( "\t" ) );
			$out[] = '</Files>';
		}
		return $out;
	}

	/**
	 * Co zablokować: [katalog względem źródła => true (cały katalog) | [nazwy plików]].
	 * Katalog źródłowy ('') ma zawsze regułę dla plików .json.
	 */
	public static function wanted( $root ) {
		$want = array();
		self::walk_hidden( $root, '', 0, $want );
		foreach ( FP_Scanner::manifest()['cats'] as $c ) {
			if ( FP_Settings::category( $c['dir'] )['hidden'] ) {
				$want[ $c['dir'] ] = true;
				continue;
			}
			foreach ( $c['groups'] as $g ) {
				if ( empty( $g['hidden'] ) ) {
					continue;
				}
				if ( '' !== $g['dir'] ) {
					$want[ $g['dir'] ] = true;
					continue;
				}
				// wydarzenie ze zdjęć luzem: pliki w katalogu nadrzędnym, obok widocznych
				foreach ( $g['photos'] as $p ) {
					$d = dirname( $p['f'] );
					$d = '.' === $d ? '' : $d;
					$n = basename( $p['f'] );
					if ( false !== strpbrk( $n, "\"\r\n" ) || ( isset( $want[ $d ] ) && true === $want[ $d ] ) ) {
						continue;
					}
					$want[ $d ][] = $n;
				}
			}
		}
		// katalog wewnątrz zablokowanego jest już zablokowany (.htaccess działa w podkatalogach)
		$dirs = array_keys( array_filter( $want, function ( $v ) { return true === $v; } ) );
		foreach ( array_keys( $want ) as $rel ) {
			foreach ( $dirs as $d ) {
				if ( $rel !== $d && 0 === strpos( $rel . '/', $d . '/' ) ) {
					unset( $want[ $rel ] );
					break;
				}
			}
		}
		ksort( $want );
		return $want;
	}

	/** Katalogi „__coś” (w tym kosz) w całym źródle; do ich wnętrza nie zaglądamy. */
	private static function walk_hidden( $root, $rel, $depth, array &$want ) {
		if ( $depth > 8 ) {
			return;
		}
		$abs = $root . ( '' === $rel ? '' : '/' . $rel );
		foreach ( (array) @scandir( $abs ) as $f ) { // phpcs:ignore
			if ( '' === $f || '.' === $f[0] || ! is_dir( $abs . '/' . $f ) || is_link( $abs . '/' . $f ) || FP_Thumbs::CACHE === $f ) {
				continue;
			}
			$r = ( '' === $rel ? '' : $rel . '/' ) . $f;
			if ( 0 === strpos( $f, '__' ) ) {
				$want[ $r ] = true;
			} else {
				self::walk_hidden( $root, $r, $depth + 1, $want );
			}
		}
	}

	/** Zapisuje blokady dla bieżącego źródła i zdejmuje te, które nie są już potrzebne. */
	public static function sync() {
		$root = FP_Settings::source_path();
		if ( ! $root ) {
			return;
		}
		$prev = wp_parse_args( (array) get_option( self::OPT, array() ), array( 'root' => '', 'dirs' => array() ) );
		if ( '' !== $prev['root'] && $prev['root'] !== $root ) {
			self::remove_all(); // nowe źródło: stare przestaje być portfolio, blokady znikają
			$prev['dirs'] = array();
		}
		$want = self::wanted( $root );
		$top = self::root_lines();
		if ( isset( $want[''] ) && is_array( $want[''] ) ) {
			$top = array_merge( $top, array( '' ), self::files_lines( $want[''] ) );
		}
		self::write( $root . '/.htaccess', $top );
		unset( $want[''] );
		foreach ( $want as $rel => $what ) {
			$abs = self::inside( $root, $rel );
			if ( $abs ) {
				self::write( $abs . '/.htaccess', true === $what ? self::dir_lines() : self::files_lines( $what ) );
			}
		}
		foreach ( array_diff( (array) $prev['dirs'], array_keys( $want ) ) as $rel ) {
			$abs = self::inside( $root, $rel );
			if ( $abs ) {
				self::remove( $abs . '/.htaccess' );
			}
		}
		update_option( self::OPT, array( 'root' => $root, 'dirs' => array_keys( $want ) ), false );
	}

	/** Usuwa wszystkie bloki wtyczki (zmiana źródła, odinstalowanie). */
	public static function remove_all() {
		$prev = wp_parse_args( (array) get_option( self::OPT, array() ), array( 'root' => '', 'dirs' => array() ) );
		$root = (string) $prev['root'];
		if ( '' === $root || ! is_dir( $root ) ) {
			delete_option( self::OPT );
			return;
		}
		foreach ( (array) $prev['dirs'] as $rel ) {
			$abs = self::inside( $root, $rel );
			if ( $abs ) {
				self::remove( $abs . '/.htaccess' );
			}
		}
		self::remove( $root . '/.htaccess' );
		delete_option( self::OPT );
	}

	/** Istniejący katalog w źródle (bez wychodzenia poza nie dowiązaniami) albo ''. */
	private static function inside( $root, $rel ) {
		$abs = realpath( $root . '/' . $rel );
		$abs = $abs ? wp_normalize_path( $abs ) : '';
		return $abs && is_dir( $abs ) && 0 === strpos( $abs . '/', $root . '/' ) ? $abs : '';
	}

	/** Treść pliku bez bloku wtyczki. */
	private static function strip( $txt ) {
		$re = '/^# BEGIN ' . preg_quote( self::MARKER, '/' ) . '\R.*?^# END ' . preg_quote( self::MARKER, '/' ) . '\R?/ms';
		return (string) preg_replace( $re, '', $txt );
	}

	private static function write( $file, array $lines ) {
		$old  = is_file( $file ) ? (string) @file_get_contents( $file ) : ''; // phpcs:ignore
		$rest = ltrim( self::strip( $old ), "\r\n" );
		$new  = '# BEGIN ' . self::MARKER . "\n" . implode( "\n", $lines ) . "\n# END " . self::MARKER . "\n" . ( '' === $rest ? '' : "\n" . $rest );
		if ( $new !== $old ) {
			@file_put_contents( $file, $new, LOCK_EX ); // phpcs:ignore
		}
	}

	private static function remove( $file ) {
		if ( ! is_file( $file ) ) {
			return;
		}
		$old  = (string) @file_get_contents( $file ); // phpcs:ignore
		$rest = self::strip( $old );
		if ( $rest === $old ) {
			return;
		}
		if ( '' === trim( $rest ) ) {
			wp_delete_file( $file );
		} else {
			@file_put_contents( $file, ltrim( $rest, "\r\n" ), LOCK_EX ); // phpcs:ignore
		}
	}

	/** Reguła dla nginx do wklejenia w konfiguracji serwera (kosz, katalogi „__”, pliki .json). */
	public static function nginx_rule() {
		$src = trim( (string) FP_Settings::get( 'source' ), '/' );
		$url = wp_parse_url( content_url( $src ), PHP_URL_PATH );
		return 'location ~ "^' . preg_quote( (string) $url ) . '/(.*/)?(__[^/]*/|_portfolio\.json|_kosz\.json)" { deny all; }';
	}
}
