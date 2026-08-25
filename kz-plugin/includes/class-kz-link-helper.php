<?php
/**
 * Gedeelde helper voor het parsen van WPBakery vc_link-velden.
 * Gedrag exact zoals in de originele elementen (url wordt lowercased).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Link_Helper {

    public static function parse( $raw_link ) {
        $link_parsed = function_exists( 'vc_build_link' ) ? vc_build_link( $raw_link ) : array();

        $url_raw    = isset( $link_parsed['url'] ) ? (string) $link_parsed['url'] : '';
        $title_attr = isset( $link_parsed['title'] ) ? (string) $link_parsed['title'] : '';
        $target     = isset( $link_parsed['target'] ) ? (string) $link_parsed['target'] : '';
        $rel        = isset( $link_parsed['rel'] ) ? (string) $link_parsed['rel'] : '';
        $url_lower  = strtolower( trim( $url_raw ) );

        return array(
            'href'   => esc_url( $url_lower ),
            'target' => $target ? ' target="' . esc_attr( $target ) . '"' : '',
            'rel'    => $rel ? ' rel="' . esc_attr( $rel ) . '"' : '',
            'title'  => $title_attr ? ' title="' . esc_attr( $title_attr ) . '"' : '',
        );
    }
}
