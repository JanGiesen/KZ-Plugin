# KZ-Plugin

WordPress-plugins voor [dekraonigezwaone.nl](https://www.kraonigezwaone.nl/) (WordPress 7.1, Pressville-thema, WPBakery Page Builder).

Deze monorepo bevat twee plugins:

| Map | Plugin | Doel |
|---|---|---|
| `kz-plugin/` | **KZ Plugin** | WPBakery-elementen: post carrousel, knop, event blok, header link, hover afbeelding, tab widget, KZ Vindt grid, ticket, recente galerijen, winkel producten, evenement info, evenementen overzicht, motto (+ post type `kz_evenement` voor KZ-Studio), en de Link-In-Bio-pagina op link.kraonigezwaone.nl |
| `kz-contentmanager-plugin/` | **KZ Contentmanager Plugin** | Haalt content op uit Google Docs en toont die via de shortcode `[kz-content]` |

Beide plugins zijn samenvoegingen/opvolgers van eerder losse plugins (KZ-Kraonige Zwaone Plugin 7.1, KZ Vindt, KZH Ticket Element, KZ-Kraonige Zwaone Contentmanager 2.0.0). Alle shortcode-namen en parameters zijn ongewijzigd overgenomen zodat bestaande content op de site blijft werken. Bij activatie deactiveren de nieuwe plugins automatisch de oude losse plugins (met een melding in het dashboard) om dubbele shortcode-registratie te voorkomen.

## Installatie

1. Download de gewenste zip (zie "Releases bouwen" hieronder, of gebruik een kant-en-klare release van GitHub).
2. Upload via **Plugins → Nieuwe plugin toevoegen → Plugin uploaden** in WordPress.
3. Activeer de plugin. Oude, overlappende plugins worden automatisch gedeactiveerd.

## Instellingenpagina's

- **KZ Plugin**: menu "KZ Plugin" — overzicht van alle elementen met shortcode en parameters, per element aan/uit-schakelaar (uitschakelen zet de render leeg maar houdt de shortcode geregistreerd, zodat content nooit een kale shortcode-tekst toont), updatestatus + "Nu controleren op updates", en de Supabase-koppeling voor de Link-In-Bio.
- **KZ Contentmanager Plugin**: menu "KZ Contentmanager" — documentenoverzicht met shortcodes, synchronisatie, instellingen (Google API key, Drive-map, webhook-URL, rate limit), en een Updates-tab.

Beide plugins delen de optie `kz_github_token` voor toegang tot een private GitHub-repo (zie hieronder).

## Link-In-Bio (link.kraonigezwaone.nl)

Sinds 8.8.0 tekent KZ Plugin een eigen "linktree": logo, kop, omschrijving, socials-iconen en grote knoppen (met of zonder icoon), in het rode of witte thema van het stijlboek. Sinds 8.9.0 kunnen knoppen in groepjes onder een tussenkop staan en een foto over de volle breedte krijgen (KZ-Studio-migratie `0041_link_in_bio_groepen_fotos.sql`; foto's alleen via `https://`). De inhoud beheer je in **KZ-Studio → Website → Link-In-Bio**; die staat in Supabase en de plugin haalt hem op via de openbare functie `link_in_bio_ophalen()` (KZ-Studio-migratie `0040_link_in_bio.sql`). Ophalen gebeurt hooguit eens per minuut; lukt het niet, dan blijft de laatste goede versie staan.

De pagina staat:

- op **https://link.kraonigezwaone.nl** — elk pad op dat subdomein behalve `/wp-…` toont de pagina;
- als reserve op **/link-in-bio/** van de gewone site.

De pagina is losse HTML (`includes/class-kz-link-in-bio.php` + `assets/css/kz-link-in-bio.css`), buiten het thema om, zodat hij snel laadt in de browser van Instagram en TikTok.

### Eenmalig instellen

1. **KZ-Studio**: migratie `0040_link_in_bio.sql` draaien (zie `supabase/README.md`, hoofdstuk 19, in die repo).
2. **WordPress → KZ Plugin → Link-In-Bio**: het Supabase-adres (staat al ingevuld) en de **anon**-key van KZ-Studio invullen (Supabase → Project Settings → API). Nooit de service-role key. Opslaan, en met "Link-In-Bio nu opnieuw ophalen" controleren dat "Laatst opgehaald" op *gelukt* staat.
3. **Hosting/DNS**: het subdomein `link.kraonigezwaone.nl` moet naar **dezelfde WordPress-installatie** wijzen als de site. Bij de meeste hosts heet dat een *alias*, *domeinalias* of *extra domein* met dezelfde map (document root) als de site; plus een SSL-certificaat voor het subdomein (bijv. Let's Encrypt). Een *redirect* is niet genoeg — dan komt de bezoeker op de hoofdsite uit.
   - Kan dat bij de host niet, stel het subdomein dan in als **doorverwijzing naar `https://www.kraonigezwaone.nl/link-in-bio/`**. Dat werkt meteen, alleen staat dan dat adres in de adresbalk.
4. Een pagina-cacheplugin moet het subdomein als eigen host behandelen (de meeste doen dat vanzelf). De pagina stuurt zelf `Cache-Control: max-age=60`.

Iconen, de URL-regel (`https://`, `http://`, `mailto:`, `tel:` — nooit `javascript:`) en de CSS staan ook in KZ-Studio, voor het voorbeeld in het beheerscherm (`src/lib/linkInBio.ts`, `src/pages/website/linkInBio.css`). Houd die gelijk.

## KZ-Motto

Sinds 8.10.0 is er het element **KZ-Motto** (`[kz_motto variant="keuze" breedte="1000"]`), ter vervanging van het losse HTML-blok met `KZH_MOTTO.svg`. `variant` is `seizoen` (Seizoensmotto), `kraonig` (Kraonige Motto), `elfelf` (11e van de 11e-Motto) of `keuze` (standaard). Met `marge_boven` en `marge_onder` (in px, sinds 8.10.2) stel je de ruimte boven en onder in; standaard 40, `0` = geen marge. Welke .svg bij welk motto hoort en welke er bij Keuze getoond wordt, beheer je in **KZ-Studio → Website → Overig → Motto** (KZ-Studio-migratie `0042_website_motto.sql`). De plugin haalt dat op via de openbare functie `motto_ophalen()`, met hetzelfde Supabase-adres en dezelfde anon-key als de Link-In-Bio, hooguit eens per minuut; lukt het niet, dan blijft de laatste goede versie staan. Is er bij een motto nog niets gekoppeld, dan toont het element niets (redacteuren zien in de bron een HTML-commentaar met de reden). Onder **KZ Plugin → Motto** staat de status en een knop om meteen opnieuw op te halen.

## KZ-Pagina

Sinds 8.12.0 is er het element **KZ-Pagina** (`[kz_pagina pagina="5dd"]`), dat een hele pagina tekent uit vaste secties: Kop, Intro, Dag (onderdelen met tijd en knop, plus een fotokolom), Tekst (blokken met tussenkop; foto's mogen; sinds 8.18.0), Tekst + foto, Route (tijdlijn met de kaart ernaast; sinds 8.18.0), Tickets (sjabloon `evenementen`: een kop en 1, 2 of 3 blokken via het element KZ-Evenement Info, elk handmatig of automatisch het eerstvolgende evenement, eventueel uit één categorie; sinds 8.16.0), Uitslagen (per jaar en categorie, zoals het KZ-Tab Widget maar op de server getekend, met `assets/js/kz-pagina.js` om door te klikken; sinds 8.18.0), Video (een YouTube-video via youtube-nocookie.com; sinds 8.19.0), Vakken (tekst naast een grid via het element KZ-Vindt Grid zelf, omgekeerd op rood; sinds 8.20.0), Kaart (Google Maps bij een adres, met een balk met het adres en een knop Route plannen; sinds 8.21.0, daarvoor een veld van Vakken), Datum + Sync (sjabloon `datum_sync`: een datum boven de Intro van een andere pagina, altijd gelijk aan wat daar gepubliceerd staat, met een knop naar die pagina en het beeld om en om links en rechts; sinds 8.22.0, KZ-Studio-migratie 0051 vult de Intro in bij het ophalen), Vragen (uitklappers in groepjes) en Motto (via KZ-Motto). Het oude sjabloon `tickets` is in 8.16.0 vervallen; zo'n sectie wordt overgeslagen. De inhoud — alleen tekst, knoppen en foto's — beheer je in **KZ-Studio → Website → Pagina's** (KZ-Studio-migratie `0044_website_paginas.sql`). De opmaak staat vast in `assets/css/kz-pagina.css`, dus er verspringt niets zoals bij losse WPBakery-blokken. Rode en witte banden wisselen vanzelf om en om (alle secties behalve de Kop; Tickets en Vragen sinds 8.17.0, met omgekeerde ticketblokken op rood), net als de kant waar de foto's staan.

Sinds 8.27.0 zijn er twee sjablonen bij. **Foto's** (`fotos`): 1 tot 12 foto's in een band, 1 tot 4 per rij (`kzp__galerij--1` t/m `--4`; op smal hooguit 2), via dezelfde `foto()` als elders. Sinds 8.27.1 met een verhouding: eigen (standaard, niets bijgesneden), liggend 3:2, staand 2:3 of vierkant 1:1, met focuspunt. Sinds 8.28.0 kan de fotokolom van Dag, Tekst en Uitslagen dat ook (standaard daar liggend, zoals eerst altijd), met één set klassen voor alles: `kzp--verhouding-eigen|liggend|staand|vierkant` (`VERHOUDINGEN`). **HTML** (`html`): eigen HTML uit KZ-Studio, die bij het tekenen door `wp_kses_post()` gaat — alleen wat WordPress in een bericht toestaat, geen script, iframe, style-blok of onclick, en shortcodes worden niet uitgevoerd. Haal die `wp_kses_post()` nooit weg: iedereen met een KZ-Studio-account kan zo'n blok vullen.

Sinds 8.24.0 is er de **Geleding-kop** (sjabloon `geleding`): bovenaan elke pagina van een geleding, partner of commissie, zoals de oude pagina's van Het Hof en de Raad van Elf. Op wit de knop *Geledingen, partners & commissies* (naar `/groeperingen/`, via KZ-Knop) en de titel als grote kop, daaronder een rode band met de afbeelding van de geleding in een witte cirkel van 200px. Zonder afbeelding in KZ-Studio neemt hij sinds 8.26.0 de uitgelichte afbeelding van het WordPress-bericht waar `[kz_pagina]` op staat (`uitgelichte_afbeelding()`, alleen https), en anders het logo. De band telt mee in het om en om en is altijd rood, dus de sectie erna is bij *auto* wit. KZ-Studio zet zo'n kop vanzelf bovenaan elke pagina in de categorie *Geledingen* (migratie 0054); de plugin tekent hem gewoon als sectie. Houd `GELEDING_KNOP` gelijk met KZ-Studio.

Sinds 8.23.0 heeft de jeugdafdeling HJC De Krössels een eigen kleurenschema: een pagina met de naam `hjc` of een naam die met `hjc-` begint (`[kz_pagina pagina="hjc-feestavond"]`) krijgt de klasse `kzp--hjc`. Alles wat normaal rood is (banden, tijden, knoppen, ticketblokken, het Vakken-grid, uitslagen, de kaart) wordt dan om en om geel (`#ffdd18`) en groen (`#0ea14b`), geteld over de rode banden; een witte band neemt het accent van de eerstvolgende rode. Alle tekst is zwart, net als het standaardlogo en het motto. De kleuren staan als `--kzp-geel` en `--kzp-groen` in `assets/css/kz-pagina.css`. Houd `is_hjc()` en het accent in `kleuren()` gelijk met `isHjc()` en `kleurenVan()` in KZ-Studio.

- Knoppen zijn het element KZ-Knop zelf (`KZ_Element_Button::render()`, sinds 8.13.0): `KZ-KNOP-WIT` op een rode band, `KZ-KNOP-ROOD` op wit.
- `pagina` is de naam van de pagina in KZ-Studio; `breedte` is `vol` (standaard: de banden lopen door tot de rand van het venster) of `kolom`.
- De plugin haalt de nieuwste **gepubliceerde** versie op via de openbare functie `website_pagina_ophalen()`, met hetzelfde Supabase-adres en dezelfde anon-key als de Link-In-Bio, hooguit eens per minuut per pagina. Lukt het niet, dan blijft de laatste goede versie staan. Wijzigingen in KZ-Studio komen pas op de site na **Publiceren** daar.
- `class-kz-pagina.php` laat alleen bekende velden door, kort ze in tot hun maximale lengte, zet tekst zelf om naar HTML (`tekst_html()`: eerst alles escapen, dan `**vet**`, `*cursief*`, `- opsomming`, `### tussenkop`, `[tekst](https://…)` en sinds 8.25.0 tabellen: regels die met `|` beginnen (Markdown, met een streepjesregel `| --- |` eronder wordt de eerste regel een vette kop) of een tab tussen de cellen hebben; opeenvolgende tabelregels worden één `<div class="kzp__tabel"><table>` zonder zichtbare lijnen, ook als het thema tabellen randen geeft — `tabel_cellen()` gelijk houden met `tabelCellen()` in KZ-Studio) en accepteert alleen https-foto's en https-, mailto- en tel-links.
- Houd gelijk met KZ-Studio (`src/lib/paginaSjablonen.ts`): de sjablonen en hun velden, `LIMIET`, `kleuren()` en `tekst_html()`. `kz-pagina.css` staat daar als kopie in `src/pages/website/pagina.css`, voor het voorbeeld.
- Een pagina met KZ-Pagina vraagt LiteSpeed Cache om hem hooguit 2 minuten te bewaren (sinds 8.13.1), zodat bezoekers een publicatie uit KZ-Studio binnen een paar minuten zien. Ingelogd zie je hem meteen; LiteSpeed slaat ingelogde bezoekers over.
- Staat er niets? Ingelogde redacteuren zien op de pagina zelf een geel blokje met de reden en de laatste ophaalstatus (sinds 8.12.1; daarvoor alleen een HTML-commentaar, en dat haalt LiteSpeed Cache weg). Bezoekers zien niets. Onder **KZ Plugin → Pagina's** staat per pagina wanneer hij voor het laatst is opgehaald, met een knop om de cache te legen.

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
