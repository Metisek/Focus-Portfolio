# Focus Portfolio

Wtyczka WordPress: portfolio zdjęć czytane prosto z folderu na hostingu (kategorie → wydarzenia → zdjęcia), z automatycznym skanowaniem, miniaturami WebP, eksploratorem plików, rejestrem fotografów, widżetami Elementora i blokami Gutenberga.

Działa na klubfocus.pl (motyw Phlox + Elementor, LiteSpeed). Instrukcja dla redaktorów: [docs/instrukcja.pdf](docs/instrukcja.pdf).

**Instalacja:** pobierz `focus-portfolio-X.Y.zip` z [najnowszego wydania](https://github.com/Metisek/Focus-Portfolio/releases/latest) i wgraj w *Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer*. Kolejne wersje pojawią się w WordPressie jako zwykła aktualizacja.

Wtyczka jest darmowa. Jeśli Ci się przydaje: [☕ postaw kawę](https://buycoffee.to/metis).

## Co gdzie jest

| Ścieżka | Zawartość |
|---|---|
| `focus-portfolio.php` | nagłówek wtyczki, wersja (`Version` i `FP_VERSION`), ładowanie klas |
| `includes/class-fp-settings.php` | ustawienia (`fp_settings`, `fp_view`, `fp_structure`) i migracje bazy (`maybe_upgrade`) |
| `includes/class-fp-scanner.php` | skan folderu źródłowego → `fp_manifest` |
| `includes/class-fp-frontend.php` | strony portfolio dla odwiedzających |
| `includes/class-fp-fs.php`, `admin/view-explorer.php` | Eksplorator (pliki, kosz `__kosz`) |
| `includes/class-fp-photos*.php` | widżet „Focus Portfolio – Zdjęcia” (losowe, z folderu, wybrane, najlepsze, z wydarzeń) |
| `includes/class-fp-elementor-widget.php`, `class-fp-embeds.php` | widżet Elementora portfolio, blok, shortcode |
| `includes/class-fp-updates.php`, `vendor/plugin-update-checker/` | aktualizacje z wydań na GitHubie ([Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker), MIT) i linki na liście wtyczek |
| `includes/class-fp-i18n.php`, `languages/*.php` | tłumaczenia tekstów dla odwiedzających (EN, DE, ES, FR, IT); panel jest po polsku |
| `includes/admin/view-*.php` | ekrany panelu |
| `assets/` | CSS i JS (bez etapu budowania, pliki edytuje się wprost) |
| `docs/` | instrukcja HTML i PDF |
| `tools/` | skrypty pomocnicze, nie trafiają na serwer |
| `.distignore` | lista plików pomijanych w paczce i na serwerze (wspólna dla skryptów i wydań) |
| `.github/workflows/release.yml` | budowanie paczki i wydania po wypchnięciu tagu |

## Praca z repozytorium

To repozytorium jest źródłem kodu. Kopia na serwerze i w lustrze FTP (`KFF_Focus_FTP`) powstaje z niego.

1. Zmieniasz pliki tutaj i zapisujesz zmiany w git (`git commit`).
2. Kopiujesz wtyczkę do lustra FTP:
   ```powershell
   .\tools\sync-to-site.ps1           # podgląd: co by się zmieniło
   .\tools\sync-to-site.ps1 -Apply    # kopiowanie
   ```
   Domyślnie celem jest `..\KFF_Focus_FTP\wp-content\plugins\focus-portfolio` (inną ścieżkę podasz w `-Target`). Skrypt robi z celu wierną kopię repozytorium: pliki, których tu nie ma, zostaną tam usunięte. Zmian nie rób więc bezpośrednio w lustrze.
3. Wgrywasz zmienione pliki na serwer, np. z VS Code (rozszerzenie SFTP w lustrze: prawy klik → *Upload*).

Od wersji z aktualizacjami z GitHuba punkty 2–3 są potrzebne tylko do szybkich poprawek bez wydania; zwykle wystarczy wydać nową wersję i kliknąć „Aktualizuj” w panelu.

Przy większych zmianach wgrywaj najpierw nowe pliki (np. nowe klasy z `includes/`), a dopiero potem `focus-portfolio.php`, który je wczytuje. Inaczej strona może przez chwilę zgłaszać błąd.

Haseł i danych FTP nie trzymaj w tym repozytorium. Zostają w `KFF_Focus_FTP/.vscode/sftp.json`.

## Wydania i aktualizacje

Strony z zainstalowaną wtyczką co 12 godzin sprawdzają [wydania w repozytorium](https://github.com/Metisek/Focus-Portfolio/releases) i pokazują nową wersję w *Wtyczki* (można też kliknąć „Sprawdź aktualizacje” przy wtyczce). Wydaniem jest tag, nie każdy commit do `main`: dopóki nie wydasz nowej wersji, strony dalej działają na poprzedniej.

Nowe wydanie:

1. Podnieś wersję w `focus-portfolio.php`: w nagłówku (`Version:`) i w `FP_VERSION`, np. `1.2`. WordPress widzi tylko wyższy numer.
2. Dopisz zmiany do `CHANGELOG.md`, zrób commit i `git push`.
3. Utwórz i wypchnij tag:
   ```powershell
   git tag v1.2
   git push origin v1.2
   ```
4. GitHub Action (*Actions → Wydanie*) sprawdza, czy tag zgadza się z wersją, buduje `focus-portfolio-1.2.zip` i publikuje wydanie. Jeśli numery się nie zgadzają, wydanie nie powstaje.

Aktualizacja podmienia cały katalog wtyczki. Dane są gdzie indziej (ustawienia w bazie, miniatury w `uploads`, `_portfolio.json` w folderach ze zdjęciami), więc nic nie ginie. Nie zapisuj niczego w katalogu wtyczki.

## Instalacja na innej stronie (bez GitHuba)

```powershell
.\tools\build-zip.ps1
```

Skrypt tworzy `dist\focus-portfolio-<wersja>.zip` z katalogiem `focus-portfolio/` w środku (to samo co w wydaniu na GitHubie). Plik instalujesz w WordPressie: *Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer*. Potem ustawiasz *Focus Portfolio → Ustawienia → Folder źródłowy* i stronę portfolio.

## Wersje i migracje

- Wersja wtyczki jest w dwóch miejscach w `focus-portfolio.php`: w nagłówku (`Version:`) i w `FP_VERSION`. Pliki CSS/JS i tak dostają w adresie datę zmiany, więc przeglądarki nie trzymają starych kopii, nawet bez podnoszenia wersji.
- Schemat danych ma osobny numer: opcję `fp_db_version` (teraz `1.3.0`). Zmianę formatu ustawień dopisujesz jako nowy blok `if ( version_compare( $ver, 'X.Y.Z', '<' ) )` w `FP_Settings::maybe_upgrade()`, a na końcu podnosisz zapisywany numer. Migracja uruchamia się sama przy pierwszym wejściu na stronę po wgraniu.
- Format manifestu skanera zmienia się razem z podpisem w `class-fp-scanner.php` (teraz `v6`). Nowy podpis wymusza pełny skan.

## Testy

Na komputerze nie ma PHP, więc wtyczkę testuje się w [WordPress Playground](https://playground.wordpress.net/) (WordPress w przeglądarce):

1. Wgraj zip z `tools/build-zip.ps1`.
2. Doinstaluj Elementor i motyw Phlox.
3. Dodaj kilka folderów ze zdjęciami do `wp-content/uploads/portfolio-focus`.

Po zmianach sprawdź:
- stronę portfolio, kategorię, galerię i lightbox;
- link `#foto-…`;
- Eksplorator;
- zapis ustawień;
- widżety w edytorze Elementora.

## Poza tym repozytorium

Drobne poprawki samej strony klubfocus.pl są w lustrze FTP w `wp-content/mu-plugins/`, nie w tej wtyczce:
- `focus-phlox-preloader-fix.php`: obejście błędu preloadera Phlox;
- `focus-site-tweaks.php`: odstępy w stopce.

## Wsparcie

Błędy i pomysły: [Issues](https://github.com/Metisek/Focus-Portfolio/issues). Napiwek: [buycoffee.to/metis](https://buycoffee.to/metis).

## Licencja

GPL-2.0-or-later. Dołączona biblioteka Plugin Update Checker: MIT (`vendor/plugin-update-checker/license.txt`).
