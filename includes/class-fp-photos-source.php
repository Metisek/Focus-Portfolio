<?php
/**
 * Zdjęcia (widżet), moduł 1: źródło. Zbiera zdjęcia, z których wolno losować: cała galeria (rekursywnie)
 * albo wybrane kategorie lub katalogi, okres od–do, tylko z gwiazdką, orientacja, wykluczeni autorzy i wydarzenia.
 * Zawsze pomija ukryte kategorie i wydarzenia oraz zdjęcia bez gotowej miniatury.
 */

defined( 'ABSPATH' ) || exit;

class FP_Photos_Source {

	/** @return array [id zdjęcia => [zdjęcie, wydarzenie, kategoria]] */
	public static function pool( array $s ) {
		$out     = array();
		$from    = self::ym( $s['from'], false );
		$to      = self::ym( $s['to'], true );
		$cats    = array_flip( (array) $s['cats'] );
		$folders = (array) $s['folders'];
		$ex_ev   = array_flip( (array) $s['ex_events'] );
		$ex_au   = array_flip( (array) $s['ex_authors'] );
		foreach ( FP_Scanner::categories() as $c ) { // bez ukrytych kategorii i wydarzeń
			if ( 'cats' === $s['source'] && ! isset( $cats[ $c['dir'] ] ) ) {
				continue;
			}
			$whole = in_array( $c['dir'] . '/*', $folders, true );
			foreach ( $c['groups'] as $g ) {
				$fid = FP_Frontend::folder_id( $g );
				if ( ( 'folders' === $s['source'] && ! $whole && ! in_array( $fid, $folders, true ) ) || isset( $ex_ev[ $fid ] ) ) {
					continue;
				}
				if ( ( $from || $to ) && ! self::in_period( $g, $from, $to ) ) {
					continue;
				}
				foreach ( $g['photos'] as $p ) {
					if ( ! FP_Thumbs::has( $p['id'], 'g' ) || FP_Thumbs::failed( $p['id'] ) ) {
						continue; // miniatura jeszcze się nie zrobiła: nie pokazujemy ciężkiego oryginału
					}
					if ( ! empty( $s['stars'] ) && empty( $p['st'] ) ) {
						continue;
					}
					if ( ! self::orientation_ok( $p, $s['orient'] ) ) {
						continue;
					}
					if ( $ex_au && '' !== $p['a'] && isset( $ex_au[ FP_People::canonical( $p['a'] ) ] ) ) {
						continue;
					}
					$out[ $p['id'] ] = array( $p, $g, $c );
				}
			}
		}
		return $out;
	}

	/** Konkretne zdjęcia w podanej kolejności (pomija usunięte oraz z ukrytych kategorii i wydarzeń). */
	public static function picked( array $ids ) {
		$want  = array_flip( array_map( 'strval', $ids ) );
		$found = array();
		foreach ( FP_Scanner::categories() as $c ) {
			foreach ( $c['groups'] as $g ) {
				foreach ( $g['photos'] as $p ) {
					if ( isset( $want[ $p['id'] ] ) ) {
						$found[ $p['id'] ] = array( $p, $g, $c );
					}
				}
			}
		}
		$out = array();
		foreach ( $ids as $id ) {
			if ( isset( $found[ $id ] ) ) {
				$out[ $id ] = $found[ $id ];
			}
		}
		return $out;
	}

	/** Zdjęcia do pola „Konkretne zdjęcia”: [id => „Kategoria · Wydarzenie · plik”]. */
	public static function choices() {
		$out = array();
		foreach ( FP_Scanner::categories() as $c ) {
			foreach ( $c['groups'] as $g ) {
				foreach ( $g['photos'] as $p ) {
					$out[ $p['id'] ] = $c['title'] . ' · ' . FP_Frontend::group_title( $g, $c ) . ' · ' . basename( $p['f'] );
				}
			}
		}
		return $out;
	}

	/** Zdjęcia z gwiazdką albo wybrane na okładkę wydarzenia. */
	public static function best( array $pool ) {
		return array_filter( $pool, function ( $e ) {
			if ( ! empty( $e[0]['st'] ) ) {
				return true;
			}
			foreach ( ! empty( $e[1]['cover_set'] ) ? (array) $e[1]['covers'] : array() as $cv ) {
				if ( $cv['f'] === $e[0]['f'] ) {
					return true;
				}
			}
			return false;
		} );
	}

	/** „2026-05” / „2026” → 202605; bez miesiąca: styczeń dla „od”, grudzień dla „do”. */
	private static function ym( $v, $end ) {
		if ( ! preg_match( '/^(\d{4})(?:-(\d{1,2}))?$/', trim( (string) $v ), $m ) ) {
			return 0;
		}
		$mon = isset( $m[2] ) ? max( 1, min( 12, (int) $m[2] ) ) : ( $end ? 12 : 1 );
		return (int) $m[1] * 100 + $mon;
	}

	/** Okres wydarzenia z jego klucza sortowania (rok·100 + miesiąc); bez daty i „Archiwum” nie pasują do żadnego okresu. */
	private static function in_period( $g, $from, $to ) {
		$sort = (int) $g['sort'];
		if ( $sort < 190001 ) {
			return false;
		}
		$mon = $sort % 100;
		$ym  = (int) floor( $sort / 100 ) * 100 + ( $mon >= 1 && $mon <= 12 ? $mon : 1 ); // edycje: numer zamiast miesiąca
		return ( ! $from || $ym >= $from ) && ( ! $to || $ym <= $to );
	}

	private static function orientation_ok( $p, $orient ) {
		if ( 'any' === $orient || empty( $p['h'] ) ) {
			return true;
		}
		$r = $p['w'] / $p['h'];
		if ( 'landscape' === $orient ) {
			return $r > 1.1;
		}
		if ( 'portrait' === $orient ) {
			return $r < 0.9;
		}
		return $r >= 0.9 && $r <= 1.1;
	}
}
