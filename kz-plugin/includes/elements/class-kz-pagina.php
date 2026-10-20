<?php
/**
 * KZ-Pagina — shortcode [kz_pagina pagina="5dd"]
 *
 * Tekent een hele pagina uit vaste secties: kop, intro, dag, tekst + foto,
 * tickets, vragen en motto. De inhoud (teksten, knoppen, foto's) beheer je in
 * KZ-Studio (Website → Pagina's); de opmaak staat hier vast, zodat er niets
 * verspringt zoals bij losse WPBakery-blokken.
 *
 * De gepubliceerde versie staat in Supabase. Deze klasse haalt hem op via de
 * openbare functie website_pagina_ophalen() (KZ-Studio, migratie 0044) met
 * hetzelfde Supabase-adres en dezelfde anon-key als de Link-In-Bio, bewaart hem
 * een minuut en valt bij een storing terug op de laatste goede versie.
 *
 * Houd gelijk met KZ-Studio (src/lib/paginaSjablonen.ts): de velden per
 * sjabloon (schoon_inhoud()), LIMIET, kleuren() en tekst_html(). Het voorbeeld
 * daar gebruikt dezelfde klassen en een kopie van assets/css/kz-pagina.css.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Element_Pagina {

    const SHORTCODE = 'kz_pagina';

    /** Per pagina: kz_pagina_laatst_{slug}, de laatste goede versie. */
    const OPTIE_LAATST = 'kz_pagina_laatst_';
    /** Per pagina: wanneer en met welk resultaat er voor het laatst is opgehaald. */
    const OPTIE_STATUS = 'kz_pagina_status';
    const CACHE        = 'kz_pagina_';
    const CACHE_SECONDEN = 60;
    /** Hoe lang LiteSpeed Cache een pagina met KZ-Pagina mag bewaren. */
    const PAGINA_CACHE_SECONDEN = 120;

    /** Maximale lengtes en aantallen; gelijk aan LIMIET in paginaSjablonen.ts. */
    const LIMIET = array(
        'titel'          => 80,
        'intro'          => 400,
        'tekst'          => 4000,
        'onderdeelTitel' => 120,
        'tijd'           => 20,
        'knopTekst'      => 40,
        'url'            => 2000,
        'hoofdtekst'     => 30,
        'ondertitel'     => 40,
        'popup'          => 300,
        'vraag'          => 200,
        'antwoord'       => 2000,
        'slottekst'      => 600,
        'onderdelen'     => 12,
        'fotos'          => 8,
        'tickets'        => 6,
        'groepen'        => 8,
        'vragen'         => 20,
    );

    const SJABLONEN = array( 'kop', 'intro', 'dag', 'tekst_foto', 'tickets', 'faq', 'motto' );

    public static function register_shortcode() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
    }

    public static function register_vc_map() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }

        vc_map(
            array(
                'name'        => 'KZ-Pagina',
                'base'        => self::SHORTCODE,
                'description' => __( 'Een hele pagina uit secties; de inhoud beheer je in KZ-Studio (Website → Pagina\'s).', 'kz-plugin' ),
                'category'    => 'Kraonige Zwaone',
                'icon'        => 'kz-icon-pagina',
                'params'      => array(
                    array(
                        'type'        => 'textfield',
                        'heading'     => __( 'Pagina', 'kz-plugin' ),
                        'param_name'  => 'pagina',
                        'admin_label' => true,
                        'description' => __( 'De naam van de pagina in KZ-Studio, bijvoorbeeld 5dd. Knop "Kopieer shortcode" in KZ-Studio geeft hem ook.', 'kz-plugin' ),
                    ),
                    array(
                        'type'        => 'dropdown',
                        'heading'     => __( 'Breedte', 'kz-plugin' ),
                        'param_name'  => 'breedte',
                        'value'       => array(
                            __( 'Volle breedte van het venster', 'kz-plugin' ) => 'vol',
                            __( 'Breedte van de kolom', 'kz-plugin' )          => 'kolom',
                        ),
                        'std'         => 'vol',
                        'description' => __( 'Volle breedte: de rode en witte banden lopen door tot de rand van het scherm.', 'kz-plugin' ),
                    ),
                ),
            )
        );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'pagina'  => '',
                'breedte' => 'vol',
            ),
            $atts,
            self::SHORTCODE
        );

        $slug = strtolower( trim( (string) $atts['pagina'] ) );
        if ( ! preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug ) || strlen( $slug ) > 60 ) {
            return self::uitleg( 'geen geldige pagina-naam opgegeven' );
        }

        self::kort_cachen();

        $data = self::gegevens( $slug );
        if ( null === $data ) {
            return self::uitleg( 'nog geen gegevens uit KZ-Studio opgehaald voor "' . $slug . '"', $slug );
        }
        if ( empty( $data['gevonden'] ) ) {
            return self::uitleg( '"' . $slug . '" is in KZ-Studio niet gepubliceerd, staat in de prullenbak, of heet daar anders', $slug );
        }

        wp_enqueue_style( 'kz-evenement-info-fonts' );
        wp_enqueue_style( 'kz-pagina-style' );

        $secties = $data['secties'];
        $opmaak  = self::kleuren( $secties );

        $html = '';
        foreach ( $secties as $n => $sectie ) {
            $html .= self::sectie( $sectie, $opmaak[ $n ] );
        }

        $klasse = 'kz-pagina kzp' . ( 'kolom' === $atts['breedte'] ? '' : ' kzp--vol' );
        return '<div class="' . esc_attr( $klasse ) . '">' . $html . '</div>';
    }

    /**
     * Vraagt LiteSpeed Cache om deze pagina maar kort te bewaren. De inhoud komt
     * uit KZ-Studio en verandert na Publiceren daar, zonder dat WordPress het
     * merkt; met de standaard-TTL van LiteSpeed zagen bezoekers (niet
     * ingelogd) dan nog lang de oude versie. Doet niets zonder LiteSpeed.
     */
    private static function kort_cachen() {
        do_action( 'litespeed_control_set_ttl', self::PAGINA_CACHE_SECONDEN );
    }

    /**
     * Voor redacteuren een zichtbaar geel blokje waarom er niets staat, met de
     * laatste ophaalstatus van deze pagina; bezoekers zien niets. Zichtbaar en
     * niet alleen als HTML-commentaar: LiteSpeed Cache haalt commentaar bij
     * "HTML minify" uit de pagina, en dan was de reden onvindbaar.
     */
    private static function uitleg( $reden, $slug = '' ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return '';
        }
        $regels = array( '<strong>KZ-Pagina</strong> (alleen zichtbaar voor redacteuren): ' . esc_html( $reden ) . '.' );

        $status = get_option( self::OPTIE_STATUS );
        if ( '' !== $slug && is_array( $status ) && isset( $status[ $slug ] ) && is_array( $status[ $slug ] ) ) {
            $st       = $status[ $slug ];
            $regels[] = 'Laatst opgehaald: ' . esc_html( wp_date( 'j F Y H:i:s', (int) $st['tijd'] ) ) . ' &mdash; '
                . ( $st['ok'] ? 'gelukt' : 'mislukt' ) . ' (' . esc_html( $st['melding'] ) . ').';
        }
        $regels[] = 'Net gepubliceerd? De site haalt hooguit eens per minuut op; onder KZ Plugin &rarr; Pagina\'s kun je dat meteen laten doen.';

        return '<!-- KZ-Pagina: ' . esc_html( $reden ) . ' -->'
            . '<div class="kz-pagina-uitleg" style="margin:24px auto;max-width:900px;padding:14px 18px;background:#fff6d6;border:2px dashed #b88a00;color:#111;font:15px/1.5 -apple-system,BlinkMacSystemFont,sans-serif;text-align:left;">'
            . implode( '<br>', $regels )
            . '</div>';
    }

    // ------------------------------------------------------------------
    // Tekenen
    // ------------------------------------------------------------------

    /**
     * Kleur en fotokant per sectie: bij "auto" om en om (de eerste band rood,
     * de eerste foto's links), geteld over de banden en de fotosecties. Gelijk
     * aan kleurenVan() in KZ-Studio.
     */
    public static function kleuren( array $secties ) {
        $uit   = array();
        $band  = 0;
        $fotos = 0;
        foreach ( $secties as $n => $s ) {
            $achtergrond = 'wit';
            $fotokant    = 'links';
            if ( in_array( $s['sjabloon'], array( 'intro', 'dag', 'tekst_foto', 'motto' ), true ) ) {
                $k           = isset( $s['inhoud']['kleur'] ) ? $s['inhoud']['kleur'] : 'auto';
                $achtergrond = 'auto' === $k ? ( 0 === $band % 2 ? 'rood' : 'wit' ) : $k;
                $band++;
            }
            if ( in_array( $s['sjabloon'], array( 'dag', 'tekst_foto' ), true ) ) {
                $k        = isset( $s['inhoud']['fotokant'] ) ? $s['inhoud']['fotokant'] : 'auto';
                $fotokant = 'auto' === $k ? ( 0 === $fotos % 2 ? 'links' : 'rechts' ) : $k;
                $fotos++;
            }
            $uit[ $n ] = array(
                'achtergrond' => $achtergrond,
                'fotokant'    => $fotokant,
            );
        }
        return $uit;
    }

    private static function sectie( array $s, array $opmaak ) {
        $i    = $s['inhoud'];
        $band = 'kzp__sectie kzp__band kzp__band--' . $opmaak['achtergrond'];
        $kant = 'kzp--foto-' . $opmaak['fotokant'];

        switch ( $s['sjabloon'] ) {
            case 'kop':
                if ( '' === trim( $i['titel'] ) ) {
                    return '';
                }
                return '<section class="kzp__sectie kzp__kop"><div class="kzp__binnen">'
                    . self::titel( $i['titel'], 'kzp__kop-titel', 'h1' )
                    . '</div></section>';

            case 'intro':
                $beeld = self::veilige_foto( $i['afbeelding'] );
                if ( '' === $beeld ) {
                    $beeld = KZ_PLUGIN_URL . 'assets/img/' . ( 'rood' === $opmaak['achtergrond'] ? 'kz-logo-wit.svg' : 'kz-logo-rood.svg' );
                }
                return '<section class="' . esc_attr( $band . ' kzp__intro' ) . '"><div class="kzp__binnen kzp__raster kzp__raster--8-4">'
                    . '<div class="kzp__tekstkolom">'
                    . self::titel( $i['titel'] )
                    . ( '' !== trim( $i['intro'] ) ? '<p class="kzp__intro-tekst">' . esc_html( $i['intro'] ) . '</p>' : '' )
                    . self::tekst_blok( $i['tekst'], 'kzp__tekst' )
                    . self::knop( $i['knop'], $opmaak['achtergrond'] )
                    . '</div>'
                    . '<div class="kzp__beeldkolom"><img class="kzp__logo" src="' . esc_url( $beeld ) . '" alt="" decoding="async"></div>'
                    . '</div></section>';

            case 'dag':
                $onderdelen = '';
                foreach ( $i['onderdelen'] as $o ) {
                    if ( $o['verborgen'] ) {
                        continue;
                    }
                    // De tijd als klein contrastlabel boven de titel.
                    $kop = '' !== trim( $o['tijd'] ) ? '<p class="kzp__tijd"><span>' . esc_html( trim( $o['tijd'] ) ) . '</span></p>' : '';
                    if ( '' !== trim( $o['titel'] ) ) {
                        $kop .= '<h3 class="kzp__onderdeel-titel">' . esc_html( $o['titel'] ) . '</h3>';
                    }
                    $onderdelen .= '<article class="kzp__onderdeel">' . $kop . self::tekst_blok( $o['tekst'], 'kzp__tekst' ) . self::knop( $o['knop'], $opmaak['achtergrond'] ) . '</article>';
                }
                $fotos = '';
                foreach ( $i['fotos'] as $f ) {
                    $fotos .= self::foto( $f );
                }
                return '<section class="' . esc_attr( $band . ' kzp__dag ' . $kant ) . '"><div class="kzp__binnen kzp__raster kzp__raster--4-8">'
                    . '<div class="kzp__tekstkolom">' . self::titel( $i['titel'] ) . $onderdelen . '</div>'
                    . ( '' !== $fotos ? '<div class="kzp__beeldkolom kzp__fotos">' . $fotos . '</div>' : '' )
                    . '</div></section>';

            case 'tekst_foto':
                $foto = self::foto( $i['foto'] );
                return '<section class="' . esc_attr( $band . ' kzp__tekst-foto ' . $kant ) . '"><div class="kzp__binnen kzp__raster kzp__raster--6-6">'
                    . '<div class="kzp__tekstkolom">' . self::titel( $i['titel'] ) . self::tekst_blok( $i['tekst'], 'kzp__tekst' ) . self::knop( $i['knop'], $opmaak['achtergrond'] ) . '</div>'
                    . ( '' !== $foto ? '<div class="kzp__beeldkolom">' . $foto . '</div>' : '' )
                    . '</div></section>';

            case 'tickets':
                $tickets = '';
                foreach ( $i['tickets'] as $t ) {
                    if ( $t['verborgen'] ) {
                        continue;
                    }
                    $tickets .= '<div class="kzp__ticket">' . self::ticket( $t ) . '</div>';
                }
                return '<section class="kzp__sectie kzp__tickets-sectie"><div class="kzp__binnen">'
                    . self::titel( $i['titel'], 'kzp__titel kzp__tickets-titel' )
                    . '<div class="kzp__tickets">' . $tickets . '</div>'
                    . '</div></section>';

            case 'faq':
                $groepen = '';
                foreach ( $i['groepen'] as $g ) {
                    if ( $g['verborgen'] ) {
                        continue;
                    }
                    $groepen .= '<div class="kzp__groep">';
                    if ( '' !== trim( $g['titel'] ) ) {
                        $groepen .= '<h3 class="kzp__groep-titel"><span>' . esc_html( $g['titel'] ) . '</span></h3>';
                    }
                    foreach ( $g['vragen'] as $v ) {
                        if ( $v['verborgen'] || '' === trim( $v['vraag'] ) ) {
                            continue;
                        }
                        $groepen .= '<details class="kzp__vraag"><summary><span>' . esc_html( $v['vraag'] ) . '</span></summary>'
                            . self::tekst_blok( $v['antwoord'], 'kzp__tekst kzp__antwoord' )
                            . '</details>';
                    }
                    $groepen .= '</div>';
                }
                return '<section class="kzp__sectie kzp__faq"><div class="kzp__binnen kzp__smal">'
                    . self::titel( $i['titel'], 'kzp__kop-titel' )
                    . $groepen
                    . self::tekst_blok( $i['slottekst'], 'kzp__tekst kzp__slot' )
                    . '</div></section>';

            case 'motto':
                if ( ! class_exists( 'KZ_Element_Motto' ) ) {
                    return '';
                }
                $motto = KZ_Element_Motto::render(
                    array(
                        'variant'     => $i['variant'],
                        'marge_boven' => '0',
                        'marge_onder' => '0',
                    )
                );
                if ( '' === $motto ) {
                    return '';
                }
                // De motto-svg's zijn wit. Op een witte band kleurt een SVG-filter
                // ze rood (#E83F35); dat werkt ook voor een svg van een ander domein.
                $filter = 'wit' === $opmaak['achtergrond'] ? self::rood_filter() : '';
                return '<section class="' . esc_attr( $band . ' kzp__motto' ) . '">' . $filter . '<div class="kzp__binnen">' . $motto . '</div></section>';
        }
        return '';
    }

    /** Een onzichtbaar SVG-filter dat alles wat niet doorzichtig is #E83F35 maakt. */
    private static function rood_filter() {
        return '<svg class="kzp__filter" width="0" height="0" aria-hidden="true" focusable="false"><filter id="kzp-rood" color-interpolation-filters="sRGB">'
            . '<feColorMatrix type="matrix" values="0 0 0 0 0.9098 0 0 0 0 0.2471 0 0 0 0 0.2078 0 0 0 1 0"/></filter></svg>';
    }

    private static function titel( $tekst, $klasse = 'kzp__titel', $tag = 'h2' ) {
        if ( '' === trim( $tekst ) ) {
            return '';
        }
        return '<' . $tag . ' class="' . esc_attr( $klasse ) . '"><span>' . esc_html( $tekst ) . '</span></' . $tag . '>';
    }

    private static function tekst_blok( $tekst, $klasse ) {
        $html = self::tekst_html( $tekst );
        return '' === $html ? '' : '<div class="' . esc_attr( $klasse ) . '">' . $html . '</div>';
    }

    /**
     * Een knop via het element KZ-Knop zelf, zodat hij precies gelijk is aan
     * elke andere KZ-Knop op de site: wit op een rode band, rood op wit.
     * KZ-Knop leest zijn link met vc_build_link() (url:…), net als in WPBakery.
     */
    private static function knop( array $knop, $achtergrond ) {
        $url = self::veilige_url( $knop['url'] );
        if ( '' === $url || '' === trim( $knop['tekst'] ) || ! class_exists( 'KZ_Element_Button' ) ) {
            return '';
        }
        return KZ_Element_Button::render(
            array(
                'text'   => $knop['tekst'],
                'link'   => 'url:' . rawurlencode( html_entity_decode( $url ) ),
                'color'  => 'rood' === $achtergrond ? 'wit' : 'rood',
                'align'  => 'left',
                'margin' => '28px 0 0',
            )
        );
    }

    private static function foto( array $foto ) {
        $url = self::veilige_foto( $foto['url'] );
        if ( '' === $url ) {
            return '';
        }
        if ( $foto['heel'] ) {
            return '<figure class="kzp__foto kzp__foto--heel"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async"></a></figure>';
        }
        $positie = (int) $foto['x'] . '% ' . (int) $foto['y'] . '%';
        return '<figure class="kzp__foto"><img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async" style="object-position:' . esc_attr( $positie ) . '"></figure>';
    }

    /** Een ticket via het bestaande element KZ-Ticket, met dezelfde vorm en kleuren. */
    private static function ticket( array $t ) {
        if ( ! class_exists( 'KZ_Element_Ticket' ) ) {
            return '';
        }
        $url = self::veilige_url( $t['url'] );
        // KZ-Ticket leest zijn link met vc_build_link() als WPBakery er is
        // (url:…|target:…), en anders als gewoon adres.
        $link = '' === $url ? '' : ( function_exists( 'vc_build_link' ) ? 'url:' . rawurlencode( html_entity_decode( $url ) ) : $url );
        return KZ_Element_Ticket::render(
            array(
                'logo_type'    => 'kzh',
                'main_text'    => $t['hoofdtekst'],
                'subtitle'     => $t['ondertitel'],
                'color_preset' => $t['kleur'],
                'link'         => $link,
                'enable_popup' => '' !== $link && '' !== trim( $t['popup'] ) ? 'true' : '',
                'popup_text'   => esc_html( $t['popup'] ),
            )
        );
    }

    /**
     * Zet de tekst van een veld om naar HTML, precies zoals tekstNaarHtml() in
     * KZ-Studio. Een witregel begint een nieuwe alinea, een losse enter wordt
     * een <br>; verder ### tussenkop, - opsomming, **vet**, *cursief* en
     * [tekst](https://…). Alles wordt eerst ge-escapet.
     */
    public static function tekst_html( $invoer ) {
        // Een harde spatie (uit geplakte tekst) telt als gewone spatie.
        $invoer = trim( str_replace( array( "\r\n", "\r", "\xC2\xA0" ), array( "\n", "\n", ' ' ), (string) $invoer ) );
        if ( '' === $invoer ) {
            return '';
        }
        $uit = '';
        foreach ( preg_split( '/\n[ \t]*\n+/', $invoer ) as $blok ) {
            if ( '' === trim( $blok ) ) {
                continue;
            }
            $alinea = array();
            $lijst  = array();
            foreach ( explode( "\n", $blok ) as $regel ) {
                if ( preg_match( '/^\s*###\s+(.+)$/u', $regel, $m ) ) {
                    $uit   .= self::sluit_alinea( $alinea ) . self::sluit_lijst( $lijst );
                    $uit   .= '<h4>' . self::regel( trim( $m[1] ) ) . '</h4>';
                } elseif ( preg_match( '/^\s*[-•]\s+(.+)$/u', $regel, $m ) ) {
                    $uit    .= self::sluit_alinea( $alinea );
                    $lijst[] = self::regel( trim( $m[1] ) );
                } elseif ( '' !== trim( $regel ) ) {
                    $uit     .= self::sluit_lijst( $lijst );
                    $alinea[] = self::regel( trim( $regel ) );
                }
            }
            $uit .= self::sluit_alinea( $alinea ) . self::sluit_lijst( $lijst );
        }
        return $uit;
    }

    private static function sluit_alinea( array &$alinea ) {
        $html   = $alinea ? '<p>' . implode( '<br>', $alinea ) . '</p>' : '';
        $alinea = array();
        return $html;
    }

    private static function sluit_lijst( array &$lijst ) {
        $html  = $lijst ? '<ul><li>' . implode( '</li><li>', $lijst ) . '</li></ul>' : '';
        $lijst = array();
        return $html;
    }

    private static function regel( $s ) {
        $s = htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' );
        $s = preg_replace_callback(
            '/\[([^\]\n]+)\]\(([^)\s]+)\)/u',
            function ( $m ) {
                $url = self::veilige_url( str_replace( '&amp;', '&', $m[2] ) );
                return '' === $url ? $m[0] : '<a href="' . $url . '">' . $m[1] . '</a>';
            },
            $s
        );
        $s = preg_replace( '/\*\*([^*\n]+)\*\*/u', '<strong>$1</strong>', $s );
        $s = preg_replace( '/(^|[^*])\*([^*\n]+)\*(?!\*)/u', '$1<em>$2</em>', $s );
        // Overgebleven blokhaken onschadelijk, zodat er nooit een shortcode in
        // de uitvoer staat (zelfde als wpTekstBlok() in KZ-Studio).
        return str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), $s );
    }

    /** Zelfde regel als isGeldigeUrl() in KZ-Studio; '' voor alles daarbuiten. */
    public static function veilige_url( $url ) {
        return KZ_Link_In_Bio::veilige_url( $url );
    }

    /** Alleen https-foto's. */
    public static function veilige_foto( $url ) {
        return KZ_Link_In_Bio::veilige_foto( $url );
    }

    // ------------------------------------------------------------------
    // Ophalen
    // ------------------------------------------------------------------

    /**
     * De gepubliceerde pagina: uit de cache, anders vers uit Supabase, en als
     * dat mislukt de laatste goede versie. Null als er nog nooit iets is
     * opgehaald.
     *
     * @return array{gevonden:bool,titel:string,secties:array}|null
     */
    public static function gegevens( $slug ) {
        $cache = get_transient( self::CACHE . $slug );
        if ( is_array( $cache ) ) {
            return $cache;
        }

        $vers = self::ophalen( $slug );
        if ( is_array( $vers ) ) {
            set_transient( self::CACHE . $slug, $vers, self::CACHE_SECONDEN );
            update_option( self::OPTIE_LAATST . $slug, $vers, false );
            return $vers;
        }

        $laatst = get_option( self::OPTIE_LAATST . $slug );
        if ( is_array( $laatst ) ) {
            // Kort cachen, zodat een storing bij Supabase niet elke bezoeker
            // laat wachten.
            set_transient( self::CACHE . $slug, $laatst, self::CACHE_SECONDEN );
            return $laatst;
        }
        return null;
    }

    /** Leegt de cache van alle pagina's die ooit zijn opgehaald. */
    public static function vergeet_cache() {
        $status = get_option( self::OPTIE_STATUS );
        if ( is_array( $status ) ) {
            foreach ( array_keys( $status ) as $slug ) {
                delete_transient( self::CACHE . $slug );
            }
        }
    }

    /** Roept website_pagina_ophalen() aan; geeft de opgeschoonde pagina terug, of null bij een fout. */
    public static function ophalen( $slug ) {
        $url     = untrailingslashit( (string) get_option( KZ_Link_In_Bio::OPTIE_URL, KZ_Link_In_Bio::STANDAARD_URL ) );
        $sleutel = trim( (string) get_option( KZ_Link_In_Bio::OPTIE_SLEUTEL, '' ) );

        if ( '' === $url || '' === $sleutel ) {
            self::zet_status( $slug, false, 'Supabase-adres of anon-key ontbreekt (KZ Plugin → Link-In-Bio).' );
            return null;
        }

        $antwoord = wp_remote_post(
            $url . '/rest/v1/rpc/website_pagina_ophalen',
            array(
                'timeout' => 5,
                'headers' => array(
                    'apikey'        => $sleutel,
                    'Authorization' => 'Bearer ' . $sleutel,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                'body'    => wp_json_encode( array( 'p_slug' => $slug ) ),
            )
        );

        if ( is_wp_error( $antwoord ) ) {
            self::zet_status( $slug, false, $antwoord->get_error_message() );
            return null;
        }
        $code = wp_remote_retrieve_response_code( $antwoord );
        $json = json_decode( wp_remote_retrieve_body( $antwoord ), true );
        if ( 200 !== $code || ! is_array( $json ) ) {
            $melding = is_array( $json ) && isset( $json['message'] ) ? $json['message'] : 'HTTP ' . $code;
            self::zet_status( $slug, false, $melding );
            return null;
        }

        $schoon = self::schoon( $json );
        self::zet_status( $slug, true, $schoon['gevonden'] ? count( $schoon['secties'] ) . ' secties' : 'niet gepubliceerd' );
        return $schoon;
    }

    private static function zet_status( $slug, $ok, $melding ) {
        $status = get_option( self::OPTIE_STATUS );
        if ( ! is_array( $status ) ) {
            $status = array();
        }
        $status[ $slug ] = array(
            'ok'      => (bool) $ok,
            'melding' => (string) $melding,
            'tijd'    => time(),
        );
        update_option( self::OPTIE_STATUS, $status, false );
    }

    /** Alleen bekende sjablonen en velden, ingekort tot LIMIET. */
    public static function schoon( array $json ) {
        $secties = array();
        if ( ! empty( $json['gevonden'] ) && isset( $json['secties'] ) && is_array( $json['secties'] ) ) {
            foreach ( $json['secties'] as $s ) {
                if ( ! is_array( $s ) || ! isset( $s['sjabloon'] ) || ! in_array( $s['sjabloon'], self::SJABLONEN, true ) ) {
                    continue;
                }
                $secties[] = array(
                    'sjabloon' => $s['sjabloon'],
                    'inhoud'   => self::schoon_inhoud( $s['sjabloon'], isset( $s['inhoud'] ) && is_array( $s['inhoud'] ) ? $s['inhoud'] : array() ),
                );
            }
        }
        return array(
            'gevonden' => ! empty( $json['gevonden'] ),
            'titel'    => self::tekst( $json, 'titel', 120 ),
            'secties'  => $secties,
        );
    }

    /** Gelijk aan normaliseer() in KZ-Studio. */
    private static function schoon_inhoud( $sjabloon, array $r ) {
        $l = self::LIMIET;
        switch ( $sjabloon ) {
            case 'kop':
                return array( 'titel' => self::tekst( $r, 'titel', $l['titel'] ) );

            case 'intro':
                return array(
                    'titel'      => self::tekst( $r, 'titel', $l['titel'] ),
                    'intro'      => self::tekst( $r, 'intro', $l['intro'] ),
                    'tekst'      => self::tekst( $r, 'tekst', $l['tekst'] ),
                    'afbeelding' => self::tekst( $r, 'afbeelding', $l['url'] ),
                    'knop'       => self::schoon_knop( $r ),
                    'kleur'      => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
                );

            case 'dag':
                $onderdelen = array();
                foreach ( self::lijst( $r, 'onderdelen', $l['onderdelen'] ) as $o ) {
                    $onderdelen[] = array(
                        'titel' => self::tekst( $o, 'titel', $l['onderdeelTitel'] ),
                        'tijd'  => self::tekst( $o, 'tijd', $l['tijd'] ),
                        'tekst' => self::tekst( $o, 'tekst', $l['tekst'] ),
                        'knop'  => self::schoon_knop( $o ),
                        'verborgen' => self::verborgen( $o ),
                    );
                }
                $fotos = array();
                foreach ( self::lijst( $r, 'fotos', $l['fotos'] ) as $f ) {
                    $fotos[] = self::schoon_foto( $f );
                }
                return array(
                    'titel'      => self::tekst( $r, 'titel', $l['titel'] ),
                    'onderdelen' => $onderdelen,
                    'fotos'      => $fotos,
                    'kleur'      => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
                    'fotokant'   => self::keuze( $r, 'fotokant', array( 'auto', 'links', 'rechts' ), 'auto' ),
                );

            case 'tekst_foto':
                return array(
                    'titel'    => self::tekst( $r, 'titel', $l['titel'] ),
                    'tekst'    => self::tekst( $r, 'tekst', $l['tekst'] ),
                    'foto'     => self::schoon_foto( isset( $r['foto'] ) && is_array( $r['foto'] ) ? $r['foto'] : array() ),
                    'knop'     => self::schoon_knop( $r ),
                    'kleur'    => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
                    'fotokant' => self::keuze( $r, 'fotokant', array( 'auto', 'links', 'rechts' ), 'auto' ),
                );

            case 'tickets':
                $tickets = array();
                foreach ( self::lijst( $r, 'tickets', $l['tickets'] ) as $t ) {
                    $tickets[] = array(
                        'hoofdtekst' => self::tekst( $t, 'hoofdtekst', $l['hoofdtekst'] ),
                        'ondertitel' => self::tekst( $t, 'ondertitel', $l['ondertitel'] ),
                        'url'        => self::tekst( $t, 'url', $l['url'] ),
                        'kleur'      => self::keuze( $t, 'kleur', array( 'rood', 'wit', 'hjc' ), 'rood' ),
                        'popup'      => self::tekst( $t, 'popup', $l['popup'] ),
                        'verborgen'  => self::verborgen( $t ),
                    );
                }
                return array(
                    'titel'   => self::tekst( $r, 'titel', $l['titel'] ),
                    'tickets' => $tickets,
                );

            case 'motto':
                return array(
                    'variant' => self::keuze( $r, 'variant', array( 'keuze', 'kraonig', 'seizoen', 'elfelf' ), 'keuze' ),
                    'kleur'   => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
                );

            case 'faq':
                $groepen = array();
                foreach ( self::lijst( $r, 'groepen', $l['groepen'] ) as $g ) {
                    $vragen = array();
                    foreach ( self::lijst( $g, 'vragen', $l['vragen'] ) as $v ) {
                        $vragen[] = array(
                            'vraag'    => self::tekst( $v, 'vraag', $l['vraag'] ),
                            'antwoord' => self::tekst( $v, 'antwoord', $l['antwoord'] ),
                            'verborgen' => self::verborgen( $v ),
                        );
                    }
                    $groepen[] = array(
                        'titel'  => self::tekst( $g, 'titel', $l['titel'] ),
                        'vragen' => $vragen,
                        'verborgen' => self::verborgen( $g ),
                    );
                }
                return array(
                    'titel'     => self::tekst( $r, 'titel', $l['titel'] ),
                    'groepen'   => $groepen,
                    'slottekst' => self::tekst( $r, 'slottekst', $l['slottekst'] ),
                );
        }
        return array();
    }

    private static function tekst( array $r, $sleutel, $max ) {
        if ( ! isset( $r[ $sleutel ] ) || ! is_string( $r[ $sleutel ] ) ) {
            return '';
        }
        return mb_substr( $r[ $sleutel ], 0, $max );
    }

    /** Een onderdeel, ticket, groep of vraag die in KZ-Studio is verborgen. */
    private static function verborgen( array $r ) {
        return isset( $r['verborgen'] ) && true === $r['verborgen'];
    }

    private static function lijst( array $r, $sleutel, $max ) {
        if ( ! isset( $r[ $sleutel ] ) || ! is_array( $r[ $sleutel ] ) ) {
            return array();
        }
        return array_values( array_filter( array_slice( $r[ $sleutel ], 0, $max ), 'is_array' ) );
    }

    private static function keuze( array $r, $sleutel, array $opties, $standaard ) {
        return isset( $r[ $sleutel ] ) && in_array( $r[ $sleutel ], $opties, true ) ? $r[ $sleutel ] : $standaard;
    }

    private static function schoon_knop( array $r ) {
        $k = isset( $r['knop'] ) && is_array( $r['knop'] ) ? $r['knop'] : array();
        return array(
            'tekst' => self::tekst( $k, 'tekst', self::LIMIET['knopTekst'] ),
            'url'   => self::tekst( $k, 'url', self::LIMIET['url'] ),
        );
    }

    private static function schoon_foto( array $f ) {
        $getal = function ( $sleutel ) use ( $f ) {
            return isset( $f[ $sleutel ] ) && is_numeric( $f[ $sleutel ] ) ? max( 0, min( 100, (float) $f[ $sleutel ] ) ) : 50;
        };
        return array(
            'url'  => self::tekst( $f, 'url', self::LIMIET['url'] ),
            'x'    => $getal( 'x' ),
            'y'    => $getal( 'y' ),
            'heel' => isset( $f['heel'] ) && true === $f['heel'],
        );
    }
}
