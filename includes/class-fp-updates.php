<?php
/**
 * Aktualizacje z wydań na GitHubie i linki „GitHub” / „Postaw kawę” na liście wtyczek.
 *
 * Nowe wydanie = tag „vX.Y” w repozytorium (GitHub Action buduje zip i dołącza go do wydania).
 * WordPress sprawdza je co 12 godzin i pokazuje w Wtyczki → „Dostępna aktualizacja”, jak przy wtyczkach z WordPress.org.
 */

defined( 'ABSPATH' ) || exit;

class FP_Updates {

	const LIB = 'vendor/plugin-update-checker/plugin-update-checker.php';

	/** Polskie teksty biblioteki (nie ma własnego tłumaczenia na polski). */
	const PL = array(
		'Check for updates'                                    => 'Sprawdź aktualizacje',
		'The %s plugin is up to date.'                         => 'Wtyczka %s jest aktualna.',
		'A new version of the %s plugin is available.'         => 'Dostępna jest nowa wersja wtyczki %s.',
		'Could not determine if updates are available for %s.' => 'Nie udało się sprawdzić aktualizacji wtyczki %s.',
		'Unknown update checker status "%s"'                   => 'Nieznany wynik sprawdzania aktualizacji: „%s”',
		'There is no changelog available.'                     => 'Brak opisu zmian.',
	);

	public static function init() {
		if ( ! is_admin() && ! wp_doing_cron() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return; // odwiedzającym niepotrzebne: sprawdzanie i instalacja działają w panelu i w zadaniach cron
		}
		if ( is_readable( FP_DIR . self::LIB ) ) {
			require_once FP_DIR . self::LIB;
			$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( FP_REPO, FP_FILE, 'focus-portfolio' );
			$checker->setBranch( 'main' ); // najpierw najnowsze wydanie, potem najwyższy tag, na końcu gałąź main
			$checker->getVcsApi()->enableReleaseAssets( '/^focus-portfolio.*\.zip$/i' );
		}
		if ( 0 === strpos( determine_locale(), 'pl' ) ) {
			add_filter( 'gettext_plugin-update-checker', array( __CLASS__, 'pl' ), 10, 2 );
			add_filter( 'gettext_with_context_plugin-update-checker', array( __CLASS__, 'pl' ), 10, 2 );
		}
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_links' ), 10, 2 );
	}

	public static function pl( $translation, $text ) {
		return isset( self::PL[ $text ] ) ? self::PL[ $text ] : $translation;
	}

	public static function row_links( $links, $file ) {
		if ( plugin_basename( FP_FILE ) === $file ) {
			$links[] = '<a href="' . esc_url( FP_REPO ) . '" target="_blank" rel="noopener">GitHub</a>';
			$links[] = '<a href="' . esc_url( FP_TIP_URL ) . '" target="_blank" rel="noopener">☕ Postaw kawę</a>';
		}
		return $links;
	}
}
