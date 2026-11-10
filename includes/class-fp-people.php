<?php
/**
 * Rejestr fotografów: imię i nazwisko, aliasy (warianty z metadanych, nicki, literówki OCR),
 * Instagram / Facebook / strona i powiązane konto użytkownika WordPress.
 * Aliasy sprowadzają różne zapisy do jednej osoby (np. „@matishoter” → „Mateusz Bojarski”).
 */

defined( 'ABSPATH' ) || exit;

class FP_People {

	const OPT = 'fp_people';

	private static $all   = null;
	private static $index = null;
	private static $auto  = null;

	public static function all() {
		if ( null === self::$all ) {
			$list      = get_option( self::OPT, array() );
			self::$all = is_array( $list ) ? array_values( $list ) : array();
		}
		return self::$all;
	}

	public static function save( array $list ) {
		$clean = array();
		$seen  = array();
		foreach ( $list as $p ) {
			$name = trim( sanitize_text_field( isset( $p['name'] ) ? $p['name'] : '' ) );
			if ( '' === $name ) {
				continue;
			}
			$id = sanitize_title( $name );
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = 1;
			$aliases     = array_values( array_filter( array_map( 'trim', array_map( 'sanitize_text_field', is_array( $p['aliases'] ?? null ) ? $p['aliases'] : explode( ',', (string) ( $p['aliases'] ?? '' ) ) ) ) ) );
			$clean[]     = array(
				'id'      => $id,
				'name'    => $name,
				'aliases' => $aliases,
				'ig'      => self::handle( $p['ig'] ?? '', 'instagram.com' ),
				'fb'      => esc_url_raw( (string) ( $p['fb'] ?? '' ) ),
				'www'     => esc_url_raw( (string) ( $p['www'] ?? '' ) ),
				'user_id' => absint( $p['user_id'] ?? 0 ),
				'hidden'  => empty( $p['hidden'] ) ? 0 : 1,
			);
		}
		usort( $clean, function ( $a, $b ) { return strcoll( $a['name'], $b['name'] ); } );
		update_option( self::OPT, $clean, false );
		self::$all   = $clean;
		self::$index = null;
	}

	/** „@nick”, „nick” albo adres profilu → sama nazwa konta. */
	private static function handle( $v, $host ) {
		$v = trim( (string) $v );
		if ( preg_match( '#' . preg_quote( $host, '#' ) . '/([A-Za-z0-9._]+)#', $v, $m ) ) {
			return $m[1];
		}
		return preg_replace( '/[^A-Za-z0-9._]/', '', ltrim( $v, '@' ) );
	}

	public static function norm( $s ) {
		$s = mb_strtolower( trim( (string) $s ) );
		$s = str_replace( 'ł', 'l', $s );
		$s = remove_accents( $s );
		return trim( preg_replace( '/[^a-z0-9@._]+/', ' ', $s ) );
	}

	/** Osoba dla zapisu autora (imię i nazwisko albo alias), albo null. */
	public static function find( $name ) {
		if ( null === self::$index ) {
			self::$index = array();
			foreach ( self::all() as $p ) {
				self::$index[ self::norm( $p['name'] ) ] = $p;
				foreach ( $p['aliases'] as $a ) {
					self::$index[ self::norm( $a ) ] = $p;
				}
				if ( '' !== $p['ig'] ) {
					self::$index[ self::norm( '@' . $p['ig'] ) ] = $p;
				}
			}
		}
		$k = self::norm( $name );
		if ( isset( self::$index[ $k ] ) ) {
			return self::$index[ $k ];
		}
		// „Kacper Wozniak @woka.art” → szukaj też po samym nicku i samym imieniu i nazwisku
		if ( preg_match( '/@[\w.]+/', $name, $m ) && isset( self::$index[ self::norm( $m[0] ) ] ) ) {
			return self::$index[ self::norm( $m[0] ) ];
		}
		$plain = self::norm( preg_replace( '/@[\w.]+/', '', $name ) );
		return isset( self::$index[ $plain ] ) ? self::$index[ $plain ] : null;
	}

