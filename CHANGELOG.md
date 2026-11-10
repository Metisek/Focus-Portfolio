# Historia zmian

Etykieta wersji została na „1.1”. Kolejne wydania na klubfocus.pl są opisane według dat.

## Niewydane

- Aktualizacje z wydań na GitHubie (Plugin Update Checker 5.7, nagłówek `Update URI`); wydanie budowane przez GitHub Action z tagu `vX.Y`.
- Linki „GitHub” i „☕ Postaw kawę” na liście wtyczek i na ekranie Pomoc.

## 2026-11-10 (schemat bazy 1.3.0)

- **Kolory i układ**
  - Kolory brane z motywu; usunięty wariant ciemny; nowy wariant „kafelki”.
  - Kolumny min./maks., równy dół siatki (masonry), okładki wypełniają kafelki.
- **Lightbox i linki**
  - Kolejność zdjęć w lightboksie taka jak w siatce.
  - Linki do zdjęć `#foto-<plik>` liczone po stronie serwera (`FP_Links`).
- **Struktura folderów**
  - Nowe schematy: `years` (lata), `dated` (daty w nazwach), miesiące w katalogach lat.
  - Ustawiany początek „roku zdjęć” (zamiast stałego roku akademickiego).
  - Tytuły, adresy i kolejność kategorii zapisane w Strukturze, więc adresy URL się nie zmieniły.
- **Fotografowie i autorzy**
  - Rejestr fotografów bez kroku „dodaj”.
  - Usunięcie osoby może też usunąć jej podpisy ze zdjęć.
  - Ustawienia: pomijany podpis organizacji (EXIF) i słowa pomijane przez OCR.
- **Eksplorator**
  - Poprawione zasłanianie kliknięć.
  - Liczniki rekurencyjne, podgląd skrzynki.
  - Przełącznik kopii generowanych przez WP, menu kontekstowe.
- **Tłumaczenia**: teksty dla odwiedzających w EN, DE, ES, FR, IT (domyślnie język strony, można wymusić).
- **Nowy widżet „Focus Portfolio – Zdjęcia”** (Elementor, blok `focus/photos`, shortcode `[focus_photos]`)
  - Tryby: losowe, najlepsze, po jednym z wydarzeń, z folderów, wybrane.
  - Widoki: wiersze, masonry, siatka, kolaż, pasek.
  - Pamięć wylosowanych zdjęć i „worek” bez powtórek.
- **Strona klubu**: usunięte wpisy na sztywno dotyczące koła; teksty demonstracyjne poza wtyczką.

## 2026-11-07

- Filtr autorów liczy zdjęcia ze wszystkich wybranych okresów i ma pozycję „Bez podpisu”.

## 2026-11-06 (schemat 1.2.0)

- **Panel**: menu „Focus Portfolio”, role i uprawnienia (Redaktor portfolio), Eksplorator z koszem, kreator Struktury, rejestr fotografów.
- **Dane**: plik `_portfolio.json` w każdym folderze.
- **Odwiedzający**: stronicowanie, filtry i sortowanie.
- **Elementor**: widżet z kartami Treść, Układ i Styl.

## 2026-11-04 (1.1.0)

- Pierwsze wydanie na klubfocus.pl.
- Widżet Elementora i blok `focus/portfolio`, okładki, OCR autorów (tesseract.js), filtr okresu, oryginalny format w lightboksie.
