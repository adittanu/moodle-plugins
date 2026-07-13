<?php
/**
 * Fast recalculate statistics for a single quiz.
 *
 * Web:  https://moodle41.test/local/quiz_stats_cache/recalculate.php?quizid=3&sesskey=XXX&web=1
 * CLI:  php local/quiz_stats_cache/recalculate.php --quizid=3
 */

// Detect web vs CLI mode.
$isweb = isset($_GET['quizid']) || isset($_POST['quizid']) || isset($_GET['id']) || isset($_POST['id']);

if (!$isweb) {
    define('CLI_SCRIPT', true);
}
require(__DIR__ . '/../../config.php');

if ($isweb) {
    // === WEB MODE ===
    require_login();
    // Accept both quizid and id (cmid).
    $quizid = optional_param('quizid', 0, PARAM_INT);
    $cmid = optional_param('id', 0, PARAM_INT);

    if ($quizid) {
        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
    } elseif ($cmid) {
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance]);
    } else {
        print_error('missingparam', '', '', 'quizid or id');
    }

    if (!$quiz) {
        print_error('invalidquizid', 'quiz');
    }

    $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course);
    $context = context_module::instance($cm->id);
    require_capability('mod/quiz:viewreports', $context);
    $PAGE->set_context($context);
    $PAGE->set_url('/local/quiz_stats_cache/recalculate.php', ['quizid' => $quizid]);
    $result = local_quiz_stats_cache\fast_calculator::calculate($quizid, (int)$quiz->grademethod, true);
    $elapsed = round(microtime(true) - $start, 2);

    if ($result === false) {
        redirect(
            new moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'statistics']),
            'No data available for this quiz.',
            null,
            \core\output\notification::NOTIFY_ERROR
        );
        exit;
    }

    // Save to Moodle's native cache tables.
    local_quiz_stats_cache\fast_calculator::save_to_moodle_cache($quizid, $result, (int)$quiz->grademethod);

    // Redirect back to statistics page.
    redirect(
        new moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'statistics']),
        "Statistics recalculated in {$elapsed}s ({$result['attempt_count']} attempts)",
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
    exit;

} else {
    // === CLI MODE ===
    $quizid = null;
    foreach ($argv as $arg) {
        if (preg_match('/^--quizid=(\d+)$/', $arg, $m)) {
            $quizid = (int)$m[1];
        }
    }
    if (!$quizid) {
        echo "Usage: php recalculate.php --quizid=ID\n";
        exit(1);
    }

    $quiz = $DB->get_record('quiz', ['id' => $quizid]);
    if (!$quiz) {
        echo "Quiz ID $quizid not found.\n";
        exit(1);
    }

    $start = microtime(true);
    $result = local_quiz_stats_cache\fast_calculator::calculate($quizid, (int)$quiz->grademethod, true);
    $elapsed = round(microtime(true) - $start, 2);

    if ($result === false) {
        echo "No data or calculation failed for quiz $quizid.\n";
        exit(1);
    }

    local_quiz_stats_cache\fast_calculator::save_to_moodle_cache($quizid, $result, (int)$quiz->grademethod);

    echo "Quiz: {$quiz->name} (ID: $quizid)\n";
    echo "Attempts: {$result['attempt_count']}\n";
    echo "Time: {$elapsed}s\n";
    echo "Cached to Moodle tables.\n";
    exit(0);
}
