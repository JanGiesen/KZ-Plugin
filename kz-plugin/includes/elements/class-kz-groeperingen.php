<?php
/**
 * KZ-Groeperingen — shortcode [kz_groeperingen]
 * Grid van de groeperingen (post type "groeperingen") met ronde foto's, op de
 * server getekend. Vervangt de WPBakery Post Grid, die zijn items via
 * admin-ajax ophaalt en voor gasten leeg bleef zodra de nonce in de
 * LiteSpeed-cache verlopen was. Zie KZ_Berichten_Grid.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Groeperingen {

    const SHORTCODE = 'kz_groeperingen';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => __( 'KZ-Groeperingen', 'kz-plugin' ),
                'base'        => self::SHORTCODE,
                'category'    => __( 'Kraonige Zwaone', 'kz-plugin' ),
                'icon'        => 'kz-icon-groeperingen',
                'description' => __( 'Grid van de groeperingen met ronde foto\'s, zonder AJAX.', 'kz-plugin' ),
                'params'      => array_merge(
                    array(
                        array(
                            'type'        => 'textfield',
                            'heading'     => __( 'Aantal', 'kz-plugin' ),
                            'param_name'  => 'aantal',
                            'value'       => '',
                            'description' => __( 'Leeg = alle groeperingen.', 'kz-plugin' ),
                        ),
                        KZ_Berichten_Grid::vc_veld_sortering( 'datum' ),
                        KZ_Berichten_Grid::vc_veld_ja_nee( 'toon_titel', __( 'Naam tonen', 'kz-plugin' ), 'ja', __( 'De naam onder de ronde foto.', 'kz-plugin' ) ),
                        KZ_Berichten_Grid::vc_veld_kleur( 'rood' ),
                        array(
                            'type'        => 'dropdown',
                            'heading'     => __( 'Cirkel', 'kz-plugin' ),
                            'param_name'  => 'cirkel',
                            'value'       => array(
                                __( 'Rood', 'kz-plugin' )             => 'rood',
                                __( 'Wit', 'kz-plugin' )              => 'wit',
                                __( 'Geen achtergrond', 'kz-plugin' ) => 'geen',
                            ),
                            'std'         => 'rood',
                            'description' => __( 'Kleur van de cirkel; alleen te zien achter een afbeelding met een doorzichtige achtergrond.', 'kz-plugin' ),
                        ),
                        array(
                            'type'        => 'dropdown',
                            'heading'     => __( 'Rand om de cirkel', 'kz-plugin' ),
                            'param_name'  => 'rand',
                            'value'       => array(
                                __( 'Geen', 'kz-plugin' ) => 'geen',
                                __( 'Wit', 'kz-plugin' )  => 'wit',
                                __( 'Rood', 'kz-plugin' ) => 'rood',
                            ),
                            'std'         => 'geen',
                            'description' => __( 'Een dunne rand; wit is bedoeld voor een rode achtergrond.', 'kz-plugin' ),
                        ),
                        array(
                            'type'        => 'textfield',
                            'heading'     => __( 'Ruimte tussen de rijen (px)', 'kz-plugin' ),
                            'param_name'  => 'rij_ruimte',
                            'value'       => '40',
                        ),
                        array(
                            'type'        => 'textfield',
                            'heading'     => __( 'Post type', 'kz-plugin' ),
                            'param_name'  => 'post_type',
                            'value'       => 'groeperingen',
                            'description' => __( 'Laat staan, tenzij het post type anders heet.', 'kz-plugin' ),
                        ),
                    ),
                    KZ_Berichten_Grid::vc_velden_opmaak( 6, 4, 3, 15 )
                ),
            )
        );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'aantal'          => '',
                'sortering'       => 'datum',
                'toon_titel'      => 'ja',
                'tekstkleur'      => 'rood',
                'cirkel'          => 'rood',
                'rand'            => 'geen',
                'rij_ruimte'      => '40',
                'post_type'       => 'groeperingen',
                'kolommen'        => '6',
                'kolommen_tablet' => '4',
                'kolommen_mobiel' => '3',
                'ruimte'          => '15',
                'el_class'        => '',
                'css'             => '',
            ),
            $atts,
            self::SHORTCODE
        );

        $post_type = sanitize_key( $atts['post_type'] );
        if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
            return '';
        }

        KZ_Berichten_Grid::cache_tag( $post_type );

        $aantal = absint( $atts['aantal'] );
        $query  = new WP_Query(
            array_merge(
                array(
                    'post_type'           => $post_type,
                    'post_status'         => 'publish',
                    'posts_per_page'      => $aantal > 0 ? $aantal : 100,
                    'no_found_rows'       => true,
                    'ignore_sticky_posts' => true,
                ),
                KZ_Berichten_Grid::sortering( $atts['sortering'] )
            )
        );

        if ( ! $query->have_posts() ) {
            return '';
        }

        wp_enqueue_style( 'kz-berichten-grid-style' );
        wp_enqueue_style( 'kz-evenement-info-fonts' ); // Work Sans voor de namen.

        $kleur  = 'wit' === $atts['tekstkleur'] ? 'wit' : 'rood';
        $cirkel = in_array( $atts['cirkel'], array( 'wit', 'rood', 'geen' ), true ) ? $atts['cirkel'] : 'rood';
        $rand   = in_array( $atts['rand'], array( 'wit', 'rood' ), true ) ? $atts['rand'] : 'geen';
        $titel  = KZ_Berichten_Grid::ja( $atts['toon_titel'] );

        $klassen = KZ_Berichten_Grid::wrapper_klassen(
            array( 'kz-grid', 'kz-groeperingen', 'kz-grid--' . $kleur, 'kz-groeperingen--cirkel-' . $cirkel, 'kz-groeperingen--rand-' . $rand ),
            $atts['el_class'],
            $atts['css']
        );
        $stijl = KZ_Berichten_Grid::stijl(
            KZ_Berichten_Grid::kolommen( $atts['kolommen'], 6 ),
            KZ_Berichten_Grid::kolommen( $atts['kolommen_tablet'], 4 ),
            KZ_Berichten_Grid::kolommen( $atts['kolommen_mobiel'], 3 ),
            min( 100, absint( $atts['ruimte'] ) )
        );

        $stijl .= sprintf( '--kz-rij-ruimte:%dpx;', min( 200, absint( $atts['rij_ruimte'] ) ) );

        ob_start();
        ?>
        <div class="<?php echo esc_attr( $klassen ); ?>" style="<?php echo esc_attr( $stijl ); ?>">
            <?php foreach ( $query->posts as $post ) : ?>
                <?php
                $naam = get_the_title( $post );
                $foto = KZ_Berichten_Grid::foto_url( $post, 'medium' );
                ?>
                <div class="kz-grid__item kz-groepering">
                    <a href="<?php echo esc_url( get_permalink( $post ) ); ?>" title="<?php echo esc_attr( wp_strip_all_tags( $naam ) ); ?>">
                        <span class="kz-groepering__foto">
                            <?php if ( $foto ) : ?>
                                <img src="<?php echo esc_url( $foto ); ?>" alt="<?php echo esc_attr( KZ_Berichten_Grid::foto_alt( $post ) ); ?>" loading="lazy" decoding="async" />
                            <?php endif; ?>
                        </span>
                        <?php if ( $titel ) : ?>
                            <span class="kz-grid__titel"><?php echo esc_html( wp_strip_all_tags( $naam ) ); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
