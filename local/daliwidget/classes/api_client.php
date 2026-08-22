<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Dali API Client for Knowledge Base operations
 *
 * @package     local_daliwidget
 * @copyright   2024 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_daliwidget;

defined('MOODLE_INTERNAL') || die();

/**
 * API client for communicating with Dali Knowledge Base API.
 */
class api_client {

    /** @var int Default upload size limit in MB (aligned with backend validation). */
    private const DEFAULT_MAX_UPLOAD_MB = 20;

    /** @var string Base URL of Dali API */
    private string $baseUrl;

    /** @var string API key for authentication */
    private string $apiKey;

    /**
     * Constructor.
     *
     * @param string|null $baseUrl Base URL (defaults to plugin setting)
     * @param string|null $apiKey API key (defaults to plugin setting)
     */
    public function __construct(?string $baseUrl = null, ?string $apiKey = null) {
        $this->baseUrl = rtrim($baseUrl ?? get_config('local_daliwidget', 'baseurl'), '/');
        $this->apiKey = $apiKey ?? get_config('local_daliwidget', 'apikey');
    }

    /**
     * Make an HTTP request to the Dali API.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint (e.g., /api/v1/knowledge/sources)
     * @param array|null $data Request body data
     * @param array|null $files Files to upload
     * @return array Response data
     */
    private function request(string $method, string $endpoint, ?array $data = null, ?array $files = null): array {
        global $CFG;

        $url = $this->baseUrl . $endpoint;
        $debugmode = get_config('local_daliwidget', 'debug_mode');
        $debuglog = [];
        $logdata = $data;
        if (isset($logdata['application_password'])) {
            $logdata['application_password'] = '[redacted]';
        }

        // Log request if debug mode enabled
        if ($debugmode) {
            $debuglog[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'type' => 'request',
                'method' => $method,
                'url' => $url,
                'api_key_preview' => substr($this->apiKey, 0, 10) . '...',
                'data' => $logdata,
            ];

            // Log to Moodle debug log
            debugging('[DaliWidget] API Request: ' . $method . ' ' . $url, DEBUG_DEVELOPER);
            if ($logdata) {
                debugging('[DaliWidget] Request Data: ' . json_encode($logdata), DEBUG_DEVELOPER);
            }
        }

        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];

        // Send privileged admin header on WordPress mutation requests only.
        $isWpMutation = str_starts_with($endpoint, '/api/v1/wordpress/')
            && in_array($method, ['POST', 'PUT', 'DELETE'], true);
        if ($isWpMutation) {
            $headers[] = 'X-Moodle-Site-Admin: 1';
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);

