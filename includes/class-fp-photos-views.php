<?php
/**
 * Zdjęcia (widżet), moduł 3: rejestr widoków. Każdy widok to:
 *   'label'  => nazwa w ustawieniach,
 *   'class'  => klasa CSS pojemnika (wygląd w assets/css/photos.css albo we własnym arkuszu),
 *   'render' => opcjonalnie callable( array $tiles, array $settings ): string; domyślnie kafle jeden po drugim.
 *               $tiles: [['html' => kafel, 'r' => proporcje przycięte do 0,7–1,5, 'rr' => proporcje zdjęcia,
 *               'photo' => dane zdjęcia, 'group' => wydarzenie], …].
 * Własny widok: add_filter( 'fp_photos_views', function ( $views ) { $views['moj'] = array( … ); return $views; } );
 */

defined( 'ABSPATH' ) || exit;

class FP_Photos_Views {

	public static function all() {
		$views = array(
			'rows'    => array( 'label' => 'Wypełnione wiersze (lekki chaos)', 'class' => 'fpp-rows' ),
			'masonry' => array( 'label' => 'Masonry (kolumny)', 'class' => 'fpp-masonry' ),
			'grid'    => array( 'label' => 'Siatka kwadratów', 'class' => 'fpp-grid' ),
			'collage' => array( 'label' => 'Kolaż: jedno duże i małe', 'class' => 'fpp-collage' ),
			'strip'   => array( 'label' => 'Pasek przewijany w poziomie', 'class' => 'fpp-strip' ),
		);
		$views = (array) apply_filters( 'fp_photos_views', $views );
		return array_filter( $views, function ( $v ) { return is_array( $v ) && ! empty( $v['label'] ); } );
	}

	/** [klucz => nazwa] do pól wyboru. */
	public static function choices() {
		return wp_list_pluck( self::all(), 'label' );
	}

	/** Wnętrze pojemnika widoku. */
	public static function render( $view, array $tiles, array $s ) {
		$all = self::all();
		$v   = isset( $all[ $view ] ) ? $all[ $view ] : $all['rows'];
		if ( ! empty( $v['render'] ) && is_callable( $v['render'] ) ) {
			return (string) call_user_func( $v['render'], $tiles, $s );
		}
		return implode( '', wp_list_pluck( $tiles, 'html' ) );
	}

	public static function css_class( $view ) {
		$all = self::all();
		return isset( $all[ $view ]['class'] ) ? (string) $all[ $view ]['class'] : 'fpp-rows';
	}
}
