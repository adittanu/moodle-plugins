<?php
// This file is part of Moodle - http://moodle.org/

namespace local_aigrading\external;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use local_aigrading\dali_service;

/**
 * Preview and apply reviewed AI grades for one quiz question.
 *
 * @package local_aigrading
 */
class review_grade_question extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'slot' => new external_value(PARAM_INT, 'Question slot number'),
            'questionid' => new external_value(PARAM_INT, 'Question ID'),
            'action' => new external_value(PARAM_ALPHA, 'preview or apply'),
            'afterattemptid' => new external_value(PARAM_INT, 'Last reviewed quiz attempt ID', VALUE_DEFAULT, 0),
            'afterslot' => new external_value(PARAM_INT, 'Last reviewed question slot', VALUE_DEFAULT, 0),
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID to grade', VALUE_DEFAULT, 0),
            'grade' => new external_value(PARAM_FLOAT, 'Reviewed grade', VALUE_DEFAULT, 0),
            'feedback' => new external_value(PARAM_RAW, 'Reviewed feedback', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $cmid, int $slot, int $questionid, string $action,
            int $afterattemptid = 0, int $afterslot = 0, int $attemptid = 0, float $grade = 0, string $feedback = ''): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), compact(
            'cmid', 'slot', 'questionid', 'action', 'afterattemptid', 'afterslot', 'attemptid', 'grade', 'feedback'
        ));
        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/aigrading:useaigrading', $context);
        require_capability('mod/quiz:grade', $context);

        if (!in_array($params['action'], ['preview', 'apply'], true)) {
            throw new \invalid_parameter_exception('Invalid review action');
        }

        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        $sql = "SELECT qa.id AS attemptid, qa.uniqueid AS qubaid, qa.userid,
                       qatt.questionid, qatt.slot
                  FROM {quiz_attempts} qa
                  JOIN {question_attempts} qatt
                    ON qatt.questionusageid = qa.uniqueid
                 WHERE qa.quiz = :quizid
                   AND qa.state = 'finished'
                   AND qa.id = :attemptid
                   AND (:requestedslot = 0 OR qatt.slot = :requestedslot2)
                   AND EXISTS (
                       SELECT 1 FROM {question_attempt_steps} qas
                        WHERE qas.questionattemptid = qatt.id AND qas.state = 'needsgrading'
                   )";
        $attempt = null;

        if ($params['action'] === 'apply') {
            $attempt = $DB->get_record_sql($sql, [
                'quizid' => $quiz->id, 'requestedslot' => $params['slot'],
                'requestedslot2' => $params['slot'], 'attemptid' => $params['attemptid'],
            ]);
            if (!$attempt) {
                throw new \moodle_exception('Attempt is no longer available for grading.');
            }
            $question = $DB->get_record('question', ['id' => $attempt->questionid], '*', MUST_EXIST);
            $maxgrade = (float)$question->defaultmark;
            if ($params['grade'] < 0 || $params['grade'] > $maxgrade) {
                throw new \invalid_parameter_exception("Grade must be between 0 and {$maxgrade}");
            }
            $quba = \question_engine::load_questions_usage_by_activity($attempt->qubaid);
            $quba->process_action((int)$attempt->slot, [
                '-mark' => $params['grade'], '-maxmark' => $maxgrade,
                '-comment' => $params['feedback'], '-commentformat' => FORMAT_PLAIN,
            ]);
            \question_engine::save_questions_usage_by_activity($quba);
            return self::response(['applied' => true, 'attemptid' => $params['attemptid'],
                'slot' => (int)$attempt->slot, 'maxgrade' => $maxgrade]);
        }

        if ($params['slot'] === 0) {
            $previewsql = str_replace('AND qa.id = :attemptid',
                'AND (qatt.slot > :afterslot OR (qatt.slot = :afterslot2 AND qa.id > :attemptid))', $sql) .
                ' ORDER BY qatt.slot ASC, qa.id ASC';
        } else {
            $previewsql = str_replace('AND qa.id = :attemptid', 'AND qa.id > :attemptid', $sql) . ' ORDER BY qa.id ASC';
        }
        $queryparams = [
            'quizid' => $quiz->id, 'attemptid' => $params['afterattemptid'],
            'requestedslot' => $params['slot'], 'requestedslot2' => $params['slot'],
        ];
        if ($params['slot'] === 0) {
            $queryparams['afterslot'] = $params['afterslot'];
            $queryparams['afterslot2'] = $params['afterslot'];
        }
        $attempt = $DB->get_record_sql($previewsql, $queryparams, IGNORE_MULTIPLE);
        if (!$attempt) {
            return self::response(['done' => true]);
        }
        $question = $DB->get_record('question', ['id' => $attempt->questionid], '*', MUST_EXIST);
        $maxgrade = (float)$question->defaultmark;
        $quba = \question_engine::load_questions_usage_by_activity($attempt->qubaid);
        $qa = $quba->get_question_attempt((int)$attempt->slot);
        $answer = $qa->get_last_qt_data()['answer'] ?? '';
        if (trim(strip_tags($answer)) === '') {
            return self::response(['attemptid' => (int)$attempt->attemptid, 'slot' => (int)$attempt->slot,
                'skipped' => true, 'maxgrade' => $maxgrade]);
        }

        $result = (new dali_service())->suggest_grade(strip_tags($question->questiontext), $answer, $maxgrade);
        if (empty($result['success'])) {
            throw new \moodle_exception($result['error'] ?? 'AI grading failed');
        }
        $user = \core_user::get_user($attempt->userid, '*', MUST_EXIST);
        return self::response([
            'attemptid' => (int)$attempt->attemptid,
            'slot' => (int)$attempt->slot,
            'student' => fullname($user),
            'answer' => trim(strip_tags($answer)),
            'grade' => (float)$result['grade'],
            'feedback' => $result['feedback'] ?? '',
            'explanation' => $result['explanation'] ?? '',
            'confidence' => $result['confidence'] ?? 'medium',
            'maxgrade' => $maxgrade,
        ]);
    }

    private static function response(array $values): array {
        return $values + [
            'success' => true, 'done' => false, 'applied' => false, 'skipped' => false,
            'attemptid' => 0, 'slot' => 0, 'student' => '', 'answer' => '', 'grade' => 0,
            'maxgrade' => 0, 'feedback' => '', 'explanation' => '', 'confidence' => '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Request succeeded'),
            'done' => new external_value(PARAM_BOOL, 'No attempts remain'),
            'applied' => new external_value(PARAM_BOOL, 'Grade was applied'),
            'skipped' => new external_value(PARAM_BOOL, 'Empty answer was skipped'),
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
            'slot' => new external_value(PARAM_INT, 'Question slot number'),
            'student' => new external_value(PARAM_TEXT, 'Student name'),
            'answer' => new external_value(PARAM_RAW, 'Student answer'),
            'grade' => new external_value(PARAM_FLOAT, 'Suggested grade'),
            'maxgrade' => new external_value(PARAM_FLOAT, 'Maximum grade'),
            'feedback' => new external_value(PARAM_RAW, 'Feedback'),
            'explanation' => new external_value(PARAM_RAW, 'Teacher explanation'),
            'confidence' => new external_value(PARAM_ALPHA, 'Confidence level'),
        ]);
    }
}
