<?php
/**
 * KZ-Event Blok — shortcode [kz_eventhome_blok]
 * Overgenomen uit KZ-Kraonige Zwaone Plugin 7.1, gedrag ongewijzigd.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Eventhome_Block {

    const SHORTCODE = 'kz_eventhome_blok';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => 'KZ-Event Blok',
                'base'        => self::SHORTCODE,
                'description' => 'Eventblok met datum en titel',
                'category'    => 'Kraonige Zwaone',
                'icon'        => 'kz-icon-event',
                'params'      => array(
                    array(
                        'type'        => 'textfield',
                        'heading'     => 'Datum',
                        'param_name'  => 'date',
                        'description' => 'Formaat: twee cijfers en drie letters (bijv. 15 NOV). Wordt automatisch geformatteerd.',
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => 'Titel',
                        'param_name'  => 'title',
                        'description' => 'Titel van het event (wordt automatisch hoofdletters)',
                    ),
                    array(
                        'type'        => 'vc_link',
                        'heading'     => 'Link',
                        'param_name'  => 'link',
                        'description' => 'Kies of voer de link in (wordt automatisch kleine letters)',
                    ),
                    array(
                        'type'        => 'checkbox',
                        'heading'     => 'Evenement in het verleden',
                        'param_name'  => 'past',
                        'value'       => array( 'Ja' => 'true' ),
                        'description' => 'Gebruik de lichte kleurenvariant voor verlopen evenementen',
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => 'Onderste marge (px)',
                        'param_name'  => 'margin_bottom',
                        'std'         => '10',
                        'description' => 'Stel de marge onder het blok in (standaard 10px)',
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'date'          => '',
                'title'         => '',
                'link'          => '',
                'past'          => 'false',
                'margin_bottom' => '10',
            ),
            $atts,
            self::SHORTCODE
        );

        $date_raw  = sanitize_text_field( $atts['date'] );
        $title_raw = sanitize_text_field( $atts['title'] );

        if ( function_exists( 'mb_strtoupper' ) ) {
            $title_text = mb_strtoupper( $title_raw, 'UTF-8' );
        } else {
            $title_text = strtoupper( $title_raw );
        }

        $date_text = self::normalize_event_date( $date_raw );

        $link = KZ_Link_Helper::parse( $atts['link'] );

        $date_html        = esc_html( $date_text );
        $title_html_text  = esc_html( $title_text );

        $classes = 'KZ-EVENTHOME-BLOK';
        if ( 'true' === $atts['past'] ) {
            $classes .= ' KZ-EVENTHOME--licht';
        }

        $margin_bottom_px = absint( $atts['margin_bottom'] );
        if ( $margin_bottom_px <= 0 && '0' !== $atts['margin_bottom'] ) {
            $margin_bottom_px = 10;
        }
        $style_attr = ' style="margin-bottom:' . esc_attr( $margin_bottom_px ) . 'px;"';

        if ( ! empty( $link['href'] ) ) {
            $output = '<a class="' . esc_attr( $classes ) . '" href="' . $link['href'] . '"' . $link['target'] . $link['rel'] . $link['title'] . $style_attr . '>';
        } else {
            $output = '<div class="' . esc_attr( $classes ) . '"' . $style_attr . '>';
        }
        $output .= '<div class="KZ-EVENTHOME-DATUM">' . $date_html . '</div>';
        $output .= '<div class="KZ-EVENTHOME-TITEL">' . $title_html_text . '</div>';
        $output .= ( ! empty( $link['href'] ) ) ? '</a>' : '</div>';

        return $output;
    }

    private static function normalize_event_date( $input ) {
        $value = trim( (string) $input );
        if ( '' === $value ) {
            return '';
        }
        $digits  = preg_replace( '/\D+/', '', $value );
        $letters = preg_replace( '/[^a-zA-Z]+/', '', $value );

        $day = substr( $digits, 0, 2 );
        if ( '' !== $day ) {
            $day = str_pad( $day, 2, '0', STR_PAD_LEFT );
        }

        if ( function_exists( 'mb_strtoupper' ) ) {
            $month = mb_strtoupper( mb_substr( $letters, 0, 3, 'UTF-8' ), 'UTF-8' );
        } else {
            $month = strtoupper( substr( $letters, 0, 3 ) );
        }

        return trim( $day . ' ' . $month );
    }
}
