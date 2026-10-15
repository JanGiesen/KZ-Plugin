<?php
/**
 * Link-In-Bio — de eigen "linktree" van de Kraonige Zwaone op
 * https://link.kraonigezwaone.nl (en, als reserve, op /link-in-bio/ van de
 * gewone site).
 *
 * De inhoud (logo-kop, omschrijving, socials, knoppen) wordt beheerd in
 * KZ-Studio (Website → Link-In-Bio) en staat in Supabase. Deze klasse haalt die
 * op via de openbare functie link_in_bio_ophalen() (KZ-Studio, migratie 0040),
 * bewaart hem een minuut, en tekent de pagina als losse HTML — buiten het
 * thema om, zodat hij snel laadt in de browser van Instagram en TikTok.
 *
 * Het subdomein moet naar dezelfde WordPress-installatie wijzen als de site
 * (bij de hostingpartij als alias/extra domein); zie README.md.
 *
 * Houd gelijk met KZ-Studio (src/lib/linkInBio.ts): iconen(), veilige_url()
 * en de markup van render_inhoud() — het voorbeeld daar gebruikt dezelfde
 * klassen en een kopie van assets/css/kz-link-in-bio.css.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Link_In_Bio {

    /** Het subdomein waarop de pagina de hele site vervangt. */
    const HOST = 'link.kraonigezwaone.nl';

    /** Reservepad op de gewone site: /link-in-bio/. */
    const PAD = 'link-in-bio';

    const OPTIE_URL    = 'kz_link_in_bio_supabase_url';
    const OPTIE_SLEUTEL = 'kz_link_in_bio_supabase_key';
    /** De laatste goede inhoud, voor als Supabase even niet antwoordt. */
    const OPTIE_LAATST = 'kz_link_in_bio_laatst';
    /** Wanneer en met welk resultaat er voor het laatst is opgehaald. */
    const OPTIE_STATUS = 'kz_link_in_bio_status';
    const CACHE        = 'kz_link_in_bio';
    const CACHE_SECONDEN = 60;

    /** Het Supabase-project van KZ-Studio. */
    const STANDAARD_URL = 'https://grmynufrzcuaqanbarbq.supabase.co';

    public static function init() {
        // parse_request: na het laden van plugins, maar vóór de canonieke
        // redirect van WordPress (template_redirect), die het subdomein anders
        // naar de hoofdsite zou sturen.
        add_action( 'parse_request', array( __CLASS__, 'misschien_tonen' ), 0 );
    }

    public static function pagina_url() {
        return 'https://' . self::HOST . '/';
    }

    /**
     * Hoort dit verzoek bij de Link-In-Bio? Op het subdomein: alles behalve
     * de WordPress-paden zelf (wp-admin, wp-json, wp-login …). Op de gewone
     * site: alleen /link-in-bio/.
     */
    public static function is_link_verzoek() {
        $host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
        $host = preg_replace( '/:\d+$/', '', $host );
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        $pad  = (string) wp_parse_url( $uri, PHP_URL_PATH );

        if ( self::HOST === $host ) {
            return ! preg_match( '#^/wp-#', $pad );
        }

        $basis = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
        return untrailingslashit( $pad ) === untrailingslashit( $basis ) . '/' . self::PAD;
    }

    public static function misschien_tonen() {
        if ( ! self::is_link_verzoek() ) {
            return;
        }
        $methode = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
        if ( 'GET' !== $methode && 'HEAD' !== $methode ) {
            return;
        }

        $data = self::gegevens();

        status_header( 200 );
        header( 'Content-Type: text/html; charset=utf-8' );
        // Kort cachen mag: de inhoud zelf ververst ook maar eens per minuut.
        header( 'Cache-Control: public, max-age=' . self::CACHE_SECONDEN );
        header_remove( 'Expires' );
        header_remove( 'Pragma' );

        if ( 'HEAD' !== $methode ) {
            echo self::render_pagina( $data ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_pagina escapet zelf.
        }
        exit;
    }

    // ------------------------------------------------------------------
    // Ophalen
    // ------------------------------------------------------------------

    /**
     * De inhoud voor de pagina: uit de cache, anders vers uit Supabase, en als
     * dat mislukt de laatste goede versie. Null als er nog nooit iets is
     * opgehaald.
     */
    public static function gegevens() {
        $cache = get_transient( self::CACHE );
        if ( is_array( $cache ) ) {
            return $cache;
        }

        $vers = self::ophalen();
        if ( is_array( $vers ) ) {
            set_transient( self::CACHE, $vers, self::CACHE_SECONDEN );
            update_option( self::OPTIE_LAATST, $vers, false );
            return $vers;
        }

        $laatst = get_option( self::OPTIE_LAATST );
        if ( is_array( $laatst ) ) {
            // Kort cachen, zodat een storing bij Supabase niet elke bezoeker
            // vijf seconden laat wachten.
            set_transient( self::CACHE, $laatst, self::CACHE_SECONDEN );
            return $laatst;
        }
        return null;
    }

    /** Leegt de cache, zodat het volgende bezoek vers ophaalt. */
    public static function vergeet_cache() {
        delete_transient( self::CACHE );
    }

    /**
     * Roept link_in_bio_ophalen() aan met de anon-key. Geeft de opgeschoonde
     * inhoud terug, of null bij een fout (die in OPTIE_STATUS komt).
     */
    public static function ophalen() {
        $url     = untrailingslashit( (string) get_option( self::OPTIE_URL, self::STANDAARD_URL ) );
        $sleutel = trim( (string) get_option( self::OPTIE_SLEUTEL, '' ) );

        if ( '' === $url || '' === $sleutel ) {
            self::zet_status( false, 'Supabase-adres of anon-key ontbreekt (KZ Plugin → Link-In-Bio).' );
            return null;
        }

        $antwoord = wp_remote_post(
            $url . '/rest/v1/rpc/link_in_bio_ophalen',
            array(
                'timeout' => 5,
                'headers' => array(
                    'apikey'        => $sleutel,
                    'Authorization' => 'Bearer ' . $sleutel,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                'body'    => '{}',
            )
        );

        if ( is_wp_error( $antwoord ) ) {
            self::zet_status( false, $antwoord->get_error_message() );
            return null;
        }
        $code = wp_remote_retrieve_response_code( $antwoord );
        $json = json_decode( wp_remote_retrieve_body( $antwoord ), true );
        if ( 200 !== $code || ! is_array( $json ) ) {
            $melding = is_array( $json ) && isset( $json['message'] ) ? $json['message'] : 'HTTP ' . $code;
            self::zet_status( false, $melding );
            return null;
        }

        $schoon = self::schoon( $json );
        self::zet_status( true, count( $schoon['links'] ) . ' links' );
        return $schoon;
    }

    private static function zet_status( $ok, $melding ) {
        update_option(
            self::OPTIE_STATUS,
            array(
                'ok'      => (bool) $ok,
                'melding' => (string) $melding,
                'tijd'    => time(),
            ),
            false
        );
    }

    /** Alleen de velden die we kennen, in de vorm die we verwachten. */
    private static function schoon( array $json ) {
        $i     = isset( $json['instellingen'] ) && is_array( $json['instellingen'] ) ? $json['instellingen'] : array();
        $thema = isset( $i['thema'] ) && 'wit' === $i['thema'] ? 'wit' : 'rood';

        $links = array();
        if ( isset( $json['links'] ) && is_array( $json['links'] ) ) {
            foreach ( $json['links'] as $l ) {
                if ( ! is_array( $l ) ) {
                    continue;
                }
                $soort = isset( $l['soort'] ) && 'social' === $l['soort'] ? 'social' : 'knop';
                $links[] = array(
                    'soort' => $soort,
                    'titel' => isset( $l['titel'] ) ? (string) $l['titel'] : '',
                    'url'   => isset( $l['url'] ) ? (string) $l['url'] : '',
                    'icoon' => isset( $l['icoon'] ) ? (string) $l['icoon'] : '',
                );
            }
        }

        return array(
            'instellingen' => array(
                'titel'        => isset( $i['titel'] ) ? (string) $i['titel'] : '',
                'omschrijving' => isset( $i['omschrijving'] ) ? (string) $i['omschrijving'] : '',
                'voettekst'    => isset( $i['voettekst'] ) ? (string) $i['voettekst'] : '',
                'thema'        => $thema,
            ),
            'links'        => $links,
        );
    }

    // ------------------------------------------------------------------
    // Tekenen
    // ------------------------------------------------------------------

    /**
     * Zelfde regel als isGeldigeUrl() in KZ-Studio en de regex in
     * link_in_bio_ophalen(); geeft '' terug voor alles wat daar niet aan
     * voldoet (zoals javascript:).
     */
    public static function veilige_url( $url ) {
        $url = trim( (string) $url );
        if ( ! preg_match( '#^(https?://\S+|mailto:\S+|tel:[0-9+() -]+)$#i', $url ) ) {
            return '';
        }
        return esc_url( $url, array( 'http', 'https', 'mailto', 'tel' ) );
    }

    /** Een icoon als inline SVG, of '' bij een onbekende sleutel. */
    public static function icoon_svg( $sleutel ) {
        $iconen = self::iconen();
        if ( ! isset( $iconen[ $sleutel ] ) ) {
            return '';
        }
        $icoon = $iconen[ $sleutel ];
        return '<svg viewBox="' . esc_attr( $icoon['viewBox'] ) . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icoon['svg'] . '</svg>';
    }

    /** De instellingen voor als er nog nooit iets is opgehaald. */
    private static function lege_instellingen() {
        return array(
            'titel'        => '',
            'omschrijving' => '',
            'voettekst'    => "Huusse' Helau'!",
            'thema'        => 'rood',
        );
    }

    /** Het hele HTML-document. */
    public static function render_pagina( $data ) {
        $inst  = is_array( $data ) ? $data['instellingen'] : self::lege_instellingen();
        $thema = 'wit' === $inst['thema'] ? 'wit' : 'rood';
        $titel = '' !== trim( $inst['titel'] ) ? $inst['titel'] : 'De Kraonige Zwaone';
        $css   = KZ_PLUGIN_URL . 'assets/css/kz-link-in-bio.css?ver=' . rawurlencode( KZ_PLUGIN_VERSION );
        $fonts = 'https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@700&family=Work+Sans:wght@400;500&display=swap';
        $icoon = KZ_PLUGIN_URL . 'assets/img/kz-logo-rood.svg';

        ob_start();
        ?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo esc_html( $titel ); ?></title>
<?php if ( '' !== trim( $inst['omschrijving'] ) ) : ?>
<meta name="description" content="<?php echo esc_attr( $inst['omschrijving'] ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $inst['omschrijving'] ); ?>">
<?php endif; ?>
<meta property="og:title" content="<?php echo esc_attr( $titel ); ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo esc_url( self::pagina_url() ); ?>">
<link rel="canonical" href="<?php echo esc_url( self::pagina_url() ); ?>">
<meta name="theme-color" content="<?php echo 'wit' === $thema ? '#ffffff' : '#e83f35'; ?>">
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( $icoon ); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?php echo esc_url( $fonts ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>">
</head>
<body class="kzl-pagina kzl-pagina--<?php echo esc_attr( $thema ); ?>">
<?php echo self::render_inhoud( $data ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_inhoud escapet zelf. ?>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * De pagina zelf (zonder <html>/<head>). Dezelfde opbouw als
     * Voorbeeld() in KZ-Studio (src/pages/website/LinkInBio.tsx).
     */
    public static function render_inhoud( $data ) {
        $inst   = is_array( $data ) ? $data['instellingen'] : self::lege_instellingen();
        $links  = is_array( $data ) ? $data['links'] : array();
        $thema  = 'wit' === $inst['thema'] ? 'wit' : 'rood';
        $logo   = KZ_PLUGIN_URL . ( 'wit' === $thema ? 'assets/img/kz-logo-rood.svg' : 'assets/img/kz-logo-wit.svg' );
        $iconen = self::iconen();

        $socials = array();
        $knoppen = array();
        foreach ( $links as $l ) {
            $url = self::veilige_url( $l['url'] );
            if ( '' === $url ) {
                continue;
            }
            if ( 'social' === $l['soort'] ) {
                $svg = self::icoon_svg( $l['icoon'] );
                if ( '' === $svg ) {
                    continue;
                }
                $label     = '' !== trim( $l['titel'] ) ? $l['titel'] : $iconen[ $l['icoon'] ]['label'];
                $socials[] = '<li><a class="kzl__social" href="' . $url . '" aria-label="' . esc_attr( $label ) . '">' . $svg . '</a></li>';
            } else {
                if ( '' === trim( $l['titel'] ) ) {
                    continue;
                }
                $svg       = self::icoon_svg( $l['icoon'] );
                $icoon     = '' !== $svg ? '<span class="kzl__knop-icoon">' . $svg . '</span>' : '';
                $knoppen[] = '<li><a class="kzl__knop" href="' . $url . '">' . $icoon . '<span class="kzl__knop-tekst"><span>' . esc_html( $l['titel'] ) . '</span></span></a></li>';
            }
        }

        $html  = '<div class="kzl kzl--' . esc_attr( $thema ) . '"><main class="kzl__binnen">';
        $html .= '<img class="kzl__logo" src="' . esc_url( $logo ) . '" alt="De Kraonige Zwaone">';
        if ( '' !== trim( $inst['titel'] ) ) {
            $html .= '<h1 class="kzl__titel"><span>' . esc_html( $inst['titel'] ) . '</span></h1>';
        }
        if ( '' !== trim( $inst['omschrijving'] ) ) {
            $html .= '<p class="kzl__omschrijving">' . esc_html( $inst['omschrijving'] ) . '</p>';
        }
        if ( $socials ) {
            $html .= '<ul class="kzl__socials">' . implode( '', $socials ) . '</ul>';
        }
        if ( $knoppen ) {
            $html .= '<ul class="kzl__knoppen">' . implode( '', $knoppen ) . '</ul>';
        } elseif ( ! is_array( $data ) ) {
            $html .= '<p class="kzl__leeg">Hier komen binnenkort onze links.</p>';
        }
        if ( '' !== trim( $inst['voettekst'] ) ) {
            $html .= '<p class="kzl__voet"><span>' . esc_html( $inst['voettekst'] ) . '</span></p>';
        }
        $html .= '</main></div>';
        return $html;
    }

    /**
     * De iconen: sleutel => label, viewBox en de binnenkant van de <svg>.
     * Overgenomen uit ICONEN in KZ-Studio (src/lib/linkInBio.ts) — wijzig ze
     * daar én hier, zodat het voorbeeld en de pagina gelijk blijven. Vaste
     * tekst, nooit invoer van gebruikers.
     */
    public static function iconen() {
        return array(
            'instagram' => array(
                'label'   => 'Instagram',
                'social'  => true,
                'viewBox' => '0 0 24 24',
                'svg'     => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="0.9" fill="currentColor" stroke="none"/>',
            ),
            'facebook' => array(
                'label'   => 'Facebook',
                'social'  => true,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path fill="currentColor" stroke="none" d="M12 2.04C6.5 2.04 2 6.53 2 12.06c0 5 3.66 9.15 8.44 9.9v-7H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.23.19 2.23.19v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.9h-2.33v7A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04z"/>',
            ),
            'tiktok' => array(
                'label'   => 'TikTok',
                'social'  => true,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 3c.4 2.6 2.4 4.6 5 4.8"/>',
            ),
            'youtube' => array(
                'label'   => 'YouTube',
                'social'  => true,
                'viewBox' => '0 0 24 24',
                'svg'     => '<rect x="2.5" y="5.5" width="19" height="13" rx="3.5"/><path d="M10 9.2v5.6l4.8-2.8z" fill="currentColor"/>',
            ),
            'whatsapp' => array(
                'label'   => 'WhatsApp',
                'social'  => true,
                'viewBox' => '0 0 20 20',
                'svg'     => '<path fill="currentColor" stroke="none" transform="translate(-244,-7439)" d="M259.821,7453.12124 C259.58,7453.80344 258.622,7454.36761 257.858,7454.53266 C257.335,7454.64369 256.653,7454.73172 254.355,7453.77943 C251.774,7452.71011 248.19,7448.90097 248.19,7446.36621 C248.19,7445.07582 248.934,7443.57337 250.235,7443.57337 C250.861,7443.57337 250.999,7443.58538 251.205,7444.07952 C251.446,7444.6617 252.034,7446.09613 252.104,7446.24317 C252.393,7446.84635 251.81,7447.19946 251.387,7447.72462 C251.252,7447.88266 251.099,7448.05372 251.27,7448.3478 C251.44,7448.63589 252.028,7449.59418 252.892,7450.36341 C254.008,7451.35771 254.913,7451.6748 255.237,7451.80984 C255.478,7451.90987 255.766,7451.88687 255.942,7451.69881 C256.165,7451.45774 256.442,7451.05762 256.724,7450.6635 C256.923,7450.38141 257.176,7450.3464 257.441,7450.44643 C257.62,7450.50845 259.895,7451.56477 259.991,7451.73382 C260.062,7451.85686 260.062,7452.43903 259.821,7453.12124 M254.002,7439 L253.997,7439 L253.997,7439 C248.484,7439 244,7443.48535 244,7449 C244,7451.18666 244.705,7453.21526 245.904,7454.86076 L244.658,7458.57687 L248.501,7457.3485 C250.082,7458.39482 251.969,7459 254.002,7459 C259.515,7459 264,7454.51465 264,7449 C264,7443.48535 259.515,7439 254.002,7439"/>',
            ),
            'spotify' => array(
                'label'   => 'Spotify',
                'social'  => true,
                'viewBox' => '0 0 24 24',
                'svg'     => '<circle cx="12" cy="12" r="9"/><path d="M7.5 9.5c3-1 6.5-.7 9 .8"/><path d="M8 12.6c2.5-.7 5.2-.4 7.2.8"/><path d="M8.6 15.4c1.9-.5 3.8-.3 5.4.6"/>',
            ),
            'x' => array(
                'label'   => 'X',
                'social'  => true,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path d="M4 4h4.5L20 20h-4.5z"/><path d="M19.5 4l-6.4 7M10.9 13L4.5 20"/>',
            ),
            'website' => array(
                'label'   => 'Website',
                'social'  => true,
                'viewBox' => '0 0 24 24',
                'svg'     => '<circle cx="12" cy="12" r="9"/><line x1="3" y1="12" x2="21" y2="12"/><path d="M12 3c3.2 2.6 3.2 15.4 0 18"/><path d="M12 3c-3.2 2.6-3.2 15.4 0 18"/>',
            ),
            'mail' => array(
                'label'   => 'E-mail',
                'social'  => true,
                'viewBox' => '0 0 24 24',
                'svg'     => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="M3.5 6l8.5 7 8.5-7"/>',
            ),
            'ticket' => array(
                'label'   => 'Ticket',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path d="M3 7h18v3a2 2 0 0 0 0 4v3H3v-3a2 2 0 0 0 0-4z"/><path d="M14.5 7.5v1.5M14.5 11.25v1.5M14.5 15v1.5"/>',
            ),
            'kalender' => array(
                'label'   => 'Kalender',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<rect x="3.5" y="5" width="17" height="15" rx="1.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
            ),
            'foto' => array(
                'label'   => 'Foto\'s',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path d="M4 8h3l2-2.5h6L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
            ),
            'video' => array(
                'label'   => 'Video',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<rect x="3" y="6" width="13" height="12" rx="1.5"/><path d="M16 10.5l5-3v9l-5-3"/>',
            ),
            'nieuws' => array(
                'label'   => 'Nieuws',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<rect x="4" y="4" width="16" height="16" rx="1.5"/><path d="M8 8.5h8M8 12h8M8 15.5h5"/>',
            ),
            'winkel' => array(
                'label'   => 'Winkel',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path d="M5 8h14l-1 12H6z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>',
            ),
            'locatie' => array(
                'label'   => 'Locatie',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path d="M12 21s-6.5-6.2-6.5-11a6.5 6.5 0 0 1 13 0c0 4.8-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
            ),
            'muziek' => array(
                'label'   => 'Muziek',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path d="M9 18V5l11-2v13"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="16" r="2.5"/>',
            ),
            'info' => array(
                'label'   => 'Info',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><circle cx="12" cy="7.8" r="0.7" fill="currentColor" stroke="none"/>',
            ),
            'euro' => array(
                'label'   => 'Doneren',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path d="M17 6.5a6.5 6.5 0 1 0 0 11"/><path d="M5 10.5h8M5 13.5h8"/>',
            ),
            'hart' => array(
                'label'   => 'Hart',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.2a4.3 4.3 0 0 1 7.5 2.6C19.5 15.4 12 20 12 20z"/>',
            ),
            'link' => array(
                'label'   => 'Link',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
            ),
        );
    }
}
