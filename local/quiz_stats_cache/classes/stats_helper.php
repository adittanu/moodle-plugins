<?php
namespace local_quiz_stats_cache;

defined('MOODLE_INTERNAL') || die();

/**
 * Library functions for quiz statistics cache plugin.
 */
class stats_helper {

    /**
     * Get cached statistics for a quiz.
     *
     * @param int $quizid
     * @return array|false stats array or false if not cached
     */
    public static function get_quiz_stats(int $quizid) {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        if (!$quiz) {
            return false;
        }

        $course = $DB->get_record('course', ['id' => $quiz->course]);

        // Get cached quiz statistics.
        $qubaids = quiz_statistics_qubaids_condition(
            $quizid,
            new \core\dml\sql_join(),
            $quiz->grademethod
        );

        $hashcode = $qubaids->get_hash_code();

        $quizstats = $DB->get_record('quiz_statistics', [
            'hashcode' => $hashcode,
        ]);

        if (!$quizstats) {
            return false;
        }

        // Get per-question statistics.
        $questionstats = $DB->get_records('question_statistics', [
            'hashcode' => $hashcode,
            'timemodified' => $quizstats->timemodified,
        ]);

        // Format output.
        $result = [
            'quiz' => [
                'id' => $quiz->id,
                'name' => $quiz->name,
                'course' => $course ? $course->fullname : '',
            ],
            'stats' => [
                'total_attempts' => (int)($quizstats->firstattemptscount + $quizstats->allattemptscount),
                'first_attempts' => [
                    'count' => (int)$quizstats->firstattemptscount,
                    'average' => $quizstats->firstattemptsavg !== null ? round((float)$quizstats->firstattemptsavg, 2) : null,
                ],
                'highest_attempts' => [
                    'count' => (int)$quizstats->highestattemptscount,
                    'average' => $quizstats->highestattemptsavg !== null ? round((float)$quizstats->highestattemptsavg, 2) : null,
                ],
                'last_attempts' => [
                    'count' => (int)$quizstats->lastattemptscount,
                    'average' => $quizstats->lastattemptsavg !== null ? round((float)$quizstats->lastattemptsavg, 2) : null,
                ],
                'all_attempts' => [
                    'count' => (int)$quizstats->allattemptscount,
                    'average' => $quizstats->allattemptsavg !== null ? round((float)$quizstats->allattemptsavg, 2) : null,
                ],
                'median' => $quizstats->median !== null ? round((float)$quizstats->median, 2) : null,
                'standard_deviation' => $quizstats->standarddeviation !== null ? round((float)$quizstats->standarddeviation, 2) : null,
                'skewness' => $quizstats->skewness !== null ? round((float)$quizstats->skewness, 4) : null,
                'kurtosis' => $quizstats->kurtosis !== null ? round((float)$quizstats->kurtosis, 4) : null,
                'cic' => $quizstats->cic !== null ? round((float)$quizstats->cic, 2) : null,
                'error_ratio' => $quizstats->errorratio !== null ? round((float)$quizstats->errorratio, 2) : null,
                'standard_error' => $quizstats->standarderror !== null ? round((float)$quizstats->standarderror, 2) : null,
            ],
            'questions' => [],
            'cached_at' => $quizstats->timemodified ? date('c', $quizstats->timemodified) : null,
        ];

        // Format per-question stats.
        foreach ($questionstats as $qstat) {
            if ($qstat->subquestion) {
                continue; // Skip sub-questions for clean output.
            }
            $result['questions'][] = [
                'slot' => (int)$qstat->slot,
                'question_id' => (int)$qstat->questionid,
                'attempts' => (int)$qstat->s,
                'facility' => $qstat->facility !== null ? round((float)$qstat->facility, 4) : null,
                'standard_deviation' => $qstat->standarddeviation !== null ? round((float)$qstat->standarddeviation, 4) : null,
                'random_guess_score' => $qstat->randomguessscore !== null ? round((float)$qstat->randomguessscore, 4) : null,
                'intended_weight' => $qstat->intendedweight !== null ? round((float)$qstat->intendedweight, 4) : null,
                'effective_weight' => $qstat->effectiveweight !== null ? round((float)$qstat->effectiveweight, 4) : null,
                'discrimination_index' => $qstat->discriminationindex !== null ? round((float)$qstat->discriminationindex, 4) : null,
                'discriminative_efficiency' => $qstat->discriminativeefficiency !== null ? round((float)$qstat->discriminativeefficiency, 4) : null,
            ];
        }

        return $result;
    }

    /**
     * Get list of all quizzes with their cache status.
     *
     * @return array
     */
    public static function get_all_quiz_cache_status(): array {
        global $DB;

        $sql = "SELECT q.id, q.name, c.fullname AS course_name,
                       (SELECT COUNT(1) FROM {quiz_attempts} qa
                        WHERE qa.quiz = q.id AND qa.state = 'finished') AS attempt_count,
                       qs.timemodified AS cached_at
                FROM {quiz} q
                JOIN {course} c ON c.id = q.course
                LEFT JOIN {quiz_statistics} qs ON qs.hashcode = CONCAT('quiz_statistics_', q.id)
                WHERE EXISTS (
                    SELECT 1 FROM {quiz_attempts} qa2
                    WHERE qa2.quiz = q.id AND qa2.state = 'finished'
                )
                ORDER BY q.id";

        return $DB->get_records_sql($sql);
    }
}
