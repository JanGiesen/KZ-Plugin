<?php
/**
 * Gedeelde basis voor KZ-Groeperingen en KZ-Nieuws: een grid van berichten dat
 * helemaal op de server getekend wordt.
 *
 * Waarom: de WPBakery Post Grid (vc_basic_grid) tekent bij het laden een lege
 * container en haalt de items daarna op via admin-ajax.php (actie
 * vc_get_vc_grid_data), met een nonce uit data-vc-public-nonce. LiteSpeed Cache
 * bewaart een pagina tot 7 dagen, een nonce voor gasten is hooguit 24 uur
 * geldig. Daarna antwoordt admin-ajax met "0" en blijft de grid leeg. Deze
 * grids staan meteen in de HTML en hebben geen AJAX, JavaScript of nonce nodig.
 * Paginering gaat via de server (?pagina=2, of /page/2/).
 *
 * Omdat de inhoud nu in de gecachete HTML staat, zou een nieuw bericht pas na
 * het verlopen van de cache verschijnen. Daarom krijgt elke pagina met zo'n
 * grid een LiteSpeed-cachetag per post type, en wordt die tag geleegd zodra een
 * bericht van dat type wordt opgeslagen, gepubliceerd of verwijderd. Zonder
 * LiteSpeed doen die acties niets.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Berichten_Grid {

    /** Querystring voor de paginering: /nieuws/?pagina=2. */
    const PAGINA_PARAM = 'pagina';

    /** Voorvoegsel van de LiteSpeed-cachetag, gevolgd door het post type. */
    const CACHE_TAG = 'KZ_GRID_';

    /** Zijn de hooks voor het legen van de cache al gezet? */
    private static $hooks_gezet = false;

    /**
     * Haakt het legen van de cache in op het opslaan en verwijderen van
     * berichten. Eén keer, vanuit kz-plugin.php.
     */
    public static function init() {
        if ( self::$hooks_gezet ) {
            return;
        }
        self::$hooks_gezet = true;

        add_action( 'save_post', array( __CLASS__, 'cache_legen_voor_bericht' ), 20, 1 );
        add_action( 'trashed_post', array( __CLASS__, 'cache_legen_voor_bericht' ), 20, 1 );
        add_action( 'before_delete_post', array( __CLASS__, 'cache_legen_voor_bericht' ), 20, 1 );
        add_action( 'transition_post_status', array( __CLASS__, 'cache_legen_bij_status' ), 20, 3 );
    }

    /**
     * Leegt de cachetag van het post type van dit bericht (alleen als het
     * bericht openbaar is of was; revisies en autosaves tellen niet).
     */
    public static function cache_legen_voor_bericht( $post_id ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        $post_type = get_post_type( $post_id );
        if ( $post_type ) {
            self::cache_legen( $post_type );
        }
    }

    public static function cache_legen_bij_status( $nieuw, $oud, $post ) {
        if ( $nieuw === $oud || ! ( $post instanceof WP_Post ) ) {
            return;
        }
        if ( 'publish' === $nieuw || 'publish' === $oud ) {
            self::cache_legen( $post->post_type );
        }
    }

    private static function cache_legen( $post_type ) {
        do_action( 'litespeed_purge', self::CACHE_TAG . sanitize_key( $post_type ) );
    }

    /** Zet de cachetag op de pagina die nu getekend wordt. */
    public static function cache_tag( $post_type ) {
        do_action( 'litespeed_tag_add', self::CACHE_TAG . sanitize_key( $post_type ) );
    }

    /**
     * Het adres waar de paginering naar linkt: de permalink van de pagina
     * (zonder /page/N/), anders het huidige adres.
     */
    public static function basis_url() {
        $id = get_queried_object_id();
        if ( $id && is_singular() ) {
            $url = get_permalink( $id );
            if ( $url ) {
                return $url;
            }
        }
        return home_url( add_query_arg( array() ) );
    }

    /**
     * Het huidige paginanummer: ?pagina=N, of /page/N/ (query var paged).
     */
    public static function huidige_pagina() {
        $pagina = 0;
        if ( isset( $_GET[ self::PAGINA_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $pagina = absint( wp_unslash( $_GET[ self::PAGINA_PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
        }
        if ( $pagina < 1 ) {
            $pagina = absint( get_query_var( 'paged' ) );
        }
        return max( 1, $pagina );
    }

    /** 'ja'/'nee' (en true/false/1/0) naar een boolean. */
    public static function ja( $waarde ) {
        return in_array( strtolower( trim( (string) $waarde ) ), array( 'ja', 'true', '1', 'yes', 'aan' ), true );
    }

    /** Een aantal kolommen tussen 1 en 8. */
    public static function kolommen( $waarde, $standaard ) {
        $k = absint( $waarde );
        return ( $k >= 1 && $k <= 8 ) ? $k : $standaard;
    }

    /**
     * De WP_Query-argumenten voor de sortering.
     *
     * @return array{orderby: mixed, order: string}
     */
    public static function sortering( $sortering ) {
        switch ( $sortering ) {
            case 'titel':
                return array( 'orderby' => 'title', 'order' => 'ASC' );
            case 'volgorde':
                return array( 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), 'order' => 'ASC' );
            case 'oudste':
                return array( 'orderby' => 'date', 'order' => 'ASC' );
            case 'willekeurig':
                return array( 'orderby' => 'rand', 'order' => 'ASC' );
            case 'datum':
            default:
                return array( 'orderby' => 'date', 'order' => 'DESC' );
        }
    }

    /**
     * De klassen van de buitenste div: vaste klasse, el_class en de klasse
     * van WPBakery's Design Options (css_editor).
     */
    public static function wrapper_klassen( array $basis, $el_class, $css ) {
        $klassen = $basis;
        if ( '' !== trim( (string) $el_class ) ) {
            $klassen[] = $el_class;
        }
        if ( '' !== (string) $css && function_exists( 'vc_shortcode_custom_css_class' ) ) {
            $klassen[] = vc_shortcode_custom_css_class( $css, '' );
        }
        $klassen = array_filter( array_map( 'trim', $klassen ) );
        return implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', implode( ' ', $klassen ) ) ) );
    }

    /** De inline CSS-variabelen voor kolommen en ruimte. */
    public static function stijl( $kolommen, $tablet, $mobiel, $ruimte ) {
        return sprintf(
            '--kz-kol:%d;--kz-kol-tablet:%d;--kz-kol-mobiel:%d;--kz-ruimte:%dpx;',
            $kolommen,
            $tablet,
            $mobiel,
            $ruimte
        );
    }

    /**
     * De uitgelichte afbeelding van een bericht als URL. Een .svg heeft geen
     * kleinere formaten, daarvoor geeft WordPress vanzelf het origineel.
     */
    public static function foto_url( $post, $formaat ) {
        $id = get_post_thumbnail_id( $post );
        if ( ! $id ) {
            return '';
        }
        $url = wp_get_attachment_image_url( $id, $formaat );
        return $url ? $url : '';
    }

    /** Alt-tekst van de uitgelichte afbeelding, anders de titel. */
    public static function foto_alt( $post ) {
        $id  = get_post_thumbnail_id( $post );
        $alt = $id ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
        return '' !== $alt ? $alt : get_the_title( $post );
    }

    /**
     * Een korte samenvatting zonder filters te draaien (the_content van een
     * bericht draaien binnen een shortcode kan WPBakery in een lus brengen).
     * Shortcode-haken worden weggehaald, hun inhoud blijft staan.
     */
    public static function samenvatting( $post, $woorden ) {
        $tekst = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
        $tekst = preg_replace( '/\[\/?[a-zA-Z0-9_\-]+[^\]]*\]/', ' ', (string) $tekst );
        $tekst = wp_strip_all_tags( $tekst, true );
        $tekst = html_entity_decode( $tekst, ENT_QUOTES, get_bloginfo( 'charset' ) );
        return wp_trim_words( $tekst, max( 1, (int) $woorden ), '…' );
    }

    /**
     * Paginering als links naar ?pagina=N. Pagina 1 is de kale permalink,
     * zodat er geen dubbele cache-ingang ontstaat.
     */
    public static function paginering( $huidig, $totaal, $basis_url, $kleur ) {
        if ( $totaal < 2 ) {
            return '';
        }

        $url = function ( $n ) use ( $basis_url ) {
            $schoon = remove_query_arg( self::PAGINA_PARAM, $basis_url );
            return 1 === $n ? $schoon : add_query_arg( self::PAGINA_PARAM, $n, $schoon );
        };

        // Welke nummers: eerste, laatste en twee rond de huidige; de rest "…".
        $nummers = array();
        for ( $n = 1; $n <= $totaal; $n++ ) {
            if ( 1 === $n || $totaal === $n || abs( $n - $huidig ) <= 2 ) {
                $nummers[] = $n;
            }
        }

        $html  = '<nav class="kz-grid-paginering kz-grid-paginering--' . esc_attr( $kleur ) . '" aria-label="' . esc_attr__( 'Paginering', 'kz-plugin' ) . '"><ul>';
        if ( $huidig > 1 ) {
            $html .= '<li><a class="kz-grid-paginering__pijl" rel="prev" href="' . esc_url( $url( $huidig - 1 ) ) . '" aria-label="' . esc_attr__( 'Vorige pagina', 'kz-plugin' ) . '">&lsaquo;</a></li>';
        }
        $vorige = 0;
        foreach ( $nummers as $n ) {
            if ( $vorige && $n > $vorige + 1 ) {
                $html .= '<li><span class="kz-grid-paginering__puntjes">&hellip;</span></li>';
            }
            if ( $n === $huidig ) {
                $html .= '<li><span class="kz-grid-paginering__huidig" aria-current="page">' . (int) $n . '</span></li>';
            } else {
                $html .= '<li><a href="' . esc_url( $url( $n ) ) . '">' . (int) $n . '</a></li>';
            }
            $vorige = $n;
        }
        if ( $huidig < $totaal ) {
            $html .= '<li><a class="kz-grid-paginering__pijl" rel="next" href="' . esc_url( $url( $huidig + 1 ) ) . '" aria-label="' . esc_attr__( 'Volgende pagina', 'kz-plugin' ) . '">&rsaquo;</a></li>';
        }
        $html .= '</ul></nav>';

        return $html;
    }

    /** De gedeelde vc_map-velden voor kolommen, ruimte, el_class en css. */
    public static function vc_velden_opmaak( $kolommen, $tablet, $mobiel, $ruimte ) {
        $opties = array();
        for ( $i = 1; $i <= 8; $i++ ) {
            $opties[ (string) $i ] = (string) $i;
        }
        $groep = __( 'Opmaak', 'kz-plugin' );

        return array(
            array(
                'type'       => 'dropdown',
                'heading'    => __( 'Kolommen (computer)', 'kz-plugin' ),
                'param_name' => 'kolommen',
                'value'      => $opties,
                'std'        => (string) $kolommen,
                'group'      => $groep,
            ),
            array(
                'type'        => 'dropdown',
                'heading'     => __( 'Kolommen (tablet)', 'kz-plugin' ),
                'param_name'  => 'kolommen_tablet',
                'value'       => $opties,
                'std'         => (string) $tablet,
                'description' => __( 'Schermen tot 1024px breed.', 'kz-plugin' ),
                'group'       => $groep,
            ),
            array(
                'type'        => 'dropdown',
                'heading'     => __( 'Kolommen (mobiel)', 'kz-plugin' ),
                'param_name'  => 'kolommen_mobiel',
                'value'       => $opties,
                'std'         => (string) $mobiel,
                'description' => __( 'Schermen tot 767px breed.', 'kz-plugin' ),
                'group'       => $groep,
            ),
            array(
                'type'        => 'textfield',
                'heading'     => __( 'Ruimte tussen de items (px)', 'kz-plugin' ),
                'param_name'  => 'ruimte',
                'value'       => (string) $ruimte,
                'description' => __( 'Zoals "Gap" bij de WPBakery Post Grid.', 'kz-plugin' ),
                'group'       => $groep,
            ),
            array(
                'type'       => 'textfield',
                'heading'    => __( 'Extra CSS class', 'kz-plugin' ),
                'param_name' => 'el_class',
                'value'      => '',
                'group'      => $groep,
            ),
            array(
                'type'       => 'css_editor',
                'heading'    => __( 'Design Options', 'kz-plugin' ),
                'param_name' => 'css',
                'group'      => __( 'Design Options', 'kz-plugin' ),
            ),
        );
    }

    /** Dropdown voor de tekstkleur. */
    public static function vc_veld_kleur( $standaard ) {
        return array(
            'type'       => 'dropdown',
            'heading'    => __( 'Tekstkleur', 'kz-plugin' ),
            'param_name' => 'tekstkleur',
            'value'      => array(
                __( 'Rood', 'kz-plugin' ) => 'rood',
                __( 'Wit', 'kz-plugin' )  => 'wit',
            ),
            'std'        => $standaard,
        );
    }

    /** Dropdown voor de sortering. */
    public static function vc_veld_sortering( $standaard ) {
        return array(
            'type'        => 'dropdown',
            'heading'     => __( 'Sortering', 'kz-plugin' ),
            'param_name'  => 'sortering',
            'value'       => array(
                __( 'Datum, nieuwste eerst', 'kz-plugin' )             => 'datum',
                __( 'Datum, oudste eerst', 'kz-plugin' )               => 'oudste',
                __( 'Titel (A-Z)', 'kz-plugin' )                       => 'titel',
                __( 'Volgorde (Pagina-attributen), dan titel', 'kz-plugin' ) => 'volgorde',
                __( 'Willekeurig', 'kz-plugin' )                       => 'willekeurig',
            ),
            'std'         => $standaard,
            'description' => __( 'Neem over wat bij de oude Post Grid onder Data Settings → Order by stond.', 'kz-plugin' ),
        );
    }

    /** Dropdown ja/nee. */
    public static function vc_veld_ja_nee( $param, $kop, $standaard, $uitleg = '' ) {
        $veld = array(
            'type'       => 'dropdown',
            'heading'    => $kop,
            'param_name' => $param,
            'value'      => array(
                __( 'Ja', 'kz-plugin' )  => 'ja',
                __( 'Nee', 'kz-plugin' ) => 'nee',
            ),
            'std'        => $standaard,
        );
        if ( '' !== $uitleg ) {
            $veld['description'] = $uitleg;
        }
        return $veld;
    }
}
