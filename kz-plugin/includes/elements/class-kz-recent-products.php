<?php
/**
 * KZ-Winkel Producten — shortcode [kz_winkel_producten]
 * Toont een willekeurige selectie van 4 tot 6 producten die op voorraad
 * zijn, uit WooCommerce. Zelfde ontwerp (hover-ring, tekstkleur) als
 * KZ-Recente Galerijen.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Recent_Products {

    const SHORTCODE = 'kz_winkel_producten';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => __( 'KZ-Winkel Producten', 'kz-plugin' ),
                'base'        => self::SHORTCODE,
                'category'    => __( 'Kraonige Zwaone', 'kz-plugin' ),
                'icon'        => 'kz-icon-recent-products',
                'description' => __( 'Toont een willekeurige selectie producten uit de webshop die op voorraad zijn.', 'kz-plugin' ),
                'params'      => array(
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Minimaal aantal producten', 'kz-plugin' ),
                        'param_name'  => 'min_aantal',
                        'value'       => '4',
                        'description' => __( 'Ondergrens voor het willekeurige aantal producten.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Maximaal aantal producten', 'kz-plugin' ),
                        'param_name'  => 'max_aantal',
                        'value'       => '6',
                        'description' => __( 'Bovengrens voor het willekeurige aantal producten.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Aantal kolommen', 'kz-plugin' ),
                        'param_name'  => 'columns',
                        'value'       => array(
                            '2' => '2',
                            '3' => '3',
                            '4' => '4',
                            '5' => '5',
                            '6' => '6',
                        ),
                        'std'         => '5',
                        'description' => __( 'Kies het aantal kolommen voor het overzicht.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Foto tonen', 'kz-plugin' ),
                        'param_name'  => 'show_photo',
                        'value'       => array(
                            'Ja'  => 'true',
                            'Nee' => 'false',
                        ),
                        'std'         => 'true',
                        'description' => __( 'Toon de productfoto.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Naam tonen', 'kz-plugin' ),
                        'param_name'  => 'show_name',
                        'value'       => array(
                            'Ja'  => 'true',
                            'Nee' => 'false',
                        ),
                        'std'         => 'true',
                        'description' => __( 'Toon de productnaam.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Prijs tonen', 'kz-plugin' ),
                        'param_name'  => 'show_price',
                        'value'       => array(
                            'Ja'  => 'true',
                            'Nee' => 'false',
                        ),
                        'std'         => 'true',
                        'description' => __( 'Toon de productprijs.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Tekstkleur', 'kz-plugin' ),
                        'param_name'  => 'text_color',
                        'value'       => array(
                            'Wit'  => 'wit',
                            'Rood' => 'rood',
                        ),
                        'std'         => 'wit',
                        'description' => __( 'Kleur van naam en prijs onder de productfoto.', 'kz-plugin' ),
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_products' ) ) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'min_aantal' => '4',
                'max_aantal' => '6',
                'columns'    => '5',
                'show_photo' => 'true',
                'show_name'  => 'true',
                'show_price' => 'true',
                'text_color' => 'wit',
            ),
            $atts,
            self::SHORTCODE
        );

        $min_aantal = max( 1, absint( $atts['min_aantal'] ) );
        $max_aantal = max( $min_aantal, absint( $atts['max_aantal'] ) );
        $columns    = max( 1, absint( $atts['columns'] ) );
        $show_photo = 'true' === $atts['show_photo'];
        $show_name  = 'true' === $atts['show_name'];
        $show_price = 'true' === $atts['show_price'];
        $text_color = 'rood' === $atts['text_color'] ? 'rood' : 'wit';

        $aantal = wp_rand( $min_aantal, $max_aantal );

        $products = wc_get_products(
            array(
                'status'       => 'publish',
                'stock_status' => 'instock',
                'limit'        => $aantal,
                'orderby'      => 'rand',
            )
        );

        if ( empty( $products ) ) {
            return '';
        }

        wp_enqueue_style( 'kz-winkel-producten-style' );

        ob_start();
        ?>
        <div class="kz-winkel-producten kz-winkel-producten--<?php echo esc_attr( $text_color ); ?>" style="--kz-columns: <?php echo esc_attr( $columns ); ?>;">
            <?php foreach ( $products as $product ) : ?>
                <?php
                $naam      = $product->get_name();
                $link      = get_permalink( $product->get_id() );
                $thumb_url = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
                if ( ! $thumb_url ) {
                    $thumb_url = wc_placeholder_img_src( 'thumbnail' );
                }
                ?>
                <div class="kz-winkel-product-item">
                    <?php if ( $link ) : ?>
                        <a href="<?php echo esc_url( $link ); ?>">
                    <?php endif; ?>

                    <?php if ( $show_photo && $thumb_url ) : ?>
                        <div class="kz-winkel-product-foto">
                            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $naam ); ?>" loading="lazy" decoding="async" />
                        </div>
                    <?php endif; ?>

                    <?php if ( $show_name ) : ?>
                        <div class="kz-winkel-product-naam"><?php echo esc_html( $naam ); ?></div>
                    <?php endif; ?>

                    <?php if ( $show_price ) : ?>
                        <div class="kz-winkel-product-prijs"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
                    <?php endif; ?>

                    <?php if ( $link ) : ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
