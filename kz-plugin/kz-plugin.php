<?php
/**
 * Plugin Name: KZ Plugin
 * Plugin URI: https://github.com/JanGiesen/KZ-Plugin
 * Description: WPBakery-elementen voor de Kraonige Zwaone website (carrousel, knop, event blok, header link, hover afbeelding, tab widget, KZ Vindt grid, ticket, recente galerijen, recente albums, evenement info, evenementen overzicht, motto, pagina, groeperingen, nieuws).
 * Version: 8.34.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Kraonige Zwaone
 * Text Domain: kz-plugin
 * Update URI: https://github.com/JanGiesen/KZ-Plugin
 *
 * Samenvoeging van:
 * - KZ-Kraonige Zwaone Plugin 7.1 (post carrousel, knop, event blok, header link, hover afbeelding, tab widget)
 * - KZ Vindt 1.0 (grid)
 * - KZH Ticket Element 1.0.0 (ticket)
 *
 * Belangrijk: alle shortcode-namen en parameters zijn ongewijzigd overgenomen
 * zodat bestaande content op de site niet breekt.
 * Wijzigingen per versie: zie CHANGELOG.md in de repository.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Directe toegang niet toegestaan.
}

define( 'KZ_PLUGIN_VERSION', '8.34.0' );
define( 'KZ_PLUGIN_FILE', __FILE__ );
define( 'KZ_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'KZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KZ_PLUGIN_GITHUB_REPO', 'JanGiesen/KZ-Plugin' );

class KZ_Plugin {

    /** @var KZ_Plugin_Updater */
    private $updater;

    /** @var array<string,string> map van element-key naar class-naam */
    private $element_classes = array(
        'post_carousel'   => 'KZ_Element_Post_Carousel',
        'button'          => 'KZ_Element_Button',
        'eventhome_block' => 'KZ_Element_Eventhome_Block',
        'header_link'     => 'KZ_Element_Header_Link',
        'hover_image'     => 'KZ_Element_Hover_Image',
        'tab_widget'      => 'KZ_Element_Tab_Widget',
        'vindt_grid'      => 'KZ_Element_Vindt_Grid',
        'ticket'          => 'KZ_Element_Ticket',
        'recent_galleries' => 'KZ_Element_Recent_Galleries',
        'recent_albums'    => 'KZ_Element_Recent_Albums',
        'recent_products'  => 'KZ_Element_Recent_Products',
        'event_info'       => 'KZ_Element_Event_Info',
        'event_overview'   => 'KZ_Element_Event_Overview',
        'motto'            => 'KZ_Element_Motto',
        'pagina'           => 'KZ_Element_Pagina',
        'groeperingen'     => 'KZ_Element_Groeperingen',
        'nieuws'           => 'KZ_Element_Nieuws',
    );

    public function __construct() {
        $this->load_dependencies();

        $this->updater = new KZ_Plugin_Updater(
            KZ_PLUGIN_FILE,
            KZ_PLUGIN_VERSION,
            KZ_PLUGIN_GITHUB_REPO,
            'kz-plugin-v',
            'kz-plugin.zip'
        );

        new KZ_Admin( $this->updater );

        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'vc_before_init', array( $this, 'register_wpbakery_elements' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'maybe_deactivate_legacy_plugins' ) );
        // Wordt er een plugin aangezet, dan bij het volgende scherm opnieuw kijken.
        add_action( 'activated_plugin', function () {
            delete_option( 'kz_legacy_gecontroleerd' );
        } );
        add_action( 'admin_notices', array( $this, 'legacy_plugin_notice' ) );

        // Registreer de twee WPBakery post-meta's zodat de REST API (KZ-Studio)
        // ze accepteert. Zie README voor achtergrond.
        add_action( 'init', array( $this, 'register_wpbakery_meta' ) );

        // Post type voor evenementen uit KZ-Studio (REST: wp/v2/kz-evenementen).
        KZ_Evenementen::init();

        // Link-In-Bio op link.kraonigezwaone.nl (inhoud uit KZ-Studio).
        KZ_Link_In_Bio::init();

        // KZ-Groeperingen en KZ-Nieuws: cache legen als een bericht verandert.
        KZ_Berichten_Grid::init();

        // Kopie op deze site van de foto's uit KZ-Studio.
        KZ_Spiegel::init();
        register_deactivation_hook( KZ_PLUGIN_FILE, array( 'KZ_Spiegel', 'deactiveren' ) );
    }

    private function load_dependencies() {
        require_once KZ_PLUGIN_PATH . 'includes/class-kz-updater.php';
        require_once KZ_PLUGIN_PATH . 'includes/class-kz-admin.php';
        require_once KZ_PLUGIN_PATH . 'includes/class-kz-link-helper.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-post-carousel.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-button.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-eventhome-block.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-header-link.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-hover-image.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-tab-widget.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-vindt-grid.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-ticket.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-recent-galleries.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-recent-albums.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-recent-products.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-event-info.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-event-overview.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-motto.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-pagina.php';
        require_once KZ_PLUGIN_PATH . 'includes/class-kz-berichten-grid.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-groeperingen.php';
        require_once KZ_PLUGIN_PATH . 'includes/elements/class-kz-nieuws.php';
        require_once KZ_PLUGIN_PATH . 'includes/class-kz-evenementen.php';
        require_once KZ_PLUGIN_PATH . 'includes/class-kz-link-in-bio.php';
        require_once KZ_PLUGIN_PATH . 'includes/class-kz-spiegel.php';
    }

    /**
     * Geeft de twee WPBakery-post-meta's vrij voor de REST API.
     *
     * KZ-Studio plaatst nieuwsberichten via de standaard WordPress REST API. Die
     * accepteert `meta` alleen voor velden die expliciet zijn geregistreerd, en
     * WPBakery registreert deze twee niet — die worden normaal bij het opslaan
     * in de editor weggeschreven, en daar komt een bericht uit de REST API nooit
     * langs. Zonder deze registratie verschijnt het bericht wel, maar zonder de
     * custom CSS van de elementen (de marges kloppen dan niet).
     */
    public function register_wpbakery_meta() {
        $velden = array(
            '_wpb_vc_js_status'          => 'sanitize_text_field',
            '_wpb_shortcodes_custom_css' => 'wp_strip_all_tags',
        );

        foreach ( $velden as $sleutel => $schoonmaker ) {
            register_post_meta(
                'post',
                $sleutel,
                array(
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => true,
                    'sanitize_callback' => $schoonmaker,
                    'auth_callback'     => function () {
                        return current_user_can( 'edit_posts' );
                    },
                )
            );
        }
    }

    public function register_shortcodes() {
        foreach ( $this->element_classes as $key => $class ) {
            if ( KZ_Admin::is_element_enabled( $key ) ) {
                call_user_func( array( $class, 'register_shortcode' ) );
            } else {
                // Shortcode blijft geregistreerd (levert lege string) zodat
                // bestaande content nooit een kale shortcode-tekst toont.
                add_shortcode( constant( "{$class}::SHORTCODE" ), '__return_empty_string' );
            }
        }
    }

    public function register_wpbakery_elements() {
        foreach ( $this->element_classes as $key => $class ) {
            if ( KZ_Admin::is_element_enabled( $key ) ) {
                call_user_func( array( $class, 'register_vc_map' ) );
            }
        }
    }

    public function register_assets() {
        wp_register_style( 'kz-plugin-style', KZ_PLUGIN_URL . 'assets/css/style.css', array(), KZ_PLUGIN_VERSION );
        wp_enqueue_style( 'kz-plugin-style' );

        wp_register_style( 'kz-vindt-style', KZ_PLUGIN_URL . 'assets/css/kz-vindt.css', array(), KZ_PLUGIN_VERSION );

        wp_register_style( 'kz-recente-galerijen-style', KZ_PLUGIN_URL . 'assets/css/kz-recente-galerijen.css', array(), KZ_PLUGIN_VERSION );

        wp_register_style( 'kz-winkel-producten-style', KZ_PLUGIN_URL . 'assets/css/kz-winkel-producten.css', array(), KZ_PLUGIN_VERSION );

        wp_register_style( 'kz-evenement-info-style', KZ_PLUGIN_URL . 'assets/css/kz-evenement-info.css', array(), KZ_PLUGIN_VERSION );
        wp_register_style( 'kz-evenementen-overzicht-style', KZ_PLUGIN_URL . 'assets/css/kz-evenementen-overzicht.css', array( 'kz-evenement-info-style' ), KZ_PLUGIN_VERSION );
        wp_register_style( 'kz-berichten-grid-style', KZ_PLUGIN_URL . 'assets/css/kz-berichten-grid.css', array(), KZ_PLUGIN_VERSION );
        wp_register_style( 'kz-pagina-style', KZ_PLUGIN_URL . 'assets/css/kz-pagina.css', array(), KZ_PLUGIN_VERSION );
        wp_register_style( 'kz-evenement-info-fonts', 'https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@700&family=Work+Sans:wght@400;500&display=swap', array(), null );

        wp_register_style( 'swiper', KZ_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.css', array(), '11.2.10' );
        wp_register_script( 'swiper', KZ_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.js', array(), '11.2.10', true );
        wp_register_script( 'kz-carousel', KZ_PLUGIN_URL . 'assets/js/kz-carousel.js', array( 'swiper' ), KZ_PLUGIN_VERSION, true );
        wp_register_script( 'kz-evenement-info', KZ_PLUGIN_URL . 'assets/js/kz-evenement-info.js', array(), KZ_PLUGIN_VERSION, true );
        wp_register_script( 'kz-tab-widget', KZ_PLUGIN_URL . 'assets/js/kz-tab-widget.js', array(), KZ_PLUGIN_VERSION, true );
        wp_register_script( 'kz-pagina', KZ_PLUGIN_URL . 'assets/js/kz-pagina.js', array(), KZ_PLUGIN_VERSION, true );
    }

    public function enqueue_admin_assets() {
        wp_enqueue_style( 'kz-plugin-admin', KZ_PLUGIN_URL . 'assets/css/admin.css', array(), KZ_PLUGIN_VERSION );
    }

    /**
     * Deactiveert automatisch de oude losse plugins die nu in KZ-Plugin zijn
     * samengevoegd, zodat shortcodes niet dubbel geregistreerd raken.
     */
    public function maybe_deactivate_legacy_plugins() {
        // get_plugins() leest alle plugin-bestanden van schijf; niet bij elk
        // beheerscherm (en elke admin-ajax), maar eens per versie of activatie.
        if ( get_option( 'kz_legacy_gecontroleerd' ) === KZ_PLUGIN_VERSION ) {
            return;
        }
        update_option( 'kz_legacy_gecontroleerd', KZ_PLUGIN_VERSION, true );

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $legacy_names = array(
            'KZ-Kraonige Zwaone Plugin',
            'KZ Vindt',
            'KZH Ticket Element',
        );

        $all_plugins    = get_plugins();
        $deactivated    = array();

        foreach ( $all_plugins as $plugin_path => $plugin_data ) {
            if ( $plugin_path === plugin_basename( KZ_PLUGIN_FILE ) ) {
                continue;
            }
            if ( in_array( $plugin_data['Name'], $legacy_names, true ) && is_plugin_active( $plugin_path ) ) {
                deactivate_plugins( $plugin_path );
                $deactivated[] = $plugin_data['Name'];
            }
        }

        if ( ! empty( $deactivated ) ) {
            set_transient( 'kz_plugin_legacy_deactivated', $deactivated, MINUTE_IN_SECONDS * 5 );
        }
    }

    public function legacy_plugin_notice() {
        $deactivated = get_transient( 'kz_plugin_legacy_deactivated' );
        if ( empty( $deactivated ) ) {
            return;
        }
        delete_transient( 'kz_plugin_legacy_deactivated' );
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>KZ Plugin</strong> heeft de volgende oude plugin(s) automatisch gedeactiveerd omdat de functionaliteit nu is overgenomen:
                <?php echo esc_html( implode( ', ', $deactivated ) ); ?>.
                Ze kunnen desgewenst verwijderd worden.
            </p>
        </div>
        <?php
    }
}

new KZ_Plugin();
