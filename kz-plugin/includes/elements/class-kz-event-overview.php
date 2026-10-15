<?php
/**
 * KZ-Evenementen Overzicht — shortcode [kz_evenementen_overzicht]
 * Toont alle zichtbare evenementen (post type kz_evenement, beheerd in
 * KZ-Studio) als KZ-Evenement Info-blokken in rijen van 2.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Event_Overview {

    const SHORTCODE = 'kz_evenementen_overzicht';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => __( 'KZ-Evenementen Overzicht', 'kz-plugin' ),
                'base'        => self::SHORTCODE,
                'category'    => __( 'Kraonige Zwaone', 'kz-plugin' ),
                'icon'        => 'kz-icon-event-overview',
                'description' => __( 'Alle zichtbare evenementen uit KZ-Studio, in rijen van 2.', 'kz-plugin' ),
                'params'      => array(
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Kolommen', 'kz-plugin' ),
                        'param_name'  => 'kolommen',
                        'value'       => array(
                            '2' => '2',
                            '1' => '1',
                        ),
                        'std'         => '2',
                        'description' => __( 'Op mobiel altijd 1 kolom.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Sortering', 'kz-plugin' ),
                        'param_name'  => 'sortering',
                        'value'       => array(
                            __( 'Datum, eerstvolgende eerst', 'kz-plugin' ) => 'oplopend',
                            __( 'Datum, laatste eerst', 'kz-plugin' )       => 'aflopend',
                        ),
                        'std'         => 'oplopend',
                        'description' => __( 'Evenementen zonder datum staan altijd achteraan.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Voorbije evenementen', 'kz-plugin' ),
                        'param_name'  => 'alleen_komend',
                        'value'       => array(
                            __( 'Tonen (zichtbaarheid regel je per evenement in KZ-Studio)', 'kz-plugin' ) => 'nee',
                            __( 'Automatisch verbergen na de datum', 'kz-plugin' )                       => 'ja',
                        ),
                        'std'         => 'nee',
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Maximaal aantal', 'kz-plugin' ),
                        'param_name'  => 'max_aantal',
                        'value'       => '',
                        'description' => __( 'Leeg = alle evenementen.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Tekst als er geen evenementen zijn', 'kz-plugin' ),
                        'param_name'  => 'leeg_tekst',
                        'value'       => '',
                        'description' => __( 'Leeg = niets tonen.', 'kz-plugin' ),
                    ),
                    array(
                        'type'       => 'textfield',
                        'heading'    => __( 'Extra CSS class', 'kz-plugin' ),
                        'param_name' => 'el_class',
                        'group'      => __( 'Layout', 'kz-plugin' ),
                    ),
                    array(
                        'type'       => 'css_editor',
                        'heading'    => __( 'Design Options', 'kz-plugin' ),
                        'param_name' => 'css',
                        'group'      => __( 'Design Options', 'kz-plugin' ),
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'kolommen'      => '2',
                'sortering'     => 'oplopend',
                'alleen_komend' => 'nee',
                'max_aantal'    => '',
                'leeg_tekst'    => '',
                'el_class'      => '',
                'css'           => '',
            ),
            $atts,
            self::SHORTCODE
        );

        $evenementen = get_posts(
            array(
                'post_type'        => KZ_Evenementen::POST_TYPE,
                'post_status'      => 'publish',
                'posts_per_page'   => -1,
                'no_found_rows'    => true,
                'suppress_filters' => false,
            )
        );

        // Sorteren in PHP: evenementen zonder datum moeten achteraan, wat met
        // een meta-orderby niet lukt (die laat ze weg of zet ze vooraan).
        $vandaag  = current_time( 'Y-m-d' );
        $aflopend = 'aflopend' === $atts['sortering'];
        $rijen    = array();
        foreach ( $evenementen as $post ) {
            $datum = (string) get_post_meta( $post->ID, 'kz_datum', true );
            if ( 'ja' === $atts['alleen_komend'] && '' !== $datum && $datum < $vandaag ) {
                continue;
            }
            $rijen[] = array( 'datum' => $datum, 'post' => $post );
        }
        usort(
            $rijen,
            function ( $a, $b ) use ( $aflopend ) {
                if ( '' === $a['datum'] || '' === $b['datum'] ) {
                    return ( '' === $a['datum'] ) - ( '' === $b['datum'] );
                }
                $verschil = strcmp( $a['datum'], $b['datum'] );
                return $aflopend ? -$verschil : $verschil;
            }
        );

        $max = absint( $atts['max_aantal'] );
        if ( $max > 0 ) {
            $rijen = array_slice( $rijen, 0, $max );
        }

        $classes = array( 'kz-evt-overzicht', 'kz-evt-overzicht--' . ( '1' === $atts['kolommen'] ? '1' : '2' ) );
        if ( function_exists( 'vc_shortcode_custom_css_class' ) ) {
            $classes[] = vc_shortcode_custom_css_class( $atts['css'], '' );
        }
        $classes[] = $atts['el_class'];
        $classes   = implode( ' ', array_filter( array_map( 'trim', $classes ) ) );

        if ( empty( $rijen ) ) {
            if ( '' === trim( $atts['leeg_tekst'] ) ) {
                return '';
            }
            return '<div class="' . esc_attr( $classes ) . ' kz-evt-overzicht--leeg"><p>' . esc_html( $atts['leeg_tekst'] ) . '</p></div>';
        }

        wp_enqueue_style( 'kz-evenementen-overzicht-style' );

        $html = '<div class="' . esc_attr( $classes ) . '">';
        foreach ( $rijen as $rij ) {
            list( $blok_atts, $omschrijving ) = KZ_Evenementen::naar_atts( $rij['post'] );
            $html .= '<div class="kz-evt-overzicht__item">' . KZ_Element_Event_Info::render( $blok_atts, $omschrijving ) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}
