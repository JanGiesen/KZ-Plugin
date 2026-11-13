<?php
/**
 * KZ-Recente Albums — shortcode [kz_recente_albums]
 * Toont een overzicht van de meest recente NextGEN Gallery-albums, in
 * hetzelfde ontwerp als KZ-Recente Galerijen (zelfde CSS).
 *
 * ngg_album heeft geen datumkolom. "Meest recent" is daarom standaard het
 * album met de nieuwste galerij erin (date_created van ngg_gallery, ook via
 * sub-albums); met sortering="aangemaakt" is het het laatst aangemaakte
 * album (hoogste id).
 *
 * De sortorder-kolom van ngg_album is base64-gecodeerde JSON: een lijst van
 * galerij-id's ("12") en sub-album-id's met een a ervoor ("a3").
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Recent_Albums {

    const SHORTCODE = 'kz_recente_albums';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => __( 'KZ-Recente Albums', 'kz-plugin' ),
                'base'        => self::SHORTCODE,
                'category'    => __( 'Kraonige Zwaone', 'kz-plugin' ),
                'icon'        => 'kz-icon-recent-galleries',
                'description' => __( 'Toont een overzicht van de meest recente NextGEN Gallery-albums.', 'kz-plugin' ),
                'params'      => array(
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Aantal albums', 'kz-plugin' ),
                        'param_name'  => 'aantal',
                        'value'       => '5',
                        'description' => __( 'Hoeveel van de meest recente albums moeten getoond worden.', 'kz-plugin' ),
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
                        'heading'     => __( 'Sortering', 'kz-plugin' ),
                        'param_name'  => 'sortering',
                        'value'       => array(
                            'Nieuwste galerij in het album' => 'galerij',
                            'Laatst aangemaakte album'      => 'aangemaakt',
                        ),
                        'std'         => 'galerij',
                        'description' => __( 'Wat telt als "recent": het album met de nieuwste galerij, of het album dat het laatst is aangemaakt.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Albums overslaan', 'kz-plugin' ),
                        'param_name'  => 'uitsluiten',
                        'value'       => '',
                        'description' => __( 'Komma-gescheiden slugs of id\'s van albums die nooit getoond worden (bijv. een overkoepelend hoofdalbum).', 'kz-plugin' ),
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
                        'description' => __( 'Toon de albumnaam onder de voorbeeldafbeelding.', 'kz-plugin' ),
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
                        'description' => __( 'Kleur van de albumtitel onder de voorbeeldafbeelding.', 'kz-plugin' ),
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
                'sortering'  => 'galerij',
                'uitsluiten' => '',
                'show_title' => 'true',
                'text_color' => 'wit',
            ),
            $atts,
            self::SHORTCODE
        );

        $ngg_album_table    = $wpdb->prefix . 'ngg_album';
        $ngg_gallery_table  = $wpdb->prefix . 'ngg_gallery';
        $ngg_pictures_table = $wpdb->prefix . 'ngg_pictures';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ngg_album_table}'" ) !== $ngg_album_table ) {
            return '';
        }

        $aantal     = max( 1, absint( $atts['aantal'] ) );
        $columns    = max( 1, absint( $atts['columns'] ) );
        $sortering  = 'aangemaakt' === $atts['sortering'] ? 'aangemaakt' : 'galerij';
        $show_title = 'true' === $atts['show_title'];
        $text_color = 'rood' === $atts['text_color'] ? 'rood' : 'wit';
        $uitsluiten = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $atts['uitsluiten'] ) ) ), 'strlen' );

        $rows = $wpdb->get_results( "SELECT id, name, slug, previewpic, sortorder, pageid FROM {$ngg_album_table}" );
        if ( empty( $rows ) ) {
            return '';
        }

        // Albums op id, met hun directe galerijen en sub-albums.
        $albums = array();
        foreach ( $rows as $row ) {
            $row->gallery_ids  = array();
            $row->subalbum_ids = array();

            $items = ! empty( $row->sortorder ) ? json_decode( base64_decode( $row->sortorder ), true ) : null;
            if ( is_array( $items ) ) {
                foreach ( $items as $item ) {
                    $item = (string) $item;
                    if ( preg_match( '/^a(\d+)$/', $item, $m ) ) {
                        $row->subalbum_ids[] = (int) $m[1];
                    } elseif ( ctype_digit( $item ) ) {
                        $row->gallery_ids[] = (int) $item;
                    }
                }
            }
            $albums[ (int) $row->id ] = $row;
        }

        // Alle galerijen (ook via sub-albums) per album.
        $all_gids = array();
        foreach ( $albums as $id => $album ) {
            $album->all_gallery_ids = self::collect_gallery_ids( $id, $albums, array() );
            $all_gids               = array_merge( $all_gids, $album->all_gallery_ids );
        }
        $all_gids = array_values( array_unique( $all_gids ) );

        // Galerijgegevens in één query.
        $galleries = array();
        if ( ! empty( $all_gids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $all_gids ), '%d' ) );
            $results      = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT gid, path, previewpic, date_created FROM {$ngg_gallery_table} WHERE gid IN ({$placeholders})",
                    $all_gids
                )
            );
            foreach ( (array) $results as $gallery ) {
                $galleries[ (int) $gallery->gid ] = $gallery;
            }
        }

        // Filteren en sorteerdatum bepalen.
        $lijst = array();
        foreach ( $albums as $id => $album ) {
            if ( in_array( (string) $id, $uitsluiten, true ) || in_array( strtolower( (string) $album->slug ), $uitsluiten, true ) ) {
                continue;
            }

            $album->latest_gallery = null;
            foreach ( $album->all_gallery_ids as $gid ) {
                if ( ! isset( $galleries[ $gid ] ) ) {
                    continue;
                }
                if ( ! $album->latest_gallery || strcmp( $galleries[ $gid ]->date_created, $album->latest_gallery->date_created ) > 0
                    || ( $galleries[ $gid ]->date_created === $album->latest_gallery->date_created && $gid > (int) $album->latest_gallery->gid ) ) {
                    $album->latest_gallery = $galleries[ $gid ];
                }
            }

            // Lege albums tellen niet mee bij "nieuwste galerij".
            if ( 'galerij' === $sortering && ! $album->latest_gallery ) {
                continue;
            }

            $lijst[] = $album;
        }

        usort(
            $lijst,
            function ( $a, $b ) use ( $sortering ) {
                if ( 'galerij' === $sortering ) {
                    $cmp = strcmp( $b->latest_gallery->date_created, $a->latest_gallery->date_created );
                    if ( 0 !== $cmp ) {
                        return $cmp;
                    }
                }
                return (int) $b->id - (int) $a->id;
            }
        );

        $lijst = array_slice( $lijst, 0, $aantal );
        if ( empty( $lijst ) ) {
            return '';
        }

        wp_enqueue_style( 'kz-recente-galerijen-style' );

        ob_start();
        ?>
        <div class="kz-recente-galerijen kz-recente-albums kz-recente-galerijen--<?php echo esc_attr( $text_color ); ?>" style="--kz-columns: <?php echo esc_attr( $columns ); ?>;">
            <?php foreach ( $lijst as $album ) : ?>
                <?php
                $titel     = KZ_Element_Recent_Galleries::strip_leading_date( $album->name );
                $link      = self::get_album_link( $album );
                $thumb_url = self::get_album_thumb_url( $album, $ngg_pictures_table, $wpdb );
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
     * Alle galerij-id's in een album, ook die in sub-albums (en dieper).
     * $visited voorkomt een eindeloze lus als albums elkaar bevatten.
     */
    private static function collect_gallery_ids( $album_id, $albums, $visited ) {
        if ( ! isset( $albums[ $album_id ] ) || in_array( $album_id, $visited, true ) ) {
            return array();
        }
        $visited[] = $album_id;

        $gids = $albums[ $album_id ]->gallery_ids;
        foreach ( $albums[ $album_id ]->subalbum_ids as $sub_id ) {
            $gids = array_merge( $gids, self::collect_gallery_ids( $sub_id, $albums, $visited ) );
        }
        return array_values( array_unique( $gids ) );
    }

    /**
     * Link naar het album: de gekoppelde WordPress-pagina (pageid) als die
     * gepubliceerd is, anders /fotos/{album-slug}/ — hetzelfde begin als de
     * galerijlinks van KZ-Recente Galerijen (/fotos/{album}/ngggaleries/...).
     */
    private static function get_album_link( $album ) {
        if ( ! empty( $album->pageid ) && 'publish' === get_post_status( $album->pageid ) ) {
            $link = get_permalink( $album->pageid );
            if ( $link ) {
                return $link;
            }
        }

        if ( ! empty( $album->slug ) ) {
            return home_url( '/fotos/' . $album->slug . '/' );
        }

        return '';
    }

    /**
     * Voorbeeldafbeelding: de ingestelde previewpic van het album, anders die
     * van de nieuwste galerij in het album (zoals KZ-Recente Galerijen hem kiest).
     */
    private static function get_album_thumb_url( $album, $ngg_pictures_table, $wpdb ) {
        if ( ! empty( $album->previewpic ) ) {
            $picture = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT p.post_id, p.filename, g.path
                     FROM {$ngg_pictures_table} p
                     LEFT JOIN {$wpdb->prefix}ngg_gallery g ON g.gid = p.galleryid
                     WHERE p.pid = %d",
                    $album->previewpic
                )
            );

            if ( $picture ) {
                if ( ! empty( $picture->post_id ) ) {
                    $url = wp_get_attachment_image_url( $picture->post_id, 'thumbnail' );
                    if ( $url ) {
                        return $url;
                    }
                }
                if ( ! empty( $picture->path ) && ! empty( $picture->filename ) ) {
                    return home_url( '/' . trim( $picture->path, '/' ) . '/thumbs/thumbs-' . $picture->filename );
                }
            }
        }

        if ( ! empty( $album->latest_gallery ) ) {
            return KZ_Element_Recent_Galleries::get_gallery_thumb_url( $album->latest_gallery, $ngg_pictures_table, $wpdb );
        }

        return '';
    }
}
