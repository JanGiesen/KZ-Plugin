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
                $soort = isset( $l['soort'] ) && in_array( $l['soort'], array( 'social', 'knop', 'kop' ), true ) ? $l['soort'] : 'knop';
                $links[] = array(
                    'soort' => $soort,
                    'titel' => isset( $l['titel'] ) ? (string) $l['titel'] : '',
                    'url'   => isset( $l['url'] ) ? (string) $l['url'] : '',
                    'icoon' => isset( $l['icoon'] ) ? (string) $l['icoon'] : '',
                    'foto'  => isset( $l['foto'] ) ? (string) $l['foto'] : '',
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

    /**
     * Een foto-adres: alleen https://, anders ''. Een foto uit Supabase
     * Storage wordt de kopie op deze site zodra die er is (KZ_Spiegel), zodat
     * hij ook bij een storing van Supabase blijft staan. Ook KZ-Pagina tekent
     * zijn foto's hiermee.
     */
    public static function veilige_foto( $url ) {
        $url = trim( (string) $url );
        if ( ! preg_match( '#^https://\S+$#i', $url ) ) {
            return '';
        }
        return esc_url( KZ_Spiegel::url( $url ), array( 'https' ) );
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
            // Tussenkop: begint een groepje knoppen (deelt hun volgorde).
            if ( 'kop' === $l['soort'] ) {
                if ( '' !== trim( $l['titel'] ) ) {
                    $knoppen[] = '<li class="kzl__kop-rij"><h2 class="kzl__kop"><span>' . esc_html( $l['titel'] ) . '</span></h2></li>';
                }
                continue;
            }
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
                $foto_url  = isset( $l['foto'] ) ? self::veilige_foto( $l['foto'] ) : '';
                // De tekst staat al op de knop, dus de foto is decoratief (alt="").
                $foto      = '' !== $foto_url ? '<img class="kzl__knop-foto" src="' . $foto_url . '" alt="" loading="lazy" decoding="async">' : '';
                $klasse    = '' !== $foto ? 'kzl__knop kzl__knop--foto' : 'kzl__knop';
                $knoppen[] = '<li><a class="' . $klasse . '" href="' . $url . '">' . $foto . '<span class="kzl__knop-balk">' . $icoon . '<span class="kzl__knop-tekst"><span>' . esc_html( $l['titel'] ) . '</span></span></span></a></li>';
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
        // Alleen koppen zonder knoppen is geen inhoud.
        $heeft_knop = false;
        foreach ( $knoppen as $k ) {
            if ( false === strpos( $k, 'kzl__kop-rij' ) ) {
                $heeft_knop = true;
                break;
            }
        }
        if ( $knoppen && $heeft_knop ) {
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
            'krant' => array(
                'label'   => 'Krant',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path d="M4 5h13v13a2 2 0 0 0 2 2H6a2 2 0 0 1-2-2z"/><path d="M17 9h3v9a2 2 0 0 1-2 2"/><path d="M7 8.5h7M7 12h7M7 15.5h4"/>',
            ),
            'feest' => array(
                'label'   => 'Feest',
                'social'  => false,
                'viewBox' => '0 0 24 24',
                'svg'     => '<path d="M4 20l4.5-11 6.5 6.5z"/><path d="M12.5 7.5c.2-1.8 1.4-3 3.2-3"/><path d="M16.5 11.5c1.6-.6 3-.2 3.8 1"/><path d="M15 3.5v.01M20.5 7v.01M19.5 16.5v.01M10.5 3.5v.01M18 4l1 1"/>',
            ),
            'optocht' => array(
                'label'   => 'Optocht',
                'social'  => false,
                'viewBox' => '0 0 240 240',
                'svg'     => '<path fill="currentColor" stroke="none" fill-rule="evenodd" d="M47.6 121c-1.5-2.8-7-13.2-10-24q-.4-1.9.2-3 .5-1.2 3-1.7c3-.6 10.2-.4 25.8-.4 34 0 51.3-.2 61.2.4 10.4.6 13 2.2 17.9 5.6a37 37 0 0 1 13.7 16.4l1 2.4.5.8.3-.1 1-.7c2.6-1.9 2.2-5.2 1-8.6-2-6-6.9-12.3-7.5-13.3-13-18.6-15.6-34.3-12.7-46.3a36 36 0 0 1 34.3-26.9c9.8 0 19.2 4.2 24.3 13.3 1.5 2.6 3 4 4 5.4q1.6 1.7 2 3.8a12 12 0 0 1-.7 5.8q-.1.7.7 1.6 2 2.5 5.2 5.2c2.3 2 4.5 4.2 5.5 6.3q1.4 2.8 0 5.4-1.1 2.4-4.3 2.3a47 47 0 0 1-14.4-5.3c-7-3.3-14.8-7.2-20.8-7.6-3.2-.2-5.8.8-7 4.1q-1.4 4.6 1.3 9.7c2.8 5.7 8.3 12 14.4 18.9 16 18.2 36 41.6 26.3 74.4a43 43 0 0 1-7.1 16c2.3.3 5.4.1 6.5 0l.3-1.2q.2-1.3.6-2l.4-.7.4-.4.6-.4.7-.3h.8l.8.2q1.4.5 2.2 2.9c1 2.7 1 7.7.3 10.6q-.3 1.8-1 2.5l-.7.6-.6.3q-1.8.5-2.5 0l-.5-.2-.5-.3-.3-.6-.3-.7-.4-2.9-1.8-.4c-2.7-.2-6.4 0-9.1 0-1.6 0-2.5.7-3.6 1.7-2.4 2-5.5 5-13 7.3q-1.4.4-2 1-.4.5-.6 1.3l-1 3a23 23 0 0 1-3.6 5.6c3.5 0 10.6-.4 24.8 0q2.6.1 3.2 1.3l.4.5.2.6q.2 1.3 0 1.6v.6l-.3.5c-.3.5-1 1.1-3.1 1.4-4 .4-17 .4-58.4.4-88 0-105.7 0-109.6-.4-2-.2-2.1-.6-2.4-.9q-.9-1-1-1.8v-.8l.2-.8q.4-1.1 2.6-1.8c3.5-.8 12.1-.4 22-.5a14 14 0 0 1-4.6-7.4q-.4-1.1-1-1.9-.6-.5-2.2-.5h-6q-1 0-2-.6t-1.8-2.6c-.6-2-1-5.8-.4-13.4q.3-2 1.4-3a6 6 0 0 1 3.6-1.4h.6a95 95 0 0 1-24.5-60.3q0-2.8 1-3.7l.5-.3.5-.3.8-.2h.7q1.7.5 4.8 2.4c5.6 3.5 12.8 6 17.1 6.5zm35 30.7 1 .8c3.2 1.9 9 3.9 12.4 5l-.4-1.5q-.4-2-1-2.6t-2.7-1.6q-2.7-1.6-4-3.4l-.6-.6h-.1a12 12 0 0 1-4.3 3.8zm-9.2 54.5a14 14 0 0 0 9.4-6.3c4-6.8.5-16.6-8-19.3-7.4-2.4-13 2-15.4 7.8-1.6 3.8-1.6 8.4.3 12 2 3.7 6.5 6.2 13.7 5.8m95.5.4c5.2-2 8.8-7 8.8-12.7a14 14 0 0 0-14-13.8 14 14 0 0 0-14.1 13.8c0 5.4 3.2 10.1 7.9 12.4q3.5 2.3 8.7 1.2 1.5-.3 2.7-.9m-41.8-80.1v.3l.3 1q3.2 5.5 6.7 5.7c3.4.4 6.4-2.4 7.8-5q.6-1 .8-1.9v-.6c-4-1.9-3.6-2.4-7.2-5.1l-.2.1-1 1.1a11 11 0 0 1-4 3l-2.3 1q-.5.1-.9.4m1.9-27.3a9 9 0 0 0 7.6 7c.6-.1 2.4-.3 4-1q1-.3 1.7-1l-1.2-.9a33 33 0 0 0-11.2-4.2zm18.6 9.4c-3 2.2-2.9 2.4-6.4 4h-.2l-1.5.7q-.3.1-.4.4v.1l.3.6 2 2.7c1.4 1.8 4.4 2.7 7 2.6q2.5 0 3.8-1.4 1-1.5-1-5a28 28 0 0 0-3.6-4.7M126 153.1c0 .6.2 2.4.8 3.5 1 1.8 3 3.2 6.5 2.5q4.2-.5 10.9-5.4l.1-.1c-4-.1-6.4-2.3-8.3-4.1l-1.4-1.3-.5-.3-.5.5-2.1 2-.3.2c-2.6 1.6-2.8 1.5-5.2 2.5m-23.9-45.8-.2.3c-3.5 4.1-6 4.3-7.7 5l-.3.2q-1.3.3-1.6.8l.1 1q.4.8 1.3 1.7c3 3 9.7 5.6 13 0q1.1-1.6 1.4-2.7v-.9q0-.3-.6-.6l-1.8-1.3q-1.8-1.4-2.8-2.6zm48.4 19.1v.4q0 .8.7 1.7 1.4 2.4 3.6 3.9l.5.2.2-1.4c.1-2.6-.1-6-.1-6.5l-.1-.4c-2.3 1.2-2.4 1-4.8 2.1M59 98.6l.1.4q2 6.1 5.4 7c3.4 1 7.1-1.2 9.3-3.7q1-1.3 1.4-2.5v-.9l-10.6-.2a31 31 0 0 1-5.6 0m46.7.4c.2.8.8 3 2.5 5 1.5 1.7 4.4 2 5.2 2.2.8-.1 2.8-.4 4.7-1.5 1.9-1 3.7-2.8 3.7-6h-.4a121 121 0 0 0-15.7.3m-23.4 0 .1 1q.5 1.4 1.6 2.7c2.1 2.4 5.7 4.4 9 3.3q3.2-.9 5.1-6.6l-.2-.7H84.1q-1 0-1.7.3m-2 27.3v.3l.2 1.3c.6 2 2.4 4.3 5.1 5q2.7 1 6.6-1 2.5-2.4 3.2-3.9.5-.8.3-1.4t-.9-.8q-1-.7-2-1-2-1.2-3.2-2.8l-1-1.2-.2.3c-2.8 3.6-7.2 5-8 5.2m26.2 13.1c-4-1.9-3.7-2.4-7-5.1l-1 1.2c-2.5 2.8-2.7 2.3-6.2 4q-1 .3-1.3 1-.2.7.4 1.5c1.2 2.4 4.8 5 7.9 4.6 4-.4 6.3-3 7-5.3q.4-.8.3-1.4zm18.9-32.2-1 1.2a14 14 0 0 1-6.1 4h-.2q-1.2.4-2 1-.3 0-.5.4l.2.7q.7 1.2 2.1 2.7c4.2 4.6 9.5 2.1 12.2-1q1.4-1.5 1.7-3 .1-.7-.4-1c-3.4-2-3.3-2.4-6-5m63-64.7q0 .3.4.5c.9 1.3 1.1 2.9 1.5 5.6l1-1.2q2.3-2.3 3.8-2.3c1-.1 2 .2 4 1q.8.3 1.3.2.3 0 .4-.2c.4-.9-.5-2-2.3-2-4.8 0-7.2-.3-8.9-1zM53.7 184h-.9l-5.7.1-.2.5q-.4 1.4-.5 3.1t.4 3l.3.6 2.9.2 1-.1.3-.7.5-1.5q.5-2.8 2-5.2m84.2-44v.3l.4 1.3q3.7 5.3 7 5.3c3.4.3 6.3-2.4 7.6-5q.6-.9.7-1.8v-.7c-4-2-3.9-2.4-7-5.1l-.9.8a15 15 0 0 1-5.6 4l-1.8.7zm-87-27.5c1.4 2.6 2.6 4.4 4.5 5.6q2.9 1.7 9 1.8l-3-7q-.3-.2-.6-.1h-1.2q-1.6.4-4.2.3-2.5 0-4.5-.6m-5.5-13.6c1.6 4.3 4.7 6.5 7.3 7.2l2 .2c-.6-1-1.9-3-2.3-5.4q-.2-1.7-.8-2.3l-.4-.1h-1l-2.6.2zm58 27.5v.5l.9 2c1.5 2.4 4.7 5.1 8 4.5q3.6-.3 6.7-6.2v-.8q0-.3-.2-.4l-.6-.3-2.3-1.1c-1.3-.7-2.3-2-3.2-3l-.7-.8c-3.6 3.9-3.8 3.6-8.5 5.6m13.4 29.2.7-1.7q.3-.5.3-.8l-2.9-1.4c-1.3-.6-2.6-2-3.8-3.3l-.8-.7c-2.7 2.4-2.4 3.5-8.2 5.5q-.2 0-.1.4 0 .7.5 1.6c1.3 2.4 4.3 5 7.7 5q3.4.1 6.6-4.6m-48.2-16q2.8 3.2 5.2 5.1 2 1.8 4 1.7a4 4 0 0 0 2.3-1.2q1.5-1.4 3-4.2l.2-1.3-.2-.2-1.3-.7c-2.2-1-3.5-2.5-4.6-3.7l-.8-.7-1 .9c-1.6 2-3.7 3-5.5 3.8zM85.1 208l7.5-.2c33.8 0 49.3.7 55.6.4h1c-2-2.4-2.7-2.6-4.6-7.9l-.7-2.2-5.1-.2c-13-.3-42.9 0-46.2 0q-1.2.1-1.7.5-1 .8-1.2 2.2c-.8 2.2-1.7 5-4.6 7.4m109-153 1.2.8 12.4 5.9-4-4.1-.2-.1q-2.4-3-4-4.2-1.2-1-2-.8-1.4.3-3 2zM28 121.8v1.1c.3 5.4 3 15.5 3.5 17 3.5 10.7 6.1 16 8 18.5 1.5 1.9 2.4 2 3 2 26.7 0 37.5.1 42.6.2l-4.6-2.4C67 150.6 59 138.6 54 132l-3.3-3.7-.3-.2c-16.6-1.3-17-3.7-22.5-6.3m30.8 5 .2.4q1 1.8 4 5.5c.5.1 2.4.8 4.2.1q2.2-.7 4.6-4.3l.5-1.3c-.3 0-1.2-.3-1.9-.3-7.3 0-10.2-.2-11.6-.2m-13.2 40.5 1.3 1.8c3.2 3.7 8.6 8.3 8.6 8.3q.9.8 1 2.1c5.7-6 14.8-7.8 21.8-5a19 19 0 0 1 12 13.3q.3 1.5.8 2.5l.3.6.1.2h52l.3-.1.2-.6.7-2.5a20 20 0 0 1 27.4-11.7 18 18 0 0 1 10 11l.9 2.1q.1.5.4.7.5.2 1.6-.1c6-2 14.9-8 20.4-22.2 11.5-30-4.2-50-19.1-67.4-10.5-12.2-20.6-23.4-22-35.6v-.3q.6-6.8 3.8-9.5c2.9-2.9 7-3.5 10.6-3.7h3.7l1.2-.3q.3-2.9-.5-3.9-.7-.8-1.3-1-.6 0-1-.3a7 7 0 0 1-3.8-7.5 6 6 0 0 1 2.4-4.2c6-4 10.2 1.1 11.3 2a9 9 0 0 0 4.2 1.4l-.3-.6c-4-5.7-10.5-8.4-17.4-8.4-9.7.1-20.2 5.7-25.2 16.1a38 38 0 0 0-1 27.5c2.7 9 13.3 21.8 17.9 32.3 3.5 8.1 3.6 15-3.7 18.6l-2 1-.5.6-.2.8v2.1c.1 2.4.7 16.5-10.1 28-8 8.4-16.3 11.3-32.4 12-15.3.8-37.8-.3-74.4 0m158.7-4c-.6 1.4-5 11.2-9.7 17q-2 2.5-4 3.7a6 6 0 0 1-3.1 1q-2.7-.2-3.2-1.2l-.2-.4-.1-.5v-.8q0-.7.7-1.5c1.9-2.7 9.2-8.7 12.8-18.8.9-2.4 1-3.9 1.3-4.8l.5-1.3.4-.4.4-.3a4 4 0 0 1 2.5-.5h.5l.5.2.4.2.4.3q.7.8.9 2.1a19 19 0 0 1-1 6M117.6 177c11 0 20-.3 23 .7q1 .3 1.6.8l.4.5.4.7v1.5q-.3.7-1 1.6c-.4.4-.9.8-3 1-3 .2-11.6 0-37.4.1a69 69 0 0 1-8.6-.6l-.7-.5a4 4 0 0 1-1-1.6l-.2-.7.2-.8.3-.7.5-.5q.5-.5 1.6-.8c3.3-1 12.7-.7 23.9-.7m82.5-30.6c0-.3.2-1.9.6-3.2q.4-1.2 1-1.9l.4-.4.6-.3.5-.2h1l.4.2q.5.1 1.1.7.8.6 1.2 2.6a12 12 0 0 1-.6 6.6q-.6 1-1.1 1.3l-.6.4-.7.1h-.8l-.6-.3-.5-.4-.5-.7a15 15 0 0 1-1.4-4.5m-85.6-6.5.1.6.9 1.8c1.6 2.5 4.7 5.3 8.1 4.6q3.5-.5 6.5-6.6l-.2-.9-.7-.4-1.5-.7a14 14 0 0 1-3.7-3l-1-1c-3.2 3.8-3.8 3.5-8.5 5.6m-29.4-27.5-3.1-1.9q-1-.7-2.3-2.2l-1-.9c-3.2 3-3.3 3.3-7.5 5.2l-.2.1-1.6.6q-.5.3-.5.5v.2l.3.5 2 2.6q2.2 2.4 4.6 2.7c3.7.4 7.1-2.6 8.6-5.2q.6-1 .7-1.7z"/>',
            ),
            'steek' => array(
                'label'   => 'Steek',
                'social'  => false,
                'viewBox' => '0 0 240 240',
                'svg'     => '<path fill="currentColor" fill-rule="evenodd" d="M23.7 101.7c.8-2.2 2-2.2 1-6.5-1-4.8 3.5-5.7 4-7.8C34 69.5 38.6 67 59 60.9c44.8-13.7 86.4 17.7 88.2 22.3 2 4.7-3.9 27 12.4 26 17.6-1-9.3-47 8.6-58 11-6.8 26.7 16.3 33.8 22.2 4.2 3.5 2.2 5.1 5.9 9.2 5.8 6.2 5.5 6.9 13.2 10.3 3 1.3 2.5 4.6-1 4.8-4.3.2-.8 2.3-.7 4 .3 10-18.8-12.5-25.8-14.5-6.7-2-15-13.7-14-8.2 6.4 30.3 17 38.5 18.8 53 .6 4.3-.2 5.4 1.5 5.1 4.6-1 23.5-24.3 22.6-10.5-1.2 19-22 39.4-27.2 59.5-2 7.5-5.4 2.8-60.1-1-1.5 0-37.5-2.3-78.4 2.6-10.3 1.2-3.8-19.5-23.3-30.6-1-.4-9-5-8.6-7.8.6-4.3 15.5-7 25.4-6 5.3.6 7.6 2.7 8-2.6.4-9.2-6.4-8.6-5.3-15.2 2.4-14.8 1.2-15.7-5.6-22.8-6.6-7 13.7-19.8-4.3-22.9-6.3-1-8-2.1-10.4 4.7-1.6 4.5 0 4.4 1 7.3 1.5 4-1.7 5.7-1.4 7.7 2.1 14.9 9.2 24.7-3.2 26.8-3 .5-14-1.1-11.6-8.4 1-3.5 5.8-15 6.3-16.2m80.7-12-1.8-.3c-.1-.3-3.7-5-2.8 1.6.5 3.4 3.5 3.2 10.8 4.4-.8-1.4-.6-1.6-2.1-2.4-.2 0-1.8-1.2-3.4-2.7zm-9.2 3.9c-3.1-5.1-3.4-5.2-3-7.2.8-2.2-.6-2.2-2.8-1.2-2.7 1.3-3 5.1-3.2 5.7-1.5 1-1.7.6-3.3.3-3.2-2.4-3.5-1.2-5-1.5-3.2-.7 0-1.6 2-4.4 0 0 .3-1-1-.8-4.6.4-3.5 10.2-8 6-1.7-1.6-1-2.1-2.6-3.6-.2-.3-.6 0-.8-.3.5 6.5-.4 7.4 1.7 8 1.3.2 3.3-1 17.1-1 .8 0 2.5-.2 9 0m13.3 12.4-1 3.3c4.3-.4 4.3-.4 8.5 0-.7-1 .3-2.6-4.2-3h-1zm102.8 43.3.3-.5q-.4.3-.2.5Zm-85.9-55.2c-1 1.5-1.2 1-3 1.4-2 .7-2 .7-4-.4-2.5-1.4-1.6-5.4-3.3-5.5-2.9-.4-3.2 4.8-3.4 5.8 1.1.2 4.2.7 14.8 3.5 4 1 4.1 1.3 4.5.7 2.5-4.5-.9-8-1.3-8.5-8.7-3.8-16.4-8.9-43.4-12.6C66.4 75.8 66 78.5 66 80c0 2.2-.1 2.3 1.5 4 2.1.5 2.1.5 3.2 2.4 3 5 2.5-3.2 8.1-4.5 3.2-.7 3.2.3 3.6 3.6 0 2.1.2 2.1 1.8 3.2.3-.6 2.4-6 6.4-6.5 2.2-.2 6 .3 5 3.1-1 3-1 3.3 1.1 5.4 4.3-11.4 13.2-5 12.5-3.2q-.2.7-.6 1.2c-.7 1.6-.1 1.6 1 2.5 1.8-2.6 1.1-3.7 4.2-4 4.2-.4 4.1.7 5.2 4.9s2.7-1.8 6.8-.6c1.7.6 1.6.6 3 1.5.2 1.1 2.6 4.1-2.3 4.4l.9-3c-.2-.3-.3 0-2-.3m7.5 52 1 .9c3.4-1.1 3.3-.3 6.5 1 1.5.7 5-5.6 10.5 1.4 1.4 1.9 1 2.4 3.3 2.4 1.7 0 1.7.9 2.3 1.8 6.9 1 6.9.2 13.7 1.8 2.8.8 3 .8 4.2-.4 1.4-1.1 1.8-.9 2.4.6.3.7 1.2 2.8 8 3 4.7 0 6.3-6.5 11.7-4.6.3.1 4.6 1.6 3.4-.3-.5-1-1.6-1.4-2-2.2-.3-.2-.6-2.2 2.9-.4 2.1-1.7 2.6-1.3 3.4-4 0-1.9.2-2 2-2.2l1.7-1v1.9c0 .4-.9 2.3-1.6 4.4l1.6 1c.2-.4 2.5-4.6 3.9-3l3-6.2c.5-1.1 3.9-9.2 4.6-13.3 1-5.8-4.5-.3-4.8 0-34 33.6-56.2 15.4-83.8 5.8-21.7-7.6-35.8-3.8-35.7-2.5 0 .7 14.5 6.6 13 14-2.7 15.5-45.6 1.6-56.7.5-13.3-1.4-27.8 1.9-21 5.8 16.7 9.5 15.2 11.5 21.5 24.9.3.7 1 2.2 9 1 3.8-.7-5.5-7-9-14-.8-1.4-4.3-16.2 19.8-5.3 3.7 1 3.8 1.6 4.6.8 1.5-1.5 2.7-1.7 2.6.5 0 2.2 0 2.7 2.2 3.2.6-5.4 1.9-7.1 6.2-4.1 2 1.5 2.2-.2 4.6.4 3.1-.4 3-1.8 6.1-1.3.4 0 10.2 3.8 13.5-9.2 1-4-5.7-13.7 3.5-14 7.6-.4 13 10.7 13.7 11.9l1-1c.8-1 1.4-2.5 3.2 0m77.5-.2c0-.5-1-.7-1-1.1 0-3 1.8-1.9 1.9-1.7 1.7 1-.8 2.7-.9 2.8m-76.7-55v7.8c-1.3 6 1.7 26.5-1.8 26.6-12.6.3-20.4-7.6-49.8 6.5-7.5 3.7-11.2-17.5-12.5-18.6-1-.8-9.5 7.1-5.2 14 2.1 3.5 7.9-.7 4-1q-1 .3-2 .2c-1.5-.3.2-4.5 2.9-2.8 2.4 1.5 6.1 12.5 6.2 12.7 1.1 13.5 12 9.7 10 5.5-1.8-3.7-5.8-1.1-3.4 0s-.2 2.8-2.1 1c-2.8-2.8 3.2-10.8 8.5 0 11-2.5 8.5 4.6 8.2 6.7l.2.8c.9-.2 8.4-1.7 8.5-5.3 0-7.1-20.9-12-14.6-14.7 32.7-15.2 70.1 27 102.3 11 11.6-5.8-11.5-30.3-16.5-64.5-1.6-10.7 4.9-1.5 11.7 3.9 11.8 9.3 16.4-.8 11.1-5.7-13.1-12.3-25.4-33.3-34-15.6-2.2 14.5-1 15.7 2.7 32.4 1.7 7.3 1.8 22.3-11.7 20.2-15.2-2.4-11.5-22-11.5-23.3.3-4.2 1-6-17.3-16-52-28.5-90.1 1-90.4 1.8-1.3 2.6.8 1.5 3.6 2 20.1 3.1 11.4 11.6 8.5 20.9-1.8 5.5 5.7 3.4 7.4 16.2 1.3 9.2-4.3 10.5 1.7 17.7 4.4 5.4 1 13 3.5 14 12.5 4.1 26.5 5 28.7 5.2 0-.4 0-5.2 1.8-4 .4.2 1 3 2.3 1.5.3-.3 2.9-5-5.2-2.9-1.9 1-6.3 4.1-11.6 1-1.5-1-3.6-4-4.3-4 0 0-3.1 2.3-6.3.7-7.4-3.4 1-12.2 3.3-6.5 0 .1 1.4 5.6-2.3 1.1-2.3-2.8-2 4.7 2.2 3.6 3-.8 5.9-9-4.3-10.3-3.9-.4-7-6.8-5-11.6 2.5-5.6-3.5-6.7.4-11.5 1.8-.7 1.8-1 3.5 0 1.2.6 1.1-.3 2.2-.5l-1-1c-3.5-2.5-11.2-3.1-8.1-9.6 2.5-5 5.7-.7 6-.2 0 .2-1.3 6.3-2.3.2-.3.1-3.2 1.4-1.5 3.9 1.9 3 5.3-.5 5.6-.9.7-3.3.3-3.3-1-6.4-.5 0-7.5-1-6.2-5.5.6-2.1 4-2.1 4.2-2.1l1.3-1.1c-1-1.9-.8-1.9-2.5-3-4.6-3.9-5.4-2.4-9.9-6.6.6-4 1-5.4 4.9-3.7 6.1 2.6 6.3-5 9.5-4.3l2.1.8c4.8-.7 5 2.3 9.5.5 3.9-4 7 1.4 10.6-1 1.6-2.2 2-1.5 4.5-.5 4.7 2.2 5.3 4.1 6 3.6 1.1-.8.6-3.3 4.5-1.3 1.6 1 1.6 1 3 2.5 2.6 1 3-.6 5.5.7 3.5 2.7 4 1.4 6.3 2.2l1.2 1c2.8 3 3.2 2 5 2 .4 0 .1.4 5.3 2.5 4.1.6 11.1 9.2 10.8 11-.4 2.2-1.4 2-1 4.2 3.3 9.4-1.5 14.2.7 14.5 4 .6 6.3.4 4.6 4.3-2.3 5.4-.8 5.7-.4 5.5.8 0 1.3-.7 2-.8l2.3-.9c10.2-1.5 3 6.5 3.6 7.6.8 1.2 6.7-6.3 9.6-2.2 0 .1-1.7 10.6 3.7 9.6.1 0 4.2-.8 1.3-1.8-8-2.6-.1-12.5 6.1-5.2l1 1.2c2-2.5 1.6-2.6 2.4-5.6 0 0 9.7-8.1 3-4.9-.5.3-5.6 1.9-2.2-8.1l.3-2.2c-.8-3.5-4.3-1.7-4-5.4.3-5.2 3.2-7-1.3-9-2.5-1 1-8.9 0-9.4-.7-.4-1.4-.1-2.1-.3-1.7-.7-.7-1.3 0-5.1l.2-1.2c-.6-3.2-1-3.2-.2-6.4-1.7-2.2-2.4-1.7-2.8-4.4.7-2.2.7-2 .4-4.2.4-2.2.4-2.1 2-3.7 2-1.1 2-1.2 4.6-1 2.2.1 2.5-.4 4 1.4 3 3.7 5.3 2.8 4.8 7.6.8.7.6.8 1.6 1.4 2.5 1.4-.3 4.3-1.8 1.7-.4-.7-.6-.4-1.3-.8l-.4.2q0 .2-.4.2l-.3-.1c-2 .4-2.5-.6-5.3.2-4.6 1-1 3.9-2.4 9-1 3 2.1 2.3 2.1 5.4 0 1.6-.4 1.5-.4 3.1 0 1 2.9 2.2 2.1 11-.2 2.8 3.6 1.8 3.2 9.5 1.3 2.2 2.1 3-.2 3.9-1.3.6-2 3.2 0 3 7.4-.8 3 6.3 2.5 6.9-2.3 2.5-.4 3.5-1.7 4.8-1 1.1-1.8.3-4.2 4.4-2.1 9.4 4.5 6.7 4.5 6.6 0-.6-4.5-7.7 1-6.6 7.4 1.5-.6 15.7-7 7.3-.4-.5-3.6-13.7-6.1-9.4-.2.3.3.8 0 1 5.3 11-10.5 8.4-9.5 0s-4.7 1.8-7 1c-2.7-1.1-1.1-2.3-3.1-5.7l-1.5-1.5c-1 0-1 0-2.1.8-3.2 2.9-4.3-5.7-4.4-6.2-9-7.6-1.4-10.4-2.8-13.2-1-1.6-2 .2-3.2-1.1-.3-.4-.1-.3 0-3l2.1-1.3c-2.6-5.9 1-6.1.7-7.5-.2-.8-.5-.8-4.2 1m0 0c-.1-.6.9-.9.8-1.4-.3-1.1-5.8-6.5-6.3-7-2.9-2-3.2-1.8-3.5-1.3-2.3 3.6-2.6-.6-3.8-.7-1 0-.6.7-1.7.7-1.5 0-1.3-.5-3.3-2.1-2.3-2.4-1.9-3.3-5.3-3.4-1.3 1.5-1.6 1.4-3 0-.5-.4-.5-.3-1.1 0-1.2.7-1.2.2-2.9-.2l-2.4-1.3c-5.3-1.3-4.3-3.6-9.6-5 2 6-2.5 2.5-4.4 1.5-1.5.6-1.6 0-2.7 1.1-.7.6-.7.3-3.3.9-2.4-.3-2.4-.2-4.6-.8-5.2-1.8-5-1.7-7-1.6-1 4.3-3.7 0-7.4 2.5-2.6 1.7-2.7 6.7 1.5 5v-1.1c1.1-2.7 39.5-3.8 70 14ZM50.3 74.4c-4.6-1.5-1.2-3-1-3 1.8 0 1 1 1 3m65.7 70.7c-2.6 1.4-1.5 2.2-2.3 5.2-.8 2.8-1 2.7-.9 5.5 0 1.5-10.6 4.8-5.3 8.7 1.8 1.4 4.5 0 2-2s3-3 3.3 1c.4 3.4-3.8 5.5-7 2.7-1.1-1-8.5-8.1-11.2-4-1.6 2.5.4 3.2 1.3 3.6 1.5.7 1.8-4.3 3.2-1.4.3.6 1.2 7.2-7.6 1.4l-2.4-1.9-.6-.5-1 .2-1.2.9c-.6 1.7-.7 1.7-.6 1.8.4.6 1.4.6 2 1.1 3.4 2.2 7.3 4 10.4 6.6 1.5.9 3.8 2.2 21.2 1.5 3.8 0-8.8-14.8-3.2-22.7 2.8-4 7.6-3.3 9.5-2.3 3.1 1.6 3.3 2 3.6 1.5 0-.2-4.6-8.4-7.1-11-3-3-3-3.1-7.1-3.7l.7 1.7c.8.8 4.8 2.2.3 6.1m9.2 33.7c-30-.5-60.7.2-70.7 2.8-.4.1-3.2 3.7 3.1 3 42.4-4.3 67.8-4.2 115.5.9 13 1.5 20.5 3.5 19.3-1.5-.5-2.6-32.8-4.7-66.4-5.2l-4.9-11c-8.3-18.4 4.8-12.7 5.6-12.2C139 163 149.4 181 146.3 173c-1.8-4.6-15.2-16.7-16.7-18-.1 0-10.4-6.7-12.4.8-1.3 4.8 4.4 16.2 5 17.4zM28.1 106c-2 7.6-1.8 7.6-2.9 15.2-.4 2.6 3.4 3.6 3.5 0 .2-15-.5-15-.6-15.2m167.4 62c-.6 1.3-1.4 1.8 0 2l1.9-.2 1-2.2c0-2.1.8-4.3.6-6.5-.1-1-2.9-1.5-3-2.3-1.5-5.9-3.4-2.1-3.5-1.8s2.6 5-1.3 5.4c-1 .1-1.8-.3-1.9-.4-3.9-2.6-4.6.4-11.8-2-8.8-3-8.8-2.5-18-3.8v1.8c-.4 4.3-2.2 4.7-5.2 2l-.4-.7c-6.4-12.5-8.6-10.8-10.5-10 .3.1 0 .7.3.9.5.3 1.2.1 1.9.4 4.7 2.2-1.6 8.8-4.9 2.8-2.8-4.8-4.9-6.9-7.4-1.9-2.3 4.7 5.4 6.5 17 23.6 1.8 2.4 7.4 2.4 9.3 1.3-9.9-20.6 17-23.3 32 1.7 1.4 1.4 1.5 3 2.6 1.3l3-6.4c-4.9-1.5-6.5-2.8-3.2-6.8 4.6-2 1.6 1.6 1.5 1.9m-42.4-8.2c-.3 3-1 3-1.2 3.2-1.8.3-3.1-2.6 1-3.2Zm-76.5-38.7c0 .6-1 5.2 2.3 7.7 2.6 2 10.3-4.2 17-5.2 17-2.6 17-2.5 33.9-1.2 1.9 0 1-1 1.4-4.5h-2.6c-1.6.6-2.3 1.3-2.5-.4-.7-3.7-2.2.3-2.4.4-2.4 4-4.2 2.2-5.6-2.1-.3-1.1-2.5-.1-3.3 1-3.7 5.5-5 1.7-11.4 1-1.9-.3-.3 4.9-6.5 3.4-3-.8-6-8.5-9-1l-1 3c-.7 1.6-5.3-.8-5.7-.7-1.6.3-1.6.3-3.1-.6zm-14.8 39.2c-.2 0-4-.8-4.2 1 0 2.3 3.4 6.7 3.8 7.2 8.3 10.3 9.8 8.7 12 7.6-.4-5.1-3.2-3.2-6.3-7l-2.2-2c-2.4-.7-4.7-1.5-3.5-5zm77.7 15.6c2 .3 2 .3 4 .3-.1-.9-.5-3.5-6.6-9.1-1.3-1-1 .2-2.1 1.8-1 1-.8 1.1-1.8 2.2l2.2.6c1.8 1.8 1.7 1.8 2.9 4zm-72-13c-.3 2.3 0 2.6 2.1 3.3 1.7.7 1.7.5 3 1.5 1 1.1 1 1.9 2 1.1 4-2.5-6.4-5.7-7.1-6m11.6 6.8c-3.3-3-3.2-.1-3.2.1.2 2.5 1.2 1 3.3 0Zm-15-7-.7 1.7 1.9.2v-2.4zm111.2 6.1c-.2.5-.8.7-1 1.1l1 2.2 2-.2 1.1-1zm4.3.5c0-.5 1.4 0 1.4-.6.2-3-24-12.2-21.7 1.2 0 .4.6 3.7 1.4 4.1.3.2.6-1 1-.8-4.7-17.4 16.6-4.7 17.9-4m-9.7 5.6c-5.3 0-5.6-4.3-2.2-6.5 1.5-.7 1.4-.7 2.5-1.9-2.8-.4-2.8-.6-4.4-.3-4.8.9-1 9-.6 9.8 3.9 3.4 6.3 4.7 7.4-.7l.4-1 .2-1.1c-.4-2.2-.5-2.1-2-3.5-.2 2.8.2 3-1.3 5.2M122.7 105l-.3 1-.8 2.2c.5.2 0 2 8.2 2.2 1 0 3.8-6.7-1-8.6-2.7-.9-2.6-.9-5.3-1.5A155.7 159.2 0 0 0 70.3 97c-.5 0-2.2.5 0 6.9 3.9-3 5.6-4.7 7.4 0 1.7 4.5 5.8 5.7 4.7 2-1.9-7.2 10-9.4 9.2-5.4-1 6 1.6 5.2 1.8 4.9 1.1-1.6-.4-2 .6-3.6 2.1-3.2 7.8-4.1 8.5-.2 1.3 7.9 5 .4 6.1 0 2.5-1.5 6.1-1.3 6.7 1 1 4.7 3.8 5.3 5.5 1.4.2-.6.2-.5 1.5-1.5.5 0 7.6-.3 5.5 4.5-1 2-1.5.9-3.5 0l-.6-1.4c-.2-.3-.8-.2-1-.6m-35.2 0c-1.8.8-2.1 1-1.8 3 .3 2.5-6.7 4-9.8-2.2-2.2-3.8-3.5.6-4.2 1.2-.6 12 7 1.7 34.8 2.2-.4-.4-.1-1.2-.6-1.5-1.4-1.2-4 1.5-5.8-5 0-.5-3.9-4-4.1 4.3 0 2.2-1 1-3.3 1-3.2 0-2.1-2.8-5.3-3Zm3.2 68c-1 0-.3 1.2-1.4 1.4-.2 0 0-.3-2.1-1.1s-9.9 0-10.4-.4c-2.7-2.2-1.3 1.7-1 3.5 10 .9 15.7-1 17-1.5zm39-4.8c5.6-1.3 5.3-4.1.7-7.6-14.3-11.3-8.6 4.9-6.9 8 3 5.2 2.8 7.4 7.5 6.6l2-.6c-.6-1.5-.8-1.4-.4-3zm-.8-4c.5 1.8.4 1.8-.2 3.4-.3 0-4.1-.8.2-3.3m48.9 13c.2.4-.3.7-.2 1.2.2.2 10.4 1.8 7.8-.6-3.7-3.6-7.3-3.8-8-3.9h-1l-2.1.6c0 1.5-.4 2.1 1 2.1s1.1.2 2.5.7m-52-65c-4.5-1-9.9-.5-19.3-1-2.7.1-32.7 2.8-32.9 5.6-.3 5.8 5.5-3.3 8 .3 2.7 4 3.2 3.9 3.4 3.5s1.3-6 6.6-6.2c4.3-.4 6.6 7.9 8 4 .2-.2-.7-4.2 6.6-5.8 1.6 1.1 1.3 1.3 2.3 3.2 2.4 4.5 3.7-3.3 9.3-2.7 3 .2 2.1 1.5 3.5 4l2.3-3.5c.6-.2 1.2 0 1.6-.4.4-.2.2-.8.6-1.1Z"/>',
            ),
            'zwaan' => array(
                'label'   => 'Zwaan',
                'social'  => false,
                'viewBox' => '0 0 240 240',
                'svg'     => '<path fill="currentColor" stroke="none" fill-rule="evenodd" d="M45.6 147.7c-8.7-11.8-13-21.9-13-25.8v-1.4l.3-1 .6-1 1-.7 1.1-.4h1q1 0 2.4.9a13 13 0 0 1 2.6 2.5q-5.5-8.5-6.7-20-.2-3.2.9-4l.9-.9 1.1-.4h1.3l1.2.4q1.5.6 4.2 2.8c3.5 2.6 8.9 6.8 16.3 9.8 11 4.5 16.8 5.8 20.6 5.5 3.7-.2 5.5-1.8 8.4-2.8 21-7 37-5.9 49.2-1.3 18.3 7 28 22 33 30.6l1.8 3.2q.3-.9.3-1.9c0-6.4-3.9-18.5-18-37.4-11.9-15.9-21.3-27.8-18.8-51.1.4-3.9 5.6-24.2 25.6-29.6 18.1-4.8 38 4.7 40 23.5.8 9 7.3 14.8 10.4 19.3q3 4 2.1 7.3-.4 2.4-3.6 4.6l-.7.4-.9.2h-2.1q-2-.4-4.8-2.2c-6-3.5-15.1-11-24-12.9-6.3-1.2-6.3-1.1-11.8-4.2q-1-.3-1.6 1.2-1.2 2.1-.3 6.5.9 5 5.8 12.2c7 10 23.3 25.1 34.4 43 11.6 18.8 17.7 40.5 3.7 62.6-10.9 17.1-31.6 26.7-55 30.4-34.5 5.4-74.5-2-95-15.3-7-4.5-10.4-8.4-13-11.2q-1.9-2.2-3.8-3.1c-1.3-.6-8-3.2-14.2-7.7-4.3-3-8.4-7-10.4-11.7q-.7-1.7-.6-2.7l.2-1 .5-1.2 1-.9 1-.5q-3-2.7-6.5-7.3-2.2-3-2.6-4.6l-.2-.8v-1l.3-1.4.4-.7.5-.6.9-.6.8-.3q1.5-.5 3.7 0c3.3.6 8 2.6 9.7 3a78 78 0 0 0 18.5 1.7M35.1 172c9.4 6.7 18.4 6.8 22.2 7.6q1.8.3 2.6 1l1 .9.3.7.2.7.1 1.6-.2.8-.4.8-.8 1-.8.6-.7.3h-.1l1 1c18.2 14.9 54.9 21.5 86.9 18 21-2.3 40.2-8.9 50.5-20.9 16.2-18.9 12.6-37.9 3.1-54.7-9.9-17.6-26.1-32.9-34.3-43.5-1-1.3-7.2-9.4-9.4-18-1.6-6.2-1-12.6 4.3-17.2l-.4-1-.9-2c-1.1-2.7-1.2-5.1.8-6.8l1-.8 1-.3 1-.1.9.1.8.4.6.4q.6.5 1 1.4c1.5 2.1 3 7.7 10 9.8l3.9 1q.9.3 1.5.2l.1-.4v-2q.1-2.2-.4-3-.3-.7-1.1-1.2l-2.5-1.8c-3.7-2.8-3.6-5.9-2.6-7.8l.6-.8.6-.6q.7-.6 1.6-.9c2.1-.8 5.3-.5 8.2 2.1q3 2.9 5 3.4l1 .1-1.5-2.2q-3.1-3.5-8.6-5.8c-9.1-3.7-21.9-3.5-30 7.4-7.6 10-7.2 21-4.1 30.9 5 15.5 16.6 28 17.1 28.8 16.3 22.6 19.2 37 17.7 44.7-1.4 6.7-6 9.2-9.8 8.6a10 10 0 0 1-6.5-4 25 25 0 0 1-3-5.5c-2.8-5.1-20.7-37-59.5-28l-3.4.8c9 1.2 21.7 3.4 32.4 8.5 9.2 4.4 16.8 11 19.6 20.6 10 34.3-28.5 49.6-56.2 39.5-24.3-8.9-31.6-17.1-44-30h-1q-1.8-.1-4.4.1a61 61 0 0 1-19-.8c4.3 3.8 7 3.8 15.4 7.5l1.3.8 1 1.1.6 1.5v1.7c-.3 1.6-1.6 3.8-5.8 4zm63.3-3.1-1.5-1.4c-1.3-1.1-2.2-2-5.3-1.7q-10.4.5-16.5-.7c5.3 3.8 7.5 4 8.6 4.2 5 .3 10.7 0 14.7-.4m92.5-118q0 2.4-.5 7.3l1.3.9 8.7 5.3a49 49 0 0 1-5.9-9.9l-.8-3zM55.9 146l1.7 2c7.6 5 8 4.4 16.7 7.4l4.5 1-1.7-1.9c-1-1-2-2.2-3-2.4q-12.1-2.5-18.2-6.2M47.4 112a46 46 0 0 0 15 17.8c9 6.2 9.3 5.8 17.2 13.3l2.5.4c2.8.2 6.2.2 8 .8l1 .4.8.6.6.8.4 1v1.1l-.2 1a6 6 0 0 1-1.3 1.9l-.5.5-.7.4-1.1.3c5.5 3.3 8 2.7 10.1 2a8 8 0 0 1 5.2 0q1.8.6 2.3 1.3l.7 1 .3 1.2v1l-.4.9-.4.7-.7.7-1.3 1a16 16 0 0 0 7 2.4c2.2.1 3.8-.6 5-1.2l2-.9 1.6-.2 1.2.1 1 .5.8.6.8 1 .5 1.2v1.3l-.2 1a6 6 0 0 1-1.9 2.5l-2 1.3c-8.2 4-14.5 5.7-19.3 6.4q3.5.7 7.7 1.2c13.5 1.4 31-.5 35.4-17.7 2.8-10.7-2-18-8.9-23-11.2-8-27.8-10.3-30.4-10.7-13.3-1.9-40.5-5-57.8-16"/>',
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
