<?php
/**
 * Tłumaczenia słownikowe napisów, które widzą odwiedzający (filtry, sortowanie, liczniki, miesiące, lightbox).
 * Tekst źródłowy jest po polsku; słowniki w languages/{kod}.php: [polski tekst => tłumaczenie],
 * liczby mnogie pod kluczem „jeden|kilka|wiele” => [jeden, wiele], nazwy miesięcy pod „@months”.
 * Język: z Ustawień (wymuszony) albo język strony (get_locale, także Polylang/WPML); spoza listy → angielski.
 * Panel administracyjny jest na razie po polsku.
 */

defined( 'ABSPATH' ) || exit;

class FP_I18n {

	const LANGS = array(
		'pl' => 'Polski',
		'en' => 'English',
		'de' => 'Deutsch',
		'es' => 'Español',
		'fr' => 'Français',
		'it' => 'Italiano',
	);

	private static $dict = array();

	/** Język strony z ustawień WordPressa, sprowadzony do obsługiwanych. */
	public static function site_lang() {
		$code = strtolower( substr( (string) get_locale(), 0, 2 ) );
		return isset( self::LANGS[ $code ] ) ? $code : 'en';
	}

	public static function lang() {
		// ekrany panelu (bez AJAX-a widżetu Elementora) zostają po polsku, żeby nie mieszać języków w jednym zdaniu
		if ( is_admin() && ! ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 0 !== strpos( (string) $_REQUEST['action'], 'fp_' ) ) ) { // phpcs:ignore
			return 'pl';
		}
		$set = (string) FP_Settings::get( 'language' );
		return isset( self::LANGS[ $set ] ) ? $set : self::site_lang();
	}

	private static function dict( $lang ) {
		if ( ! isset( self::$dict[ $lang ] ) ) {
			$file               = FP_DIR . 'languages/' . $lang . '.php';
			self::$dict[ $lang ] = 'pl' !== $lang && is_readable( $file ) ? (array) include $file : array();
		}
		return self::$dict[ $lang ];
	}

	/** Tłumaczenie tekstu; brak w słowniku (np. własna etykieta z ustawień) = tekst bez zmian. */
	public static function t( $s ) {
		$d = self::dict( self::lang() );
		return isset( $d[ $s ] ) && is_string( $d[ $s ] ) ? $d[ $s ] : $s;
	}

	/** Forma liczby mnogiej: polskie trzy formy albo dwie formy ze słownika (francuski: 0 i 1 w liczbie pojedynczej). */
	public static function n( $n, $one, $few, $many ) {
		$lang = self::lang();
		if ( 'pl' !== $lang ) {
			$d = self::dict( $lang );
			$k = $one . '|' . $few . '|' . $many;
			if ( isset( $d[ $k ] ) && is_array( $d[ $k ] ) ) {
				$single = 'fr' === $lang ? abs( (int) $n ) <= 1 : 1 === abs( (int) $n );
				return $single ? $d[ $k ][0] : $d[ $k ][1];
			}
		}
		if ( 1 === (int) $n ) {
			return $one;
		}
		$d10  = $n % 10;
		$d100 = $n % 100;
		return ( $d10 >= 2 && $d10 <= 4 && ( $d100 < 10 || $d100 >= 20 ) ) ? $few : $many;
	}

	/** Nazwa miesiąca (1–12) w bieżącym języku. */
	public static function month( $i ) {
		$d = self::dict( self::lang() );
		return isset( $d['@months'][ $i - 1 ] ) ? $d['@months'][ $i - 1 ] : FP_Scanner::MONTH_NAMES[ $i - 1 ];
	}

	/** Polska nazwa miesiąca (jak w nazwach katalogów i okresach) → bieżący język. */
	public static function month_pl( $name ) {
		$i = array_search( $name, FP_Scanner::MONTH_NAMES, true );
		return false === $i ? self::t( $name ) : self::month( $i + 1 );
	}

	/** Etykieta okresu z manifestu („Maj 2026”, „2026”, „Archiwum”, „Pozostałe”) w bieżącym języku. */
	public static function period( $label ) {
		$label = (string) $label;
		if ( '' === $label || 'pl' === self::lang() ) {
			return $label;
		}
		if ( preg_match( '/^(\S+) (\d{4})$/u', $label, $m ) ) {
			return self::month_pl( $m[1] ) . ' ' . $m[2];
		}
		return self::t( $label );
	}
}
