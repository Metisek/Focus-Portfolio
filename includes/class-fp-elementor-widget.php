<?php
/**
 * Widżet Elementora „Focus Portfolio”.
 *  Treść: co pokazać, teksty, filtry i przeglądanie (dane).
 *  Układ: szerokość, kolumny, odstępy, proporcje (z ustawieniami per urządzenie).
 *  Styl: kolory, typografia, zaokrąglenia.
 * Puste pola = wartości z Focus Portfolio → Wygląd. Marginesy widżetu: zakładka Zaawansowane.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

class FP_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'focus-portfolio';
	}

	public function get_title() {
		return 'Focus Portfolio';
	}

	public function get_icon() {
		return 'eicon-gallery-masonry';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'portfolio', 'galeria', 'zdjęcia', 'focus', 'fotorelacje' );
	}

	public function get_style_depends() {
		return array( 'focus-portfolio' );
	}

	public function get_script_depends() {
		return array( 'focus-portfolio' );
	}

	private static function inherit( $opts ) {
		return array( '' => 'Jak w ustawieniach' ) + $opts;
	}

	private static function yesno() {
		return array( '' => 'Jak w ustawieniach', '1' => 'Pokaż', '0' => 'Ukryj' );
	}

	protected function register_controls() {
		$cats = array( '' => 'Strona główna portfolio (wszystkie kategorie)' );
		foreach ( FP_Scanner::categories() as $c ) {
			$cats[ $c['slug'] ] = 'Tylko: ' . $c['title'];
		}

		$cats['__folders'] = 'Zdjęcia z wybranych katalogów';

		/* ---------- Treść ---------- */
		$this->start_controls_section( 'fp_data', array( 'label' => 'Portfolio', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'category', array( 'label' => 'Pokaż', 'type' => Controls_Manager::SELECT, 'options' => $cats, 'default' => '' ) );
		$this->add_control( 'folders', array(
			'label'       => 'Katalogi',
			'type'        => Controls_Manager::SELECT2,
			'multiple'    => true,
			'label_block' => true,
			'options'     => FP_Frontend::folder_choices(),
			'condition'   => array( 'category' => '__folders' ),
			'description' => 'Jedno albo kilka wydarzeń, albo cała kategoria. Zdjęcia pokażą się razem, z filtrami i stronicowaniem jak w „Wszystkie zdjęcia”. Dobre na podstronę, np. galerię jednego projektu.',
		) );
		$this->add_control( 'fp_info', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => 'Na stronie portfolio widżet pokazuje to, czego dotyczy adres: stronę główną, kategorię albo wydarzenie. Tytuły i opisy katalogów zmienisz w <b>Focus Portfolio → Eksplorator</b>.',
			'content_classes' => 'elementor-descriptor',
			'condition'       => array( 'category!' => '__folders' ),
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'fp_texts', array( 'label' => 'Teksty strony głównej', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'show_header', array( 'label' => 'Nagłówek', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '' ) );
		$this->add_control( 'title', array( 'label' => 'Tytuł', 'type' => Controls_Manager::TEXT, 'placeholder' => FP_Settings::view( 'title' ), 'condition' => array( 'show_header!' => '0' ) ) );
		$this->add_control( 'intro', array( 'label' => 'Wstęp', 'type' => Controls_Manager::TEXTAREA, 'placeholder' => FP_Settings::view( 'intro' ), 'condition' => array( 'show_header!' => '0' ) ) );
		$this->add_control( 'show_stats', array( 'label' => 'Statystyki (zdjęcia, galerie)', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '' ) );
		$this->add_control( 'show_counts', array( 'label' => 'Liczniki na kartach', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '' ) );
		$this->add_control( 'show_authors', array( 'label' => 'Autorzy zdjęć', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'fp_browse', array( 'label' => 'Przeglądanie i filtry', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'cover_count', array( 'label' => 'Okładek na karcie wydarzenia', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( array( '1' => '1', '3' => '3 (mozaika)', '5' => '5 (mozaika)' ) ), 'default' => '', 'description' => 'Ma pierwszeństwo przed liczbą okładek wybranych w Eksploratorze; brakujące uzupełnia automat.' ) );
		$this->add_control( 'per_page', array( 'label' => 'Zdjęć na stronę', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( array( '20' => '20', '50' => '50', '100' => '100' ) ), 'default' => '' ) );
		$this->add_control( 'photo_sort', array( 'label' => 'Sortowanie „Wszystkie zdjęcia”', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( FP_Frontend::photo_sorts() ), 'default' => '' ) );
		$this->add_control( 'show_jumps', array( 'label' => 'Filtr miesięcy i lat', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '' ) );
		$this->add_control( 'show_author_filter', array( 'label' => 'Filtr autorów', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '' ) );
		$this->add_control( 'show_sort', array( 'label' => 'Wybór sortowania', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '' ) );
		$this->add_control( 'show_per_page', array( 'label' => 'Wybór liczby na stronę', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '' ) );
		$this->add_control( 'lightbox', array( 'label' => 'Lightbox', 'type' => Controls_Manager::SELECT, 'options' => self::yesno(), 'default' => '' ) );
		$this->end_controls_section();

		/* ---------- Układ ---------- */
		$this->start_controls_section( 'fp_layout', array( 'label' => 'Układ', 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'variant', array( 'label' => 'Wariant', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( array( 'classic' => 'Klasyczny', 'tiles' => 'Kafelki' ) ), 'default' => '' ) );
		$this->add_control( 'gallery', array( 'label' => 'Układ galerii', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( array( 'masonry' => 'Masonry', 'justified' => 'Wyrównane rzędy', 'grid' => 'Siatka' ) ), 'default' => '' ) );
		$this->add_control( 'content_width', array(
			'label'     => 'Szerokość treści',
			'type'      => Controls_Manager::SELECT,
			'options'   => array( '' => 'Jak w ustawieniach', 'boxed' => 'Opakowane', 'full' => 'Pełna szerokość' ),
			'default'   => '',
			'selectors_dictionary' => array( 'full' => '--fp-maxw: none;', 'boxed' => '', '' => '' ),
			'selectors' => array( '{{WRAPPER}} .fp' => '{{VALUE}}' ),
		) );
		$this->add_responsive_control( 'max_width', array(
			'label'      => 'Szerokość',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%', 'vw' ),
			'range'      => array( 'px' => array( 'min' => 600, 'max' => 2400, 'step' => 10 ), '%' => array( 'min' => 30, 'max' => 100 ) ),
			'selectors'  => array( '{{WRAPPER}} .fp' => '--fp-maxw: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'content_width' => 'boxed' ),
		) );
		$this->add_responsive_control( 'side_pad', array(
			'label'      => 'Margines boczny portfolio',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%', 'vw' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
			'selectors'  => array( '{{WRAPPER}} .fp' => '--fp-pad: {{SIZE}}{{UNIT}};' ),
		) );
		$modes = self::inherit( array( 'fixed' => 'Stała liczba na urządzenie', 'auto' => 'Automatycznie (min–maks.)' ) );
		$this->add_control( 'cols_mode', array( 'label' => 'Kolumny galerii', 'type' => Controls_Manager::SELECT, 'options' => $modes, 'default' => '', 'separator' => 'before' ) );
		$this->add_responsive_control( 'columns', array(
			'label'       => 'Liczba kolumn',
			'type'        => Controls_Manager::NUMBER,
			'min'         => 1,
			'max'         => 8,
			'selectors'   => array( '{{WRAPPER}} .fp' => '--fp-cols: {{VALUE}};' ),
			'device_args' => array(
				'tablet' => array( 'selectors' => array( '{{WRAPPER}} .fp' => '--fp-cols-t: {{VALUE}};' ) ),
				'mobile' => array( 'selectors' => array( '{{WRAPPER}} .fp' => '--fp-cols-m: {{VALUE}};' ) ),
			),
			'condition'   => array( 'cols_mode!' => 'auto' ),
		) );
		$this->add_control( 'cols_min', array( 'label' => 'Najmniej kolumn', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 8, 'selectors' => array( '{{WRAPPER}} .fp' => '--fp-gmin: {{VALUE}};' ), 'condition' => array( 'cols_mode' => 'auto' ) ) );
		$this->add_control( 'cols_max', array( 'label' => 'Najwięcej kolumn', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 8, 'selectors' => array( '{{WRAPPER}} .fp' => '--fp-gmax: {{VALUE}};' ), 'condition' => array( 'cols_mode' => 'auto' ) ) );
		$this->add_control( 'tile_min', array(
			'label'      => 'Min. szerokość zdjęcia',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 120, 'max' => 600 ) ),
			'selectors'  => array( '{{WRAPPER}} .fp' => '--fp-gtile: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'cols_mode' => 'auto' ),
		) );
		$this->add_control( 'even_cols', array( 'label' => 'Wyrównaj dół galerii (masonry)', 'type' => Controls_Manager::SELECT, 'options' => array( '' => 'Jak w ustawieniach', '1' => 'Tak', '0' => 'Nie' ), 'default' => '' ) );
		$this->add_control( 'cards_mode', array( 'label' => 'Kolumny kart wydarzeń', 'type' => Controls_Manager::SELECT, 'options' => $modes, 'default' => '', 'separator' => 'before' ) );
		$this->add_responsive_control( 'cards_cols', array(
			'label'       => 'Liczba kolumn kart',
			'type'        => Controls_Manager::NUMBER,
			'min'         => 1,
			'max'         => 6,
			'selectors'   => array( '{{WRAPPER}} .fp' => '--fp-ccols: {{VALUE}};' ),
			'device_args' => array(
				'tablet' => array( 'selectors' => array( '{{WRAPPER}} .fp' => '--fp-ccols-t: {{VALUE}};' ) ),
				'mobile' => array( 'selectors' => array( '{{WRAPPER}} .fp' => '--fp-ccols-m: {{VALUE}};' ) ),
			),
			'condition'   => array( 'cards_mode!' => 'auto' ),
		) );
		$this->add_control( 'cards_min', array( 'label' => 'Najmniej kolumn kart', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 6, 'selectors' => array( '{{WRAPPER}} .fp' => '--fp-cmin: {{VALUE}};' ), 'condition' => array( 'cards_mode' => 'auto' ) ) );
		$this->add_control( 'cards_max', array( 'label' => 'Najwięcej kolumn kart', 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 6, 'selectors' => array( '{{WRAPPER}} .fp' => '--fp-cmax: {{VALUE}};' ), 'condition' => array( 'cards_mode' => 'auto' ) ) );
		$this->add_responsive_control( 'gap', array(
			'label'      => 'Odstęp między zdjęciami',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .fp' => '--fp-gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'row_height', array(
			'label'      => 'Wysokość rzędu (wyrównane rzędy)',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 120, 'max' => 480 ) ),
			'selectors'  => array( '{{WRAPPER}} .fp' => '--fp-rh: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'card_min', array(
			'label'      => 'Min. szerokość karty wydarzenia',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 140, 'max' => 420 ) ),
			'selectors'  => array( '{{WRAPPER}} .fp' => '--fp-card-min: {{SIZE}}{{UNIT}};' ),
			'condition'  => array( 'cards_mode' => 'auto' ),
		) );
		$this->add_control( 'cover_fit', array( 'label' => 'Zdjęcia na okładkach', 'type' => Controls_Manager::SELECT, 'options' => self::inherit( array( 'cover' => 'Wypełnij pole (przytnij)', 'contain' => 'Całe zdjęcie (z tłem)' ) ), 'default' => '', 'separator' => 'before' ) );
		$this->add_control( 'card_ratio', array(
			'label'     => 'Proporcje okładek',
			'type'      => Controls_Manager::SELECT,
			'options'   => self::inherit( array( '3/2' => '3:2', '4/3' => '4:3', '1/1' => '1:1', '16/9' => '16:9', '4/5' => '4:5 (pion)' ) ),
			'default'   => '',
			'selectors' => array( '{{WRAPPER}} .fp' => '--fp-ratio: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		/* ---------- Styl ---------- */
		$this->start_controls_section( 'fp_colors', array( 'label' => 'Kolory', 'tab' => Controls_Manager::TAB_STYLE ) );
		foreach ( array( 'accent' => array( 'Akcent', '--fp-accent: {{VALUE}}; --fp-accent-ink: {{VALUE}};' ), 'text' => array( 'Tekst', '--fp-text: {{VALUE}};' ), 'muted' => array( 'Tekst pomocniczy', '--fp-muted: {{VALUE}};' ), 'line' => array( 'Linie i ramki', '--fp-line: {{VALUE}};' ), 'soft' => array( 'Tło elementów', '--fp-soft: {{VALUE}};' ), 'bg' => array( 'Tło portfolio', '--fp-bg: {{VALUE}};' ) ) as $k => $c ) {
			$this->add_control( 'c_' . $k, array( 'label' => $c[0], 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .fp' => $c[1] ) ) );
		}
		$this->end_controls_section();

		$this->start_controls_section( 'fp_type', array( 'label' => 'Typografia', 'tab' => Controls_Manager::TAB_STYLE ) );
		foreach ( array( 'h1' => array( 'Tytuł strony', '.fp-h1' ), 'h2' => array( 'Nagłówki sekcji i kategorii', '.fp-h2' ), 'h3' => array( 'Tytuły wydarzeń', '.fp-h3' ), 'lede' => array( 'Opisy', '.fp-lede, {{WRAPPER}} .fp-desc' ), 'meta' => array( 'Podpisy i liczniki', '.fp-sub, {{WRAPPER}} .fp-count, {{WRAPPER}} .fp-crumbs' ) ) as $k => $t ) {
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'typo_' . $k, 'label' => $t[0], 'selector' => '{{WRAPPER}} ' . $t[1] ) );
		}
		$this->end_controls_section();

		$this->start_controls_section( 'fp_cards', array( 'label' => 'Karty i zdjęcia', 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'radius', array(
			'label'      => 'Zaokrąglenie okładek',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'selectors'  => array( '{{WRAPPER}} .fp-cover, {{WRAPPER}} .fp-mosaic, {{WRAPPER}} .fp-pair img' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'ph_radius', array(
			'label'      => 'Zaokrąglenie zdjęć w galerii',
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'selectors'  => array( '{{WRAPPER}} .fp-ph' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'hover_zoom', array(
			'label'        => 'Powiększenie okładki po najechaniu',
			'type'         => Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'selectors_dictionary' => array( '' => 'transform: none !important;' ),
			'selectors'    => array( '{{WRAPPER}} .fp-ev:hover .fp-cover img, {{WRAPPER}} .fp-cat:hover .fp-mosaic img' => '{{VALUE}}' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$args = array( '_no_vars' => 1 );
		foreach ( array( 'category', 'variant', 'gallery', 'cover_count', 'per_page', 'photo_sort', 'title', 'intro', 'cols_mode', 'cards_mode', 'cover_fit' ) as $k ) {
			if ( isset( $s[ $k ] ) && '' !== $s[ $k ] ) {
				$args[ $k ] = sanitize_text_field( (string) $s[ $k ] );
			}
		}
		if ( isset( $args['category'] ) && '__folders' === $args['category'] ) {
			unset( $args['category'] );
			$args['folders'] = array_map( 'sanitize_text_field', array_map( 'strval', (array) ( isset( $s['folders'] ) ? $s['folders'] : array() ) ) );
			if ( ! $args['folders'] ) {
				$args['folders'] = array( '__none' ); // pusty wybór: komunikat zamiast strony głównej portfolio
			}
		}
		foreach ( array( 'show_header', 'show_stats', 'show_counts', 'show_authors', 'show_jumps', 'show_author_filter', 'show_sort', 'show_per_page', 'lightbox', 'even_cols' ) as $k ) {
			if ( isset( $s[ $k ] ) && '' !== $s[ $k ] ) {
				$args[ $k ] = (int) $s[ $k ];
			}
		}
		echo FP_Frontend::render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
