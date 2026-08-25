<?php
/**
 * KZ-Hover Afbeelding — shortcode [kz_hoverafbeelding]
 * Overgenomen uit KZ-Kraonige Zwaone Plugin 7.1, gedrag ongewijzigd.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Hover_Image {

    const SHORTCODE = 'kz_hoverafbeelding';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => 'KZ-Hover Afbeelding',
                'base'        => self::SHORTCODE,
                'description' => 'Een afbeelding die een hover-effect heeft.',
                'category'    => 'Kraonige Zwaone',
                'icon'        => 'kz-icon-hover-image',
                'params'      => array(
                    array(
                        'type'        => 'attach_image',
                        'heading'     => 'Afbeelding',
                        'param_name'  => 'image',
                        'description' => 'Selecteer een afbeelding voor de hover-effect.',
                    ),
                    array(
                        'type'        => 'vc_link',
                        'heading'     => 'Link',
                        'param_name'  => 'link',
                        'description' => 'Kies of voer de link in (optioneel)',
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => 'Breedte (px)',
                        'param_name'  => 'width',
                        'description' => 'Stel de breedte van de afbeelding in (bijv. 328)',
                        'std'         => '328',
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
                'image'   => '',
                'link'    => '',
                'width'   => '328',
                'align'   => 'center',
                'margins' => 'on',
            ),
            $atts,
            self::SHORTCODE
        );

        $image_id = absint( $atts['image'] );
        $width    = absint( $atts['width'] );
        $align    = in_array( $atts['align'], array( 'left', 'center', 'right' ), true ) ? $atts['align'] : 'center';
        $margins  = ( 'off' === $atts['margins'] ) ? 'off' : 'on';

        $link = KZ_Link_Helper::parse( $atts['link'] );

        $image_url = wp_get_attachment_image_url( $image_id, 'full' );
        $image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
        if ( empty( $image_alt ) ) {
            $image_alt = get_the_title( $image_id );
        }
        $image_alt = sanitize_text_field( $image_alt );

        $wrapper_styles  = 'text-align:' . esc_attr( $align ) . ';';
        $wrapper_styles .= ( 'off' === $margins ) ? 'margin:0;' : 'margin:20px auto;';

        $hover_styles = 'width:' . esc_attr( $width ) . 'px; margin: 0 auto;';

        $output  = '<div class="kz-hover-image-wrapper" style="' . esc_attr( $wrapper_styles ) . '">';
        $output .= '<div class="KZ-HOVERAFBEELDING" style="' . esc_attr( $hover_styles ) . '">';

        $has_link = ! empty( $link['href'] );

        if ( $has_link ) {
            $output .= '<a href="' . $link['href'] . '"' . $link['target'] . $link['rel'] . $link['title'] . '>';
        }

        if ( $image_url ) {
            $output .= '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $image_alt ) . '" style="width: 100%; height: auto;">';
        }

        if ( $has_link ) {
            $output .= '</a>';
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }
}
