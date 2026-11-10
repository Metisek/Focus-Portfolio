# Focus Portfolio

Wtyczka WordPress: portfolio zdjęć czytane prosto z folderu na hostingu (kategorie → wydarzenia → zdjęcia). Ma automatyczne skanowanie, miniatury WebP, eksplorator plików, rejestr fotografów, widżety Elementora i bloki Gutenberga.

Instrukcja dla redaktorów: [docs/instrukcja.pdf](docs/instrukcja.pdf). Ta sama treść jest w panelu, w *Focus Portfolio → Instrukcja*.

## Instalacja

Wymagania: WordPress 6.0+, PHP 7.4+.

1. Pobierz `focus-portfolio-X.Y.zip` z [najnowszego wydania](https://github.com/Metisek/Focus-Portfolio/releases/latest).
2. Wgraj go w *Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer* i włącz wtyczkę.
3. W *Focus Portfolio → Ustawienia* wskaż folder źródłowy ze zdjęciami i stronę portfolio.

## Aktualizacje

Wtyczka sama sprawdza [wydania w tym repozytorium](https://github.com/Metisek/Focus-Portfolio/releases). Nowa wersja pojawia się w *Wtyczki* jako zwykła aktualizacja. Przy wtyczce jest też link „Sprawdź aktualizacje”.

Ustawienia, miniatury i opisy folderów leżą poza katalogiem wtyczki, więc aktualizacja ich nie rusza.

## Co gdzie jest

| Ścieżka | Zawartość |
|---|---|
| `focus-portfolio.php` | nagłówek wtyczki, wersja, ładowanie klas |
| `includes/class-fp-settings.php` | ustawienia i migracje bazy |
| `includes/class-fp-scanner.php` | skan folderu źródłowego |
| `includes/class-fp-frontend.php` | strony portfolio dla odwiedzających |
| `includes/class-fp-fs.php`, `admin/view-explorer.php` | Eksplorator (pliki, kosz) |
| `includes/class-fp-photos*.php` | widżet „Focus Portfolio – Zdjęcia” (losowe, z folderu, wybrane, najlepsze, z wydarzeń) |
| `includes/class-fp-elementor-widget.php`, `class-fp-embeds.php` | widżet Elementora portfolio, blok, shortcode |
| `includes/class-fp-updates.php`, `vendor/plugin-update-checker/` | aktualizacje z wydań na GitHubie ([Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)) |
| `includes/class-fp-i18n.php`, `languages/*.php` | tłumaczenia tekstów dla odwiedzających (EN, DE, ES, FR, IT); panel jest po polsku |
| `includes/admin/view-*.php` | ekrany panelu |
| `assets/` | CSS i JS |
| `docs/` | instrukcja dla redaktorów (HTML i PDF) |

## Wsparcie

Błędy i pomysły zgłaszaj w [Issues](https://github.com/Metisek/Focus-Portfolio/issues). Wtyczka jest darmowa; jeśli Ci się przydaje, [☕ postaw kawę](https://buycoffee.to/metis).

## Licencja

GPL-2.0-or-later. Dołączona biblioteka Plugin Update Checker: MIT (`vendor/plugin-update-checker/license.txt`).
