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

$clearevents = optional_param('clearevents', 0, PARAM_BOOL);
if ($clearevents && confirm_sesskey()) {
    require_capability('quizaccess/webcamguard:reviewattempts', $context);
    $DB->delete_records('quizaccess_wg_events', ['attemptid' => $attemptid]);
    redirect($url, get_string('eventscleared', 'quizaccess_webcamguard'));
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
$page = optional_param('page', 0, PARAM_INT);
$filtertype = optional_param('filtertype', '', PARAM_ALPHANUMEXT);
$filterseverity = optional_param('filterseverity', '', PARAM_ALPHA);

$eventtypes = [
    '' => get_string('alltypes', 'quizaccess_webcamguard'),
    'no_face' => get_string('event_no_face', 'quizaccess_webcamguard'),
    'multiple_faces' => get_string('event_multiple_faces', 'quizaccess_webcamguard'),
    'window_blur' => get_string('event_window_blur', 'quizaccess_webcamguard'),
    'camera_error' => get_string('event_camera_error', 'quizaccess_webcamguard'),
    'camera_stopped' => get_string('event_camera_stopped', 'quizaccess_webcamguard'),
    'monitoring_error' => get_string('event_monitoring_error', 'quizaccess_webcamguard'),
    'identity_check' => get_string('event_identity_check', 'quizaccess_webcamguard'),
    'warning_sent' => get_string('event_warning_sent', 'quizaccess_webcamguard'),
];
$severities = [
    '' => get_string('allseverities', 'quizaccess_webcamguard'),
    'violation' => get_string('severity_violation', 'quizaccess_webcamguard'),
    'warning' => get_string('severity_warning', 'quizaccess_webcamguard'),
    'info' => get_string('severity_info', 'quizaccess_webcamguard'),
];

// Filter events.
$filtered = [];
$hiddentypes = ['heartbeat', 'live_started', 'live_disconnected', 'live_requested', 'live_stopped'];
$monitoringstartedshown = false;
foreach ($events as $event) {
    if (in_array($event->eventtype, $hiddentypes, true)) {
        continue;
    }
    if ($event->eventtype === 'monitoring_resumed' && $event->severity !== 'violation') {
        continue;
    }
    if ($event->eventtype === 'monitoring_started') {
        if ($monitoringstartedshown) {
            continue;
        }
        $monitoringstartedshown = true;
    }
    if ($filtertype !== '' && $event->eventtype !== $filtertype) {
        continue;
    }
    if ($filterseverity !== '' && $event->severity !== $filterseverity) {
        continue;
    }
    $filtered[] = $event;
}
$filtered = array_values($filtered);

echo $OUTPUT->heading(get_string('timeline', 'quizaccess_webcamguard'), 3);
echo html_writer::start_div('mb-2');
$clearurl = new moodle_url('/mod/quiz/accessrule/webcamguard/attempt.php', [
    'cmid' => $cmid,
    'attemptid' => $attempt->id,
    'clearevents' => 1,
    'sesskey' => sesskey(),
]);
echo html_writer::tag('button', get_string('clearevents', 'quizaccess_webcamguard'), [
    'type' => 'button',
    'class' => 'btn btn-sm btn-outline-danger',
    'onclick' => 'if(confirm(\'' . addslashes(get_string('cleareventsconfirm', 'quizaccess_webcamguard')) . '\')){window.location.href=\'' . $clearurl->out(false) . '\';}',
]);
echo html_writer::end_div();

// Filter form.
$filterurl = new moodle_url('/mod/quiz/accessrule/webcamguard/attempt.php', [
    'cmid' => $cmid,
    'attemptid' => $attempt->id,
]);
echo html_writer::start_div('d-flex align-items-center gap-2 mb-3', ['style' => 'column-gap:8px;']);
echo html_writer::label(get_string('eventtype', 'quizaccess_webcamguard'), 'filtertype', false, ['class' => 'mr-1 mb-0']);
echo html_writer::select($eventtypes, 'filtertype', $filtertype, false, [
    'id' => 'filtertype',
    'class' => 'custom-select custom-select-sm',
    'onchange' => 'window.location.href=\'' . $filterurl->out(false) . '&page=0&filtertype=\'+this.value+\'&filterseverity=' . $filterseverity . '\'',
]);
echo '&nbsp;';
echo html_writer::label(get_string('severity', 'quizaccess_webcamguard'), 'filterseverity', false, ['class' => 'mr-1 mb-0']);
echo html_writer::select($severities, 'filterseverity', $filterseverity, false, [
    'id' => 'filterseverity',
    'class' => 'custom-select custom-select-sm',
    'onchange' => 'window.location.href=\'' . $filterurl->out(false) . '&page=0&filtertype=' . $filtertype . '&filterseverity=\'+this.value',
]);
echo html_writer::end_div();

$perpage = 12;
$total = count($filtered);
$pages = max(1, (int)ceil($total / $perpage));
$page = max(0, min($page, $pages - 1));
$offset = $page * $perpage;
$slice = array_slice($filtered, $offset, $perpage);

echo \quizaccess_webcamguard\output\attempt_detail::render_events($slice, $context->id);

// Pagination.
if ($pages > 1) {
    echo html_writer::start_div('d-flex justify-content-center mt-3');
    echo html_writer::start_tag('nav', ['aria-label' => get_string('pagination', 'core')]);
    echo html_writer::start_tag('ul', ['class' => 'pagination pagination-sm mb-0']);
    for ($i = 0; $i < $pages; $i++) {
        $purl = new moodle_url('/mod/quiz/accessrule/webcamguard/attempt.php', [
            'cmid' => $cmid,
            'attemptid' => $attempt->id,
            'page' => $i,
            'filtertype' => $filtertype,
            'filterseverity' => $filterseverity,
        ]);
        $link = html_writer::link($purl, $i + 1, [
            'class' => 'page-link' . ($i === $page ? ' active' : ''),
        ]);
        echo html_writer::tag('li', $link, ['class' => 'page-item' . ($i === $page ? ' active' : '')]);
    }
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('nav');
    echo html_writer::end_div();
}
echo $OUTPUT->footer();
