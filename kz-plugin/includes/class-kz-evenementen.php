<?php
/**
 * Post type "kz_evenement" — evenementen die vanuit KZ-Studio via de REST API
 * worden aangemaakt (wp-json/wp/v2/kz-evenementen) en op de site getoond met
 * het element KZ-Evenementen Overzicht.
 *
 * Zichtbaar in het overzicht = status "publish", onzichtbaar = "draft".
 * Daarnaast verdwijnt een evenement vanzelf: zie is_verlopen().
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
            'kz_categorie'    => 'sanitize_text_field',
            'kz_seizoen'      => array( __CLASS__, 'schoon_seizoen' ),
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

    /** Alleen "jjjj-jjjj", anders leeg (= automatisch het huidige seizoen). */
    public static function schoon_seizoen( $waarde ) {
        $waarde = trim( (string) $waarde );
        return preg_match( '/^\d{4}-\d{4}$/', $waarde ) ? $waarde : '';
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

    /* --- Seizoenskaart -------------------------------------------------------
     * Er is altijd precies één evenement met categorie "Seizoenskaart": het
     * staat vooraan, verloopt nooit en toont een seizoen in plaats van een
     * datum. Ook deze regels staan in KZ-Studio (src/lib/evenementTijd.ts).
     */

    public static function is_seizoenskaart( $post ) {
        return 'seizoenskaart' === strtolower( trim( (string) get_post_meta( $post->ID, 'kz_categorie', true ) ) );
    }

    /**
     * Het seizoen als "2026-2027": de ingevulde waarde, of anders het huidige
     * seizoen (1 juni t/m 31 mei, zoals overal in KZ-Studio).
     */
    public static function seizoen_label( $waarde, $vandaag = null ) {
        $waarde = self::schoon_seizoen( $waarde );
        if ( '' !== $waarde ) {
            return $waarde;
        }
        $vandaag = null === $vandaag ? current_time( 'Y-m-d' ) : $vandaag;
        $jaar    = (int) substr( $vandaag, 0, 4 );
        $maand   = (int) substr( $vandaag, 5, 2 );
        return $maand >= 6 ? $jaar . '-' . ( $jaar + 1 ) : ( $jaar - 1 ) . '-' . $jaar;
    }

    /* --- Tijd en volgorde ----------------------------------------------------
     * Deze regels staan ook in KZ-Studio (src/lib/evenementTijd.ts); houd ze
     * gelijk, anders toont KZ-Studio iets anders dan de website.
     */

    /** De n-de tijd (0 = start, 1 = eind) uit vrije tekst als "20:11 - 02:00", als "HH:MM". */
    private static function tijd_uit( $tijd, $n ) {
        if ( preg_match_all( '/(\d{1,2})[:.](\d{2})/', (string) $tijd, $treffers, PREG_SET_ORDER ) && isset( $treffers[ $n ] ) ) {
            return sprintf( '%02d:%s', (int) $treffers[ $n ][1], $treffers[ $n ][2] );
        }
        return null;
    }

    public static function starttijd( $tijd ) {
        $start = self::tijd_uit( $tijd, 0 );
        return null === $start ? '00:00' : $start;
    }

    /** "jjjj-mm-dd HH:MM", of leeg zonder datum (die komen achteraan). */
    public static function sorteersleutel( $post ) {
        $datum = (string) get_post_meta( $post->ID, 'kz_datum', true );
        if ( '' === $datum ) {
            return '';
        }
        return $datum . ' ' . self::starttijd( get_post_meta( $post->ID, 'kz_tijd', true ) );
    }

    /**
     * Verlopen = vanaf twee dagen na het einde. Het einde is de datum, of de dag
     * erna als de eindtijd na middernacht valt ("20:11 - 02:00"). Een evenement
     * op 14/11 staat dus op 15/11 nog op de site en is vanaf 16/11 weg.
     */
    public static function is_verlopen( $post, $vandaag = null ) {
        if ( self::is_seizoenskaart( $post ) ) {
            return false;
        }
        $datum = (string) get_post_meta( $post->ID, 'kz_datum', true );
        if ( '' === $datum ) {
            return false;
        }
        $tijd  = get_post_meta( $post->ID, 'kz_tijd', true );
        $eind  = self::tijd_uit( $tijd, 1 );
        $dagen = ( null !== $eind && $eind < self::starttijd( $tijd ) ) ? 3 : 2;
        $grens = gmdate( 'Y-m-d', strtotime( $datum . ' 00:00:00 UTC +' . $dagen . ' days' ) );
        return ( null === $vandaag ? current_time( 'Y-m-d' ) : $vandaag ) >= $grens;
    }

    /** Alle gepubliceerde evenementen: Seizoenskaart vooraan, dan op datum + starttijd, zonder datum achteraan. */
    public static function gesorteerd( $categorie = '' ) {
        $args = array(
            'post_type'        => self::POST_TYPE,
            'post_status'      => 'publish',
            'posts_per_page'   => -1,
            'no_found_rows'    => true,
            'suppress_filters' => false,
        );
        if ( '' !== $categorie ) {
            $args['meta_query'] = array(
                array(
                    'key'   => 'kz_categorie',
                    'value' => $categorie,
                ),
            );
        }

        $rijen = array();
        foreach ( get_posts( $args ) as $post ) {
            $rijen[] = array(
                'seizoen' => self::is_seizoenskaart( $post ),
                'sleutel' => self::sorteersleutel( $post ),
                'post'    => $post,
            );
        }
        usort(
            $rijen,
            function ( $a, $b ) {
                if ( $a['seizoen'] !== $b['seizoen'] ) {
                    return $a['seizoen'] ? -1 : 1;
                }
                if ( '' === $a['sleutel'] || '' === $b['sleutel'] ) {
                    return ( '' === $a['sleutel'] ) - ( '' === $b['sleutel'] );
                }
                return strcmp( $a['sleutel'], $b['sleutel'] );
            }
        );
        return wp_list_pluck( $rijen, 'post' );
    }

    /**
     * Voor KZ-Evenement Info in automatische stand: het eerstvolgende evenement
     * in de categorie dat nog niet verlopen is, en anders het laatst gehouden.
     *
     * @return WP_Post|null
     */
    public static function kies_automatisch( $categorie ) {
        $laatste = null;
        foreach ( self::gesorteerd( $categorie ) as $post ) {
            if ( self::is_seizoenskaart( $post ) ) {
                return $post;
            }
            if ( '' === (string) get_post_meta( $post->ID, 'kz_datum', true ) ) {
                continue;
            }
            if ( ! self::is_verlopen( $post ) ) {
                return $post;
            }
            $laatste = $post;
        }
        return $laatste;
    }

    /** Gebruikte categorieën, voor de keuzelijsten in WPBakery. */
    public static function categorieen() {
        global $wpdb;
        $waarden = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s AND pm.meta_value <> '' AND p.post_type = %s AND p.post_status IN ('publish', 'draft')
                 ORDER BY pm.meta_value",
                'kz_categorie',
                self::POST_TYPE
            )
        );
        return is_array( $waarden ) ? $waarden : array();
    }

    /**
     * Keuzelijst voor vc_map: eerst de optie zonder categorie, dan de gebruikte
     * categorieën. Een eerder gekozen categorie die (nog) niet bestaat blijft
     * zo geldig in de shortcode, ook al staat hij niet in de lijst.
     */
    public static function categorie_opties( $leeg_label ) {
        $opties = array( $leeg_label => '' );
        // vc_map draait op elke pagina; alleen in de editor is de lijst nodig.
        if ( ! is_admin() && empty( $_GET['vc_editable'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return $opties;
        }
        foreach ( self::categorieen() as $categorie ) {
            $opties[ $categorie ] = $categorie;
        }
        return $opties;
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
            'variant'       => self::is_seizoenskaart( $post ) ? 'seizoen' : 'normaal',
            'seizoen'       => self::is_seizoenskaart( $post ) ? self::seizoen_label( $meta( 'kz_seizoen' ) ) : '',
        );

        return array( $atts, (string) $post->post_content );
    }
}
