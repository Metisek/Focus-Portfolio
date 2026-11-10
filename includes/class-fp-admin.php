<?php
/**
 * Panel Focus Portfolio: menu boczne z podstronami (Pulpit, Eksplorator, Fotografowie, Struktura,
 * Wygląd, Ustawienia, Instrukcja), wspólne elementy (zwijane karty, zakładki) i zapis formularzy.
 * Widoki stron są w includes/admin/view-*.php.
 */

defined( 'ABSPATH' ) || exit;

class FP_Admin {

	const SLUG = 'focus-portfolio';

	/** slug => [tytuł, etykieta w menu, uprawnienie, plik widoku] */
	public static function pages() {
		return array(
			self::SLUG                => array( 'Focus Portfolio', 'Pulpit', FP_Caps::VIEW, 'dashboard' ),
			self::SLUG . '-explorer'  => array( 'Eksplorator zdjęć', 'Eksplorator', FP_Caps::VIEW, 'explorer' ),
			self::SLUG . '-people'    => array( 'Fotografowie i autorzy', 'Fotografowie', FP_Caps::VIEW, 'people' ),
			self::SLUG . '-structure' => array( 'Struktura katalogów', 'Struktura', FP_Caps::ADMIN, 'structure' ),
			self::SLUG . '-view'      => array( 'Wygląd portfolio', 'Wygląd', FP_Caps::ADMIN, 'view' ),
			self::SLUG . '-settings'  => array( 'Ustawienia', 'Ustawienia', FP_Caps::ADMIN, 'settings' ),
			self::SLUG . '-help'      => array( 'Instrukcja', 'Instrukcja', FP_Caps::VIEW, 'help' ),
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'save_settings', 'save_thumbs', 'save_perms', 'save_view', 'save_structure', 'save_people', 'reset_thumbs', 'create_page', 'elementor_fix' ) as $a ) {
			add_action( 'admin_post_fp_' . $a, array( __CLASS__, $a ) );
		}
		foreach ( array( 'scan', 'thumbs', 'upload', 'dirs', 'set_cover', 'ocr_list', 'ocr_save' ) as $a ) {
			add_action( 'wp_ajax_fp_' . $a, array( __CLASS__, 'ajax_' . $a ) );
		}
		add_filter( 'display_post_states', function ( $states, $post ) {
			if ( (int) FP_Settings::get( 'page_id' ) === (int) $post->ID ) {
				$states['focus_portfolio'] = 'Strona portfolio';
			}
			return $states;
		}, 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( FP_FILE ), function ( $links ) {
			array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">Panel</a>' );
			return $links;
		} );
		// stary adres z wersji 1.x (Ustawienia → Focus Portfolio, Wygląd → Portfolio)
		add_action( 'admin_init', function () {
			global $pagenow;
			$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore
			if ( ( 'options-general.php' === $pagenow && self::SLUG === $page ) || ( 'themes.php' === $pagenow && self::SLUG . '-view' === $page ) ) {
				wp_safe_redirect( self::url( 'options-general.php' === $pagenow ? '' : 'view' ) );
				exit;
			}
		} );
		add_action( 'admin_notices', array( __CLASS__, 'elementor_notice' ) );
	}

	public static function url( $page = '', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG . ( $page ? '-' . $page : '' ) ), $args ), admin_url( 'admin.php' ) );
	}

	public static function menu() {
		if ( ! FP_Caps::can( FP_Caps::VIEW ) ) {
			return;
		}
		$first = true;
		foreach ( self::pages() as $slug => $p ) {
			if ( $first ) {
				add_menu_page( $p[0], 'Focus Portfolio', FP_Caps::menu_cap( FP_Caps::VIEW ), $slug, array( __CLASS__, 'render' ), 'dashicons-format-gallery', 58 );
				$first = false;
			}
			if ( FP_Caps::can( $p[2] ) ) {
				add_submenu_page( self::SLUG, $p[0], $p[1], FP_Caps::menu_cap( $p[2] ), $slug, array( __CLASS__, 'render' ) );
			}
		}
		// skrót w menu Wygląd (zgodnie z konwencją WordPressa)
		if ( FP_Caps::can( FP_Caps::ADMIN ) ) {
			global $submenu;
			$submenu['themes.php'][] = array( 'Focus Portfolio', FP_Caps::menu_cap( FP_Caps::ADMIN ), self::url( 'view' ) ); // phpcs:ignore
		}
	}

	public static function current() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		$all  = self::pages();
		return isset( $all[ $page ] ) ? array( $page, $all[ $page ] ) : array( '', null );
	}

	public static function assets( $hook ) {
		list( $slug, $p ) = self::current();
		if ( ! $p ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'fp-admin', FP_URL . 'assets/css/admin.css', array(), fp_ver( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'fp-admin', FP_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), fp_ver( 'assets/js/admin.js' ), true );
		wp_localize_script( 'fp-admin', 'FP_ADMIN', array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fp_admin' ),
			'tree'    => 'dashboard' === $p[3] ? FP_Importer::tree() : array(),
			'maxSize' => wp_max_upload_size(),
			'can'     => array( 'edit' => FP_Caps::can( FP_Caps::EDIT ), 'admin' => FP_Caps::can( FP_Caps::ADMIN ) ),
			'page'    => $p[3],
			'ocrSkip' => array_values( array_filter( array_map( function ( $w ) { return mb_strtoupper( trim( $w ) ); }, explode( ',', (string) FP_Settings::get( 'ocr_skip' ) ) ) ) ),
		) );
		if ( 'people' === $p[3] ) {
			wp_enqueue_script( 'fp-admin-tools', FP_URL . 'assets/js/admin-tools.js', array( 'jquery', 'fp-admin' ), fp_ver( 'assets/js/admin-tools.js' ), true );
		}
		if ( 'explorer' === $p[3] ) {
			wp_enqueue_style( 'fp-explorer', FP_URL . 'assets/css/explorer.css', array( 'fp-admin' ), fp_ver( 'assets/css/explorer.css' ) );
			wp_enqueue_script( 'fp-explorer', FP_URL . 'assets/js/explorer.js', array( 'fp-admin' ), fp_ver( 'assets/js/explorer.js' ), true );
		}
	}

	/** Wspólny szkielet każdej podstrony: nagłówek, zakładki między podstronami, komunikaty, widok. */
	public static function render() {
		list( $slug, $p ) = self::current();
		if ( ! $p || ! FP_Caps::can( $p[2] ) ) {
			wp_die( 'Brak uprawnień do tej części Focus Portfolio.' );
		}
		$pid = (int) FP_Settings::get( 'page_id' );
		echo '<div class="wrap fp-admin fp-page-' . esc_attr( $p[3] ) . '">';
		echo '<h1 class="fp-title">' . esc_html( $p[0] );
		if ( $pid ) {
			echo ' <a class="page-title-action" href="' . esc_url( get_permalink( $pid ) ) . '" target="_blank">Zobacz portfolio</a>';
		}
		echo '</h1><nav class="fp-nav" aria-label="Focus Portfolio">';
		foreach ( self::pages() as $s => $pp ) {
			if ( FP_Caps::can( $pp[2] ) ) {
				printf( '<a href="%s"%s>%s</a>', esc_url( admin_url( 'admin.php?page=' . $s ) ), $s === $slug ? ' aria-current="page"' : '', esc_html( $pp[1] ) );
			}
		}
		echo '</nav>';
		self::notice();
		include FP_DIR . 'includes/admin/view-' . $p[3] . '.php';
		echo '</div>';
	}

	/** Zwijana karta; stan zapamiętuje przeglądarka (admin.js). */
	public static function card( $id, $title, $open = true, $extra_class = '' ) {
		printf(
			'<details class="fp-card %s" id="fp-card-%s" data-card="%s"%s><summary><h2>%s</h2></summary><div class="fp-card-body">',
			esc_attr( $extra_class ),
			esc_attr( $id ),
			esc_attr( $id ),
			$open ? ' open' : '',
			esc_html( $title )
		);
	}

	public static function card_end() {
		echo '</div></details>';
	}

	public static function readonly_note() {
		if ( ! FP_Caps::can( FP_Caps::EDIT ) ) {
			echo '<p class="fp-ro">Masz uprawnienia tylko do podglądu. Zmiany może wprowadzić osoba z uprawnieniem „Redakcja”.</p>';
		}
	}

	/* ---------- strona portfolio ---------- */

	public static function page_field( $name, $id ) {
		$pid = (int) FP_Settings::get( 'page_id' );
		?>
		<div class="fp-field">
			<label for="<?php echo esc_attr( $id ); ?>">Strona portfolio</label>
			<?php
			wp_dropdown_pages( array(
				'name'              => $name, // phpcs:ignore
				'id'                => $id,   // phpcs:ignore
				'selected'          => $pid,
				'show_option_none'  => '— wybierz stronę —',
				'option_none_value' => 0,
				'post_status'       => array( 'publish', 'draft', 'private' ),
			) );
			?>
			<p class="description">Tu stoi portfolio i z niej wychodzą podstrony kategorii i wydarzeń
				(<code><?php echo esc_html( wp_parse_url( $pid ? get_permalink( $pid ) : home_url( '/portfolio/' ), PHP_URL_PATH ) ); ?>fotorelacje/…</code>), generowane z katalogów.
				Układ strony (sekcje, nagłówki, marginesy) ustawiasz w Elementorze: wstaw widżet <b>Focus Portfolio</b>, a pokaże on to, czego dotyczy adres.</p>
			<?php if ( ! $pid ) : ?>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fp_create_page' ), 'fp_create_page' ) ); ?>">Utwórz stronę „Portfolio”</a></p>
			<?php endif; ?>
			<?php self::elementor_status( $pid ); ?>
		</div>
		<?php
	}

	/** Strona portfolio w Elementorze bez widżetu: w edytorze widać starą treść (np. „TBA”), a na stronie portfolio. */
	public static function elementor_state( $pid ) {
		if ( ! $pid || ! defined( 'ELEMENTOR_VERSION' ) || 'builder' !== get_post_meta( $pid, '_elementor_edit_mode', true ) ) {
			return '';
		}
		return FP_Frontend::embeds( $pid ) ? 'ok' : 'missing';
	}

	private static function elementor_status( $pid ) {
		$st = self::elementor_state( $pid );
		if ( 'missing' === $st ) {
			echo '<div class="fp-callout"><p><b>Strona jest zbudowana w Elementorze, ale nie ma widżetu Focus Portfolio.</b> Na stronie portfolio zastępuje całą treść, a w edytorze Elementora widać starą treść (np. „TBA”).</p>';
			if ( FP_Caps::can( FP_Caps::ADMIN ) ) {
				echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fp_elementor_fix' ), 'fp_elementor_fix' ) ) . '">Wstaw widżet Focus Portfolio do strony</a> ';
				echo '<span class="description">Dodaje sekcję z widżetem na górze strony i usuwa sam tekst „TBA”. Resztę treści zostawia.</span></p>';
			}
			echo '</div>';
		} elseif ( 'ok' === $st ) {
			echo '<p class="fp-ok">Strona ma widżet Focus Portfolio: to, co widzisz w Elementorze, jest na stronie. <a href="' . esc_url( admin_url( 'post.php?post=' . $pid . '&action=elementor' ) ) . '">Edytuj w Elementorze</a></p>';
		}
	}

	public static function elementor_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$pid    = (int) FP_Settings::get( 'page_id' );
		if ( ! $screen || 'page' !== $screen->id || ! isset( $_GET['post'] ) || (int) $_GET['post'] !== $pid || 'missing' !== self::elementor_state( $pid ) ) { // phpcs:ignore
			return;
		}
		echo '<div class="notice notice-info"><p>To jest strona portfolio. Wstaw na nią widżet <b>Focus Portfolio</b> w Elementorze, żeby w edytorze widzieć portfolio. <a href="' . esc_url( self::url( 'settings' ) ) . '">Zrób to automatycznie</a></p></div>';
	}

	/** Dodaje kontener z widżetem na górze strony Elementora i usuwa widżety tekstowe zawierające tylko „TBA”. */
	public static function elementor_fix() {
		self::guard_post( FP_Caps::ADMIN, 'fp_elementor_fix' );
		$pid  = (int) FP_Settings::get( 'page_id' );
		$data = json_decode( (string) get_post_meta( $pid, '_elementor_data', true ), true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$strip = function ( $els ) use ( &$strip ) {
			$out = array();
			foreach ( $els as $el ) {
				$txt = isset( $el['settings']['editor'] ) ? trim( wp_strip_all_tags( $el['settings']['editor'] ) ) : ( isset( $el['settings']['title'] ) ? trim( wp_strip_all_tags( $el['settings']['title'] ) ) : null );
				if ( isset( $el['elType'] ) && 'widget' === $el['elType'] && 'TBA' === $txt ) {
					continue;
				}
				if ( ! empty( $el['elements'] ) ) {
					$el['elements'] = $strip( $el['elements'] );
				}
				$out[] = $el;
			}
			return $out;
		};
		$data = $strip( $data );
		$id   = function () {
			return substr( md5( wp_generate_password( 12, false ) ), 0, 7 );
		};
		$widget = array( 'id' => $id(), 'elType' => 'widget', 'widgetType' => 'focus-portfolio', 'settings' => array(), 'elements' => array() );
		$use_containers = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->experiments && \Elementor\Plugin::$instance->experiments->is_feature_active( 'container' );
		if ( $use_containers ) {
			$wrap = array( 'id' => $id(), 'elType' => 'container', 'settings' => array(), 'elements' => array( $widget ), 'isInner' => false );
		} else {
			$wrap = array( 'id' => $id(), 'elType' => 'section', 'settings' => array(), 'elements' => array( array( 'id' => $id(), 'elType' => 'column', 'settings' => array( '_column_size' => 100 ), 'elements' => array( $widget ), 'isInner' => false ) ), 'isInner' => false );
		}
		array_unshift( $data, $wrap );
		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		self::back( 'settings', 'elementor' );
	}

	public static function create_page() {
		self::guard_post( FP_Caps::ADMIN, 'fp_create_page' );
		$existing = get_page_by_path( 'portfolio' );
		$id       = $existing ? $existing->ID : wp_insert_post( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Portfolio',
			'post_name'   => 'portfolio',
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			FP_Settings::update( array( 'page_id' => (int) $id ) );
			update_option( 'fp_flush_rewrite', 1 );
		}
		self::back( 'settings', 'saved' );
	}

	/* ---------- zapis formularzy ---------- */

	private static function guard_post( $cap, $nonce ) {
		if ( ! FP_Caps::can( $cap ) ) {
			wp_die( 'Brak uprawnień.' );
		}
		check_admin_referer( $nonce );
	}

	private static function back( $page, $msg, $args = array() ) {
		wp_safe_redirect( self::url( $page, array_merge( array( 'fp_msg' => $msg ), $args ) ) );
		exit;
	}

	public static function save_settings() {
		self::guard_post( FP_Caps::ADMIN, 'fp_save_settings' );
		$in  = isset( $_POST['fp'] ) ? (array) wp_unslash( $_POST['fp'] ) : array(); // phpcs:ignore
		$old = FP_Settings::all();
		$src = trim( preg_replace( '#\.\.+|[^\p{L}\p{N} _./-]#u', '', str_replace( '\\', '/', (string) ( isset( $in['source'] ) ? $in['source'] : '' ) ) ), '/ ' );
		if ( ! FP_Settings::source_allowed( $src ) || ! is_dir( WP_CONTENT_DIR . '/' . $src ) ) {
			self::back( 'settings', 'source_bad' );
		}
		$new = array(
			'source'        => $src,
			'page_id'       => absint( isset( $in['page_id'] ) ? $in['page_id'] : 0 ),
			'academic_year' => max( 2015, min( 2100, (int) ( isset( $in['academic_year'] ) ? $in['academic_year'] : 2025 ) ) ),
			'season_start'  => max( 1, min( 12, (int) ( isset( $in['season_start'] ) ? $in['season_start'] : 1 ) ) ),
			'ignore_artists' => sanitize_textarea_field( isset( $in['ignore_artists'] ) ? $in['ignore_artists'] : '' ),
			'ocr_skip'      => sanitize_text_field( isset( $in['ocr_skip'] ) ? $in['ocr_skip'] : '' ),
			'language'      => isset( $in['language'] ) && isset( FP_I18n::LANGS[ $in['language'] ] ) ? $in['language'] : '',
		);
		FP_Settings::update( $new );
		if ( $new['page_id'] !== (int) $old['page_id'] ) {
			update_option( 'fp_flush_rewrite', 1 );
		}
		$msg   = 'saved';
		$fresh = $new['ignore_artists'] !== (string) $old['ignore_artists']; // autorzy z EXIF od nowa
		if ( $fresh || $new['source'] !== $old['source'] || $new['academic_year'] !== (int) $old['academic_year'] || $new['season_start'] !== (int) $old['season_start'] ) {
			$r   = FP_Scanner::scan( $fresh );
			$msg = is_wp_error( $r ) ? 'source_bad' : 'scanned';
		}
		self::back( 'settings', $msg );
	}

	public static function save_thumbs() {
		self::guard_post( FP_Caps::ADMIN, 'fp_save_thumbs' );
		$in  = isset( $_POST['fp'] ) ? (array) wp_unslash( $_POST['fp'] ) : array(); // phpcs:ignore
		$old = FP_Settings::all();
		$new = array(
			'grid_size'  => max( 300, min( 2400, (int) $in['grid_size'] ) ),
			'large_size' => max( 1000, min( 4000, (int) $in['large_size'] ) ),
			'quality'    => max( 40, min( 95, (int) $in['quality'] ) ),
			'cron_batch' => max( 1, min( 200, (int) $in['cron_batch'] ) ),
			'ondemand'   => empty( $in['ondemand'] ) ? 0 : 1,
		);
		FP_Settings::update( $new );
		if ( $new['grid_size'] !== (int) $old['grid_size'] || $new['quality'] !== (int) $old['quality'] ) {
			FP_Thumbs::reset_size( 'g' );
		}
		if ( $new['large_size'] !== (int) $old['large_size'] ) {
			FP_Thumbs::reset_size( 'l' );
		}
		self::back( 'settings', 'saved', array( 'tab' => 'thumbs' ) );
	}

	public static function save_perms() {
		self::guard_post( FP_Caps::ADMIN, 'fp_save_perms' );
		$m = isset( $_POST['caps'] ) ? (array) wp_unslash( $_POST['caps'] ) : array(); // phpcs:ignore
		FP_Caps::save_matrix( $m );
		if ( ! empty( $_POST['create_role'] ) ) { // phpcs:ignore
			FP_Caps::install();
		}
		self::back( 'settings', 'saved', array( 'tab' => 'perms' ) );
	}

	public static function reset_thumbs() {
		self::guard_post( FP_Caps::ADMIN, 'fp_reset_thumbs' );
		if ( ! empty( $_POST['confirm'] ) ) { // phpcs:ignore
			FP_Thumbs::reset();
		}
		self::back( 'settings', 'reset', array( 'tab' => 'thumbs' ) );
	}

	public static function save_view() {
		self::guard_post( FP_Caps::ADMIN, 'fp_save_view' );
		$in  = isset( $_POST['fv'] ) ? (array) wp_unslash( $_POST['fv'] ) : array(); // phpcs:ignore
		$d   = FP_Settings::view_defaults();
		$out = array();
		$enums = array(
			'variant'     => array( 'classic', 'tiles' ),
			'gallery'     => array( 'masonry', 'grid', 'justified' ),
			'cols_mode'   => array( 'fixed', 'auto' ),
			'cards_mode'  => array( 'fixed', 'auto' ),
			'cover_fit'   => array( 'cover', 'contain' ),
			'sort'        => array( 'newest', 'oldest' ),
			'card_ratio'  => array( '3/2', '4/3', '1/1', '16/9', '4/5' ),
			'cover_count' => array( '1', '3', '5' ),
			'per_page'    => array( '20', '50', '100' ),
			'photo_sort'  => array( 'best', 'newest', 'oldest', 'author', 'folder' ),
		);
		foreach ( $enums as $k => $allowed ) {
			$out[ $k ] = isset( $in[ $k ] ) && in_array( (string) $in[ $k ], $allowed, true ) ? (string) $in[ $k ] : $d[ $k ];
		}
		$ranges = array(
			'columns' => array( 1, 8 ), 'columns_t' => array( 1, 6 ), 'columns_m' => array( 1, 4 ), 'cols_min' => array( 1, 8 ), 'cols_max' => array( 1, 8 ), 'tile_min' => array( 120, 600 ),
			'ccols' => array( 1, 6 ), 'ccols_t' => array( 1, 5 ), 'ccols_m' => array( 1, 3 ), 'cards_min' => array( 1, 6 ), 'cards_max' => array( 1, 6 ), 'card_min' => array( 140, 420 ),
			'gap' => array( 0, 40 ), 'max_width' => array( 0, 2400 ), 'side_pad' => array( 0, 120 ), 'row_height' => array( 120, 480 ),
		);
		foreach ( $ranges as $k => $r ) {
			$out[ $k ] = max( $r[0], min( $r[1], isset( $in[ $k ] ) ? (int) $in[ $k ] : $d[ $k ] ) );
		}
		$out['cols_max']  = max( $out['cols_min'], $out['cols_max'] );
		$out['cards_max'] = max( $out['cards_min'], $out['cards_max'] );
		foreach ( array( 'show_header', 'show_stats', 'show_all_view', 'show_jumps', 'show_counts', 'show_authors', 'show_related', 'lightbox', 'lb_download', 'show_author_filter', 'show_sort', 'show_per_page', 'even_cols' ) as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		$c             = isset( $in['accent'] ) ? sanitize_hex_color( $in['accent'] ) : '';
		$out['accent'] = $c ? $c : $d['accent'];
		foreach ( array( 'text', 'muted', 'line', 'soft' ) as $k ) {
			// puste = z motywu strony
			$out[ $k ] = isset( $in[ $k ] ) ? (string) sanitize_hex_color( $in[ $k ] ) : '';
		}
		foreach ( array( 'title', 'heading_font', 'label_events', 'label_all', 'label_photos', 'label_other' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) && '' !== trim( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : ( 'heading_font' === $k ? '' : $d[ $k ] );
		}
		$out['intro'] = isset( $in['intro'] ) ? sanitize_textarea_field( $in['intro'] ) : '';
		FP_Settings::update_view( $out );
		self::back( 'view', 'saved' );
	}

	public static function save_structure() {
		self::guard_post( FP_Caps::ADMIN, 'fp_save_structure' );
		$in   = isset( $_POST['fs'] ) ? (array) wp_unslash( $_POST['fs'] ) : array(); // phpcs:ignore
		$old  = FP_Settings::structure();
		$cats = array();
		foreach ( (array) ( isset( $in['categories'] ) ? $in['categories'] : array() ) as $dir => $c ) {
			$dir          = FP_Importer::clean_segment( $dir );
			$cats[ $dir ] = array(
				'title'   => sanitize_text_field( isset( $c['title'] ) ? $c['title'] : '' ),
				'slug'    => isset( $old['categories'][ $dir ]['slug'] ) ? sanitize_title( $old['categories'][ $dir ]['slug'] ) : '', // adres zostaje
				'desc'    => sanitize_text_field( isset( $c['desc'] ) ? $c['desc'] : '' ),
				'mode'    => isset( $c['mode'] ) && in_array( $c['mode'], array( 'auto', 'months', 'numbered', 'events', 'years', 'dated' ), true ) ? $c['mode'] : 'auto',
				'other'   => sanitize_text_field( isset( $c['other'] ) ? $c['other'] : '' ),
				'order'   => (int) ( isset( $c['order'] ) ? $c['order'] : 0 ),
				'hidden'  => empty( $c['hidden'] ) ? 0 : 1,
				'filters' => array(
					'period' => empty( $c['f_period'] ) ? 0 : 1,
					'author' => empty( $c['f_author'] ) ? 0 : 1,
					'sort'   => empty( $c['f_sort'] ) ? 0 : 1,
					'search' => empty( $c['f_search'] ) ? 0 : 1,
				),
			);
		}
		$new = array(
			'categories'   => $cats,
			'auto_archive' => empty( $in['auto_archive'] ) ? 0 : 1,
			'date_policy'  => isset( $in['date_policy'] ) && in_array( $in['date_policy'], array( 'inbox', 'archive', 'today' ), true ) ? $in['date_policy'] : 'inbox',
		);
		FP_Settings::update_structure( $new );
		if ( wp_list_pluck( $cats, 'mode' ) !== wp_list_pluck( (array) $old['categories'], 'mode' ) ) {
			FP_Scanner::scan();
		}
		self::back( 'structure', 'saved' );
	}

	public static function save_people() {
		self::guard_post( FP_Caps::EDIT, 'fp_save_people' );
		$rows  = isset( $_POST['people'] ) ? (array) wp_unslash( $_POST['people'] ) : array(); // phpcs:ignore
		$match = ! empty( $_POST['match_users'] ); // phpcs:ignore
		$list  = array();
		$strip = array();
		foreach ( $rows as $r ) {
			$name = trim( sanitize_text_field( isset( $r['name'] ) ? $r['name'] : '' ) );
			$orig = trim( sanitize_text_field( isset( $r['orig'] ) ? $r['orig'] : '' ) );
			$del  = isset( $r['del'] ) ? (int) $r['del'] : 0;
			if ( 2 === $del ) {
				$strip[] = '' !== $orig ? $orig : $name; // podpis autora znika też ze zdjęć
			}
			if ( $del || '' === $name ) {
				continue;
			}
			if ( $match && empty( $r['user_id'] ) ) {
				$r['user_id'] = FP_People::suggest_user( $name );
			}
			// poprawione imię i nazwisko: stary zapis zostaje aliasem, więc podpisy na zdjęciach dalej wskazują tę osobę
			$renamed = '' !== $orig && $orig !== $name;
			if ( $renamed ) {
				$r['aliases'] = trim( ( isset( $r['aliases'] ) ? $r['aliases'] : '' ) . ', ' . $orig, ', ' );
			}
			$has = $renamed || ! empty( $r['saved'] ) || ! empty( $r['user_id'] ) || ! empty( $r['hidden'] );
			foreach ( array( 'aliases', 'ig', 'fb', 'www' ) as $k ) {
				$has = $has || '' !== trim( isset( $r[ $k ] ) ? (string) $r[ $k ] : '' );
			}
			if ( $has ) {
				$list[] = $r; // osoby z samym podpisem i bez danych nie muszą być zapisane: i tak są na liście
			}
		}
		$n = $strip ? FP_Scanner::strip_authors( $strip ) : 0;
		FP_People::save( $list );
		if ( $n ) {
			FP_Scanner::scan();
			self::back( 'people', 'stripped', array( 'n' => $n ) );
		}
		self::back( 'people', 'saved' );
	}

	/* ---------- AJAX ---------- */

	public static function ajax_guard( $cap ) {
		check_ajax_referer( 'fp_admin', 'nonce' );
		if ( ! FP_Caps::can( $cap ) ) {
			wp_send_json_error( array( 'message' => 'Brak uprawnień do tej operacji.' ), 403 );
		}
	}

	public static function ajax_scan() {
		self::ajax_guard( FP_Caps::EDIT );
		$r = FP_Scanner::scan();
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		wp_send_json_success( array_merge( $r['stats'], FP_Thumbs::status(), array( 'issues' => count( $r['issues'] ), 'tree' => FP_Importer::tree() ) ) );
	}

	public static function ajax_thumbs() {
		self::ajax_guard( FP_Caps::EDIT );
		wp_send_json_success( FP_Thumbs::process_batch( 6, 20 ) );
	}

	public static function ajax_upload() {
		self::ajax_guard( FP_Caps::EDIT );
		$dir = FP_Importer::target(
			isset( $_POST['cat'] ) ? wp_unslash( $_POST['cat'] ) : '',     // phpcs:ignore
			isset( $_POST['sub'] ) ? wp_unslash( $_POST['sub'] ) : '',     // phpcs:ignore
			isset( $_POST['event'] ) ? wp_unslash( $_POST['event'] ) : ''  // phpcs:ignore
		);
		if ( is_wp_error( $dir ) ) {
			wp_send_json_error( array( 'message' => $dir->get_error_message() ) );
		}
		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => 'Brak pliku.' ) );
		}
		$r = FP_Importer::import_upload( $_FILES['file'], $dir ); // phpcs:ignore
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		wp_send_json_success( $r );
	}

	/** Podkatalogi w wp-content do wyboru źródła (drzewo ładowane na żądanie). */
	public static function ajax_dirs() {
		self::ajax_guard( FP_Caps::ADMIN );
		$rel  = trim( str_replace( '\\', '/', (string) ( isset( $_POST['dir'] ) ? wp_unslash( $_POST['dir'] ) : '' ) ), '/' ); // phpcs:ignore
		$base = realpath( WP_CONTENT_DIR );
		$abs  = realpath( WP_CONTENT_DIR . ( '' === $rel ? '' : '/' . $rel ) );
		if ( ! $abs || 0 !== strpos( $abs, $base ) || false !== strpos( $rel, '..' ) ) {
			wp_send_json_error( array( 'message' => 'Niepoprawny katalog.' ) );
		}
		$out = array();
		foreach ( (array) @scandir( $abs ) as $f ) { // phpcs:ignore
			if ( '' === $f || '.' === $f[0] || ! is_dir( $abs . '/' . $f ) ) {
				continue;
			}
			$r     = ( '' === $rel ? '' : $rel . '/' ) . $f;
			$out[] = array(
				'name'    => $f,
				'path'    => $r,
				'allowed' => FP_Settings::source_allowed( $r ),
				'images'  => count( preg_grep( FP_Scanner::IMG_RE, (array) @scandir( $abs . '/' . $f ) ) ), // phpcs:ignore
			);
		}
		usort( $out, function ( $a, $b ) { return strnatcasecmp( $a['name'], $b['name'] ); } );
		wp_send_json_success( array( 'dirs' => $out ) );
	}

	/** Okładka z lightboxa na stronie: zdjęcie trafia na początek listy okładek wydarzenia (max 5). */
	public static function ajax_set_cover() {
		self::ajax_guard( FP_Caps::EDIT );
		$cat = isset( $_POST['cat'] ) ? sanitize_title( wp_unslash( $_POST['cat'] ) ) : '';     // phpcs:ignore
		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore
		$f   = isset( $_POST['f'] ) ? (string) wp_unslash( $_POST['f'] ) : '';                  // phpcs:ignore
		$c   = FP_Scanner::find_category( $cat, true );
		$g   = $c ? FP_Scanner::find_group( $c, $key ) : null;
		if ( ! $g || ! in_array( $f, wp_list_pluck( $g['photos'], 'f' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Nie znaleziono zdjęcia w tym wydarzeniu.' ) );
		}
		$r = FP_Scanner::set_group_meta( $g, 'covers', function ( $list ) use ( $f, $g ) {
			$rel = substr( $f, strlen( $g['mdir'] ) + 1 );
			$list = array_values( array_diff( (array) $list, array( $rel ) ) );
			array_unshift( $list, $rel );
			return array_slice( $list, 0, 5 );
		} );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => 'Ustawiono okładkę: ' . FP_Frontend::group_title( $g, $c ) . '.' ) );
	}

	/* ---------- autorzy z OCR ---------- */

	public static function ajax_ocr_list() {
		self::ajax_guard( FP_Caps::EDIT );
		$all   = ! empty( $_POST['all'] ); // phpcs:ignore
		$dirs  = isset( $_POST['dirs'] ) ? array_filter( array_map( 'strval', (array) wp_unslash( $_POST['dirs'] ) ) ) : array(); // phpcs:ignore
		$items = array();
		$known = array();
		foreach ( FP_Scanner::categories( true ) as $c ) {
			foreach ( $c['groups'] as $g ) {
				$in_scope = ! $dirs;
				foreach ( $dirs as $d ) {
					if ( 0 === strpos( $g['mdir'] . '/', rtrim( $d, '/' ) . '/' ) || 0 === strpos( $g['photos'][0]['f'], rtrim( $d, '/' ) . '/' ) ) {
						$in_scope = true;
					}
				}
				foreach ( $g['photos'] as $p ) {
					if ( '' !== $p['a'] ) {
						$known[ $p['a'] ] = 1;
					}
					if ( $in_scope && ( $all || ( '' === $p['a'] && empty( $p['ax'] ) ) ) ) { // podpis usunięty celowo nie wraca z OCR
						$items[] = array( 'f' => $p['f'], 'src' => FP_Thumbs::url( $p, 'l' ), 'current' => $p['a'], 'event' => FP_Frontend::group_title( $g, $c ) );
					}
				}
			}
		}
		foreach ( FP_People::all() as $person ) {
			$known[ $person['name'] ] = 1;
		}
		wp_send_json_success( array( 'items' => $items, 'known' => array_keys( $known ) ) );
	}

	public static function ajax_ocr_save() {
		self::ajax_guard( FP_Caps::EDIT );
		$in = isset( $_POST['items'] ) ? (array) wp_unslash( $_POST['items'] ) : array(); // phpcs:ignore
		$n  = 0;
		foreach ( $in as $it ) {
			$f = isset( $it['f'] ) ? (string) $it['f'] : '';
			$a = isset( $it['a'] ) ? sanitize_text_field( $it['a'] ) : '';
			if ( '' === $f || false !== strpos( $f, '..' ) ) {
				continue;
			}
			if ( ! is_wp_error( FP_Scanner::set_author( $f, $a ) ) && '' !== $a ) {
				$n++;
			}
		}
		FP_Scanner::scan();
		wp_send_json_success( array( 'message' => sprintf( 'Zapisano autorów %d zdjęć. Pojawią się na stronie od razu.', $n ) ) );
	}

	/* ---------- komunikaty ---------- */

	public static function notice() {
		$map = array(
			'saved'      => array( 'success', 'Zapisano.' ),
			'scanned'    => array( 'success', 'Zapisano i przeskanowano katalog źródłowy.' ),
			'source_bad' => array( 'error', 'Nie zapisano: takiego katalogu nie ma w wp-content albo nie może być źródłem (np. plugins, themes).' ),
			'reset'      => array( 'success', 'Miniatury usunięte. Wygeneruj je ponownie na Pulpicie.' ),
			'elementor'  => array( 'success', 'Wstawiono widżet Focus Portfolio na stronę portfolio. Otwórz ją w Elementorze, żeby ustawić wygląd.' ),
			'photos_cleared' => array( 'success', 'Wyczyszczono cache zdjęć: przy następnym wyświetleniu widżety wylosują nowe zdjęcia.' ),
		);
		$k = isset( $_GET['fp_msg'] ) ? sanitize_key( $_GET['fp_msg'] ) : ''; // phpcs:ignore
		if ( 'stripped' === $k ) {
			$map[ $k ] = array( 'success', sprintf( 'Zapisano. Usunięto podpis autora ze zdjęć: %d (przywrócisz go w Eksploratorze).', isset( $_GET['n'] ) ? (int) $_GET['n'] : 0 ) ); // phpcs:ignore
		}
		if ( isset( $map[ $k ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $k ][0] ), esc_html( $map[ $k ][1] ) );
		}
	}
}
