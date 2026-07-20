<?php
/**
 * CLI script to pre-cache quiz statistics.
 *
 * Usage:
 *   php local/quiz_stats_cache/cli/precache.php                    # All quizzes
 *   php local/quiz_stats_cache/cli/precache.php --quizid=42        # Single quiz
 *   php local/quiz_stats_cache/cli/precache.php --stale=3600       # Only if cache older than 1 hour
 *   php local/quiz_stats_cache/cli/precache.php --dry-run          # Show what would be done
 *
 * @package    local_quiz_stats_cache
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
define('NO_OUTPUT_BUFFERING', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/statisticslib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/report.php');

// Parse CLI options.
list($options, $unrecognised) = cli_get_params([
    'quizid'  => false,
    'force'   => false,
    'stale'   => 0,
    'fast'    => false,
    'dry-run' => false,
    'help'    => false,
], [
    'h' => 'help',
]);
if ($options['help']) {
    echo "Pre-cache quiz statistics.

Usage:
  php local/quiz_stats_cache/cli/precache.php [options]

Options:
  --quizid=ID     Cache stats for a specific quiz only
  --force         Recalculate even if no changes detected
  --fast          Use SQL-based calculator (100-1000x faster, same results)
  --dry-run       Show what would be done without doing it
  -h, --help      Show this help

Behavior:
  - Without --force: only recalculates if quiz has new attempts since last run
  - With --force: always recalcululates (use after quiz edit, grade change, etc.)
  - Cached results stored in temp dir, returned instantly if no changes

Examples:
  php local/quiz_stats_cache/cli/precache.php --fast
  php local/quiz_stats_cache/cli/precache.php --fast --quizid=42
  php local/quiz_stats_cache/cli/precache.php --fast --force
";
    exit(0);
}

$mtrace = function(string $msg) {
    echo $msg . "\n";
};

$mtrace("=== Quiz Statistics Pre-Cache ===");
$mtrace("Time: " . date('Y-m-d H:i:s'));
$mtrace("");

// Build query.
if ($options['quizid']) {
    $quizid = (int)$options['quizid'];
    $quiz = $DB->get_record('quiz', ['id' => $quizid]);
    if (!$quiz) {
        $mtrace("ERROR: Quiz ID $quizid not found.");
        exit(1);
    }
    $quizzes = [$quiz];
} else {
    $quizzes = $DB->get_records_sql(
        "SELECT q.id, q.name, q.course, q.grademethod
         FROM {quiz} q
         WHERE EXISTS (
             SELECT 1 FROM {quiz_attempts} qa
             WHERE qa.quiz = q.id AND qa.state = 'finished'
         )
         ORDER BY q.id"
    );
}

if (empty($quizzes)) {
    $mtrace("No quizzes with finished attempts found.");
    exit(0);
}

$mtrace("Found " . count($quizzes) . " quiz(es) to process.");
$mtrace("");

$success = 0;
$skipped = 0;
$failed = 0;

foreach ($quizzes as $quiz) {
    $attemptcount = $DB->count_records('quiz_attempts', [
        'quiz' => $quiz->id,
        'state' => 'finished',
    ]);

    // Check if there are changes (unless --force or --stale).
    if (!$options['force'] && $options['fast']) {
        if ($options['stale'] > 0) {
            // --stale=SECS: skip if last calculation is newer than SECS.
            $lastcalc = (int) get_config('local_quiz_stats_cache', "lastcalc_{$quiz->id}");
            if ($lastcalc && (time() - $lastcalc) < (int)$options['stale']) {
                $age = time() - $lastcalc;
                $mtrace("  [SKIP] {$quiz->name} (ID: {$quiz->id}) - cache is recent ({$age}s ago)");
                $skipped++;
                continue;
            }
        } else if (!\local_quiz_stats_cache\fast_calculator::has_changes($quiz->id)) {
            $mtrace("  [SKIP] {$quiz->name} (ID: {$quiz->id}) - no changes");
            $skipped++;
            continue;
        }
    }

    $mode = $options['fast'] ? 'FAST' : 'MOODLE';
    $mtrace("  [CACHE:$mode] {$quiz->name} (ID: {$quiz->id}) - {$attemptcount} attempts...");

    if ($options['dry-run']) {
        $mtrace("    (dry-run, skipping)");
        $skipped++;
        continue;
    }

    try {
        $start = microtime(true);

        if ($options['fast']) {
            // SQL-based calculator — no PHP loops, no timeout.
            $result = \local_quiz_stats_cache\fast_calculator::calculate(
                $quiz->id,
                (int)$quiz->grademethod,
                $options['force']
            );
            if ($result === false) {
                throw new \Exception('No data or calculation failed');
            }
            $elapsed = round(microtime(true) - $start, 1);

            // Also save to Moodle's native tables so built-in report reads cache.
            \local_quiz_stats_cache\fast_calculator::save_to_moodle_cache(
                $quiz->id, $result, (int)$quiz->grademethod
            );

            $mtrace("    ✓ Done in {$elapsed}s ({$result['attempt_count']} attempts) [cached to Moodle tables]");
        } else {
            // Moodle's original calculator.
            $qubaids = quiz_statistics_qubaids_condition(
                $quiz->id,
                new \core\dml\sql_join(),
                $quiz->grademethod
            );
            $report = new \quiz_statistics_report();
            $report->clear_cached_data($qubaids);
            $report->calculate_questions_stats_for_question_bank($quiz->id);
            $elapsed = round(microtime(true) - $start, 1);
            $mtrace("    ✓ Done in {$elapsed}s");
        }
        $success++;
    } catch (\Exception $e) {
        $mtrace("    ✗ Error: " . $e->getMessage());
        $failed++;
    }
}

$mtrace("");
$mtrace("=== Summary ===");
$mtrace("  Success: $success");
$mtrace("  Skipped: $skipped");
$mtrace("  Failed:  $failed");
$mtrace("");
$mtrace("Done.");
