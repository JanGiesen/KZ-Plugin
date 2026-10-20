<?php
/**
 * KZ Vindt Grid — shortcode [kz_vindt_grid]
 * Overgenomen uit KZ Vindt 1.0. Dubbele array-keys uit de originele vc_map
 * (die door PHP toch stil overschreven werden) zijn opgeschoond; het
 * gerenderde gedrag is ongewijzigd.
 *
 * Het grid zelf tekent html(); KZ-Pagina (sjabloon Vakken) roept die ook aan,
 * zodat een grid op een KZ-Pagina precies gelijk is aan dit element.
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
                'name'        => __( 'KZ-Vindt Grid', 'kz-plugin' ),
                'base'        => self::SHORTCODE,
                'category'    => __( 'Kraonige Zwaone', 'kz-plugin' ),
                'icon'        => 'kz-icon-vindt-grid',
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
                            array(
                                'type'        => 'textfield',
                                'heading'     => __( 'Link (optioneel)', 'kz-plugin' ),
                                'param_name'  => 'link',
                                'value'       => '',
                                'description' => __( 'Maakt het vak klikbaar, bijv. een Google Maps-link naar het adres.', 'kz-plugin' ),
                            ),
                        ),
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
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

        $items = vc_param_group_parse_atts( $atts['items'] );
        $vakken = array();
        foreach ( is_array( $items ) ? $items : array() as $item ) {
            $vakken[] = array(
                'leeg'  => isset( $item['is_empty'] ) && 'yes' === $item['is_empty'],
                'datum' => isset( $item['date'] ) ? $item['date'] : '',
                'label' => isset( $item['label'] ) ? $item['label'] : '',
                'tijd'  => isset( $item['time'] ) ? $item['time'] : '',
                'link'  => isset( $item['link'] ) ? $item['link'] : '',
            );
        }

        return self::html(
            intval( $atts['columns'] ),
            ! empty( $atts['column_headers'] ) ? array_map( 'trim', explode( ',', $atts['column_headers'] ) ) : array(),
            isset( $atts['inverted'] ) && 'yes' === $atts['inverted'],
            $vakken
        );
    }

    /**
     * Het grid. Een vak is array( leeg, datum, label, tijd, link ); met een
     * link wordt het vak een <a>.
     *
     * @param int      $kolommen 2, 3 of 4.
     * @param string[] $koppen   Kolomkoppen, mag leeg.
     * @param bool     $omgekeerd Witte vakken met rode tekst (voor op rood).
     * @param array[]  $vakken
     */
    public static function html( $kolommen, array $koppen, $omgekeerd, array $vakken ) {
        wp_enqueue_style( 'kz-vindt-style' );

        $grid_class = 'kz-vindt-grid' . ( $omgekeerd ? ' kz-vindt-inverted' : '' );

        ob_start();
        ?>
        <div class="<?php echo esc_attr( $grid_class ); ?>" style="--kz-columns: <?php echo esc_attr( (int) $kolommen ); ?>;">
            <?php foreach ( $koppen as $kop ) : ?>
                <div class="kz-vindt-header"><?php echo esc_html( $kop ); ?></div>
            <?php endforeach; ?>
            <?php foreach ( $vakken as $vak ) : ?>
                <?php
                $leeg = ! empty( $vak['leeg'] );
                $link = ! $leeg && ! empty( $vak['link'] ) ? esc_url( trim( $vak['link'] ) ) : '';
                $tag  = '' !== $link ? 'a' : 'div';
                ?>
                <<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?> class="kz-vindt-item <?php echo $leeg ? 'kz-vindt-empty' : ''; ?><?php echo '' !== $link ? ' kz-vindt-link' : ''; ?>"<?php echo '' !== $link ? ' href="' . $link . '" target="_blank" rel="noopener"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
                    <?php if ( ! $leeg ) : ?>
                        <div class="kz-vindt-date"><?php echo esc_html( isset( $vak['datum'] ) ? $vak['datum'] : '' ); ?></div>
                        <?php if ( ! empty( $vak['label'] ) ) : ?>
                            <div class="kz-vindt-label"><?php echo esc_html( $vak['label'] ); ?></div>
                        <?php endif; ?>
                        <div class="kz-vindt-time"><?php echo esc_html( isset( $vak['tijd'] ) ? $vak['tijd'] : '' ); ?></div>
                    <?php endif; ?>
                </<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
