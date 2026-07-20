<?php
namespace local_quiz_stats_cache\task;

use core\dml\sql_join;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/statisticslib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/report.php');

/**
 * Scheduled task: pre-cache quiz statistics for all quizzes with recent attempts.
 *
 * Runs every 2 hours. Calculates stats in background so users never wait.
 */
class precache_all extends \core\task\scheduled_task {

    public function get_name(): string {
        return 'Pre-cache all quiz statistics';
    }

    public function execute(): void {
        global $DB;

        $this->precache_all_quizzes();
    }

    /**
     * Pre-cache statistics for all quizzes that have finished attempts.
     */
    public function precache_all_quizzes(): void {
        global $DB;

        // Find quizzes with finished attempts.
        $sql = "SELECT DISTINCT q.id, q.name, q.course, q.grademethod
                FROM {quiz} q
                JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.state = 'finished'
                ORDER BY q.id";

        $quizzes = $DB->get_records_sql($sql);

        if (empty($quizzes)) {
            mtrace('  No quizzes with finished attempts found.');
            return;
        }

        mtrace('  Found ' . count($quizzes) . ' quizzes to check.');
        $processed = 0;
        $skipped = 0;

        foreach ($quizzes as $quiz) {
            // Skip if no changes since last calculation.
            if (!\local_quiz_stats_cache\fast_calculator::has_changes($quiz->id)) {
                $skipped++;
                continue;
            }

            $start = microtime(true);
            $result = \local_quiz_stats_cache\fast_calculator::calculate(
                $quiz->id,
                (int)$quiz->grademethod
            );

            if ($result) {
                // Save to Moodle's native cache tables so built-in report reads from cache.
                \local_quiz_stats_cache\fast_calculator::save_to_moodle_cache(
                    $quiz->id,
                    $result,
                    (int)$quiz->grademethod
                );
                $elapsed = round(microtime(true) - $start, 1);
                mtrace("    ✓ {$quiz->name} ({$quiz->id}) - {$elapsed}s ({$result['attempt_count']} attempts)");
                $processed++;
            } else {
                mtrace("    ✗ {$quiz->name} ({$quiz->id}) - no data");
            }
        }

        mtrace("  Processed: {$processed}, Skipped: {$skipped}");
    }

    /**
     * Pre-cache statistics for a single quiz.
     *
     * @param int $quizid
     * @return bool true if successful
     */
    public static function precache_quiz(int $quizid): bool {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        if (!$quiz) {
            return false;
        }

        $attemptcount = $DB->count_records('quiz_attempts', [
            'quiz' => $quizid,
            'state' => 'finished',
        ]);

        if ($attemptcount === 0) {
            return false;
        }

        mtrace("    Quiz {$quiz->name} (ID: $quizid) - $attemptcount attempts...");

        try {
            $result = \local_quiz_stats_cache\fast_calculator::calculate(
                $quizid,
                (int)$quiz->grademethod
            );
            if ($result) {
                mtrace("      ✓ Cached successfully.");
                return true;
            }
            mtrace("      ✗ No data returned.");
            return false;
        } catch (\Exception $e) {
            mtrace("      ✗ Error: " . $e->getMessage());
            return false;
        }
    }
}
