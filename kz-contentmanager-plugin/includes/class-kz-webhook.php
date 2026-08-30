<?php
/**
 * Webhook-sync dashboard: externe synchronisatie zonder inloggen via een
 * beveiligde URL (?kz_webhook_sync=true&token=...).
 * Overgenomen uit KZ-Kraonige Zwaone Contentmanager 2.0.0, gedrag ongewijzigd
 * (token-vergelijking is nu timing-safe via hash_equals()).
 *
 * De POST-acties (list_documents, sync_all, sync_single) geven naast de
 * HTML-pagina ook een JSON-response met de documentenlijst, zodat externe
 * consumenten (zoals kz-studio) hun eigen UI kunnen bouwen op dezelfde
 * beveiligde endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_Webhook {

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'handle_webhook_sync' ) );
    }

    public function handle_webhook_sync() {
        if ( ! isset( $_GET['kz_webhook_sync'] ) || 'true' !== $_GET['kz_webhook_sync'] ) {
            return;
        }

        $token         = get_option( 'kz_webhook_token' );
        $provided_token = isset( $_GET['token'] ) ? (string) $_GET['token'] : '';

        if ( empty( $token ) || ! hash_equals( (string) $token, $provided_token ) ) {
            if ( isset( $_POST['action'] ) ) {
                header( 'Content-Type: application/json' );
                echo wp_json_encode( array( 'success' => false, 'data' => 'Invalid token' ) );
                exit;
            }

            http_response_code( 401 );
            echo 'Invalid token';
            exit;
        }

        if ( isset( $_POST['action'] ) ) {
            $this->handle_webhook_ajax();
            exit;
        }

        $this->show_webhook_sync_page();
        exit;
    }

    private function handle_webhook_ajax() {
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        ob_start();

        header( 'Content-Type: application/json' );
        header( 'Cache-Control: no-cache' );

        remove_all_actions( 'wp_footer' );
        remove_all_actions( 'wp_head' );
        remove_all_actions( 'shutdown' );

        $action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

        if ( 'list_documents' === $action ) {
            ob_clean();
            echo wp_json_encode(
                array(
                    'success'   => true,
                    'data'      => 'OK',
                    'documents' => $this->get_documents_payload(),
                )
            );
            ob_end_flush();
            die();
        }

        if ( 'sync_all' === $action ) {
            $last_webhook_sync = get_option( 'kz_last_webhook_sync', 0 );
            $current_time      = time();
            $rate_limit        = get_option( 'kz_rate_limit_seconds', 300 );

            if ( $rate_limit > 0 && ( $current_time - $last_webhook_sync ) < $rate_limit ) {
                ob_clean();
                echo wp_json_encode(
                    array(
                        'success'   => false,
                        'data'      => 'Rate limit exceeded. Please wait before syncing all documents again.',
                        'documents' => $this->get_documents_payload(),
                    )
                );
                ob_end_flush();
                die();
            }

            update_option( 'kz_last_webhook_sync', $current_time );
        }

        $google_drive = new KZ_CM_Google_Drive_Handler();

        if ( 'sync_all' === $action ) {
            $result = $google_drive->sync_documents();
        } elseif ( 'sync_single' === $action && isset( $_POST['document_id'] ) ) {
            $document_id = sanitize_text_field( wp_unslash( $_POST['document_id'] ) );
            $result      = $google_drive->sync_single_document( $document_id );
        } else {
            ob_clean();
            echo wp_json_encode( array( 'success' => false, 'data' => 'Invalid action' ) );
            ob_end_flush();
            die();
        }

        ob_clean();
        echo wp_json_encode(
            array(
                'success'   => (bool) $result['success'],
                'data'      => $result['message'],
                'documents' => $this->get_documents_payload(),
            )
        );
        ob_end_flush();
        die();
    }

    /**
     * Haalt de gesynchroniseerde documenten op in een JSON-vriendelijke vorm,
     * zodat externe consumenten (zoals kz-studio) ze kunnen tonen zonder de
     * HTML-dashboardpagina te hoeven parsen.
     *
     * @return array<int, array<string, mixed>>
     */
    private function get_documents_payload() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kz_documents';
        $documents  = $wpdb->get_results( "SELECT document_id, document_name, last_modified FROM $table_name ORDER BY document_name" );

        return array_map(
            static function ( $doc ) {
                $utc_time = new DateTime( $doc->last_modified, new DateTimeZone( 'UTC' ) );

                $dutch_time = clone $utc_time;
                $dutch_time->setTimezone( new DateTimeZone( 'Europe/Amsterdam' ) );

                return array(
                    'document_id'          => $doc->document_id,
                    'document_name'        => $doc->document_name,
                    'last_modified'        => $utc_time->format( DateTime::ATOM ),
                    'last_modified_europe' => $dutch_time->format( 'd-m-Y H:i' ),
                );
            },
            $documents
        );
    }

    private function show_webhook_sync_page() {
        global $wpdb;
        $table_name     = $wpdb->prefix . 'kz_documents';
        $documents      = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY document_name" );
        $last_sync      = get_option( 'kz_last_sync' );
        $webhook_token  = get_option( 'kz_webhook_token' );
        $rate_limit     = get_option( 'kz_rate_limit_seconds', 300 );
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>KZ Contentmanager - Sync Dashboard</title>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f1f1f1; }
                .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #333; margin-bottom: 30px; }
                .sync-status { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
                .btn { background: #0073aa; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-right: 10px; }
                .btn:hover { background: #005a87; }
                .btn:disabled { background: #ccc; cursor: not-allowed; }
                .btn-secondary { background: #666; }
                .btn-secondary:hover { background: #555; }
                .document-list { margin-top: 30px; }
                .document-item { background: #f9f9f9; padding: 15px; margin-bottom: 10px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
                .document-name { font-weight: 600; }
                .result { margin-top: 15px; padding: 10px; border-radius: 4px; display: none; }
                .result.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
                .result.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                .loading { display: none; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🔄 KZ Contentmanager - Sync Dashboard</h1>

                <div style="background: #e7f3ff; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #0c5460;">
                    <p style="margin: 0; font-size: 14px;">
                        <?php
                        if ( $rate_limit > 0 ) {
                            $minutes   = floor( $rate_limit / 60 );
                            $seconds   = $rate_limit % 60;
                            $time_text = $minutes > 0 ? $minutes . ' minuten' : $seconds . ' seconden';
                            echo 'Je kunt hieronder de pagina\'s synchroniseren. "Synchroniseer alle pagina\'s" kan maar eens in de ' . esc_html( $time_text ) . '.';
                        } else {
                            echo 'Je kunt hieronder de pagina\'s synchroniseren. "Synchroniseer alle pagina\'s" heeft geen rate limiting.';
                        }
                        ?>
                        Individuele pagina's kun je zo vaak syncen als je wilt. Succes! Jan
                    </p>
                </div>

                <div class="sync-status">
                    <p><strong>Laatste sync:</strong>
                        <?php
                        if ( $last_sync ) {
                            $dutch_time = new DateTime( $last_sync, new DateTimeZone( 'UTC' ) );
                            $dutch_time->setTimezone( new DateTimeZone( 'Europe/Amsterdam' ) );
                            echo esc_html( $dutch_time->format( 'd-m-Y H:i:s' ) );
                        } else {
                            echo 'Nog nooit gesynchroniseerd';
                        }
                        ?>
                    </p>
                    <button id="sync-all-btn" class="btn">🔄 Synchroniseer alle pagina's</button>
                    <span id="sync-all-loading" class="loading">Synchroniseren...</span>
                </div>

                <div id="sync-all-result" class="result"></div>

                <div class="document-list">
                    <h2>Pagina's (<?php echo count( $documents ); ?>)</h2>

                    <?php if ( empty( $documents ) ) : ?>
                        <p>Geen pagina's gevonden. Synchroniseer eerst alle pagina's.</p>
                    <?php else : ?>
                        <?php foreach ( $documents as $doc ) : ?>
                            <div class="document-item">
                                <div>
                                    <div class="document-name"><?php echo esc_html( $doc->document_name ); ?></div>
                                    <small>Laatst gewijzigd:
                                        <?php
                                        $dutch_time = new DateTime( $doc->last_modified, new DateTimeZone( 'UTC' ) );
                                        $dutch_time->setTimezone( new DateTimeZone( 'Europe/Amsterdam' ) );
                                        echo esc_html( $dutch_time->format( 'd-m-Y H:i' ) );
                                        ?>
                                    </small>
                                </div>
                                <div>
                                    <button class="btn btn-secondary sync-doc-btn" data-doc-id="<?php echo esc_attr( $doc->document_id ); ?>">
                                        🔄 Sync
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const syncAllBtn = document.getElementById('sync-all-btn');
                    const syncAllLoading = document.getElementById('sync-all-loading');
                    const syncAllResult = document.getElementById('sync-all-result');

                    syncAllBtn.addEventListener('click', function() {
                        syncAllBtn.disabled = true;
                        syncAllLoading.style.display = 'inline';
                        syncAllResult.style.display = 'none';

                        fetch(window.location.href, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=sync_all'
                        })
                        .then(response => response.json())
                        .then(data => {
                            syncAllResult.className = 'result ' + (data.success ? 'success' : 'error');
                            syncAllResult.innerHTML = data.data.replace(/\n/g, '<br>');
                            syncAllResult.style.display = 'block';
                            if (data.success) {
                                setTimeout(() => location.reload(), 2000);
                            }
                        })
                        .catch(error => {
                            syncAllResult.className = 'result error';
                            syncAllResult.textContent = 'Netwerkfout: ' + error.message;
                            syncAllResult.style.display = 'block';
                        })
                        .finally(() => {
                            syncAllBtn.disabled = false;
                            syncAllLoading.style.display = 'none';
                        });
                    });

                    document.querySelectorAll('.sync-doc-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const docId = this.dataset.docId;
                            const originalText = this.textContent;

                            this.disabled = true;
                            this.textContent = 'Syncing...';

                            fetch(window.location.href, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'action=sync_single&document_id=' + encodeURIComponent(docId)
                            })
                            .then(response => response.json())
                            .then(data => {
                                this.textContent = data.success ? '✅ Gesynchroniseerd' : '❌ Fout';
                                setTimeout(() => {
                                    this.textContent = originalText;
                                    this.disabled = false;
                                }, 2000);
                            })
                            .catch(error => {
                                this.textContent = '❌ Netwerkfout';
                                setTimeout(() => {
                                    this.textContent = originalText;
                                    this.disabled = false;
                                }, 2000);
                            });
                        });
                    });
                });
            </script>
        </body>
        </html>
        <?php
    }
}
