<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ailessonplan\api;

/**
 * Dali/Mastra API client for AI Lesson Plan.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class mastra_client {
    /** @var int request timeout in seconds */
    private const TIMEOUT = 90;

    /** @var string|null */
    private $apikey;

    /** @var string */
    private $apibaseurl;

    public function __construct() {
        $this->apikey = get_config('local_ailessonplan', 'apikey') ?: get_config('local_daliwidget', 'apikey');
        $this->apibaseurl = get_config('local_ailessonplan', 'apibaseurl') ?: get_config('local_daliwidget', 'baseurl') ?: 'http://localhost:8000';
    }

    /**
     * Generate a lesson plan.
     *
     * @param array $payload
     * @return array
     */
    public function generate_lesson_plan(array $payload): array {
        if (empty($this->apikey)) {
            throw new \moodle_exception('noapikey', 'local_ailessonplan');
        }

        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader([
            'Content-Type: application/json',
            'X-API-KEY: ' . $this->apikey,
        ]);

        $url = rtrim($this->apibaseurl, '/') . '/api/moodle/lesson-plan';
        $response = $curl->post($url, json_encode($payload), [
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_SSL_VERIFYPEER' => false,
            'CURLOPT_SSL_VERIFYHOST' => 0,
        ]);
        $info = $curl->get_info();

        if ($curl->get_errno()) {
            throw new \moodle_exception('apierror', 'local_ailessonplan', '', 'Curl error code: ' . $curl->get_errno());
        }

        if (($info['http_code'] ?? 0) !== 200) {
            $errordata = json_decode($response, true);
            $message = 'HTTP ' . ($info['http_code'] ?? 'unknown');
            if (is_array($errordata)) {
                $error = $errordata['error'] ?? $errordata['message'] ?? null;
                $message .= ': ' . (is_string($error) ? $error : json_encode($error));
            } else if (!empty($response)) {
                $message .= ' - Response: ' . substr($response, 0, 500);
            }
            throw new \moodle_exception('apierror', 'local_ailessonplan', '', $message . ' (URL: ' . $url . ')');
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('invalidjson', 'local_ailessonplan');
        }

        if (!empty($decoded['success']) && isset($decoded['plan']) && is_array($decoded['plan'])) {
            return $decoded['plan'];
        }

        if (isset($decoded['title']) || isset($decoded['meetings'])) {
            return $decoded;
        }

        if (!empty($decoded['error'])) {
            $error = is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
            throw new \moodle_exception('apierror', 'local_ailessonplan', '', $error);
        }

        throw new \moodle_exception('invalidjson', 'local_ailessonplan');
    }
}
