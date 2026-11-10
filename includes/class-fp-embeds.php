<?php
/**
 * Portfolio jako element strony: widżet Elementora „Focus Portfolio” i blok Gutenberga „focus/portfolio”.
 * Oba mają te same ustawienia; puste pole = wartość z Wygląd → Portfolio.
 * Marginesy i dopełnienie ustawia się standardowo (Elementor: Zaawansowane, blok: Wymiary).
 */

defined( 'ABSPATH' ) || exit;

class FP_Embeds {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'elementor/widgets/register', function ( $manager ) {
			require_once FP_DIR . 'includes/class-fp-elementor-widget.php';
			$manager->register( new FP_Elementor_Widget() );
		} );
	}

	/** Opcje wspólne dla widżetu i bloku: [klucz => [etykieta, typ, opcje]]. */
	public static function controls() {
		$cats = array( '' => 'Wszystkie kategorie (strona główna portfolio)' );
		foreach ( FP_Scanner::categories() as $c ) {
			$cats[ $c['slug'] ] = $c['title'];
		}
		$inherit = array( '' => 'Jak w Wygląd → Portfolio' );
		return array(
			'category'    => array( 'Pokaż', 'select', $cats ),
			'variant'     => array( 'Wariant', 'select', $inherit + array( 'classic' => 'Klasyczny', 'tiles' => 'Kafelki' ) ),
			'gallery'     => array( 'Układ galerii', 'select', $inherit + array( 'masonry' => 'Masonry', 'justified' => 'Wyrównane rzędy', 'grid' => 'Siatka' ) ),
			'columns'     => array( 'Kolumny (komputer)', 'select', $inherit + array( '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6' ) ),
			'columns_t'   => array( 'Kolumny (tablet)', 'select', $inherit + array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ) ),
			'columns_m'   => array( 'Kolumny (telefon)', 'select', $inherit + array( '1' => '1', '2' => '2', '3' => '3' ) ),
			'ccols'       => array( 'Kolumny kart (komputer)', 'select', $inherit + array( '2' => '2', '3' => '3', '4' => '4', '5' => '5' ) ),
			'ccols_m'     => array( 'Kolumny kart (telefon)', 'select', $inherit + array( '1' => '1', '2' => '2' ) ),
			'cover_fit'   => array( 'Zdjęcia na okładkach', 'select', $inherit + array( 'cover' => 'Wypełnij pole', 'contain' => 'Całe zdjęcie' ) ),
			'card_ratio'  => array( 'Proporcje okładek', 'select', $inherit + array( '3/2' => '3:2', '4/3' => '4:3', '1/1' => '1:1', '16/9' => '16:9', '4/5' => '4:5' ) ),
			'show_header' => array( 'Nagłówek z tytułem i wstępem', 'select', $inherit + array( '1' => 'Pokaż', '0' => 'Ukryj' ) ),
			'show_stats'  => array( 'Liczby zdjęć i galerii', 'select', $inherit + array( '1' => 'Pokaż', '0' => 'Ukryj' ) ),
			'max_width'   => array( 'Szerokość treści (px, 0 = cała)', 'number', array() ),
			'side_pad'    => array( 'Margines boczny portfolio (px)', 'number', array() ),
		);
	}

	/** Ustawienia z widżetu/bloku → argumenty FP_Frontend::render(). */
	public static function args( $raw ) {
		$out = array();
		foreach ( array_keys( self::controls() ) as $k ) {
			if ( ! isset( $raw[ $k ] ) || '' === $raw[ $k ] || null === $raw[ $k ] ) {
				continue;
			}
			$val       = is_array( $raw[ $k ] ) && isset( $raw[ $k ]['size'] ) ? $raw[ $k ]['size'] : $raw[ $k ];
			$out[ $k ] = in_array( $k, array( 'columns', 'columns_t', 'columns_m', 'ccols', 'ccols_m', 'show_header', 'show_stats', 'max_width', 'side_pad' ), true ) ? (int) $val : sanitize_text_field( (string) $val );
		}
		return $out;
	}

	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_script( 'fp-block', FP_URL . 'assets/js/block.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ), fp_ver( 'assets/js/block.js' ), true );
		$controls = array();
		foreach ( self::controls() as $k => $c ) {
			$opts = array();
			foreach ( $c[2] as $value => $label ) {
				$opts[] = array( 'value' => (string) $value, 'label' => $label );
			}
			$controls[] = array( 'key' => $k, 'label' => $c[0], 'type' => $c[1], 'options' => $opts );
		}
		wp_localize_script( 'fp-block', 'FP_BLOCK', array( 'controls' => $controls ) );
		FP_Frontend::register_assets();
		$attributes = array();
		foreach ( self::controls() as $k => $c ) {
			$attributes[ $k ] = array( 'type' => 'string', 'default' => '' );
		}
		register_block_type( 'focus/portfolio', array(
			'api_version'     => 3,
			'title'           => 'Focus Portfolio',
			'category'        => 'media',
			'icon'            => 'format-gallery',
			'editor_script'   => 'fp-block',
			'editor_style'    => 'focus-portfolio',
			'style'           => 'focus-portfolio',
			'script'          => 'focus-portfolio',
			'attributes'      => $attributes,
			'supports'        => array(
				'align'   => array( 'wide', 'full' ),
				'html'    => false,
				'spacing' => array( 'margin' => true, 'padding' => true ),
			),
			'render_callback' => function ( $attrs ) {
				$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : 'class="wp-block-focus-portfolio"';
				return '<div ' . $wrapper . '>' . FP_Frontend::render( FP_Embeds::args( $attrs ) ) . '</div>';
			},
		) );
	}
}
