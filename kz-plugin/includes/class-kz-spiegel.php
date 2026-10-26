<?php
/**
 * Spiegel — een kopie op deze site van de foto's uit KZ-Studio.
 *
 * Pagina's (KZ-Pagina), de Link-In-Bio en KZ-Motto krijgen hun foto's als
 * adres in Supabase Storage. Zonder kopie haalt de browser van de bezoeker
 * die daar zelf op, en ligt Supabase eruit, dan blijven de vlakken leeg —
 * ook al staat de tekst er dankzij de "laatste goede versie" gewoon.
 *
 * Daarom gaat elk foto-adres bij het tekenen door url():
 * - staat er al een kopie in wp-content/uploads/kz-studio/, dan wordt dat
 *   het adres;
 * - anders komt het adres in de wachtrij en blijft het voorlopig het
 *   Supabase-adres. WP-Cron (kz_spiegel_kopieren) downloadt de wachtrij op
 *   de achtergrond, dus een bezoeker wacht nooit op een download.
 *
 * Alleen adressen uit Supabase Storage van het ingestelde project worden
 * gekopieerd; R2 (de fotobibliotheek), de eigen site en de rest blijven zoals
 * ze zijn. Zo'n adres verandert nooit van inhoud (KZ-Studio uploadt met
 * upsert: false onder een nieuwe naam), dus een kopie hoeft nooit ververst.
 * Een kopie die 90 dagen niet meer op een pagina is getekend, ruimt
 * kz_spiegel_opruimen op.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Spiegel {

    /** Per adres (md5): url, bestand ('' = nog niet), gezien, pogingen, volgende, fout. */
    const OPTIE  = 'kz_spiegel';
    const MAP    = 'kz-studio';
    const KOPIEREN = 'kz_spiegel_kopieren';
    const OPRUIMEN = 'kz_spiegel_opruimen';
    const SLOT   = 'kz_spiegel_bezig';

    /** Zo lang mag een kopie ongebruikt blijven staan. */
    const BEWAAR_DAGEN = 90;
    /** Niet meer per cron-ronde, zodat één ronde nooit te lang duurt. */
    const PER_RONDE    = 10;
    const MAX_BYTES    = 15728640; // 15 MB

    /** Bestandstypes die gekopieerd mogen worden, met hun extensie. */
    const TYPES = array(
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    );

    /** @var array|null De lijst van dit verzoek. */
    private static $lijst = null;
    /** @var array Wijzigingen van dit verzoek, weggeschreven bij shutdown. */
    private static $nieuw = array();
    /** @var bool Is er in dit verzoek een foto getekend die nog geen kopie heeft? */
    private static $wacht = false;
    /** @var bool Staat bewaar() al klaar voor shutdown? */
    private static $gepland = false;

    public static function init() {
        add_action( self::KOPIEREN, array( __CLASS__, 'kopieren' ) );
        add_action( self::OPRUIMEN, array( __CLASS__, 'opruimen' ) );
        add_action( 'init', array( __CLASS__, 'plan_opruimen' ) );
    }

    public static function plan_opruimen() {
        if ( ! wp_next_scheduled( self::OPRUIMEN ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::OPRUIMEN );
        }
    }

    /** Bij het deactiveren van de plugin: geen cron-taken achterlaten. De kopieën blijven staan. */
    public static function deactiveren() {
        wp_clear_scheduled_hook( self::KOPIEREN );
        wp_clear_scheduled_hook( self::OPRUIMEN );
    }

    // ------------------------------------------------------------------
    // Bij het tekenen
    // ------------------------------------------------------------------

    /**
     * Het adres om te tekenen: de kopie als die er is, anders het origineel
     * (en dan komt hij in de wachtrij). Adressen van buiten Supabase Storage
     * komen ongewijzigd terug.
     */
    public static function url( $url ) {
        $url = (string) $url;
        if ( ! self::is_supabase( $url ) ) {
            return $url;
        }

        $sleutel = md5( $url );
        $lijst   = self::lijst();
        $item    = isset( $lijst[ $sleutel ] ) ? $lijst[ $sleutel ] : null;

        if ( $item && '' !== $item['bestand'] && file_exists( self::map_pad() . '/' . $item['bestand'] ) ) {
            if ( $item['gezien'] < time() - DAY_IN_SECONDS ) {
                self::onthoud( $sleutel, array( 'gezien' => time() ) );
            }
            return self::map_url() . '/' . $item['bestand'];
        }

        if ( ! $item || '' !== $item['bestand'] ) {
            // Nieuw, of de kopie is van de schijf verdwenen: opnieuw kopiëren.
            self::onthoud(
                $sleutel,
                array(
                    'url'      => $url,
                    'bestand'  => '',
                    'gezien'   => time(),
                    'pogingen' => 0,
                    'volgende' => 0,
                    'fout'     => '',
                )
            );
        } elseif ( $item['gezien'] < time() - DAY_IN_SECONDS ) {
            self::onthoud( $sleutel, array( 'gezien' => time() ) );
        }
        self::$wacht = true;
        self::bij_shutdown();

        // Deze pagina tekent nog een adres van Supabase: vraag LiteSpeed Cache
        // hem niet lang te bewaren, zodat de kopie er snel in komt.
        do_action( 'litespeed_control_set_ttl', 300 );
        return $url;
    }

    /** Een openbaar adres in Supabase Storage van het ingestelde project? */
    public static function is_supabase( $url ) {
        $basis = untrailingslashit( (string) get_option( KZ_Link_In_Bio::OPTIE_URL, KZ_Link_In_Bio::STANDAARD_URL ) );
        if ( '' === $basis || 0 !== stripos( $url, 'https://' ) ) {
            return false;
        }
        return 1 === preg_match( '#^' . preg_quote( $basis, '#' ) . '/storage/v1/(object|render/image)/public/\S+$#i', $url );
    }

    /** Onthoudt een wijziging; alles samen gaat bij shutdown in één keer weg. */
    private static function onthoud( $sleutel, array $velden ) {
        self::bij_shutdown();
        self::$nieuw[ $sleutel ] = isset( self::$nieuw[ $sleutel ] ) ? array_merge( self::$nieuw[ $sleutel ], $velden ) : $velden;
        if ( isset( self::$lijst[ $sleutel ] ) ) {
            self::$lijst[ $sleutel ] = array_merge( self::$lijst[ $sleutel ], $velden );
        } elseif ( isset( $velden['url'] ) ) {
            self::$lijst[ $sleutel ] = $velden;
        }
    }

    private static function bij_shutdown() {
        if ( ! self::$gepland ) {
            add_action( 'shutdown', array( __CLASS__, 'bewaar' ) );
            self::$gepland = true;
        }
    }

    /**
     * Schrijft de wijzigingen van dit verzoek weg, op een vers gelezen lijst:
     * de cron kan intussen een kopie hebben afgerond, en die mag een bezoeker
     * niet overschrijven.
     */
    public static function bewaar() {
        if ( ! self::$nieuw ) {
            // Niets nieuws, maar wel iets wat nog wacht: zorg dat de cron er
            // nog is (die kan bij een fout op de host zijn weggevallen).
            if ( self::$wacht && ! wp_next_scheduled( self::KOPIEREN ) ) {
                self::plan_kopieren( time() );
            }
            return;
        }
        $lijst    = self::lees( true );
        $wachtrij = false;
        foreach ( self::$nieuw as $sleutel => $velden ) {
            if ( isset( $velden['url'] ) ) {
                // Een nieuw item, of een kopie die van de schijf verdwenen is.
                // Een kopie die de cron net heeft afgerond, laten we staan.
                if ( isset( $lijst[ $sleutel ] ) && '' !== $lijst[ $sleutel ]['bestand'] && file_exists( self::map_pad() . '/' . $lijst[ $sleutel ]['bestand'] ) ) {
                    continue;
                }
                if ( isset( $lijst[ $sleutel ] ) && '' === $lijst[ $sleutel ]['bestand'] ) {
                    // Al in de wachtrij: alleen gezien bijwerken, de pogingen niet terugzetten.
                    $lijst[ $sleutel ]['gezien'] = $velden['gezien'];
                    continue;
                }
                $lijst[ $sleutel ] = $velden;
                $wachtrij          = true;
            } elseif ( isset( $lijst[ $sleutel ] ) ) {
                $lijst[ $sleutel ]['gezien'] = max( (int) $lijst[ $sleutel ]['gezien'], (int) $velden['gezien'] );
            }
        }
        update_option( self::OPTIE, $lijst, false );
        self::$nieuw = array();

        if ( $wachtrij || ( self::$wacht && ! wp_next_scheduled( self::KOPIEREN ) ) ) {
            self::plan_kopieren( time() );
        }
    }

    // ------------------------------------------------------------------
    // Kopiëren (WP-Cron)
    // ------------------------------------------------------------------

    private static function plan_kopieren( $wanneer ) {
        $gepland = wp_next_scheduled( self::KOPIEREN );
        if ( $gepland && $gepland <= $wanneer ) {
            return;
        }
        if ( $gepland ) {
            wp_unschedule_event( $gepland, self::KOPIEREN );
        }
        wp_schedule_single_event( max( time(), (int) $wanneer ), self::KOPIEREN );
    }

    /**
     * Kopieert wat er klaarstaat, hooguit PER_RONDE per keer, en plant de
     * volgende ronde: meteen als er meer klaarstaat, anders bij de eerste
     * nieuwe poging van iets wat mislukte.
     *
     * @param bool $ook_later Ook wat na een mislukte poging nog even moet
     *                        wachten (de knop in het beheerscherm).
     * @return array{gelukt:int,mislukt:int} Voor de knop in het beheerscherm.
     */
    public static function kopieren( $ook_later = false ) {
        $telling = array( 'gelukt' => 0, 'mislukt' => 0 );
        if ( get_transient( self::SLOT ) ) {
            return $telling;
        }
        set_transient( self::SLOT, 1, 5 * MINUTE_IN_SECONDS );

        if ( ! self::maak_map() ) {
            delete_transient( self::SLOT );
            return $telling;
        }

        $lijst = self::lees( true );
        $nu    = time();
        $klaar = array();
        foreach ( $lijst as $sleutel => $item ) {
            if ( '' === $item['bestand'] && ( $ook_later || (int) $item['volgende'] <= $nu ) ) {
                $klaar[] = $sleutel;
            }
        }

        foreach ( array_slice( $klaar, 0, self::PER_RONDE ) as $sleutel ) {
            $resultaat = self::download( $lijst[ $sleutel ]['url'], $sleutel );

            // Per item vers lezen en wegschrijven: een bezoeker kan intussen
            // de lijst hebben bijgewerkt.
            $lijst = self::lees( true );
            if ( ! isset( $lijst[ $sleutel ] ) ) {
                continue;
            }
            if ( is_wp_error( $resultaat ) ) {
                $pogingen = (int) $lijst[ $sleutel ]['pogingen'] + 1;
                // 10 minuten, 20, 40 … tot hooguit een dag.
                $lijst[ $sleutel ]['pogingen'] = $pogingen;
                $lijst[ $sleutel ]['volgende'] = time() + min( DAY_IN_SECONDS, 10 * MINUTE_IN_SECONDS * ( 2 ** min( $pogingen - 1, 8 ) ) );
                $lijst[ $sleutel ]['fout']     = $resultaat->get_error_message();
                ++$telling['mislukt'];
            } else {
                $lijst[ $sleutel ]['bestand']  = $resultaat;
                $lijst[ $sleutel ]['pogingen'] = 0;
                $lijst[ $sleutel ]['fout']     = '';
                ++$telling['gelukt'];
            }
            update_option( self::OPTIE, $lijst, false );
        }

        // Volgende ronde plannen.
        $eerste = null;
        foreach ( self::lees( true ) as $item ) {
            if ( '' === $item['bestand'] ) {
                $eerste = null === $eerste ? (int) $item['volgende'] : min( $eerste, (int) $item['volgende'] );
            }
        }
        delete_transient( self::SLOT );
        if ( null !== $eerste ) {
            self::plan_kopieren( max( $eerste, time() + 5 ) );
        }
        // Pagina's die nog het Supabase-adres tekenden, staan in LiteSpeed
        // hooguit 5 minuten (zie url()); daarna tekenen ze de kopie.
        return $telling;
    }

    /**
     * Downloadt één foto naar de map. Geeft de bestandsnaam terug, of een
     * WP_Error met een Nederlandse reden.
     */
    private static function download( $url, $sleutel ) {
        if ( ! function_exists( 'wp_tempnam' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $tijdelijk = wp_tempnam( 'kz-spiegel' );
        if ( ! $tijdelijk ) {
            return new WP_Error( 'kz_spiegel', 'Geen tijdelijk bestand te maken.' );
        }

        $antwoord = wp_safe_remote_get(
            $url,
            array(
                'timeout'             => 20,
                'redirection'         => 2,
                'stream'              => true,
                'filename'            => $tijdelijk,
                'limit_response_size' => self::MAX_BYTES,
            )
        );

        if ( is_wp_error( $antwoord ) ) {
            wp_delete_file( $tijdelijk );
            return new WP_Error( 'kz_spiegel', $antwoord->get_error_message() );
        }
        $code = (int) wp_remote_retrieve_response_code( $antwoord );
        if ( 200 !== $code ) {
            wp_delete_file( $tijdelijk );
            return new WP_Error( 'kz_spiegel', 'HTTP ' . $code );
        }
        $grootte = (int) filesize( $tijdelijk );
        if ( $grootte <= 0 || $grootte >= self::MAX_BYTES ) {
            wp_delete_file( $tijdelijk );
            return new WP_Error( 'kz_spiegel', $grootte ? 'Groter dan 15 MB.' : 'Leeg bestand.' );
        }

        // Het type uit de inhoud zelf, niet uit wat de server zegt.
        $type = wp_get_image_mime( $tijdelijk );
        if ( $type && isset( self::TYPES[ $type ] ) ) {
            $extensie = self::TYPES[ $type ];
        } elseif ( self::is_veilige_svg( $tijdelijk ) ) {
            $extensie = 'svg';
        } else {
            wp_delete_file( $tijdelijk );
            return new WP_Error( 'kz_spiegel', 'Geen afbeelding (of een .svg met script erin).' );
        }

        $bestand = $sleutel . '.' . $extensie;
        $doel    = self::map_pad() . '/' . $bestand;
        if ( ! @rename( $tijdelijk, $doel ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- terugval hieronder.
            if ( ! @copy( $tijdelijk, $doel ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
                wp_delete_file( $tijdelijk );
                return new WP_Error( 'kz_spiegel', 'Kan niet schrijven in ' . self::MAP . '/.' );
            }
            wp_delete_file( $tijdelijk );
        }
        @chmod( $doel, 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        return $bestand;
    }

    /**
     * Een .svg (zoals de motto's) komt straks van ons eigen domein. Wie het
     * adres los opent, krijgt hem als document; dan mag er niets in zitten dat
     * iets uitvoert. Liever een keer te streng (dan blijft het Supabase-adres
     * staan) dan te los. De .htaccess in de map verbiedt scripts ook nog.
     */
    private static function is_veilige_svg( $pad ) {
        $inhoud = (string) file_get_contents( $pad ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        if ( ! preg_match( '/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE svg[^>[]*>\s*)?<svg[\s>]/is', $inhoud ) ) {
            return false;
        }
        return ! preg_match( '/<script|<foreignobject|<iframe|<embed|<object|<!entity|\son[a-z]+\s*=|javascript:|data:text\/html/i', $inhoud );
    }

    // ------------------------------------------------------------------
    // Opruimen (dagelijks)
    // ------------------------------------------------------------------

    /** Haalt kopieën weg die BEWAAR_DAGEN niet getekend zijn, en losse bestanden zonder item. */
    public static function opruimen() {
        $lijst  = self::lees( true );
        $grens  = time() - self::BEWAAR_DAGEN * DAY_IN_SECONDS;
        $map    = self::map_pad();
        foreach ( $lijst as $sleutel => $item ) {
            if ( (int) $item['gezien'] < $grens ) {
                if ( '' !== $item['bestand'] ) {
                    wp_delete_file( $map . '/' . $item['bestand'] );
                }
                unset( $lijst[ $sleutel ] );
            }
        }
        update_option( self::OPTIE, $lijst, false );

        $bekend = array();
        foreach ( $lijst as $item ) {
            if ( '' !== $item['bestand'] ) {
                $bekend[ $item['bestand'] ] = true;
            }
        }
        foreach ( (array) glob( $map . '/*' ) as $pad ) {
            $naam = basename( (string) $pad );
            if ( preg_match( '/^[a-f0-9]{32}\.(jpg|png|gif|webp|avif|svg)$/', $naam ) && ! isset( $bekend[ $naam ] ) ) {
                wp_delete_file( $pad );
            }
        }
    }

    // ------------------------------------------------------------------
    // Hulpjes
    // ------------------------------------------------------------------

    /** De lijst, één keer per verzoek gelezen. */
    private static function lijst() {
        if ( null === self::$lijst ) {
            self::$lijst = self::lees( false );
        }
        return self::$lijst;
    }

    private static function lees( $vers ) {
        if ( $vers ) {
            wp_cache_delete( self::OPTIE, 'options' );
        }
        $lijst = get_option( self::OPTIE, array() );
        return is_array( $lijst ) ? $lijst : array();
    }

    public static function map_pad() {
        $uploads = wp_upload_dir( null, false );
        return untrailingslashit( $uploads['basedir'] ) . '/' . self::MAP;
    }

    public static function map_url() {
        $uploads = wp_upload_dir( null, false );
        return set_url_scheme( untrailingslashit( $uploads['baseurl'] ), 'https' ) . '/' . self::MAP;
    }

    /** Maakt de map, met een .htaccess die nooit iets laat uitvoeren. */
    private static function maak_map() {
        $map = self::map_pad();
        if ( ! wp_mkdir_p( $map ) ) {
            return false;
        }
        if ( ! file_exists( $map . '/.htaccess' ) ) {
            $regels = "# KZ Plugin: kopieën van foto's uit KZ-Studio. Hier wordt nooit iets uitgevoerd.\n"
                . "<IfModule mod_headers.c>\n"
                . "Header set Content-Security-Policy \"default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox\"\n"
                . "Header set X-Content-Type-Options \"nosniff\"\n"
                . "Header set Cache-Control \"public, max-age=31536000, immutable\"\n"
                . "</IfModule>\n"
                . "<FilesMatch \"\\.(php|phtml|phar|html?|js)$\">\n"
                . "Require all denied\n"
                . "</FilesMatch>\n"
                . "Options -Indexes\n";
            file_put_contents( $map . '/.htaccess', $regels ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }
        return true;
    }

    /**
     * Voor het beheerscherm: hoeveel er gekopieerd is, hoeveel er wacht, en de
     * laatste fouten.
     *
     * @return array{gekopieerd:int,wachtend:int,bytes:int,fouten:array}
     */
    public static function overzicht() {
        $overzicht = array( 'gekopieerd' => 0, 'wachtend' => 0, 'bytes' => 0, 'fouten' => array() );
        $map       = self::map_pad();
        foreach ( self::lees( true ) as $item ) {
            if ( '' !== $item['bestand'] ) {
                ++$overzicht['gekopieerd'];
                $pad = $map . '/' . $item['bestand'];
                $overzicht['bytes'] += file_exists( $pad ) ? (int) filesize( $pad ) : 0;
            } else {
                ++$overzicht['wachtend'];
                if ( '' !== $item['fout'] ) {
                    $overzicht['fouten'][] = array( 'url' => $item['url'], 'fout' => $item['fout'], 'pogingen' => (int) $item['pogingen'] );
                }
            }
        }
        return $overzicht;
    }
}
