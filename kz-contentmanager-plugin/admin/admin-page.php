<?php
// Voorkom directe toegang
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** @var KZ_Updater $kz_cm_updater Wordt meegegeven door KZ_ContentManager::admin_page(). */

// Sla instellingen op als formulier is verzonden
if ( isset( $_POST['kz_save_settings'] ) ) {
    check_admin_referer( 'kz_settings_nonce' );

    update_option( 'kz_google_drive_folder_id', sanitize_text_field( wp_unslash( $_POST['google_drive_folder_id'] ) ) );
    update_option( 'kz_google_api_key', sanitize_text_field( wp_unslash( $_POST['google_api_key'] ) ) );
    update_option( 'kz_rate_limit_seconds', absint( $_POST['rate_limit_seconds'] ) );
    update_option( 'kz_cm_debug', isset( $_POST['kz_cm_debug'] ) ? 1 : 0 );
    if ( isset( $_POST['kz_github_token'] ) ) {
        update_option( 'kz_github_token', sanitize_text_field( wp_unslash( $_POST['kz_github_token'] ) ) );
    }

    echo '<div class="notice notice-success"><p>Instellingen opgeslagen!</p></div>';
}

if ( isset( $_POST['kz_check_updates'] ) ) {
    check_admin_referer( 'kz_settings_nonce' );
    $kz_cm_updater->clear_cache();
    $kz_cm_updater->get_latest_release( true );
    if ( function_exists( 'wp_update_plugins' ) ) {
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();
    }
    echo '<div class="notice notice-success"><p>Update-controle uitgevoerd.</p></div>';
}

$folder_id      = get_option( 'kz_google_drive_folder_id' );
$api_key        = get_option( 'kz_google_api_key' );
$rate_limit     = get_option( 'kz_rate_limit_seconds', 300 );
$last_sync      = get_option( 'kz_last_sync' );
$debug_enabled  = get_option( 'kz_cm_debug', false );
$github_token   = get_option( 'kz_github_token', '' );
$latest_release = $kz_cm_updater->get_latest_release();
$current_version = $kz_cm_updater->get_current_version();
?>

