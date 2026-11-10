<?php
/**
 * Uprawnienia wtyczki: trzy poziomy przypisywane rolom w Focus Portfolio → Ustawienia → Uprawnienia.
 *
 *  fp_view  Podgląd       panel tylko do odczytu (eksplorator, statystyki, problemy)
 *  fp_edit  Redakcja      zdjęcia i katalogi w źródle, autorzy, okładki, gwiazdki, kolejność, OCR, miniatury
 *  fp_admin Administracja źródło, struktura, wygląd, uprawnienia, opróżnianie kosza
 *
 * Administrator ma zawsze wszystkie trzy (nie da się ich odebrać, żeby nikt się nie zablokował).
 */

defined( 'ABSPATH' ) || exit;

class FP_Caps {

	const VIEW  = 'fp_view';
	const EDIT  = 'fp_edit';
	const ADMIN = 'fp_admin';
	const ROLE  = 'fp_editor';

	public static function labels() {
		return array(
			self::VIEW  => array( 'Podgląd', 'Panel wtyczki tylko do odczytu: eksplorator, statystyki, lista problemów.' ),
			self::EDIT  => array( 'Redakcja', 'Wgrywanie, przenoszenie, zmiana nazw i usuwanie (do kosza) w katalogu źródłowym; autorzy, fotografowie, okładki, gwiazdki, kolejność, OCR, miniatury.' ),
			self::ADMIN => array( 'Administracja', 'Katalog źródłowy, strona portfolio, struktura katalogów, wygląd, uprawnienia, opróżnianie kosza.' ),
		);
	}

	/** Poziomy są zagnieżdżone: Administracja zawiera Redakcję, Redakcja zawiera Podgląd. */
	public static function can( $cap ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$chain = array( self::VIEW => array( self::VIEW, self::EDIT, self::ADMIN ), self::EDIT => array( self::EDIT, self::ADMIN ), self::ADMIN => array( self::ADMIN ) );
		foreach ( isset( $chain[ $cap ] ) ? $chain[ $cap ] : array( $cap ) as $c ) {
			if ( current_user_can( $c ) ) {
				return true;
			}
		}
		return false;
	}

	/** Uprawnienie dla add_menu_page: najniższe, które daje dostęp do danego ekranu. */
	public static function menu_cap( $cap ) {
		if ( self::can( $cap ) ) {
			foreach ( array( self::ADMIN, self::EDIT, self::VIEW ) as $c ) {
				if ( current_user_can( $c ) ) {
					return $c;
				}
			}
		}
		return $cap;
	}

	public static function install() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array( self::VIEW, self::EDIT, self::ADMIN ) as $c ) {
				$admin->add_cap( $c );
			}
		}
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, 'Redaktor portfolio', array( 'read' => true, 'upload_files' => true, self::VIEW => true, self::EDIT => true ) );
		}
	}

	/** Zapis macierzy rola × uprawnienie. */
	public static function save_matrix( array $matrix ) {
		foreach ( wp_roles()->roles as $slug => $role ) {
			$r = get_role( $slug );
			if ( ! $r ) {
				continue;
			}
			foreach ( array( self::VIEW, self::EDIT, self::ADMIN ) as $c ) {
				$want = ! empty( $matrix[ $slug ][ $c ] ) || 'administrator' === $slug;
				$want ? $r->add_cap( $c ) : $r->remove_cap( $c );
			}
		}
	}
}
