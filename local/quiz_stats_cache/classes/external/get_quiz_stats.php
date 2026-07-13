<?php
namespace local_quiz_stats_cache\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/statisticslib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/report.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use local_quiz_stats_cache\stats_helper;

/**
 * External function to get cached quiz statistics.
 */
class get_quiz_stats extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'Quiz ID', VALUE_REQUIRED),
        ]);
    }

    public static function execute(int $quizid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['quizid' => $quizid]);

        $quiz = $DB->get_record('quiz', ['id' => $params['quizid']]);
        if (!$quiz) {
            throw new \moodle_exception('invalidquizid', 'quiz');
        }

        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quiz:viewreports', $context);

        $stats = stats_helper::get_quiz_stats($params['quizid']);

        if ($stats === false) {
            // Not cached — use fast SQL calculator instead of waiting.
            $fastresult = \local_quiz_stats_cache\fast_calculator::calculate(
                $params['quizid'],
                (int)$quiz->grademethod
            );
            if ($fastresult) {
                return [
                    'cached' => true,
                    'message' => 'OK (computed live via SQL)',
                    'quiz' => $fastresult['quiz'],
                    'stats' => $fastresult['stats'],
                    'questions' => $fastresult['questions'],
                ];
            }
            return [
                'cached' => false,
                'message' => 'No data available for this quiz.',
                'quiz' => ['id' => $quiz->id, 'name' => $quiz->name],
                'stats' => null,
                'questions' => [],
            ];
        }

        return [
            'cached' => true,
            'message' => 'OK',
            'quiz' => $stats['quiz'],
            'stats' => $stats['stats'],
            'questions' => $stats['questions'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cached' => new external_value(PARAM_BOOL, 'Whether stats were cached'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'quiz' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Quiz ID'),
                'name' => new external_value(PARAM_TEXT, 'Quiz name'),
            ], 'Quiz info', VALUE_OPTIONAL),
            'stats' => new external_single_structure([
                'total_attempts' => new external_value(PARAM_INT, 'Total attempts'),
                'first_attempts' => new external_single_structure([
                    'count' => new external_value(PARAM_INT, 'Count'),
                    'average' => new external_value(PARAM_FLOAT, 'Average grade', VALUE_OPTIONAL),
                ], 'First attempts'),
                'highest_attempts' => new external_single_structure([
                    'count' => new external_value(PARAM_INT, 'Count'),
                    'average' => new external_value(PARAM_FLOAT, 'Average grade', VALUE_OPTIONAL),
                ], 'Highest attempts'),
                'last_attempts' => new external_single_structure([
                    'count' => new external_value(PARAM_INT, 'Count'),
                    'average' => new external_value(PARAM_FLOAT, 'Average grade', VALUE_OPTIONAL),
                ], 'Last attempts'),
                'all_attempts' => new external_single_structure([
                    'count' => new external_value(PARAM_INT, 'Count'),
                    'average' => new external_value(PARAM_FLOAT, 'Average grade', VALUE_OPTIONAL),
                ], 'All attempts'),
                'median' => new external_value(PARAM_FLOAT, 'Median', VALUE_OPTIONAL),
                'standard_deviation' => new external_value(PARAM_FLOAT, 'Standard deviation', VALUE_OPTIONAL),
                'skewness' => new external_value(PARAM_FLOAT, 'Skewness', VALUE_OPTIONAL),
                'kurtosis' => new external_value(PARAM_FLOAT, 'Kurtosis', VALUE_OPTIONAL),
                'cic' => new external_value(PARAM_FLOAT, 'Cronbach alpha', VALUE_OPTIONAL),
                'error_ratio' => new external_value(PARAM_FLOAT, 'Error ratio', VALUE_OPTIONAL),
                'standard_error' => new external_value(PARAM_FLOAT, 'Standard error', VALUE_OPTIONAL),
            ], 'Quiz statistics', VALUE_OPTIONAL),
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'slot' => new external_value(PARAM_INT, 'Question slot'),
                    'question_id' => new external_value(PARAM_INT, 'Question ID'),
                    'attempts' => new external_value(PARAM_INT, 'Number of attempts'),
                    'facility' => new external_value(PARAM_FLOAT, 'Facility index (0-1)', VALUE_OPTIONAL),
                    'standard_deviation' => new external_value(PARAM_FLOAT, 'Standard deviation', VALUE_OPTIONAL),
                    'random_guess_score' => new external_value(PARAM_FLOAT, 'Random guess score', VALUE_OPTIONAL),
                    'intended_weight' => new external_value(PARAM_FLOAT, 'Intended weight', VALUE_OPTIONAL),
                    'effective_weight' => new external_value(PARAM_FLOAT, 'Effective weight', VALUE_OPTIONAL),
                    'discrimination_index' => new external_value(PARAM_FLOAT, 'Discrimination index', VALUE_OPTIONAL),
                    'discriminative_efficiency' => new external_value(PARAM_FLOAT, 'Discriminative efficiency', VALUE_OPTIONAL),
                ]),
                'Per-question statistics'
            ),
        ]);
    }
}
