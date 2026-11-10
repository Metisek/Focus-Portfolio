<?php
/**
 * Miniatury: dwa rozmiary (g = siatka, l = lightbox) w uploads/portfolio-focus-cache.
 * Generowane partiami przez cron, przyciskiem w panelu albo przy pierwszym wyświetleniu.
 * Oryginały zostają nietknięte; kopie nie mają EXIF ani GPS.
 */

defined( 'ABSPATH' ) || exit;

class FP_Thumbs {

	const OPT_DONE = 'fp_thumbs_done';   // [id => 'g' | 'gL' | 'x' (nie da się przetworzyć)]
	const CACHE    = 'portfolio-focus-cache';

	private static $done = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'serve_ondemand' ), 1 );
		add_action( 'fp_scanned', array( __CLASS__, 'cleanup' ) );
	}

	public static function dir() {
		$up = wp_upload_dir( null, false );
		return wp_normalize_path( $up['basedir'] ) . '/' . self::CACHE;
	}

	public static function base_url() {
		$up = wp_upload_dir( null, false );
		return set_url_scheme( $up['baseurl'] ) . '/' . self::CACHE;
	}

	public static function ext() {
		$e = get_option( 'fp_thumb_ext' );
		if ( ! $e ) {
			$e = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ? 'webp' : 'jpg';
			update_option( 'fp_thumb_ext', $e, true );
		}
		return $e;
	}

	private static function done() {
		if ( null === self::$done ) {
			self::$done = (array) get_option( self::OPT_DONE, array() );
		}
		return self::$done;
	}

	/** Flaga w mapie gotowych miniatur: g = siatka (WebP), L = powiększenie w formacie źródłowym. */
	private static function flag( $size ) {
		return 'l' === $size ? 'L' : $size;
	}

	public static function has( $id, $size ) {
		$d = self::done();
		return isset( $d[ $id ] ) && false !== strpos( $d[ $id ], self::flag( $size ) );
	}

	/** Siatka zawsze w WebP (lekki podgląd); powiększenie w formacie oryginału, żeby „Zapisz obraz” dawało JPG/PNG. */
	public static function size_ext( $p, $size ) {
		if ( 'g' === $size ) {
			return self::ext();
		}
		$e = strtolower( pathinfo( $p['f'], PATHINFO_EXTENSION ) );
		$e = 'jpeg' === $e ? 'jpg' : $e;
		return in_array( $e, array( 'jpg', 'png', 'webp' ), true ) ? $e : 'jpg';
	}

	private static function rel( $p, $size ) {
		return substr( $p['id'], 0, 2 ) . '/' . $p['id'] . '-' . $size . '.' . self::size_ext( $p, $size );
	}

	public static function file( $p, $size ) {
		return self::dir() . '/' . self::rel( $p, $size );
	}

	public static function failed( $id ) {
		$d = self::done();
		return isset( $d[ $id ] ) && false !== strpos( $d[ $id ], 'x' );
	}

	public static function url( $p, $size ) {
		if ( self::failed( $p['id'] ) ) {
			return self::original_url( $p );
		}
		if ( self::has( $p['id'], $size ) ) {
			return self::base_url() . '/' . self::rel( $p, $size );
		}
		if ( FP_Settings::get( 'ondemand' ) ) {
			return add_query_arg( array( 'fp_img' => $p['id'], 's' => $size ), home_url( '/' ) );
		}
		return self::original_url( $p );
	}

	public static function original_url( $p ) {
		return FP_Settings::source_url() . '/' . implode( '/', array_map( 'rawurlencode', explode( '/', $p['f'] ) ) );
	}

	/** Tworzy brakujące rozmiary jednego zdjęcia. */
	public static function generate( $p, $sizes = array( 'g', 'l' ) ) {
		$src = FP_Settings::source_path() . '/' . $p['f'];
		if ( ! is_readable( $src ) ) {
			return new WP_Error( 'fp_missing', 'Brak pliku ' . $p['f'] );
		}
		wp_raise_memory_limit( 'image' );
		$dims = array(
			'g' => (int) FP_Settings::get( 'grid_size' ),
			'l' => (int) FP_Settings::get( 'large_size' ),
		);
		$made = '';
		// najpierw większy rozmiar, mniejszy robimy z niego (szybciej niż z 20 MB oryginału)
		$order = array_values( array_intersect( array( 'l', 'g' ), $sizes ) );
		$from  = $src;
		foreach ( $order as $size ) {
			$dest = self::file( $p, $size );
			if ( file_exists( $dest ) ) {
				$made .= self::flag( $size );
				$from  = 'l' === $size ? $dest : $from;
				continue;
			}
			$ed = wp_get_image_editor( $from );
			if ( is_wp_error( $ed ) ) {
				return $ed;
			}
			if ( method_exists( $ed, 'maybe_exif_rotate' ) && $from === $src ) {
				$ed->maybe_exif_rotate();
			}
			$q = (int) FP_Settings::get( 'quality' );
			$ed->set_quality( 'l' === $size ? max( 85, $q ) : $q );
			$cur = $ed->get_size();
			if ( max( $cur['width'], $cur['height'] ) > $dims[ $size ] ) {
				$ed->resize( $dims[ $size ], $dims[ $size ], false );
			}
			wp_mkdir_p( dirname( $dest ) );
			$mimes = array( 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
			$saved = $ed->save( $dest, $mimes[ self::size_ext( $p, $size ) ] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			$made .= self::flag( $size );
			if ( 'l' === $size ) {
				$from = $dest;
			}
		}
		// powiększenia z wersji 1.0 były w WebP; po przejściu na format źródłowy są zbędne
		$legacy = self::dir() . '/' . substr( $p['id'], 0, 2 ) . '/' . $p['id'] . '-l.webp';
		if ( 'webp' !== self::size_ext( $p, 'l' ) && file_exists( $legacy ) ) {
			wp_delete_file( $legacy );
		}
		self::mark( $p['id'], $made );
		return true;
	}

	private static function mark( $id, $sizes ) {
		$d        = (array) get_option( self::OPT_DONE, array() );
		$cur      = isset( $d[ $id ] ) ? $d[ $id ] : '';
		$d[ $id ] = implode( '', array_unique( str_split( $cur . $sizes ) ) );
		update_option( self::OPT_DONE, $d, false );
		self::$done = $d;
	}

	public static function status() {
		$all  = FP_Scanner::all_photos();
		$done = 0;
		foreach ( $all as $id => $p ) {
			if ( self::failed( $id ) || ( self::has( $id, 'g' ) && self::has( $id, 'l' ) ) ) {
				$done++;
			}
		}
		return array( 'done' => $done, 'total' => count( $all ) );
	}

	/** Przetwarza kolejne brakujące zdjęcia, aż skończy się limit liczby albo czasu. */
	public static function process_batch( $limit = 20, $seconds = 20 ) {
		$start  = microtime( true );
		$n      = 0;
		$errors = array();
		foreach ( FP_Scanner::all_photos() as $id => $p ) {
			if ( self::failed( $id ) || ( self::has( $id, 'g' ) && self::has( $id, 'l' ) ) ) {
				continue;
			}
			if ( $n >= $limit || microtime( true ) - $start > $seconds ) {
				break;
			}
			$r = self::generate( $p );
			if ( is_wp_error( $r ) ) {
				$errors[] = $p['f'] . ': ' . $r->get_error_message();
				self::mark( $id, 'x' ); // nie próbuj w kółko; strona pokaże oryginał
			}
			$n++;
		}
		return array_merge( self::status(), array( 'processed' => $n, 'errors' => $errors ) );
	}

	/** Po skanie: usuń miniatury zdjęć, których już nie ma (albo zmieniły się pliki). */
	public static function cleanup() {
		$all  = FP_Scanner::all_photos();
		$d    = (array) get_option( self::OPT_DONE, array() );
		$keep = array_intersect_key( $d, $all );
		foreach ( array_diff_key( $d, $all ) as $id => $s ) {
			foreach ( (array) glob( self::dir() . '/' . substr( $id, 0, 2 ) . '/' . $id . '-*' ) as $f ) {
				if ( $f ) {
					wp_delete_file( $f );
				}
			}
		}
		update_option( self::OPT_DONE, $keep, false );
		self::$done = $keep;
	}

	/** Usuwa miniatury jednego rozmiaru (np. po zmianie rozmiaru siatki); kolejne wejścia i cron zrobią nowe. */
	public static function reset_size( $size ) {
		$flag = self::flag( $size );
		$d    = (array) get_option( self::OPT_DONE, array() );
		foreach ( $d as $id => $f ) {
			$d[ $id ] = str_replace( $flag, '', $f );
		}
		update_option( self::OPT_DONE, array_filter( $d, 'strlen' ), false );
		self::$done = null;
		foreach ( (array) glob( self::dir() . '/*/*-' . $size . '.*' ) as $f ) {
			if ( $f ) {
				wp_delete_file( $f );
			}
		}
	}

	public static function reset() {
		$dir = self::dir();
		if ( is_dir( $dir ) ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $it as $f ) {
				$f->isDir() ? @rmdir( $f->getPathname() ) : wp_delete_file( $f->getPathname() ); // phpcs:ignore
			}
		}
		delete_option( self::OPT_DONE );
		delete_option( 'fp_thumb_ext' );
		self::$done = array();
	}

	/** ?fp_img=<id>&s=g|l → generuje brakującą miniaturę i przekierowuje do pliku. */
	public static function serve_ondemand() {
		if ( empty( $_GET['fp_img'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$id   = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['fp_img'] ) ); // phpcs:ignore
		$size = isset( $_GET['s'] ) && 'l' === $_GET['s'] ? 'l' : 'g'; // phpcs:ignore
		$hit  = FP_Scanner::photo_by_id( $id );
		if ( ! $hit ) {
			status_header( 404 );
			exit;
		}
		$p = $hit[0];
		nocache_headers();
		if ( ! self::has( $id, $size ) ) {
			if ( get_transient( 'fp_lock_' . $id ) ) {
				wp_redirect( self::original_url( $p ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect
				exit;
			}
			set_transient( 'fp_lock_' . $id, 1, MINUTE_IN_SECONDS );
			$r = self::generate( $p );
			delete_transient( 'fp_lock_' . $id );
			if ( is_wp_error( $r ) ) {
				self::mark( $id, 'x' );
				wp_redirect( self::original_url( $p ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect
				exit;
			}
		}
		wp_safe_redirect( self::url( $p, $size ), 302 );
		exit;
	}
}
