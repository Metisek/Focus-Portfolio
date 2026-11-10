# Focus Portfolio

A WordPress plugin that builds a photo portfolio straight from a folder on your hosting: categories → events → photos. It includes:
- automatic scanning and WebP thumbnails;
- a file explorer and a photographers registry;
- Elementor widgets and Gutenberg blocks.

The editors' manual is in Polish: [docs/instrukcja.html](docs/instrukcja.html), also as a [PDF](docs/instrukcja.pdf). The same manual is in the dashboard under *Focus Portfolio → Instrukcja*. The admin screens are in Polish. Visitor-facing text is translated into English, German, Spanish, French and Italian, following the site language.

## Installation

Requirements: WordPress 6.0+, PHP 7.4+.

1. Download `focus-portfolio-X.Y.zip` from the [latest release](https://github.com/Metisek/Focus-Portfolio/releases/latest).
2. Upload it under *Plugins → Add New → Upload Plugin* and activate it.
3. In *Focus Portfolio → Ustawienia* (Settings), choose the source folder with your photos and the portfolio page.

## Updates

The plugin checks the [releases of this repository](https://github.com/Metisek/Focus-Portfolio/releases) by itself. A new version shows up under *Plugins* like any other update, and the plugin row also has a "Check for updates" link.

Settings, thumbnails and folder descriptions are stored outside the plugin folder, so updates don't touch them.

## Code map

| Path | Contents |
|---|---|
| `focus-portfolio.php` | plugin header, version, class loading |
| `includes/class-fp-settings.php` | settings and database migrations |
| `includes/class-fp-scanner.php` | source folder scanner |
| `includes/class-fp-frontend.php` | visitor-facing portfolio pages |
| `includes/class-fp-fs.php`, `admin/view-explorer.php` | file explorer (files, trash) |
| `includes/class-fp-photos*.php` | "Focus Portfolio – Zdjęcia" photos widget (random, from folders, picked, best, one per event) |
| `includes/class-fp-elementor-widget.php`, `class-fp-embeds.php` | Elementor portfolio widget, block, shortcode |
| `includes/class-fp-updates.php`, `vendor/plugin-update-checker/` | updates from GitHub releases ([Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)) |
| `includes/class-fp-i18n.php`, `languages/*.php` | translations of visitor-facing text |
| `includes/admin/view-*.php` | dashboard screens |
| `assets/` | CSS and JavaScript |
| `docs/` | editors' manual (HTML and PDF, Polish) |

## Support

Report bugs and ideas in [Issues](https://github.com/Metisek/Focus-Portfolio/issues). The plugin is free; if you find it useful, you can [☕ buy me a coffee](https://buycoffee.to/metis).

## License

GPL-2.0-or-later. Bundled Plugin Update Checker library: MIT (`vendor/plugin-update-checker/license.txt`).
