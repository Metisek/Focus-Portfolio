<?php
/**
 * Adresy pojedynczych zdjęć: kotwica „#foto-<nazwa pliku>” (tę samą czyta lightbox w portfolio.js)
 * i głęboki link „wydarzenie + strona + kotwica”, który otwiera dokładnie to zdjęcie.
 * Kotwice liczy PHP i wypisuje w galerii (data-fp-id), więc link z innej strony i galeria zawsze się zgadzają.
 */

defined( 'ABSPATH' ) || exit;

class FP_Links {

	/** Pierwsze 20 zdjęć wydarzenia jest na stronie 1 przy każdej liczbie na stronę (20 / 50 / 100). */
	const FIRST_PAGE = 20;

	/** Dalsze zdjęcia: link ustawia 100 na stronę i numer strony, żeby położenie było jednoznaczne. */
	const DEEP_PAGE = 100;

	/**
	 * Nazwa pliku → część kotwicy, jak photoId() w portfolio.js: bez znaków diakrytycznych (rozkład NFD),
	 * „ł”/„Ł” → „l”, każdy ciąg innych znaków niż A–Z, 0–9, „.”, „_”, „-” → „-”, bez „-” na brzegach.
	 */
	public static function slug( $filename ) {
		$n = str_replace( array( 'ł', 'Ł' ), 'l', (string) $filename );
		$d = class_exists( 'Normalizer' ) ? Normalizer::normalize( $n, Normalizer::FORM_D ) : false;
		// bez rozszerzenia intl: remove_accents daje to samo dla liter polskich i zachodnioeuropejskich
		$n = false === $d ? remove_accents( $n ) : preg_replace( '/[\x{0300}-\x{036f}]/u', '', $d );
		$n = preg_replace( '/[^A-Za-z0-9._-]+/', '-', $n );
		return trim( $n, '-' );
	}

	/** Kotwice zdjęć w kolejności galerii na jednej stronie; powtórzone nazwy dostają „-2”, „-3”. */
	public static function ids( array $files ) {
		$seen = array();
		$out  = array();
		foreach ( $files as $f ) {
			$base = 'foto-' . self::slug( basename( (string) $f ) );
			$id   = $base;
			$k    = 2;
			while ( isset( $seen[ $id ] ) ) {
				$id = $base . '-' . $k++;
			}
			$seen[ $id ] = 1;
			$out[]       = $id;
		}
		return $out;
	}

	/**
	 * Link otwierający zdjęcie $p na stronie jego wydarzenia $g (kategoria $c), w domyślnej kolejności „jak w katalogach”.
	 * Zdjęcia dalej niż na pierwszych 20 miejscach: ?na=100&strona=N, żeby trafić na właściwą stronę galerii.
	 */
	public static function photo_url( $p, $g, $c ) {
		$url   = FP_Frontend::url( $c['slug'], $g['key'] );
		$files = wp_list_pluck( $g['photos'], 'f' );
		$i     = array_search( $p['f'], $files, true );
		if ( false === $i ) {
			return $url;
		}
		$per  = $i < self::FIRST_PAGE ? self::FIRST_PAGE : self::DEEP_PAGE;
		$page = (int) floor( $i / $per );
		$ids  = self::ids( array_slice( $files, $page * $per, $per ) );
		if ( $i >= self::FIRST_PAGE ) {
			$url = add_query_arg( array( 'na' => self::DEEP_PAGE, 'strona' => $page > 0 ? $page + 1 : false ), $url );
		}
		return $url . '#' . $ids[ $i - $page * $per ];
	}
}
