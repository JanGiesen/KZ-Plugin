<?php
/**
 * KZ-Recente Galerijen — shortcode [kz_recente_galerijen]
 * Toont een overzicht van de meest recente NextGEN Gallery-galerijen.
 *
 * Leest rechtstreeks uit de ngg_gallery/ngg_pictures-tabellen: NextGEN Gallery
 * (getest tegen 4.4.0) heeft geen publieke API-methode om "alle galerijen"
 * op te halen. Elke ngg_pictures-rij is gekoppeld aan een gewone WordPress-
 * attachment (post_id), dus de voorbeeldafbeelding gaat via de standaard
 * wp_get_attachment_image_url() in plaats van zelf bestandspaden te bouwen.
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
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Tekstkleur', 'kz-plugin' ),
                        'param_name'  => 'text_color',
                        'value'       => array(
                            'Wit'  => 'wit',
                            'Rood' => 'rood',
                        ),
                        'std'         => 'wit',
                        'description' => __( 'Kleur van de galerijtitel onder de voorbeeldafbeelding.', 'kz-plugin' ),
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
        global $wpdb;

        $atts = shortcode_atts(
            array(
                'aantal'     => '5',
                'columns'    => '5',
                'show_title' => 'true',
                'text_color' => 'wit',
            ),
            $atts,
            self::SHORTCODE
        );

        $ngg_gallery_table  = $wpdb->prefix . 'ngg_gallery';
        $ngg_pictures_table = $wpdb->prefix . 'ngg_pictures';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ngg_gallery_table}'" ) !== $ngg_gallery_table ) {
            return '';
        }

        $aantal     = max( 1, absint( $atts['aantal'] ) );
        $columns    = max( 1, absint( $atts['columns'] ) );
        $show_title = 'true' === $atts['show_title'];
        $text_color = 'rood' === $atts['text_color'] ? 'rood' : 'wit';

        $galleries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT gid, name, slug, title, path, previewpic, pageid, extras_post_id
                 FROM {$ngg_gallery_table}
                 ORDER BY date_created DESC, gid DESC
                 LIMIT %d",
                $aantal
            )
        );

        if ( empty( $galleries ) ) {
            return '';
        }

        wp_enqueue_style( 'kz-recente-galerijen-style' );

        ob_start();
        ?>
        <div class="kz-recente-galerijen kz-recente-galerijen--<?php echo esc_attr( $text_color ); ?>" style="--kz-columns: <?php echo esc_attr( $columns ); ?>;">
            <?php foreach ( $galleries as $gallery ) : ?>
                <?php
                $titel = ! empty( $gallery->title ) ? $gallery->title : $gallery->name;
                $titel = self::strip_leading_date( $titel );

                $link = self::get_gallery_album_link( $gallery->gid, $gallery->slug, $wpdb );
                if ( ! $link ) {
                    if ( ! empty( $gallery->extras_post_id ) ) {
                        $link = get_permalink( $gallery->extras_post_id );
                    } elseif ( ! empty( $gallery->pageid ) ) {
                        $link = get_permalink( $gallery->pageid );
                    }
                }

                $thumb_url = self::get_gallery_thumb_url( $gallery, $ngg_pictures_table, $wpdb );
                ?>
                <div class="kz-recente-galerij-item">
                    <?php if ( $link ) : ?>
                        <a href="<?php echo esc_url( $link ); ?>">
                    <?php endif; ?>

                    <?php if ( $thumb_url ) : ?>
                        <div class="kz-recente-galerij-foto">
                            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $titel ); ?>" loading="lazy" decoding="async" />
                        </div>
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

    /**
     * Bepaalt de voorbeeldafbeelding-URL voor een galerij: eerst de expliciet
     * ingestelde previewpic, anders de eerste (niet-uitgesloten) foto in de
     * galerij. Gaat via de gekoppelde WP-attachment (post_id); alleen als die
     * ontbreekt valt het terug op het klassieke NextGEN-bestandspad.
     */
    private static function get_gallery_thumb_url( $gallery, $ngg_pictures_table, $wpdb ) {
        $picture = null;

        if ( ! empty( $gallery->previewpic ) ) {
            $picture = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT post_id, filename FROM {$ngg_pictures_table} WHERE pid = %d",
                    $gallery->previewpic
                )
            );
        }

        if ( ! $picture ) {
            $picture = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT post_id, filename FROM {$ngg_pictures_table}
                     WHERE galleryid = %d AND exclude = 0
                     ORDER BY sortorder ASC
                     LIMIT 1",
                    $gallery->gid
                )
            );
        }

        if ( ! $picture ) {
            return '';
        }

        if ( ! empty( $picture->post_id ) ) {
            $url = wp_get_attachment_image_url( $picture->post_id, 'thumbnail' );
            if ( $url ) {
                return $url;
            }
        }

        if ( ! empty( $gallery->path ) && ! empty( $picture->filename ) ) {
            // De path-kolom bevat het volledige relatieve pad vanaf de site-root
            // (bijv. "/wp-content/gallery/appel/"), dus niet nog eens onder
            // wp-content/gallery/ hangen.
            $relative_path = trim( $gallery->path, '/' );
            return home_url( '/' . $relative_path . '/thumbs/thumbs-' . $picture->filename );
        }

        return '';
    }

    /**
     * Bouwt de front-end galerij-URL op basis van het NextGEN-album waarin de
     * galerij zit: /fotos/{album}/ngggaleries/{album}/{galerij-slug}/.
     *
     * NextGEN Gallery/Pro genereert deze geneste URL zelf via een intern
     * rewrite-mechanisme dat niet via een standaard get_permalink() te
     * benaderen is; de albumkoppeling (welke galerijen bij welk album horen)
     * staat in de sortorder-kolom van ngg_album, base64-gecodeerd als JSON-
     * array van gallery-ID's (geen PHP serialize()).
     */
    private static function get_gallery_album_link( $gid, $slug, $wpdb ) {
        if ( empty( $slug ) ) {
            return '';
        }

        $ngg_album_table = $wpdb->prefix . 'ngg_album';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ngg_album_table}'" ) !== $ngg_album_table ) {
            return '';
        }

        $albums = $wpdb->get_results( "SELECT slug, sortorder FROM {$ngg_album_table}" );

        foreach ( $albums as $album ) {
            if ( empty( $album->sortorder ) ) {
                continue;
            }

            $gallery_ids = json_decode( base64_decode( $album->sortorder ), true );
            if ( ! is_array( $gallery_ids ) ) {
                continue;
            }

            if ( in_array( (string) $gid, array_map( 'strval', $gallery_ids ), true ) ) {
                return home_url( '/fotos/' . $album->slug . '/ngggaleries/' . $album->slug . '/' . $slug . '/' );
            }
        }

        return '';
    }

    /**
     * Verbergt de interne YYYY-MM-DD-datumprefix uit galerijnamen
     * (afspraak: elke galerij heet "YYYY-MM-DD Omschrijving").
     */
    private static function strip_leading_date( $titel ) {
        return preg_replace( '/^\d{4}-\d{2}-\d{2}\s+/', '', $titel );
    }
}
