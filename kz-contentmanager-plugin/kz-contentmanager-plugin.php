<?php
/**
 * Plugin Name: KZ Contentmanager Plugin
 * Plugin URI: https://www.kraonigezwaone.nl/
 * Description: Haalt de content voor de site op vanuit Google Docs.
 * Version: 3.0.2
 * Author: Kraonige Zwaone
 * License: GPL v2 or later
 * Text Domain: kz-contentmanager-plugin
 * Update URI: https://github.com/JanGiesen/KZ-Plugin
 *
 * Opvolger van KZ-Kraonige Zwaone Contentmanager 2.0.0. Zelfde database-tabel
 * (`{$wpdb->prefix}kz_documents`), zelfde opties en zelfde shortcode
 * `[kz-content]` zodat bestaande content niet breekt.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KZ_CONTENTMANAGER_VERSION', '3.0.2' );
define( 'KZ_CONTENTMANAGER_PLUGIN_FILE', __FILE__ );
define( 'KZ_CONTENTMANAGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KZ_CONTENTMANAGER_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'KZ_CONTENTMANAGER_GITHUB_REPO', 'JanGiesen/KZ-Plugin' );

class KZ_ContentManager {

    /** @var KZ_CM_Updater */
    private $updater;

    public function __construct() {
        $this->load_dependencies();

        $this->updater = new KZ_CM_Updater(
            KZ_CONTENTMANAGER_PLUGIN_FILE,
            KZ_CONTENTMANAGER_VERSION,
            KZ_CONTENTMANAGER_GITHUB_REPO,
            'kz-contentmanager-v',
            'kz-contentmanager-plugin.zip'
        );

        new KZ_Webhook();

        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'wp_ajax_kz_sync_documents', array( $this, 'sync_documents_ajax_handler' ) );
        add_action( 'wp_ajax_kz_sync_single_document', array( $this, 'sync_single_document_ajax_handler' ) );
        add_action( 'wp_ajax_kz_get_documents', array( $this, 'get_documents' ) );
        add_shortcode( 'kz-content', array( $this, 'content_shortcode' ) );
        add_action( 'admin_init', array( $this, 'maybe_deactivate_legacy_plugin' ) );
        add_action( 'admin_notices', array( $this, 'legacy_plugin_notice' ) );

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    private function load_dependencies() {
        require_once KZ_CONTENTMANAGER_PLUGIN_PATH . 'includes/class-kz-updater.php';
        require_once KZ_CONTENTMANAGER_PLUGIN_PATH . 'includes/class-kz-google-drive.php';
        require_once KZ_CONTENTMANAGER_PLUGIN_PATH . 'includes/class-kz-webhook.php';
    }

    public function init() {
        load_plugin_textdomain( 'kz-contentmanager-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function activate() {
        $this->create_tables();

        add_option( 'kz_google_drive_folder_id', '' );
        add_option( 'kz_google_api_key', '' );
        add_option( 'kz_last_sync', '' );
        add_option( 'kz_webhook_token', wp_generate_password( 20, false ) );
        add_option( 'kz_last_webhook_sync', 0 );
        add_option( 'kz_rate_limit_seconds', 300 );
        add_option( 'kz_cm_debug', 0 );
    }

    public function deactivate() {
        // Opties en tabel blijven staan: sync-configuratie blijft werken na
        // een her-activatie of upgrade.
    }

    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name       = $wpdb->prefix . 'kz_documents';

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            document_id varchar(255) NOT NULL,
            document_name varchar(255) NOT NULL,
            last_modified datetime DEFAULT CURRENT_TIMESTAMP,
            content longtext NOT NULL,
            components longtext NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY document_id (document_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function add_admin_menu() {
        add_menu_page(
            'KZ Contentmanager',
            'KZ Contentmanager',
            'manage_options',
            'kz-contentmanager',
            array( $this, 'admin_page' ),
            'dashicons-bird',
            30
        );

        add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );
    }

    public function load_admin_assets( $hook ) {
        if ( false !== strpos( $hook, 'kz-contentmanager' ) ) {
            wp_enqueue_style( 'kz-admin-style', KZ_CONTENTMANAGER_PLUGIN_URL . 'assets/css/admin-style.css', array(), KZ_CONTENTMANAGER_VERSION );
        }
    }

    public function admin_page() {
        $kz_cm_updater = $this->updater;
        include KZ_CONTENTMANAGER_PLUGIN_PATH . 'admin/admin-page.php';
    }

    public function sync_documents_ajax_handler() {
        check_ajax_referer( 'kz_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Geen toegang' );
        }

        $google_drive = new KZ_Google_Drive_Handler();
        $result       = $google_drive->sync_documents();

        if ( $result['success'] ) {
            wp_send_json_success( $result['message'] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    public function sync_single_document_ajax_handler() {
        check_ajax_referer( 'kz_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Geen toegang' );
        }

        $document_id  = isset( $_POST['document_id'] ) ? sanitize_text_field( wp_unslash( $_POST['document_id'] ) ) : '';
        $google_drive = new KZ_Google_Drive_Handler();
        $result       = $google_drive->sync_single_document( $document_id );

        if ( $result['success'] ) {
            wp_send_json_success( $result['message'] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    public function get_documents() {
        check_ajax_referer( 'kz_nonce', 'nonce' );

        global $wpdb;
        $table_name = $wpdb->prefix . 'kz_documents';

        $documents = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY document_name" );

        wp_send_json_success( $documents );
    }

    public function content_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'page'      => '',
                'component' => '',
            ),
            $atts
        );

        if ( empty( $atts['page'] ) || empty( $atts['component'] ) ) {
            return '<p>Fout: Pagina en component naam zijn vereist.</p>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'kz_documents';

        $document = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE document_name = %s",
                $atts['page']
            )
        );

        if ( ! $document ) {
            return '<p>Document niet gevonden: ' . esc_html( $atts['page'] ) . '</p>';
        }

        $components = json_decode( $document->components, true );

        if ( ! isset( $components[ $atts['component'] ] ) ) {
            return '<p>Component niet gevonden: ' . esc_html( $atts['component'] ) . '</p>';
        }

        return '<div class="kz-content-component">' . $components[ $atts['component'] ]['content'] . '</div>';
    }

    /**
     * Deactiveert automatisch de oude losse contentmanager-plugin.
     * Opties en de documenten-tabel blijven ongemoeid, dus sync-config en
     * gesynchroniseerde content blijven direct werken onder deze plugin.
     */
    public function maybe_deactivate_legacy_plugin() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();

        foreach ( $all_plugins as $plugin_path => $plugin_data ) {
            if ( $plugin_path === plugin_basename( __FILE__ ) ) {
                continue;
            }
            if ( 'KZ-Kraonige Zwaone Contentmanager' === $plugin_data['Name'] && is_plugin_active( $plugin_path ) ) {
                deactivate_plugins( $plugin_path );
                set_transient( 'kz_cm_legacy_deactivated', 1, MINUTE_IN_SECONDS * 5 );
            }
        }
    }

    public function legacy_plugin_notice() {
        if ( ! get_transient( 'kz_cm_legacy_deactivated' ) ) {
            return;
        }
        delete_transient( 'kz_cm_legacy_deactivated' );
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>KZ Contentmanager Plugin</strong> heeft de oude plugin "KZ-Kraonige Zwaone Contentmanager" automatisch gedeactiveerd.
                Je synchronisatie-instellingen en gesynchroniseerde documenten blijven gewoon werken. De oude plugin kan verwijderd worden.
            </p>
        </div>
        <?php
    }
}

// Initialiseer de plugin
new KZ_ContentManager();
