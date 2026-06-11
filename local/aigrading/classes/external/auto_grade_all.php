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

namespace local_aigrading\external;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use local_aigrading\dali_service;

/**
 * External function to auto-grade all ungraded essays for ALL questions in a quiz.
 * Now supports batch processing and skip already graded.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_grade_all extends external_api
{

    /**
     * Returns the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'batchsize' => new external_value(PARAM_INT, 'Total batch size across all questions', false, 20),
            'startfrom' => new external_value(PARAM_INT, 'Last processed question attempt ID', false, 0),
        ]);
    }

    /**
     * Execute the function - grade all ungraded attempts for all essay questions in the quiz.
     * Now processes in batches across all questions.
     *
     * @param int $cmid Course module ID
     * @param int $batchsize Total batch size
     * @param int $startfrom Last processed question attempt ID
     * @return array
     */
    public static function execute(int $cmid, int $batchsize = 20, int $startfrom = 0): array
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'batchsize' => $batchsize,
            'startfrom' => $startfrom,
        ]);

        // Check capability.
        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/aigrading:useaigrading', $context);
        require_capability('mod/quiz:grade', $context);

        // Get config values.
        $configbatchsize = get_config('local_aigrading', 'batchsize') ?: 10;
        $batchsize = min($params['batchsize'], $configbatchsize * 2, 100);

        // Get the quiz.
        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        // Get the next batch of essay attempts whose latest step still needs grading.
        $basewhere = "FROM {quiz_attempts} qa
                JOIN {question_usages} qu ON qu.id = qa.uniqueid
                JOIN {question_attempts} qatt ON qatt.questionusageid = qu.id
                JOIN {question} q ON q.id = qatt.questionid
                JOIN {question_attempt_steps} qaslatest ON qaslatest.questionattemptid = qatt.id
                    AND qaslatest.sequencenumber = (
                        SELECT MAX(qasmax.sequencenumber)
                          FROM {question_attempt_steps} qasmax
                         WHERE qasmax.questionattemptid = qatt.id
                    )
                LEFT JOIN {qtype_essay_options} qe ON qe.questionid = q.id
               WHERE qa.quiz = :quizid
                 AND qa.state = 'finished'
                 AND q.qtype = 'essay'
                 AND qaslatest.state = 'needsgrading'
                 AND qatt.id > :startfrom";

        $queryparams = [
            'quizid' => $quiz->id,
            'startfrom' => $params['startfrom'],
        ];

        $totalcandidates = (int)$DB->count_records_sql("SELECT COUNT(1) {$basewhere}", $queryparams);

        $sql = "SELECT qatt.id AS questionattemptid,
                       qatt.slot,
                       qatt.questionid,
                       q.questiontext,
                       q.defaultmark,
                       qe.graderinfo,
                       qe.graderinfoformat,
                       qa.id AS attemptid,
                       qa.uniqueid AS qubaid
                FROM {quiz_attempts} qa
                JOIN {question_usages} qu ON qu.id = qa.uniqueid
                JOIN {question_attempts} qatt ON qatt.questionusageid = qu.id
                JOIN {question} q ON q.id = qatt.questionid
                JOIN {question_attempt_steps} qaslatest ON qaslatest.questionattemptid = qatt.id
                    AND qaslatest.sequencenumber = (
                        SELECT MAX(qasmax.sequencenumber)
                          FROM {question_attempt_steps} qasmax
                         WHERE qasmax.questionattemptid = qatt.id
                    )
                LEFT JOIN {qtype_essay_options} qe ON qe.questionid = q.id
                WHERE qa.quiz = :quizid
                AND qa.state = 'finished'
                AND q.qtype = 'essay'
                AND qaslatest.state = 'needsgrading'
                AND qatt.id > :startfrom
                ORDER BY qatt.id";

        $attempts = $DB->get_records_sql($sql, $queryparams, 0, $batchsize + 1);
        $hasmore = count($attempts) > $batchsize;
        $attempts = array_slice(array_values($attempts), 0, $batchsize);
        $batchcount = count($attempts);

        if (empty($attempts)) {
            return [
                'success' => true,
                'graded' => 0,
                'failed' => 0,
                'skipped' => 0,
                'total' => $totalcandidates,
                'remaining' => 0,
                'hasmore' => false,
                'nextstart' => 0,
                'currentbatch' => 0,
                'message' => 'No ungraded essay attempts found in this quiz.',
            ];
        }

        // Use AI service to grade.
        $service = new dali_service();
        $graded = 0;
        $failed = 0;
        $skipped = 0;
        $lastprocessedid = (int)$params['startfrom'];

        foreach ($attempts as $attempt) {
            $lastprocessedid = max($lastprocessedid, (int)$attempt->questionattemptid);
            $questiontext = strip_tags($attempt->questiontext);
            $maxgrade = $attempt->defaultmark;
            $graderinfo = !empty($attempt->graderinfo) ? strip_tags($attempt->graderinfo) : '';

            try {
                // Load the question usage and get the answer.
                $quba = \question_engine::load_questions_usage_by_activity($attempt->qubaid);
                $qa = $quba->get_question_attempt($attempt->slot);

                // Get the last response (student answer).
                $response = $qa->get_last_qt_data();
                $answertext = $response['answer'] ?? '';

                if (empty(trim($answertext))) {
                    // Skip empty answers.
                    $skipped++;
                    continue;
                }

                // Check if already graded.
                $step = $qa->get_last_step();
                if ($step->has_qt_var('-mark') && $step->get_qt_var('-mark') !== null) {
                    $skipped++;
                    continue;
                }

                // Get AI suggestion.
                $result = $service->suggest_grade($questiontext, $answertext, $maxgrade, null, $graderinfo);

                if ($result['success']) {
                    // Submit the grade using manual grading.
                    $quba->process_action($attempt->slot, [
                        '-mark' => $result['grade'],
                        '-maxmark' => $maxgrade,
                        '-comment' => $result['feedback'],
                        '-commentformat' => FORMAT_HTML,
                    ]);
                    \question_engine::save_questions_usage_by_activity($quba);
                    $graded++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        $remaining = max(0, $totalcandidates - $batchcount);

        return [
            'success' => true,
            'graded' => $graded,
            'failed' => $failed,
            'skipped' => $skipped,
            'total' => $totalcandidates,
            'remaining' => $remaining,
            'hasmore' => $hasmore,
            'nextstart' => $hasmore ? $lastprocessedid : 0,
            'currentbatch' => $batchcount,
            'message' => "Batch processed: $graded graded, $failed failed, $skipped skipped. $remaining remaining.",
        ];
    }

    /**
     * Returns the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'graded' => new external_value(PARAM_INT, 'Number of attempts graded'),
            'failed' => new external_value(PARAM_INT, 'Number of attempts failed'),
            'skipped' => new external_value(PARAM_INT, 'Number of attempts skipped'),
            'total' => new external_value(PARAM_INT, 'Total remaining candidates at batch start'),
            'remaining' => new external_value(PARAM_INT, 'Remaining attempts after this batch'),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether there are more attempts to process'),
            'nextstart' => new external_value(PARAM_INT, 'Next question attempt cursor'),
            'currentbatch' => new external_value(PARAM_INT, 'Number of attempts in current batch'),
            'message' => new external_value(PARAM_RAW, 'Status message'),
        ]);
    }
}