            if ($files) {
                // Multipart form data for file uploads
                $postData = $this->build_multipart_fields($data ?? []);
                foreach ($files as $key => $file) {
                    $postData[$key] = new \CURLFile($file['tmp_name'], $file['type'], $file['name']);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            } else if ($data) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } else if ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data ?? []));
        } else if ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if ($data) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } else if ($method === 'GET' && $data) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log response if debug mode enabled
        if ($debugmode) {
            debugging('[DaliWidget] API Response - HTTP Code: ' . $httpCode, DEBUG_DEVELOPER);
            if ($error) {
                debugging('[DaliWidget] cURL Error: ' . $error, DEBUG_DEVELOPER);
            }
            if ($response) {
                $responsePreview = strlen($response) > 500 ? substr($response, 0, 500) . '...' : $response;
                debugging('[DaliWidget] Response Body: ' . $responsePreview, DEBUG_DEVELOPER);
            }
        }

        if ($error) {
            if ($debugmode) {
                debugging('[DaliWidget] Request FAILED: ' . $error, DEBUG_DEVELOPER);
            }
            return ['success' => false, 'error' => 'cURL error: ' . $error, '_debug' => $debuglog];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            if ($httpCode === 413) {
                $errorMsg = 'Upload rejected (HTTP 413): file too large for server limit. Recommended max ' .
                    $this->format_bytes($this->get_max_upload_bytes()) . '.';
                if ($debugmode) {
                    debugging('[DaliWidget] Request FAILED (413): ' . $errorMsg, DEBUG_DEVELOPER);
                }
                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'http_code' => $httpCode,
                    '_debug' => $debuglog,
                ];
            }

            $errorMsg = $decoded['error'] ?? $decoded['message'] ?? 'HTTP ' . $httpCode;
            if ($debugmode) {
                debugging('[DaliWidget] Request FAILED (' . $httpCode . '): ' . $errorMsg, DEBUG_DEVELOPER);
            }
            return [
                'success' => false,
                'error' => $errorMsg,
                'http_code' => $httpCode,
                '_debug' => $debuglog,
            ];
        }

        if ($debugmode) {
            debugging('[DaliWidget] Request SUCCESS', DEBUG_DEVELOPER);
        }

        $result = $decoded ?? ['success' => true, 'raw' => $response];
        $result['_debug'] = $debuglog;
        return $result;
    }

    public function getWordpressConnections(): array { return $this->request('GET', '/api/v1/wordpress/connections'); }
    public function createWordpressConnection(array $data): array { return $this->request('POST', '/api/v1/wordpress/connections', $data); }
    public function updateWordpressConnection(int $id, array $data): array { return $this->request('PUT', "/api/v1/wordpress/connections/{$id}", $data); }
    public function validateWordpressConnection(int $id): array { return $this->request('POST', "/api/v1/wordpress/connections/{$id}/validate", []); }
    public function deleteWordpressConnection(int $id, ?array $body = null): array { return $this->request('DELETE', "/api/v1/wordpress/connections/{$id}", $body); }
    public function getWordpressHeldRemovals(int $id): array {
        return $this->request('GET', "/api/v1/wordpress/connections/{$id}/held-removals");
    }

    public function reviewWordpressHeldRemovals(int $connectionid, int $holdid, string $decision): array {
        return $this->request('POST', "/api/v1/wordpress/connections/{$connectionid}/held-removals/{$holdid}", [
            'decision' => $decision, 'confirmed' => true,
        ]);
    }
    public function getWordpressRuns(int $id): array {
        return $this->request('GET', "/api/v1/wordpress/connections/{$id}/runs");
    }

    /**
     * Get the count of knowledge sources owned by a WordPress connection.
     *
     * @param int $id Connection ID.
     * @return array API response with 'owned_source_count' in data.
     */
    public function getWordpressOwnedSourceCount(int $id): array {
        return $this->request('GET', "/api/v1/wordpress/connections/{$id}/owned-sources");
    }

    /**
     * Preview sync changes for a WordPress connection.
     *
     * @param int $id Connection ID.
     * @return array API response with preview diff.
     */
    public function previewWordpressSync(int $id): array {
        return $this->request('POST', "/api/v1/wordpress/connections/{$id}/preview", []);
    }

    /**
     * Discover posts from a WordPress connection.
     *
     * @param int $id Connection ID.
     * @param array $filters Discovery filters.
     * @return array API response.
     */
    public function getWordpressPosts(int $id, array $filters = []): array {
        return $this->request('GET', "/api/v1/wordpress/connections/{$id}/posts", $filters);
    }

    /**
     * Persist or cancel a manual post selection.
     *
     * @param int $connectionid Connection ID.
     * @param int $postid WordPress post ID.
     * @param bool $selected Whether the post is manually selected.
     * @param string $locale Translation identity.
     * @param bool $confirmed Whether deselection was confirmed.
     * @return array API response.
     */
    public function selectWordpressPost(
        int $connectionid,
        int $postid,
        bool $selected,
        string $locale,
        bool $confirmed = false
    ): array {
        return $this->request(
            'PUT',
            "/api/v1/wordpress/connections/{$connectionid}/posts/{$postid}/selection",
            ['selected' => $selected, 'locale' => $locale, 'confirmed' => $confirmed]
        );
    }

    /**
     * Get all knowledge sources.
     *
     * @param int|null $courseId Filter by course ID
     * @return array
     */
    public function getSources(?int $courseId = null): array {
        $params = [];
        if ($courseId) {
            $params['course_id'] = $courseId;
        }
        return $this->request('GET', '/api/v1/knowledge/sources', $params);
    }

    /**
     * Add a text source.
     *
     * @param string $title Title of the source
     * @param string $content Text content
     * @param array $metadata Additional metadata
     * @return array
     */
    public function addTextSource(string $title, string $content, array $metadata = []): array {
        if (empty($metadata['transport'])) {
            $metadata['transport'] = 'inline_text';
        }
        return $this->request('POST', '/api/v1/knowledge/sources', [
            'type' => 'text',
            'title' => $title,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Add a URL source.
     *
     * @param string $url URL to scrape
     * @param string|null $title Optional title
     * @param array $metadata Additional metadata
     * @return array
     */
    public function addUrlSource(string $url, ?string $title = null, array $metadata = []): array {
        if (empty($metadata['transport'])) {
            $metadata['transport'] = 'url_fetch';
        }
        $ingestType = self::detectIngestTypeFromUrl($url);

        if ($ingestType === 'youtube') {
            return $this->addYoutubeSource($url, $title, $metadata);
        }

        if ($ingestType === 'video' || $ingestType === 'audio' || $ingestType === 'scorm') {
            return $this->addTypedUrlSource($ingestType, $url, $title, $metadata);
        }

        $data = [
            'url' => $url,
            'metadata' => $metadata,
        ];
        if ($title) {
            $data['title'] = $title;
        }
        return $this->request('POST', '/api/v1/knowledge/sources/url', $data);
    }

    /**
     * Add a typed URL source for new ingestion routes.
     *
     * @param string $type Source type (video|audio|scorm)
     * @param string $url Public source URL
     * @param string|null $title Optional title
     * @param array $metadata Additional metadata
     * @return array
     */
    public function addTypedUrlSource(string $type, string $url, ?string $title = null, array $metadata = []): array {
        if (empty($metadata['transport'])) {
            $metadata['transport'] = 'remote_url';
        }
        $data = [
            'type' => $type,
            'title' => $title ?: ucfirst($type) . ' Source',
            'metadata' => array_merge($metadata, ['url' => $url]),
        ];

        return $this->request('POST', '/api/v1/knowledge/sources', $data);
    }

    /**
     * Add a remote file source from a signed/public URL.
     *
     * @param string $type Source type (document|video|audio|scorm)
     * @param string $url Remote file URL
     * @param string|null $title Optional title
     * @param array $metadata Additional metadata
     * @return array
     */
    public function addRemoteFileSource(string $type, string $url, ?string $title = null, array $metadata = []): array {
        $allowed = ['document', 'video', 'audio', 'scorm'];
        if (!in_array($type, $allowed, true)) {
            return [
                'success' => false,
                'error' => 'Unsupported remote source type: ' . $type,
            ];
        }

        if (empty($metadata['transport'])) {
            $metadata['transport'] = 'signed_url';
        }

        $data = [
            'type' => $type,
            'title' => $title ?: ucfirst($type) . ' Source',
            'metadata' => array_merge($metadata, ['url' => $url]),
        ];

        return $this->request('POST', '/api/v1/knowledge/sources', $data);
    }

    /**
     * Add a YouTube video source.
     *
     * @param string $url YouTube URL
     * @param string|null $title Optional title
     * @param array $metadata Additional metadata
     * @return array
     */
    public function addYoutubeSource(string $url, ?string $title = null, array $metadata = []): array {
        if (empty($metadata['transport'])) {
            $metadata['transport'] = 'remote_url';
        }
        $data = [
            'url' => $url,
            'metadata' => $metadata,
        ];
        if ($title) {
            $data['title'] = $title;
        }
        return $this->request('POST', '/api/v1/knowledge/sources/youtube', $data);
    }

    /**
     * Upload a source file.
     *
     * @param array $file File array from $_FILES
     * @param string|null $title Optional title
     * @param array $metadata Additional metadata
     * @param string $sourceType Source type (document|video|audio|scorm)
     * @return array
     */
    public function uploadDocument(array $file, ?string $title = null, array $metadata = [], string $sourceType = 'document'): array {
        $maxbytes = $this->get_max_upload_bytes();
        $filesize = $this->get_file_size($file);

        if ($filesize > 0 && $filesize > $maxbytes) {
            return [
                'success' => false,
                'error' => 'File too large (' . $this->format_bytes($filesize) . '). Max allowed is ' .
                    $this->format_bytes($maxbytes) . '.',
                'http_code' => 413,
            ];
        }

        if (empty($metadata['transport'])) {
            $metadata['transport'] = 'binary_upload';
        }

        $data = [
            'metadata' => $metadata,
            'source_type' => $sourceType,
        ];
        if ($title) {
            $data['title'] = $title;
        }
        return $this->request('POST', '/api/v1/knowledge/sources/document', $data, ['file' => $file]);
    }

    /**
     * Convert nested arrays into multipart field notation.
     *
     * @param array $data
     * @param string $prefix
     * @return array
     */
    private function build_multipart_fields(array $data, string $prefix = ''): array {
        $fields = [];

        foreach ($data as $key => $value) {
            $fieldname = $prefix === '' ? (string)$key : $prefix . '[' . $key . ']';

            if (is_array($value)) {
                $fields += $this->build_multipart_fields($value, $fieldname);
                continue;
            }

            if (is_bool($value)) {
                $fields[$fieldname] = $value ? '1' : '0';
                continue;
            }

            if ($value === null) {
                $fields[$fieldname] = '';
                continue;
            }

            $fields[$fieldname] = (string)$value;
        }

        return $fields;
    }

    /**
     * Resolve maximum upload bytes from plugin config.
     *
     * @return int
     */
    private function get_max_upload_bytes(): int {
        $configuredmb = (int) (get_config('local_daliwidget', 'maxuploadmb') ?: self::DEFAULT_MAX_UPLOAD_MB);
        if ($configuredmb <= 0) {
            $configuredmb = self::DEFAULT_MAX_UPLOAD_MB;
        }

        return $configuredmb * 1024 * 1024;
    }

    /**
     * Get file size from upload payload.
     *
     * @param array $file
     * @return int
     */
    private function get_file_size(array $file): int {
        if (!empty($file['size']) && is_numeric($file['size'])) {
            return (int) $file['size'];
        }

        if (!empty($file['tmp_name']) && is_string($file['tmp_name']) && is_file($file['tmp_name'])) {
            $size = filesize($file['tmp_name']);
            if ($size !== false) {
                return (int) $size;
            }
        }

        return 0;
    }

    /**
     * Format byte count into human readable text.
     *
     * @param int $bytes
     * @return string
     */
    private function format_bytes(int $bytes): string {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Delete a knowledge source.
     *
     * @param int $sourceId Source ID
     * @return array
     */
    public function deleteSource(int $sourceId): array {
        return $this->request('DELETE', '/api/v1/knowledge/sources/' . $sourceId);
    }

    /**
     * Retry a knowledge source from the beginning.
     *
     * @param int $sourceId Source ID
     * @return array
     */
    public function retrySource(int $sourceId): array {
        return $this->request('POST', '/api/v1/knowledge/sources/' . $sourceId . '/retry');
    }

    /**
     * Build metadata for a Moodle course/activity context.
     *
     * @param \stdClass $course Course object
     * @param \cm_info|null $cm Course module (activity) info
     * @return array
     */
    public static function buildMoodleMetadata(\stdClass $course, ?\cm_info $cm = null): array {
        $metadata = [
            'course' => [
                'id' => $course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
                'visible' => !empty($course->visible),
            ],
        ];

        if ($cm) {
            $metadata['activity'] = [
                'id' => $cm->id,
                'modname' => $cm->modname,
                'name' => $cm->name,
            ];
        }

        return $metadata;
    }

    /**
     * Detect ingest type from URL.
     *
     * @param string $url
     * @return string|null
     */
    private static function detectIngestTypeFromUrl(string $url): ?string {
        if (preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/i', $url)) {
            return 'youtube';
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === '') {
            return null;
        }

        if (in_array($extension, ['mp4', 'mov', 'mkv', 'webm'], true)) {
            return 'video';
        }

        if (in_array($extension, ['mp3', 'wav', 'm4a', 'aac', 'flac', 'ogg'], true)) {
            return 'audio';
        }

        if (in_array($extension, ['scorm', 'zip'], true)) {
            return 'scorm';
        }

        return null;
    }
}
