<?php
/**
 * Zdjęcia (widżet), moduł 2: wybór zdjęć i cache. Tryby:
 *  - random  : losowo ze źródła; „worek” sprawia, że kolejne losowania biorą jeszcze niepokazane zdjęcia,
 *              a po wyczerpaniu źródła zaczyna się nowy cykl;
 *  - best    : jak random, ale tylko zdjęcia z gwiazdką i okładki (gdy ich brak: całe źródło);
 *  - events  : po jednym zdjęciu z każdego z najnowszych wydarzeń (kliknięcie prowadzi do różnych fotorelacji);
 *  - folder  : po kolei, jak w katalogach (bez losowania);
 *  - picked  : konkretne zdjęcia w wybranej kolejności.
 * Zawsze najwyżej 12 zdjęć, każde raz (twardy limit). Losowane tryby trzymają w cache tylko identyfikatory:
 * opcja fp_ph_<hash> = [ids, t, gen, bag]; odświeżanie przy każdym renderze, co 1 / 6 / 24 h albo ręcznie,
 * a nowy skan katalogów unieważnia cache (worek zostaje).
 */

defined( 'ABSPATH' ) || exit;

class FP_Photos_Picker {

	const MAX    = 12;
	const PREFIX = 'fp_ph_';

	const REFRESH = array( 'render' => 0, '1h' => HOUR_IN_SECONDS, '6h' => 6 * HOUR_IN_SECONDS, '24h' => DAY_IN_SECONDS, 'manual' => -1 );

	/** Klucz stanu: to samo źródło i ten sam tryb = wspólny cache dla wszystkich widżetów. */
	public static function key( array $s ) {
		$k = array();
		foreach ( array( 'mode', 'source', 'cats', 'folders', 'from', 'to', 'stars', 'orient', 'ex_authors', 'ex_events', 'refresh', 'bag' ) as $f ) {
			$k[ $f ] = is_array( $s[ $f ] ) ? array_values( array_unique( array_map( 'strval', $s[ $f ] ) ) ) : (string) $s[ $f ];
			if ( is_array( $k[ $f ] ) ) {
				sort( $k[ $f ] );
			}
		}
		return self::PREFIX . substr( md5( wp_json_encode( $k ) ), 0, 16 );
	}

	/** Ile zdjęć jest widocznych najwyżej (największa liczba z komputera, tabletu i telefonu, do 12). */
	public static function visible( array $s ) {
		return min( self::MAX, max( $s['rows'] * $s['per_row'], $s['rows_t'] * $s['per_row_t'], $s['rows_m'] * $s['per_row_m'] ) );
	}

	/** Czy tryb losuje (ma cache i odświeżanie). */
	public static function is_random( array $s ) {
		return in_array( $s['mode'], array( 'random', 'best', 'events' ), true );
	}

	/** @return array [[zdjęcie, wydarzenie, kategoria], …] najwyżej 12, bez powtórek */
	public static function draw( array $s ) {
		if ( 'picked' === $s['mode'] ) {
			return array_values( array_slice( FP_Photos_Source::picked( (array) $s['photos'] ), 0, self::MAX ) );
		}
		$pool = FP_Photos_Source::pool( $s );
		if ( 'folder' === $s['mode'] ) {
			return array_values( array_slice( $pool, 0, self::MAX ) ); // kolejność kategorii, wydarzeń i plików jak na stronie
		}
		if ( 'best' === $s['mode'] ) {
			$best = FP_Photos_Source::best( $pool );
			$pool = $best ? $best : $pool;
		}
		if ( ! $pool ) {
			return array();
		}
		$key   = self::key( $s );
		$st    = wp_parse_args( (array) get_option( $key, array() ), array( 'ids' => array(), 't' => 0, 'gen' => 0, 'bag' => array() ) );
		$gen   = (int) FP_Scanner::manifest()['generated'];
		$valid = array_values( array_filter( (array) $st['ids'], function ( $id ) use ( $pool ) { return isset( $pool[ $id ] ); } ) );
		$need  = min( self::visible( $s ), 'events' === $s['mode'] ? count( self::events( $pool ) ) : count( $pool ) );
		if ( self::expired( $s['refresh'], (int) $st['t'] ) || $gen !== (int) $st['gen'] || count( $valid ) < $need ) {
			$valid     = 'events' === $s['mode'] ? self::pick_events( $pool, self::visible( $s ) ) : self::pick( array_keys( $pool ), $st, ! empty( $s['bag'] ) );
			$st['ids'] = $valid;
			$st['t']   = time();
			$st['gen'] = $gen;
			update_option( $key, $st, false );
		}
		$out = array();
		foreach ( array_slice( $valid, 0, self::MAX ) as $id ) {
			$out[] = $pool[ $id ];
		}
		return $out;
	}

