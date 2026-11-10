<?php
/**
 * Strona portfolio: adresy /portfolio/<kategoria>/[wszystkie|<miesiąc>/<wydarzenie>]/,
 * renderowanie widoków, tytuły dla SEO i zasoby CSS/JS.
 */

defined( 'ABSPATH' ) || exit;

class FP_Frontend {

	const QV = 'fp_path';

	private static $route = null;

	public static function init() {
		add_shortcode( 'focus_portfolio', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'rewrite' ), 20 );
		add_filter( 'query_vars', function ( $v ) {
			$v[] = self::QV;
			return $v;
		} );
		add_filter( 'redirect_canonical', function ( $redirect ) {
			return get_query_var( self::QV ) ? false : $redirect;
		} );
		add_action( 'template_redirect', array( __CLASS__, 'guard_404' ) );
		add_filter( 'the_content', array( __CLASS__, 'replace_content' ), 9999 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ) );
		add_filter( 'wpseo_title', array( __CLASS__, 'seo_title' ) );
		add_filter( 'wpseo_metadesc', array( __CLASS__, 'seo_desc' ) );
		add_filter( 'get_canonical_url', array( __CLASS__, 'seo_url' ) );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'seo_url' ) );
		add_filter( 'wpseo_opengraph_url', array( __CLASS__, 'seo_url' ) );
		add_action( 'wpseo_add_opengraph_images', array( __CLASS__, 'seo_image' ) );
		add_filter( 'body_class', function ( $c ) {
			if ( self::is_portfolio_page() ) {
				$c[] = 'fp-page';
			}
			return $c;
		} );
	}

	/* ---------- adresy ---------- */

	public static function page_id() {
		return (int) FP_Settings::get( 'page_id' );
	}

	public static function rewrite() {
		$id = self::page_id();
		if ( ! $id || 'page' !== get_post_type( $id ) ) {
			return;
		}
		$uri   = get_page_uri( $id );
		$regex = '^' . preg_quote( $uri, '#' ) . '/(.+?)/?$';
		add_rewrite_rule( $regex, 'index.php?pagename=' . $uri . '&' . self::QV . '=$matches[1]', 'top' );
		$rules = get_option( 'rewrite_rules' );
		if ( get_option( 'fp_flush_rewrite' ) || ( is_array( $rules ) && ! isset( $rules[ $regex ] ) && get_option( 'permalink_structure' ) ) ) {
			delete_option( 'fp_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	}

	public static function base_url() {
		$id = self::page_id();
		return $id ? trailingslashit( get_permalink( $id ) ) : trailingslashit( home_url( '/portfolio' ) );
	}

	public static function url( $cat = '', $key = '' ) {
		$u = self::base_url();
		if ( '' === $cat ) {
			return $u;
		}
		if ( ! get_option( 'permalink_structure' ) ) {
			return add_query_arg( self::QV, $cat . ( '' !== $key ? '/' . $key : '' ), $u );
		}
		return $u . $cat . '/' . ( '' !== $key ? $key . '/' : '' );
	}

	/** @return array{view:string,cat:?array,group:?array,valid:bool} */
	public static function route() {
		if ( null !== self::$route ) {
			return self::$route;
		}
		$path = trim( (string) get_query_var( self::QV ), '/' );
		$r    = array( 'view' => 'home', 'cat' => null, 'group' => null, 'valid' => true );
		if ( '' !== $path ) {
			$parts    = explode( '/', $path, 2 );
			$r['cat'] = FP_Scanner::find_category( sanitize_title( $parts[0] ) );
			if ( ! $r['cat'] ) {
				$r['valid'] = false;
			} elseif ( empty( $parts[1] ) ) {
				$r['view'] = 'cat';
			} elseif ( 'wszystkie' === $parts[1] ) {
				$r['view'] = FP_Settings::view( 'show_all_view' ) ? 'all' : 'cat';
			} else {
				$r['group'] = FP_Scanner::find_group( $r['cat'], $parts[1] );
				$r['view']  = 'event';
				$r['valid'] = (bool) $r['group'];
			}
		}
		self::$route = $r;
		return $r;
	}

	public static function is_portfolio_page() {
		$id = self::page_id();
		if ( $id && is_page( $id ) ) {
			return true;
		}
		$post = get_post();
		return is_singular() && $post && self::embeds( $post->ID );
	}

	/** Czy strona ma portfolio wstawione ręcznie: widżet Elementora, blok albo shortcode. */
	public static function embeds( $post_id ) {
		$content = (string) get_post_field( 'post_content', $post_id );
		if ( has_shortcode( $content, 'focus_portfolio' ) || has_block( 'focus/portfolio', $content ) ) {
			return true;
		}
		$el = get_post_meta( $post_id, '_elementor_data', true );
		return is_string( $el ) && false !== strpos( $el, '"widgetType":"focus-portfolio"' );
	}

	public static function guard_404() {
		if ( ! get_query_var( self::QV ) || ! self::is_portfolio_page() ) {
			return;
		}
		FP_Scanner::maybe_rescan();
		if ( ! self::route()['valid'] ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/* ---------- osadzanie ---------- */

	public static function replace_content( $content ) {
		$id = self::page_id();
		if ( ! $id || ! is_page( $id ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		// widżet/blok na stronie decyduje sam, gdzie stoi portfolio
		if ( isset( $_GET['elementor-preview'] ) || self::embeds( $id ) ) { // phpcs:ignore
			return $content;
		}
		return self::render();
	}

	public static function shortcode() {
		return self::render();
	}

	/** Rejestracja zawsze (widżet i blok mogą być na dowolnej stronie), dołączenie tylko tam, gdzie trzeba. */
	public static function register_assets() {
		if ( wp_script_is( 'focus-portfolio', 'registered' ) ) {
			return;
		}
		wp_register_style( 'focus-portfolio', FP_URL . 'assets/css/portfolio.css', array(), fp_ver( 'assets/css/portfolio.css' ) );
		wp_add_inline_style( 'focus-portfolio', self::css_vars() );
		wp_register_script( 'focus-portfolio', FP_URL . 'assets/js/portfolio.js', array(), fp_ver( 'assets/js/portfolio.js' ), true );
		wp_localize_script( 'focus-portfolio', 'FP_CONF', array(
			'lightbox' => (int) FP_Settings::view( 'lightbox' ),
			'download' => (int) FP_Settings::view( 'lb_download' ),
			'admin'    => self::can_edit() ? array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'fp_admin' ) ) : null,
			'chunk'    => 60,
			'i18n'     => array_map( array( 'FP_I18n', 't' ), array(
				'close' => 'Zamknij',
				'prev'  => 'Poprzednie',
				'next'  => 'Następne',
				'goto'  => 'Przejdź do wydarzenia →',
				'orig'  => 'Pobierz oryginał',
				'more'  => 'Pokaż więcej zdjęć',
				'cover' => 'Ustaw jako okładkę wydarzenia',
				'all'   => 'Wszystkie okresy',
			) ),
		) );
	}

	/** Napis dla odwiedzających w języku portfolio (FP_I18n). */
	private static function t( $s ) {
		return FP_I18n::t( $s );
	}

	public static function assets() {
		if ( self::is_portfolio_page() ) {
			wp_enqueue_style( 'focus-portfolio' );
			wp_enqueue_script( 'focus-portfolio' );
		}
	}

	public static function can_edit() {
		return is_user_logged_in() && current_user_can( 'edit_theme_options' );
	}

	/** Zmienne CSS z ustawień (albo z nadpisań widżetu/bloku). Puste kolory = z motywu strony (domyślne w portfolio.css). */
	public static function vars( $v ) {
		$accent = sanitize_hex_color( $v['accent'] ) ? $v['accent'] : '#1bb0ce';
		$maxw   = (int) $v['max_width'];
		$int    = function ( $k, $min, $max, $def ) use ( $v ) {
			return max( $min, min( $max, isset( $v[ $k ] ) && '' !== $v[ $k ] ? (int) $v[ $k ] : $def ) );
		};
		$cmin = $int( 'cards_min', 1, 6, 1 );
		$gmin = $int( 'cols_min', 1, 8, 2 );
		$vars = array(
			'--fp-accent'      => $accent,
			'--fp-accent-ink'  => self::shade( $accent, -0.38 ),
			'--fp-accent-soft' => self::mix_white( $accent, 0.88 ),
			'--fp-cols'        => $int( 'columns', 1, 8, 4 ),
			'--fp-cols-t'      => $int( 'columns_t', 1, 6, 3 ),
			'--fp-cols-m'      => $int( 'columns_m', 1, 4, 2 ),
			'--fp-gmin'        => $gmin,
			'--fp-gmax'        => max( $gmin, $int( 'cols_max', 1, 8, 5 ) ),
			'--fp-gtile'       => $int( 'tile_min', 120, 600, 260 ) . 'px',
			'--fp-ccols'       => $int( 'ccols', 1, 6, 4 ),
			'--fp-ccols-t'     => $int( 'ccols_t', 1, 5, 3 ),
			'--fp-ccols-m'     => $int( 'ccols_m', 1, 3, 2 ),
			'--fp-cmin'        => $cmin,
			'--fp-cmax'        => max( $cmin, $int( 'cards_max', 1, 6, 4 ) ),
			'--fp-card-min'    => $int( 'card_min', 140, 420, 230 ) . 'px',
			'--fp-gap'         => $int( 'gap', 0, 40, 10 ) . 'px',
			'--fp-rh'          => $int( 'row_height', 120, 480, 260 ) . 'px',
			'--fp-ratio'       => preg_match( '#^\d+/\d+$#', $v['card_ratio'] ) ? $v['card_ratio'] : '3/2',
			'--fp-maxw'        => $maxw > 0 ? max( 600, min( 2400, $maxw ) ) . 'px' : 'none',
			'--fp-pad'         => $int( 'side_pad', 0, 120, 24 ) . 'px',
		);
		foreach ( array( 'text', 'muted', 'line', 'soft' ) as $k ) {
			if ( ! empty( $v[ $k ] ) && sanitize_hex_color( $v[ $k ] ) ) {
				$vars[ '--fp-' . $k ] = $v[ $k ];
			}
		}
		if ( ! empty( $v['text'] ) && sanitize_hex_color( $v['text'] ) ) {
			$vars['--fp-ink'] = $v['text'];
		}
		if ( '' !== trim( $v['heading_font'] ) ) {
			$vars['--fp-hfont'] = '"' . str_replace( '"', '', sanitize_text_field( $v['heading_font'] ) ) . '", sans-serif';
		}
		return $vars;
	}

	/** Klasy głównego elementu: wariant, układ galerii, tryby kolumn, wyrównanie, dopasowanie okładek. */
	private static function classes( $v ) {
		$variant = 'tiles' === $v['variant'] ? 'tiles' : 'classic'; // dawny „ciemny” = klasyczny w kolorach strony
		$c       = array( 'fp', 'fp-v-' . $variant, 'fp-g-' . sanitize_html_class( $v['gallery'] ) );
		if ( '' !== trim( $v['heading_font'] ) ) {
			$c[] = 'fp-hf';
		}
		if ( ! empty( $v['text'] ) && sanitize_hex_color( $v['text'] ) ) {
			$c[] = 'fp-has-text';
		}
		if ( 'auto' === $v['cols_mode'] ) {
			$c[] = 'fp-gc-auto';
		}
		if ( 'auto' === $v['cards_mode'] ) {
			$c[] = 'fp-cc-auto';
		}
		if ( ! empty( $v['even_cols'] ) ) {
			$c[] = 'fp-even';
		}
		if ( 'contain' === $v['cover_fit'] ) {
			$c[] = 'fp-fit-contain';
		}
		return $c;
	}

	public static function css_vars() {
		$css = '.fp{';
		foreach ( self::vars( FP_Settings::view_all() ) as $k => $val ) {
			$css .= $k . ':' . $val . ';';
		}
		return $css . '}';
	}

	private static function shade( $hex, $f ) {
		$c = sscanf( ltrim( $hex, '#' ), '%02x%02x%02x' );
		foreach ( $c as &$x ) {
			$x = (int) max( 0, min( 255, $f < 0 ? $x * ( 1 + $f ) : $x + ( 255 - $x ) * $f ) );
		}
		return vsprintf( '#%02x%02x%02x', $c );
	}

	private static function mix_white( $hex, $f ) {
		return self::shade( $hex, $f );
	}

	/* ---------- SEO ---------- */

	private static function route_title() {
		if ( ! self::is_portfolio_page() ) {
			return '';
		}
		$r = self::route();
		if ( 'event' === $r['view'] && $r['group'] ) {
			return self::group_title( $r['group'], $r['cat'] ) . ( $r['group']['period'] ? ' · ' . FP_I18n::period( $r['group']['period'] ) : '' ) . ' · ' . $r['cat']['title'];
		}
		if ( in_array( $r['view'], array( 'cat', 'all' ), true ) && $r['cat'] ) {
			return $r['cat']['title'] . ( 'all' === $r['view'] ? ' · ' . self::t( FP_Settings::view( 'label_all' ) ) : '' );
		}
		return '';
	}

	public static function title_parts( $parts ) {
		$t = self::route_title();
		if ( '' !== $t ) {
			$parts['title'] = $t . ' · ' . self::t( FP_Settings::view( 'title' ) );
		}
		return $parts;
	}

	public static function seo_title( $title ) {
		$t = self::route_title();
		return '' !== $t ? $t . ' · ' . self::t( FP_Settings::view( 'title' ) ) . ' · ' . get_bloginfo( 'name' ) : $title;
	}

	public static function seo_desc( $desc ) {
		$r = self::is_portfolio_page() ? self::route() : null;
		if ( $r && 'event' === $r['view'] && $r['group'] ) {
			$g = $r['group'];
			return $g['desc'] ? $g['desc'] : sprintf( self::t( '%1$s: %2$s z wydarzenia %3$s.' ), $r['cat']['title'], self::photos_word( count( $g['photos'] ) ), self::group_title( $g, $r['cat'] ) );
		}
		return $desc;
	}

	public static function seo_url( $url ) {
		$r = self::is_portfolio_page() ? self::route() : null;
		if ( $r && $r['cat'] && $r['valid'] ) {
			return self::url( $r['cat']['slug'], 'event' === $r['view'] ? $r['group']['key'] : ( 'all' === $r['view'] ? 'wszystkie' : '' ) );
		}
		return $url;
	}

	public static function seo_image( $container ) {
		$r = self::is_portfolio_page() ? self::route() : null;
		if ( $r && 'event' === $r['view'] && $r['group'] && is_object( $container ) && method_exists( $container, 'add_image' ) ) {
			$container->add_image( array( 'url' => FP_Thumbs::url( self::cover( $r['group'] ), 'l' ) ) );
		}
	}

	/* ---------- renderowanie ---------- */

	/**
	 * @param array $args Nadpisania z widżetu Elementora lub bloku (puste wartości = ustawienia z Wygląd → Portfolio),
	 *                    plus 'category' = slug kategorii pokazywanej zamiast strony głównej portfolio.
	 */
	public static function render( $args = array() ) {
		FP_Scanner::maybe_rescan();
		$args = array_filter( (array) $args, function ( $x ) {
			return '' !== $x && null !== $x;
		} );
		$only    = isset( $args['category'] ) ? sanitize_title( $args['category'] ) : '';
		$folders = isset( $args['folders'] ) ? array_values( array_filter( array_map( 'strval', (array) $args['folders'] ) ) ) : array();
		// widżet Elementora ustawia zmienne CSS własnymi selektorami (per urządzenie), więc bez stylu inline
		$no_vars = ! empty( $args['_no_vars'] );
		unset( $args['category'], $args['_no_vars'], $args['folders'] );
		FP_Settings::$override = $args;
		$out                   = self::render_inner( $only, (bool) $args && ! $no_vars, $folders );
		FP_Settings::$override = array();
		return $out;
	}

	/** Identyfikator katalogu do wyboru w widżecie: katalog wydarzenia, „katalog#nazwa” dla grupy luzem, „kategoria/*” dla całej kategorii. */
	public static function folder_id( $g ) {
		return '' !== (string) $g['dir'] ? $g['dir'] : $g['mdir'] . '#' . $g['mkey'];
	}

	/** Lista do pola „Katalogi” w widżecie: [id => etykieta]. */
	public static function folder_choices() {
		$out = array();
		foreach ( FP_Scanner::categories() as $c ) {
			$out[ $c['dir'] . '/*' ] = $c['title'] . ': wszystkie (' . self::photos_word( $c['count'] ) . ')';
			foreach ( $c['groups'] as $g ) {
				$out[ self::folder_id( $g ) ] = $c['title'] . ' · ' . self::group_title( $g, $c ) . ( $g['period'] ? ' · ' . $g['period'] : '' ) . ' (' . count( $g['photos'] ) . ')';
			}
		}
		return $out;
	}

	/** Zdjęcia z wybranych katalogów (widżet na dowolnej podstronie), z filtrami i stronicowaniem jak „Wszystkie zdjęcia”. */
	private static function view_folders( $ids ) {
		$items   = array();
		$periods = array();
		foreach ( FP_Scanner::categories() as $c ) {
			$whole = in_array( $c['dir'] . '/*', $ids, true );
			foreach ( $c['groups'] as $g ) {
				if ( ! $whole && ! in_array( self::folder_id( $g ), $ids, true ) ) {
					continue;
				}
				$g['_c'] = $c;
				if ( $g['period'] ) {
					$periods[ $g['period'] ] = max( isset( $periods[ $g['period'] ] ) ? $periods[ $g['period'] ] : 0, (int) $g['sort'] );
				}
				foreach ( $g['photos'] as $p ) {
					$items[] = array( $p, $g );
				}
			}
		}
		if ( ! $items ) {
			echo '<p class="fp-empty">' . esc_html( self::t( current_user_can( 'edit_posts' ) ? 'Wybierz katalogi w ustawieniach widżetu Focus Portfolio.' : 'Brak zdjęć.' ) ) . '</p>';
			return;
		}
		arsort( $periods );
		$c = array( 'dir' => '', 'slug' => '', 'title' => '', 'other' => FP_Settings::view( 'label_other' ), 'mode' => 'events' );
		echo '<div class="fp-gallery-wrap">';
		self::photo_list( $items, $c, true, self::req( FP_Settings::view( 'photo_sort' ) ), array_keys( $periods ) );
		echo '</div>';
	}

	private static function render_inner( $only, $has_override, $folders = array() ) {
		$r       = self::route();
		$v       = FP_Settings::view_all();
		$classes = self::classes( $v );
		if ( ! FP_Scanner::categories() ) {
			$msg = current_user_can( 'manage_options' )
				? 'Portfolio jest puste. Sprawdź folder źródłowy w Ustawienia → Focus Portfolio.'
				: 'Portfolio wkrótce.';
			return '<div class="fp"><p class="fp-empty">' . esc_html( self::t( $msg ) ) . '</p></div>';
		}
		if ( '' !== $only && 'home' === $r['view'] && FP_Scanner::find_category( $only ) ) {
			$r = array( 'view' => 'cat', 'cat' => FP_Scanner::find_category( $only ), 'group' => null, 'valid' => true );
		}
		$style = '';
		if ( $has_override ) {
			foreach ( self::vars( $v ) as $k => $val ) {
				$style .= $k . ':' . $val . ';';
			}
		}
		ob_start();
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>';
		if ( $folders ) {
			self::view_folders( $folders );
		} elseif ( ! $r['valid'] ) {
			echo '<p class="fp-empty">' . esc_html( self::t( 'Nie ma takiej galerii.' ) ) . ' <a href="' . esc_url( self::url() ) . '">' . esc_html( self::t( $v['title'] ) ) . '</a></p>';
		} elseif ( 'home' === $r['view'] ) {
			self::view_home();
		} elseif ( 'event' === $r['view'] ) {
			self::view_event( $r['cat'], $r['group'] );
		} else {
			self::view_category( $r['cat'], 'all' === $r['view'] );
		}
		echo '</div>';
		return ob_get_clean();
	}

	private static function view_home() {
		$v    = FP_Settings::view_all();
		$cats = FP_Scanner::categories();
		$st   = FP_Scanner::manifest()['stats'];
		if ( $v['show_header'] ) {
			echo '<header class="fp-head"><h1 class="fp-h1">' . esc_html( self::t( $v['title'] ) ) . '</h1>';
			if ( $v['intro'] ) {
				echo '<p class="fp-lede">' . esc_html( $v['intro'] ) . '</p>';
			}
			if ( $v['show_stats'] ) {
				$groups = array_sum( array_map( function ( $c ) { return count( $c['groups'] ); }, $cats ) );
				$photos = array_sum( array_map( function ( $c ) { return $c['count']; }, $cats ) );
				printf(
					'<div class="fp-stats"><div><b>%d</b><span>%s</span></div><div><b>%d</b><span>%s</span></div><div><b>%d</b><span>%s</span></div></div>',
					(int) $photos, esc_html( self::plural( $photos, 'zdjęcie', 'zdjęcia', 'zdjęć' ) ),
					(int) $groups, esc_html( self::plural( $groups, 'galeria', 'galerie', 'galerii' ) ),
					count( $cats ), esc_html( self::plural( count( $cats ), 'kategoria', 'kategorie', 'kategorii' ) )
				);
			}
			echo '</header>';
		}
		echo '<div class="fp-cats">';
		foreach ( $cats as $c ) {
			$pick = array();
			foreach ( $c['groups'] as $g ) {
				if ( empty( $g['other'] ) ) {
					$pick[] = self::cover( $g );
				}
				if ( count( $pick ) >= 3 ) {
					break;
				}
			}
			if ( ! empty( $c['mosaic'] ) ) {
				$pick = array_slice( array_merge( $c['mosaic'], $pick ), 0, 3 );
			}
			if ( count( $pick ) < 3 ) {
				$pick = array_slice( array_merge( $pick, $c['groups'][0]['photos'] ), 0, 3 );
			}
			echo '<a class="fp-cat" href="' . esc_url( self::url( $c['slug'] ) ) . '"><div class="fp-mosaic">';
			foreach ( $pick as $p ) {
				echo self::img( $p, '' ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</div><div class="fp-cat-txt"><div class="fp-meta"><h2 class="fp-h2">' . esc_html( $c['title'] ) . '</h2>';
			if ( $v['show_counts'] ) {
				echo '<span class="fp-count">' . esc_html( self::photos_word( $c['count'] ) ) . '</span>';
			}
			echo '</div>';
			$sub = trim( $c['desc'] . ( $v['show_counts'] ? ( $c['desc'] ? ' · ' : '' ) . self::groups_word( $c ) : '' ) );
			if ( $sub ) {
				echo '<p class="fp-cat-desc">' . esc_html( $sub ) . '</p>';
			}
			echo '</div></a>';
		}
		echo '</div>';
	}

	/* ---------- parametry przeglądania (z adresu strony) ---------- */

	public static function photo_sorts() {
		return array_map( array( 'FP_I18n', 't' ), array(
			'best'   => 'Najlepsze',
			'newest' => 'Najnowsze',
			'oldest' => 'Najstarsze',
			'author' => 'Autor A–Ż',
			'folder' => 'Jak w katalogach',
		) );
	}

	/** Filtry, sortowanie i strona z adresu (?okres[]=…&autor[]=…&sort=…&na=50&strona=2&q=…). */
	private static function req( $default_sort ) {
		$v    = FP_Settings::view_all();
		$get  = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
		$list = function ( $k ) use ( $get ) {
			return isset( $get[ $k ] ) ? array_values( array_filter( array_map( 'sanitize_title', (array) $get[ $k ] ) ) ) : array();
		};
		$sort = isset( $get['sort'] ) && isset( self::photo_sorts()[ $get['sort'] ] ) ? $get['sort'] : $default_sort;
		$na   = isset( $get['na'] ) && in_array( (string) $get['na'], array( '20', '50', '100' ), true ) ? (int) $get['na'] : (int) $v['per_page'];
		return array(
			'okres'  => $list( 'okres' ),
			'autor'  => $list( 'autor' ),
			'sort'   => $sort,
			'dsort'  => $default_sort,
			'na'     => $na,
			'strona' => max( 1, isset( $get['strona'] ) ? (int) $get['strona'] : 1 ),
			'q'      => isset( $get['q'] ) ? sanitize_text_field( $get['q'] ) : '',
		);
	}

	private static function period_slug( $g, $c ) {
		return FP_Scanner::slug( $g['period'] ? $g['period'] : $c['other'] );
	}

	public static function author_name( $a ) {
		return FP_People::canonical( $a );
	}

	/** Filtr okresów i autorów, sortowanie. @param array $items [[zdjęcie, grupa], …] */
	private static function filter_sort( $items, $c, $r ) {
		if ( $r['okres'] ) {
			$items = array_values( array_filter( $items, function ( $it ) use ( $c, $r ) { return in_array( self::period_slug( $it[1], $c ), $r['okres'], true ); } ) );
		}
		if ( $r['autor'] ) {
			$items = array_values( array_filter( $items, function ( $it ) use ( $r ) { return in_array( self::author_key( $it[0] ), $r['autor'], true ); } ) );
		}
		foreach ( $items as $i => &$it ) {
			$it[2] = $i; // kolejność w katalogach
		}
		unset( $it );
		$cover_rank = function ( $p, $g ) {
			foreach ( isset( $g['covers'] ) ? $g['covers'] : array() as $i => $cv ) {
				if ( $cv['f'] === $p['f'] ) {
					return 5 - $i;
				}
			}
			return 0;
		};
		$sort = $r['sort'];
		usort( $items, function ( $a, $b ) use ( $sort, $cover_rank ) {
			list( $pa, $ga, $ia ) = $a;
			list( $pb, $gb, $ib ) = $b;
			switch ( $sort ) {
				case 'best':
					$ka = array( ! empty( $pa['st'] ) ? 1 : 0, $cover_rank( $pa, $ga ), $ga['sort'] );
					$kb = array( ! empty( $pb['st'] ) ? 1 : 0, $cover_rank( $pb, $gb ), $gb['sort'] );
					if ( $ka !== $kb ) {
						return $ka < $kb ? 1 : -1;
					}
					break;
				case 'newest':
				case 'oldest':
					$da = isset( $pa['d'] ) ? $pa['d'] : $pa['m'];
					$db = isset( $pb['d'] ) ? $pb['d'] : $pb['m'];
					if ( $ga['sort'] !== $gb['sort'] && $ga['sort'] && $gb['sort'] ) {
						$c2 = $ga['sort'] < $gb['sort'] ? -1 : 1;
						return 'newest' === $sort ? -$c2 : $c2;
					}
					if ( $da !== $db ) {
						return ( $da < $db ? -1 : 1 ) * ( 'newest' === $sort ? -1 : 1 );
					}
					break;
				case 'author':
					$aa = self::author_name( $pa['a'] );
					$ab = self::author_name( $pb['a'] );
					if ( $aa !== $ab ) {
						if ( '' === $aa || '' === $ab ) {
							return '' === $aa ? 1 : -1;
						}
						return strcoll( $aa, $ab );
					}
					break;
			}
			return $ia - $ib;
		} );
		return $items;
	}

	const NO_AUTHOR = 'bez-podpisu';

	/** Klucz autora w filtrze (?autor[]=…); zdjęcia bez podpisu mają własny klucz. */
	private static function author_key( $p ) {
		$n = self::author_name( $p['a'] );
		return '' === $n ? self::NO_AUTHOR : sanitize_title( $n );
	}

	/**
	 * Autorzy w zestawie zdjęć: [slug => [nazwa, liczba]], liczone ze wszystkich zdjęć widoku
	 * (nie tylko z bieżącej strony). „Bez podpisu” na końcu, więc suma = liczba zdjęć.
	 */
	private static function author_counts( $items ) {
		$out  = array();
		$none = 0;
		foreach ( $items as $it ) {
			$n = self::author_name( $it[0]['a'] );
			if ( '' === $n ) {
				$none++;
				continue;
			}
			$p = FP_People::find( $n );
			if ( $p && ! empty( $p['hidden'] ) ) {
				continue;
			}
			$k = sanitize_title( $n );
			if ( ! isset( $out[ $k ] ) ) {
				$out[ $k ] = array( $n, 0 );
			}
			$out[ $k ][1]++;
		}
		uasort( $out, function ( $a, $b ) { return strcoll( $a[0], $b[0] ); } );
		if ( $none && $out ) {
			$out[ self::NO_AUTHOR ] = array( self::t( 'Bez podpisu' ), $none );
		}
		return $out;
	}

	/* ---------- pasek filtrów (formularz GET) ---------- */

	/**
	 * @param array $o periods (etykiety), authors (author_counts), show_sort, show_pp, show_q
	 */
	private static function toolbar( $c, $r, $o ) {
		$v      = FP_Settings::view_all();
		$conf   = FP_Settings::category( $c['dir'] );
		$f      = $conf['filters'];
		$parts  = array();
		$active = $r['okres'] || $r['autor'] || '' !== $r['q'] || $r['sort'] !== $r['dsort'];
		ob_start();
		if ( ! empty( $o['show_q'] ) && $f['search'] ) {
			printf( '<label class="fp-q"><span class="screen-reader-text">%s</span><input type="search" name="q" value="%s" placeholder="%s"></label>', esc_html( self::t( 'Szukaj wydarzenia' ) ), esc_attr( $r['q'] ), esc_attr( self::t( 'Szukaj wydarzenia…' ) ) );
		}
		if ( $v['show_jumps'] && $f['period'] && ! empty( $o['periods'] ) && count( $o['periods'] ) > 1 ) {
			self::period_filter( $o['periods'], $c, $r['okres'] );
		}
		if ( $v['show_author_filter'] && $f['author'] && ! empty( $o['authors'] ) && count( $o['authors'] ) > 1 ) {
			self::author_filter( $o['authors'], $r['autor'] );
		}
		if ( ! empty( $o['show_sort'] ) && $v['show_sort'] && $f['sort'] ) {
			echo '<label class="fp-sel"><span>' . esc_html( self::t( 'Sortuj' ) ) . '</span><select name="sort" data-fp-auto>';
			foreach ( self::photo_sorts() as $k => $l ) {
				if ( 'folder' === $k && 'folder' !== $r['dsort'] && empty( $o['folder_sort'] ) ) {
					continue;
				}
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $k, $r['sort'], false ), esc_html( $l ) );
			}
			echo '</select></label>';
		}
		if ( ! empty( $o['show_pp'] ) && $v['show_per_page'] ) {
			echo '<label class="fp-sel"><span>' . esc_html( self::t( 'Na stronie' ) ) . '</span><select name="na" data-fp-auto>';
			foreach ( array( 20, 50, 100 ) as $n ) {
				printf( '<option value="%d"%s>%d</option>', $n, selected( $n, $r['na'], false ), $n );
			}
			echo '</select></label>';
		}
		$inner = ob_get_clean();
		if ( '' === trim( $inner ) ) {
			return;
		}
		echo '<form class="fp-filters" method="get" action="' . esc_url( strtok( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), '?' ) ) . '" data-fp-filters>'; // phpcs:ignore
		if ( ! get_option( 'permalink_structure' ) && get_query_var( self::QV ) ) {
			printf( '<input type="hidden" name="%s" value="%s"><input type="hidden" name="page_id" value="%d">', esc_attr( self::QV ), esc_attr( get_query_var( self::QV ) ), (int) self::page_id() );
		}
		echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput -- zbudowane wyżej z escapowaniem
		echo '<noscript><button type="submit" class="fp-apply">' . esc_html( self::t( 'Zastosuj' ) ) . '</button></noscript>';
		if ( $active ) {
			echo '<a class="fp-clear" href="' . esc_url( strtok( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), '?' ) ) . '">' . esc_html( self::t( 'Wyczyść filtry' ) ) . '</a>'; // phpcs:ignore
		}
		echo '</form>';
	}

	/**
	 * Filtr okresów: rozwijane pole z polami wyboru pogrupowanymi po latach (2026 → Maj, Kwiecień…).
	 * Kilka zaznaczonych = zdjęcia lub wydarzenia z dowolnego z nich; nic = wszystko.
	 */
	private static function period_filter( $labels, $c, $chosen = array() ) {
		$years = array();
		foreach ( $labels as $label ) {
			if ( preg_match( '/^(.*\S)\s+(\d{4})$/u', $label, $m ) ) {
				$years[ $m[2] ][] = array( FP_Scanner::slug( $label ), $m[1] );
			} else {
				$years[ $label ] = array(); // rok (DKF), „Archiwum”, „Pozostałe”
			}
		}
		$n = count( $chosen );
		$summary = ! $n ? self::t( in_array( $c['mode'], array( 'numbered', 'years' ), true ) ? 'Wszystkie lata' : 'Wszystkie okresy' ) : ( $n <= 2 ? implode( ', ', array_map( function ( $s ) use ( $labels ) {
			foreach ( $labels as $l ) {
				if ( FP_Scanner::slug( $l ) === $s ) {
					return FP_I18n::period( $l );
				}
			}
			return $s;
		}, $chosen ) ) : sprintf( self::t( 'Okresy: %d' ), $n ) );
		echo '<details class="fp-filter' . ( $n ? ' is-active' : '' ) . '" data-fp-dd><summary><span class="fp-filter-label">' . esc_html( $summary ) . '</span></summary><div class="fp-filter-panel">';
		foreach ( $years as $year => $months ) {
			echo '<fieldset class="fp-filter-year">';
			if ( $months ) {
				$all_on = ! array_diff( wp_list_pluck( $months, 0 ), $chosen );
				printf( '<label class="fp-filter-y"><input type="checkbox" data-fp-year="%s"%s> %s</label><div class="fp-filter-months">', esc_attr( $year ), checked( $all_on, true, false ), esc_html( $year ) );
				foreach ( $months as $mo ) {
					printf( '<label><input type="checkbox" name="okres[]" value="%s" data-fp-in="%s"%s> %s</label>', esc_attr( $mo[0] ), esc_attr( $year ), checked( in_array( $mo[0], $chosen, true ), true, false ), esc_html( FP_I18n::month_pl( $mo[1] ) ) );
				}
				echo '</div>';
			} else {
				$s = FP_Scanner::slug( (string) $year );
				printf( '<label class="fp-filter-y"><input type="checkbox" name="okres[]" value="%s"%s> %s</label>', esc_attr( $s ), checked( in_array( $s, $chosen, true ), true, false ), esc_html( FP_I18n::period( (string) $year ) ) );
			}
			echo '</fieldset>';
		}
		echo self::filter_buttons() . '</div></details>'; // phpcs:ignore
	}

	private static function filter_buttons() {
		return '<div class="fp-filter-btns"><button type="button" class="fp-filter-clear">' . esc_html( self::t( 'Wyczyść' ) ) . '</button><button type="submit" class="fp-filter-ok">' . esc_html( self::t( 'Pokaż' ) ) . '</button></div>';
	}

	/** Filtr autorów: kilku naraz (LUB), z wyszukiwarką. */
	private static function author_filter( $authors, $chosen ) {
		$n       = count( $chosen );
		$summary = ! $n ? self::t( 'Wszyscy autorzy' ) : ( 1 === $n && isset( $authors[ $chosen[0] ] ) ? $authors[ $chosen[0] ][0] : sprintf( self::t( 'Autorzy: %d' ), $n ) );
		echo '<details class="fp-filter' . ( $n ? ' is-active' : '' ) . '" data-fp-dd><summary><span class="fp-filter-label">' . esc_html( $summary ) . '</span></summary><div class="fp-filter-panel">';
		printf( '<input type="search" class="fp-filter-search" placeholder="%s" aria-label="%s">', esc_attr( self::t( 'Szukaj autora…' ) ), esc_attr( self::t( 'Szukaj autora' ) ) );
		echo '<div class="fp-filter-list">';
		foreach ( $authors as $slug => $a ) {
			printf( '<label data-fp-name="%s"%s><input type="checkbox" name="autor[]" value="%s"%s> %s <span class="fp-filter-n">%d</span></label>', esc_attr( mb_strtolower( $a[0] ) ), self::NO_AUTHOR === $slug ? ' class="fp-filter-none"' : '', esc_attr( $slug ), checked( in_array( $slug, $chosen, true ), true, false ), esc_html( $a[0] ), (int) $a[1] );
		}
		echo '</div>' . self::filter_buttons() . '</div></details>'; // phpcs:ignore
	}

	/** Stronicowanie: ‹ 1 2 3 … 9 ›, z zachowaniem filtrów w adresie. */
	private static function pagination( $page, $pages, $total, $per ) {
		if ( $pages <= 1 ) {
			return;
		}
		$link = function ( $n ) {
			return esc_url( add_query_arg( 'strona', $n > 1 ? $n : false ) );
		};
		echo '<nav class="fp-pages" aria-label="' . esc_attr( self::t( 'Strony' ) ) . '">';
		if ( $page > 1 ) {
			echo '<a class="fp-pg fp-pg-prev" href="' . $link( $page - 1 ) . '" rel="prev">‹ <span>' . esc_html( self::t( 'Poprzednia' ) ) . '</span></a>'; // phpcs:ignore
		}
		$show = array_unique( array_filter( array( 1, 2, $page - 1, $page, $page + 1, $pages - 1, $pages ), function ( $n ) use ( $pages ) { return $n >= 1 && $n <= $pages; } ) );
		sort( $show );
		$prev = 0;
		foreach ( $show as $n ) {
			if ( $n - $prev > 1 ) {
				echo '<span class="fp-pg-gap">…</span>';
			}
			echo $n === $page ? '<span class="fp-pg is-current" aria-current="page">' . (int) $n . '</span>' : '<a class="fp-pg" href="' . $link( $n ) . '">' . (int) $n . '</a>'; // phpcs:ignore
			$prev = $n;
		}
		if ( $page < $pages ) {
			echo '<a class="fp-pg fp-pg-next" href="' . $link( $page + 1 ) . '" rel="next"><span>' . esc_html( self::t( 'Następna' ) ) . '</span> ›</a>'; // phpcs:ignore
		}
		echo '<span class="fp-pg-info">' . esc_html( sprintf( self::t( '%1$d–%2$d z %3$d' ), (int) ( ( $page - 1 ) * $per + 1 ), (int) min( $total, $page * $per ), (int) $total ) ) . '</span>';
		echo '</nav>';
	}

	/** Lista zdjęć z filtrami, sortowaniem i stronicowaniem. */
	private static function photo_list( $items, $c, $flat, $r, $periods ) {
		// liczby przy autorach: wszystkie zdjęcia widoku w wybranych okresach (filtr autorów nie zawęża własnej listy)
		$scope   = $r['okres'] ? array_values( array_filter( $items, function ( $it ) use ( $c, $r ) { return in_array( self::period_slug( $it[1], $c ), $r['okres'], true ); } ) ) : $items;
		$authors = self::author_counts( $scope );
		// zaznaczony autor bez zdjęć w wybranych okresach zostaje na liście (z zerem), żeby dało się go odznaczyć
		$all = null;
		foreach ( $r['autor'] as $s ) {
			if ( ! isset( $authors[ $s ] ) ) {
				$all = null === $all ? self::author_counts( $items ) : $all;
				if ( isset( $all[ $s ] ) ) {
					$authors[ $s ] = array( $all[ $s ][0], 0 );
				}
			}
		}
		if ( $all && isset( $authors[ self::NO_AUTHOR ] ) ) {
			$none = $authors[ self::NO_AUTHOR ];
			unset( $authors[ self::NO_AUTHOR ] );
			$authors[ self::NO_AUTHOR ] = $none;
		}
		$items   = self::filter_sort( $items, $c, $r );
		$total   = count( $items );
		$pages   = max( 1, (int) ceil( $total / $r['na'] ) );
		$page    = min( $r['strona'], $pages );
		self::toolbar( $c, $r, array(
			'periods'     => $flat ? $periods : array(),
			'authors'     => $authors,
			'show_sort'   => true,
			'show_pp'     => $total > 20 || $r['na'] !== (int) FP_Settings::view( 'per_page' ),
			'folder_sort' => ! $flat,
		) );
		if ( ! $total ) {
			echo '<p class="fp-empty">' . esc_html( self::t( 'Brak zdjęć dla wybranych filtrów.' ) ) . '</p>';
			return;
		}
		self::gallery( array_slice( $items, ( $page - 1 ) * $r['na'], $r['na'] ), $c, $flat );
		self::pagination( $page, $pages, $total, $r['na'] );
	}

	private static function view_category( $c, $all ) {
		$v = FP_Settings::view_all();
		echo self::crumbs( array( array( self::t( $v['title'] ), self::url() ), array( $c['title'], '' ) ) ); // phpcs:ignore
		echo '<h1 class="fp-h1">' . esc_html( $c['title'] ) . '</h1>';
		if ( ! empty( $c['subtitle'] ) ) {
			echo '<p class="fp-subtitle">' . esc_html( $c['subtitle'] ) . '</p>';
		}
		if ( $c['desc'] ) {
			echo '<p class="fp-lede">' . esc_html( $c['desc'] ) . '</p>';
		}

		$periods = array();
		if ( in_array( $c['mode'], array( 'months', 'numbered', 'years', 'dated' ), true ) ) {
			foreach ( $c['groups'] as $g ) {
				$k = $g['period'] ? $g['period'] : $c['other'];
				if ( ! isset( $periods[ $k ] ) ) {
					$periods[ $k ] = array();
				}
				$periods[ $k ][] = $g;
			}
		}

		echo '<div class="fp-bar">';
		if ( $v['show_all_view'] ) {
			echo '<nav class="fp-seg" aria-label="' . esc_attr( self::t( 'Widok' ) ) . '">';
			printf( '<a href="%s"%s>%s</a>', esc_url( self::url( $c['slug'] ) ), $all ? '' : ' aria-current="page"', esc_html( self::t( 'numbered' === $c['mode'] ? 'Edycje' : $v['label_events'] ) ) );
			printf( '<a href="%s"%s>%s <span>%d</span></a>', esc_url( self::url( $c['slug'], 'wszystkie' ) ), $all ? ' aria-current="page"' : '', esc_html( self::t( $v['label_all'] ) ), (int) $c['count'] );
			echo '</nav>';
		}
		$r = self::req( $all ? $v['photo_sort'] : 'folder' );
		if ( ! $all ) {
			self::toolbar( $c, $r, array( 'periods' => array_keys( $periods ), 'show_q' => true ) );
		}
		echo '</div>';

		if ( $all ) {
			$items = array();
			foreach ( $c['groups'] as $g ) {
				foreach ( $g['photos'] as $p ) {
					$items[] = array( $p, $g );
				}
			}
			self::photo_list( $items, $c, true, $r, array_keys( $periods ) );
			return;
		}

		// widok wydarzeń: filtr okresów i wyszukiwarka nazw
		$match = function ( $g ) use ( $r, $c ) {
			if ( $r['okres'] && ! in_array( self::period_slug( $g, $c ), $r['okres'], true ) ) {
				return false;
			}
			return '' === $r['q'] || false !== mb_stripos( self::group_title( $g, $c ) . ' ' . $g['period'], $r['q'] );
		};
		if ( $periods ) {
			$i     = 0;
			$shown = 0;
			foreach ( $periods as $k => $gs ) {
				$gs = array_values( array_filter( $gs, $match ) );
				if ( ! $gs ) {
					continue;
				}
				$shown++;
				$n = array_sum( array_map( function ( $g ) { return count( $g['photos'] ); }, $gs ) );
				printf( '<section class="fp-period" id="fp-p%d" data-fp-period="%s"><div class="fp-period-head"><h2 class="fp-h2">%s</h2>', $i++, esc_attr( FP_Scanner::slug( $k ) ), esc_html( FP_I18n::period( $k ) ) );
				if ( $v['show_counts'] ) {
					printf( '<span class="fp-count">%s · %s</span>', esc_html( self::groups_word( $c, count( $gs ) ) ), esc_html( self::photos_word( $n ) ) );
				}
				echo '</div>';
				self::cards( $gs, $c );
				echo '</section>';
			}
			if ( ! $shown ) {
				echo '<p class="fp-empty">' . esc_html( self::t( 'Brak wydarzeń dla wybranych filtrów.' ) ) . '</p>';
			}
		} else {
			$gs = array_values( array_filter( $c['groups'], $match ) );
			echo '<div class="fp-spaced">';
			$gs ? self::cards( $gs, $c ) : print( '<p class="fp-empty">' . esc_html( self::t( 'Brak wydarzeń dla wybranych filtrów.' ) ) . '</p>' );
			echo '</div>';
		}
	}

	private static function view_event( $c, $g ) {
		$v       = FP_Settings::view_all();
		$title   = self::group_title( $g, $c );
		$crumbs  = array( array( self::t( $v['title'] ), self::url() ), array( $c['title'], self::url( $c['slug'] ) ) );
		if ( $g['period'] && in_array( $c['mode'], array( 'months', 'years', 'dated' ), true ) ) {
			$crumbs[] = array( FP_I18n::period( $g['period'] ), add_query_arg( 'okres[]', self::period_slug( $g, $c ), self::url( $c['slug'] ) ) );
		}
		$crumbs[] = array( $title, '' );
		echo self::crumbs( $crumbs ); // phpcs:ignore

		$authors = self::authors( $g );
		echo '<div class="fp-ev-head"><div>';
		echo '<h1 class="fp-h1">' . esc_html( $title ) . '</h1>';
		if ( ! empty( $g['subtitle'] ) ) {
			echo '<p class="fp-subtitle">' . esc_html( $g['subtitle'] ) . '</p>';
		}
		$sub = array_filter( array( 'numbered' === $c['mode'] && ! empty( $g['num'] ) ? sprintf( self::t( 'Edycja %d' ), (int) $g['num'] ) : '', FP_I18n::period( $g['period'] ), self::photos_word( count( $g['photos'] ) ) ) );
		echo '<p class="fp-lede fp-lede-tight">' . esc_html( implode( ' · ', $sub ) ) . '</p>';
		if ( $g['desc'] ) {
			echo '<div class="fp-desc">' . wp_kses_post( wpautop( $g['desc'] ) ) . '</div>';
		}
		if ( $v['show_related'] && $g['rel'] ) {
			printf(
				'<a class="fp-see-also" href="%s">%s</a>',
				esc_url( self::url( $g['rel'][0], $g['rel'][1] ) ),
				esc_html( sprintf( self::t( 'Zobacz też: %1$s · %2$s →' ), self::rel_cat_title( $g['rel'][0], $g['rel'][2] ), $title ) )
			);
		}
		if ( $v['show_authors'] && $authors ) {
			$first = array_slice( $authors, 0, 4 );
			$rest  = array_slice( $authors, 4 );
			echo '<p class="fp-credits"><span class="fp-credits-l">' . esc_html( self::t( $v['label_photos'] ) ) . ':</span> ' . self::author_links( $first ); // phpcs:ignore
			if ( $rest ) {
				printf( '<details class="fp-credits-more"><summary>%s</summary>, %s</details>', esc_html( self::and_others( count( $rest ) ) ), self::author_links( $rest ) ); // phpcs:ignore
			}
			echo '</p>';
		}
		echo '</div>';
		echo '</div>';

		$items = array_map( function ( $p ) use ( $g ) { return array( $p, $g ); }, $g['photos'] );
		$r     = self::req( 'folder' );
		echo '<div class="fp-gallery-wrap">';
		self::photo_list( $items, $c, false, $r, array() );
		echo '</div>';

		$same = array_values( array_filter( $c['groups'], function ( $x ) use ( $g ) {
			return $x['key'] !== $g['key'] && $x['period'] === $g['period'] && '' !== $g['period'];
		} ) );
		$more = $same ? $same : array_values( array_filter( $c['groups'], function ( $x ) use ( $g ) { return $x['key'] !== $g['key']; } ) );
		$more = array_slice( $more, 0, 4 );
		if ( $more ) {
			echo '<section class="fp-more"><h2 class="fp-h2 fp-h2-sm">' . esc_html( $same ? sprintf( self::t( 'Więcej z: %s' ), FP_I18n::period( $g['period'] ) ) : sprintf( self::t( 'Więcej: %s' ), $c['title'] ) ) . '</h2>';
			self::cards( $more, $c );
			echo '</section>';
		}
	}

	/** Nazwiska z linkami do profili z rejestru fotografów. */
	private static function author_links( $names ) {
		$out = array();
		foreach ( $names as $n ) {
			$url   = FP_People::link( FP_People::find( $n ) );
			$out[] = $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><b>' . esc_html( $n ) . '</b></a>' : '<b>' . esc_html( $n ) . '</b>';
		}
		return implode( ', ', $out );
	}

	/* ---------- elementy ---------- */

	/** Okładki karty: wybrane w Eksploratorze, uzupełnione kolejnymi zdjęciami do żądanej liczby. */
	private static function card_covers( $g, $n ) {
		$list = isset( $g['covers'] ) ? $g['covers'] : array();
		if ( ! $list ) {
			$list = array( self::cover( $g ) );
		}
		$have = wp_list_pluck( $list, 'f' );
		foreach ( $g['photos'] as $p ) {
			if ( count( $list ) >= $n ) {
				break;
			}
			if ( ! in_array( $p['f'], $have, true ) ) {
				$list[] = $p;
			}
		}
		return array_slice( $list, 0, $n );
	}

	private static function cards( $groups, $c ) {
		$v        = FP_Settings::view_all();
		$numbered = 'numbered' === $c['mode'];
		$want     = max( 1, (int) $v['cover_count'] );
		echo '<div class="' . ( $numbered ? 'fp-events fp-events-pair' : 'fp-events' ) . '">';
		foreach ( $groups as $g ) {
			$title = self::group_title( $g, $c );
			echo '<a class="fp-ev" href="' . esc_url( self::url( $c['slug'], $g['key'] ) ) . '">';
			if ( 1 === $want && $numbered && count( $g['photos'] ) > 1 && empty( $g['cover_set'] ) ) {
				echo '<div class="fp-pair">' . self::img( $g['photos'][0], $title ) . self::img( $g['photos'][1], $title ) . '</div>'; // phpcs:ignore
				printf( '<span class="fp-num">%s</span>', esc_html( sprintf( self::t( '#%02d · temat' ), (int) ( isset( $g['num'] ) ? $g['num'] : 0 ) ) ) );
			} else {
				$covers = self::card_covers( $g, $want );
				echo '<div class="fp-cover fp-cover-m' . count( $covers ) . '">';
				foreach ( $covers as $p ) {
					echo self::img( $p, $title ); // phpcs:ignore
				}
				if ( $v['show_counts'] ) {
					echo '<span class="fp-n">' . count( $g['photos'] ) . '</span>';
				}
				echo '</div>';
			}
			// tytuł, linia z okresem i liczbą zdjęć, osobna linia autorów (w kafelkach pokazywana po najechaniu)
			echo '<div class="fp-ev-txt"><h3 class="fp-h3">' . esc_html( $title ) . '</h3>';
			$sub = array();
			$a   = self::authors( $g );
			$by  = '';
			if ( $numbered ) {
				$by = implode( ' · ', $a );
			} else {
				if ( $g['period'] && ! in_array( $c['mode'], array( 'months', 'years', 'dated' ), true ) ) { // tam okres jest nagłówkiem grupy
					$sub[] = FP_I18n::period( $g['period'] );
				}
				if ( $v['show_counts'] ) {
					$sub[] = self::photos_word( count( $g['photos'] ) );
				}
				if ( $v['show_authors'] && $a ) {
					$by = sprintf( self::t( 'fot. %s' ), implode( ', ', array_slice( $a, 0, 3 ) ) . ( count( $a ) > 3 ? ' ' . self::and_others( count( $a ) - 3 ) : '' ) );
				}
			}
			$sub = array_filter( $sub );
			if ( $sub ) {
				echo '<div class="fp-sub">' . esc_html( implode( ' · ', $sub ) ) . '</div>';
			}
			if ( '' !== $by ) {
				echo '<div class="fp-by">' . esc_html( $by ) . '</div>';
			}
			echo '</div></a>';
		}
		echo '</div>';
	}

	/** @param array $items [[zdjęcie, grupa], ...] */
	private static function gallery( $items, $c, $flat ) {
		$v = FP_Settings::view_all();
		echo '<div class="fp-gallery" data-fp-gallery' . ( $v['lightbox'] ? '' : ' data-fp-nolb' ) . '>';
		$anchors = FP_Links::ids( array_map( function ( $it ) { return $it[0]['f']; }, $items ) ); // kotwice #foto-… tej strony galerii
		foreach ( $items as $i => $it ) {
			list( $p, $g ) = $it;
			$gc            = isset( $g['_c'] ) ? $g['_c'] : $c; // widok wybranych katalogów: każde zdjęcie ze swojej kategorii
			$title         = self::group_title( $g, $gc );
			$author        = self::author_name( $p['a'] );
			$period        = FP_I18n::period( $g['period'] );
			$cap           = array( $title );
			if ( $period ) {
				$cap[] = $period;
			}
			if ( $v['show_authors'] && '' !== $author ) {
				$cap[] = sprintf( self::t( 'fot. %s' ), $author );
			}
			// link = oryginał (otwórz w nowej karcie / zapisz jako daje plik źródłowy),
			// lightbox pokazuje powiększenie 2048 px w tym samym formacie co oryginał
			$attrs = array(
				'class'                        => 'fp-ph' . ( ! empty( $p['st'] ) ? ' is-star' : '' ),
				'href'                         => FP_Thumbs::original_url( $p ),
				'data-fp-src'                  => FP_Thumbs::url( $p, 'l' ),
				'style'                        => '--r:' . round( $p['w'] / max( 1, $p['h'] ), 4 ),
				'data-fp-cap'                  => implode( ' · ', $cap ),
				'data-fp-n'                    => basename( $p['f'] ), // nazwa pliku w adresie lightboxa (#foto-…)
				'data-fp-id'                   => $anchors[ $i ],
				'data-elementor-open-lightbox' => 'no',
			);
			if ( $flat ) {
				$attrs['data-fp-ev'] = self::url( $gc['slug'], $g['key'] );
			}
			if ( self::can_edit() ) {
				$attrs['data-fp-key'] = $gc['slug'] . '/' . $g['key'];
				$attrs['data-fp-f']   = $p['f'];
			}
			echo '<a';
			foreach ( $attrs as $k => $val ) {
				echo ' ' . esc_attr( $k ) . '="' . ( in_array( $k, array( 'href', 'data-fp-src', 'data-fp-ev' ), true ) ? esc_url( $val ) : esc_attr( $val ) ) . '"';
			}
			echo '>' . self::img( $p, $title ); // phpcs:ignore
			$tag = $flat ? $title . ( $period ? ' · ' . $period : '' ) : ( $v['show_authors'] && '' !== $author ? sprintf( self::t( 'fot. %s' ), $author ) : '' );
			if ( '' !== $tag ) {
				echo '<span class="fp-tag">' . esc_html( $tag ) . '</span>';
			}
			echo '</a>';
		}
		echo '</div>';
	}

	private static function img( $p, $alt ) {
		$max = (int) FP_Settings::get( 'grid_size' );
		$s   = min( 1, $max / max( 1, $p['w'], $p['h'] ) );
		return sprintf(
			'<img src="%s" width="%d" height="%d" alt="%s" loading="lazy" decoding="async">',
			esc_url( FP_Thumbs::url( $p, 'g' ) ),
			(int) round( $p['w'] * $s ),
			(int) round( $p['h'] * $s ),
			esc_attr( $alt )
		);
	}

	private static function crumbs( $parts ) {
		$out = '<nav class="fp-crumbs" aria-label="' . esc_attr( self::t( 'Okruszki' ) ) . '">';
		foreach ( $parts as $i => $p ) {
			if ( $i ) {
				$out .= '<span class="fp-sep" aria-hidden="true">/</span>';
			}
			$out .= $p[1] ? '<a href="' . esc_url( $p[1] ) . '">' . esc_html( $p[0] ) . '</a>' : '<span aria-current="page">' . esc_html( $p[0] ) . '</span>';
		}
		return $out . '</nav>';
	}

	private static function period_anchor( $c, $period ) {
		$i = 0;
		$seen = array();
		foreach ( $c['groups'] as $g ) {
			$k = $g['period'] ? $g['period'] : $c['other'];
			if ( isset( $seen[ $k ] ) ) {
				continue;
			}
			if ( $k === $period ) {
				return 'fp-p' . $i;
			}
			$seen[ $k ] = 1;
			$i++;
		}
		return '';
	}

	private static function rel_cat_title( $slug, $fallback ) {
		$c = FP_Scanner::find_category( $slug );
		return $c ? $c['title'] : $fallback;
	}

	public static function group_title( $g, $c ) {
		return ! empty( $g['other'] ) ? self::t( $c['other'] ) : $g['title'];
	}

	public static function cover( $g ) {
		return ! empty( $g['cover'] ) ? $g['cover'] : $g['photos'][0];
	}

	private static function authors( $g ) {
		return array_values( array_unique( array_filter( array_map( function ( $p ) { return FP_People::canonical( $p['a'] ); }, $g['photos'] ) ) ) );
	}

	/** Forma liczby mnogiej (polskie formy; w innych językach ze słownika FP_I18n). */
	public static function plural( $n, $one, $few, $many ) {
		return FP_I18n::n( $n, $one, $few, $many );
	}

	/** „i 3 inne osoby” / „and 3 others” */
	private static function and_others( $n ) {
		return sprintf( self::t( 'i %s' ), $n . ' ' . self::plural( $n, 'inna osoba', 'inne osoby', 'innych osób' ) );
	}

	public static function photos_word( $n ) {
		return $n . ' ' . self::plural( $n, 'zdjęcie', 'zdjęcia', 'zdjęć' );
	}

	private static function groups_word( $c, $n = null ) {
		$n = null === $n ? count( $c['groups'] ) : $n;
		return 'numbered' === $c['mode']
			? $n . ' ' . self::plural( $n, 'edycja', 'edycje', 'edycji' )
			: $n . ' ' . self::plural( $n, 'wydarzenie', 'wydarzenia', 'wydarzeń' );
	}
}
