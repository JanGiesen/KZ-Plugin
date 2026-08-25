<?php
/**
 * Plugin Name: KZ Plugin
 * Plugin URI: https://github.com/JanGiesen/KZ-Plugin
 * Description: WPBakery-elementen voor de Kraonige Zwaone website (carrousel, knop, event blok, header link, hover afbeelding, tab widget, KZ Vindt grid, ticket).
 * Version: 8.1.3
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
 *
 * 8.1.2: dummy-release om de volledige auto-update-cyclus end-to-end te
 * testen (geen functionele wijzigingen).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Directe toegang niet toegestaan.
}

define( 'KZ_PLUGIN_VERSION', '8.1.3' );
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
        add_action( 'admin_notices', array( $this, 'legacy_plugin_notice' ) );

        // Registreer de twee WPBakery post-meta's zodat de REST API (KZ-Studio)
        // ze accepteert. Zie README voor achtergrond.
        add_action( 'init', array( $this, 'register_wpbakery_meta' ) );
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

        wp_register_style( 'swiper', KZ_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.css', array(), '11' );
        wp_register_script( 'swiper', KZ_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.js', array(), '11', true );
        wp_register_script( 'kz-carousel', KZ_PLUGIN_URL . 'assets/js/kz-carousel.js', array( 'swiper' ), KZ_PLUGIN_VERSION, true );
        wp_register_script( 'kz-tab-widget', KZ_PLUGIN_URL . 'assets/js/kz-tab-widget.js', array(), KZ_PLUGIN_VERSION, true );
    }

    public function enqueue_admin_assets() {
        wp_enqueue_style( 'kz-plugin-admin', KZ_PLUGIN_URL . 'assets/css/admin.css', array(), KZ_PLUGIN_VERSION );
    }

    /**
     * Deactiveert automatisch de oude losse plugins die nu in KZ-Plugin zijn
     * samengevoegd, zodat shortcodes niet dubbel geregistreerd raken.
     */
    public function maybe_deactivate_legacy_plugins() {
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
