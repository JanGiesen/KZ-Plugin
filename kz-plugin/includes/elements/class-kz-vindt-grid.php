<?php
/**
 * KZ Vindt Grid — shortcode [kz_vindt_grid]
 * Overgenomen uit KZ Vindt 1.0. Dubbele array-keys uit de originele vc_map
 * (die door PHP toch stil overschreven werden) zijn opgeschoond; het
 * gerenderde gedrag is ongewijzigd.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Vindt_Grid {

    const SHORTCODE = 'kz_vindt_grid';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => __( 'KZ Vindt Grid', 'kz-plugin' ),
                'base'        => self::SHORTCODE,
                'category'    => __( 'Kraonige Zwaone', 'kz-plugin' ),
                'description' => __( 'Toont een grid met datums en tijden.', 'kz-plugin' ),
                'params'      => array(
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Aantal Kolommen', 'kz-plugin' ),
                        'param_name'  => 'columns',
                        'value'       => array(
                            '2' => '2',
                            '3' => '3',
                            '4' => '4',
                        ),
                        'std'         => '3',
                        'description' => __( 'Kies het aantal kolommen voor het grid.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Kolom Koppen', 'kz-plugin' ),
                        'param_name'  => 'column_headers',
                        'value'       => '',
                        'description' => __( 'Voer de titels in gescheiden door een komma (bijv. VRIJDAG, ZATERDAG, ZONDAG).', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'checkbox',
                        'heading'     => __( 'Kleuren Omdraaien', 'kz-plugin' ),
                        'param_name'  => 'inverted',
                        'value'       => array( __( 'Ja', 'kz-plugin' ) => 'yes' ),
                        'description' => __( 'Vink dit aan voor witte blokken met rode tekst (voor op een rode achtergrond).', 'kz-plugin' ),
                    ),
                    array(
                        'type'       => 'param_group',
                        'heading'    => __( 'Items', 'kz-plugin' ),
                        'param_name' => 'items',
                        'params'     => array(
                            array(
                                'type'       => 'checkbox',
                                'heading'    => __( 'Is dit een leeg vak?', 'kz-plugin' ),
                                'param_name' => 'is_empty',
                                'value'      => array( __( 'Ja', 'kz-plugin' ) => 'yes' ),
                            ),
                            array(
                                'type'       => 'textfield',
                                'heading'    => __( 'Datum (Dag/Maand)', 'kz-plugin' ),
                                'param_name' => 'date',
                                'value'      => '31/01',
                            ),
                            array(
                                'type'       => 'textfield',
                                'heading'    => __( 'Tijd', 'kz-plugin' ),
                                'param_name' => 'time',
                                'value'      => '12:00 - 19:00',
                            ),
                            array(
                                'type'        => 'textfield',
                                'heading'     => __( 'Label (optioneel)', 'kz-plugin' ),
                                'param_name'  => 'label',
                                'value'       => '',
                                'description' => __( 'Bijv. KOOPAVOND', 'kz-plugin' ),
                            ),
                        ),
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
        wp_enqueue_style( 'kz-vindt-style' );

        $atts = shortcode_atts(
            array(
                'columns'        => '3',
                'column_headers' => '',
                'inverted'       => '',
                'items'          => '',
            ),
            $atts,
            self::SHORTCODE
        );

        $items      = vc_param_group_parse_atts( $atts['items'] );
        $columns    = intval( $atts['columns'] );
        $headers    = ! empty( $atts['column_headers'] ) ? array_map( 'trim', explode( ',', $atts['column_headers'] ) ) : array();
        $inverted   = isset( $atts['inverted'] ) && 'yes' === $atts['inverted'];
        $grid_class = 'kz-vindt-grid' . ( $inverted ? ' kz-vindt-inverted' : '' );

        ob_start();
        ?>
        <div class="<?php echo esc_attr( $grid_class ); ?>" style="--kz-columns: <?php echo esc_attr( $columns ); ?>;">
            <?php if ( ! empty( $headers ) ) : ?>
                <?php foreach ( $headers as $header ) : ?>
                    <div class="kz-vindt-header"><?php echo esc_html( $header ); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ( ! empty( $items ) ) : ?>
                <?php foreach ( $items as $item ) : ?>
                    <?php
                    $is_empty = isset( $item['is_empty'] ) && 'yes' === $item['is_empty'];
                    $date     = isset( $item['date'] ) ? $item['date'] : '';
                    $time     = isset( $item['time'] ) ? $item['time'] : '';
                    $label    = isset( $item['label'] ) ? $item['label'] : '';
                    ?>
                    <div class="kz-vindt-item <?php echo $is_empty ? 'kz-vindt-empty' : ''; ?>">
                        <?php if ( ! $is_empty ) : ?>
                            <div class="kz-vindt-date"><?php echo esc_html( $date ); ?></div>
                            <?php if ( ! empty( $label ) ) : ?>
                                <div class="kz-vindt-label"><?php echo esc_html( $label ); ?></div>
                            <?php endif; ?>
                            <div class="kz-vindt-time"><?php echo esc_html( $time ); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
