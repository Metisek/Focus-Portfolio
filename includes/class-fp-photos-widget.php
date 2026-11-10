<?php
/**
 * Widżet Elementora „Focus Portfolio – Zdjęcia” (moduł 5). Osobny od widżetu „Focus Portfolio”.
 *  Treść: wybór zdjęć i źródło, losowanie, kliknięcie i podpis. Układ: widok, wiersze × zdjęcia (komputer / tablet / telefon), odstęp.
 *  Styl: zaokrąglenie, filtr kolorów, powiększenie, podpis.
 * Puste pola = Focus Portfolio → Wygląd → „Zdjęcia na innych stronach”.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

class FP_Photos_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'focus-portfolio-photos';
	}

	public function get_title() {
		return 'Focus Portfolio – Zdjęcia';
	}

	public function get_icon() {
		return 'eicon-gallery-justified';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'zdjęcia', 'losowe', 'portfolio', 'galeria', 'focus', 'random', 'photos' );
	}

	public function get_style_depends() {
		return array( 'fp-photos' );
	}

	public function get_script_depends() {
		return array( 'fp-photos' );
	}

	/** Treść zależy od losowania i cache wtyczki, więc Elementor nie może jej zapamiętywać (Element Caching). */
	protected function is_dynamic_content(): bool {
		return true;
	}

	private static function inherit( $opts ) {
		return array( '' => 'Jak w ustawieniach' ) + $opts;
	}

	private static function yesno() {
		return array( '' => 'Jak w ustawieniach', '1' => 'Tak', '0' => 'Nie' );
	}

	protected function register_controls() {
		$ch      = FP_Photos::choices();
		$folders = FP_Frontend::folder_choices();
		$events  = array_filter( $folders, function ( $k ) { return '/*' !== substr( $k, -2 ); }, ARRAY_FILTER_USE_KEY );
		$cats    = array();
		foreach ( FP_Scanner::categories() as $c ) {
			$cats[ $c['dir'] ] = $c['title'];
		}
		$authors = array_keys( FP_People::from_photos() );
		$authors = array_combine( $authors, $authors );
		$random  = array( 'mode' => array( '', 'random', 'best', 'events' ) );

		/* ---------- Treść ---------- */
		$this->start_controls_section( 'fpp_pick', array( 'label' => 'Zdjęcia', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'mode', array( 'label' => 'Wybór zdjęć', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( $ch['mode'] ), 'default' => '' ) );
		$this->add_control( 'photos', array(
			'label'       => 'Zdjęcia',
			'type'        => Controls_Manager::SELECT2,
			'multiple'    => true,
			'label_block' => true,
			'options'     => FP_Photos_Source::choices(),
			'condition'   => array( 'mode' => 'picked' ),
			'description' => 'Wpisz fragment nazwy wydarzenia albo pliku. Kolejność jak na liście (najwyżej 12).',
		) );
		$this->add_control( 'source', array( 'label' => 'Źródło', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( $ch['source'] ), 'default' => '', 'condition' => array( 'mode!' => 'picked' ) ) );
		$this->add_control( 'cats', array( 'label' => 'Kategorie', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => $cats, 'condition' => array( 'mode!' => 'picked', 'source' => 'cats' ) ) );
		$this->add_control( 'folders', array( 'label' => 'Katalogi', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => $folders, 'condition' => array( 'mode!' => 'picked', 'source' => 'folders' ) ) );
		$this->add_control( 'from', array( 'label' => 'Okres od', 'type' => Controls_Manager::TEXT, 'placeholder' => 'np. 2025-10', 'condition' => array( 'mode!' => 'picked' ) ) );
		$this->add_control( 'to', array( 'label' => 'Okres do', 'type' => Controls_Manager::TEXT, 'placeholder' => 'np. 2026-06', 'condition' => array( 'mode!' => 'picked' ) ) );
		$this->add_control( 'stars', array( 'label' => 'Tylko z gwiazdką', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '', 'condition' => array( 'mode!' => 'picked' ) ) );
		$this->add_control( 'orient', array( 'label' => 'Orientacja', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( $ch['orient'] ), 'default' => '', 'condition' => array( 'mode!' => 'picked' ) ) );
		$this->add_control( 'ex_authors', array( 'label' => 'Wyklucz autorów', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => $authors, 'separator' => 'before', 'condition' => array( 'mode!' => 'picked' ) ) );
		$this->add_control( 'ex_events', array( 'label' => 'Wyklucz wydarzenia', 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => $events, 'condition' => array( 'mode!' => 'picked' ), 'description' => 'Np. ze względu na prywatność. Ukryte kategorie i wydarzenia są pomijane zawsze.' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'fpp_draw', array( 'label' => 'Losowanie', 'tab' => Controls_Manager::TAB_CONTENT, 'condition' => $random ) );
		$this->add_control( 'refresh', array( 'label' => 'Nowe losowanie', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( $ch['refresh'] ), 'default' => '' ) );
		$this->add_control( 'bag', array( 'label' => 'Bez powtórek między losowaniami', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '', 'description' => 'Worek: kolejne losowania biorą jeszcze niepokazane zdjęcia; po pokazaniu wszystkich zaczyna się od nowa.' ) );
		$this->add_control( 'shuffle', array( 'label' => 'Tasuj w przeglądarce', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '', 'description' => 'Strona bywa w cache (LiteSpeed). Przeglądarka miesza wtedy 12 zapisanych zdjęć przy każdym wejściu.' ) );
		$this->add_control( 'button', array( 'label' => 'Przycisk „Losuj ponownie”', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'fpp_click', array( 'label' => 'Kliknięcie i podpis', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'action', array( 'label' => 'Kliknięcie', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( $ch['action'] ), 'default' => '' ) );
		$this->add_control( 'caption', array( 'label' => 'Podpis po najechaniu', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( $ch['caption'] ), 'default' => '' ) );
		$this->end_controls_section();

		/* ---------- Układ ---------- */
		$this->start_controls_section( 'fpp_layout', array( 'label' => 'Układ', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'view', array( 'label' => 'Widok', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( $ch['view'] ), 'default' => '' ) );
		$this->add_responsive_control( 'rows', array( 'label' => 'Wiersze', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 6, 'description' => 'Wiersze × zdjęcia w wierszu: najwyżej 12.' ) );
		$this->add_responsive_control( 'per_row', array( 'label' => 'Zdjęć w wierszu (kolumn)', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 8 ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Odstęp', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ) ) );
		$this->add_control( 'chaos', array( 'label' => 'Chaos wierszy', 'type' => Controls_Manager::SLIDER, 'size_units' => array( '%' ), 'range' => array( '%' => array( 'min' => 0, 'max' => 100 ) ), 'description' => '50 % = wysokość wiersza ±6 %.', 'condition' => array( 'view' => array( '', 'rows' ) ) ) );
		$this->add_control( 'strip_h', array( 'label' => 'Wysokość paska', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 120, 'max' => 600 ) ), 'condition' => array( 'view' => 'strip' ) ) );
		$this->end_controls_section();

		/* ---------- Styl ---------- */
		$this->start_controls_section( 'fpp_style', array( 'label' => 'Zdjęcia', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'radius', array( 'label' => 'Zaokrąglenie', 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ) ) );
		$this->add_control( 'filter', array( 'label' => 'Filtr kolorów', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( $ch['filter'] ), 'default' => '' ) );
		$this->add_control( 'zoom', array( 'label' => 'Powiększenie po najechaniu', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '' ) );
		$this->add_control( 'zoom_scale', array( 'label' => 'Siła powiększenia', 'type' => Controls_Manager::SLIDER, 'size_units' => array( '%' ), 'range' => array( '%' => array( 'min' => 100, 'max' => 125 ) ), 'condition' => array( 'zoom!' => '0' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'fpp_caption', array( 'label' => 'Podpis', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'cap_typo', 'selector' => '{{WRAPPER}} .fpp-cap' ) );
		$this->add_control( 'cap_color', array( 'label' => 'Kolor', 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .fpp-cap' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$size = function ( $k ) use ( $s ) {
			return isset( $s[ $k ]['size'] ) && '' !== $s[ $k ]['size'] ? $s[ $k ]['size'] : '';
		};
		$args = array();
		foreach ( array( 'mode', 'source', 'from', 'to', 'orient', 'refresh', 'action', 'caption', 'view', 'filter', 'stars', 'bag', 'shuffle', 'button', 'zoom' ) as $k ) {
			$args[ $k ] = isset( $s[ $k ] ) ? (string) $s[ $k ] : '';
		}
		foreach ( array( 'photos', 'cats', 'folders', 'ex_authors', 'ex_events' ) as $k ) {
			$args[ $k ] = isset( $s[ $k ] ) && is_array( $s[ $k ] ) ? $s[ $k ] : array();
		}
		foreach ( array( '' => '', '_tablet' => '_t', '_mobile' => '_m' ) as $suf => $to ) {
			$args[ 'rows' . $to ]    = isset( $s[ 'rows' . $suf ] ) ? $s[ 'rows' . $suf ] : '';
			$args[ 'per_row' . $to ] = isset( $s[ 'per_row' . $suf ] ) ? $s[ 'per_row' . $suf ] : '';
		}
		$args['gap']        = $size( 'gap' );
		$args['gap_m']      = $size( 'gap_mobile' );
		$args['chaos']      = $size( 'chaos' );
		$args['strip_h']    = $size( 'strip_h' );
		$args['radius']     = $size( 'radius' );
		$args['zoom_scale'] = $size( 'zoom_scale' );
		echo FP_Photos::render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
