<?php
/**
 * Plugin Name: KZ Plugin
 * Plugin URI: https://github.com/JanGiesen/KZ-Plugin
 * Description: WPBakery-elementen voor de Kraonige Zwaone website (carrousel, knop, event blok, header link, hover afbeelding, tab widget, KZ Vindt grid, ticket, evenement info, evenementen overzicht, motto, pagina).
 * Version: 8.24.1
 * Author: Kraonige Zwaone
 * Text Domain: kz-plugin
 * Update URI: https://github.com/JanGiesen/KZ-Plugin
 *
 * Samenvoeging van:
 * - KZ-Kraonige Zwaone Plugin 7.1 (post carrousel, knop, event blok, header link, hover afbeelding, tab widget)
 * - KZ Vindt 1.0 (grid)
 * - KZH Ticket Element 1.0.0 (ticket)
 *
 * Belangrijk: alle shortcode-namen en parameters zijn ongewijzigd overgenomen
 * zodat bestaande content op de site niet breekt.
 *
 * 8.1.2: dummy-release om de volledige auto-update-cyclus end-to-end te
 * testen (geen functionele wijzigingen).
 *
 * 8.2.0: nieuw element KZ-Recente Galerijen — overzicht van de 5 meest
 * recente NextGEN Gallery-galerijen (shortcode kz_recente_galerijen).
 *
 * 8.2.1: KZ-Recente Galerijen — tekstkleur-optie, hover-effect op de
 * thumbnails, en de galerijlink wordt nu opgebouwd via het NextGEN-album
 * in plaats van get_permalink() (die op productie een kale query-string-
 * link teruggaf).
 *
 * 8.2.2: fix voor de albumlink uit 8.2.1 — de sortorder-kolom van
 * ngg_album is base64-gecodeerde JSON, geen PHP serialize(), waardoor de
 * albumkoppeling niet herkend werd.
 *
 * 8.2.3: KZ-Recente Galerijen — de uitzoom-ring is nu veel dunner en
 * gebruikt dezelfde kleur als de ingestelde tekstkleur, verheft zich
 * tijdens hover boven de rest van de rij, en een YYYY-MM-DD-datumprefix
 * in de galerijnaam wordt niet meer getoond.
 *
 * 8.3.0: nieuw element KZ-Winkel Producten — willekeurige selectie van
 * 4 tot 6 op voorraad zijnde WooCommerce-producten (shortcode
 * kz_winkel_producten), in hetzelfde ontwerp als KZ-Recente Galerijen.
 *
 * 8.3.1: KZ-Winkel Producten — losse kleurinstelling voor de prijs
 * (naast de naam), en een optionele vaste rand (wit/rood) om de
 * productfoto, los van het hover-effect.
 *
 * 8.4.0: nieuw element KZ-Evenement Info — infoblok voor de ticketpagina
 * met rode kop, logo, datumblok, omschrijving, feitenlijst en kaartenknop
 * (shortcode kz_evenement_info).
 *
 * 8.4.1: KZ-Evenement Info — datum blijft naast de tekst tot een blok-
 * breedte van 420px (daarvoor al vanaf 600px eronder), en de datum mag
 * ook zonder jaartal (dd/mm) worden ingevuld. Blokken die naast elkaar
 * staan worden even hoog, met de knop onderaan.
 *
 * 8.4.2: KZ-Evenement Info — titel blijft naast het logo tot 420px
 * blokbreedte, net als de datum naast de tekst.
 *
 * 8.4.3: externe URL's in links worden niet meer volledig naar kleine
 * letters omgezet.
 *
 * 8.4.4: KZ-Evenement Info — op telefoonbreedte geen logo, en het
 * datumticket over de volle breedte met uitgeschreven datum ("ZA 14 NOV")
 * in plaats van 14/11 en de losse dagnaam erboven. (Stond eerst als 8.4.3
 * genoteerd, maar die versie was al uitgebracht met alleen de link-fix.)
 *
 * 8.4.5: geen functionele wijzigingen; nieuw versienummer zodat browsers
 * en caches de CSS en JS van KZ-Evenement Info opnieuw ophalen.
 *
 * 8.5.0: post type kz_evenement (REST: wp/v2/kz-evenementen) zodat
 * KZ-Studio evenementen kan aanmaken, plus nieuw element KZ-Evenementen
 * Overzicht (shortcode kz_evenementen_overzicht) dat alle zichtbare
 * evenementen als KZ-Evenement Info-blokken in rijen van 2 toont.
 *
 * 8.6.0: evenementen krijgen een categorie (kz_categorie); het overzicht
 * sorteert op datum + begintijd, filtert op categorie en laat verlopen
 * evenementen (vanaf 2 dagen na de datum) vanzelf weg. KZ-Evenement Info
 * kan automatisch het eerstvolgende evenement uit een categorie tonen (of
 * het laatste als er geen nieuw is). Rode koppen naast elkaar worden even
 * hoog zodat de balken op één lijn liggen. Label standaard leeg, feiten
 * standaard Waar / Prijs / Tijden.
 *
 * 8.7.0: Seizoenskaart — het evenement met categorie "Seizoenskaart"
 * staat altijd vooraan, verloopt nooit en toont in omgekeerde kleuren een
 * seizoen (bijv. 2026-2027, meta kz_seizoen, leeg = huidig seizoen) in
 * plaats van een datum. Ook als losse uitvoering van KZ-Evenement Info
 * (parameters variant en seizoen).
 *
 * 8.8.0: Link-In-Bio — eigen "linktree" op link.kraonigezwaone.nl (en
 * /link-in-bio/ op de gewone site) met logo, socials en knoppen. De inhoud
 * komt uit KZ-Studio (Website → Link-In-Bio) via Supabase; instellingen
 * onder KZ Plugin → Link-In-Bio.
 *
 * 8.9.0: Link-In-Bio — tussenkoppen om knoppen in groepjes te zetten, een
 * optionele foto over de volle breedte bij een knop, en de iconen Krant en
 * Feest. Hoort bij KZ-Studio-migratie 0041.
 *
 * 8.10.0: nieuw element KZ-Motto (shortcode kz_motto) — het motto als
 * afbeelding: Seizoensmotto, Kraonige Motto, 11e van de 11e-Motto of de
 * Keuze. Welke .svg bij welk motto hoort en wat de keuze is, beheer je in
 * KZ-Studio (Website → Overig → Motto, migratie 0042); opgehaald via
 * dezelfde Supabase-instellingen als de Link-In-Bio.
 *
 * 8.10.1: KZ-Motto — optie Marges (Aan = 20px boven en onder), standaard
 * Uit zodat bestaande pagina's niet verschuiven.
 *
 * 8.10.2: KZ-Motto — marge boven en onder los in te stellen (marge_boven,
 * marge_onder, in px), standaard 40px. De dropdown Marges uit 8.10.1 is
 * vervangen; het attribuut marges wordt nog geaccepteerd maar doet niets.
 *
 * 8.11.0: Link-In-Bio — drie iconen van de vereniging erbij: Optocht, Steek
 * en Zwaan (gevulde silhouetten).
 *
 * 8.12.0: nieuw element KZ-Pagina (shortcode kz_pagina) — een hele pagina uit
 * vaste secties (kop, intro, dag, tekst + foto, tickets, vragen, motto),
 * beheerd in KZ-Studio (Website → Pagina's, migratie 0044). De opmaak staat
 * vast in assets/css/kz-pagina.css; KZ-Studio levert alleen tekst, knoppen en
 * foto's. Opgehaald via dezelfde Supabase-instellingen als de Link-In-Bio.
 *
 * 8.12.1: KZ-Pagina — staat er niets, dan zien ingelogde redacteuren op de
 * pagina zelf een geel blokje met de reden en de laatste ophaalstatus. Alleen
 * een HTML-commentaar bleek onvindbaar: LiteSpeed Cache haalt dat weg.
 *
 * 8.13.0: KZ-Pagina lijkt meer op de oude pagina's. Knoppen zijn nu het
 * element KZ-Knop zelf (KZ-KNOP-ROOD op wit, KZ-KNOP-WIT op rood); koppen in
 * gewone hoofd- en kleine letters; tekst rood op wit; geen lijnen tussen de
 * onderdelen; meer ruimte tussen alinea's en foto's.
 *
 * 8.13.1: KZ-Pagina vraagt LiteSpeed Cache de pagina hooguit 2 minuten te
 * bewaren (litespeed_control_set_ttl). Anders zagen bezoekers na Publiceren
 * in KZ-Studio nog lang de oude versie; alleen ingelogd zag je de nieuwe.
 *
 * 8.14.0: KZ-Pagina — het motto is een band die meedoet met om en om (rood
 * of wit, of vast), en op wit worden de witte motto-svg's rood. Per sectie
 * kies je welk motto: KZ (kraonig), Seizoen, 11e van de 11e of Keuze. De
 * grote kop is nu gelijk aan de WPBakery-kop KZ-HEAD (48px/60px, 700).
 *
 * 8.14.1: KZ-Pagina — boven de grote kop evenveel ruimte als eronder (16px,
 * op telefoon 8px) in plaats van 40px erboven. De tijd van een onderdeel
 * staat niet meer als "[20:30]" achter de titel, maar als kleine contrastkop
 * erboven (rood op wit, wit op rood).
 *
 * 8.15.0: KZ-Pagina — losse onderdelen, tickets, vragen-groepen en vragen
 * kunnen in KZ-Studio verborgen worden (veld verborgen); die worden hier
 * overgeslagen.
 *
 * 8.16.0: KZ-Pagina — nieuw sjabloon Evenementen: 1, 2 of 3 blokken
 * KZ-Evenement Info naast elkaar, elk handmatig of automatisch (het
 * eerstvolgende evenement, eventueel uit één categorie), getekend met het
 * element zelf. Het sjabloon Tickets is weg; een oude tickets-sectie wordt
 * overgeslagen. Hoort bij KZ-Studio-migratie 0045.
 *
 * 8.17.0: KZ-Pagina — het sjabloon Evenementen heet in KZ-Studio Tickets en
 * krijgt een kop zoals de vragen. Tickets en vragen doen mee met om en om
 * rood en wit (veld kleur); op een rode band zijn de ticketblokken
 * omgekeerd. De slottekst onder de vragen staat gecentreerd.
 *
 * 8.18.0: KZ-Pagina — drie nieuwe sjablonen. Tekst (titel, intro, blokken
 * met tussenkop, tekst en knop; foto's mogen), Route (een tijdlijn met punten
 * en stappen met pijltjes, met de kaart ernaast) en Uitslagen (per jaar en
 * categorie, zoals het KZ-Tab Widget, maar op de server getekend; het nieuwe
 * assets/js/kz-pagina.js wisselt alleen de panelen). De grote kop wordt op
 * een heel smal scherm iets kleiner. Hoort bij KZ-Studio-migratie 0046.
 *
 * 8.19.0: KZ-Pagina — sjabloon Video: een YouTube-video (watch, youtu.be,
 * shorts …) ingesloten via youtube-nocookie.com, 16:9. Hoort bij
 * KZ-Studio-migratie 0047.
 *
 * 8.20.0: KZ-Pagina — sjabloon Vakken: tekst naast een KZ-Vindt Grid (het
 * element zelf, via KZ_Element_Vindt_Grid::html()), omgekeerd op een rode
 * band, met eventueel een kaart van Google Maps en een knop Route plannen
 * eronder. KZ-Vindt Grid krijgt per vak een optionele link (nieuwe
 * parameter `link`); bestaande grids blijven gelijk. Hoort bij
 * KZ-Studio-migratie 0049.
 *
 * 8.21.0: KZ-Pagina — de kaart is een eigen sjabloon Kaart (titel, adres,
 * kleur) in plaats van een veld van Vakken; Vakken tekent geen kaart meer.
 * Hoort bij KZ-Studio-migratie 0050, die bestaande adressen verhuist.
 *
 * 8.22.0: KZ-Pagina — sjabloon Datum + Sync (`datum_sync`): een datum (of
 * periode, of eigen tekst) boven de Intro van een andere pagina, met een knop
 * naar die pagina en het beeld om en om links en rechts. De Intro vult
 * KZ-Studio zelf in bij het ophalen (migratie 0051), uit de nieuwste
 * gepubliceerde versie van de bronpagina; zo blijft een overzicht als
 * /evenementen/ vanzelf gelijk aan de losse pagina's.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Directe toegang niet toegestaan.
}

define( 'KZ_PLUGIN_VERSION', '8.24.1' );
define( 'KZ_PLUGIN_FILE', __FILE__ );
define( 'KZ_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'KZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KZ_PLUGIN_GITHUB_REPO', 'JanGiesen/KZ-Plugin' );

class KZ_Plugin {

    /** @var KZ_Plugin_Updater */
    private $updater;

    /** @var array<string,string> map van element-key naar class-naam */
    private $element_classes = array(
        'post_carousel'   => 'KZ_Element_Post_Carousel',
        'button'          => 'KZ_Element_Button',
        'eventhome_block' => 'KZ_Element_Eventhome_Block',
        'header_link'     => 'KZ_Element_Header_Link',
        'hover_image'     => 'KZ_Element_Hover_Image',
        'tab_widget'      => 'KZ_Element_Tab_Widget',
        'vindt_grid'      => 'KZ_Element_Vindt_Grid',
        'ticket'          => 'KZ_Element_Ticket',
        'recent_galleries' => 'KZ_Element_Recent_Galleries',
        'recent_products'  => 'KZ_Element_Recent_Products',
        'event_info'       => 'KZ_Element_Event_Info',
        'event_overview'   => 'KZ_Element_Event_Overview',
        'motto'            => 'KZ_Element_Motto',
        'pagina'           => 'KZ_Element_Pagina',
    );

    public function __construct() {
        $this->load_dependencies();

        $this->updater = new KZ_Plugin_Updater(
            KZ_PLUGIN_FILE,
            KZ_PLUGIN_VERSION,
            KZ_PLUGIN_GITHUB_REPO,
            'kz-plugin-v',
            'kz-plugin.zip'
        );

        new KZ_Admin( $this->updater );

        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'vc_before_init', array( $this, 'register_wpbakery_elements' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'maybe_deactivate_legacy_plugins' ) );
        add_action( 'admin_notices', array( $this, 'legacy_plugin_notice' ) );

        // Registreer de twee WPBakery post-meta's zodat de REST API (KZ-Studio)
        // ze accepteert. Zie README voor achtergrond.
        add_action( 'init', array( $this, 'register_wpbakery_meta' ) );

        // Post type voor evenementen uit KZ-Studio (REST: wp/v2/kz-evenementen).
        KZ_Evenementen::init();

        // Link-In-Bio op link.kraonigezwaone.nl (inhoud uit KZ-Studio).
        KZ_Link_In_Bio::init();
    }

    private function load_dependencies() {
        require_once KZ_PLUGIN_PATH . 'includes/class-kz-updater.php';
        require_once KZ_PLUGIN_PATH . 'includes/class-kz-admin.php';
        require_once KZ_PLUGIN_PATH . 'includes/class-kz-link-helper.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-post-carousel.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-button.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-eventhome-block.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-header-link.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-hover-image.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-tab-widget.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-vindt-grid.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-ticket.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-recent-galleries.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-recent-products.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-event-info.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-event-overview.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-motto.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-pagina.php';
        require_once KZ_PLUGIN_PATH . 'includes/class-kz-evenementen.php';
        require_once KZ_PLUGIN_PATH . 'includes/class-kz-link-in-bio.php';
    }

    /**
     * Geeft de twee WPBakery-post-meta's vrij voor de REST API.
     *
     * KZ-Studio plaatst nieuwsberichten via de standaard WordPress REST API. Die
     * accepteert `meta` alleen voor velden die expliciet zijn geregistreerd, en
     * WPBakery registreert deze twee niet — die worden normaal bij het opslaan
     * in de editor weggeschreven, en daar komt een bericht uit de REST API nooit
     * langs. Zonder deze registratie verschijnt het bericht wel, maar zonder de
     * custom CSS van de elementen (de marges kloppen dan niet).
     */
    public function register_wpbakery_meta() {
        $velden = array(
            '_wpb_vc_js_status'          => 'sanitize_text_field',
            '_wpb_shortcodes_custom_css' => 'wp_strip_all_tags',
        );

        foreach ( $velden as $sleutel => $schoonmaker ) {
            register_post_meta(
                'post',
                $sleutel,
                array(
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => true,
                    'sanitize_callback' => $schoonmaker,
                    'auth_callback'     => function () {
                        return current_user_can( 'edit_posts' );
                    },
                )
            );
        }
    }

    public function register_shortcodes() {
        foreach ( $this->element_classes as $key => $class ) {
            if ( KZ_Admin::is_element_enabled( $key ) ) {
                call_user_func( array( $class, 'register_shortcode' ) );
            } else {
                // Shortcode blijft geregistreerd (levert lege string) zodat
                // bestaande content nooit een kale shortcode-tekst toont.
                add_shortcode( constant( "{$class}::SHORTCODE" ), '__return_empty_string' );
            }
        }
    }

    public function register_wpbakery_elements() {
        foreach ( $this->element_classes as $key => $class ) {
            if ( KZ_Admin::is_element_enabled( $key ) ) {
                call_user_func( array( $class, 'register_vc_map' ) );
            }
        }
    }

    public function register_assets() {
        wp_register_style( 'kz-plugin-style', KZ_PLUGIN_URL . 'assets/css/style.css', array(), KZ_PLUGIN_VERSION );
        wp_enqueue_style( 'kz-plugin-style' );

        wp_register_style( 'kz-vindt-style', KZ_PLUGIN_URL . 'assets/css/kz-vindt.css', array(), KZ_PLUGIN_VERSION );

        wp_register_style( 'kz-recente-galerijen-style', KZ_PLUGIN_URL . 'assets/css/kz-recente-galerijen.css', array(), KZ_PLUGIN_VERSION );

        wp_register_style( 'kz-winkel-producten-style', KZ_PLUGIN_URL . 'assets/css/kz-winkel-producten.css', array(), KZ_PLUGIN_VERSION );

        wp_register_style( 'kz-evenement-info-style', KZ_PLUGIN_URL . 'assets/css/kz-evenement-info.css', array(), KZ_PLUGIN_VERSION );
        wp_register_style( 'kz-evenementen-overzicht-style', KZ_PLUGIN_URL . 'assets/css/kz-evenementen-overzicht.css', array( 'kz-evenement-info-style' ), KZ_PLUGIN_VERSION );
        wp_register_style( 'kz-pagina-style', KZ_PLUGIN_URL . 'assets/css/kz-pagina.css', array(), KZ_PLUGIN_VERSION );
        wp_register_style( 'kz-evenement-info-fonts', 'https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@700&family=Work+Sans:wght@400;500&display=swap', array(), null );

        wp_register_style( 'swiper', KZ_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.css', array(), '11' );
        wp_register_script( 'swiper', KZ_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.js', array(), '11', true );
        wp_register_script( 'kz-carousel', KZ_PLUGIN_URL . 'assets/js/kz-carousel.js', array( 'swiper' ), KZ_PLUGIN_VERSION, true );
        wp_register_script( 'kz-evenement-info', KZ_PLUGIN_URL . 'assets/js/kz-evenement-info.js', array(), KZ_PLUGIN_VERSION, true );
        wp_register_script( 'kz-tab-widget', KZ_PLUGIN_URL . 'assets/js/kz-tab-widget.js', array(), KZ_PLUGIN_VERSION, true );
        wp_register_script( 'kz-pagina', KZ_PLUGIN_URL . 'assets/js/kz-pagina.js', array(), KZ_PLUGIN_VERSION, true );
    }

    public function enqueue_admin_assets() {
        wp_enqueue_style( 'kz-plugin-admin', KZ_PLUGIN_URL . 'assets/css/admin.css', array(), KZ_PLUGIN_VERSION );
    }

    /**
     * Deactiveert automatisch de oude losse plugins die nu in KZ-Plugin zijn
     * samengevoegd, zodat shortcodes niet dubbel geregistreerd raken.
     */
    public function maybe_deactivate_legacy_plugins() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $legacy_names = array(
            'KZ-Kraonige Zwaone Plugin',
            'KZ Vindt',
            'KZH Ticket Element',
        );

        $all_plugins    = get_plugins();
        $deactivated    = array();

        foreach ( $all_plugins as $plugin_path => $plugin_data ) {
            if ( $plugin_path === plugin_basename( KZ_PLUGIN_FILE ) ) {
                continue;
            }
            if ( in_array( $plugin_data['Name'], $legacy_names, true ) && is_plugin_active( $plugin_path ) ) {
                deactivate_plugins( $plugin_path );
                $deactivated[] = $plugin_data['Name'];
            }
        }

        if ( ! empty( $deactivated ) ) {
            set_transient( 'kz_plugin_legacy_deactivated', $deactivated, MINUTE_IN_SECONDS * 5 );
        }
    }

    public function legacy_plugin_notice() {
        $deactivated = get_transient( 'kz_plugin_legacy_deactivated' );
        if ( empty( $deactivated ) ) {
            return;
        }
        delete_transient( 'kz_plugin_legacy_deactivated' );
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>KZ Plugin</strong> heeft de volgende oude plugin(s) automatisch gedeactiveerd omdat de functionaliteit nu is overgenomen:
                <?php echo esc_html( implode( ', ', $deactivated ) ); ?>.
                Ze kunnen desgewenst verwijderd worden.
            </p>
        </div>
        <?php
    }
}

new KZ_Plugin();
