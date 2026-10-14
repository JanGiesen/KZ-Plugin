<?php
/**
 * KZ-Evenement Info — shortcode [kz_evenement_info]
 * Infoblok voor een evenement op de ticketpagina: rode kop met logo, label
 * en titel, een datumblok (dag, dd/mm, tijd), omschrijving, een lijstje
 * feiten (Waar, Prijs, ...) en een kaartenknop.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Event_Info {

    const SHORTCODE = 'kz_evenement_info';

    private static $dagen = array( 'Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag' );

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => __( 'KZ-Evenement Info', 'kz-plugin' ),
                'base'        => self::SHORTCODE,
                'category'    => __( 'Kraonige Zwaone', 'kz-plugin' ),
                'icon'        => 'kz-icon-event-info',
                'description' => __( 'Infoblok voor een evenement met datum, feiten en kaartenknop.', 'kz-plugin' ),
                'params'      => array(
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Label', 'kz-plugin' ),
                        'param_name'  => 'label',
                        'value'       => 'Kom ook',
                        'description' => __( 'Klein wit label boven de titel, bijv. "Wij gaan naar de film". Leeg = geen label.', 'kz-plugin' ),
                        'admin_label' => true,
                        'group'       => __( 'Inhoud', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Titel', 'kz-plugin' ),
                        'param_name'  => 'titel',
                        'value'       => '',
                        'admin_label' => true,
                        'group'       => __( 'Inhoud', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textarea_html',
                        'heading'     => __( 'Omschrijving', 'kz-plugin' ),
                        'param_name'  => 'content',
                        'value'       => '',
                        'group'       => __( 'Inhoud', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'param_group',
                        'heading'     => __( 'Feiten', 'kz-plugin' ),
                        'param_name'  => 'feiten',
                        'description' => __( 'Korte regels onder de omschrijving, bijv. Waar / Prijs / Leeftijd.', 'kz-plugin' ),
                        'group'       => __( 'Inhoud', 'kz-plugin' ),
                        'value'       => rawurlencode(
                            wp_json_encode(
                                array(
                                    array( 'label' => 'Waar', 'waarde' => '' ),
                                    array( 'label' => 'Prijs', 'waarde' => '' ),
                                )
                            )
                        ),
                        'params'      => array(
                            array(
                                'type'        => 'textfield',
                                'heading'     => __( 'Label', 'kz-plugin' ),
                                'param_name'  => 'label',
                                'admin_label' => true,
                            ),
                            array(
                                'type'       => 'textfield',
                                'heading'    => __( 'Waarde', 'kz-plugin' ),
                                'param_name' => 'waarde',
                            ),
                        ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Logo tonen', 'kz-plugin' ),
                        'param_name'  => 'show_logo',
                        'value'       => array(
                            'Ja'  => 'true',
                            'Nee' => 'false',
                        ),
                        'std'         => 'true',
                        'group'       => __( 'Inhoud', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Datum', 'kz-plugin' ),
                        'param_name'  => 'datum',
                        'value'       => '',
                        'description' => __( 'Formaat dd-mm-jjjj (bijv. 27-11-2026). De dag van de week wordt automatisch ingevuld. Leeg = geen datumblok.', 'kz-plugin' ),
                        'admin_label' => true,
                        'group'       => __( 'Datum & tijd', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Tekst boven datum', 'kz-plugin' ),
                        'param_name'  => 'dag_tekst',
                        'value'       => '',
                        'description' => __( 'Optioneel: vervangt de automatische dagnaam, bijv. "Elke vrijdag".', 'kz-plugin' ),
                        'group'       => __( 'Datum & tijd', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Tijd', 'kz-plugin' ),
                        'param_name'  => 'tijd',
                        'value'       => '',
                        'description' => __( 'Bijv. 19:00 - 23:59. Leeg = geen tijd.', 'kz-plugin' ),
                        'group'       => __( 'Datum & tijd', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'vc_link',
                        'heading'     => __( 'Link kaartenknop', 'kz-plugin' ),
                        'param_name'  => 'link',
                        'description' => __( 'Bijv. het AllUnited-bestelformulier. Leeg = geen knop.', 'kz-plugin' ),
                        'group'       => __( 'Kaarten', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Knoptekst', 'kz-plugin' ),
                        'param_name'  => 'knop_tekst',
                        'value'       => 'Kaarten bestellen',
                        'group'       => __( 'Kaarten', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Status', 'kz-plugin' ),
                        'param_name'  => 'status',
                        'value'       => array(
                            'Kaarten beschikbaar'            => 'open',
                            'Kaartverkoop nog niet gestart' => 'binnenkort',
                            'Uitverkocht'                   => 'uitverkocht',
                        ),
                        'std'         => 'open',
                        'description' => __( 'Bij "nog niet gestart" en "Uitverkocht" is de knop niet klikbaar.', 'kz-plugin' ),
                        'group'       => __( 'Kaarten', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Start kaartverkoop', 'kz-plugin' ),
                        'param_name'  => 'verkoop_start',
                        'value'       => '',
                        'description' => __( 'Optioneel, formaat dd-mm-jjjj. De knop toont dan "Kaartverkoop begint op 12/12" en wordt vanaf die dag vanzelf de bestelknop (als er een link is ingevuld). Leeg = "Kaartverkoop nog niet gestart".', 'kz-plugin' ),
                        'group'       => __( 'Kaarten', 'kz-plugin' ),
                        'dependency'  => array(
                            'element' => 'status',
                            'value'   => 'binnenkort',
                        ),
                    ),
                    array(
                        'type'        => 'textarea',
                        'heading'     => __( 'Kleine tekst', 'kz-plugin' ),
                        'param_name'  => 'kleine_tekst',
                        'value'       => '',
                        'description' => __( 'Optionele toelichting onder de knop, bijv. "Maximaal 50 kaarten per bestelling."', 'kz-plugin' ),
                        'group'       => __( 'Kaarten', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Maximale breedte', 'kz-plugin' ),
                        'param_name'  => 'max_width',
                        'value'       => '820px',
                        'description' => __( 'Bijv. 820px of 100%.', 'kz-plugin' ),
                        'group'       => __( 'Layout', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Extra CSS class', 'kz-plugin' ),
                        'param_name'  => 'el_class',
                        'group'       => __( 'Layout', 'kz-plugin' ),
                    ),
                    array(
                        'type'       => 'css_editor',
                        'heading'    => __( 'Design Options', 'kz-plugin' ),
                        'param_name' => 'css',
                        'group'      => __( 'Design Options', 'kz-plugin' ),
                    ),
                ),
            )
        );
    }

    public static function render( $atts, $content = '' ) {
        $atts = shortcode_atts(
            array(
                'label'        => 'Kom ook',
                'titel'        => '',
                'feiten'       => '',
                'show_logo'    => 'true',
                'datum'        => '',
                'dag_tekst'    => '',
                'tijd'         => '',
                'link'         => '',
                'knop_tekst'   => 'Kaarten bestellen',
                'status'       => 'open',
                'verkoop_start' => '',
                'kleine_tekst' => '',
                'max_width'    => '820px',
                'el_class'     => '',
                'css'          => '',
            ),
            $atts,
            self::SHORTCODE
        );

        wp_enqueue_style( 'kz-evenement-info-fonts' );
        wp_enqueue_style( 'kz-evenement-info-style' );

        $titel_id  = wp_unique_id( 'kz-evt-titel-' );
        $show_logo = 'false' !== $atts['show_logo'];
        $datum     = self::parse_datum( $atts['datum'] );
        $dag_tekst = '' !== trim( $atts['dag_tekst'] ) ? $atts['dag_tekst'] : ( $datum ? self::$dagen[ (int) $datum->format( 'w' ) ] : '' );
        $toon_datum = $datum || '' !== trim( $atts['datum'] ) || '' !== trim( $atts['tijd'] );
        $uitverkocht = 'uitverkocht' === $atts['status'];

        $feiten = array();
        if ( ! empty( $atts['feiten'] ) && function_exists( 'vc_param_group_parse_atts' ) ) {
            $feiten = vc_param_group_parse_atts( $atts['feiten'] );
        } elseif ( ! empty( $atts['feiten'] ) ) {
            $feiten = json_decode( rawurldecode( $atts['feiten'] ), true );
        }
        $feiten = array_filter(
            is_array( $feiten ) ? $feiten : array(),
            function ( $feit ) {
                return ! empty( $feit['waarde'] );
            }
        );

        $href   = '';
        $target = '';
        $rel    = '';
        if ( ! empty( $atts['link'] ) ) {
            if ( function_exists( 'vc_build_link' ) ) {
                $link   = vc_build_link( $atts['link'] );
                $href   = isset( $link['url'] ) ? trim( $link['url'] ) : '';
                $target = isset( $link['target'] ) ? trim( $link['target'] ) : '';
                $rel    = isset( $link['rel'] ) ? trim( $link['rel'] ) : '';
            } else {
                $href = $atts['link'];
            }
        }
        if ( '_blank' === $target && false === strpos( $rel, 'noopener' ) ) {
            $rel = trim( $rel . ' noopener' );
        }
        // Kaartverkoop nog niet gestart: met startdatum gaat de knop vanaf die dag vanzelf open.
        $binnenkort = false;
        if ( 'binnenkort' === $atts['status'] ) {
            $verkoop_start = self::parse_datum( $atts['verkoop_start'] );
            $binnenkort    = ! $verkoop_start || '' === $href || $verkoop_start->format( 'Y-m-d' ) > current_time( 'Y-m-d' );
        }

        if ( $uitverkocht ) {
            $knop_tekst = __( 'Uitverkocht', 'kz-plugin' );
        } elseif ( $binnenkort ) {
            $knop_tekst = $verkoop_start
                /* translators: %s: startdatum kaartverkoop als dd/mm */
                ? sprintf( __( 'Kaartverkoop begint op %s', 'kz-plugin' ), $verkoop_start->format( 'd/m' ) )
                : __( 'Kaartverkoop nog niet gestart', 'kz-plugin' );
        } else {
            $knop_tekst = $atts['knop_tekst'];
        }
        $toon_knop = $uitverkocht || $binnenkort || '' !== $href;

        if ( function_exists( 'wpb_js_remove_wpautop' ) ) {
            $omschrijving = wpb_js_remove_wpautop( $content, true );
        } else {
            $omschrijving = wpautop( do_shortcode( (string) $content ) );
        }

        $classes = array( 'kz-evt' );
        if ( ! $toon_datum ) {
            $classes[] = 'kz-evt--zonder-datum';
        }
        if ( function_exists( 'vc_shortcode_custom_css_class' ) ) {
            $classes[] = vc_shortcode_custom_css_class( $atts['css'], '' );
        }
        $classes[] = $atts['el_class'];
        $classes   = implode( ' ', array_filter( array_map( 'trim', $classes ) ) );

        ob_start();
        ?>
        <section class="<?php echo esc_attr( $classes ); ?>" style="max-width: <?php echo esc_attr( $atts['max_width'] ); ?>;"<?php echo '' !== $atts['titel'] ? ' aria-labelledby="' . esc_attr( $titel_id ) . '"' : ''; ?>>
            <div class="kz-evt__top">
                <?php if ( $show_logo ) : ?>
                    <img class="kz-evt__logo" src="<?php echo esc_url( KZ_PLUGIN_URL . 'assets/img/kz-logo-wit.svg' ); ?>" width="84" height="100" alt="<?php esc_attr_e( 'Logo Kraonige Zwaone', 'kz-plugin' ); ?>" />
                <?php endif; ?>
                <div>
                    <?php if ( '' !== $atts['label'] ) : ?>
                        <span class="kz-evt__kop kz-evt__cat"><?php echo esc_html( $atts['label'] ); ?></span>
                    <?php endif; ?>
                    <?php if ( '' !== $atts['titel'] ) : ?>
                        <h2 id="<?php echo esc_attr( $titel_id ); ?>" class="kz-evt__kop kz-evt__titel"><?php echo esc_html( $atts['titel'] ); ?></h2>
                    <?php endif; ?>
                </div>
            </div>

            <div class="kz-evt__body">
                <div class="kz-evt__balk" aria-hidden="true"></div>

                <?php if ( $toon_datum ) : ?>
                    <div class="kz-evt__datum">
                        <?php if ( '' !== $dag_tekst ) : ?>
                            <span class="kz-evt__dag"><?php echo esc_html( $dag_tekst ); ?></span>
                        <?php endif; ?>
                        <div class="kz-evt__blok">
                            <?php if ( $datum ) : ?>
                                <span class="kz-evt__dd"><?php echo esc_html( $datum->format( 'd/m' ) ); ?></span>
                            <?php elseif ( '' !== trim( $atts['datum'] ) ) : ?>
                                <span class="kz-evt__dd"><?php echo esc_html( $atts['datum'] ); ?></span>
                            <?php endif; ?>
                            <?php if ( '' !== trim( $atts['tijd'] ) ) : ?>
                                <span class="kz-evt__tijd"><?php echo esc_html( $atts['tijd'] ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="kz-evt__tekst">
                    <?php echo wp_kses_post( $omschrijving ); ?>
                    <?php if ( ! empty( $feiten ) ) : ?>
                        <ul class="kz-evt__feiten">
                            <?php foreach ( $feiten as $feit ) : ?>
                                <li><b><?php echo esc_html( isset( $feit['label'] ) ? $feit['label'] : '' ); ?></b><span><?php echo esc_html( $feit['waarde'] ); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <?php if ( $toon_knop ) : ?>
                    <div class="kz-evt__actie">
                        <?php if ( $uitverkocht ) : ?>
                            <span class="kz-evt__knop kz-evt__knop--uit" aria-disabled="true"><span><?php echo esc_html( $knop_tekst ); ?></span></span>
                        <?php elseif ( $binnenkort ) : ?>
                            <span class="kz-evt__knop kz-evt__knop--binnenkort" aria-disabled="true"><span><?php echo esc_html( $knop_tekst ); ?></span></span>
                        <?php elseif ( $toon_knop ) : ?>
                            <a class="kz-evt__knop" href="<?php echo esc_url( $href ); ?>"<?php echo $target ? ' target="' . esc_attr( $target ) . '"' : ''; ?><?php echo $rel ? ' rel="' . esc_attr( $rel ) . '"' : ''; ?>><span><?php echo esc_html( $knop_tekst ); ?></span></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ( '' !== trim( $atts['kleine_tekst'] ) ) : ?>
                    <p class="kz-evt__klein"><?php echo esc_html( $atts['kleine_tekst'] ); ?></p>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Leest een datum in als dd-mm-jjjj, dd/mm/jjjj, dd.mm.jjjj of jjjj-mm-dd.
     *
     * @return DateTime|null
     */
    private static function parse_datum( $waarde ) {
        $waarde = trim( (string) $waarde );
        if ( '' === $waarde ) {
            return null;
        }
        foreach ( array( '!d-m-Y', '!d/m/Y', '!d.m.Y', '!Y-m-d' ) as $formaat ) {
            $datum  = DateTime::createFromFormat( $formaat, $waarde );
            $fouten = DateTime::getLastErrors();
            // Ongeldige data als 31-02-2026 geven een waarschuwing in plaats van een fout.
            if ( $datum && ( ! $fouten || ( 0 === $fouten['warning_count'] && 0 === $fouten['error_count'] ) ) ) {
                return $datum;
            }
        }
        return null;
    }
}
