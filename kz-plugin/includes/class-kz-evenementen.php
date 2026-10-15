<?php
/**
 * Post type "kz_evenement" — evenementen die vanuit KZ-Studio via de REST API
 * worden aangemaakt (wp-json/wp/v2/kz-evenementen) en op de site getoond met
 * het element KZ-Evenementen Overzicht.
 *
 * Zichtbaar in het overzicht = status "publish", onzichtbaar = "draft".
 * De velden zijn dezelfde als die van het losse element KZ-Evenement Info.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Evenementen {

    const POST_TYPE = 'kz_evenement';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register' ) );
    }

    public static function register() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels'          => array(
                    'name'          => __( 'Evenementen', 'kz-plugin' ),
                    'singular_name' => __( 'Evenement', 'kz-plugin' ),
                    'add_new_item'  => __( 'Nieuw evenement', 'kz-plugin' ),
                    'edit_item'     => __( 'Evenement bewerken', 'kz-plugin' ),
                    'all_items'     => __( 'Alle evenementen', 'kz-plugin' ),
                ),
                'description'     => __( 'Evenementen voor het overzicht op de ticketpagina. Beheer ze bij voorkeur in KZ-Studio; wijzigingen hier worden overschreven bij de volgende synchronisatie.', 'kz-plugin' ),
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => true,
                'menu_icon'       => 'dashicons-tickets-alt',
                'show_in_rest'    => true,
                'rest_base'       => 'kz-evenementen',
                'supports'        => array( 'title', 'editor', 'custom-fields' ),
                'capability_type' => 'post',
                'map_meta_cap'    => true,
            )
        );

        $tekst = array(
            'kz_label'        => 'sanitize_text_field',
            'kz_dag_tekst'    => 'sanitize_text_field',
            'kz_tijd'         => 'sanitize_text_field',
            'kz_link_url'     => 'esc_url_raw',
            'kz_knop_tekst'   => 'sanitize_text_field',
            'kz_kleine_tekst' => 'sanitize_textarea_field',
            'kz_datum'        => array( __CLASS__, 'schoon_datum' ),
            'kz_verkoop_start' => array( __CLASS__, 'schoon_datum' ),
            'kz_status'       => array( __CLASS__, 'schoon_status' ),
        );
        foreach ( $tekst as $sleutel => $schoonmaker ) {
            self::register_meta( $sleutel, 'string', '', $schoonmaker, true );
        }

        self::register_meta( 'kz_link_nieuw_venster', 'boolean', true, 'rest_sanitize_boolean', true );
        self::register_meta( 'kz_show_logo', 'boolean', true, 'rest_sanitize_boolean', true );

        self::register_meta(
            'kz_feiten',
            'array',
            array(),
            array( __CLASS__, 'schoon_feiten' ),
            array(
                'schema' => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'                 => 'object',
                        'properties'           => array(
                            'label'  => array( 'type' => 'string' ),
                            'waarde' => array( 'type' => 'string' ),
                        ),
                        'additionalProperties' => false,
                    ),
                ),
            )
        );
    }

    private static function register_meta( $sleutel, $type, $standaard, $schoonmaker, $show_in_rest ) {
        register_post_meta(
            self::POST_TYPE,
            $sleutel,
            array(
                'type'              => $type,
                'single'            => true,
                'default'           => $standaard,
                'show_in_rest'      => $show_in_rest,
                'sanitize_callback' => $schoonmaker,
                'auth_callback'     => function () {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }

    /** Alleen jjjj-mm-dd, anders leeg. */
    public static function schoon_datum( $waarde ) {
        $waarde = trim( (string) $waarde );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $waarde ) ? $waarde : '';
    }

    public static function schoon_status( $waarde ) {
        return in_array( $waarde, array( 'open', 'binnenkort', 'uitverkocht' ), true ) ? $waarde : 'open';
    }

    public static function schoon_feiten( $waarde ) {
        $schoon = array();
        foreach ( (array) $waarde as $feit ) {
            if ( ! is_array( $feit ) ) {
                continue;
            }
            $schoon[] = array(
                'label'  => sanitize_text_field( isset( $feit['label'] ) ? $feit['label'] : '' ),
                'waarde' => sanitize_text_field( isset( $feit['waarde'] ) ? $feit['waarde'] : '' ),
            );
        }
        return $schoon;
    }

    /**
     * Zet een evenement om naar de attributen van [kz_evenement_info], zodat
     * het overzicht exact hetzelfde blok toont als het losse element.
     *
     * @param WP_Post $post
     * @return array{0: array, 1: string} atts en omschrijving (content)
     */
    public static function naar_atts( $post ) {
        $meta = function ( $sleutel ) use ( $post ) {
            return get_post_meta( $post->ID, $sleutel, true );
        };

        $link = '';
        $url  = (string) $meta( 'kz_link_url' );
        if ( '' !== $url ) {
            $link = 'url:' . rawurlencode( $url );
            if ( $meta( 'kz_link_nieuw_venster' ) ) {
                $link .= '|target:' . rawurlencode( '_blank' );
            }
        }

        $feiten = $meta( 'kz_feiten' );
        $knop   = (string) $meta( 'kz_knop_tekst' );

        $atts = array(
            'label'         => (string) $meta( 'kz_label' ),
            'titel'         => get_the_title( $post ),
            'feiten'        => ! empty( $feiten ) && is_array( $feiten ) ? rawurlencode( wp_json_encode( array_values( $feiten ) ) ) : '',
            'show_logo'     => $meta( 'kz_show_logo' ) ? 'true' : 'false',
            'datum'         => (string) $meta( 'kz_datum' ),
            'dag_tekst'     => (string) $meta( 'kz_dag_tekst' ),
            'tijd'          => (string) $meta( 'kz_tijd' ),
            'link'          => $link,
            'knop_tekst'    => '' !== $knop ? $knop : 'Kaarten bestellen',
            'status'        => self::schoon_status( $meta( 'kz_status' ) ),
            'verkoop_start' => (string) $meta( 'kz_verkoop_start' ),
            'kleine_tekst'  => (string) $meta( 'kz_kleine_tekst' ),
            'max_width'     => '100%',
        );

        return array( $atts, (string) $post->post_content );
    }
}
