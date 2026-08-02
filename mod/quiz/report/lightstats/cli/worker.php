<?php
// This file is part of Moodle - http://moodle.org/.

define('CLI_SCRIPT', true);
define('NO_OUTPUT_BUFFERING', true);

require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$job = $DB->get_record_sql("SELECT * FROM {quiz_lightstats_jobs} WHERE status = 'queued' ORDER BY timecreated ASC", [], IGNORE_MULTIPLE);
if (!$job) {
    mtrace('No queued light statistics jobs.');
    exit(0);
}

$job->timestarted = time();
\quiz_lightstats\job::update($job, 'running', 15, 'Loading finished attempts');

try {
    $quiz = $DB->get_record('quiz', ['id' => $job->quizid], '*', MUST_EXIST);
    $data = (new \quiz_lightstats\calculator($DB))->calculate($quiz);
    \quiz_lightstats\job::update($job, 'running', 85, 'Saving cached statistics');
    $job->payload = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    if ($job->payload === false) {
        throw new coding_exception('Could not encode statistics payload.');
    }
    $job->attemptcount = \quiz_lightstats\job::attempt_count($job->quizid);
    $job->timecompleted = time();
    $job->error = null;
    \quiz_lightstats\job::update($job, 'complete', 100, 'Complete');
    mtrace("Completed light statistics for quiz {$job->quizid}.");
} catch (Throwable $error) {
    $job->error = $error->getMessage();
    $job->timecompleted = time();
    \quiz_lightstats\job::update($job, 'failed', 100, 'Failed');
    mtrace("Failed light statistics for quiz {$job->quizid}: {$error->getMessage()}");
    exit(1);
}
