<?php
/**
 * KZ-Evenementen Overzicht — shortcode [kz_evenementen_overzicht]
 * Toont alle zichtbare evenementen (post type kz_evenement, beheerd in
 * KZ-Studio) als KZ-Evenement Info-blokken in rijen van 2, gesorteerd op
 * datum en begintijd, optioneel uit één categorie. Verlopen evenementen
 * (vanaf 2 dagen na de datum) verdwijnen vanzelf.
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
                        'description' => __( 'Op datum en begintijd. Evenementen zonder datum staan altijd achteraan.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Categorie', 'kz-plugin' ),
                        'param_name'  => 'categorie',
                        'value'       => KZ_Evenementen::categorie_opties( __( 'Alle categorieën', 'kz-plugin' ) ),
                        'std'         => '',
                        'description' => __( 'Alleen evenementen uit deze categorie (ingesteld in KZ-Studio). Verlopen evenementen verdwijnen altijd vanzelf, vanaf 2 dagen na de datum.', 'kz-plugin' ),
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
                'categorie'     => '',
                'max_aantal'    => '',
                'leeg_tekst'    => '',
                'el_class'      => '',
                'css'           => '',
            ),
            $atts,
            self::SHORTCODE
        );

        // Verlopen evenementen (vanaf 2 dagen na de datum) laten we altijd weg.
        // Het oude attribuut alleen_komend wordt nog geaccepteerd maar doet
        // niets meer, zodat bestaande shortcodes blijven werken.
        $rijen = array();
        foreach ( KZ_Evenementen::gesorteerd( trim( $atts['categorie'] ) ) as $post ) {
            if ( ! KZ_Evenementen::is_verlopen( $post ) ) {
                $rijen[] = $post;
            }
        }

        if ( 'aflopend' === $atts['sortering'] ) {
            // Omdraaien, maar evenementen zonder datum blijven achteraan.
            $met    = array();
            $zonder = array();
            foreach ( $rijen as $post ) {
                if ( '' === KZ_Evenementen::sorteersleutel( $post ) ) {
                    $zonder[] = $post;
                } else {
                    $met[] = $post;
                }
            }
            $rijen = array_merge( array_reverse( $met ), $zonder );
        }

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
            list( $blok_atts, $omschrijving ) = KZ_Evenementen::naar_atts( $rij );
            $html .= '<div class="kz-evt-overzicht__item">' . KZ_Element_Event_Info::render( $blok_atts, $omschrijving ) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}
