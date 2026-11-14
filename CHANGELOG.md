# Changelog KZ Plugin

Nieuwste onderaan. Vanaf 8.34.0 staan wijzigingen hier en in de GitHub-releases, niet meer in de pluginheader.

8.1.2: dummy-release om de volledige auto-update-cyclus end-to-end te
testen (geen functionele wijzigingen).

8.2.0: nieuw element KZ-Recente Galerijen — overzicht van de 5 meest
recente NextGEN Gallery-galerijen (shortcode kz_recente_galerijen).

8.2.1: KZ-Recente Galerijen — tekstkleur-optie, hover-effect op de
thumbnails, en de galerijlink wordt nu opgebouwd via het NextGEN-album
in plaats van get_permalink() (die op productie een kale query-string-
link teruggaf).

8.2.2: fix voor de albumlink uit 8.2.1 — de sortorder-kolom van
ngg_album is base64-gecodeerde JSON, geen PHP serialize(), waardoor de
albumkoppeling niet herkend werd.

8.2.3: KZ-Recente Galerijen — de uitzoom-ring is nu veel dunner en
gebruikt dezelfde kleur als de ingestelde tekstkleur, verheft zich
tijdens hover boven de rest van de rij, en een YYYY-MM-DD-datumprefix
in de galerijnaam wordt niet meer getoond.

8.3.0: nieuw element KZ-Winkel Producten — willekeurige selectie van
4 tot 6 op voorraad zijnde WooCommerce-producten (shortcode
kz_winkel_producten), in hetzelfde ontwerp als KZ-Recente Galerijen.

8.3.1: KZ-Winkel Producten — losse kleurinstelling voor de prijs
(naast de naam), en een optionele vaste rand (wit/rood) om de
productfoto, los van het hover-effect.

8.4.0: nieuw element KZ-Evenement Info — infoblok voor de ticketpagina
met rode kop, logo, datumblok, omschrijving, feitenlijst en kaartenknop
(shortcode kz_evenement_info).

8.4.1: KZ-Evenement Info — datum blijft naast de tekst tot een blok-
breedte van 420px (daarvoor al vanaf 600px eronder), en de datum mag
ook zonder jaartal (dd/mm) worden ingevuld. Blokken die naast elkaar
staan worden even hoog, met de knop onderaan.

8.4.2: KZ-Evenement Info — titel blijft naast het logo tot 420px
blokbreedte, net als de datum naast de tekst.

8.4.3: externe URL's in links worden niet meer volledig naar kleine
letters omgezet.

8.4.4: KZ-Evenement Info — op telefoonbreedte geen logo, en het
datumticket over de volle breedte met uitgeschreven datum ("ZA 14 NOV")
in plaats van 14/11 en de losse dagnaam erboven. (Stond eerst als 8.4.3
genoteerd, maar die versie was al uitgebracht met alleen de link-fix.)

8.4.5: geen functionele wijzigingen; nieuw versienummer zodat browsers
en caches de CSS en JS van KZ-Evenement Info opnieuw ophalen.

8.5.0: post type kz_evenement (REST: wp/v2/kz-evenementen) zodat
KZ-Studio evenementen kan aanmaken, plus nieuw element KZ-Evenementen
Overzicht (shortcode kz_evenementen_overzicht) dat alle zichtbare
evenementen als KZ-Evenement Info-blokken in rijen van 2 toont.

8.6.0: evenementen krijgen een categorie (kz_categorie); het overzicht
sorteert op datum + begintijd, filtert op categorie en laat verlopen
evenementen (vanaf 2 dagen na de datum) vanzelf weg. KZ-Evenement Info
kan automatisch het eerstvolgende evenement uit een categorie tonen (of
het laatste als er geen nieuw is). Rode koppen naast elkaar worden even
hoog zodat de balken op één lijn liggen. Label standaard leeg, feiten
standaard Waar / Prijs / Tijden.

8.7.0: Seizoenskaart — het evenement met categorie "Seizoenskaart"
staat altijd vooraan, verloopt nooit en toont in omgekeerde kleuren een
seizoen (bijv. 2026-2027, meta kz_seizoen, leeg = huidig seizoen) in
plaats van een datum. Ook als losse uitvoering van KZ-Evenement Info
(parameters variant en seizoen).

8.8.0: Link-In-Bio — eigen "linktree" op link.kraonigezwaone.nl (en
/link-in-bio/ op de gewone site) met logo, socials en knoppen. De inhoud
komt uit KZ-Studio (Website → Link-In-Bio) via Supabase; instellingen
onder KZ Plugin → Link-In-Bio.

8.9.0: Link-In-Bio — tussenkoppen om knoppen in groepjes te zetten, een
optionele foto over de volle breedte bij een knop, en de iconen Krant en
Feest. Hoort bij KZ-Studio-migratie 0041.

8.10.0: nieuw element KZ-Motto (shortcode kz_motto) — het motto als
afbeelding: Seizoensmotto, Kraonige Motto, 11e van de 11e-Motto of de
Keuze. Welke .svg bij welk motto hoort en wat de keuze is, beheer je in
KZ-Studio (Website → Overig → Motto, migratie 0042); opgehaald via
dezelfde Supabase-instellingen als de Link-In-Bio.

