<?php
/**
 * Gedeelde helper voor het parsen van WPBakery vc_link-velden.
 *
 * Schema en domein worden altijd naar kleine letters gezet. Het pad wordt
 * alleen verkleind bij links naar de eigen site (of relatieve links); bij
 * externe links blijven pad, query en fragment ongewijzigd, omdat die vaak
 * hoofdlettergevoelig zijn (bijv. YouTube-video-ID's: ?v=dQw4w9WgXcQ).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Link_Helper {

    public static function parse( $raw_link ) {
        if ( ! preg_match( '/^\s*(url|title|target|rel):/', (string) $raw_link ) ) {
            // Een kale URL (bijv. uit KZ-Pagina): dan is het de URL zelf.
            $link_parsed = array( 'url' => (string) $raw_link );
        } elseif ( function_exists( 'vc_build_link' ) ) {
            $link_parsed = vc_build_link( $raw_link );
        } else {
            $link_parsed = self::parse_vc_link( (string) $raw_link );
        }

        $url_raw    = isset( $link_parsed['url'] ) ? (string) $link_parsed['url'] : '';
        $title_attr = isset( $link_parsed['title'] ) ? (string) $link_parsed['title'] : '';
        $target     = isset( $link_parsed['target'] ) ? (string) $link_parsed['target'] : '';
        $rel        = isset( $link_parsed['rel'] ) ? (string) $link_parsed['rel'] : '';

        return array(
            'href'   => esc_url( self::normalize_url( $url_raw ) ),
            'target' => $target ? ' target="' . esc_attr( $target ) . '"' : '',
            'rel'    => $rel ? ' rel="' . esc_attr( $rel ) . '"' : '',
            'title'  => $title_attr ? ' title="' . esc_attr( $title_attr ) . '"' : '',
        );
    }

    /**
     * Zelfde als vc_build_link() voor als WPBakery er (even) niet is:
     * "url:...|title:...|target:...|rel:...", elk deel URL-gecodeerd.
     */
    private static function parse_vc_link( $raw ) {
        $uit = array();
        foreach ( explode( '|', $raw ) as $deel ) {
            $paar = explode( ':', $deel, 2 );
            if ( 2 === count( $paar ) ) {
                $uit[ $paar[0] ] = rawurldecode( $paar[1] );
            }
        }
        return $uit;
    }

    /**
     * Hulp voor param_group-velden van WPBakery. Zonder WPBakery (uitgezet,
     * of halverwege een update) gaf vc_param_group_parse_atts() een fatale
     * fout op elke pagina met zo'n element; nu de eigen terugval.
     */
    public static function param_group( $waarde ) {
        if ( function_exists( 'vc_param_group_parse_atts' ) ) {
            $lijst = vc_param_group_parse_atts( $waarde );
        } else {
            $lijst = json_decode( rawurldecode( (string) $waarde ), true );
        }
        return is_array( $lijst ) ? $lijst : array();
    }

    /**
     * Verkleint schema en domein; het pad alleen bij interne links.
     * Query en fragment blijven altijd ongewijzigd.
     */
    public static function normalize_url( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) {
            return '';
        }
        $url_original = $url;

        // Zonder schema maar met domein (bijv. "youtu.be/abc"): esc_url maakt
        // daar later http:// van, dus behandel het eerste deel als domein.
        if ( ! preg_match( '#^([a-z][a-z0-9+.\-]*:|[/?\#.])#i', $url ) && preg_match( '#^[^/?\#]+\.[a-z]{2,}([/?\#:]|$)#i', $url ) ) {
            $url = '//' . $url;
        }

        // Splits "schema://domein" (optioneel) van de rest.
        if ( preg_match( '#^([a-z][a-z0-9+.\-]*:)?(//[^/?\#]*)?(.*)$#is', $url, $m ) ) {
            $scheme = strtolower( $m[1] );
            $host   = strtolower( $m[2] );
            $rest   = $m[3];
        } else {
            return $url;
        }

        // Geen domein: mailto:, tel:, #anker e.d. ongemoeid laten (op schema na).
        if ( '' === $host && '' !== $scheme ) {
            return $scheme . $rest;
        }

        $site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
        $link_host = preg_replace( '#^//([^@]*@)?([^:]*).*$#', '$2', $host );
        $internal  = ( '' === $host ) || ( '' !== $site_host && preg_replace( '/^www\./', '', $link_host ) === preg_replace( '/^www\./', '', $site_host ) );

        // Bestanden in de mediabibliotheek houden hun hoofdletters: de server is
        // daar hoofdlettergevoelig, dus "/Flyer-HJC.pdf" verkleinen gaf een 404.
        if ( $internal && ! preg_match( '#^/wp-content/#i', $rest ) ) {
            $split = strcspn( $rest, '?#' );
            $rest  = strtolower( substr( $rest, 0, $split ) ) . substr( $rest, $split );
        }

        $result = $scheme . $host . $rest;
        if ( '' === $scheme && 0 === strpos( $result, '//' ) && 0 !== strpos( $url_original, '//' ) ) {
            $result = substr( $result, 2 );
        }

        return $result;
    }
}