<div class="wrap">
    <h1>KZ-Kraonige Zwaone Contentmanager</h1>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="#documents-tab" class="nav-tab nav-tab-active" data-tab="documents-tab">Documenten</a>
        <a href="#sync-tab" class="nav-tab" data-tab="sync-tab">Synchronisatie</a>
        <a href="#settings-tab" class="nav-tab" data-tab="settings-tab">Instellingen</a>
        <a href="#updates-tab" class="nav-tab" data-tab="updates-tab">Updates</a>
        <a href="#help-tab" class="nav-tab" data-tab="help-tab">Help</a>
    </nav>

    <div class="kz-admin-container">
        <!-- Documenten Tab -->
        <div class="kz-tab-content" id="documents-tab">
            <h2>Documenten en Componenten</h2>

            <div class="kz-actions-bar" style="margin-bottom: 20px;">
                <button id="kz-copy-all-shortcodes" class="button button-secondary">
                    📋 Kopieer alle shortcodes
                </button>
            </div>

            <div id="kz-documents-list">
                <p>Laden...</p>
            </div>
        </div>

        <!-- Synchronisatie Tab -->
        <div class="kz-tab-content" id="sync-tab" style="display: none;">
            <h2>Synchronisatie</h2>

            <div class="kz-sync-status">
                <p><strong>Laatste synchronisatie:</strong>
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

                <button id="kz-sync-button" class="button button-primary">
                    <span class="sync-text">Documenten synchroniseren</span>
                    <span class="sync-loading" style="display: none;">Synchroniseren...</span>
                </button>

                <div id="kz-sync-result"></div>
            </div>
        </div>

        <!-- Instellingen Tab -->
        <div class="kz-tab-content" id="settings-tab" style="display: none;">
            <h2>Instellingen</h2>

            <form method="post" action="">
                <?php wp_nonce_field( 'kz_settings_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="google_drive_folder_id">Google Drive Folder ID</label>
                        </th>
                        <td>
                            <input type="text" id="google_drive_folder_id" name="google_drive_folder_id"
                                   value="<?php echo esc_attr( $folder_id ); ?>" class="regular-text" />
                            <p class="description">
                                De ID van de Google Drive map waar je documenten staan.
                                Je vindt deze in de URL van de map: https://drive.google.com/drive/folders/FOLDER_ID
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="google_api_key">Google API Key</label>
                        </th>
                        <td>
                            <input type="text" id="google_api_key" name="google_api_key"
                                   value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
                            <p class="description">
                                Je Google API key voor toegang tot Google Drive en Docs API.
                                <a href="https://console.developers.google.com/" target="_blank">Maak er een aan</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label>Webhook Sync URL</label>
                        </th>
                        <td>
                            <?php
                            $webhook_token = get_option( 'kz_webhook_token' );
                            $webhook_url   = home_url( '/?kz_webhook_sync=true&token=' . $webhook_token );
                            ?>
                            <input type="text" id="webhook_url" value="<?php echo esc_attr( $webhook_url ); ?>" class="large-text" readonly />
                            <button type="button" id="copy-webhook-url" class="button button-secondary" style="margin-left: 10px;">
                                📋 Kopieer URL
                            </button>
                            <p class="description">
                                Gebruik deze URL om documenten te synchroniseren zonder in te loggen.
                                <strong>Bewaar deze URL veilig!</strong> Rate limit: <?php echo esc_html( $rate_limit ); ?> seconden.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="rate_limit_seconds">Rate Limit (seconden)</label>
                        </th>
                        <td>
                            <input type="number" id="rate_limit_seconds" name="rate_limit_seconds"
                                   value="<?php echo esc_attr( $rate_limit ); ?>" class="small-text" min="0" max="3600" />
                            <p class="description">
                                Tijd in seconden tussen bulk sync acties. 0 = geen limiet.
                                <strong>Voor debugging kun je dit op 0 zetten.</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="kz_cm_debug">Debug-informatie in sync-resultaat</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="kz_cm_debug" name="kz_cm_debug" value="1" <?php checked( $debug_enabled ); ?> />
                                Toon technische debug-info bij synchronisatie (standaard uit)
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="kz_save_settings" class="button-primary" value="Instellingen opslaan" />
                </p>
            </form>
        </div>

        <!-- Updates Tab -->
        <div class="kz-tab-content" id="updates-tab" style="display: none;">
            <h2>Updates</h2>

            <table class="form-table">
                <tr>
                    <th scope="row">Huidige versie</th>
                    <td><?php echo esc_html( $current_version ); ?></td>
                </tr>
                <tr>
                    <th scope="row">Laatst gevonden release</th>
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
            </table>

            <form method="post" action="">
                <?php wp_nonce_field( 'kz_settings_nonce' ); ?>
                <p class="submit">
                    <button type="submit" name="kz_check_updates" class="button button-secondary">Nu controleren op updates</button>
                </p>
            </form>

            <h3>GitHub-token (optioneel)</h3>
            <p>Alleen nodig zolang de plugin-repository privé is. Wordt gedeeld met KZ Plugin.</p>
            <form method="post" action="">
                <?php wp_nonce_field( 'kz_settings_nonce' ); ?>
                <input type="text" name="kz_github_token" value="<?php echo esc_attr( $github_token ); ?>" class="regular-text" placeholder="ghp_..." />
                <p class="submit">
                    <input type="submit" name="kz_save_settings" class="button-primary" value="Token opslaan" />
                </p>
            </form>
        </div>

        <!-- Help Tab -->
        <div class="kz-tab-content" id="help-tab" style="display: none;">
            <h2>Help & Gebruik</h2>

            <h3>Shortcode Gebruik</h3>
            <p>Gebruik de volgende shortcode om content te tonen:</p>
            <code>[kz-content page="documentnaam" component="componentnaam"]</code>

            <h3>Document Structuur</h3>
            <p>Je Google Docs moeten de volgende structuur hebben:</p>
            <ul>
                <li>Gebruik <code>==KZCON=Titel=</code> om componenten te scheiden</li>
                <li>Het deel vóór de eerste marker wordt automatisch 'main'</li>
                <li>Formatting (H1, H2, H3, bold, italic) wordt automatisch behouden</li>
            </ul>

            <h3>Voorbeeld Document</h3>
            <pre>
# Hoofdtitel

Dit is de hoofdcontent van het document.

==KZCON=Header=
# Header Component
Dit is de header content.

==KZCON=Footer=
# Footer Component
Dit is de footer content.
            </pre>

            <h3>Google API Setup</h3>
            <ol>
                <li>Ga naar <a href="https://console.developers.google.com/" target="_blank">Google Cloud Console</a></li>
                <li>Maak een nieuw project aan</li>
                <li>Activeer de Google Drive API en Google Docs API</li>
                <li>Maak een API key aan</li>
                <li>Voeg de API key hierboven in</li>
                <li>Zorg dat je Google Drive map publiek toegankelijk is of gebruik een service account</li>
            </ol>
        </div>
    </div>
</div>

<style>
.kz-documents-grid {
    display: grid;
    gap: 20px;
    margin-top: 20px;
}

.kz-document-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.document-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.document-header h3 {
    margin: 0;
    font-size: 16px;
    color: #333;
}

.document-actions {
    display: flex;
    gap: 10px;
}

.components-list {
    display: grid;
    gap: 8px;
}

.kz-component-compact {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #f9f9f9;
    border-radius: 3px;
    border-left: 3px solid #0073aa;
}

.component-title {
    font-weight: 600;
    color: #333;
    min-width: 120px;
}

.shortcode-compact {
    font-family: monospace;
    font-size: 12px;
    background: #fff;
    padding: 4px 8px;
    border-radius: 3px;
    border: 1px solid #ddd;
    flex: 1;
    margin-left: 15px;
    cursor: pointer;
}

.shortcode-compact:hover {
    background: #f0f8ff;
}

.kz-actions-bar {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.copy-doc-shortcodes {
    white-space: nowrap;
}

.sync-single-doc {
    white-space: nowrap;
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}

.sync-single-doc:hover {
    background: #005a87;
    border-color: #005a87;
}

.sync-single-doc:disabled {
    background: #ccc;
    border-color: #ccc;
    color: #666;
}

.sync-result {
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: 3px;
    font-size: 12px;
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab functionaliteit
    $('.nav-tab-wrapper a').click(function() {
        var tabId = $(this).data('tab');

        $('.kz-tab-content').hide();
        $('.nav-tab-wrapper a').removeClass('nav-tab-active');

        $('#' + tabId).show();
        $(this).addClass('nav-tab-active');
    });

    // Sync functionaliteit
    $('#kz-sync-button').click(function() {
        var button = $(this);
        var syncText = button.find('.sync-text');
        var syncLoading = button.find('.sync-loading');
        var resultDiv = $('#kz-sync-result');

        button.prop('disabled', true);
        syncText.hide();
        syncLoading.show();
        resultDiv.removeClass('success error').html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'kz_sync_documents',
                nonce: '<?php echo esc_js( wp_create_nonce( 'kz_nonce' ) ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.addClass('success').html('<strong>Succes!</strong> ' + response.data);
                    loadDocuments();
                } else {
                    resultDiv.addClass('error').html('<strong>Fout!</strong> ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Er is een netwerkfout opgetreden.';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMsg = xhr.responseJSON.data;
                }
                resultDiv.addClass('error').html('<strong>Fout!</strong> ' + errorMsg);
            },
            complete: function() {
                button.prop('disabled', false);
                syncLoading.hide();
                syncText.show();
            }
        });
    });

    function loadDocuments() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'kz_get_documents',
                nonce: '<?php echo esc_js( wp_create_nonce( 'kz_nonce' ) ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayDocuments(response.data);
                } else {
                    $('#kz-documents-list').html('<p>Fout bij het laden van documenten.</p>');
                }
            },
            error: function() {
                $('#kz-documents-list').html('<p>Netwerkfout bij het laden van documenten.</p>');
            }
        });
    }

    function displayDocuments(documents) {
        if (documents.length === 0) {
            $('#kz-documents-list').html('<p>Geen documenten gevonden. Synchroniseer eerst je documenten.</p>');
            return;
        }

        var html = '<div class="kz-documents-grid">';
        var allShortcodes = [];

        documents.forEach(function(doc) {
            var components = JSON.parse(doc.components);
            var componentList = '';
            var docShortcodes = [];

            Object.keys(components).forEach(function(componentName) {
                var componentData = components[componentName];
                var displayTitle = componentData.title || componentName;
                var shortcode = '[kz-content page="' + doc.document_name + '" component="' + componentName + '"]';

                componentList += '<div class="kz-component-compact">' +
                    '<span class="component-title">' + displayTitle + '</span>' +
                    '<code class="shortcode-compact">' + shortcode + '</code>' +
                    '</div>';

                docShortcodes.push(shortcode);
                allShortcodes.push(shortcode);
            });

            html += '<div class="kz-document-card">' +
                '<div class="document-header">' +
                    '<h3>' + doc.document_name + '</h3>' +
                    '<div class="document-actions">' +
                        '<button class="button button-small sync-single-doc" data-doc-id="' + doc.document_id + '">' +
                            '🔄 Sync document' +
                        '</button>' +
                        '<button class="button button-small copy-doc-shortcodes" data-shortcodes="' + docShortcodes.join('\\n') + '">' +
                            '📋 Kopieer document shortcodes' +
                        '</button>' +
                    '</div>' +
                '</div>' +
                '<div class="components-list">' + componentList + '</div>' +
                '</div>';
        });

        html += '</div>';
        $('#kz-documents-list').html(html);

        window.kzAllShortcodes = allShortcodes.join('\n');
    }

    $('.nav-tab-wrapper a[data-tab="documents-tab"]').click(function() {
        loadDocuments();
    });

    $('#kz-copy-all-shortcodes').click(function() {
        if (window.kzAllShortcodes) {
            copyToClipboard(window.kzAllShortcodes);
            showCopyMessage('Alle shortcodes zijn gekopieerd!');
        } else {
            alert('Geen shortcodes gevonden. Laad eerst de documenten.');
        }
    });

    $(document).on('click', '.copy-doc-shortcodes', function() {
        var shortcodes = $(this).data('shortcodes');
        copyToClipboard(shortcodes);
        showCopyMessage('Document shortcodes zijn gekopieerd!');
    });

    $(document).on('click', '.shortcode-compact', function() {
        var shortcode = $(this).text();
        copyToClipboard(shortcode);
        showCopyMessage('Shortcode gekopieerd!');
    });

    $(document).on('click', '.sync-single-doc', function() {
        var docId = $(this).data('doc-id');
        var button = $(this);
        var resultDiv = $('<div class="sync-result"></div>');
        button.after(resultDiv);

        button.prop('disabled', true);
        resultDiv.html('Synchroniseren...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'kz_sync_single_document',
                document_id: docId,
                nonce: '<?php echo esc_js( wp_create_nonce( 'kz_nonce' ) ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<strong>Succes!</strong> ' + response.data);
                } else {
                    resultDiv.html('<strong>Fout!</strong> ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Er is een netwerkfout opgetreden.';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMsg = xhr.responseJSON.data;
                }
                resultDiv.html('<strong>Fout!</strong> ' + errorMsg);
            },
            complete: function() {
                button.prop('disabled', false);
                setTimeout(function() {
                    resultDiv.fadeOut(function() {
                        resultDiv.remove();
                    });
                }, 2000);
            }
        });
    });

    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text);
        } else {
            var textarea = $('<textarea>');
            $('body').append(textarea);
            textarea.val(text).select();
            document.execCommand('copy');
            textarea.remove();
        }
    }

    function showCopyMessage(message) {
        var notice = $('<div class="notice notice-success is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 300px;"><p>' + message + '</p></div>');
        $('body').append(notice);
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 2000);
    }

    $('#copy-webhook-url').click(function() {
        var webhookUrl = $('#webhook_url').val();
        copyToClipboard(webhookUrl);
        showCopyMessage('Webhook URL gekopieerd!');
    });
});
</script>