8.10.1: KZ-Motto — optie Marges (Aan = 20px boven en onder), standaard
Uit zodat bestaande pagina's niet verschuiven.

8.10.2: KZ-Motto — marge boven en onder los in te stellen (marge_boven,
marge_onder, in px), standaard 40px. De dropdown Marges uit 8.10.1 is
vervangen; het attribuut marges wordt nog geaccepteerd maar doet niets.

8.11.0: Link-In-Bio — drie iconen van de vereniging erbij: Optocht, Steek
en Zwaan (gevulde silhouetten).

8.12.0: nieuw element KZ-Pagina (shortcode kz_pagina) — een hele pagina uit
vaste secties (kop, intro, dag, tekst + foto, tickets, vragen, motto),
beheerd in KZ-Studio (Website → Pagina's, migratie 0044). De opmaak staat
vast in assets/css/kz-pagina.css; KZ-Studio levert alleen tekst, knoppen en
foto's. Opgehaald via dezelfde Supabase-instellingen als de Link-In-Bio.

8.12.1: KZ-Pagina — staat er niets, dan zien ingelogde redacteuren op de
pagina zelf een geel blokje met de reden en de laatste ophaalstatus. Alleen
een HTML-commentaar bleek onvindbaar: LiteSpeed Cache haalt dat weg.

8.13.0: KZ-Pagina lijkt meer op de oude pagina's. Knoppen zijn nu het
element KZ-Knop zelf (KZ-KNOP-ROOD op wit, KZ-KNOP-WIT op rood); koppen in
gewone hoofd- en kleine letters; tekst rood op wit; geen lijnen tussen de
onderdelen; meer ruimte tussen alinea's en foto's.

8.13.1: KZ-Pagina vraagt LiteSpeed Cache de pagina hooguit 2 minuten te
bewaren (litespeed_control_set_ttl). Anders zagen bezoekers na Publiceren
in KZ-Studio nog lang de oude versie; alleen ingelogd zag je de nieuwe.

8.14.0: KZ-Pagina — het motto is een band die meedoet met om en om (rood
of wit, of vast), en op wit worden de witte motto-svg's rood. Per sectie
kies je welk motto: KZ (kraonig), Seizoen, 11e van de 11e of Keuze. De
grote kop is nu gelijk aan de WPBakery-kop KZ-HEAD (48px/60px, 700).

8.14.1: KZ-Pagina — boven de grote kop evenveel ruimte als eronder (16px,
op telefoon 8px) in plaats van 40px erboven. De tijd van een onderdeel
staat niet meer als "[20:30]" achter de titel, maar als kleine contrastkop
erboven (rood op wit, wit op rood).

8.15.0: KZ-Pagina — losse onderdelen, tickets, vragen-groepen en vragen
kunnen in KZ-Studio verborgen worden (veld verborgen); die worden hier
overgeslagen.

8.16.0: KZ-Pagina — nieuw sjabloon Evenementen: 1, 2 of 3 blokken
KZ-Evenement Info naast elkaar, elk handmatig of automatisch (het
eerstvolgende evenement, eventueel uit één categorie), getekend met het
element zelf. Het sjabloon Tickets is weg; een oude tickets-sectie wordt
overgeslagen. Hoort bij KZ-Studio-migratie 0045.

8.17.0: KZ-Pagina — het sjabloon Evenementen heet in KZ-Studio Tickets en
krijgt een kop zoals de vragen. Tickets en vragen doen mee met om en om
rood en wit (veld kleur); op een rode band zijn de ticketblokken
omgekeerd. De slottekst onder de vragen staat gecentreerd.

8.18.0: KZ-Pagina — drie nieuwe sjablonen. Tekst (titel, intro, blokken
met tussenkop, tekst en knop; foto's mogen), Route (een tijdlijn met punten
en stappen met pijltjes, met de kaart ernaast) en Uitslagen (per jaar en
categorie, zoals het KZ-Tab Widget, maar op de server getekend; het nieuwe
assets/js/kz-pagina.js wisselt alleen de panelen). De grote kop wordt op
een heel smal scherm iets kleiner. Hoort bij KZ-Studio-migratie 0046.

8.19.0: KZ-Pagina — sjabloon Video: een YouTube-video (watch, youtu.be,
shorts …) ingesloten via youtube-nocookie.com, 16:9. Hoort bij
KZ-Studio-migratie 0047.

8.20.0: KZ-Pagina — sjabloon Vakken: tekst naast een KZ-Vindt Grid (het
element zelf, via KZ_Element_Vindt_Grid::html()), omgekeerd op een rode
band, met eventueel een kaart van Google Maps en een knop Route plannen
eronder. KZ-Vindt Grid krijgt per vak een optionele link (nieuwe
parameter `link`); bestaande grids blijven gelijk. Hoort bij
KZ-Studio-migratie 0049.

8.21.0: KZ-Pagina — de kaart is een eigen sjabloon Kaart (titel, adres,
kleur) in plaats van een veld van Vakken; Vakken tekent geen kaart meer.
Hoort bij KZ-Studio-migratie 0050, die bestaande adressen verhuist.

8.22.0: KZ-Pagina — sjabloon Datum + Sync (`datum_sync`): een datum (of
periode, of eigen tekst) boven de Intro van een andere pagina, met een knop
naar die pagina en het beeld om en om links en rechts. De Intro vult
KZ-Studio zelf in bij het ophalen (migratie 0051), uit de nieuwste
gepubliceerde versie van de bronpagina; zo blijft een overzicht als
/evenementen/ vanzelf gelijk aan de losse pagina's.

8.29.0: foto's uit KZ-Studio (Supabase Storage) van KZ-Pagina, Link-In-Bio
en KZ-Motto krijgen een kopie in wp-content/uploads/kz-studio/ (KZ_Spiegel,
via WP-Cron op de achtergrond). Die kopie wordt getekend zodra hij er is,
dus bij een storing van Supabase blijven ook de foto's staan.

8.30.0: twee nieuwe elementen, KZ-Groeperingen (shortcode kz_groeperingen,
ronde foto's) en KZ-Nieuws (shortcode kz_nieuws, met paginering via
?pagina=2). Ze vervangen de WPBakery Post Grids, die hun items via
admin-ajax ophalen met een nonce: in de LiteSpeed-cache (7 dagen) verloopt
die nonce na hooguit een dag en bleven de grids voor gasten leeg. Deze
staan meteen in de HTML. De pagina's krijgen een LiteSpeed-cachetag per post
type (KZ_GRID_post, KZ_GRID_groeperingen) die geleegd wordt als een bericht
van dat type verandert. Zie includes/class-kz-berichten-grid.php.

8.30.1: KZ-Groeperingen zoals de oude grid: de afbeelding staat helemaal in
een rode (of witte) cirkel, optioneel met een dunne witte of rode rand
(parameter rand), met de naam eronder in Work Sans (standaard aan) en meer
ruimte tussen de rijen (rij_ruimte). KZ-Nieuws krijgt paginering_kleur
(rood of wit), zodat de paginering op een rode achtergrond te zien is.

8.30.2: KZ-Groeperingen gebruikt niet meer de klasse kz-groep: daar hing op
de site nog Custom CSS van de oude grid aan, die het hele item rond afknipte
(ook de naam). Nu kz-groepering, met resets tegen CSS van het thema. De
afbeelding vult de cirkel weer helemaal (de afbeeldingen zijn vierkant met
een eigen rode achtergrond).

8.30.3: KZ-Groeperingen — een onderste rij die niet vol is, staat in het
midden (flex in plaats van grid), en bij hover wordt de cirkel 15% groter
en draait hij 8 graden, met een klein veer-effect.

8.31.0: KZ-Pagina — Intro en Route kunnen een tweede afbeelding hebben
(afbeeldingWit, kaartWit): de eerste komt op een rode band, de tweede op een
witte, ook als de kleur op "auto" om en om wisselt. Is er maar één, dan
komt die op elke kleur. Datum + Sync volgt de Intro waar hij uit komt.
Zie beeld_voor_kleur(); gelijk aan beeldVoorKleur() in KZ-Studio (2.23.0).

8.32.0: nieuw element KZ-Recente Albums (shortcode kz_recente_albums) — de
meest recente NextGEN Gallery-albums, in hetzelfde ontwerp als KZ-Recente
Galerijen. Standaard gesorteerd op de nieuwste galerij in het album (ook via
sub-albums), of op het laatst aangemaakte album; albums overslaan kan met
uitsluiten. Link: de gekoppelde pagina van het album, anders /fotos/{slug}/.

8.33.0: KZ-Recente Albums — sorteren op naam (naam_af, naam_op), grotere
albumnaam (titel_grootte, standaard 32px, schaalt terug op smal), en de link
is nu altijd /fotos/{albumnaam}/ (bijv. /fotos/2023-2024/) in plaats van de
gekoppelde pagina of de slug, die niet altijd klopten.

8.34.0: beveiliging en stabiliteit. Tab Widget zet namen en punten als tekst
neer (geen HTML meer); het GitHub-token gaat alleen nog mee met de eigen
download en staat niet meer leesbaar in het beheerscherm; strengere controle
op .svg-kopieën; bij een storing van Supabase wacht een bezoeker hooguit 3 s
en daarna een minuut niet opnieuw; evenementpagina's en het motto krijgen
een cachetag/TTL in LiteSpeed (verlopen evenementen blijven niet dagen staan);
geen fatale fout meer als WPBakery even uit staat; links naar bestanden in
/wp-content/ houden hun hoofdletters; KZ-Knop zonder link tekent geen lege
link meer; ticketpopup sluit met Esc en houdt "&" in de URL heel; mobiele
carrouselpijlen zijn met het toetsenbord te bedienen; de scan op oude plugins
draait eens per versie in plaats van bij elk beheerscherm.
