<?php
/**
 * Import zdjęć z panelu: pojedyncze pliki albo ZIP trafiają do folderu źródłowego
 * jako kategoria / [podfolder] / [wydarzenie]. Akceptowane są tylko prawdziwe obrazy.
 */

defined( 'ABSPATH' ) || exit;

class FP_Importer {

	const EXT = array( 'jpg', 'jpeg', 'png', 'webp' );

	/** Nazwa folderu/pliku bez znaków niedozwolonych i bez „..”; polskie litery i spacje zostają. */
	public static function clean_segment( $s ) {
		$s = wp_strip_all_tags( (string) $s );
		$s = preg_replace( '#[\\\\/:*?"<>|\x00-\x1F]#u', '', $s );
		$s = preg_replace( '/\s+/u', ' ', $s );
		$s = trim( $s, " .\t" );
		return mb_substr( $s, 0, 120 );
	}

	/**
	 * Folder docelowy albo WP_Error. Segmenty po clean_segment() nie mają ukośników ani „..”,
	 * więc ścieżka zawsze leży w folderze źródłowym. Folder powstaje dopiero przy zapisie pliku.
	 */
	public static function target( $cat, $sub, $event ) {
		$root = FP_Settings::source_path();
		if ( ! $root ) {
			return new WP_Error( 'fp_source', 'Folder źródłowy nie istnieje. Ustaw go w sekcji „Źródło zdjęć”.' );
		}
		if ( '' === self::clean_segment( $cat ) ) {
			return new WP_Error( 'fp_cat', 'Wybierz albo wpisz kategorię.' );
		}
		$parts = array_values( array_filter( array_map( array( __CLASS__, 'clean_segment' ), array( $cat, $sub, $event ) ), 'strlen' ) );
		return $root . '/' . implode( '/', $parts );
	}

	private static function ensure_dir( $dir ) {
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'fp_mkdir', 'Nie udało się utworzyć folderu ' . substr( $dir, strlen( FP_Settings::source_path() ) + 1 ) . '. Sprawdź uprawnienia na hostingu.' );
		}
		return true;
	}

	/** @return array{saved:string[],skipped:string[]}|WP_Error */
	public static function import_upload( array $file, $dir ) {
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'fp_upload', self::upload_error( (int) $file['error'] ) );
		}
		$name = self::clean_segment( wp_basename( $file['name'] ) );
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( 'zip' === $ext ) {
			return self::import_zip( $file['tmp_name'], $dir );
		}
		$ok = self::check_image( $file['tmp_name'], $name );
		if ( is_wp_error( $ok ) ) {
			return array( 'saved' => array(), 'skipped' => array( $name . ': ' . $ok->get_error_message() ) );
		}
		$made = self::ensure_dir( $dir );
		if ( is_wp_error( $made ) ) {
			return $made;
		}
		$dest = $dir . '/' . wp_unique_filename( $dir, $name );
		if ( ! @move_uploaded_file( $file['tmp_name'], $dest ) ) { // phpcs:ignore
			return new WP_Error( 'fp_move', 'Nie udało się zapisać pliku ' . $name . '.' );
		}
		@chmod( $dest, 0644 ); // phpcs:ignore
		return array( 'saved' => array( wp_basename( $dest ) ), 'skipped' => array() );
	}

	private static function import_zip( $zip, $dir ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		$tmp = trailingslashit( get_temp_dir() ) . 'fp-import-' . wp_generate_password( 8, false );
		wp_mkdir_p( $tmp );
		$r = unzip_file( $zip, $tmp );
		if ( is_wp_error( $r ) ) {
			self::rrmdir( $tmp );
			return $r;
		}
		$saved   = array();
		$skipped = array();
		$base    = wp_normalize_path( realpath( $tmp ) );
		$it      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( ! $f->isFile() ) {
				continue;
			}
			$rel = ltrim( substr( wp_normalize_path( $f->getPathname() ), strlen( $base ) ), '/' );
			if ( preg_match( '#(^|/)(__MACOSX|\.)#', $rel ) ) {
				continue;
			}
			$segs = array_values( array_filter( array_map( array( __CLASS__, 'clean_segment' ), explode( '/', $rel ) ), 'strlen' ) );
			$name = array_pop( $segs );
			$ok   = self::check_image( $f->getPathname(), $name );
			if ( is_wp_error( $ok ) ) {
				$skipped[] = $rel . ': ' . $ok->get_error_message();
				continue;
			}
			$to = $dir . ( $segs ? '/' . implode( '/', $segs ) : '' );
			if ( is_wp_error( self::ensure_dir( $to ) ) ) {
				$skipped[] = $rel . ': nie udało się utworzyć folderu';
				continue;
			}
			$dest = $to . '/' . wp_unique_filename( $to, $name );
			if ( @rename( $f->getPathname(), $dest ) || @copy( $f->getPathname(), $dest ) ) { // phpcs:ignore
				@chmod( $dest, 0644 ); // phpcs:ignore
				$saved[] = ( $segs ? implode( '/', $segs ) . '/' : '' ) . wp_basename( $dest );
			} else {
				$skipped[] = $rel . ': nie udało się zapisać';
			}
		}
		self::rrmdir( $tmp );
		return array( 'saved' => $saved, 'skipped' => $skipped );
	}

	private static function check_image( $path, $name ) {
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::EXT, true ) ) {
			return new WP_Error( 'fp_type', 'dozwolone są JPG, PNG, WEBP i ZIP' );
		}
		$info = @getimagesize( $path ); // phpcs:ignore
		if ( ! $info || ! in_array( $info[2], array( IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP ), true ) ) {
			return new WP_Error( 'fp_type', 'to nie jest poprawny obraz' );
		}
		return true;
	}

	private static function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); // phpcs:ignore
		}
		@rmdir( $dir ); // phpcs:ignore
	}

	private static function upload_error( $code ) {
		$map = array(
			UPLOAD_ERR_INI_SIZE  => 'Plik jest większy niż limit serwera (upload_max_filesize = ' . ini_get( 'upload_max_filesize' ) . ').',
			UPLOAD_ERR_FORM_SIZE => 'Plik jest za duży.',
			UPLOAD_ERR_PARTIAL   => 'Plik wgrał się tylko częściowo. Spróbuj ponownie.',
			UPLOAD_ERR_NO_FILE   => 'Nie wybrano pliku.',
		);
		return isset( $map[ $code ] ) ? $map[ $code ] : 'Błąd wgrywania (kod ' . $code . ').';
	}

	/** Drzewo dwóch poziomów pod każdą kategorią, do podpowiedzi w formularzu importu. */
	public static function tree() {
		$root = FP_Settings::source_path();
		$out  = array();
		if ( ! $root ) {
			return $out;
		}
		list( $cats ) = FP_Scanner::ls( $root );
		foreach ( $cats as $c ) {
			list( $subs ) = FP_Scanner::ls( $root . '/' . $c );
			$out[ $c ]    = array();
			foreach ( $subs as $s ) {
				list( $ev ) = FP_Scanner::ls( $root . '/' . $c . '/' . $s );
				$out[ $c ][ $s ] = $ev;
			}
		}
		return $out;
	}
}
