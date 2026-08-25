<?php
/**
 * KZ-Tab Widget — shortcode [kz_tab_widget]
 * Overgenomen uit KZ-Kraonige Zwaone Plugin 7.1. Gedrag ongewijzigd, alleen de
 * inline <script> is verplaatst naar assets/js/kz-tab-widget.js (zie daar).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Tab_Widget {

    const SHORTCODE = 'kz_tab_widget';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => 'KZ-Tab Widget',
                'base'        => self::SHORTCODE,
                'description' => 'Een tab widget voor het tonen van uitslagen of andere data.',
                'category'    => 'Kraonige Zwaone',
                'icon'        => 'kz-icon-tab-widget',
                'params'      => array(
                    array(
                        'type'        => 'textfield',
                        'heading'     => 'Widget ID',
                        'param_name'  => 'widget_id',
                        'description' => 'Unieke ID voor deze widget (bijv. uitslagen-widget)',
                        'std'         => 'uitslagen-widget',
                    ),
                    array(
                        'type'        => 'param_group',
                        'heading'     => 'Jaren',
                        'param_name'  => 'years',
                        'description' => 'Voeg jaren toe voor de tabs',
                        'params'      => array(
                            array(
                                'type'        => 'textfield',
                                'heading'     => 'Jaar',
                                'param_name'  => 'year',
                                'description' => 'Jaar (bijv. 2025)',
                            ),
                            array(
                                'type'       => 'param_group',
                                'heading'    => 'Categorieën',
                                'param_name' => 'categories',
                                'params'     => array(
                                    array(
                                        'type'        => 'textfield',
                                        'heading'     => 'Categorie naam',
                                        'param_name'  => 'category_name',
                                        'description' => 'Naam van de categorie (bijv. Individuelen en Duo\'s)',
                                    ),
                                    array(
                                        'type'        => 'textarea',
                                        'heading'     => 'Data (JSON)',
                                        'param_name'  => 'category_data',
                                        'description' => 'JSON data voor deze categorie. Format: [{"naam":"Naam","punten":123}]',
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
        wp_enqueue_script( 'kz-tab-widget' );

        $atts = shortcode_atts(
            array(
                'widget_id' => 'uitslagen-widget',
                'years'     => '',
            ),
            $atts,
            self::SHORTCODE
        );

        $widget_id = sanitize_html_class( $atts['widget_id'] );

        $years_data = array();
        if ( ! empty( $atts['years'] ) ) {
            $years_array = vc_param_group_parse_atts( $atts['years'] );
            if ( is_array( $years_array ) ) {
                foreach ( $years_array as $year_data ) {
                    if ( isset( $year_data['year'] ) && ! empty( $year_data['year'] ) ) {
                        $year               = sanitize_text_field( $year_data['year'] );
                        $years_data[ $year ] = array();

                        if ( isset( $year_data['categories'] ) && ! empty( $year_data['categories'] ) ) {
                            $categories_array = vc_param_group_parse_atts( $year_data['categories'] );
                            if ( is_array( $categories_array ) ) {
                                foreach ( $categories_array as $category_data ) {
                                    if ( isset( $category_data['category_name'] ) && ! empty( $category_data['category_name'] ) ) {
                                        $category_name      = sanitize_text_field( $category_data['category_name'] );
                                        $category_data_json = isset( $category_data['category_data'] ) ? $category_data['category_data'] : '[]';

                                        $decoded_data = json_decode( $category_data_json, true );
                                        if ( is_array( $decoded_data ) ) {
                                            $years_data[ $year ][ $category_name ] = $decoded_data;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $year_tabs_id     = $widget_id . '-year-tabs';
        $category_tabs_id = $widget_id . '-category-tabs';
        $results_id       = $widget_id . '-results';
        $data_id          = $widget_id . '-data';

        $output  = '<div class="' . esc_attr( $widget_id ) . '">';
        $output .= '<div class="tabs" id="' . esc_attr( $year_tabs_id ) . '"></div>';
        $output .= '<div class="subtabs" id="' . esc_attr( $category_tabs_id ) . '"></div>';
        $output .= '<div class="content" id="' . esc_attr( $results_id ) . '"></div>';
        $output .= '</div>';

        $output .= '<script type="application/json" id="' . esc_attr( $data_id ) . '" data-kz-tab-widget="1">'
            . wp_json_encode( $years_data )
            . '</script>';

        return $output;
    }
}
