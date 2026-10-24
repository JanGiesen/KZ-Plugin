<?php
/**
 * KZ-Pagina — shortcode [kz_pagina pagina="5dd"]
 *
 * Tekent een hele pagina uit vaste secties: kop, geleding-kop, intro, dag, tekst, foto's, tekst + foto,
 * route, tickets (KZ-Evenement Info), uitslagen, video, vakken (KZ-Vindt Grid), kaart, datum + sync, vragen, HTML en motto. De inhoud (teksten, knoppen, foto's) beheer je in
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
        'label'          => 40,
        'omschrijving'   => 2000,
        'feitLabel'      => 20,
        'feitWaarde'     => 120,
        'dagTekst'       => 40,
        'seizoen'        => 9,
        'categorie'      => 60,
        'kleineTekst'    => 300,
        'vraag'          => 200,
        'antwoord'       => 2000,
        'slottekst'      => 600,
        'onderdelen'     => 12,
        'fotos'          => 8,
        'feiten'         => 8,
        'blokken'        => 6,
        'groepen'        => 8,
        'vragen'         => 20,
        'tekstIntro'     => 1000,
        'tekstBlokken'   => 12,
        'stap'           => 200,
        'afstand'        => 40,
        'stappen'        => 40,
        'jaar'           => 20,
        'jaren'          => 20,
        'categorieen'    => 6,
        'rijen'          => 60,
        'uitslagNaam'    => 120,
        'vakken'         => 24,
        'vakTekst'       => 40,
        'kolomKop'       => 30,
        'ondertitel'     => 120,
        'adres'          => 200,
        'datumTekst'     => 60,
        'galerij'        => 12,
        'html'           => 20000,
    );

    /**
     * De pijltjes bij een routestap: paden in een 24×24-viewBox, met een lijn
     * in de tekstkleur. Gelijk aan ROUTE_ICONEN in KZ-Studio.
     */
    const ROUTE_ICONEN = array(
        'rechtdoor'    => 'M12 20V4M6 10l6-6 6 6',
        'links'        => 'M18 20v-7a4 4 0 0 0-4-4H5M9 5 5 9l4 4',
        'rechts'       => 'M6 20v-7a4 4 0 0 1 4-4h9M15 5l4 4-4 4',
        'bocht-links'  => 'M16 20c0-6-2-11-9-14M7 6h5M7 6v5',
        'bocht-rechts' => 'M8 20c0-6 2-11 9-14M17 6h-5M17 6v5',
        'keren'        => 'M8 20V9a4 4 0 0 1 8 0v5M12 11l4 4 4-4',
    );

    /** "tickets" (tot 8.15) bestaat niet meer: zo'n sectie wordt overgeslagen. */
    const SJABLONEN = array( 'kop', 'geleding', 'intro', 'dag', 'tekst', 'fotos', 'tekst_foto', 'route', 'evenementen', 'uitslagen', 'video', 'vakken', 'kaart', 'datum_sync', 'faq', 'html', 'motto' );

    /**
     * De verhouding van foto's (Foto's, en de fotokolom van Dag, Tekst en
     * Uitslagen): eigen (niets bijgesneden), liggend 3:2, staand 2:3 of
     * vierkant 1:1. In de CSS: kzp--verhouding-…; gelijk aan Verhouding in
     * KZ-Studio.
     */
    const VERHOUDINGEN = array( 'eigen', 'liggend', 'staand', 'vierkant' );

    /**
     * De knop boven een Geleding-kop, terug naar het overzicht van alle
     * geledingen. Gelijk aan GELEDING_KNOP in KZ-Studio.
     */
    const GELEDING_KNOP = array(
        'tekst' => 'Geledingen, partners & commissies',
        'pad'   => '/groeperingen/',
    );

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
        wp_enqueue_script( 'kz-pagina' );

        $secties = $data['secties'];
        $opmaak  = self::kleuren( $secties );

        $html = '';
        foreach ( $secties as $n => $sectie ) {
            $html .= self::sectie( $sectie, $opmaak[ $n ] );
        }

        $klasse = 'kz-pagina kzp' . ( 'kolom' === $atts['breedte'] ? '' : ' kzp--vol' ) . ( self::is_hjc( $slug ) ? ' kzp--hjc' : '' );
        return '<div class="' . esc_attr( $klasse ) . '">' . $html . '</div>';
    }

    /**
     * Een pagina van de jeugdafdeling HJC De Krössels: de naam is "hjc" of
     * begint met "hjc-". Die krijgt kzp--hjc: geel en groen in plaats van rood,
     * en zwarte tekst. Gelijk aan isHjc() in KZ-Studio.
     */
    public static function is_hjc( $slug ) {
        return 'hjc' === $slug || 0 === strpos( $slug, 'hjc-' );
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
     *
     * Het accent is de kleur die op een HJC-pagina (kzp--hjc) de plaats van rood
     * inneemt: om en om geel en groen, geteld over de rode banden. Een witte band
     * krijgt het accent van de eerstvolgende rode, zodat hij afsteekt tegen de
     * rode band ervoor.
     */
    public static function kleuren( array $secties ) {
        $uit   = array();
        $band  = 0;
        $fotos = 0;
        $rood  = 0;
        foreach ( $secties as $n => $s ) {
            $achtergrond = 'wit';
            $fotokant    = 'links';
            if ( in_array( $s['sjabloon'], array( 'geleding', 'intro', 'dag', 'tekst', 'fotos', 'tekst_foto', 'route', 'evenementen', 'uitslagen', 'video', 'vakken', 'kaart', 'datum_sync', 'faq', 'html', 'motto' ), true ) ) {
                // De Geleding-kop is altijd rood; de band erna dus wit.
                $k           = 'geleding' === $s['sjabloon'] ? 'rood' : ( isset( $s['inhoud']['kleur'] ) ? $s['inhoud']['kleur'] : 'auto' );
                $achtergrond = 'auto' === $k ? ( 0 === $band % 2 ? 'rood' : 'wit' ) : $k;
                $band++;
            }
            if ( in_array( $s['sjabloon'], array( 'dag', 'tekst', 'tekst_foto', 'route', 'uitslagen', 'datum_sync' ), true ) ) {
                $k        = isset( $s['inhoud']['fotokant'] ) ? $s['inhoud']['fotokant'] : 'auto';
                $fotokant = 'auto' === $k ? ( 0 === $fotos % 2 ? 'links' : 'rechts' ) : $k;
                $fotos++;
            }
            $accent = 0 === $rood % 2 ? 'geel' : 'groen';
            if ( 'rood' === $achtergrond ) {
                $rood++;
            }
            $uit[ $n ] = array(
                'achtergrond' => $achtergrond,
                'fotokant'    => $fotokant,
                'accent'      => $accent,
            );
        }
        return $uit;
    }

    private static function sectie( array $s, array $opmaak ) {
        $i    = $s['inhoud'];
        $band = 'kzp__sectie kzp__band kzp__band--' . $opmaak['achtergrond'] . ' kzp--accent-' . $opmaak['accent'];
        $kant = 'kzp--foto-' . $opmaak['fotokant'];

        switch ( $s['sjabloon'] ) {
            case 'kop':
                if ( '' === trim( $i['titel'] ) ) {
                    return '';
                }
                return '<section class="kzp__sectie kzp__kop"><div class="kzp__binnen">'
                    . self::titel( $i['titel'], 'kzp__kop-titel', 'h1' )
                    . '</div></section>';

            case 'geleding':
                // De afbeelding uit KZ-Studio; anders de uitgelichte afbeelding
                // van het WordPress-bericht waar de pagina op staat; anders het
                // logo, zodat de cirkel nooit leeg is.
                $beeld = self::veilige_foto( $i['afbeelding'] );
                if ( '' === $beeld ) {
                    $beeld = self::uitgelichte_afbeelding();
                }
                $cirkel = 'kzp__geleding-beeld';
                if ( '' === $beeld ) {
                    $cirkel .= ' kzp__geleding-beeld--logo';
                    $beeld   = KZ_PLUGIN_URL . 'assets/img/kz-logo-rood.svg';
                }
                $knop = class_exists( 'KZ_Element_Button' )
                    ? KZ_Element_Button::render(
                        array(
                            'text'   => self::GELEDING_KNOP['tekst'],
                            'link'   => 'url:' . rawurlencode( home_url( self::GELEDING_KNOP['pad'] ) ),
                            'color'  => 'rood',
                            'align'  => 'center',
                            'margin' => '0 0 12px',
                        )
                    )
                    : '';
                return '<section class="' . esc_attr( 'kzp__sectie kzp__geleding kzp--accent-' . $opmaak['accent'] ) . '">'
                    . '<div class="kzp__binnen kzp__geleding-kop">' . $knop . self::titel( $i['titel'], 'kzp__kop-titel', 'h1' ) . '</div>'
                    . '<div class="kzp__band kzp__band--rood kzp__geleding-band"><div class="kzp__binnen">'
                    . '<div class="' . esc_attr( $cirkel ) . '"><img src="' . esc_url( $beeld ) . '" alt="" decoding="async"></div>'
                    . '</div></div></section>';

            case 'intro':
                $beeld = self::veilige_foto( $i['afbeelding'] );
                $logo  = 'kzp__logo';
                if ( '' === $beeld ) {
                    $logo .= ' kzp__logo--standaard';
                    $beeld = KZ_PLUGIN_URL . 'assets/img/' . ( 'rood' === $opmaak['achtergrond'] ? 'kz-logo-wit.svg' : 'kz-logo-rood.svg' );
                }
                return '<section class="' . esc_attr( $band . ' kzp__intro' ) . '"><div class="kzp__binnen kzp__raster kzp__raster--8-4">'
                    . '<div class="kzp__tekstkolom">'
                    . self::titel( $i['titel'] )
                    . ( '' !== trim( $i['intro'] ) ? '<p class="kzp__intro-tekst">' . esc_html( $i['intro'] ) . '</p>' : '' )
                    . self::tekst_blok( $i['tekst'], 'kzp__tekst' )
                    . self::knop( $i['knop'], $opmaak['achtergrond'] )
                    . '</div>'
                    . '<div class="kzp__beeldkolom"><img class="' . esc_attr( $logo ) . '" src="' . esc_url( $beeld ) . '" alt="" decoding="async"></div>'
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
                    . ( '' !== $fotos ? '<div class="' . esc_attr( 'kzp__beeldkolom kzp__fotos kzp--verhouding-' . $i['verhouding'] ) . '">' . $fotos . '</div>' : '' )
                    . '</div></section>';

            case 'tekst':
                $blokken = '';
                foreach ( $i['blokken'] as $b ) {
                    if ( $b['verborgen'] ) {
                        continue;
                    }
                    $blok = ( '' !== trim( $b['titel'] ) ? '<h3 class="kzp__blok-titel">' . esc_html( $b['titel'] ) . '</h3>' : '' )
                        . self::tekst_blok( $b['tekst'], 'kzp__tekst' )
                        . self::knop( $b['knop'], $opmaak['achtergrond'] );
                    if ( '' !== $blok ) {
                        $blokken .= '<div class="kzp__blok">' . $blok . '</div>';
                    }
                }
                $fotos = '';
                foreach ( $i['fotos'] as $f ) {
                    $fotos .= self::foto( $f );
                }
                // Zonder foto's over de volle breedte, met foto's een kolom ernaast (zoals Dag).
                return '<section class="' . esc_attr( $band . ' kzp__tekstsectie ' . $kant ) . '"><div class="kzp__binnen' . ( '' !== $fotos ? ' kzp__raster kzp__raster--4-8' : '' ) . '">'
                    . '<div class="kzp__tekstkolom">'
                    . self::titel( $i['titel'] )
                    . ( '' !== trim( $i['intro'] ) ? '<p class="kzp__intro-tekst">' . esc_html( $i['intro'] ) . '</p>' : '' )
                    . $blokken
                    . '</div>'
                    . ( '' !== $fotos ? '<div class="' . esc_attr( 'kzp__beeldkolom kzp__fotos kzp--verhouding-' . $i['verhouding'] ) . '">' . $fotos . '</div>' : '' )
                    . '</div></section>';

            case 'route':
                $stappen = '';
                foreach ( $i['stappen'] as $st ) {
                    if ( $st['verborgen'] || '' === trim( $st['tekst'] ) ) {
                        continue;
                    }
                    $punt     = 'punt' === $st['soort'];
                    $afstand  = '' !== trim( $st['afstand'] ) ? '<span class="kzp__stap-afstand">' . esc_html( $st['afstand'] ) . '</span>' : '';
                    $stappen .= '<li class="kzp__stap kzp__stap--' . ( $punt ? 'punt' : 'stap' ) . '">'
                        . '<span class="kzp__stap-teken" aria-hidden="true">' . ( $punt ? '' : self::route_icoon( $st['richting'] ) ) . '</span>'
                        . '<div class="kzp__stap-inhoud">'
                        . ( $punt ? '<h3 class="kzp__stap-titel">' . esc_html( $st['tekst'] ) . '</h3>' : '<p class="kzp__stap-tekst">' . esc_html( $st['tekst'] ) . '</p>' )
                        . $afstand
                        . '</div></li>';
                }
                $kaart = self::veilige_foto( $i['kaart'] );
                return '<section class="' . esc_attr( $band . ' kzp__route ' . $kant ) . '"><div class="kzp__binnen' . ( '' !== $kaart ? ' kzp__raster kzp__raster--6-6' : '' ) . '">'
                    . '<div class="kzp__tekstkolom">'
                    . self::titel( $i['titel'] )
                    . ( '' !== $stappen ? '<ol class="kzp__stappen">' . $stappen . '</ol>' : '' )
                    . self::knop( $i['knop'], $opmaak['achtergrond'] )
                    . '</div>'
                    . ( '' !== $kaart ? '<div class="kzp__beeldkolom"><a class="kzp__kaart" href="' . esc_url( $kaart ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $kaart ) . '" alt="' . esc_attr__( 'Kaart van de route', 'kz-plugin' ) . '" loading="lazy" decoding="async"></a></div>' : '' )
                    . '</div></section>';

            case 'uitslagen':
                $uitslagen = self::uitslagen( $i['jaren'] );
                if ( '' === $uitslagen ) {
                    return '';
                }
                $foto = self::foto( $i['foto'] );
                return '<section class="' . esc_attr( $band . ' kzp__uitslagen-sectie ' . $kant ) . '"><div class="kzp__binnen">'
                    . self::titel( $i['titel'] )
                    . '<div class="' . ( '' !== $foto ? 'kzp__raster kzp__raster--4-8' : '' ) . '">'
                    . '<div class="kzp__tekstkolom">' . $uitslagen . '</div>'
                    . ( '' !== $foto ? '<div class="' . esc_attr( 'kzp__beeldkolom kzp--verhouding-' . $i['verhouding'] ) . '">' . $foto . '</div>' : '' )
                    . '</div></div></section>';

            case 'video':
                $id = self::youtube_id( $i['url'] );
                if ( '' === $id ) {
                    return '';
                }
                // youtube-nocookie: geen cookies tot iemand op afspelen drukt.
                return '<section class="' . esc_attr( $band . ' kzp__video-sectie' ) . '"><div class="kzp__binnen">'
                    . self::titel( $i['titel'] )
                    . '<div class="kzp__video"><iframe src="' . esc_url( 'https://www.youtube-nocookie.com/embed/' . $id ) . '"'
                    . ' title="' . esc_attr( '' !== trim( $i['titel'] ) ? $i['titel'] : 'Video' ) . '" loading="lazy"'
                    . ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"'
                    . ' referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>'
                    . '</div></section>';

            case 'vakken':
                $grid  = self::vakken_grid( $i, 'rood' === $opmaak['achtergrond'] );
                $tekst = self::tekst_blok( $i['tekst'], 'kzp__tekst' ) . self::knop( $i['knop'], $opmaak['achtergrond'] );
                if ( '' === $grid && '' === $tekst && '' === trim( $i['titel'] ) ) {
                    return '';
                }
                $kop   = ( '' !== trim( $i['vakkenTitel'] ) ? '<h3 class="kzp__vakken-titel">' . esc_html( $i['vakkenTitel'] )
                    . ( '' !== trim( $i['vakkenOndertitel'] ) ? ' <small>' . esc_html( $i['vakkenOndertitel'] ) . '</small>' : '' ) . '</h3>' : '' );
                $beide = '' !== $tekst && '' !== $grid;
                return '<section class="' . esc_attr( $band . ' kzp__vakken-sectie' ) . '"><div class="kzp__binnen">'
                    . self::titel( $i['titel'] )
                    . '<div class="' . ( $beide ? 'kzp__raster kzp__raster--6-6' : '' ) . '">'
                    . ( '' !== $tekst ? '<div class="kzp__tekstkolom">' . $tekst . '</div>' : '' )
                    . ( '' !== $grid ? '<div class="kzp__beeldkolom kzp__vakken">' . $kop . $grid . '</div>' : '' )
                    . '</div>'
                    . '</div></section>';

            case 'kaart':
                $kaart = self::kaart( $i['adres'], $opmaak['achtergrond'] );
                if ( '' === $kaart ) {
                    return '';
                }
                return '<section class="' . esc_attr( $band . ' kzp__kaart-sectie' ) . '"><div class="kzp__binnen">'
                    . self::titel( $i['titel'] )
                    . $kaart
                    . '</div></section>';

            case 'datum_sync':
                // De Intro van een andere pagina (ingevuld door website_pagina_ophalen()),
                // met de datum erboven en een knop naar die pagina.
                $intro = $i['intro'];
                $beeld = self::veilige_foto( $intro['afbeelding'] );
                $logo  = 'kzp__logo';
                if ( '' === $beeld ) {
                    $logo .= ' kzp__logo--standaard';
                    $beeld = KZ_PLUGIN_URL . 'assets/img/' . ( 'rood' === $opmaak['achtergrond'] ? 'kz-logo-wit.svg' : 'kz-logo-rood.svg' );
                }
                $datum = self::datum_label( $i );
                $knop  = array(
                    'tekst' => $i['knopTekst'],
                    'url'   => '' !== $i['slug'] ? home_url( '/' . $i['slug'] . '/' ) : '',
                );
                return '<section class="' . esc_attr( $band . ' kzp__intro kzp__datum-sync ' . $kant ) . '"><div class="kzp__binnen kzp__raster kzp__raster--8-4">'
                    . '<div class="kzp__tekstkolom">'
                    . ( '' !== $datum ? '<p class="kzp__tijd kzp__datum"><span>' . esc_html( $datum ) . '</span></p>' : '' )
                    . self::titel( $intro['titel'] )
                    . ( '' !== trim( $intro['intro'] ) ? '<p class="kzp__intro-tekst">' . esc_html( $intro['intro'] ) . '</p>' : '' )
                    . self::tekst_blok( $intro['tekst'], 'kzp__tekst' )
                    . self::knop( $knop, $opmaak['achtergrond'] )
                    . '</div>'
                    . '<div class="kzp__beeldkolom"><img class="' . esc_attr( $logo ) . '" src="' . esc_url( $beeld ) . '" alt="" decoding="async"></div>'
                    . '</div></section>';

            case 'fotos':
                // 1 tot 12 foto's in een band, perRij naast elkaar, in hun eigen
                // verhouding of allemaal liggend, staand of vierkant.
                $fotos = '';
                foreach ( $i['fotos'] as $f ) {
                    $fotos .= self::foto( $f );
                }
                if ( '' === $fotos && '' === trim( $i['titel'] ) ) {
                    return '';
                }
                return '<section class="' . esc_attr( $band . ' kzp__fotosectie' ) . '"><div class="kzp__binnen">'
                    . self::titel( $i['titel'] )
                    . ( '' !== $fotos ? '<div class="' . esc_attr( 'kzp__galerij kzp__galerij--' . $i['perRij'] . ' kzp--verhouding-' . $i['verhouding'] ) . '">' . $fotos . '</div>' : '' )
                    . '</div></section>';

            case 'html':
                // Eigen HTML, met alleen wat WordPress in een bericht toestaat:
                // geen script, iframe, style-blok of onclick. Shortcodes worden
                // niet uitgevoerd.
                $vrij = trim( wp_kses_post( $i['html'] ) );
                if ( '' === $vrij && '' === trim( $i['titel'] ) ) {
                    return '';
                }
                return '<section class="' . esc_attr( $band . ' kzp__html-sectie' ) . '"><div class="kzp__binnen">'
                    . self::titel( $i['titel'] )
                    . ( '' !== $vrij ? '<div class="kzp__vrij">' . $vrij . '</div>' : '' )
                    . '</div></section>';

            case 'tekst_foto':
                $foto = self::foto( $i['foto'] );
                return '<section class="' . esc_attr( $band . ' kzp__tekst-foto ' . $kant ) . '"><div class="kzp__binnen kzp__raster kzp__raster--6-6">'
                    . '<div class="kzp__tekstkolom">' . self::titel( $i['titel'] ) . self::tekst_blok( $i['tekst'], 'kzp__tekst' ) . self::knop( $i['knop'], $opmaak['achtergrond'] ) . '</div>'
                    . ( '' !== $foto ? '<div class="kzp__beeldkolom">' . $foto . '</div>' : '' )
                    . '</div></section>';

            case 'evenementen':
                $blokken = '';
                foreach ( $i['blokken'] as $b ) {
                    if ( $b['verborgen'] ) {
                        continue;
                    }
                    $blok = self::evenement( $b, 1 === $i['kolommen'] ? '820px' : '100%' );
                    if ( '' !== $blok ) {
                        $blokken .= '<div class="kzp__evenement">' . $blok . '</div>';
                    }
                }
                if ( '' === $blokken ) {
                    return '';
                }
                // Op rood zijn de blokken omgekeerd; het witte logo op de witte kop wordt rood.
                $filter = 'rood' === $opmaak['achtergrond'] ? self::rood_filter() : '';
                return '<section class="' . esc_attr( $band . ' kzp__evenementen kzp__evenementen--' . $i['kolommen'] ) . '">' . $filter . '<div class="kzp__binnen">'
                    . self::titel( $i['titel'], 'kzp__kop-titel kzp__evenementen-titel' )
                    . '<div class="kzp__evenementen-rij">' . $blokken . '</div>'
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
                return '<section class="' . esc_attr( $band . ' kzp__faq' ) . '"><div class="kzp__binnen kzp__smal">'
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

    /**
     * Het id van een YouTube-video uit een link (watch?v=, youtu.be/, /embed/,
     * /shorts/, /live/), of ''. Gelijk aan youtubeId() in KZ-Studio.
     */
    public static function youtube_id( $url ) {
        $patroon = '~^https?://(?:(?:www\.|m\.)?youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])~';
        return preg_match( $patroon, trim( (string) $url ), $m ) ? $m[1] : '';
    }

    /**
     * Het grid van het sjabloon Vakken via het element KZ-Vindt Grid zelf, dus
     * precies gelijk aan dat element elders op de site. Op een rode band
     * omgekeerd: witte vakken met rode tekst. Verborgen vakken vallen weg;
     * '' als er niets overblijft.
     */
    private static function vakken_grid( array $i, $op_rood ) {
        if ( ! class_exists( 'KZ_Element_Vindt_Grid' ) ) {
            return '';
        }
        $vakken = array();
        $gevuld = false;
        foreach ( $i['vakken'] as $v ) {
            if ( $v['verborgen'] ) {
                continue;
            }
            $vakken[] = array(
                'leeg'  => $v['leeg'],
                'datum' => $v['datum'],
                'label' => $v['label'],
                'tijd'  => $v['tijd'],
                'link'  => $v['leeg'] ? '' : self::veilige_url( $v['url'] ),
            );
            if ( ! $v['leeg'] ) {
                $gevuld = true;
            }
        }
        if ( ! $gevuld ) {
            return '';
        }
        $koppen = array();
        foreach ( array_slice( $i['koppen'], 0, $i['kolommen'] ) as $kop ) {
            $koppen[] = trim( $kop );
        }
        // Alleen koppen als er minstens één is ingevuld; anders schuift het grid niet.
        if ( '' === implode( '', $koppen ) ) {
            $koppen = array();
        }
        return KZ_Element_Vindt_Grid::html( $i['kolommen'], $koppen, $op_rood, $vakken );
    }

    /**
     * Een kaart van Google Maps met het adres, met eronder een balk met het
     * adres en een knop om de route te plannen. De balk heeft de kleur van de
     * tekst (rood op een witte band), dus de knop is omgekeerd. Gelijk aan
     * kaartUrl() en routeUrl() in KZ-Studio.
     */
    private static function kaart( $adres, $achtergrond ) {
        $adres = trim( (string) $adres );
        if ( '' === $adres ) {
            return '';
        }
        $kaart = 'https://www.google.com/maps?q=' . rawurlencode( $adres ) . '&z=16&output=embed';
        $route = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $adres );
        return '<div class="kzp__kaartblok">'
            . '<div class="kzp__kaartvenster"><iframe src="' . esc_url( $kaart ) . '" title="' . esc_attr( 'Kaart: ' . $adres ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>'
            . '<div class="kzp__kaartbalk"><p class="kzp__adres">' . esc_html( $adres ) . '</p>'
            . self::knop(
                array(
                    'tekst' => 'Route plannen',
                    'url'   => $route,
                ),
                'rood' === $achtergrond ? 'wit' : 'rood'
            )
            . '</div></div>';
    }

    private static function route_icoon( $richting ) {
        $pad = isset( self::ROUTE_ICONEN[ $richting ] ) ? self::ROUTE_ICONEN[ $richting ] : self::ROUTE_ICONEN['rechtdoor'];
        return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="' . esc_attr( $pad ) . '"/></svg>';
    }

    /**
     * De uitslagen zoals het KZ-Tab Widget ze toont (jaren als pillen,
     * categorieën als tabs, de uitslag in een vlak), maar hier helemaal op de
     * server getekend en ge-escapet: kz-pagina.js wisselt alleen welk paneel
     * zichtbaar is. Zonder JavaScript staat het laatste jaar open. Een jaar of
     * categorie zonder regels valt weg. Gelijk aan zichtbareUitslagen() en het
     * voorbeeld in KZ-Studio.
     */
    private static function uitslagen( array $jaren ) {
        $zichtbaar = array();
        foreach ( $jaren as $j ) {
            if ( $j['verborgen'] || '' === trim( $j['jaar'] ) ) {
                continue;
            }
            $categorieen = array();
            foreach ( $j['categorieen'] as $c ) {
                $heeft_regels = false;
                foreach ( $c['rijen'] as $rij ) {
                    if ( '' !== trim( $rij['naam'] ) ) {
                        $heeft_regels = true;
                    }
                }
                if ( ! $c['verborgen'] && '' !== trim( $c['naam'] ) && $heeft_regels ) {
                    $categorieen[] = $c;
                }
            }
            if ( $categorieen ) {
                $zichtbaar[] = array(
                    'jaar'        => $j['jaar'],
                    'categorieen' => $categorieen,
                );
            }
        }
        if ( ! $zichtbaar ) {
            return '';
        }

        $actief  = count( $zichtbaar ) - 1;
        $knoppen = '';
        $panelen = '';
        foreach ( $zichtbaar as $n => $j ) {
            $aan      = $n === $actief;
            $knoppen .= '<button type="button" class="kzp__jaar" data-jaar="' . $n . '" aria-pressed="' . ( $aan ? 'true' : 'false' ) . '">' . esc_html( $j['jaar'] ) . '</button>';
            $tabs     = '';
            $lijsten  = '';
            foreach ( $j['categorieen'] as $m => $c ) {
                $tabs .= '<button type="button" class="kzp__categorie" data-categorie="' . $m . '" aria-pressed="' . ( 0 === $m ? 'true' : 'false' ) . '">' . esc_html( $c['naam'] ) . '</button>';
                $regels = '';
                $plek   = 0;
                foreach ( $c['rijen'] as $rij ) {
                    if ( '' === trim( $rij['naam'] ) ) {
                        continue;
                    }
                    $met_punten = null !== $rij['punten'];
                    if ( $met_punten ) {
                        $plek++;
                    }
                    $regels .= '<li class="kzp__uitslag-rij">'
                        . '<span class="kzp__uitslag-naam">' . ( $met_punten ? $plek . '. ' : '' ) . esc_html( $rij['naam'] ) . '</span>'
                        . ( $met_punten ? '<span class="kzp__uitslag-punten">' . (int) $rij['punten'] . '</span>' : '' )
                        . '</li>';
                }
                $lijsten .= '<ul class="kzp__uitslag" data-categorie="' . $m . '"' . ( 0 === $m ? '' : ' hidden' ) . '>' . $regels . '</ul>';
            }
            $panelen .= '<div class="kzp__jaar-paneel" data-jaar="' . $n . '"' . ( $aan ? '' : ' hidden' ) . '>'
                . '<div class="kzp__categorieen">' . $tabs . '</div>'
                . $lijsten
                . '</div>';
        }
        return '<div class="kzp__uitslagen" data-kzp-uitslagen><div class="kzp__jaren">' . $knoppen . '</div>' . $panelen . '</div>';
    }

    /**
     * De datum boven een Datum + Sync: "zaterdag 14 november 2026", of een
     * periode ("vrijdag 12 tot en met dinsdag 16 februari 2027"; maand en
     * jaar alleen waar ze verschillen). datumTekst gaat voor. Gelijk aan
     * datumLabel() in KZ-Studio.
     */
    public static function datum_label( array $i ) {
        if ( '' !== trim( $i['datumTekst'] ) ) {
            return trim( $i['datumTekst'] );
        }
        if ( '' === $i['datum'] ) {
            return '';
        }
        $dagen   = array( 'zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag' );
        $maanden = array( 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december' );
        $deel    = function ( $iso ) use ( $dagen ) {
            list( $j, $m, $d ) = array_map( 'intval', explode( '-', $iso ) );
            return array(
                'j'       => $j,
                'm'       => $m,
                'dag'     => $d,
                'weekdag' => $dagen[ (int) gmdate( 'w', gmmktime( 0, 0, 0, $m, $d, $j ) ) ],
            );
        };
        $van    = $deel( $i['datum'] );
        $voluit = $van['weekdag'] . ' ' . $van['dag'] . ' ' . $maanden[ $van['m'] - 1 ] . ' ' . $van['j'];
        if ( '' === $i['datumTot'] || $i['datumTot'] <= $i['datum'] ) {
            return $voluit;
        }
        $tot = $deel( $i['datumTot'] );
        if ( $tot['j'] !== $van['j'] ) {
            $begin = $voluit;
        } elseif ( $tot['m'] !== $van['m'] ) {
            $begin = $van['weekdag'] . ' ' . $van['dag'] . ' ' . $maanden[ $van['m'] - 1 ];
        } else {
            $begin = $van['weekdag'] . ' ' . $van['dag'];
        }
        return $begin . ' tot en met ' . $tot['weekdag'] . ' ' . $tot['dag'] . ' ' . $maanden[ $tot['m'] - 1 ] . ' ' . $tot['j'];
    }

    /**
     * De uitgelichte afbeelding van het bericht of de pagina waar
     * [kz_pagina] op staat (volle grootte), of '' als die er niet is. Alleen
     * https, net als de foto's uit KZ-Studio.
     */
    private static function uitgelichte_afbeelding() {
        $id = get_the_ID();
        if ( ! $id ) {
            $id = get_queried_object_id();
        }
        if ( ! $id || ! has_post_thumbnail( $id ) ) {
            return '';
        }
        $url = get_the_post_thumbnail_url( $id, 'full' );
        return $url ? self::veilige_foto( set_url_scheme( $url, 'https' ) ) : '';
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

    /**
     * Eén blok via het element KZ-Evenement Info zelf, dus precies gelijk aan
     * dat element elders op de site. Automatisch: het eerstvolgende evenement
     * (eventueel uit één categorie), zoals het element dat ook kiest; leeg als
     * er geen is.
     */
    private static function evenement( array $b, $max_breedte ) {
        if ( ! class_exists( 'KZ_Element_Event_Info' ) ) {
            return '';
        }
        if ( 'automatisch' === $b['bron'] ) {
            return KZ_Element_Event_Info::render(
                array(
                    'bron'      => 'automatisch',
                    'categorie' => $b['categorie'],
                    'max_width' => $max_breedte,
                )
            );
        }

        $link = '';
        $url  = self::veilige_url( $b['linkUrl'] );
        if ( '' !== $url ) {
            $url = html_entity_decode( $url );
            // KZ-Evenement Info leest zijn link met vc_build_link() (url:…|target:…)
            // als WPBakery er is, en anders als gewoon adres.
            if ( function_exists( 'vc_build_link' ) ) {
                $link = 'url:' . rawurlencode( $url ) . ( $b['nieuwVenster'] ? '|target:' . rawurlencode( '_blank' ) : '' );
            } else {
                $link = $url;
            }
        }
        $feiten = array();
        foreach ( $b['feiten'] as $f ) {
            $feiten[] = array(
                'label'  => $f['label'],
                'waarde' => $f['waarde'],
            );
        }

        return KZ_Element_Event_Info::render(
            array(
                'label'         => $b['label'],
                'titel'         => $b['titel'],
                'feiten'        => $feiten ? rawurlencode( wp_json_encode( $feiten ) ) : '',
                'show_logo'     => $b['toonLogo'] ? 'true' : 'false',
                'variant'       => $b['variant'],
                'seizoen'       => $b['seizoen'],
                'datum'         => $b['datum'],
                'dag_tekst'     => $b['dagTekst'],
                'tijd'          => $b['tijd'],
                'link'          => $link,
                'knop_tekst'    => $b['knopTekst'],
                'status'        => $b['status'],
                'verkoop_start' => $b['verkoopStart'],
                'kleine_tekst'  => $b['kleineTekst'],
                'max_width'     => $max_breedte,
            ),
            self::tekst_html( $b['omschrijving'] )
        );
    }

    /**
     * Zet de tekst van een veld om naar HTML, precies zoals tekstNaarHtml() in
     * KZ-Studio. Een witregel begint een nieuwe alinea, een losse enter wordt
     * een <br>; verder ### tussenkop, - opsomming, **vet**, *cursief* en
     * [tekst](https://…), en tabellen zonder lijnen: regels die met | beginnen
     * of een tab bevatten (zie tabel_cellen()). Alles wordt eerst ge-escapet.
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
            $tabel  = array();
            foreach ( explode( "\n", $blok ) as $regel ) {
                $kop    = preg_match( '/^\s*###\s+(.+)$/u', $regel, $m_kop );
                $punt   = ! $kop && preg_match( '/^\s*[-•]\s+(.+)$/u', $regel, $m_punt );
                $cellen = $kop || $punt ? null : self::tabel_cellen( $regel );
                if ( $kop ) {
                    $uit .= self::sluit_alinea( $alinea ) . self::sluit_lijst( $lijst ) . self::sluit_tabel( $tabel );
                    $uit .= '<h4>' . self::regel( trim( $m_kop[1] ) ) . '</h4>';
                } elseif ( $punt ) {
                    $uit    .= self::sluit_alinea( $alinea ) . self::sluit_tabel( $tabel );
                    $lijst[] = self::regel( trim( $m_punt[1] ) );
                } elseif ( null !== $cellen ) {
                    $uit .= self::sluit_alinea( $alinea ) . self::sluit_lijst( $lijst );
                    if ( self::is_scheiding( $cellen ) ) {
                        // Alleen direct onder de eerste regel maakt hij daar een kop van.
                        if ( 1 === count( $tabel ) ) {
                            $tabel[0]['kop'] = true;
                        }
                    } else {
                        $tabel[] = array(
                            'kop'    => false,
                            'cellen' => $cellen,
                        );
                    }
                } elseif ( '' !== trim( $regel ) ) {
                    $uit     .= self::sluit_lijst( $lijst ) . self::sluit_tabel( $tabel );
                    $alinea[] = self::regel( trim( $regel ) );
                }
            }
            $uit .= self::sluit_alinea( $alinea ) . self::sluit_lijst( $lijst ) . self::sluit_tabel( $tabel );
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

    /**
     * Een regel van een tabel: hij begint met "|" (Markdown), of er staat een
     * tab tussen twee stukken tekst. Geeft de cellen, of null. Gelijk aan
     * tabelCellen() in KZ-Studio.
     */
    private static function tabel_cellen( $regel ) {
        if ( preg_match( '/^\s*\|/u', $regel ) ) {
            $r = preg_replace( '/\|$/', '', preg_replace( '/^\|/', '', trim( $regel ) ) );
            return array_map( 'trim', explode( '|', $r ) );
        }
        if ( preg_match( '/\S[ ]*\t[\t ]*\S/u', $regel ) ) {
            return preg_split( '/[ ]*\t[\t ]*/', trim( $regel ) );
        }
        return null;
    }

    /** De streepjesregel onder de kop van een Markdown-tabel: | --- | :---: | */
    private static function is_scheiding( array $cellen ) {
        foreach ( $cellen as $c ) {
            if ( ! preg_match( '/^:?-{3,}:?$/', $c ) ) {
                return false;
            }
        }
        return count( $cellen ) > 0;
    }

    /** Een tabel zonder zichtbare lijnen; de kop (als die er is) vet. */
    private static function sluit_tabel( array &$tabel ) {
        if ( ! $tabel ) {
            return '';
        }
        $kop  = '';
        $rest = '';
        foreach ( $tabel as $rij ) {
            $tag = $rij['kop'] ? 'th' : 'td';
            $tr  = '<tr>';
            foreach ( $rij['cellen'] as $c ) {
                $tr .= '<' . $tag . '>' . self::regel( $c ) . '</' . $tag . '>';
            }
            $tr .= '</tr>';
            if ( $rij['kop'] ) {
                $kop .= $tr;
            } else {
                $rest .= $tr;
            }
        }
        $tabel = array();
        return '<div class="kzp__tabel"><table>' . ( '' !== $kop ? '<thead>' . $kop . '</thead>' : '' ) . ( '' !== $rest ? '<tbody>' . $rest . '</tbody>' : '' ) . '</table></div>';
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
                $inhoud = self::schoon_inhoud( $s['sjabloon'], isset( $s['inhoud'] ) && is_array( $s['inhoud'] ) ? $s['inhoud'] : array() );
                // Een Datum + Sync zonder Intro telt niet mee in het om en om.
                if ( 'datum_sync' === $s['sjabloon'] && null === $inhoud['intro'] ) {
                    continue;
                }
                $secties[] = array(
                    'sjabloon' => $s['sjabloon'],
                    'inhoud'   => $inhoud,
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

            case 'geleding':
                return array(
                    'titel'      => self::tekst( $r, 'titel', $l['titel'] ),
                    'afbeelding' => self::tekst( $r, 'afbeelding', $l['url'] ),
                );

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
                    // Tot 8.28.0 altijd 3:2: zonder keuze blijft het liggend.
                    'verhouding' => self::keuze( $r, 'verhouding', self::VERHOUDINGEN, 'liggend' ),
                    'fotokant'   => self::keuze( $r, 'fotokant', array( 'auto', 'links', 'rechts' ), 'auto' ),
                );

            case 'tekst':
                $blokken = array();
                foreach ( self::lijst( $r, 'blokken', $l['tekstBlokken'] ) as $b ) {
                    $blokken[] = array(
                        'titel'     => self::tekst( $b, 'titel', $l['onderdeelTitel'] ),
                        'tekst'     => self::tekst( $b, 'tekst', $l['tekst'] ),
                        'knop'      => self::schoon_knop( $b ),
                        'verborgen' => self::verborgen( $b ),
                    );
                }
                $fotos = array();
                foreach ( self::lijst( $r, 'fotos', $l['fotos'] ) as $f ) {
                    $fotos[] = self::schoon_foto( $f );
                }
                return array(
                    'titel'      => self::tekst( $r, 'titel', $l['titel'] ),
                    'intro'      => self::tekst( $r, 'intro', $l['tekstIntro'] ),
                    'blokken'    => $blokken,
                    'fotos'      => $fotos,
                    'kleur'      => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
                    // Tot 8.28.0 altijd 3:2: zonder keuze blijft het liggend.
                    'verhouding' => self::keuze( $r, 'verhouding', self::VERHOUDINGEN, 'liggend' ),
                    'fotokant'   => self::keuze( $r, 'fotokant', array( 'auto', 'links', 'rechts' ), 'auto' ),
                );

            case 'route':
                $stappen = array();
                foreach ( self::lijst( $r, 'stappen', $l['stappen'] ) as $st ) {
                    $stappen[] = array(
                        'soort'     => self::keuze( $st, 'soort', array( 'punt', 'stap' ), 'stap' ),
                        'richting'  => self::keuze( $st, 'richting', array_keys( self::ROUTE_ICONEN ), 'rechtdoor' ),
                        'tekst'     => self::tekst( $st, 'tekst', $l['stap'] ),
                        'afstand'   => self::tekst( $st, 'afstand', $l['afstand'] ),
                        'verborgen' => self::verborgen( $st ),
                    );
                }
                return array(
                    'titel'    => self::tekst( $r, 'titel', $l['titel'] ),
                    'stappen'  => $stappen,
                    'kaart'    => self::tekst( $r, 'kaart', $l['url'] ),
                    'knop'     => self::schoon_knop( $r ),
                    'kleur'    => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
                    'fotokant' => self::keuze( $r, 'fotokant', array( 'auto', 'links', 'rechts' ), 'auto' ),
                );

            case 'uitslagen':
                $jaren = array();
                foreach ( self::lijst( $r, 'jaren', $l['jaren'] ) as $j ) {
                    $categorieen = array();
                    foreach ( self::lijst( $j, 'categorieen', $l['categorieen'] ) as $c ) {
                        $rijen = array();
                        foreach ( self::lijst( $c, 'rijen', $l['rijen'] ) as $rij ) {
                            $p       = isset( $rij['punten'] ) ? $rij['punten'] : null;
                            $rijen[] = array(
                                'naam'   => self::tekst( $rij, 'naam', $l['uitslagNaam'] ),
                                'punten' => is_int( $p ) && abs( $p ) < 1000000 ? $p : null,
                            );
                        }
                        $categorieen[] = array(
                            'naam'      => self::tekst( $c, 'naam', $l['titel'] ),
                            'rijen'     => $rijen,
                            'verborgen' => self::verborgen( $c ),
                        );
                    }
                    $jaren[] = array(
                        'jaar'        => self::tekst( $j, 'jaar', $l['jaar'] ),
                        'categorieen' => $categorieen,
                        'verborgen'   => self::verborgen( $j ),
                    );
                }
                return array(
                    'titel'      => self::tekst( $r, 'titel', $l['titel'] ),
                    'foto'       => self::schoon_foto( isset( $r['foto'] ) && is_array( $r['foto'] ) ? $r['foto'] : array() ),
                    'jaren'      => $jaren,
                    'kleur'      => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
                    // Tot 8.28.0 altijd 3:2: zonder keuze blijft het liggend.
                    'verhouding' => self::keuze( $r, 'verhouding', self::VERHOUDINGEN, 'liggend' ),
                    'fotokant'   => self::keuze( $r, 'fotokant', array( 'auto', 'links', 'rechts' ), 'auto' ),
                );

            case 'video':
                return array(
                    'titel' => self::tekst( $r, 'titel', $l['titel'] ),
                    'url'   => self::tekst( $r, 'url', $l['url'] ),
                    'kleur' => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
                );

            case 'vakken':
                $vakken = array();
                foreach ( self::lijst( $r, 'vakken', $l['vakken'] ) as $v ) {
                    $vakken[] = array(
                        'leeg'      => isset( $v['leeg'] ) && true === $v['leeg'],
                        'datum'     => self::tekst( $v, 'datum', $l['vakTekst'] ),
                        'label'     => self::tekst( $v, 'label', $l['label'] ),
                        'tijd'      => self::tekst( $v, 'tijd', $l['vakTekst'] ),
                        'url'       => self::tekst( $v, 'url', $l['url'] ),
                        'verborgen' => self::verborgen( $v ),
                    );
                }
                $koppen = array();
                if ( isset( $r['koppen'] ) && is_array( $r['koppen'] ) ) {
                    foreach ( array_slice( array_values( $r['koppen'] ), 0, 4 ) as $kop ) {
                        $koppen[] = is_string( $kop ) ? mb_substr( $kop, 0, $l['kolomKop'] ) : '';
                    }
                }
                return array(
                    'titel'            => self::tekst( $r, 'titel', $l['titel'] ),
                    'tekst'            => self::tekst( $r, 'tekst', $l['tekst'] ),
                    'knop'             => self::schoon_knop( $r ),
                    'vakkenTitel'      => self::tekst( $r, 'vakkenTitel', $l['titel'] ),
                    'vakkenOndertitel' => self::tekst( $r, 'vakkenOndertitel', $l['ondertitel'] ),
                    'kolommen'         => isset( $r['kolommen'] ) && in_array( $r['kolommen'], array( 2, 4 ), true ) ? $r['kolommen'] : 3,
                    'koppen'           => $koppen,
                    'vakken'           => $vakken,
                    'kleur'            => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
                );

            case 'kaart':
                return array(
                    'titel' => self::tekst( $r, 'titel', $l['titel'] ),
                    'adres' => self::tekst( $r, 'adres', $l['adres'] ),
                    'kleur' => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
                );

            case 'datum_sync':
                // `slug` en `intro` zet website_pagina_ophalen() erin (KZ-Studio 0051);
                // zonder intro tekent sectie() niets.
                $slug = self::tekst( $r, 'slug', 60 );
                return array(
                    'datum'      => self::datum( $r, 'datum' ),
                    'datumTot'   => self::datum( $r, 'datumTot' ),
                    'datumTekst' => self::tekst( $r, 'datumTekst', $l['datumTekst'] ),
                    'knopTekst'  => self::tekst( $r, 'knopTekst', $l['knopTekst'] ),
                    'kleur'      => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
                    'fotokant'   => self::keuze( $r, 'fotokant', array( 'auto', 'links', 'rechts' ), 'auto' ),
                    'slug'       => preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug ) ? $slug : '',
                    'intro'      => isset( $r['intro'] ) && is_array( $r['intro'] ) ? self::schoon_inhoud( 'intro', $r['intro'] ) : null,
                );

            case 'fotos':
                $fotos = array();
                foreach ( self::lijst( $r, 'fotos', $l['galerij'] ) as $f ) {
                    $fotos[] = self::schoon_foto( $f );
                }
                return array(
                    'titel'      => self::tekst( $r, 'titel', $l['titel'] ),
                    'fotos'      => $fotos,
                    'perRij'     => self::keuze( $r, 'perRij', array( 1, 2, 3, 4 ), 2 ),
                    // Eigen verhouding (niets bijgesneden), of liggend 3:2, staand 2:3 of vierkant 1:1.
                    'verhouding' => self::keuze( $r, 'verhouding', self::VERHOUDINGEN, 'eigen' ),
                    'kleur'      => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
                );

            case 'html':
                return array(
                    'titel' => self::tekst( $r, 'titel', $l['titel'] ),
                    'html'  => self::tekst( $r, 'html', $l['html'] ),
                    'kleur' => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
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

            case 'evenementen':
                $blokken = array();
                foreach ( self::lijst( $r, 'blokken', $l['blokken'] ) as $b ) {
                    $feiten = array();
                    foreach ( self::lijst( $b, 'feiten', $l['feiten'] ) as $f ) {
                        $feiten[] = array(
                            'label'  => self::tekst( $f, 'label', $l['feitLabel'] ),
                            'waarde' => self::tekst( $f, 'waarde', $l['feitWaarde'] ),
                        );
                    }
                    $blokken[] = array(
                        'verborgen'    => self::verborgen( $b ),
                        'bron'         => self::keuze( $b, 'bron', array( 'handmatig', 'automatisch' ), 'automatisch' ),
                        'categorie'    => self::tekst( $b, 'categorie', $l['categorie'] ),
                        'label'        => self::tekst( $b, 'label', $l['label'] ),
                        'titel'        => self::tekst( $b, 'titel', $l['titel'] ),
                        'omschrijving' => self::tekst( $b, 'omschrijving', $l['omschrijving'] ),
                        'feiten'       => $feiten,
                        'toonLogo'     => ! isset( $b['toonLogo'] ) || false !== $b['toonLogo'],
                        'variant'      => self::keuze( $b, 'variant', array( 'normaal', 'seizoen' ), 'normaal' ),
                        'seizoen'      => self::tekst( $b, 'seizoen', $l['seizoen'] ),
                        'datum'        => self::datum( $b, 'datum' ),
                        'dagTekst'     => self::tekst( $b, 'dagTekst', $l['dagTekst'] ),
                        'tijd'         => self::tekst( $b, 'tijd', $l['tijd'] ),
                        'linkUrl'      => self::tekst( $b, 'linkUrl', $l['url'] ),
                        'nieuwVenster' => isset( $b['nieuwVenster'] ) && true === $b['nieuwVenster'],
                        'knopTekst'    => self::tekst( $b, 'knopTekst', $l['knopTekst'] ),
                        'status'       => self::keuze( $b, 'status', array( 'open', 'binnenkort', 'uitverkocht' ), 'open' ),
                        'verkoopStart' => self::datum( $b, 'verkoopStart' ),
                        'kleineTekst'  => self::tekst( $b, 'kleineTekst', $l['kleineTekst'] ),
                    );
                }
                return array(
                    'titel'    => self::tekst( $r, 'titel', $l['titel'] ),
                    'kolommen' => isset( $r['kolommen'] ) && in_array( $r['kolommen'], array( 2, 3 ), true ) ? $r['kolommen'] : 1,
                    'blokken'  => $blokken,
                    'kleur'    => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
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
                    'kleur'     => self::keuze( $r, 'kleur', array( 'auto', 'rood', 'wit' ), 'auto' ),
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

    /** Alleen "jjjj-mm-dd", anders leeg. */
    private static function datum( array $r, $sleutel ) {
        return isset( $r[ $sleutel ] ) && is_string( $r[ $sleutel ] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $r[ $sleutel ] ) ? $r[ $sleutel ] : '';
    }

    /** Een onderdeel, evenementblok, groep of vraag die in KZ-Studio is verborgen. */
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
