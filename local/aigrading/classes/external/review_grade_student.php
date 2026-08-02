<?php
// This file is part of Moodle - http://moodle.org/

namespace local_aigrading\external;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use local_aigrading\dali_service;

/** Review all ungraded essay questions for one quiz attempt. */
class review_grade_student extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'action' => new external_value(PARAM_ALPHA, 'preview or apply'),
            'afterattemptid' => new external_value(PARAM_INT, 'Last reviewed attempt ID', VALUE_DEFAULT, 0),
            'attemptid' => new external_value(PARAM_INT, 'Attempt ID', VALUE_DEFAULT, 0),
            'grades' => new external_multiple_structure(new external_single_structure([
                'slot' => new external_value(PARAM_INT, 'Question slot'),
                'grade' => new external_value(PARAM_FLOAT, 'Reviewed grade'),
                'feedback' => new external_value(PARAM_RAW, 'Reviewed feedback'),
            ]), 'Reviewed grades', VALUE_DEFAULT, []),
        ]);
    }

    public static function execute(int $cmid, string $action, int $afterattemptid = 0,
            int $attemptid = 0, array $grades = []): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'cmid', 'action', 'afterattemptid', 'attemptid', 'grades'
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
        $targetid = $params['action'] === 'apply' ? $params['attemptid'] : $params['afterattemptid'];
        $operator = $params['action'] === 'apply' ? '=' : '>';
        $attempt = $DB->get_record_sql("SELECT qa.id, qa.uniqueid, qa.userid
                  FROM {quiz_attempts} qa
                 WHERE qa.quiz = :quizid AND qa.state = 'finished' AND qa.id {$operator} :attemptid
                   AND EXISTS (
                       SELECT 1 FROM {question_attempts} qatt
                       JOIN {question_attempt_steps} qas ON qas.questionattemptid = qatt.id
                        WHERE qatt.questionusageid = qa.uniqueid AND qas.state = 'needsgrading'
                   ) ORDER BY qa.id ASC", ['quizid' => $quiz->id, 'attemptid' => $targetid], IGNORE_MULTIPLE);
        if (!$attempt) {
            if ($params['action'] === 'apply') {
                throw new \moodle_exception('Attempt is no longer available for grading.');
            }
            return ['success' => true, 'done' => true, 'attemptid' => 0, 'student' => '', 'questions' => []];
        }

        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        if ($params['action'] === 'apply') {
            foreach ($params['grades'] as $reviewed) {
                $qa = $quba->get_question_attempt($reviewed['slot']);
                $maxgrade = (float)$qa->get_max_mark();
                if ($reviewed['grade'] < 0 || $reviewed['grade'] > $maxgrade || $qa->get_state() !== \question_state::$needsgrading) {
                    throw new \invalid_parameter_exception('Invalid or stale grade for slot ' . $reviewed['slot']);
                }
                $quba->process_action($reviewed['slot'], [
                    '-mark' => $reviewed['grade'], '-maxmark' => $maxgrade,
                    '-comment' => $reviewed['feedback'], '-commentformat' => FORMAT_PLAIN,
                ]);
            }
            \question_engine::save_questions_usage_by_activity($quba);
            return ['success' => true, 'done' => false, 'attemptid' => (int)$attempt->id,
                'student' => '', 'questions' => []];
        }

        $questions = [];
        $service = new dali_service();
        foreach ($quba->get_slots() as $slot) {
            $qa = $quba->get_question_attempt($slot);
            if ($qa->get_state() !== \question_state::$needsgrading) {
                continue;
            }
            $answer = $qa->get_last_qt_data()['answer'] ?? '';
            if (trim(strip_tags($answer)) === '') {
                continue;
            }
            $question = $qa->get_question();
            $maxgrade = (float)$qa->get_max_mark();
            $result = $service->suggest_grade(strip_tags($question->questiontext), $answer, $maxgrade);
            if (empty($result['success'])) {
                throw new \moodle_exception($result['error'] ?? 'AI grading failed');
            }
            $questions[] = [
                'slot' => (int)$slot, 'question' => strip_tags($question->questiontext),
                'answer' => trim(strip_tags($answer)), 'grade' => (float)$result['grade'],
                'maxgrade' => $maxgrade, 'feedback' => $result['feedback'] ?? '',
                'explanation' => $result['explanation'] ?? '',
                'confidence' => $result['confidence'] ?? 'medium',
            ];
        }
        $user = \core_user::get_user($attempt->userid, '*', MUST_EXIST);
        return ['success' => true, 'done' => false, 'attemptid' => (int)$attempt->id,
            'student' => fullname($user), 'questions' => $questions];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Request succeeded'),
            'done' => new external_value(PARAM_BOOL, 'No students remain'),
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
            'student' => new external_value(PARAM_TEXT, 'Student name'),
            'questions' => new external_multiple_structure(new external_single_structure([
                'slot' => new external_value(PARAM_INT, 'Question slot'),
                'question' => new external_value(PARAM_RAW, 'Question text'),
                'answer' => new external_value(PARAM_RAW, 'Student answer'),
                'grade' => new external_value(PARAM_FLOAT, 'Suggested grade'),
                'maxgrade' => new external_value(PARAM_FLOAT, 'Maximum grade'),
                'feedback' => new external_value(PARAM_RAW, 'Feedback'),
                'explanation' => new external_value(PARAM_RAW, 'Teacher explanation'),
                'confidence' => new external_value(PARAM_ALPHA, 'Confidence level'),
            ])),
        ]);
    }
}
