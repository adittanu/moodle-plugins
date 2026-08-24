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
 * Mastra API client for AI Quiz Generator plugin.
 *
 * @package    local_aiquizgen
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiquizgen\api;

defined('MOODLE_INTERNAL') || die();

class mastra_client {
    private const TIMEOUT = 60;

    private $apikey;
    private $apibaseurl;

    public function __construct() {
        $this->apikey = get_config('local_aiquizgen', 'apikey');
        $this->apibaseurl = get_config('local_aiquizgen', 'apibaseurl') ?: 'http://localhost:8000';
    }

    public function generate_questions($topic, $count, $type, $difficulty, $language, $additionalinstructions = '', $answeroptioncount = null) {
        if (empty($this->apikey)) {
            throw new \moodle_exception('noapikey', 'local_aiquizgen');
        }

        $response = $this->call_api($topic, $count, $type, $difficulty, $language, $additionalinstructions, $answeroptioncount);

        return $this->parse_response($response);
    }

    private function call_api($topic, $count, $type, $difficulty, $language, $additionalinstructions, $answeroptioncount) {
        $data = [
            'topic' => $topic,
            'count' => $count,
            'type' => $type,
            'difficulty' => $difficulty,
            'language' => $language,
            'additionalinstructions' => $additionalinstructions
        ];

        if ($type === 'multichoice') {
            $data['answerOptionCount'] = $answeroptioncount;
        }

        $url = rtrim($this->apibaseurl, '/') . '/api/moodle/quiz';

        // Create curl with ignoresecurity flag to bypass Moodle's cURL security restrictions
        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader([
            'Content-Type: application/json',
            'X-API-KEY: ' . $this->apikey,
        ]);
        
        $options = [
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_SSL_VERIFYPEER' => false,
            'CURLOPT_SSL_VERIFYHOST' => 0,
        ];

        $response = $curl->post($url, json_encode($data), $options);
        $info = $curl->get_info();

        // In Moodle's curl class:
        // $response contains the output
        // $info contains header info including http_code
        // $curl->get_errno() returns error number
        
        if ($curl->get_errno()) {
            throw new \moodle_exception('apierror', 'local_aiquizgen', '', 'Curl error code: ' . $curl->get_errno());
        }

        if ($info['http_code'] !== 200) {
            $errormsg = 'HTTP ' . $info['http_code'];
            // Try to decode error from body
            $errordata = json_decode($response, true);
            if (isset($errordata['error'])) {
                $errormsg .= ': ' . (is_string($errordata['error']) ? $errordata['error'] : json_encode($errordata['error']));
            } else if (!empty($response)) {
                // Include raw response for debugging
                $errormsg .= ' - Response: ' . substr($response, 0, 500);
            }
            
            // Debug info
            $errormsg .= ' (URL: ' . $url . ')';
            
            if ($info['http_code'] === 401) {
                throw new \moodle_exception('invalidapikey', 'local_aiquizgen');
            }
            
            throw new \moodle_exception('apierror', 'local_aiquizgen', '', $errormsg);
        }

        return $response;
    }

    private function parse_response($response) {
        $questions = json_decode($response, true);

        if (!is_array($questions)) {
            throw new \moodle_exception('invalidjson', 'local_aiquizgen');
        }

        // Validate and sanitize questions
        $validated_questions = [];
        foreach ($questions as $qdata) {
            // Check for required fields based on existing openai_client logic
            if (empty($qdata['questiontext']) || empty($qdata['questiontype']) || empty($qdata['answers'])) {
                continue; // Skip invalid questions
            }

            // Sanitize answers to ensure fraction is never null
            $sanitized_answers = [];
            foreach ($qdata['answers'] as $answer) {
                $sanitized_answers[] = [
                    'text' => $answer['text'] ?? '',
                    'fraction' => is_numeric($answer['fraction']) ? (float)$answer['fraction'] : 0,
                    'feedback' => $answer['feedback'] ?? ''
                ];
            }

            $qdata['answers'] = $sanitized_answers;
            $validated_questions[] = $qdata;
        }

        return $validated_questions;
    }

    public function test_connection() {
        if (empty($this->apikey)) {
            return ['success' => false, 'message' => 'API key not configured'];
        }

        // For connection test, we can try a simple generation request with 1 question or just check if endpoint is reachable.
        // Or better, maybe the backend has a health check?
        // Let's assume we just try to generate 1 dummy question to verify e2e connectivity and auth.
        
        try {
            // Using a very simple request for connection test
            $this->call_api('connection test', 1, 'truefalse', 'easy', 'english', '', null);
            return ['success' => true, 'message' => 'Connection successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
