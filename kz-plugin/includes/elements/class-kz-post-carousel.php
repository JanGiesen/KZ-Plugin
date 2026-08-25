<?php
/**
 * KZ-PostCarrousel — shortcode [kz_post_doorscroll]
 * Overgenomen uit KZ-Kraonige Zwaone Plugin 7.1, gedrag ongewijzigd.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Post_Carousel {

    const SHORTCODE = 'kz_post_doorscroll';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => 'KZ-PostCarrousel',
                'base'        => self::SHORTCODE,
                'description' => 'Carrousel voor Posts',
                'category'    => 'Kraonige Zwaone',
                'icon'        => 'kz-icon-carousel',
                'params'      => array(
                    array(
                        'type'        => 'dropdown',
                        'heading'     => 'Uitgelichte afbeelding toevoegen',
                        'param_name'  => 'featured_image',
                        'value'       => array(
                            'Ja'  => 'true',
                            'Nee' => 'false',
                        ),
                        'description' => 'Voegt automatisch de uitgelichte afbeelding van deze pagina/bericht toe als eerste afbeelding',
                        'std'         => 'true',
                    ),
                    array(
                        'type'        => 'attach_images',
                        'heading'     => 'Afbeeldingen',
                        'param_name'  => 'images',
                        'description' => 'Selecteer meerdere afbeeldingen voor de carrousel (worden toegevoegd na de uitgelichte afbeelding)',
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => 'Maximale breedte (px)',
                        'param_name'  => 'max_width',
                        'description' => 'Stel de maximale breedte van alle afbeeldingen in (bijv. 400)',
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
        wp_enqueue_style( 'swiper' );
        wp_enqueue_script( 'swiper' );
        wp_enqueue_script( 'kz-carousel' );

        $atts = shortcode_atts(
            array(
                'images'         => '',
                'max_width'      => '',
                'featured_image' => 'true',
            ),
            $atts,
            self::SHORTCODE
        );

        $images         = sanitize_text_field( $atts['images'] );
        $max_width      = absint( $atts['max_width'] );
        $featured_image = sanitize_text_field( $atts['featured_image'] );

        $uid    = uniqid( 'kzpd_' );
        $output = '<div class="kz-post-doorscroll-swiper-container" id="' . esc_attr( $uid ) . '">';
        $output .= '<div class="swiper">';
        $output .= '<div class="swiper-wrapper">';

        $all_images = array();

        if ( 'true' === $featured_image ) {
            $featured_image_id = get_post_thumbnail_id();
            if ( $featured_image_id ) {
                $all_images[] = $featured_image_id;
            }
        }

        if ( $images ) {
            $image_ids = array_map( 'absint', explode( ',', $images ) );
            foreach ( $image_ids as $id ) {
                if ( ! in_array( $id, $all_images, true ) ) {
                    $all_images[] = $id;
                }
            }
        }

        foreach ( $all_images as $id ) {
            $alt_text = get_post_meta( $id, '_wp_attachment_image_alt', true );
            if ( empty( $alt_text ) ) {
                $alt_text = get_the_title( $id );
            }
            $alt_text = sanitize_text_field( $alt_text );

            $attributes = array(
                'loading'  => 'lazy',
                'decoding' => 'async',
                'alt'      => $alt_text,
            );

            if ( ! empty( $max_width ) ) {
                $attributes['style'] = 'max-width:' . esc_attr( $max_width ) . 'px;';
            }

            $img_html = wp_get_attachment_image( $id, 'full', false, $attributes );
            if ( $img_html ) {
                $output .= '<div class="swiper-slide">' . $img_html . '</div>';
            }
        }

        $output .= '</div>';
        $output .= '</div>';
        $output .= '<div class="swiper-button-prev mobile-hidden">'
            . '<svg viewBox="0 0 24 24"><polyline points="15 5 8 12 15 19" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            . '</div>';
        $output .= '<div class="swiper-button-next mobile-hidden">'
            . '<svg viewBox="0 0 24 24"><polyline points="9 5 16 12 9 19" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            . '</div>';
        $output .= '</div>';

        $output .= '<div class="mobile-navigation">';
        $output .= '<div class="swiper-button-prev mobile-only">'
            . '<svg viewBox="0 0 24 24"><polyline points="15 5 8 12 15 19" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            . '</div>';
        $output .= '<div class="swiper-button-next mobile-only">'
            . '<svg viewBox="0 0 24 24"><polyline points="9 5 16 12 9 19" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            . '</div>';
        $output .= '</div>';

        return $output;
    }
}
