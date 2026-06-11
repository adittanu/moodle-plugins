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
use external_multiple_structure;
use external_value;
use local_aigrading\dali_service;

/**
 * External function to auto-grade all ungraded essays for a quiz question.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_grade_question extends external_api
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
            'slot' => new external_value(PARAM_INT, 'Question slot number'),
            'questionid' => new external_value(PARAM_INT, 'Question ID'),
            'batchsize' => new external_value(PARAM_INT, 'Batch size for processing', false, 10),
            'startfrom' => new external_value(PARAM_INT, 'Start from index (for resume)', false, 0),
            'progresskey' => new external_value(PARAM_ALPHANUM, 'Progress tracking key', false, ''),
        ]);
    }

    /**
     * Execute the function - grade all ungraded attempts for a question.
     * Now supports batch processing and skip already graded.
     *
     * @param int $cmid Course module ID
     * @param int $slot Question slot
     * @param int $questionid Question ID
     * @param int $batchsize Batch size
     * @param int $startfrom Start index
     * @param string $progresskey Progress tracking key
     * @return array
     */
    public static function execute(int $cmid, int $slot, int $questionid, int $batchsize = 10, int $startfrom = 0, string $progresskey = ''): array
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'slot' => $slot,
            'questionid' => $questionid,
            'batchsize' => $batchsize,
            'startfrom' => $startfrom,
            'progresskey' => $progresskey,
        ]);

        // Check capability.
        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/aigrading:useaigrading', $context);
        require_capability('mod/quiz:grade', $context);

        // Get config values.
        $configbatchsize = get_config('local_aigrading', 'batchsize') ?: 10;
        $batchsize = min($params['batchsize'], $configbatchsize, 50); // Cap at 50
        
        // Get the quiz.
        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        // Get question info.
        $question = $DB->get_record('question', ['id' => $params['questionid']], '*', MUST_EXIST);
        $questiontext = strip_tags($question->questiontext);
        $maxgrade = $question->defaultmark;

        // Get ALL ungraded attempts for this question.
        $sql = "SELECT DISTINCT
                    qa.id as attemptid,
                    qa.uniqueid as qubaid,
                    qatt.id as questionattemptid
                FROM {quiz_attempts} qa
                JOIN {question_usages} qu ON qu.id = qa.uniqueid
                JOIN {question_attempts} qatt ON qatt.questionusageid = qu.id AND qatt.slot = :slot
                WHERE qa.quiz = :quizid
                AND qa.state = 'finished'
                AND qatt.questionid = :questionid
                AND EXISTS (
                    SELECT 1 FROM {question_attempt_steps} qas2 
                    WHERE qas2.questionattemptid = qatt.id 
                    AND qas2.state = 'needsgrading'
                )
                ORDER BY qa.id";

        $allattempts = $DB->get_records_sql($sql, [
            'quizid' => $quiz->id,
            'slot' => $params['slot'],
            'questionid' => $params['questionid'],
        ]);

        $totalcount = count($allattempts);
        
        if ($totalcount === 0) {
            return [
                'success' => true,
                'graded' => 0,
                'failed' => 0,
                'skipped' => 0,
                'total' => 0,
                'remaining' => 0,
                'hasmore' => false,
                'nextstart' => 0,
                'message' => 'No ungraded attempts found. All essays may have been graded already.',
            ];
        }

        // Slice the batch from all attempts.
        $attempts = array_slice($allattempts, $params['startfrom'], $batchsize);
        $batchcount = count($attempts);

        // Use AI service to grade each attempt.
        $service = new dali_service();
        $graded = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($attempts as $attempt) {
            try {
                // Load the question usage and get the answer.
                $quba = \question_engine::load_questions_usage_by_activity($attempt->qubaid);
                $qa = $quba->get_question_attempt($params['slot']);

                // Get the last response (student answer).
                $response = $qa->get_last_qt_data();
                $answertext = $response['answer'] ?? '';

                if (empty(trim($answertext))) {
                    // Skip empty answers - count as skipped not failed
                    $skipped++;
                    continue;
                }

                // Check if already has a grade (double-check to avoid regrading)
                // Check if the step state is not 'needsgrading' anymore
                $step = $qa->get_last_step();
                if ($step->has_qt_var('-mark') && $step->get_qt_var('-mark') !== null) {
                    // Already graded - has mark set
                    $skipped++;
                    continue;
                }

                // Get AI suggestion.
                $result = $service->suggest_grade($questiontext, $answertext, $maxgrade);

                if ($result['success']) {
                    // Submit the grade using manual grading.
                    $quba->process_action($params['slot'], [
                        '-mark' => $result['grade'],
                        '-maxmark' => $maxgrade,
                        '-comment' => $result['feedback'] ?? '',
                        '-commentformat' => FORMAT_HTML,
                    ]);
                    \question_engine::save_questions_usage_by_activity($quba);
                    $graded++;
                } else {
                    $failed++;
                    $errors[] = $result['error'] ?? 'Unknown error';
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }

        // Update progress in cache if key provided.
        if (!empty($progresskey)) {
            $progressdata = (array)(\cache::make('local_aigrading', 'grading_progress')->get($progresskey) ?: []);
            $progressdata['graded'] = ($progressdata['graded'] ?? 0) + $graded;
            $progressdata['failed'] = ($progressdata['failed'] ?? 0) + $failed;
            $progressdata['skipped'] = ($progressdata['skipped'] ?? 0) + $skipped;
            $progressdata['processed'] = ($progressdata['processed'] ?? 0) + $batchcount;
            $progressdata['lastupdate'] = time();
            \cache::make('local_aigrading', 'grading_progress')->set($progresskey, $progressdata);
        }

        // Calculate remaining.
        $processedsofar = $params['startfrom'] + $batchcount;
        $remaining = max(0, $totalcount - $processedsofar);
        $hasmore = $remaining > 0;
        $nextstart = $hasmore ? $processedsofar : 0;

        return [
            'success' => true,
            'graded' => $graded,
            'failed' => $failed,
            'skipped' => $skipped,
            'total' => $totalcount,
            'remaining' => $remaining,
            'hasmore' => $hasmore,
            'nextstart' => $nextstart,
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
            'graded' => new external_value(PARAM_INT, 'Number of attempts graded in this batch'),
            'failed' => new external_value(PARAM_INT, 'Number of attempts failed in this batch'),
            'skipped' => new external_value(PARAM_INT, 'Number of attempts skipped (empty or already graded)'),
            'total' => new external_value(PARAM_INT, 'Total ungraded attempts'),
            'remaining' => new external_value(PARAM_INT, 'Remaining attempts to grade'),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether there are more attempts to process'),
            'nextstart' => new external_value(PARAM_INT, 'Next start index for batch'),
            'currentbatch' => new external_value(PARAM_INT, 'Number of attempts in current batch'),
            'message' => new external_value(PARAM_RAW, 'Status message'),
        ]);
    }
}
