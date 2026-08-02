<?php
// This file is part of Moodle - http://moodle.org/.

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');

$quizid = required_param('quizid', PARAM_INT);
$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quizid, $quiz->course, false, MUST_EXIST);
require_login($quiz->course, false, $cm);
require_capability('mod/quiz:viewreports', context_module::instance($cm->id));

$job = \quiz_lightstats\job::get($quizid);
header('Content-Type: application/json');
echo json_encode($job ? [
    'status' => $job->status,
    'progress' => (int)$job->progress,
    'message' => $job->message,
    'complete' => $job->status === 'complete',
] : ['status' => 'none', 'progress' => 0, 'message' => '', 'complete' => false]);
