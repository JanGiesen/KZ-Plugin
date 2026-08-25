<?php
/**
 * Instellingenpagina voor KZ-Plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Admin {

    /** @var KZ_Updater */
    private $updater;

    /** Elementen-metadata voor het overzicht + aan/uit-schakelaars. */
    private $elements = array(
        'post_carousel'    => array(
            'label'     => 'KZ-PostCarrousel',
            'shortcode' => 'kz_post_doorscroll',
            'params'    => 'featured_image, images, max_width',
        ),
        'button'           => array(
            'label'     => 'KZ-Knop',
            'shortcode' => 'kz_knop',
            'params'    => 'text, link, color, align, margins, margin',
        ),
        'eventhome_block'  => array(
            'label'     => 'KZ-Event Blok',
            'shortcode' => 'kz_eventhome_blok',
            'params'    => 'date, title, link, past, margin_bottom',
        ),
        'header_link'      => array(
            'label'     => 'KZ-Header Link',
            'shortcode' => 'kz_header_link',
            'params'    => 'text, link, color, align, margins',
        ),
        'hover_image'      => array(
            'label'     => 'KZ-Hover Afbeelding',
            'shortcode' => 'kz_hoverafbeelding',
            'params'    => 'image, link, width, align, margins',
        ),
        'tab_widget'       => array(
            'label'     => 'KZ-Tab Widget',
            'shortcode' => 'kz_tab_widget',
            'params'    => 'widget_id, years (param_group)',
        ),
        'vindt_grid'       => array(
            'label'     => 'KZ Vindt Grid',
            'shortcode' => 'kz_vindt_grid',
            'params'    => 'columns, column_headers, inverted, items (param_group)',
        ),
        'ticket'           => array(
            'label'     => 'KZH Ticket',
            'shortcode' => 'kzh_ticket',
            'params'    => 'logo_type, main_text, subtitle, link, color_preset, max_width, el_class',
        ),
    );

    public function __construct( KZ_Updater $updater ) {
        $this->updater = $updater;
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_post_kz_plugin_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_kz_plugin_check_updates', array( $this, 'handle_check_updates' ) );
    }

    public static function get_disabled_elements() {
        $disabled = get_option( 'kz_plugin_disabled_elements', array() );
        return is_array( $disabled ) ? $disabled : array();
    }

    public static function is_element_enabled( $key ) {
        return ! in_array( $key, self::get_disabled_elements(), true );
    }

    public function add_menu() {
        add_menu_page(
            'KZ Plugin',
            'KZ Plugin',
            'manage_options',
            'kz-plugin',
            array( $this, 'render_page' ),
            'dashicons-admin-generic',
            58
        );
    }

    public function save_settings() {
        check_admin_referer( 'kz_plugin_settings' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Geen toegang' );
        }

        $disabled = array();
        if ( isset( $_POST['kz_enabled'] ) && is_array( $_POST['kz_enabled'] ) ) {
            $enabled_keys = array_map( 'sanitize_key', wp_unslash( $_POST['kz_enabled'] ) );
        } else {
            $enabled_keys = array();
        }
        foreach ( array_keys( $this->elements ) as $key ) {
            if ( ! in_array( $key, $enabled_keys, true ) ) {
                $disabled[] = $key;
            }
        }
        update_option( 'kz_plugin_disabled_elements', $disabled );

        if ( isset( $_POST['kz_github_token'] ) ) {
            update_option( 'kz_github_token', sanitize_text_field( wp_unslash( $_POST['kz_github_token'] ) ) );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'kz-plugin', 'kz_saved' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_check_updates() {
        check_admin_referer( 'kz_plugin_check_updates' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Geen toegang' );
        }

        $this->updater->clear_cache();
        $this->updater->get_latest_release( true );

        if ( function_exists( 'wp_update_plugins' ) ) {
            delete_site_transient( 'update_plugins' );
            wp_update_plugins();
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'kz-plugin', 'kz_checked' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $disabled_elements = self::get_disabled_elements();
        $github_token      = get_option( 'kz_github_token', '' );
        $latest_release    = $this->updater->get_latest_release();
        $current_version   = $this->updater->get_current_version();
        ?>
        <div class="wrap kz-plugin-admin">
            <h1>KZ Plugin</h1>

            <?php if ( isset( $_GET['kz_saved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen.</p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['kz_checked'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Update-controle uitgevoerd.</p></div>
            <?php endif; ?>

            <h2>Updates</h2>
            <table class="widefat" style="max-width: 700px;">
                <tbody>
                    <tr>
                        <td><strong>Huidige versie</strong></td>
                        <td><?php echo esc_html( $current_version ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Laatst gevonden release</strong></td>
                        <td>
                            <?php
                            if ( $latest_release ) {
                                echo esc_html( $latest_release['version'] );
                                if ( version_compare( $latest_release['version'], $current_version, '>' ) ) {
                                    echo ' &mdash; <span style="color:#d63638;">nieuwe versie beschikbaar</span>';
                                } else {
                                    echo ' &mdash; up-to-date';
                                }
                            } else {
                                echo 'Geen release gevonden (of nog niet gecontroleerd).';
                            }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 10px;">
                <?php wp_nonce_field( 'kz_plugin_check_updates' ); ?>
                <input type="hidden" name="action" value="kz_plugin_check_updates" />
                <button type="submit" class="button button-secondary">Nu controleren op updates</button>
            </form>

            <h2 style="margin-top: 30px;">Elementen</h2>
            <p>Overzicht van alle shortcodes/WPBakery-elementen. Uitzetten laat de shortcode geregistreerd (bestaande content blijft dus intact) maar de elementweergave wordt leeg.</p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'kz_plugin_settings' ); ?>
                <input type="hidden" name="action" value="kz_plugin_save_settings" />

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Aan/Uit</th>
                            <th>Element</th>
                            <th>Shortcode</th>
                            <th>Parameters</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $this->elements as $key => $el ) : ?>
                            <tr>
                                <td>
                                    <label class="screen-reader-text" for="kz-el-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $el['label'] ); ?></label>
                                    <input type="checkbox" id="kz-el-<?php echo esc_attr( $key ); ?>" name="kz_enabled[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ! in_array( $key, $disabled_elements, true ) ); ?> />
                                </td>
                                <td><strong><?php echo esc_html( $el['label'] ); ?></strong></td>
                                <td><code>[<?php echo esc_html( $el['shortcode'] ); ?>]</code></td>
                                <td><?php echo esc_html( $el['params'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="margin-top: 30px;">GitHub-token (optioneel)</h2>
                <p>Alleen nodig zolang de plugin-repository privé is. Fine-grained token met alleen leestoegang tot deze repo.</p>
                <input type="text" name="kz_github_token" value="<?php echo esc_attr( $github_token ); ?>" class="regular-text" placeholder="ghp_..." />

                <p class="submit">
                    <button type="submit" class="button button-primary">Instellingen opslaan</button>
                </p>
            </form>
        </div>
        <?php
    }
}
