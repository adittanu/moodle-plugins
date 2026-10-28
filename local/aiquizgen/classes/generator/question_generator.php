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
 * Question generator for AI Quiz Generator plugin.
 *
 * @package    local_aiquizgen
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiquizgen\generator;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');
require_once($CFG->dirroot . '/question/type/truefalse/questiontype.php');
require_once($CFG->dirroot . '/question/type/shortanswer/questiontype.php');
require_once($CFG->dirroot . '/question/type/essay/questiontype.php');
require_once($CFG->dirroot . '/question/engine/bank.php');

class question_generator {
    private const ESSAY_RESPONSE_FORMAT = 'editor';
    
    public function save_questions_to_bank($questions, $categoryid, $courseid) {
        global $DB, $USER;

        // Parse category ID to extract only the ID part (critical for PostgreSQL compatibility)
        if (is_string($categoryid) && strpos($categoryid, ',') !== false) {
            list($catid, $contextid) = explode(',', $categoryid);
            $categoryid = (int)$catid;
        }

        // Ensure categoryid is an integer (PostgreSQL is strict about types)
        $categoryid = (int)$categoryid;
        if ($categoryid <= 0) {
            throw new \moodle_exception('invalidcategoryid', 'local_aiquizgen', '', $categoryid);
        }

        $savedcount = 0;
        $errors = [];

        foreach ($questions as $qdata) {
            try {
                if (empty($qdata['questiontext']) || empty($qdata['questiontype'])) {
                    $errors[] = 'Skipped a question with an empty text or type.';
                    continue;
                }
                if ($qdata['questiontype'] !== 'essay' && empty($qdata['answers'])) {
                    $errors[] = 'Skipped a non-essay question without answers: ' .
                        \core_text::substr((string)$qdata['questiontext'], 0, 60);
                    continue;
                }

                $question = $this->prepare_question_data($qdata, $categoryid, $courseid);
                $questionid = $this->save_question($question);

                if ($questionid) {
                    $savedcount++;
                }
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        return [
            'success' => $savedcount > 0,
            'saved' => $savedcount,
            'errors' => $errors,
        ];
    }

    private function prepare_question_data($qdata, $categoryid, $courseid) {
        global $USER;

        $question = new \stdClass();
        $question->category = $categoryid;

        $questiontext = trim((string)($qdata['questiontext'] ?? ''));
        $plainname = trim(strip_tags($questiontext));
        $question->name = \core_text::substr($plainname !== '' ? $plainname : 'Question', 0, 50);
        $question->questiontext = $this->plain_text_to_html($questiontext);
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = $this->normalize_mark($qdata['defaultmark'] ?? 1);
        $question->penalty = 0.3333333;

        $allowedtypes = ['multichoice', 'truefalse', 'shortanswer', 'essay'];
        $qtype = (string)($qdata['questiontype'] ?? '');
        $question->qtype = in_array($qtype, $allowedtypes, true) ? $qtype : 'shortanswer';

        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->version = 1;
        $question->hidden = 0;
        $question->idnumber = null;
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;

        $answers = is_array($qdata['answers'] ?? null) ? $qdata['answers'] : [];

        switch ($question->qtype) {
            case 'multichoice':
                $question->single = 1;
                $question->shuffleanswers = 1;
                $question->answernumbering = 'abc';
                $question->correctfeedback = ['text' => 'Your answer is correct.', 'format' => FORMAT_HTML];
                $question->partiallycorrectfeedback = ['text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML];
                $question->incorrectfeedback = ['text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML];
                $answers = $this->normalize_multichoice_answers($answers);
                break;
            case 'truefalse':
                // True/False questions need specific fields
                $question->correctfeedback = ['text' => 'Your answer is correct.', 'format' => FORMAT_HTML];
                $question->incorrectfeedback = ['text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML];
                $question->feedbacktrue = ['text' => '', 'format' => FORMAT_HTML];
                $question->feedbackfalse = ['text' => '', 'format' => FORMAT_HTML];
                $answers = $this->normalize_truefalse_answers($answers);
                break;
            case 'shortanswer':
                $question->usecase = 0;
                // Every accepted answer is correct in Moodle's short answer qtype.
                foreach ($answers as $index => $answer) {
                    if (is_array($answer)) {
                        $answers[$index]['fraction'] = 1.0;
                    }
                }
                break;
            case 'essay':
                // Essay questions have specific fields
                $question->responseformat = self::ESSAY_RESPONSE_FORMAT;
                $question->responserequired = 1;
                $question->responsefieldlines = 15;
                $question->minwordlimit = 0;  // 0 means no limit
                $question->maxwordlimit = 0;  // 0 means no limit
                $question->attachments = 0;
                $question->attachmentsrequired = 0;
                $question->maxbytes = 0;
                $question->filetypeslist = '';

                // For essay, graderinfo contains the model answer and grading rubric.
                // Prefer the explicit field, but keep backwards compatibility with
                // responses that put the model answer in answers[0].text.
                $graderinfo = trim((string)($qdata['graderinfo'] ?? ''));
                if ($graderinfo === '' && !empty($answers[0]['text'])) {
                    $graderinfo = trim((string)$answers[0]['text']);
                }
                $question->graderinfo = [
                    'text' => $this->plain_text_to_html($graderinfo),
                    'format' => FORMAT_HTML,
                ];
                $question->responsetemplate = ['text' => '', 'format' => FORMAT_HTML];
                break;
        }

        // Essay questions don't use answer/fraction/feedback arrays like other types.
        if ($question->qtype !== 'essay') {
            $question->answer = [];
            $question->fraction = [];
            $question->feedback = [];

            $answerindex = 0;
            foreach ($answers as $answer) {
                if (!is_array($answer)) {
                    continue;
                }

                $answertext = trim((string)($answer['text'] ?? ''));
                if ($answertext === '') {
                    continue;
                }

                // Handle answer format based on question type
                if ($question->qtype === 'shortanswer') {
                    // Short Answer questions need answer as plain string
                    $question->answer[$answerindex] = $answertext;
                } else {
                    // Other question types use array format
                    $question->answer[$answerindex] = [
                        'text' => $this->plain_text_to_html($answertext),
                        'format' => FORMAT_HTML
                    ];
                }

                // Ensure fraction is not null - default to 0 if null
                $fraction = isset($answer['fraction']) && is_numeric($answer['fraction'])
                    ? (float)$answer['fraction'] : 0.0;
                $question->fraction[$answerindex] = $question->qtype === 'shortanswer' ? 1.0 : $fraction;
                $question->feedback[$answerindex] = [
                    'text' => $this->plain_text_to_html((string)($answer['feedback'] ?? '')),
                    'format' => FORMAT_HTML
                ];

                $answerindex++;
            }
        } else {
            // For essay, clear answer arrays
            $question->answer = [];
            $question->fraction = [];
            $question->feedback = [];
        }

        // Special handling for True/False questions
        if ($question->qtype === 'truefalse') {
            // Find which answer is correct (True or False)
            $correctanswer = null;
            foreach ($answers as $answer) {
                if (($answer['fraction'] ?? 0) > 0) {
                    $correctanswer = $this->truefalse_value((string)($answer['text'] ?? ''), true);
                    break;
                }
            }

            // Set correctanswer field for True/False questions
            if ($correctanswer === 'False') {
                $question->correctanswer = 0;
            } else {
                $question->correctanswer = 1;
            }

            // Set feedbacktrue and feedbackfalse based on answers
            foreach ($answers as $answer) {
                $value = $this->truefalse_value((string)($answer['text'] ?? ''), true);
                if ($value === 'True') {
                    $question->feedbacktrue = [
                        'text' => $this->plain_text_to_html((string)($answer['feedback'] ?? '')),
                        'format' => FORMAT_HTML,
                    ];
                } else if ($value === 'False') {
                    $question->feedbackfalse = [
                        'text' => $this->plain_text_to_html((string)($answer['feedback'] ?? '')),
                        'format' => FORMAT_HTML,
                    ];
                }
            }
        }

        return $question;
    }

    /**
     * Convert plain text to safe HTML, preserving line breaks. Existing HTML is kept.
     *
     * @param string $text
     * @return string
     */
    private function plain_text_to_html(string $text): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // Keep already-formatted HTML (e.g. rich content from a PDF source).
        if (preg_match('/<(?:p|br|ul|ol|li|strong|em|b|i|h[1-6])\b[^>]*>/i', $text)) {
            return $text;
        }

        return nl2br(s($text), false);
    }

    /**
     * Normalize a maximum mark to a safe positive float.
     *
     * @param mixed $value
     * @return float
     */
    private function normalize_mark($value): float {
        if (is_string($value)) {
            $value = trim($value);
            $hascomma = strpos($value, ',') !== false;
            $hasdot = strpos($value, '.') !== false;
            if ($hascomma && !$hasdot) {
                $value = str_replace(',', '.', $value);
            } else if ($hascomma && $hasdot) {
                $value = str_replace(',', '', $value);
            }

            $converted = unformat_float($value);
            if ($converted !== false && is_numeric($converted)) {
                $value = $converted;
            }
        }

        $mark = is_numeric($value) ? (float)$value : 1.0;
        if ($mark <= 0) {
            $mark = 1.0;
        }

        return round(min(1000.0, $mark), 2);
    }

    /**
     * Convert an answer text into True/False.
     *
     * @param string $text
     * @param bool $allowletters
     * @return string|null
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
     * Build exactly two canonical True/False answers.
     *
     * @param array $answers
     * @return array
     */
    private function normalize_truefalse_answers(array $answers): array {
        $correct = null;
        $feedback = ['True' => '', 'False' => ''];

        foreach ($answers as $answer) {
            if (!is_array($answer)) {
                continue;
            }
            $value = $this->truefalse_value((string)($answer['text'] ?? ''), true);
            if ($value === null) {
                continue;
            }
            if (($answer['fraction'] ?? 0) > 0) {
                $correct = $value;
            }
            if (!empty($answer['feedback'])) {
                $feedback[$value] = (string)$answer['feedback'];
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
    private function normalize_multichoice_answers(array $answers): array {
        $normalized = [];
        foreach ($answers as $answer) {
            if (is_array($answer) && trim((string)($answer['text'] ?? '')) !== '') {
                $normalized[] = $answer;
            }
        }

        $correctindex = null;
        foreach ($normalized as $index => $answer) {
            if (($answer['fraction'] ?? 0) > 0) {
                $correctindex = $index;
                break;
            }
        }
        if ($correctindex === null && !empty($normalized)) {
            $correctindex = 0;
        }

        foreach ($normalized as $index => $answer) {
            $normalized[$index]['fraction'] = $index === $correctindex ? 1.0 : 0.0;
        }

        return $normalized;
    }

    private function save_question($question) {
        global $DB;

        // Get question type handler
        $qtype = \question_bank::get_qtype($question->qtype);
        
        // Get category and context
        $category = $DB->get_record('question_categories', ['id' => $question->category], '*', MUST_EXIST);
        $context = \context::instance_by_id($category->contextid);

        // Create form data object (second parameter for save_question)
        $formdata = new \stdClass();
        $formdata->category = $question->category . ',' . $category->contextid; // Format: "id,contextid"
        $formdata->name = $question->name;
        $formdata->questiontext = [
            'text' => $question->questiontext,
            'format' => $question->questiontextformat,
        ];
        $formdata->questiontextformat = $question->questiontextformat;
        $formdata->generalfeedback = [
            'text' => $question->generalfeedback,
            'format' => $question->generalfeedbackformat,
        ];
        $formdata->generalfeedbackformat = $question->generalfeedbackformat;
        $formdata->defaultmark = $question->defaultmark;
        $formdata->penalty = $question->penalty;
        
        // Copy type-specific fields (skip for essay - handled separately)
        if ($question->qtype !== 'essay') {
            foreach ($question as $key => $value) {
                if (!isset($formdata->$key)) {
                    $formdata->$key = $value;
                }
            }
        }

        // Special handling for True/False questions
        if ($question->qtype === 'truefalse') {
            // Ensure True/False specific fields are properly set in formdata
            if (isset($question->correctanswer)) {
                $formdata->correctanswer = $question->correctanswer;
            }
            if (isset($question->feedbacktrue) && is_array($question->feedbacktrue)) {
                $formdata->feedbacktrue = $question->feedbacktrue;
            } else {
                $formdata->feedbacktrue = ['text' => '', 'format' => FORMAT_HTML];
            }
            if (isset($question->feedbackfalse) && is_array($question->feedbackfalse)) {
                $formdata->feedbackfalse = $question->feedbackfalse;
            } else {
                $formdata->feedbackfalse = ['text' => '', 'format' => FORMAT_HTML];
            }
        }

        // Special handling for Short Answer questions
        if ($question->qtype === 'shortanswer') {
            // Ensure usecase is properly set
            $formdata->usecase = $question->usecase ?? 0;

            // Ensure answer field is properly formatted as string
            if (isset($question->answer) && is_array($question->answer)) {
                foreach ($question->answer as $index => $answer) {
                    if (is_array($answer) && isset($answer['text'])) {
                        // Convert array format to string for Short Answer
                        $question->answer[$index] = $answer['text'];
                    }
                }
                $formdata->answer = $question->answer;
            }
        }

        // Special handling for Essay questions
        if ($question->qtype === 'essay') {
            // Set essay-specific fields
            $formdata->responseformat = $question->responseformat ?? self::ESSAY_RESPONSE_FORMAT;
            $formdata->responserequired = $question->responserequired ?? 1;
            $formdata->responsefieldlines = $question->responsefieldlines ?? 15;
            $formdata->attachments = $question->attachments ?? 0;
            $formdata->attachmentsrequired = $question->attachmentsrequired ?? 0;
            $formdata->maxbytes = $question->maxbytes ?? 0;
            $formdata->filetypeslist = $question->filetypeslist ?? '';
            $formdata->responsetemplate = $question->responsetemplate ?? ['text' => '', 'format' => FORMAT_HTML];
            $formdata->graderinfo = $question->graderinfo ?? ['text' => '', 'format' => FORMAT_HTML];
            $formdata->minwordlimit = $question->minwordlimit ?? 0;
            $formdata->maxwordlimit = $question->maxwordlimit ?? 0;
            
            // Essay doesn't use answer/fraction/feedback arrays
            $formdata->answer = [];
            $formdata->fraction = [];
            $formdata->feedback = [];
        }
        
        // Create empty question object (first parameter for save_question)
        $questionobj = new \stdClass();
        $questionobj->qtype = $question->qtype;
        
        // Save question using question type (expects 2 parameters: question object and form data)
        $savedquestion = $qtype->save_question($questionobj, $formdata);

        return $savedquestion->id ?? false;
    }

    public function validate_question_data($qdata) {
        $errors = [];

        if (empty($qdata['questiontext'])) {
            $errors[] = 'Question text is empty';
        }

        if (empty($qdata['questiontype'])) {
            $errors[] = 'Question type is not specified';
        }

        if (!in_array($qdata['questiontype'], ['multichoice', 'truefalse', 'shortanswer', 'essay'])) {
            $errors[] = 'Invalid question type: ' . $qdata['questiontype'];
        }

        // Essay questions don't require answers/correct answer like other types
        if ($qdata['questiontype'] !== 'essay') {
            if (empty($qdata['answers']) || !is_array($qdata['answers'])) {
                $errors[] = 'No answers provided';
            } else {
                $hascorrect = false;
                foreach ($qdata['answers'] as $answer) {
                    if (isset($answer['fraction']) && $answer['fraction'] > 0) {
                        $hascorrect = true;
                        break;
                    }
                }
                if (!$hascorrect) {
                    $errors[] = 'No correct answer specified';
                }
            }
        }

        return $errors;
    }

    public function log_generation($userid, $courseid, $categoryid, $topic, $count, $type, $success) {
        global $DB;

        if (!get_config('local_aiquizgen', 'enablelog')) {
            return;
        }

        // Extract category ID from "id,contextid" format if needed
        if (is_string($categoryid) && strpos($categoryid, ',') !== false) {
            list($catid, $contextid) = explode(',', $categoryid);
            $categoryid = (int)$catid;
        }

        $log = new \stdClass();
        $log->userid = $userid;
        $log->courseid = $courseid;
        $log->categoryid = (int)$categoryid;
        $log->topic = $topic;
        $log->questioncount = $count;
        $log->questiontype = $type;
        $log->success = $success ? 1 : 0;
        $log->timecreated = time();

        $DB->insert_record('local_aiquizgen_log', $log);
    }
}
