<?php
/**
 * KZH Ticket — shortcode [kzh_ticket]
 * Overgenomen uit KZH Ticket Element 1.0.0, gedrag ongewijzigd.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Ticket {

    const SHORTCODE = 'kzh_ticket';

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }
        vc_map(
            array(
                'name'        => __( 'KZH Ticket', 'kz-plugin' ),
                'base'        => self::SHORTCODE,
                'category'    => __( 'Kraonige Zwaone', 'kz-plugin' ),
                'icon'        => 'icon-wpb-application-icon-large',
                'description' => __( 'Ticket element met aanpasbare tekst en kleuren', 'kz-plugin' ),
                'params'      => array(
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Logo', 'kz-plugin' ),
                        'param_name'  => 'logo_type',
                        'value'       => array(
                            __( 'Kraonige Zwaone', 'kz-plugin' )       => 'kzh',
                            __( 'HJC De Krössels', 'kz-plugin' )       => 'hjc',
                            __( 'Aangepast (uploaden)', 'kz-plugin' )  => 'custom',
                        ),
                        'std'         => 'kzh',
                        'description' => __( 'Kies het logo voor het ticket', 'kz-plugin' ),
                        'group'       => __( 'Inhoud', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'attach_image',
                        'heading'     => __( 'Aangepast logo', 'kz-plugin' ),
                        'param_name'  => 'logo_image',
                        'description' => __( 'Upload een eigen logo', 'kz-plugin' ),
                        'group'       => __( 'Inhoud', 'kz-plugin' ),
                        'dependency'  => array(
                            'element' => 'logo_type',
                            'value'   => 'custom',
                        ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Hoofdregel', 'kz-plugin' ),
                        'param_name'  => 'main_text',
                        'value'       => 'TICKET',
                        'description' => __( 'De hoofdtekst op het ticket', 'kz-plugin' ),
                        'group'       => __( 'Inhoud', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Ondertitel', 'kz-plugin' ),
                        'param_name'  => 'subtitle',
                        'value'       => "HUUSSE' HELAU!",
                        'description' => __( 'De ondertitel op het ticket', 'kz-plugin' ),
                        'group'       => __( 'Inhoud', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'vc_link',
                        'heading'     => __( 'Link', 'kz-plugin' ),
                        'param_name'  => 'link',
                        'description' => __( 'Optioneel: link waar het ticket naar verwijst', 'kz-plugin' ),
                        'group'       => __( 'Link', 'kz-plugin' ),
                    ),
                    array(
                        'type'       => 'checkbox',
                        'heading'    => __( 'Toon bevestiging popup', 'kz-plugin' ),
                        'param_name' => 'enable_popup',
                        'value'      => array( __( 'Ja, toon popup voor openen', 'kz-plugin' ) => 'true' ),
                        'group'      => __( 'Link', 'kz-plugin' ),
                        'dependency' => array(
                            'element'   => 'link',
                            'not_empty' => true,
                        ),
                    ),
                    array(
                        'type'        => 'textarea',
                        'heading'     => __( 'Popup tekst', 'kz-plugin' ),
                        'param_name'  => 'popup_text',
                        'value'       => __( 'Weet je zeker dat je verder wilt?', 'kz-plugin' ),
                        'description' => __( 'Tekst in de rode popup', 'kz-plugin' ),
                        'group'       => __( 'Link', 'kz-plugin' ),
                        'dependency'  => array(
                            'element' => 'enable_popup',
                            'value'   => 'true',
                        ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Kleurenschema', 'kz-plugin' ),
                        'param_name'  => 'color_preset',
                        'value'       => array(
                            __( 'Rood (Kraonige Zwaone)', 'kz-plugin' ) => 'rood',
                            __( 'Wit (omgekeerd)', 'kz-plugin' )        => 'wit',
                            __( 'HJC (geel/zwart)', 'kz-plugin' )       => 'hjc',
                            __( 'Aangepast', 'kz-plugin' )              => 'custom',
                        ),
                        'std'         => 'rood',
                        'description' => __( 'Kies een voorgedefinieerd kleurenschema of aangepast', 'kz-plugin' ),
                        'group'       => __( 'Kleuren', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'colorpicker',
                        'heading'     => __( 'Achtergrondkleur', 'kz-plugin' ),
                        'param_name'  => 'bg_color',
                        'value'       => '#E83F35',
                        'description' => __( 'De achtergrondkleur van het ticket', 'kz-plugin' ),
                        'group'       => __( 'Kleuren', 'kz-plugin' ),
                        'dependency'  => array(
                            'element' => 'color_preset',
                            'value'   => 'custom',
                        ),
                    ),
                    array(
                        'type'        => 'colorpicker',
                        'heading'     => __( 'Tekstkleur', 'kz-plugin' ),
                        'param_name'  => 'text_color',
                        'value'       => '#FFFFFF',
                        'description' => __( 'De kleur van de tekst', 'kz-plugin' ),
                        'group'       => __( 'Kleuren', 'kz-plugin' ),
                        'dependency'  => array(
                            'element' => 'color_preset',
                            'value'   => 'custom',
                        ),
                    ),
                    array(
                        'type'        => 'colorpicker',
                        'heading'     => __( 'Randkleur (stippellijn)', 'kz-plugin' ),
                        'param_name'  => 'border_color',
                        'value'       => '#FFFFFF',
                        'description' => __( 'De kleur van de stippellijn rand', 'kz-plugin' ),
                        'group'       => __( 'Kleuren', 'kz-plugin' ),
                        'dependency'  => array(
                            'element' => 'color_preset',
                            'value'   => 'custom',
                        ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Maximale breedte', 'kz-plugin' ),
                        'param_name'  => 'max_width',
                        'value'       => '100%',
                        'description' => __( 'Maximale breedte (bijv. 500px of 100%)', 'kz-plugin' ),
                        'group'       => __( 'Layout', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Extra CSS class', 'kz-plugin' ),
                        'param_name'  => 'el_class',
                        'description' => __( 'Voeg extra CSS classes toe', 'kz-plugin' ),
                        'group'       => __( 'Layout', 'kz-plugin' ),
                    ),
                    array(
                        'type'    => 'css_editor',
                        'heading' => __( 'Design Options', 'kz-plugin' ),
                        'param_name' => 'css',
                        'group'   => __( 'Design Options', 'kz-plugin' ),
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'logo_type'    => 'kzh',
                'logo_image'   => '',
                'main_text'    => 'TICKET',
                'subtitle'     => "HUUSSE' HELAU!",
                'color_preset' => 'rood',
                'bg_color'     => '#E83F35',
                'text_color'   => '#FFFFFF',
                'border_color' => '#FFFFFF',
                'max_width'    => '100%',
                'el_class'     => '',
                'link'         => '',
                'enable_popup' => '',
                'popup_text'   => __( 'Weet je zeker dat je verder wilt?', 'kz-plugin' ),
                'css'          => '',
            ),
            $atts,
            self::SHORTCODE
        );

        $color_preset = $atts['color_preset'];
        switch ( $color_preset ) {
            case 'rood':
                $bg_color     = '#E83F35';
                $text_color   = '#FFFFFF';
                $border_color = '#FFFFFF';
                break;
            case 'wit':
                $bg_color     = '#FFFFFF';
                $text_color   = '#E83F35';
                $border_color = '#E83F35';
                break;
            case 'hjc':
                $bg_color     = '#FEDD19';
                $text_color   = '#231F20';
                $border_color = '#231F20';
                break;
            case 'custom':
            default:
                $bg_color     = esc_attr( $atts['bg_color'] );
                $text_color   = esc_attr( $atts['text_color'] );
                $border_color = esc_attr( $atts['border_color'] );
                break;
        }

        $main_text    = esc_html( $atts['main_text'] );
        $subtitle     = esc_html( $atts['subtitle'] );
        $max_width    = esc_attr( $atts['max_width'] );
        $el_class     = esc_attr( $atts['el_class'] );
        $logo_type    = esc_attr( $atts['logo_type'] );
        $enable_popup = ( 'true' === $atts['enable_popup'] || '1' === $atts['enable_popup'] || 'yes' === $atts['enable_popup'] );
        $popup_text   = isset( $atts['popup_text'] ) ? wp_kses_post( $atts['popup_text'] ) : '';
        $css_class    = function_exists( 'vc_shortcode_custom_css_class' ) ? vc_shortcode_custom_css_class( $atts['css'], ' ' ) : '';

        $href       = '';
        $target     = '';
        $rel        = '';
        $title_attr = '';
        if ( ! empty( $atts['link'] ) ) {
            if ( function_exists( 'vc_build_link' ) ) {
                $link       = vc_build_link( $atts['link'] );
                $href       = isset( $link['url'] ) ? esc_url( $link['url'] ) : '';
                $target     = isset( $link['target'] ) && $link['target'] ? esc_attr( $link['target'] ) : '';
                $rel        = isset( $link['rel'] ) && $link['rel'] ? esc_attr( $link['rel'] ) : '';
                $title_attr = isset( $link['title'] ) && $link['title'] ? esc_attr( $link['title'] ) : '';
            } else {
                $href = esc_url( $atts['link'] );
            }
        }
        $has_link = ! empty( $href );

        $logo_svg = '';
        switch ( $logo_type ) {
            case 'hjc':
                $logo_svg = self::get_hjc_logo_paths( $text_color );
                break;
            case 'custom':
                if ( ! empty( $atts['logo_image'] ) ) {
                    $logo_url = wp_get_attachment_image_url( $atts['logo_image'], 'medium' );
                    if ( $logo_url ) {
                        $logo_svg = 'custom';
                    }
                }
                break;
            case 'kzh':
            default:
                $logo_svg = self::get_kzh_logo_paths( $text_color );
                break;
        }

        $text_length = mb_strlen( $main_text );
        if ( $text_length <= 9 ) {
            $font_size = 42;
        } elseif ( $text_length <= 12 ) {
            $font_size = 34;
        } elseif ( $text_length <= 15 ) {
            $font_size = 28;
        } elseif ( $text_length <= 18 ) {
            $font_size = 24;
        } else {
            $font_size = 20;
        }

        ob_start();
        ?>
        <?php $uid = 'kzh_ticket_' . uniqid(); ?>
        <div id="<?php echo $uid; ?>" class="kzh-ticket-wrapper <?php echo $el_class . $css_class; ?><?php echo $has_link ? ' kzh-has-link' : ''; ?>" style="max-width: <?php echo $max_width; ?>; margin: 0 auto;">
            <style>
                #<?php echo $uid; ?> .kzh-ticket-svg { transition: transform .2s ease; display:block; }
                #<?php echo $uid; ?>.kzh-has-link a.kzh-ticket-link:hover .kzh-ticket-svg { transform: scale(1.03); }
                #<?php echo $uid; ?> .kzh-popup-overlay{ position:fixed; inset:0; background: rgba(232,63,53,0.97); display:none; align-items:center; justify-content:center; z-index:9999; }
                #<?php echo $uid; ?> .kzh-popup-inner{ max-width: 90vw; padding: 24px; text-align:center; color:#fff; font-family:'Roboto Condensed', sans-serif; }
                #<?php echo $uid; ?> .kzh-popup-buttons{ margin-top: 16px; display:flex; gap:12px; justify-content:center; }
                #<?php echo $uid; ?> .kzh-btn{ border:1px solid #fff; background:transparent; color:#fff; padding:8px 16px; cursor:pointer; font-family:'Roboto Condensed', sans-serif; }
            </style>
            <?php if ( $has_link ) : ?>
            <a class="kzh-ticket-link" href="<?php echo $href; ?>"<?php echo $title_attr ? ' title="' . $title_attr . '"' : ''; ?><?php echo $target ? ' target="' . $target . '"' : ''; ?><?php echo $rel ? ' rel="' . $rel . '"' : ''; ?>>
            <?php endif; ?>
            <svg class="kzh-ticket-svg" viewBox="0 0 303 159" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: auto; font-family: 'Roboto Condensed', sans-serif;">
                <defs>
                    <clipPath id="ticketClip">
                        <path d="M24,159.028L24,158.873C23.974,156.212 23.527,153.567 22.642,151.056C21.42,147.59 19.391,144.425 16.762,141.858C14.236,139.392 11.171,137.491 7.833,136.338C5.314,135.467 2.664,135.036 0,135.028L0,129.916C3.036,129.916 5.5,127.451 5.5,124.416C5.5,121.38 3.036,118.916 0,118.916L0,115.916C3.036,115.916 5.5,113.451 5.5,110.416C5.5,107.38 3.036,104.916 0,104.916L0,101.916C3.036,101.916 5.5,99.451 5.5,96.416C5.5,93.38 3.036,90.916 0,90.916L0,87.916C3.036,87.916 5.5,85.451 5.5,82.416C5.5,79.38 3.036,76.916 0,76.916L0,73.916C3.036,73.916 5.5,71.451 5.5,68.416C5.5,65.38 3.036,62.916 0,62.916L0,59.916C3.036,59.916 5.5,57.451 5.5,54.416C5.5,51.38 3.036,48.916 0,48.916L0,45.916C3.036,45.916 5.5,43.451 5.5,40.416C5.5,37.38 3.036,34.916 0,34.916L0,29.916C13.246,29.916 24,19.162 24,5.916L278.787,5.916C278.787,19.162 289.542,29.916 302.787,29.916L302.787,34.916C299.752,34.916 297.287,37.38 297.287,40.416C297.287,43.451 299.752,45.916 302.787,45.916L302.787,48.916C299.752,48.916 297.287,51.38 297.287,54.416C297.287,57.451 299.752,59.916 302.787,59.916L302.787,62.916C299.752,62.916 297.287,65.38 297.287,68.416C297.287,71.451 299.752,73.916 302.787,73.916L302.787,76.916C299.752,76.916 297.287,79.38 297.287,82.416C297.287,85.451 299.752,87.916 302.787,87.916L302.787,90.916C299.752,90.916 297.287,93.38 297.287,96.416C297.287,99.451 299.752,101.916 302.787,101.916L302.787,104.916C299.752,104.916 297.287,107.38 297.287,110.416C297.287,113.451 299.752,115.916 302.787,115.916L302.787,118.916C299.752,118.916 297.287,121.38 297.287,124.416C297.287,127.451 299.752,129.916 302.787,129.916L302.787,135.028C300.124,135.036 297.473,135.467 294.954,136.338C291.617,137.491 288.551,139.392 286.025,141.858C283.396,144.425 281.367,147.59 280.145,151.056C279.26,153.567 278.813,156.212 278.788,158.873L278.787,159.028L24,159.028Z"/>
                    </clipPath>
                </defs>

                <!-- Achtergrond -->
                <g transform="translate(0.734, -0.482)">
                    <path d="M24,159.028L24,158.873C23.974,156.212 23.527,153.567 22.642,151.056C21.42,147.59 19.391,144.425 16.762,141.858C14.236,139.392 11.171,137.491 7.833,136.338C5.314,135.467 2.664,135.036 0,135.028L0,129.916C3.036,129.916 5.5,127.451 5.5,124.416C5.5,121.38 3.036,118.916 0,118.916L0,115.916C3.036,115.916 5.5,113.451 5.5,110.416C5.5,107.38 3.036,104.916 0,104.916L0,101.916C3.036,101.916 5.5,99.451 5.5,96.416C5.5,93.38 3.036,90.916 0,90.916L0,87.916C3.036,87.916 5.5,85.451 5.5,82.416C5.5,79.38 3.036,76.916 0,76.916L0,73.916C3.036,73.916 5.5,71.451 5.5,68.416C5.5,65.38 3.036,62.916 0,62.916L0,59.916C3.036,59.916 5.5,57.451 5.5,54.416C5.5,51.38 3.036,48.916 0,48.916L0,45.916C3.036,45.916 5.5,43.451 5.5,40.416C5.5,37.38 3.036,34.916 0,34.916L0,29.916C13.246,29.916 24,19.162 24,5.916L278.787,5.916C278.787,19.162 289.542,29.916 302.787,29.916L302.787,34.916C299.752,34.916 297.287,37.38 297.287,40.416C297.287,43.451 299.752,45.916 302.787,45.916L302.787,48.916C299.752,48.916 297.287,51.38 297.287,54.416C297.287,57.451 299.752,59.916 302.787,59.916L302.787,62.916C299.752,62.916 297.287,65.38 297.287,68.416C297.287,71.451 299.752,73.916 302.787,73.916L302.787,76.916C299.752,76.916 297.287,79.38 297.287,82.416C297.287,85.451 299.752,87.916 302.787,87.916L302.787,90.916C299.752,90.916 297.287,93.38 297.287,96.416C297.287,99.451 299.752,101.916 302.787,101.916L302.787,104.916C299.752,104.916 297.287,107.38 297.287,110.416C297.287,113.451 299.752,115.916 302.787,115.916L302.787,118.916C299.752,118.916 297.287,121.38 297.287,124.416C297.287,127.451 299.752,129.916 302.787,129.916L302.787,135.028C300.124,135.036 297.473,135.467 294.954,136.338C291.617,137.491 288.551,139.392 286.025,141.858C283.396,144.425 281.367,147.59 280.145,151.056C279.26,153.567 278.813,156.212 278.788,158.873L278.787,159.028L24,159.028Z" fill="<?php echo $bg_color; ?>"/>
                </g>

                <!-- Stippellijn rand -->
                <g transform="translate(20.97, 10.17)">
                    <path d="M24,159.028L24,158.873C23.974,156.212 23.527,153.567 22.642,151.056C21.42,147.59 19.391,144.425 16.762,141.858C14.236,139.392 11.171,137.491 7.833,136.338C5.314,135.467 2.664,135.036 0,135.028L0,29.916C13.246,29.916 24,19.162 24,5.916L278.787,5.916C278.787,19.162 289.542,29.916 302.787,29.916L302.787,135.028C300.124,135.036 297.473,135.467 294.954,136.338C291.617,137.491 288.551,139.392 286.025,141.858C283.396,144.425 281.367,147.59 280.145,151.056C279.26,153.567 278.813,156.212 278.788,158.873L278.787,159.028L24,159.028Z"
                          fill="none"
                          stroke="<?php echo $border_color; ?>"
                          stroke-width="3"
                          stroke-dasharray="6,9"
                          transform="scale(0.871)"/>
                </g>

                <!-- Logo -->
                <?php if ( 'custom' === $logo_svg && ! empty( $atts['logo_image'] ) ) : ?>
                    <?php $logo_url = wp_get_attachment_image_url( $atts['logo_image'], 'medium' ); ?>
                    <?php if ( $logo_url ) : ?>
                    <image href="<?php echo esc_url( $logo_url ); ?>"
                           x="122" y="25"
                           width="60" height="45"
                           preserveAspectRatio="xMidYMid meet"/>
                    <?php endif; ?>
                <?php else : ?>
                    <?php echo $logo_svg; ?>
                <?php endif; ?>

                <!-- Hoofdtekst -->
                <text x="151.5" y="95"
                      text-anchor="middle"
                      fill="<?php echo $text_color; ?>"
                      font-family="'Roboto Condensed', sans-serif"
                      font-weight="700"
                      font-size="<?php echo $font_size; ?>"
                      letter-spacing="2">
                    <?php echo $main_text; ?>
                </text>

                <!-- Ondertitel -->
                <text x="151.5" y="118"
                      text-anchor="middle"
                      fill="<?php echo $text_color; ?>"
                      font-family="'Roboto Condensed', sans-serif"
                      font-weight="400"
                      font-size="12"
                      letter-spacing="1">
                    <?php echo $subtitle; ?>
                </text>
            </svg>
            <?php if ( $has_link ) : ?>
            </a>
            <?php endif; ?>
            <?php if ( $has_link && $enable_popup ) : ?>
            <div class="kzh-popup-overlay" id="<?php echo $uid; ?>_popup" role="dialog" aria-modal="true" aria-labelledby="<?php echo $uid; ?>_popup_msg">
                <div class="kzh-popup-inner">
                    <div id="<?php echo $uid; ?>_popup_msg"><?php echo $popup_text; ?></div>
                    <div class="kzh-popup-buttons">
                        <button type="button" class="kzh-btn" id="<?php echo $uid; ?>_cancel">Annuleren</button>
                        <button type="button" class="kzh-btn" id="<?php echo $uid; ?>_proceed">Verder</button>
                    </div>
                </div>
            </div>
            <script>(function(){
                var root=document.getElementById('<?php echo $uid; ?>');
                var link=root.querySelector('a.kzh-ticket-link');
                var overlay=document.getElementById('<?php echo $uid; ?>_popup');
                if(link && overlay){
                  link.addEventListener('click',function(e){ e.preventDefault(); overlay.style.display='flex'; });
                  document.getElementById('<?php echo $uid; ?>_cancel').addEventListener('click',function(){ overlay.style.display='none'; });
                  document.getElementById('<?php echo $uid; ?>_proceed').addEventListener('click',function(){ overlay.style.display='none';
                    var href='<?php echo $href; ?>'; var target='<?php echo $target; ?>';
                    if(target==='_blank'){ window.open(href,'_blank'); } else { window.location.href=href; }
                  });
                }
            })();</script>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function get_kzh_logo_paths( $color ) {
        return '<g fill="' . esc_attr( $color ) . '" transform="translate(119, 18) scale(0.45)">
            <path d="M18.976,10.453C23.618,11.404 26.597,12.396 29.709,13.957C30.8,13.206 36.372,11.815 38.846,11.935C38.58,9.5 39.903,8.25 41.24,7.609C44.664,6.998 45.648,10.273 46.127,10.493C46.326,10.313 45.163,0.689 32.043,0.6C27.914,0.66 21.822,2.792 18.976,10.453Z"/>
            <path d="M28.313,14.198C25.4,13.056 22.487,11.935 19.315,11.455C9.104,9.901 2.059,15.259 2.219,15.92L2.259,15.88C2.338,16.121 2.658,16 2.837,16.061C8.606,14.808 13.51,17.643 15.286,18.764C16.044,18.764 16.722,18.424 17.261,17.983C18.079,17.382 18.298,16.461 18.458,15.54C19.096,16.241 19.994,16.721 20.892,16.982C21.809,17.163 22.747,16.961 23.285,16.161C23.544,15.8 23.744,15.4 23.864,14.959C23.904,14.859 23.944,14.679 24.103,14.719C24.402,15.299 24.762,15.901 25.34,16.301C26.118,16.641 27.016,16.702 27.834,16.461C28.333,16.121 28.592,15.64 28.931,15.16C29.25,14.599 28.632,14.418 28.313,14.198Z"/>
            <path d="M31.045,14.619C31.045,14.619 32.642,15.7 33.3,16.361C34.158,16.401 34.377,15.36 35.016,14.959C35.215,15.28 35.035,15.74 35.155,16.061C35.355,16.942 36.133,17.663 36.971,17.903C37.868,18.204 38.746,17.843 39.424,17.262C39.783,17.081 39.943,16.541 40.382,16.641C40.282,17.462 40.481,18.263 40.961,18.905C41.539,19.585 42.337,19.766 43.195,19.766C48.455,16.46 55.637,18.864 55.637,18.864C53.542,16.009 42.07,9.601 31.045,14.619Z"/>
            <path d="M29.789,14.959C29.669,15.2 29.829,15.459 29.889,15.68C30.547,16.521 31.564,17.062 32.662,16.841C32.721,16.782 32.821,16.721 32.821,16.641C31.923,16.401 31.265,15.62 30.367,15.339C30.128,15.24 30.088,14.979 29.789,14.959Z"/>
            <path d="M29.31,15.781C28.991,16.622 28.153,17.403 27.215,17.422C26.019,17.523 24.821,17.102 24.143,16.021C23.904,16.361 23.704,16.742 23.425,17.082C22.587,17.703 21.43,18.003 20.373,17.663C19.754,17.563 19.395,16.902 18.836,16.702C18.657,17.262 18.498,17.843 18.019,18.284C17.161,19.004 16.203,19.405 15.006,19.285C14.488,19.345 14.069,19.004 13.67,18.945L13.67,19.105C14.887,19.485 15.888,20.265 17.653,21.989C21.363,20.086 26.078,19.646 30.467,19.766C34.988,19.965 40.003,22.77 41.04,23.09C41.918,22.189 42.816,21.268 43.913,20.787C44.272,20.706 44.611,20.546 44.83,20.247L44.691,20.106C44.551,20.066 44.491,20.186 44.392,20.247C42.875,20.706 41.12,20.386 40.143,19.044C40.082,18.584 39.783,18.164 39.903,17.703C39.465,17.863 39.085,18.223 38.746,18.524C37.51,19.004 36.393,18.324 35.395,17.803C35.155,17.283 34.776,16.822 34.736,16.221C34.038,16.441 33.779,17.082 33.101,17.323C31.923,17.523 30.627,17.502 29.789,16.401C29.569,16.221 29.669,15.821 29.31,15.781Z"/>
            <path d="M51.533,63.024C51.074,64.165 50.456,65.167 49.618,66.109C48.522,67.75 46.726,68.892 45.07,69.753C44.512,70.134 43.913,70.374 43.254,70.474C43.075,70.374 43.374,70.134 43.355,69.954C43.754,68.091 43.454,66.248 41.918,65.047C40.182,63.885 38.107,65.087 36.451,65.728C32.781,67.491 29.071,69.353 24.522,68.892C18.816,68.873 13.949,66.83 9.121,64.706L8.064,64.086C8.503,63.986 8.961,63.925 9.4,63.805C10.857,63.144 12.652,61.943 12.991,60.241C13.909,60.38 14.827,60.702 15.725,61.062C20.612,62.844 25.34,64.967 29.649,68.171L29.889,68.171C31.883,66.589 33.958,64.947 36.113,63.645C39.125,62.364 41.998,60.742 45.35,60.241C45.369,61.943 45.23,63.946 43.494,64.907C43.475,65.007 43.494,65.087 43.594,65.147C45.05,64.887 45.748,63.245 46.067,61.963C46.426,61.302 45.648,59.66 46.945,59.9C47.923,59.74 49.219,59.86 50.197,59.62C50.297,59.139 49.719,58.838 49.479,58.458C49.14,57.757 48.461,57.216 48.182,56.496C48.621,56.215 49.239,56.215 49.758,56.295C50.557,56.355 51.154,57.037 51.573,57.697C52.291,59.299 52.112,61.422 51.533,63.024Z"/>
            <path d="M42.676,70.474C42.537,70.254 42.316,69.873 41.998,69.753C40.402,69.453 38.826,70.374 37.369,71.015C35.414,71.576 33.679,72.658 31.704,73.178C30.607,73.599 29.43,73.819 28.213,73.979C23.445,74.701 18.697,73.839 14.288,72.698C13.151,72.357 12.114,71.836 10.977,71.496C10.777,71.496 10.637,71.316 10.458,71.255C8.902,70.615 7.426,69.873 5.949,69.092C5.311,68.612 4.653,68.432 4.094,67.731C2.877,66.99 1.78,65.768 0.922,64.666C0.324,63.445 0.743,61.823 1.5,60.761C2.02,60.281 2.358,59.7 3.037,59.52C3.117,59.8 3.256,60.1 3.276,60.34C3.675,60.942 3.974,61.602 4.473,62.164C6.708,64.346 9.44,65.307 12.034,66.589C13.371,67.009 14.647,67.55 15.964,68.031C17.161,68.331 18.398,68.572 19.595,68.892C24.522,69.774 30.547,69.814 34.676,67.07C36.691,66.329 38.547,65.047 40.9,65.288C42.178,65.707 42.816,67.07 43.055,68.271C43.075,69.052 43.096,69.873 42.676,70.474Z"/>
        </g>';
    }

    private static function get_hjc_logo_paths( $color ) {
        return '<g fill="' . esc_attr( $color ) . '" transform="translate(132, 20) scale(0.7)">
            <!-- Nar/Jester silhouette -->
            <path d="M30,5 C25,5 22,8 22,12 C22,14 23,16 25,17 L23,25 C20,26 18,29 18,33 C18,38 22,42 27,42 L27,55 C27,58 29,60 32,60 C35,60 37,58 37,55 L37,42 C42,42 46,38 46,33 C46,29 44,26 41,25 L39,17 C41,16 42,14 42,12 C42,8 39,5 34,5 L30,5 Z"/>
            <!-- Bells/points on hat -->
            <circle cx="20" cy="8" r="3"/>
            <circle cx="44" cy="8" r="3"/>
            <circle cx="32" cy="2" r="3"/>
        </g>';
    }
}
