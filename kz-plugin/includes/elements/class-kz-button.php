<?php
/**
 * KZ-Knop — shortcode [kz_knop]
 * Overgenomen uit KZ-Kraonige Zwaone Plugin 7.1, gedrag ongewijzigd.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Button {

    const SHORTCODE = 'kz_knop';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => 'KZ-Knop',
                'base'        => self::SHORTCODE,
                'description' => 'Knop met kleur, link, tekst en uitlijning',
                'category'    => 'Kraonige Zwaone',
                'icon'        => 'kz-icon-button',
                'params'      => array(
                    array(
                        'type'        => 'textfield',
                        'heading'     => 'Tekst',
                        'param_name'  => 'text',
                        'description' => 'Tekst op de knop (wordt automatisch hoofdletters)',
                    ),
                    array(
                        'type'        => 'vc_link',
                        'heading'     => 'Link',
                        'param_name'  => 'link',
                        'description' => 'Kies of voer de link in (wordt automatisch kleine letters)',
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
                            'Midden'  => 'center',
                            'Links'   => 'left',
                            'Rechts'  => 'right',
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
                    array(
                        'type'        => 'textfield',
                        'heading'     => 'Marge',
                        'param_name'  => 'margin',
                        'description' => 'Voor eigen marge in (bijv. 20px 0 of 10px 20px 15px 10px) Boven, rechts, onder, links',
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
                'margin'  => '',
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

        $color = ( 'wit' === $atts['color'] ) ? 'wit' : 'rood';
        $align = in_array( $atts['align'], array( 'left', 'center', 'right' ), true ) ? $atts['align'] : 'center';

        $wrapper_styles = 'text-align:' . esc_attr( $align ) . ';';

        if ( ! empty( $atts['margin'] ) ) {
            $margin = trim( sanitize_text_field( $atts['margin'] ) );
            if ( preg_match( '/^[0-9\s%pxemrem]+$/', $margin ) ) {
                $wrapper_styles .= 'margin:' . $margin . ';';
            }
        } else {
            $margins = ( 'off' === $atts['margins'] ) ? '0' : '20px 0';
            $wrapper_styles .= 'margin:' . $margins . ';';
        }

        $button_class = ( 'wit' === $color ) ? 'KZ-KNOP-WIT' : 'KZ-KNOP-ROOD';
        $text_html    = esc_html( $text );

        $output  = '<div class="kz-button-wrapper" style="' . $wrapper_styles . '">';
        $output .= '<a class="' . esc_attr( $button_class ) . '" href="' . $link['href'] . '"' . $link['target'] . $link['rel'] . $link['title'] . '>' . $text_html . '</a>';
        $output .= '</div>';

        return $output;
    }
}
