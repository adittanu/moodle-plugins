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
    // Generating up to 20 questions can take longer than one minute.
    private const TIMEOUT = 180;
    private const EXTRACT_TIMEOUT = 120;

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

    /**
     * Extract existing questions (with answer keys) from a document chunk.
     *
     * @param string $documenttext Chunk of document text.
     * @param int $part Chunk number (1-based).
     * @param int $totalparts Total number of chunks.
     * @param string $extractformat Either "asis" or "multichoice".
     * @param string $additionalinstructions Optional teacher instructions.
     * @return array Normalized questions.
     */
    public function extract_questions($documenttext, $part, $totalparts, $extractformat = 'asis', $additionalinstructions = '') {
        if (empty($this->apikey)) {
            throw new \moodle_exception('noapikey', 'local_aiquizgen');
        }

        $data = [
            'topic' => $documenttext,
            'part' => (int)$part,
            'totalParts' => (int)$totalparts,
            'extractFormat' => $extractformat === 'multichoice' ? 'multichoice' : 'asis',
            'additionalinstructions' => (string)$additionalinstructions,
        ];

        $response = $this->post_api('/api/moodle/quiz/extract', $data, self::EXTRACT_TIMEOUT);

        return $this->parse_response($response, true);
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

        return $this->post_api('/api/moodle/quiz', $data, self::TIMEOUT);
    }

    /**
     * POST JSON to the Dali API.
     *
     * @param string $path API path beginning with a slash.
     * @param array $data Payload.
     * @param int $timeout Request timeout in seconds.
     * @return string Raw response body.
     */
    private function post_api($path, array $data, $timeout) {
        $url = rtrim($this->apibaseurl, '/') . $path;

        // Create curl with ignoresecurity flag to bypass Moodle's cURL security restrictions
        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader([
            'Content-Type: application/json',
            'X-API-KEY: ' . $this->apikey,
        ]);

        $options = [
            'CURLOPT_TIMEOUT' => $timeout,
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

        if (($info['http_code'] ?? 0) !== 200) {
            $errormsg = 'HTTP ' . ($info['http_code'] ?? 'unknown');
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

            if (($info['http_code'] ?? 0) === 401) {
                throw new \moodle_exception('invalidapikey', 'local_aiquizgen');
            }

            throw new \moodle_exception('apierror', 'local_aiquizgen', '', $errormsg);
        }

        return $response;
    }

    /**
     * Decode, validate, and normalize a question response.
     *
     * @param string $response Raw JSON response.
     * @param bool $extractonly Restrict the result to multiple choice and essay.
     * @return array
     */
    private function parse_response($response, bool $extractonly = false) {
        $questions = json_decode($response, true);

        if (!is_array($questions)) {
            throw new \moodle_exception('invalidjson', 'local_aiquizgen');
        }

        // Some models wrap the array in an object.
        if (isset($questions['questions']) && is_array($questions['questions'])) {
            $questions = $questions['questions'];
        } else if (isset($questions['questiontext'])) {
            $questions = [$questions];
        }

        $validated_questions = [];

        foreach ($questions as $qdata) {
            if (!is_array($qdata)) {
                continue;
            }

            $questiontext = trim((string)($qdata['questiontext'] ?? ''));
            if ($questiontext === '') {
                continue;
            }

            // Remove a leading question number if the model left one in place.
            $questiontext = preg_replace('/^\s*(?:soal|question|pertanyaan)\s+\d+\s*[.):\-]\s*/iu', '', $questiontext);
            $questiontext = trim((string)$questiontext);
            if ($questiontext === '') {
                continue;
            }

            $answers = $this->normalize_answers($qdata['answers'] ?? []);
            $graderinfo = $this->normalize_graderinfo($qdata['graderinfo'] ?? '');
            $questiontype = $this->normalize_question_type($qdata['questiontype'] ?? '');

            if ($questiontype === '') {
                $questiontype = $this->infer_question_type($answers, $graderinfo);
            }

            // Imports only produce multiple choice and essay questions.
            if ($extractonly) {
                if ($questiontype === 'truefalse') {
                    $questiontype = 'multichoice';
                } else if ($questiontype === 'shortanswer') {
                    $questiontype = 'essay';
                }
            }

            if ($questiontype === 'essay') {
                if ($graderinfo === '' && !empty($answers[0]['text'])) {
                    $graderinfo = $answers[0]['text'];
                }
                // Essay questions do not use answer options.
                $answers = [];
            } else if ($questiontype === 'truefalse') {
                $answers = $this->normalize_truefalse_answers($answers);
            } else if ($questiontype === 'shortanswer') {
                foreach ($answers as $index => $answer) {
                    $answers[$index]['fraction'] = 1.0;
                }
            } else if ($questiontype === 'multichoice') {
                if (count($answers) < 2) {
                    // The model claimed multiple choice but did not provide options.
                    $questiontype = $extractonly
                        ? 'essay'
                        : $this->infer_question_type($answers, $graderinfo);
                    if ($questiontype === 'multichoice') {
                        $questiontype = 'shortanswer';
                    }
                    if ($questiontype === 'essay' && $graderinfo === '' && !empty($answers[0]['text'])) {
                        $graderinfo = $answers[0]['text'];
                        $answers = [];
                    }
                } else {
                    $answers = $this->normalize_multichoice_answers($answers);
                }
            }

            if ($questiontype !== 'essay' && empty($answers)) {
                continue;
            }

            $validated_questions[] = [
                'questiontext' => $questiontext,
                'questiontype' => $questiontype,
                'defaultmark' => $this->normalize_mark($qdata['defaultmark'] ?? 1),
                'graderinfo' => $questiontype === 'essay' ? $graderinfo : '',
                'answers' => $answers,
            ];
        }

        return $validated_questions;
    }

    /**
     * Normalize an answer list.
     *
     * @param mixed $answers
     * @return array
     */
    private function normalize_answers($answers) {
        if (!is_array($answers)) {
            return [];
        }

        $normalized = [];
        foreach ($answers as $answer) {
            if (!is_array($answer)) {
                continue;
            }
            $text = trim((string)($answer['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $normalized[] = [
                'text' => $text,
                'fraction' => is_numeric($answer['fraction'] ?? null) ? (float)$answer['fraction'] : 0.0,
                'feedback' => trim((string)($answer['feedback'] ?? '')),
            ];
        }
        return $normalized;
    }

    /**
     * Normalize the grader information field (string or {text, format}).
     *
     * @param mixed $graderinfo
     * @return string
     */
    private function normalize_graderinfo($graderinfo) {
        if (is_array($graderinfo)) {
            $graderinfo = $graderinfo['text'] ?? '';
        }
        return trim((string)$graderinfo);
    }

    /**
     * Normalize a question type name.
     *
     * @param mixed $type
     * @return string Empty string when unknown.
     */
    private function normalize_question_type($type) {
        $key = strtolower(trim((string)$type));
        $key = preg_replace('/[^a-z]/', '', $key);

        $map = [
            'multichoice' => 'multichoice',
            'multiplechoice' => 'multichoice',
            'mcq' => 'multichoice',
            'pg' => 'multichoice',
            'pilihanganda' => 'multichoice',
            'truefalse' => 'truefalse',
            'trueorfalse' => 'truefalse',
            'tf' => 'truefalse',
            'benarsalah' => 'truefalse',
            'shortanswer' => 'shortanswer',
            'shortanswers' => 'shortanswer',
            'isian' => 'shortanswer',
            'isiansingkat' => 'shortanswer',
            'essay' => 'essay',
            'esai' => 'essay',
            'uraian' => 'essay',
            'essayquestion' => 'essay',
        ];

        return $map[$key] ?? '';
    }

    /**
     * Infer a question type from the extracted answer key.
     *
     * @param array $answers
     * @param string $graderinfo
     * @return string
     */
    private function infer_question_type(array $answers, string $graderinfo) {
        if (count($answers) >= 3) {
            return 'multichoice';
        }

        if (count($answers) === 2) {
            $first = $this->truefalse_value($answers[0]['text']);
            $second = $this->truefalse_value($answers[1]['text']);
            if ($first !== null && $second !== null && $first !== $second) {
                return 'truefalse';
            }
            return 'multichoice';
        }

        if (count($answers) === 1) {
            if ($this->truefalse_value($answers[0]['text']) !== null) {
                return 'truefalse';
            }
            $text = $answers[0]['text'];
            if ($graderinfo !== '' || strlen($text) > 120 || substr_count($text, "\n") > 0) {
                return 'essay';
            }
            return 'shortanswer';
        }

        return 'essay';
    }

    /**
     * Convert an answer text to a true/false value.
     *
     * @param string $text
     * @param bool $allowletters Allow single-letter shortcuts (T/F, B/S).
     * @return string|null "True", "False", or null.
     */
    private function truefalse_value(string $text, bool $allowletters = false) {
        $value = strtolower(trim($text));
        $value = trim($value, " \t\n\r\0\x0B.!:;");

        $truewords = ['true', 'benar', 'betul', 'ya', 'yes', '1'];
        $falsewords = ['false', 'salah', 'tidak', 'no', '0'];

        if ($allowletters) {
            $truewords[] = 't';
            $truewords[] = 'b';
            $falsewords[] = 'f';
            $falsewords[] = 's';
        }

        if (in_array($value, $truewords, true)) {
            return 'True';
        }
        if (in_array($value, $falsewords, true)) {
            return 'False';
        }
        return null;
    }

    /**
     * Normalize true/false answers to exactly two canonical options.
     *
     * @param array $answers
     * @return array
     */
    private function normalize_truefalse_answers(array $answers) {
        $correct = null;
        $feedback = ['True' => '', 'False' => ''];

        foreach ($answers as $answer) {
            $value = $this->truefalse_value($answer['text'], true);
            if ($value === null) {
                continue;
            }
            if (($answer['fraction'] ?? 0) > 0) {
                $correct = $value;
            }
            if (!empty($answer['feedback'])) {
                $feedback[$value] = $answer['feedback'];
            }
        }

        if ($correct === null) {
            $correct = 'True';
        }

        return [
            ['text' => 'True', 'fraction' => $correct === 'True' ? 1.0 : 0.0, 'feedback' => $feedback['True']],
            ['text' => 'False', 'fraction' => $correct === 'False' ? 1.0 : 0.0, 'feedback' => $feedback['False']],
        ];
    }

    /**
     * Ensure exactly one correct option for multiple choice.
     *
     * @param array $answers
     * @return array
     */
    private function normalize_multichoice_answers(array $answers) {
        $correctindex = null;
        foreach ($answers as $index => $answer) {
            if (($answer['fraction'] ?? 0) > 0) {
                $correctindex = $index;
                break;
            }
        }
        if ($correctindex === null) {
            $correctindex = 0;
        }

        foreach ($answers as $index => $answer) {
            $answers[$index]['fraction'] = $index === $correctindex ? 1.0 : 0.0;
        }

        return $answers;
    }

    /**
     * Normalize a maximum mark value.
     *
     * @param mixed $value
     * @return float
     */
    private function normalize_mark($value) {
        if (is_string($value)) {
            $value = trim($value);
            $hascomma = strpos($value, ',') !== false;
            $hasdot = strpos($value, '.') !== false;
            if ($hascomma && !$hasdot) {
                $value = str_replace(',', '.', $value);
            } else if ($hascomma && $hasdot) {
                $value = str_replace(',', '', $value);
            }

            if (function_exists('unformat_float')) {
                $converted = unformat_float($value);
                if ($converted !== false && is_numeric($converted)) {
                    $value = $converted;
                }
            }
        }

        $mark = is_numeric($value) ? (float)$value : 1.0;
        if ($mark <= 0) {
            $mark = 1.0;
        }

        return round(min(1000.0, $mark), 2);
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
