<?php
/**
 * KZ-Recente Galerijen — shortcode [kz_recente_galerijen]
 * Toont een overzicht van de meest recente NextGEN Gallery-galerijen.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Recent_Galleries {

    const SHORTCODE = 'kz_recente_galerijen';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => __( 'KZ-Recente Galerijen', 'kz-plugin' ),
                'base'        => self::SHORTCODE,
                'category'    => __( 'Kraonige Zwaone', 'kz-plugin' ),
                'icon'        => 'kz-icon-recent-galleries',
                'description' => __( 'Toont een overzicht van de meest recente NextGEN Gallery-galerijen.', 'kz-plugin' ),
                'params'      => array(
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Aantal galerijen', 'kz-plugin' ),
                        'param_name'  => 'aantal',
                        'value'       => '5',
                        'description' => __( 'Hoeveel van de meest recente galerijen moeten getoond worden.', 'kz-plugin' ),
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
                        ),
                        'std'         => '5',
                        'description' => __( 'Kies het aantal kolommen voor het overzicht.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Titel tonen', 'kz-plugin' ),
                        'param_name'  => 'show_title',
                        'value'       => array(
                            'Ja'  => 'true',
                            'Nee' => 'false',
                        ),
                        'std'         => 'true',
                        'description' => __( 'Toon de galerijnaam onder de voorbeeldafbeelding.', 'kz-plugin' ),
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'aantal'     => '5',
                'columns'    => '5',
                'show_title' => 'true',
            ),
            $atts,
            self::SHORTCODE
        );

        if ( ! class_exists( 'nggdb' ) ) {
            return '';
        }

        $aantal     = max( 1, absint( $atts['aantal'] ) );
        $columns    = max( 1, absint( $atts['columns'] ) );
        $show_title = 'true' === $atts['show_title'];

        $galleries = nggdb::find_all_galleries();
        if ( empty( $galleries ) ) {
            return '';
        }

        // NextGEN Gallery slaat geen aanmaakdatum per galerij op: het hoogste
        // gallery-ID is de meest recent aangemaakte galerij.
        usort(
            $galleries,
            function ( $a, $b ) {
                return $b->gid - $a->gid;
            }
        );

        $galleries = array_slice( $galleries, 0, $aantal );

        wp_enqueue_style( 'kz-recente-galerijen-style' );

        ob_start();
        ?>
        <div class="kz-recente-galerijen" style="--kz-columns: <?php echo esc_attr( $columns ); ?>;">
            <?php foreach ( $galleries as $gallery ) : ?>
                <?php
                $titel = ! empty( $gallery->title ) ? $gallery->title : $gallery->name;

                $link = '';
                if ( ! empty( $gallery->pageid ) ) {
                    $link = get_permalink( $gallery->pageid );
                }

                $thumb_url = '';
                if ( ! empty( $gallery->previewpic ) ) {
                    $preview_image = nggdb::find_image( $gallery->previewpic );
                    if ( $preview_image && ! empty( $preview_image->thumbURL ) ) {
                        $thumb_url = $preview_image->thumbURL;
                    }
                }
                ?>
                <div class="kz-recente-galerij-item">
                    <?php if ( $link ) : ?>
                        <a href="<?php echo esc_url( $link ); ?>">
                    <?php endif; ?>

                    <?php if ( $thumb_url ) : ?>
                        <img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $titel ); ?>" loading="lazy" decoding="async" />
                    <?php endif; ?>

                    <?php if ( $show_title ) : ?>
                        <div class="kz-recente-galerij-titel"><?php echo esc_html( $titel ); ?></div>
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
