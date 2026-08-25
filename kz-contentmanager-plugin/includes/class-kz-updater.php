<?php
/**
 * Lichte GitHub-releases updater voor KZ-plugins.
 *
 * Haalt releases op van een GitHub-repo, filtert op een tag-prefix (zodat
 * meerdere plugins dezelfde repo kunnen delen) en levert WordPress de
 * update-info via de `update_plugins_{hostname}`-hook die WordPress sinds
 * 5.8 ondersteunt voor plugins met een `Update URI`-header.
 *
 * Ondersteunt optioneel een GitHub-token (optie `kz_github_token`) voor
 * private repositories.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_CM_Updater {

    private $plugin_file;
    private $plugin_slug;
    private $plugin_basename;
    private $current_version;
    private $repo;
    private $tag_prefix;
    private $asset_name;
    private $cache_key;

    /**
     * @param string $plugin_file      __FILE__ van het plugin-hoofdbestand.
     * @param string $current_version  Huidige plugin-versie.
     * @param string $repo             "owner/repo" op GitHub.
     * @param string $tag_prefix       Tag-prefix voor deze plugin, bijv. "kz-plugin-v".
     * @param string $asset_name       Bestandsnaam van de release-zip, bijv. "kz-plugin.zip".
     */
    public function __construct( $plugin_file, $current_version, $repo, $tag_prefix, $asset_name ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_basename = plugin_basename( $plugin_file );
        $this->plugin_slug     = dirname( $this->plugin_basename );
        $this->current_version = $current_version;
        $this->repo            = $repo;
        $this->tag_prefix      = $tag_prefix;
        $this->asset_name      = $asset_name;
        $this->cache_key       = 'kz_updater_' . sanitize_key( $tag_prefix ) . '_release';

        add_filter( 'update_plugins_github.com', array( $this, 'check_for_update' ), 10, 3 );
        add_filter( 'upgrader_pre_download', array( $this, 'maybe_authenticate_download' ), 10, 3 );
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
    }

    /**
     * Haalt de laatste passende release op (met caching), of false.
     */
    public function get_latest_release( $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( $this->cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $releases = $this->fetch_releases();
        $release  = $this->find_matching_release( $releases );

        // Cache ook een "geen release"-resultaat, anders bij elke check een API-call.
        set_transient( $this->cache_key, $release, 6 * HOUR_IN_SECONDS );

        return $release;
    }

    private function fetch_releases() {
        $url  = "https://api.github.com/repos/{$this->repo}/releases";
        $args = array(
            'headers' => array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'KZ-Updater-WordPress',
            ),
            'timeout' => 15,
        );

        $token = get_option( 'kz_github_token' );
        if ( ! empty( $token ) ) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return is_array( $body ) ? $body : array();
    }

    private function find_matching_release( $releases ) {
        foreach ( $releases as $release ) {
            if ( empty( $release['tag_name'] ) ) {
                continue;
            }
            if ( 0 !== strpos( $release['tag_name'], $this->tag_prefix ) ) {
                continue;
            }
            if ( ! empty( $release['draft'] ) ) {
                continue;
            }

            $version = substr( $release['tag_name'], strlen( $this->tag_prefix ) );

            $asset_url = '';
            if ( ! empty( $release['assets'] ) ) {
                foreach ( $release['assets'] as $asset ) {
                    if ( isset( $asset['name'] ) && $asset['name'] === $this->asset_name ) {
                        $asset_url = $asset['url']; // API-asset-URL (werkt ook voor private repos met token).
                        break;
                    }
                }
            }

            if ( empty( $asset_url ) ) {
                // Fallback: zipball van de release zelf (werkt alleen bij juiste mapstructuur in de repo-root).
                continue;
            }

            return array(
                'version'   => $version,
                'tag'       => $release['tag_name'],
                'download'  => $asset_url,
                'changelog' => isset( $release['body'] ) ? $release['body'] : '',
                'published' => isset( $release['published_at'] ) ? $release['published_at'] : '',
            );
        }

        return false;
    }

    /**
     * WordPress-hook: `update_plugins_github.com`.
     */
    public function check_for_update( $update, $plugin_data, $plugin_file ) {
        if ( $plugin_file !== $this->plugin_basename ) {
            return $update;
        }

        $release = $this->get_latest_release();

        if ( ! $release ) {
            return $update;
        }

        if ( ! version_compare( $release['version'], $this->current_version, '>' ) ) {
            return $update;
        }

        return array(
            'id'            => 'github.com/' . $this->repo . '/' . $this->plugin_slug,
            'slug'          => $this->plugin_slug,
            'plugin'        => $this->plugin_basename,
            'new_version'   => $release['version'],
            'url'           => 'https://github.com/' . $this->repo,
            'package'       => $release['download'],
            'icons'         => array(),
            'banners'       => array(),
            'banners_rgba'  => array(),
            'tested'        => '',
            'requires_php'  => '',
            'compatibility' => new stdClass(),
        );
    }

    /**
     * Voegt de GitHub-token-header toe wanneer WordPress de release-asset downloadt
     * (nodig zolang de repo privé is, want de asset-API levert dan alleen data met auth).
     */
    public function maybe_authenticate_download( $reply, $package, $upgrader ) {
        if ( false === strpos( (string) $package, 'api.github.com/repos/' . $this->repo ) ) {
            return $reply;
        }

        $token = get_option( 'kz_github_token' );
        if ( empty( $token ) ) {
            return $reply;
        }

        add_filter(
            'http_request_args',
            function ( $args ) use ( $token ) {
                $args['headers']['Authorization'] = 'Bearer ' . $token;
                $args['headers']['Accept']         = 'application/octet-stream';
                return $args;
            },
            10,
            1
        );

        return $reply;
    }

    public function plugin_row_meta( $links, $file ) {
        if ( $file !== $this->plugin_basename ) {
            return $links;
        }
        $links[] = '<a href="https://github.com/' . esc_attr( $this->repo ) . '" target="_blank" rel="noopener">GitHub</a>';
        return $links;
    }

    public function clear_cache() {
        delete_transient( $this->cache_key );
    }

    public function get_current_version() {
        return $this->current_version;
    }
}
