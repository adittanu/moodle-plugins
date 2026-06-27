<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Webcam Guard attempt detail page.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid, 'quiz' => $quiz->id], '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $attempt->userid], '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('quizaccess/webcamguard:viewreport', $context);

$url = new moodle_url('/mod/quiz/accessrule/webcamguard/attempt.php', ['cmid' => $cmid, 'attemptid' => $attemptid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('attemptdetailtitle', 'quizaccess_webcamguard'));
$PAGE->set_heading(format_string($quiz->name));

$review = $DB->get_record('quizaccess_wg_reviews', ['attemptid' => $attemptid]);
if (!$review) {
    $now = time();
    $review = (object)[
        'attemptid' => $attemptid,
        'quizid' => $quiz->id,
        'userid' => $attempt->userid,
        'status' => 'pending',
        'reviewedby' => null,
        'reviewcomment' => '',
        'timecreated' => $now,
        'timemodified' => $now,
        'timereviewed' => null,
    ];
    try {
        $review->id = $DB->insert_record('quizaccess_wg_reviews', $review);
    } catch (\dml_exception $e) {
        $review = $DB->get_record('quizaccess_wg_reviews', ['attemptid' => $attemptid]);
    }
}

if (data_submitted() && confirm_sesskey()) {
    require_capability('quizaccess/webcamguard:reviewattempts', $context);
    $newstatus = required_param('reviewstatus', PARAM_ALPHANUMEXT);
    if (!in_array($newstatus, ['pending', 'cleared', 'suspicious'], true)) {
        throw new invalid_parameter_exception('Invalid review status.');
    }
    $comment = optional_param('reviewcomment', '', PARAM_TEXT);
    $now = time();
    $review->status = $newstatus;
    $review->reviewcomment = $comment;
    $review->reviewedby = $USER->id;
    $review->timemodified = $now;
    $review->timereviewed = $now;
    $DB->update_record('quizaccess_wg_reviews', $review);
    redirect($url, get_string('reviewupdated', 'quizaccess_webcamguard'));
}

$events = $DB->get_records('quizaccess_wg_events', ['attemptid' => $attemptid], 'timecreated ASC, id ASC');
$webcamguardconfig = $DB->get_record('quizaccess_wg_config', ['quizid' => $quiz->id, 'enabled' => 1]);
$liveenabled = $webcamguardconfig
    && !empty($webcamguardconfig->liveenabled)
    && \quizaccess_webcamguard\livekit\token_service::is_configured();

$statusoptions = [
    'pending' => get_string('pending', 'quizaccess_webcamguard'),
    'cleared' => get_string('cleared', 'quizaccess_webcamguard'),
    'suspicious' => get_string('suspicious', 'quizaccess_webcamguard'),
];
$form = html_writer::start_tag('form', ['method' => 'post', 'class' => 'mb-4']);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cmid]);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'attemptid', 'value' => $attemptid]);
$form .= html_writer::div(html_writer::label(get_string('status', 'quizaccess_webcamguard'), 'id_reviewstatus') .
    html_writer::select($statusoptions, 'reviewstatus', $review->status, false, ['id' => 'id_reviewstatus']), 'form-group');
$form .= html_writer::div(html_writer::label(get_string('reviewcomment', 'quizaccess_webcamguard'), 'id_reviewcomment') .
    html_writer::tag('textarea', s($review->reviewcomment), [
        'id' => 'id_reviewcomment',
        'name' => 'reviewcomment',
        'class' => 'form-control',
        'rows' => 3,
    ]), 'form-group');
$form .= html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary',
    'value' => get_string('savereview', 'quizaccess_webcamguard')]);
$form .= html_writer::end_tag('form');

require_once(__DIR__ . '/classes/output/attempt_detail.php');

$reporturl = new moodle_url('/mod/quiz/accessrule/webcamguard/report.php', ['cmid' => $cmid]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('attemptdetailtitle', 'quizaccess_webcamguard'));
echo html_writer::div(html_writer::link($reporturl, get_string('reporttitle', 'quizaccess_webcamguard')), 'mb-3');
echo html_writer::tag('p', get_string('student', 'quizaccess_webcamguard') . ': ' . fullname($user));
echo html_writer::tag('p', get_string('attempt', 'quizaccess_webcamguard') . ': ' . s($attempt->attempt));
echo \quizaccess_webcamguard\output\attempt_detail::render_summary(array_values($events));
echo $form;
if ($liveenabled) {
    $livepanel = html_writer::div(
        html_writer::tag('button', get_string('livewatch', 'quizaccess_webcamguard'), [
            'type' => 'button',
            'class' => 'btn btn-secondary mr-2',
            'data-action' => 'webcamguard-live-start',
        ]) .
        html_writer::tag('button', get_string('livestop', 'quizaccess_webcamguard'), [
            'type' => 'button',
            'class' => 'btn btn-outline-secondary',
            'data-action' => 'webcamguard-live-stop',
        ]) .
        html_writer::div(get_string('livestatusidle', 'quizaccess_webcamguard'), 'mt-2 text-muted', [
            'data-region' => 'webcamguard-live-status',
        ]) .
        html_writer::div('', 'mt-3', [
            'data-region' => 'webcamguard-live-video',
        ]),
        'p-3 mb-4 border rounded bg-light',
        ['data-region' => 'webcamguard-live-panel']
    );
    echo $livepanel;
    $PAGE->requires->js_call_amd('quizaccess_webcamguard/live_teacher', 'init', [[
        'courseid' => (int)$course->id,
        'cmid' => (int)$cm->id,
        'quizid' => (int)$quiz->id,
        'attemptid' => (int)$attempt->id,
        'scriptUrl' => $CFG->wwwroot . '/mod/quiz/accessrule/webcamguard/livekit/livekit-client.umd.js',
        'strings' => [
            'starting' => get_string('livestarting', 'quizaccess_webcamguard'),
            'waiting' => get_string('livewaitingstudent', 'quizaccess_webcamguard'),
            'connected' => get_string('livestudentconnected', 'quizaccess_webcamguard'),
            'stopped' => get_string('livestopped', 'quizaccess_webcamguard'),
            'failed' => get_string('livefailed', 'quizaccess_webcamguard'),
            'notconfigured' => get_string('livenotconfigured', 'quizaccess_webcamguard'),
        ],
    ]]);
}
echo $OUTPUT->heading(get_string('timeline', 'quizaccess_webcamguard'), 3);
echo \quizaccess_webcamguard\output\attempt_detail::render_events(array_values($events), $context->id);
echo $OUTPUT->footer();
