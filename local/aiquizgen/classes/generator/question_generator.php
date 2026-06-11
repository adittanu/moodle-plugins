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
        global $USER, $DB;

        $question = new \stdClass();
        $question->category = $categoryid;
        $question->name = substr($qdata['questiontext'], 0, 50); // Question name from text
        $question->questiontext = $qdata['questiontext'];
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1.0;
        $question->penalty = 0.3333333;
        $question->qtype = $qdata['questiontype'];
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->version = 1;
        $question->hidden = 0;
        $question->idnumber = null;
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;

        switch ($qdata['questiontype']) {
            case 'multichoice':
                $question->single = 1;
                $question->shuffleanswers = 1;
                $question->answernumbering = 'abc';
                $question->correctfeedback = ['text' => 'Your answer is correct.', 'format' => FORMAT_HTML];
                $question->partiallycorrectfeedback = ['text' => 'Your answer is partially correct.', 'format' => FORMAT_HTML];
                $question->incorrectfeedback = ['text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML];
                break;
            case 'truefalse':
                // True/False questions need specific fields
                $question->correctfeedback = ['text' => 'Your answer is correct.', 'format' => FORMAT_HTML];
                $question->incorrectfeedback = ['text' => 'Your answer is incorrect.', 'format' => FORMAT_HTML];
                $question->feedbacktrue = ['text' => '', 'format' => FORMAT_HTML];
                $question->feedbackfalse = ['text' => '', 'format' => FORMAT_HTML];
                break;
            case 'shortanswer':
                $question->usecase = 0;
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
                
                // For essay, graderinfo contains the model answer (optional)
                $modelanswer = '';
                if (!empty($qdata['answers']) && isset($qdata['answers'][0]['text'])) {
                    $modelanswer = $qdata['answers'][0]['text'];
                }
                $question->graderinfo = ['text' => $modelanswer, 'format' => FORMAT_HTML];
                $question->responsetemplate = ['text' => '', 'format' => FORMAT_HTML];
                break;
        }

        // Essay questions don't use answer/fraction/feedback arrays like other types
        if ($qdata['questiontype'] !== 'essay') {
            $question->answer = [];
            $question->fraction = [];
            $question->feedback = [];

            foreach ($qdata['answers'] as $index => $answer) {
                // Handle answer format based on question type
                if ($qdata['questiontype'] === 'shortanswer') {
                    // Short Answer questions need answer as plain string
                    $question->answer[$index] = $answer['text'] ?? '';
                } else {
                    // Other question types use array format
                    $question->answer[$index] = [
                        'text' => $answer['text'],
                        'format' => FORMAT_HTML
                    ];
                }

                // Ensure fraction is not null - default to 0 if null
                $question->fraction[$index] = $answer['fraction'] ?? 0;
                $question->feedback[$index] = [
                    'text' => $answer['feedback'] ?? '',
                    'format' => FORMAT_HTML
                ];
            }
        } else {
            // For essay, clear answer arrays
            $question->answer = [];
            $question->fraction = [];
            $question->feedback = [];
        }

        // Special handling for True/False questions
        if ($qdata['questiontype'] === 'truefalse') {
            // Find which answer is correct (True or False)
            $correctanswer = null;
            foreach ($qdata['answers'] as $index => $answer) {
                if (($answer['fraction'] ?? 0) > 0) {
                    $correctanswer = strtolower(trim($answer['text']));
                    break;
                }
            }

            // Set correctanswer field for True/False questions
            if ($correctanswer === 'true') {
                $question->correctanswer = 1;
            } elseif ($correctanswer === 'false') {
                $question->correctanswer = 0;
            } else {
                // Default to True if not found
                $question->correctanswer = 1;
            }

            // Set feedbacktrue and feedbackfalse based on answers
            foreach ($qdata['answers'] as $index => $answer) {
                $answertext = strtolower(trim($answer['text']));
                if ($answertext === 'true') {
                    $question->feedbacktrue = ['text' => $answer['feedback'] ?? '', 'format' => FORMAT_HTML];
                } elseif ($answertext === 'false') {
                    $question->feedbackfalse = ['text' => $answer['feedback'] ?? '', 'format' => FORMAT_HTML];
                }
            }
        }

        return $question;
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
