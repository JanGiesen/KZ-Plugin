<?php
/**
 * KZ-Motto — shortcode [kz_motto]
 *
 * Toont het motto als afbeelding, precies zoals het tot nu toe als losse HTML
 * op de pagina's stond (gecentreerd, 1000px breed, nooit breder dan de kolom).
 *
 * Er zijn drie motto's: Seizoensmotto, Kraonige Motto en 11e van de 11e-Motto.
 * Welke .svg bij welk motto hoort, en welke er bij "Keuze" getoond wordt,
 * beheer je in KZ-Studio (Website → Overig → Motto). Die gegevens staan in
 * Supabase; deze klasse haalt ze op via de openbare functie motto_ophalen()
 * (KZ-Studio, migratie 0042) met hetzelfde Supabase-adres en dezelfde anon-key
 * als de Link-In-Bio, bewaart ze een minuut en valt bij een storing terug op de
 * laatste goede versie.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Motto {

    const SHORTCODE = 'kz_motto';

    /** De laatste goede gegevens, voor als Supabase even niet antwoordt. */
    const OPTIE_LAATST = 'kz_motto_laatst';
    /** Wanneer en met welk resultaat er voor het laatst is opgehaald. */
    const OPTIE_STATUS = 'kz_motto_status';
    const CACHE        = 'kz_motto';
    const CACHE_SECONDEN = 60;

    /** Sleutel => naam. Dezelfde sleutels als in KZ-Studio (src/lib/types.ts, MottoSoort). */
    public static function soorten() {
        return array(
            'seizoen' => __( 'Seizoensmotto', 'kz-plugin' ),
            'kraonig' => __( 'Kraonige Motto', 'kz-plugin' ),
            'elfelf'  => __( '11e van de 11e-Motto', 'kz-plugin' ),
        );
    }

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }

        $varianten = array( __( 'Keuze (ingesteld in KZ-Studio)', 'kz-plugin' ) => 'keuze' );
        foreach ( self::soorten() as $sleutel => $naam ) {
            $varianten[ $naam ] = $sleutel;
        }

        vc_map(
            array(
                'name'        => 'KZ-Motto',
                'base'        => self::SHORTCODE,
                'description' => __( 'Het motto als afbeelding; de .svg\'s beheer je in KZ-Studio.', 'kz-plugin' ),
                'category'    => 'Kraonige Zwaone',
                'icon'        => 'kz-icon-motto',
                'params'      => array(
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Motto', 'kz-plugin' ),
                        'param_name'  => 'variant',
                        'value'       => $varianten,
                        'std'         => 'keuze',
                        'admin_label' => true,
                        'description' => __( 'Keuze toont het motto dat in KZ-Studio (Website → Overig → Motto) is aangevinkt.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Breedte (px)', 'kz-plugin' ),
                        'param_name'  => 'breedte',
                        'std'         => '1000',
                        'description' => __( 'Nooit breder dan de kolom.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Marge boven (px)', 'kz-plugin' ),
                        'param_name'  => 'marge_boven',
                        'std'         => '40',
                        'description' => __( '0 = geen marge.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Marge onder (px)', 'kz-plugin' ),
                        'param_name'  => 'marge_onder',
                        'std'         => '40',
                        'description' => __( '0 = geen marge.', 'kz-plugin' ),
                    ),
                    array(
                        'type'       => 'textfield',
                        'heading'    => __( 'Extra CSS-klasse', 'kz-plugin' ),
                        'param_name' => 'el_class',
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'variant'  => 'keuze',
                'breedte'  => '1000',
                'marge_boven' => '40',
                'marge_onder' => '40',
                // Uit 8.10.1 (Aan/Uit); blijft geaccepteerd zodat oude shortcodes
                // niet breken, maar marge_boven/marge_onder bepalen de marge.
                'marges'   => '',
                'el_class' => '',
            ),
            $atts,
            self::SHORTCODE
        );

        $data = self::gegevens();
        if ( null === $data ) {
            return self::uitleg( 'nog geen gegevens uit KZ-Studio opgehaald' );
        }

        $soorten = self::soorten();
        $soort   = isset( $soorten[ $atts['variant'] ] ) ? $atts['variant'] : $data['keuze'];
        $url     = $data[ $soort ];
        if ( '' === $url ) {
            return self::uitleg( 'bij ' . $soorten[ $soort ] . ' is nog geen .svg gekoppeld' );
        }
        // De kopie op deze site als die er is, zodat het motto ook bij een
        // storing van Supabase blijft staan.
        $url = KZ_Spiegel::url( $url );

        $breedte = absint( $atts['breedte'] );
        if ( ! $breedte ) {
            $breedte = 1000;
        }
        $klasse = trim( 'kz-motto ' . implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['el_class'] ) ) ) );

        $stijl = 'text-align:center;margin:' . self::marge( $atts['marge_boven'] ) . 'px 0 ' . self::marge( $atts['marge_onder'] ) . 'px;';

        return '<div class="' . esc_attr( $klasse ) . '" style="' . esc_attr( $stijl ) . '">'
            . '<img src="' . esc_url( $url ) . '" width="' . esc_attr( $breedte ) . '" alt="' . esc_attr( 'Motto Kraonige Zwaone' ) . '" style="max-width:100%;height:auto;" decoding="async">'
            . '</div>';
    }

    /** Een marge in px; leeg of onzin = de standaard 40, "0" = geen. */
    private static function marge( $waarde ) {
        $waarde = trim( (string) $waarde );
        return preg_match( '/^\d+$/', $waarde ) ? absint( $waarde ) : 40;
    }

    /** Voor redacteuren een HTML-commentaar waarom er niets staat; bezoekers zien niets. */
    private static function uitleg( $reden ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return '';
        }
        return '<!-- KZ-Motto: ' . esc_html( $reden ) . ' -->';
    }

    // ------------------------------------------------------------------
    // Ophalen
    // ------------------------------------------------------------------

    /**
     * De gegevens: uit de cache, anders vers uit Supabase, en als dat mislukt
     * de laatste goede versie. Null als er nog nooit iets is opgehaald.
     *
     * @return array{seizoen:string,kraonig:string,elfelf:string,keuze:string}|null
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
            // laat wachten.
            set_transient( self::CACHE, $laatst, self::CACHE_SECONDEN );
            return $laatst;
        }
        return null;
    }

    /** Leegt de cache, zodat het volgende bezoek vers ophaalt. */
    public static function vergeet_cache() {
        delete_transient( self::CACHE );
    }

    /** Roept motto_ophalen() aan; geeft de opgeschoonde gegevens terug, of null bij een fout. */
    public static function ophalen() {
        $url     = untrailingslashit( (string) get_option( KZ_Link_In_Bio::OPTIE_URL, KZ_Link_In_Bio::STANDAARD_URL ) );
        $sleutel = trim( (string) get_option( KZ_Link_In_Bio::OPTIE_SLEUTEL, '' ) );

        if ( '' === $url || '' === $sleutel ) {
            self::zet_status( false, 'Supabase-adres of anon-key ontbreekt (KZ Plugin → Link-In-Bio).' );
            return null;
        }

        $antwoord = wp_remote_post(
            $url . '/rest/v1/rpc/motto_ophalen',
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

        $schoon    = self::schoon( $json );
        $gekoppeld = count( array_filter( array_intersect_key( $schoon, self::soorten() ) ) );
        self::zet_status( true, $gekoppeld . ' van 3 gekoppeld, keuze: ' . self::soorten()[ $schoon['keuze'] ] );
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

    /** Alleen https-adressen en een bekende keuze. */
    private static function schoon( array $json ) {
        $schoon = array();
        foreach ( array_keys( self::soorten() ) as $sleutel ) {
            $url = isset( $json[ $sleutel ] ) ? trim( (string) $json[ $sleutel ] ) : '';
            $schoon[ $sleutel ] = preg_match( '#^https://\S+$#i', $url ) ? esc_url_raw( $url, array( 'https' ) ) : '';
        }
        $schoon['keuze'] = isset( $json['keuze'] ) && isset( self::soorten()[ $json['keuze'] ] ) ? $json['keuze'] : 'seizoen';
        return $schoon;
    }
}
