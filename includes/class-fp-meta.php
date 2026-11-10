<?php
/**
 * Metadane katalogu w pliku _portfolio.json wewnątrz katalogu (przenoszą się razem z nim, także przez FTP).
 *
 * Pola katalogu (wydarzenia, miesiąca albo kategorii):
 *   title, subtitle, description   teksty na stronie
 *   date        "RRRR-MM" lub "RRRR-MM-DD": data wydarzenia albo poprawiony rok miesiąca
 *   archive     true = trafia do „Archiwum”
 *   hidden      true = niewidoczne na stronie
 *   covers      do 5 ścieżek zdjęć (względem tego katalogu), w kolejności wyświetlania
 *   order       auto | date | manual | balanced;  manual: lista ścieżek w ustalonej kolejności
 *   stars       ścieżki zdjęć oznaczonych gwiazdką („najlepsze”)
 *   authors     { ścieżka: "Imię Nazwisko" } autorzy ustawieni ręcznie lub z OCR
 *   author      domyślny autor zdjęć bez podpisu
 *   groups      { "Nazwa grupy": { ...pola wydarzenia } } dla zdjęć luzem pogrupowanych po nazwie pliku
 */

defined( 'ABSPATH' ) || exit;

class FP_Meta {

	const FILE = '_portfolio.json';

	private static $cache = array();

	public static function file( $rel ) {
		$root = FP_Settings::source_path();
		$rel  = trim( (string) $rel, '/' );
		return $root . ( '' === $rel ? '' : '/' . $rel ) . '/' . self::FILE;
	}

	public static function get( $rel ) {
		$rel = trim( (string) $rel, '/' );
		if ( ! array_key_exists( $rel, self::$cache ) ) {
			$f    = self::file( $rel );
			$data = is_readable( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : null; // phpcs:ignore
			self::$cache[ $rel ] = is_array( $data ) ? $data : array();
		}
		return self::$cache[ $rel ];
	}

	/** Scala zmiany z zapisanymi danymi; wartość null usuwa pole. Zapis atomowy (plik tymczasowy + rename). */
	public static function set( $rel, array $changes ) {
		$rel  = trim( (string) $rel, '/' );
		$data = self::get( $rel );
		foreach ( $changes as $k => $v ) {
			if ( null === $v || ( is_array( $v ) && ! $v ) || '' === $v ) {
				unset( $data[ $k ] );
			} else {
				$data[ $k ] = $v;
			}
		}
		$f = self::file( $rel );
		if ( ! is_dir( dirname( $f ) ) ) {
			return new WP_Error( 'fp_meta', 'Katalog nie istnieje.' );
		}
		if ( ! $data ) {
			if ( file_exists( $f ) ) {
				wp_delete_file( $f );
			}
			self::$cache[ $rel ] = array();
			return true;
		}
		$data['v'] = 1;
		$tmp       = $f . '.tmp' . wp_generate_password( 6, false );
		$json      = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === file_put_contents( $tmp, $json, LOCK_EX ) || ! @rename( $tmp, $f ) ) { // phpcs:ignore
			@unlink( $tmp ); // phpcs:ignore
			return new WP_Error( 'fp_meta', 'Nie udało się zapisać ' . self::FILE . ' (uprawnienia katalogu na serwerze).' );
		}
		self::$cache[ $rel ] = $data;
		return true;
	}

	/** Pole grupy luzem (zdjęcia „Nazwa-01.jpg” w katalogu miesiąca) zapisane w katalogu nadrzędnym. */
	public static function set_group( $dir, $group, array $changes ) {
		$m      = self::get( $dir );
		$groups = isset( $m['groups'] ) ? (array) $m['groups'] : array();
		$g      = isset( $groups[ $group ] ) ? (array) $groups[ $group ] : array();
		foreach ( $changes as $k => $v ) {
			if ( null === $v || ( is_array( $v ) && ! $v ) || '' === $v ) {
				unset( $g[ $k ] );
			} else {
				$g[ $k ] = $v;
			}
		}
		if ( $g ) {
			$groups[ $group ] = $g;
		} else {
			unset( $groups[ $group ] );
		}
		return self::set( $dir, array( 'groups' => $groups ) );
	}

	/** Po zmianie nazwy lub przeniesieniu pliku popraw odwołania w metadanych katalogu. */
	public static function rename_ref( $dir, $old, $new ) {
		$m = self::get( $dir );
		if ( ! $m ) {
			return;
		}
		$changed = false;
		foreach ( array( 'covers', 'stars', 'manual' ) as $k ) {
			if ( ! empty( $m[ $k ] ) ) {
				$list = array();
				foreach ( (array) $m[ $k ] as $f ) {
					if ( $f === $old ) {
						$changed = true;
						if ( null !== $new ) {
							$list[] = $new;
						}
					} else {
						$list[] = $f;
					}
				}
				$m[ $k ] = $list;
			}
		}
		if ( isset( $m['authors'][ $old ] ) ) {
			if ( null !== $new ) {
				$m['authors'][ $new ] = $m['authors'][ $old ];
			}
			unset( $m['authors'][ $old ] );
			$changed = true;
		}
		if ( $changed ) {
			self::set( $dir, array( 'covers' => $m['covers'] ?? null, 'stars' => $m['stars'] ?? null, 'manual' => $m['manual'] ?? null, 'authors' => $m['authors'] ?? null ) );
		}
	}

	public static function flush() {
		self::$cache = array();
	}
}