	/** Sekundy do następnego losowania (czas życia strony w cache, np. LiteSpeed); 0 = bez ograniczenia z widżetu. */
	public static function ttl( array $s ) {
		$every = isset( self::REFRESH[ $s['refresh'] ] ) ? self::REFRESH[ $s['refresh'] ] : 0;
		if ( $every <= 0 || ! self::is_random( $s ) ) {
			return 0;
		}
		$st = (array) get_option( self::key( $s ), array() );
		return max( 60, ( isset( $st['t'] ) ? (int) $st['t'] : 0 ) + $every - time() );
	}

	private static function expired( $refresh, $t ) {
		$every = isset( self::REFRESH[ $refresh ] ) ? self::REFRESH[ $refresh ] : 0;
		if ( $every < 0 ) {
			return 0 === $t; // ręcznie: tylko pierwsze losowanie i po wyczyszczeniu cache
		}
		return 0 === $every || time() - $t >= $every;
	}

	/** Losowanie z workiem (albo bez); aktualizuje worek w $st. */
	private static function pick( array $ids, array &$st, $bag_on ) {
		$prev = (array) $st['ids'];
		if ( ! $bag_on ) {
			// bez worka: niezależne losowanie, ale (jeśli się da) bez zdjęć z poprzedniego
			$cand = count( $ids ) - count( array_intersect( $ids, $prev ) ) >= self::MAX ? array_values( array_diff( $ids, $prev ) ) : $ids;
			shuffle( $cand );
			$st['bag'] = array();
			return array_slice( $cand, 0, self::MAX );
		}
		$bag  = array_values( array_intersect( (array) $st['bag'], $ids ) );
		$left = array_values( array_diff( $ids, $bag ) );
		shuffle( $left );
		$pick = array_slice( $left, 0, self::MAX );
		if ( count( $pick ) < self::MAX ) {
			// worek wyczerpany: nowy cykl; dobierz bez powtórek w tym losowaniu i, jeśli się da, bez zdjęć z poprzedniego
			$more = array_values( array_diff( $ids, $pick, $prev ) );
			if ( count( $more ) < self::MAX - count( $pick ) ) {
				$more = array_values( array_diff( $ids, $pick ) );
			}
			shuffle( $more );
			$pick = array_merge( $pick, array_slice( $more, 0, self::MAX - count( $pick ) ) );
			$bag  = array();
		}
		$st['bag'] = array_values( array_unique( array_merge( $bag, $pick ) ) );
		return $pick;
	}

	/** Wydarzenia ze zdjęciami w źródle, od najnowszych: [id katalogu => [sort, [id zdjęcia, …]]]. */
	private static function events( array $pool ) {
		$ev = array();
		foreach ( $pool as $id => $e ) {
			$fid = FP_Frontend::folder_id( $e[1] );
			if ( ! isset( $ev[ $fid ] ) ) {
				$ev[ $fid ] = array( (int) $e[1]['sort'], array(), count( $ev ) );
			}
			$ev[ $fid ][1][] = $id;
		}
		uasort( $ev, function ( $a, $b ) { return $b[0] !== $a[0] ? $b[0] - $a[0] : $a[2] - $b[2]; } ); // przy remisie kolejność jak w portfolio
		return $ev;
	}

	/** Po jednym losowym zdjęciu z każdego z $n najnowszych wydarzeń. */
	private static function pick_events( array $pool, $n ) {
		$out = array();
		foreach ( array_slice( self::events( $pool ), 0, min( self::MAX, $n ) ) as $e ) {
			$out[] = $e[1][ array_rand( $e[1] ) ];
		}
		return $out;
	}

	/** Usuwa cache i worki wszystkich widżetów (nowe losowanie przy następnym wyświetleniu). */
	public static function clear() {
		global $wpdb;
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $names as $n ) {
			delete_option( $n ); // przez delete_option, żeby wyczyścić też cache obiektów
		}
		return count( (array) $names );
	}
}