	/** Nazwa do wyświetlenia: z rejestru, a bez rejestru jeden zapis dla wariantów różniących się ogonkami i wielkością liter. */
	public static function canonical( $name ) {
		if ( '' === (string) $name ) {
			return '';
		}
		$p = self::find( $name );
		if ( $p ) {
			return $p['name'];
		}
		$auto = self::auto_variants();
		$k    = self::norm( $name );
		return isset( $auto[ $k ] ) ? $auto[ $k ] : (string) $name;
	}

	/**
	 * Zapisy autorów ze zdjęć pogrupowane po nazwie bez ogonków; wybierany jest wariant
	 * z największą liczbą polskich znaków, a przy remisie najczęstszy („Norbert Bożym”, nie „Norbert Bozym”).
	 */
	private static function auto_variants() {
		if ( null !== self::$auto ) {
			return self::$auto;
		}
		$count = array();
		foreach ( (array) ( FP_Scanner::manifest()['cats'] ?? array() ) as $c ) {
			foreach ( $c['groups'] as $g ) {
				foreach ( $g['photos'] as $ph ) {
					if ( '' === $ph['a'] ) {
						continue;
					}
					$k = self::norm( $ph['a'] );
					$count[ $k ][ $ph['a'] ] = isset( $count[ $k ][ $ph['a'] ] ) ? $count[ $k ][ $ph['a'] ] + 1 : 1;
				}
			}
		}
		self::$auto = array();
		foreach ( $count as $k => $vars ) {
			$best = null;
			foreach ( $vars as $v => $n ) {
				$score = array( preg_match_all( '/[^\x00-\x7F]/u', $v ), $n, preg_match( '/^\p{Lu}/u', $v ) ? 1 : 0 );
				if ( null === $best || $score > $best[1] ) {
					$best = array( $v, $score );
				}
			}
			self::$auto[ $k ] = $best[0];
		}
		return self::$auto;
	}

	/** Autorzy występujący w zdjęciach: [nazwa kanoniczna => liczba zdjęć], plus nieznane zapisy. */
	public static function from_photos() {
		$out = array();
		foreach ( FP_Scanner::categories( true ) as $c ) {
			foreach ( $c['groups'] as $g ) {
				foreach ( $g['photos'] as $p ) {
					if ( '' === $p['a'] ) {
						continue;
					}
					$n         = self::canonical( $p['a'] );
					$out[ $n ] = isset( $out[ $n ] ) ? $out[ $n ] + 1 : 1;
				}
			}
		}
		arsort( $out );
		return $out;
	}

	/** Podpowiedź konta WordPress dla osoby: imię + nazwisko, nazwa wyświetlana albo login, z tolerancją 2 znaków. */
	public static function suggest_user( $name ) {
		$want = self::norm( preg_replace( '/@[\w.]+/', '', $name ) );
		if ( '' === $want ) {
			return 0;
		}
		$best = array( 0, 99 );
		foreach ( get_users( array( 'fields' => array( 'ID', 'display_name', 'user_login' ), 'number' => 2000 ) ) as $u ) {
			$cands = array(
				self::norm( get_user_meta( $u->ID, 'first_name', true ) . ' ' . get_user_meta( $u->ID, 'last_name', true ) ),
				self::norm( $u->display_name ),
				self::norm( str_replace( array( '.', '_' ), ' ', $u->user_login ) ),
			);
			foreach ( array_filter( $cands, 'strlen' ) as $c ) {
				$d = levenshtein( $want, $c );
				if ( $d < $best[1] ) {
					$best = array( (int) $u->ID, $d );
				}
			}
		}
		return $best[1] <= 2 ? $best[0] : 0;
	}

	/** Link do profilu osoby (Instagram, potem Facebook, potem strona). */
	public static function link( $p ) {
		if ( ! $p ) {
			return '';
		}
		if ( '' !== $p['ig'] ) {
			return 'https://www.instagram.com/' . rawurlencode( $p['ig'] ) . '/';
		}
		return '' !== $p['fb'] ? $p['fb'] : $p['www'];
	}
}
