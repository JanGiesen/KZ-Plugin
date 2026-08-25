# KZ-Plugin

WordPress-plugins voor [dekraonigezwaone.nl](https://www.kraonigezwaone.nl/) (WordPress 7.1, Pressville-thema, WPBakery Page Builder).

Deze monorepo bevat twee plugins:

| Map | Plugin | Doel |
|---|---|---|
| `kz-plugin/` | **KZ Plugin** | WPBakery-elementen: post carrousel, knop, event blok, header link, hover afbeelding, tab widget, KZ Vindt grid, ticket |
| `kz-contentmanager-plugin/` | **KZ Contentmanager Plugin** | Haalt content op uit Google Docs en toont die via de shortcode `[kz-content]` |

Beide plugins zijn samenvoegingen/opvolgers van eerder losse plugins (KZ-Kraonige Zwaone Plugin 7.1, KZ Vindt, KZH Ticket Element, KZ-Kraonige Zwaone Contentmanager 2.0.0). Alle shortcode-namen en parameters zijn ongewijzigd overgenomen zodat bestaande content op de site blijft werken. Bij activatie deactiveren de nieuwe plugins automatisch de oude losse plugins (met een melding in het dashboard) om dubbele shortcode-registratie te voorkomen.

## Installatie

1. Download de gewenste zip (zie "Releases bouwen" hieronder, of gebruik een kant-en-klare release van GitHub).
2. Upload via **Plugins → Nieuwe plugin toevoegen → Plugin uploaden** in WordPress.
3. Activeer de plugin. Oude, overlappende plugins worden automatisch gedeactiveerd.

## Instellingenpagina's

- **KZ Plugin**: menu "KZ Plugin" — overzicht van alle elementen met shortcode en parameters, per element aan/uit-schakelaar (uitschakelen zet de render leeg maar houdt de shortcode geregistreerd, zodat content nooit een kale shortcode-tekst toont), updatestatus + "Nu controleren op updates".
- **KZ Contentmanager Plugin**: menu "KZ Contentmanager" — documentenoverzicht met shortcodes, synchronisatie, instellingen (Google API key, Drive-map, webhook-URL, rate limit), en een Updates-tab.

Beide plugins delen de optie `kz_github_token` voor toegang tot een private GitHub-repo (zie hieronder).

## Automatische updates

Beide plugins bevatten een lichte, eigen updater (`includes/class-kz-updater.php`, identiek in beide plugins) die gebruikmaakt van WordPress' `Update URI`-mechanisme (sinds WP 5.8). Zolang de plugin-header `Update URI: https://github.com/JanGiesen/KZ-Plugin` bevat, checkt WordPress via de hook `update_plugins_github.com` periodiek of er een nieuwe release is — en toont/voert die update net als bij een plugin uit de WordPress-directory, inclusief **automatische updates**.

De updater:

- Haalt `https://api.github.com/repos/JanGiesen/KZ-Plugin/releases` op (gecached, 6 uur).
- Filtert op een tag-prefix per plugin:
  - KZ Plugin: tags die beginnen met `kz-plugin-v` (bijv. `kz-plugin-v8.0.1`), asset `kz-plugin.zip`.
  - KZ Contentmanager Plugin: tags die beginnen met `kz-contentmanager-v` (bijv. `kz-contentmanager-v3.0.1`), asset `kz-contentmanager-plugin.zip`.
- Vergelijkt de versie uit de tag met de huidige plugin-versie; is er een nieuwere, dan levert het die aan WordPress.

### Repository publiek of privé?

- **Publiek** (aanbevolen, simpelst): geen extra configuratie nodig, updates werken direct.
- **Privé**: vul op de KZ Plugin-instellingenpagina (of de Updates-tab van Contentmanager) een GitHub **fine-grained personal access token** in met alleen leestoegang tot deze repo (`Contents: Read-only`). De updater gebruikt dit token zowel om releases op te vragen als om de release-zip te downloaden.

## Releases maken

Elke release is een GitHub Release met een tag in het juiste formaat en de juiste zip als asset.

### Automatisch via GitHub Actions

**Belangrijk:** maak een nieuwe release altijd via de GitHub-website, niet via `git tag` + `git push` vanuit een Claude Code-sessie — die sessies mogen (bewust, vanuit het toegangsbeleid van de omgeving) geen git-tags naar deze repo pushen; dat commando faalt met een HTTP 403. Vanaf je eigen machine kan `git push origin <tag>` overigens wel gewoon.

1. Versie ophogen in de plugin-header (`Version:` in `kz-plugin.php` of `kz-contentmanager-plugin.php`) én in de bijbehorende `define()`-constante bovenin dat bestand. Commit en push die wijziging (gewoon via een PR, dat werkt wel).
2. Ga naar **Releases → "Draft a new release"** op GitHub: https://github.com/JanGiesen/KZ-Plugin/releases/new
3. Bij **"Choose a tag"**: typ de nieuwe tag en kies **"Create new tag: ... on publish"**:
   - KZ Plugin: `kz-plugin-v8.1.0` (formaat: `kz-plugin-v` + versienummer)
   - KZ Contentmanager Plugin: `kz-contentmanager-v3.0.1` (formaat: `kz-contentmanager-v` + versienummer)
4. Zorg dat **target** op `main` staat.
5. Klik **"Publish release"**. Dit maakt en pusht de tag; het aanmaken van een release-zip-asset kun je aan de zip zelf overlaten (zie hierna) — een titel/omschrijving invullen is niet nodig, want de workflow overschrijft/vult de zip-asset toch aan.
6. De workflow `.github/workflows/release.yml` start automatisch op de tag-push, bouwt de juiste zip en **uploadt die als asset aan de zojuist gepubliceerde release** (de workflow herkent dat de release al bestaat en maakt 'm niet nogmaals aan — dat zou falen).
7. Binnen 6 uur (of direct via "Nu controleren op updates" in het dashboard) ziet de site de nieuwe versie.

Alternatief, als je liever vanaf je eigen machine werkt: `git tag <tag>` + `git push origin <tag>` daar werkt gewoon en triggert dezelfde workflow (die maakt dan zelf de release aan, inclusief zip-asset).

### Lokaal (zonder GitHub Actions)

```bash
./build.sh
```

Bouwt `dist/kz-plugin.zip` en `dist/kz-contentmanager-plugin.zip`, klaar om handmatig te uploaden of als release-asset toe te voegen.

## Ontwikkelen

- Beide plugins zijn class-based, met per WPBakery-element een eigen bestand onder `kz-plugin/includes/elements/`.
- Nieuwe elementen: maak een class met `register_shortcode()`, `register_vc_map()` en `render($atts)`, registreer die in `$element_classes` in `kz-plugin.php`, en voeg 'm toe aan de `$elements`-array in `includes/class-kz-admin.php` voor het instellingenoverzicht.
- Swiper (voor de post carrousel) wordt lokaal meegeleverd in `kz-plugin/assets/vendor/swiper/` — geen CDN-afhankelijkheid.
- `php -l` alle gewijzigde bestanden vóór het maken van een release.

## Belangrijk: KZ-Studio / REST API

KZ Plugin registreert twee WPBakery post-meta-velden (`_wpb_vc_js_status`, `_wpb_shortcodes_custom_css`) voor de REST API, zodat berichten die via KZ-Studio worden geplaatst de juiste WPBakery-opmaak/CSS meekrijgen. Zie de commentaar bij `register_wpbakery_meta()` in `kz-plugin.php` voor de achtergrond — dit is bewust overgenomen uit versie 7.1 en mag niet verwijderd worden.
