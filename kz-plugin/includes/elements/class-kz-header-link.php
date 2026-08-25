<?php
/**
 * KZ-Header Link — shortcode [kz_header_link]
 * Overgenomen uit KZ-Kraonige Zwaone Plugin 7.1, gedrag ongewijzigd.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Header_Link {

    const SHORTCODE = 'kz_header_link';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => 'KZ-Header Link',
                'base'        => self::SHORTCODE,
                'description' => 'Een link die in de header wordt gebruikt.',
                'category'    => 'Kraonige Zwaone',
                'icon'        => 'kz-icon-header-link',
                'params'      => array(
                    array(
                        'type'        => 'textfield',
                        'heading'     => 'Tekst',
                        'param_name'  => 'text',
                        'description' => 'De tekst die op de knop komt.',
                    ),
                    array(
                        'type'        => 'vc_link',
                        'heading'     => 'Link',
                        'param_name'  => 'link',
                        'description' => 'De URL van de link.',
                    ),
                    array(
                        'type'    => 'dropdown',
                        'heading' => 'Kleur',
                        'param_name' => 'color',
                        'value'   => array(
                            'Rood' => 'rood',
                            'Wit'  => 'wit',
                        ),
                        'std'     => 'rood',
                    ),
                    array(
                        'type'    => 'dropdown',
                        'heading' => 'Uitlijning',
                        'param_name' => 'align',
                        'value'   => array(
                            'Midden' => 'center',
                            'Links'  => 'left',
                            'Rechts' => 'right',
                        ),
                        'std'     => 'center',
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => 'Marges',
                        'param_name'  => 'margins',
                        'value'       => array(
                            'Aan' => 'on',
                            'Uit' => 'off',
                        ),
                        'std'         => 'on',
                        'description' => 'Zet de standaard verticale marges (20px) aan of uit',
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'text'    => '',
                'link'    => '',
                'color'   => 'rood',
                'align'   => 'center',
                'margins' => 'on',
            ),
            $atts,
            self::SHORTCODE
        );

        $text_raw = sanitize_text_field( $atts['text'] );
        if ( function_exists( 'mb_strtoupper' ) ) {
            $text = mb_strtoupper( $text_raw, 'UTF-8' );
        } else {
            $text = strtoupper( $text_raw );
        }

        $link = KZ_Link_Helper::parse( $atts['link'] );

        $color   = ( 'wit' === $atts['color'] ) ? 'wit' : 'rood';
        $align   = in_array( $atts['align'], array( 'left', 'center', 'right' ), true ) ? $atts['align'] : 'center';
        $margins = ( 'off' === $atts['margins'] ) ? 'off' : 'on';

        $wrapper_styles  = 'text-align:' . esc_attr( $align ) . ';';
        $wrapper_styles .= ( 'off' === $margins ) ? 'margin:0;' : 'margin:20px auto;';

        $header_class = 'KZ-HEADER-LINK ' . esc_attr( $color );

        // Let op: net als in het origineel wordt de tekst hier NIET ge-escaped
        // (bestaande content vertrouwt hierop voor eventuele HTML in de titel).
        $text_html = $text;

        $output  = '<div class="kz-header-link-wrapper" style="' . esc_attr( $wrapper_styles ) . '">';
        $output .= '<div class="' . esc_attr( $header_class ) . '">';
        $output .= '<a href="' . $link['href'] . '"' . $link['target'] . $link['rel'] . $link['title'] . '>' . $text_html . '</a>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }
}
