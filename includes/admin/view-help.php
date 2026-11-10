<?php
/**
 * Instrukcja: PDF do pobrania i podgląd tej samej treści (docs/instrukcja.html).
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="fp-intro">Jak wgrywać, nazywać i porządkować zdjęcia, żeby portfolio układało się samo. Wyślij PDF osobom, które dostają uprawnienie „Redakcja”.</p>
<p class="fp-actions">
	<a class="button button-primary" href="<?php echo esc_url( FP_URL . 'docs/instrukcja.pdf?v=' . FP_VERSION ); ?>" download>Pobierz instrukcję (PDF)</a>
	<a class="button" href="<?php echo esc_url( FP_URL . 'docs/instrukcja.html?v=' . FP_VERSION ); ?>" target="_blank">Otwórz w nowej karcie</a>
	<?php if ( FP_Caps::can( FP_Caps::ADMIN ) ) : ?>
		<a class="button" href="<?php echo esc_url( FP_Admin::url( 'structure' ) ); ?>">Struktura katalogów</a>
	<?php endif; ?>
</p>
<p class="fp-support">Focus Portfolio jest darmowe, a kod jest otwarty na <a href="<?php echo esc_url( FP_REPO ); ?>" target="_blank" rel="noopener">GitHubie</a> (tam zgłaszasz błędy i pomysły). Jeśli wtyczka Ci się przydaje, możesz <a href="<?php echo esc_url( FP_TIP_URL ); ?>" target="_blank" rel="noopener">postawić autorowi kawę ☕</a>.</p>
<iframe class="fp-help-frame" title="Instrukcja Focus Portfolio" src="<?php echo esc_url( FP_URL . 'docs/instrukcja.html?v=' . FP_VERSION ); ?>"></iframe>
<style>.fp-help-frame{width:100%;height:calc(100vh - 260px);min-height:560px;border:1px solid #dcdcde;border-radius:6px;background:#fff;padding:0}</style>
