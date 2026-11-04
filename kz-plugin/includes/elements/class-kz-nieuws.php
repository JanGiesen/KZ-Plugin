<?php
/**
 * KZ-Nieuws — shortcode [kz_nieuws]
 * Grid van nieuwsberichten, op de server getekend, met paginering via de
 * server (?pagina=2 of /page/2/). Vervangt de WPBakery Post Grid, die zijn
 * items via admin-ajax ophaalt en voor gasten leeg bleef zodra de nonce in de
 * LiteSpeed-cache verlopen was. Zie KZ_Berichten_Grid.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Nieuws {

    const SHORTCODE = 'kz_nieuws';

    /** Toegestane beeldverhoudingen (achtervoegsel van de klasse kz-nieuws--…). */
    const VERHOUDINGEN = array( 'liggend', 'breed', 'vierkant', 'staand', 'eigen' );

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => __( 'KZ-Nieuws', 'kz-plugin' ),
                'base'        => self::SHORTCODE,
                'category'    => __( 'Kraonige Zwaone', 'kz-plugin' ),
                'icon'        => 'kz-icon-nieuws',
                'description' => __( 'Grid van nieuwsberichten met paginering, zonder AJAX.', 'kz-plugin' ),
                'params'      => array_merge(
                    array(
                        array(
                            'type'        => 'textfield',
                            'heading'     => __( 'Berichten per pagina', 'kz-plugin' ),
                            'param_name'  => 'per_pagina',
                            'value'       => '9',
                        ),
                        KZ_Berichten_Grid::vc_veld_ja_nee( 'paginering', __( 'Paginering', 'kz-plugin' ), 'ja', __( 'Nee = alleen de eerste pagina (bijvoorbeeld op de voorpagina).', 'kz-plugin' ) ),
                        array(
                            'type'        => 'textfield',
                            'heading'     => __( 'Maximaal aantal berichten', 'kz-plugin' ),
                            'param_name'  => 'max_aantal',
                            'value'       => '',
                            'description' => __( 'Leeg = alle berichten. Zoals "Total items" bij de WPBakery Post Grid.', 'kz-plugin' ),
                        ),
                        array(
                            'type'        => 'textfield',
                            'heading'     => __( 'Categorie', 'kz-plugin' ),
                            'param_name'  => 'categorie',
                            'value'       => '',
                            'description' => __( 'Slug van een of meer categorieën, met komma\'s. Leeg = alle berichten.', 'kz-plugin' ),
                        ),
                        KZ_Berichten_Grid::vc_veld_sortering( 'datum' ),
                        array(
                            'type'       => 'dropdown',
                            'heading'    => __( 'Beeldverhouding foto', 'kz-plugin' ),
                            'param_name' => 'verhouding',
                            'value'      => array(
                                __( 'Liggend 3:2', 'kz-plugin' )          => 'liggend',
                                __( 'Breed 16:9', 'kz-plugin' )           => 'breed',
                                __( 'Vierkant 1:1', 'kz-plugin' )         => 'vierkant',
                                __( 'Staand 4:5', 'kz-plugin' )           => 'staand',
                                __( 'Eigen (niet bijsnijden)', 'kz-plugin' ) => 'eigen',
                            ),
                            'std'        => 'liggend',
                        ),
                        KZ_Berichten_Grid::vc_veld_ja_nee( 'toon_datum', __( 'Datum tonen', 'kz-plugin' ), 'ja' ),
                        KZ_Berichten_Grid::vc_veld_ja_nee( 'toon_titel', __( 'Titel tonen', 'kz-plugin' ), 'ja' ),
                        KZ_Berichten_Grid::vc_veld_ja_nee( 'toon_samenvatting', __( 'Samenvatting tonen', 'kz-plugin' ), 'nee' ),
                        array(
                            'type'        => 'textfield',
                            'heading'     => __( 'Woorden in de samenvatting', 'kz-plugin' ),
                            'param_name'  => 'woorden',
                            'value'       => '20',
                            'dependency'  => array(
                                'element' => 'toon_samenvatting',
                                'value'   => array( 'ja' ),
                            ),
                        ),
                        KZ_Berichten_Grid::vc_veld_kleur( 'rood' ),
                        array(
                            'type'        => 'dropdown',
                            'heading'     => __( 'Kleur paginering', 'kz-plugin' ),
                            'param_name'  => 'paginering_kleur',
                            'value'       => array(
                                __( 'Rood (voor een witte achtergrond)', 'kz-plugin' ) => 'rood',
                                __( 'Wit (voor een rode achtergrond)', 'kz-plugin' )   => 'wit',
                            ),
                            'std'         => 'rood',
                            'dependency'  => array(
                                'element' => 'paginering',
                                'value'   => array( 'ja' ),
                            ),
                        ),
                        array(
                            'type'        => 'textfield',
                            'heading'     => __( 'Tekst als er geen berichten zijn', 'kz-plugin' ),
                            'param_name'  => 'leeg_tekst',
                            'value'       => '',
                            'description' => __( 'Leeg = niets tonen.', 'kz-plugin' ),
                        ),
                        array(
                            'type'        => 'textfield',
                            'heading'     => __( 'Post type', 'kz-plugin' ),
                            'param_name'  => 'post_type',
                            'value'       => 'post',
                            'description' => __( 'Laat staan op "post" voor gewone berichten.', 'kz-plugin' ),
                        ),
                    ),
                    KZ_Berichten_Grid::vc_velden_opmaak( 3, 2, 1, 30 )
                ),
            )
        );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'per_pagina'        => '9',
                'paginering'        => 'ja',
                'max_aantal'        => '',
                'categorie'         => '',
                'sortering'         => 'datum',
                'verhouding'        => 'liggend',
                'toon_datum'        => 'ja',
                'toon_titel'        => 'ja',
                'toon_samenvatting' => 'nee',
                'woorden'           => '20',
                'tekstkleur'        => 'rood',
                'paginering_kleur'  => 'rood',
                'leeg_tekst'        => '',
                'post_type'         => 'post',
                'kolommen'          => '3',
                'kolommen_tablet'   => '2',
                'kolommen_mobiel'   => '1',
                'ruimte'            => '30',
                'el_class'          => '',
                'css'               => '',
            ),
            $atts,
            self::SHORTCODE
        );

        $post_type = sanitize_key( $atts['post_type'] );
        if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
            return '';
        }

        KZ_Berichten_Grid::cache_tag( $post_type );

        $per_pagina = min( 100, max( 1, absint( $atts['per_pagina'] ) ) );
        $max        = absint( $atts['max_aantal'] );
        $paginering = KZ_Berichten_Grid::ja( $atts['paginering'] );
        $pagina     = $paginering ? KZ_Berichten_Grid::huidige_pagina() : 1;

        // Met een maximum: het totaal aantal pagina's volgt uit dat maximum,
        // en de laatste pagina wordt ingekort.
        if ( $max > 0 ) {
            $max_paginas = max( 1, (int) ceil( $max / $per_pagina ) );
            $pagina      = min( $pagina, $max_paginas );
        }

        $args = array_merge(
            array(
                'post_type'           => $post_type,
                'post_status'         => 'publish',
                'posts_per_page'      => $per_pagina,
                'paged'               => $pagina,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => ! $paginering,
            ),
            KZ_Berichten_Grid::sortering( $atts['sortering'] )
        );

        $categorie = trim( (string) $atts['categorie'] );
        if ( '' !== $categorie && 'post' === $post_type ) {
            $args['category_name'] = implode( ',', array_filter( array_map( 'sanitize_title', explode( ',', $categorie ) ) ) );
        }

        $query = new WP_Query( $args );

        // Een te hoog paginanummer (?pagina=99): toon de laatste pagina.
        if ( $paginering && ! $query->have_posts() && $pagina > 1 && $query->max_num_pages > 0 ) {
            $pagina        = (int) $query->max_num_pages;
            $args['paged'] = $pagina;
            $query         = new WP_Query( $args );
        }

        $leeg = trim( (string) $atts['leeg_tekst'] );
        if ( ! $query->have_posts() ) {
            return '' === $leeg ? '' : '<p class="kz-grid-leeg">' . esc_html( $leeg ) . '</p>';
        }

        $posts = $query->posts;
        $totaal_paginas = $paginering ? (int) $query->max_num_pages : 1;
        if ( $max > 0 ) {
            $totaal_paginas = min( $totaal_paginas, $max_paginas );
            $over           = $max - ( $pagina - 1 ) * $per_pagina;
            $posts          = array_slice( $posts, 0, max( 0, $over ) );
        }

        wp_enqueue_style( 'kz-berichten-grid-style' );

        $kleur      = 'wit' === $atts['tekstkleur'] ? 'wit' : 'rood';
        $verhouding = in_array( $atts['verhouding'], self::VERHOUDINGEN, true ) ? $atts['verhouding'] : 'liggend';
        $met_datum  = KZ_Berichten_Grid::ja( $atts['toon_datum'] );
        $met_titel  = KZ_Berichten_Grid::ja( $atts['toon_titel'] );
        $met_tekst  = KZ_Berichten_Grid::ja( $atts['toon_samenvatting'] );

        $klassen = KZ_Berichten_Grid::wrapper_klassen(
            array( 'kz-grid', 'kz-nieuws', 'kz-grid--' . $kleur, 'kz-nieuws--' . $verhouding ),
            $atts['el_class'],
            $atts['css']
        );
        $stijl = KZ_Berichten_Grid::stijl(
            KZ_Berichten_Grid::kolommen( $atts['kolommen'], 3 ),
            KZ_Berichten_Grid::kolommen( $atts['kolommen_tablet'], 2 ),
            KZ_Berichten_Grid::kolommen( $atts['kolommen_mobiel'], 1 ),
            min( 100, absint( $atts['ruimte'] ) )
        );

        ob_start();
        ?>
        <div class="kz-nieuws-wrapper">
            <div class="<?php echo esc_attr( $klassen ); ?>" style="<?php echo esc_attr( $stijl ); ?>">
                <?php foreach ( $posts as $post ) : ?>
                    <?php
                    $titel = wp_strip_all_tags( get_the_title( $post ) );
                    $foto  = KZ_Berichten_Grid::foto_url( $post, 'medium_large' );
                    ?>
                    <article class="kz-grid__item kz-nieuws__item">
                        <a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
                            <span class="kz-nieuws__foto<?php echo $foto ? '' : ' kz-nieuws__foto--leeg'; ?>">
                                <?php if ( $foto ) : ?>
                                    <img src="<?php echo esc_url( $foto ); ?>" alt="<?php echo esc_attr( KZ_Berichten_Grid::foto_alt( $post ) ); ?>" loading="lazy" decoding="async" />
                                <?php endif; ?>
                            </span>
                            <?php if ( $met_datum ) : ?>
                                <time class="kz-nieuws__datum" datetime="<?php echo esc_attr( get_the_date( 'c', $post ) ); ?>"><?php echo esc_html( get_the_date( '', $post ) ); ?></time>
                            <?php endif; ?>
                            <?php if ( $met_titel ) : ?>
                                <span class="kz-grid__titel"><?php echo esc_html( $titel ); ?></span>
                            <?php endif; ?>
                            <?php if ( $met_tekst ) : ?>
                                <span class="kz-nieuws__tekst"><?php echo esc_html( KZ_Berichten_Grid::samenvatting( $post, absint( $atts['woorden'] ) ) ); ?></span>
                            <?php endif; ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php
            if ( $paginering ) {
                echo KZ_Berichten_Grid::paginering( $pagina, $totaal_paginas, KZ_Berichten_Grid::basis_url(), 'wit' === $atts['paginering_kleur'] ? 'wit' : 'rood' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- alles is al ge-escaped.
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
