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
 * OpenAI API client for AI Quiz Generator plugin.
 *
 * @package    local_aiquizgen
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiquizgen\api;

defined('MOODLE_INTERNAL') || die();

class openai_client {
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const TIMEOUT = 60;

    private $apikey;
    private $model;
    private $temperature;
    private $maxtokens;

    public function __construct() {
        $this->apikey = get_config('local_aiquizgen', 'apikey');
        $this->model = get_config('local_aiquizgen', 'model') ?: 'gpt-3.5-turbo';
        $this->temperature = (float) (get_config('local_aiquizgen', 'temperature') ?: 0.7);
        $this->maxtokens = (int) (get_config('local_aiquizgen', 'maxtokens') ?: 2000);
    }

    public function generate_questions($topic, $count, $type, $difficulty, $language, $additionalinstructions = '') {
        if (empty($this->apikey)) {
            throw new \moodle_exception('noapikey', 'local_aiquizgen');
        }

        $prompt = $this->build_prompt($topic, $count, $type, $difficulty, $language, $additionalinstructions);
        $response = $this->call_api($prompt);

        return $this->parse_response($response);
    }

    private function build_prompt($topic, $count, $type, $difficulty, $language, $additionalinstructions) {
        $languagemap = [
            'english' => 'English',
            'indonesian' => 'Indonesian (Bahasa Indonesia)',
            'thai' => 'Thai (ภาษาไทย)',
            'vietnamese' => 'Vietnamese (Tiếng Việt)',
            'malay' => 'Malay (Bahasa Melayu)',
            'filipino' => 'Filipino (Tagalog)',
            'burmese' => 'Burmese (မြန်မာစာ)',
            'khmer' => 'Khmer (ភាសាខ្មែរ)',
            'lao' => 'Lao (ພາສາລາວ)',
            'tetum' => 'Tetum (Tetun)',
        ];
        $langtext = $languagemap[$language] ?? 'English';
        
        $typeinstructions = '';
        switch ($type) {
            case 'multichoice':
                $typeinstructions = 'Each question should have 4 answer options with exactly one correct answer.';
                break;
            case 'truefalse':
                $typeinstructions = 'Each question should be answerable with True or False.';
                break;
            case 'shortanswer':
                $typeinstructions = 'Each question should have a short text answer (1-3 words).';
                break;
            case 'essay':
                $typeinstructions = 'Each question should require a detailed written response (essay). Provide a model answer and grading criteria.';
                break;
        }

        $prompt = "You are an expert educator creating {$difficulty} level {$type} quiz questions.

Topic: {$topic}
Number of questions: {$count}
Question type: {$type}
Difficulty: {$difficulty}
Language: {$langtext}
{$typeinstructions}

";

        // Add additional instructions with more emphasis
        if (!empty($additionalinstructions)) {
            $prompt .= "IMPORTANT - Additional Requirements: {$additionalinstructions}\n\n";
            $prompt .= "CRITICAL: You MUST follow the additional requirements above strictly in ALL questions you generate.\n\n";
        }

        $prompt .= "Generate {$count} high-quality quiz questions in valid JSON format. Use this exact structure:

";

        if ($type === 'multichoice') {
            $prompt .= '[
  {
    "questiontext": "Question text here?",
    "questiontype": "multichoice",
    "answers": [
      {"text": "Answer option 1", "fraction": 1.0, "feedback": "Correct! Brief explanation."},
      {"text": "Answer option 2", "fraction": 0, "feedback": "Incorrect. Brief explanation."},
      {"text": "Answer option 3", "fraction": 0, "feedback": "Incorrect. Brief explanation."},
      {"text": "Answer option 4", "fraction": 0, "feedback": "Incorrect. Brief explanation."}
    ]
  }
]';
        } else if ($type === 'truefalse') {
            $prompt .= '[
  {
    "questiontext": "Question text here?",
    "questiontype": "truefalse",
    "answers": [
      {"text": "True", "fraction": 1.0, "feedback": "Correct! Brief explanation."},
      {"text": "False", "fraction": 0, "feedback": "Incorrect. Brief explanation."}
    ]
  }
]';
        } else if ($type === 'essay') {
            $prompt .= '[
  {
    "questiontext": "Essay question text here? Provide detailed instructions for the response.",
    "questiontype": "essay",
    "answers": [
      {"text": "Model answer with key points to include", "fraction": 1.0, "feedback": "Excellent response covering all key points."}
    ]
  }
]';
        } else {
            $prompt .= '[
  {
    "questiontext": "Question text here?",
    "questiontype": "shortanswer",
    "answers": [
      {"text": "correct answer", "fraction": 1.0, "feedback": "Correct!"}
    ]
  }
]';
        }

        $prompt .= "\n\nIMPORTANT: Return ONLY valid JSON, no additional text or explanation. Use 'fraction': 1.0 for correct answers and 'fraction': 0 for incorrect answers.";

        // Add final reminder about additional instructions if provided
        if (!empty($additionalinstructions)) {
            $prompt .= "\n\nFINAL REMINDER: Make sure ALL questions follow the additional requirements specified above!";
        }

        return $prompt;
    }

    private function call_api($prompt) {
        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert educator who creates high-quality quiz questions. Always follow ALL instructions provided by the user, especially additional requirements. Always respond with valid JSON only.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxtokens,
        ];

        $ch = curl_init(self::API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apikey,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \moodle_exception('apierror', 'local_aiquizgen', '', $error);
        }

        if ($httpcode !== 200) {
            $errordata = json_decode($response, true);
            $errormsg = $errordata['error']['message'] ?? 'Unknown error';
            
            if ($httpcode === 401) {
                throw new \moodle_exception('invalidapikey', 'local_aiquizgen');
            }
            
            throw new \moodle_exception('apierror', 'local_aiquizgen', '', $errormsg);
        }

        return $response;
    }

    private function parse_response($response) {
        $data = json_decode($response, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \moodle_exception('invalidjson', 'local_aiquizgen');
        }

        $content = $data['choices'][0]['message']['content'];
        
        $content = preg_replace('/^```json\s*/s', '', $content);
        $content = preg_replace('/\s*```$/s', '', $content);
        $content = trim($content);

        $questions = json_decode($content, true);

        if (!is_array($questions)) {
            throw new \moodle_exception('invalidjson', 'local_aiquizgen');
        }

        // Validate and sanitize questions
        $validated_questions = [];
        foreach ($questions as $qdata) {
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

        try {
            $data = [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => 'Say "OK"']
                ],
                'max_tokens' => 10,
            ];

            $ch = curl_init(self::API_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apikey,
            ]);

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpcode === 200) {
                return ['success' => true, 'message' => 'Connection successful'];
            } else {
                return ['success' => false, 'message' => 'HTTP ' . $httpcode];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
