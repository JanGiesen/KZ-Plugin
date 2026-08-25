<?php
/**
 * Google Drive/Docs synchronisatie-handler.
 * Overgenomen uit KZ-Kraonige Zwaone Contentmanager 2.0.0, gedrag ongewijzigd
 * (op de debug-output na, die nu achter een instelbare debug-modus zit).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KZ_CM_Google_Drive_Handler {

    private $api_key;
    private $folder_id;
    private $debug_log = array();

    public function __construct() {
        $this->api_key   = get_option( 'kz_google_api_key' );
        $this->folder_id = get_option( 'kz_google_drive_folder_id' );
    }

    private function is_debug() {
        return (bool) get_option( 'kz_cm_debug', false );
    }

    public function sync_documents() {
        if ( empty( $this->api_key ) || empty( $this->folder_id ) ) {
            return array(
                'success' => false,
                'message' => 'Google API key of folder ID niet geconfigureerd',
            );
        }

        try {
            $connection_check = $this->check_connection();
            if ( ! $connection_check['success'] ) {
                return $connection_check;
            }

            $documents = $this->get_documents_from_drive();

            $this->save_documents( $documents );
            update_option( 'kz_last_sync', current_time( 'mysql' ) );

            $message = count( $documents ) . ' documenten gesynchroniseerd';
            $message .= $this->build_debug_suffix( $documents[0]['content'] ?? '' );

            return array( 'success' => true, 'message' => $message );
        } catch ( Exception $e ) {
            return array( 'success' => false, 'message' => 'Fout: ' . $e->getMessage() );
        }
    }

    public function sync_single_document( $document_id ) {
        $document_id = sanitize_text_field( (string) $document_id );

        if ( empty( $this->api_key ) || empty( $this->folder_id ) ) {
            return array(
                'success' => false,
                'message' => 'Google API key of folder ID niet geconfigureerd',
            );
        }

        try {
            $connection_check = $this->check_connection();
            if ( ! $connection_check['success'] ) {
                return $connection_check;
            }

            $document = $this->get_document_from_drive( $document_id );

            $this->save_document( $document );
            update_option( 'kz_last_sync', current_time( 'mysql' ) );

            $message = 'Document gesynchroniseerd';
            $message .= $this->build_debug_suffix( $document['content'] ?? '' );

            return array( 'success' => true, 'message' => $message );
        } catch ( Exception $e ) {
            return array( 'success' => false, 'message' => 'Fout: ' . $e->getMessage() );
        }
    }

    private function build_debug_suffix( $sample_content ) {
        if ( ! $this->is_debug() ) {
            return '';
        }
        $suffix = '';
        if ( ! empty( $this->debug_log ) ) {
            $suffix .= "\n\nDEBUG - CSS Processing:\n" . implode( "\n", $this->debug_log );
        }
        if ( ! empty( $sample_content ) ) {
            $suffix .= "\n\nDEBUG - Final HTML:\n" . substr( $sample_content, 0, 200 ) . '...';
        }
        return $suffix;
    }

    private function check_connection() {
        $test_url      = "https://www.googleapis.com/drive/v3/files/{$this->folder_id}?key={$this->api_key}";
        $test_response = wp_remote_get( $test_url );

        if ( is_wp_error( $test_response ) ) {
            return array(
                'success' => false,
                'message' => 'Kan geen verbinding maken met Google Drive API: ' . $test_response->get_error_message(),
            );
        }

        $test_code = wp_remote_retrieve_response_code( $test_response );
        if ( 200 !== $test_code ) {
            $test_body  = wp_remote_retrieve_body( $test_response );
            $test_data  = json_decode( $test_body, true );
            $error_msg  = isset( $test_data['error']['message'] ) ? $test_data['error']['message'] : 'HTTP ' . $test_code;
            return array(
                'success' => false,
                'message' => 'API Fout: ' . $error_msg . ' - Controleer of de map publiek toegankelijk is',
            );
        }

        return array( 'success' => true );
    }

    private function get_documents_from_drive() {
        $url = "https://www.googleapis.com/drive/v3/files?q='{$this->folder_id}'+in+parents+and+mimeType='application/vnd.google-apps.document'&key={$this->api_key}";

        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Kan geen verbinding maken met Google Drive API' );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['files'] ) ) {
            throw new Exception( 'Geen documenten gevonden in de opgegeven map' );
        }

        $documents = array();

        foreach ( $data['files'] as $file ) {
            $content    = $this->get_document_content( $file['id'] );
            $components = $this->parse_components( $content );

            $documents[] = array(
                'id'         => $file['id'],
                'name'       => $file['name'],
                'content'    => $content,
                'components' => $components,
                'modified'   => $file['modifiedTime'],
            );
        }

        return $documents;
    }

    private function get_document_from_drive( $document_id ) {
        $url = "https://www.googleapis.com/drive/v3/files/{$document_id}?key={$this->api_key}";

        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Kan geen verbinding maken met Google Drive API' );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['name'] ) ) {
            throw new Exception( 'Document niet gevonden' );
        }

        $content    = $this->get_document_content( $document_id );
        $components = $this->parse_components( $content );

        return array(
            'id'         => $document_id,
            'name'       => $data['name'],
            'content'    => $content,
            'components' => $components,
            'modified'   => $data['modifiedTime'],
        );
    }

    private function get_document_content( $document_id ) {
        $export_url = "https://docs.google.com/document/d/{$document_id}/export?format=html&key={$this->api_key}";

        $response = wp_remote_get( $export_url );

        if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
            $content = wp_remote_retrieve_body( $response );
            if ( ! empty( $content ) ) {
                return $this->clean_google_docs_html( $content );
            }
        }

        $export_url_txt = "https://docs.google.com/document/d/{$document_id}/export?format=txt&key={$this->api_key}";

        $response = wp_remote_get( $export_url_txt );

        if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
            $content = wp_remote_retrieve_body( $response );
            if ( ! empty( $content ) ) {
                return '<p>' . str_replace( "\n\n", "</p>\n<p>", nl2br( esc_html( $content ) ) ) . '</p>';
            }
        }

        $url = "https://docs.googleapis.com/v1/documents/{$document_id}?key={$this->api_key}";

        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Kan document inhoud niet ophalen' );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['body']['content'] ) ) {
            if ( isset( $data['error'] ) ) {
                throw new Exception( 'Google Docs API Error: ' . $data['error']['message'] . ' - Maak documenten publiek toegankelijk' );
            }
            throw new Exception( 'Document heeft geen inhoud - maak documenten publiek toegankelijk' );
        }

        return $this->convert_to_html( $data );
    }

    private function convert_to_html( $document_data ) {
        $html    = '';
        $content = $document_data['body']['content'];

        foreach ( $content as $element ) {
            if ( isset( $element['paragraph'] ) ) {
                $html .= $this->process_paragraph( $element['paragraph'] );
            }
        }

        return $html;
    }

    private function process_paragraph( $paragraph ) {
        $html     = '';
        $elements = $paragraph['elements'];

        foreach ( $elements as $element ) {
            if ( isset( $element['textRun'] ) ) {
                $text        = $element['textRun']['content'];
                $formatting  = $element['textRun']['textStyle'];

                if ( isset( $formatting['bold'] ) && $formatting['bold'] ) {
                    $text = '<strong>' . $text . '</strong>';
                }
                if ( isset( $formatting['italic'] ) && $formatting['italic'] ) {
                    $text = '<em>' . $text . '</em>';
                }

                $html .= $text;
            }
        }

        if ( isset( $paragraph['paragraphStyle']['namedStyleType'] ) ) {
            $style = $paragraph['paragraphStyle']['namedStyleType'];

            switch ( $style ) {
                case 'HEADING_1':
                    return '<h1>' . $html . '</h1>';
                case 'HEADING_2':
                    return '<h2>' . $html . '</h2>';
                case 'HEADING_3':
                    return '<h3>' . $html . '</h3>';
                case 'HEADING_4':
                    return '<h4>' . $html . '</h4>';
                case 'HEADING_5':
                    return '<h5>' . $html . '</h5>';
                case 'HEADING_6':
                    return '<h6>' . $html . '</h6>';
                default:
                    return '<p>' . $html . '</p>';
            }
        }

        return '<p>' . $html . '</p>';
    }

    private function parse_components( $html_content ) {
        $components = array();

        $parts = preg_split( '/==KZCON=([^=]+)=/', $html_content, -1, PREG_SPLIT_DELIM_CAPTURE );

        if ( count( $parts ) === 1 ) {
            $components['main'] = array(
                'title'   => 'Main',
                'content' => trim( $parts[0] ),
            );
        } else {
            $components['main'] = array(
                'title'   => 'Main',
                'content' => trim( $parts[0] ),
            );

            for ( $i = 1; $i < count( $parts ); $i += 2 ) {
                if ( isset( $parts[ $i ] ) && isset( $parts[ $i + 1 ] ) ) {
                    $component_title   = trim( $parts[ $i ] );
                    $component_content = trim( $parts[ $i + 1 ] );
                    $component_key     = sanitize_title( $component_title );

                    $components[ $component_key ] = array(
                        'title'   => $component_title,
                        'content' => $component_content,
                    );
                }
            }
        }

        return $components;
    }

    private function save_documents( $documents ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kz_documents';

        foreach ( $documents as $document ) {
            $wpdb->replace(
                $table_name,
                array(
                    'document_id'   => $document['id'],
                    'document_name' => $document['name'],
                    'last_modified' => $document['modified'],
                    'content'       => $document['content'],
                    'components'    => wp_json_encode( $document['components'] ),
                ),
                array( '%s', '%s', '%s', '%s', '%s' )
            );
        }
    }

    private function save_document( $document ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kz_documents';

        $wpdb->replace(
            $table_name,
            array(
                'document_id'   => $document['id'],
                'document_name' => $document['name'],
                'last_modified' => $document['modified'],
                'content'       => $document['content'],
                'components'    => wp_json_encode( $document['components'] ),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
    }

    private function clean_google_docs_html( $html ) {
        $this->debug_log = array( 'Starting cleanup...' );

        $html = $this->remove_hidden_content( $html );

        $bold_classes   = array();
        $italic_classes = array();

        if ( preg_match( '/<style[^>]*>(.*?)<\/style>/s', $html, $matches ) ) {
            $css = $matches[1];
            if ( preg_match_all( '/\.c(\d+)\{[^}]*font-weight\s*:\s*(?:700|bold)[^}]*\}/i', $css, $bold_matches ) ) {
                foreach ( $bold_matches[1] as $num ) {
                    $bold_classes[] = 'c' . $num;
                }
            }
            if ( preg_match_all( '/\.c(\d+)\{[^}]*font-style\s*:\s*italic[^}]*\}/i', $css, $italic_matches ) ) {
                foreach ( $italic_matches[1] as $num ) {
                    $italic_classes[] = 'c' . $num;
                }
            }
        }

        if ( empty( $bold_classes ) ) {
            $bold_classes       = array( 'c6', 'c1', 'c2', 'c3', 'c4', 'c5', 'c7', 'c8', 'c9', 'c10' );
            $this->debug_log[]  = 'Using common bold classes: ' . implode( ', ', $bold_classes );
        } else {
            $this->debug_log[] = 'Found bold classes: ' . implode( ', ', $bold_classes );
        }

        $bold_count = 0;
        foreach ( $bold_classes as $class ) {
            $pattern = '/<span[^>]*class="[^"]*\b' . preg_quote( $class, '/' ) . '\b[^"]*"[^>]*>(.*?)<\/span>/s';
            $html    = preg_replace_callback(
                $pattern,
                function ( $matches ) use ( &$bold_count ) {
                    $bold_count++;
                    return '<strong>' . $matches[1] . '</strong>';
                },
                $html
            );
        }
        $this->debug_log[] = 'Bold conversions applied: ' . $bold_count;

        $italic_count = 0;
        foreach ( $italic_classes as $class ) {
            $pattern = '/<span[^>]*class="[^"]*\b' . preg_quote( $class, '/' ) . '\b[^"]*"[^>]*>(.*?)<\/span>/s';
            $html    = preg_replace_callback(
                $pattern,
                function ( $matches ) use ( &$italic_count ) {
                    $italic_count++;
                    return '<em>' . $matches[1] . '</em>';
                },
                $html
            );
        }
        $this->debug_log[] = 'Italic conversions applied: ' . $italic_count;

        $html = preg_replace( '/<style[^>]*>.*?<\/style>/s', '', $html );
        $html = preg_replace( '/<script[^>]*>.*?<\/script>/s', '', $html );

        $html = preg_replace( '/\s(?:class|id)="[^"]*"/i', '', $html );
        $html = preg_replace( '/\sstyle="[^"]*(?:margin|padding|border)[^"]*"/i', '', $html );

        $html = preg_replace( '/<table[^>]*>/i', '<table>', $html );
        $html = preg_replace( '/<tr[^>]*>/i', '<tr>', $html );
        $html = preg_replace( '/<td[^>]*>/i', '<td>', $html );
        $html = preg_replace( '/<th[^>]*>/i', '<th>', $html );

        $html = preg_replace( '/<p[^>]*>/i', '<p>', $html );
        $html = preg_replace( '/<h([1-6])[^>]*>/i', '<h$1>', $html );

        $html = preg_replace( '/<p>\s*<\/p>/', '', $html );
        $html = preg_replace( '/<span[^>]*>\s*<\/span>/', '', $html );

        $html = preg_replace( '/\s+/', ' ', $html );
        $html = trim( $html );

        return $html;
    }

    private function remove_hidden_content( $html ) {
        $pattern  = '/------\s*\{\{HIDDEN\s+START\s*------.*?------\s*HIDDEN\s+END\s*\}\}\s*------/s';
        $html     = preg_replace( $pattern, '', $html );

        $pattern2 = '/------\s*\{\{\s*HIDDEN\s+START\s*------.*?------\s*HIDDEN\s+END\s*\}\}\s*------/s';
        $html     = preg_replace( $pattern2, '', $html );

        return $html;
    }
}
