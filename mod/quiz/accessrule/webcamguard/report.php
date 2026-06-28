<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Webcam Guard teacher report.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('quizaccess/webcamguard:viewreport', $context);

$url = new moodle_url('/mod/quiz/accessrule/webcamguard/report.php', ['cmid' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reporttitle', 'quizaccess_webcamguard'));
$PAGE->set_heading(format_string($quiz->name));

$allowedstatuses = ['', 'pending', 'cleared', 'suspicious'];
if (!in_array($status, $allowedstatuses, true)) {
    $status = '';
}

$params = ['quizid' => $quiz->id, 'evquizid' => $quiz->id];
$statussql = '';
if ($status !== '') {
    $statussql = ' AND r.status = :status';
    $params['status'] = $status;
}

// Risk score SQL below uses weights matching quizaccess_webcamguard::EVENT_WEIGHTS.
$namefields = get_all_user_name_fields(true, 'u');
$sql = "SELECT r.id, r.attemptid, r.quizid, r.userid, r.status, qa.attempt,
               $namefields,
               COALESCE(ev.eventcount, 0) AS eventcount,
               COALESCE(ev.violationcount, 0) AS violationcount,
               COALESCE(ev.riskscore, 0) AS riskscore
          FROM {quizaccess_wg_reviews} r
          JOIN {quiz_attempts} qa ON qa.id = r.attemptid
          JOIN {user} u ON u.id = r.userid
     LEFT JOIN (
               SELECT attemptid,
                      COUNT(1) AS eventcount,
                      SUM(CASE WHEN severity = 'violation' THEN 1 ELSE 0 END) AS violationcount,
                      SUM(CASE WHEN severity = 'violation' THEN
                          CASE eventtype
                              WHEN 'no_face' THEN 2
                              WHEN 'multiple_faces' THEN 4
                              WHEN 'window_blur' THEN 3
                              WHEN 'camera_stopped' THEN 5
                              WHEN 'camera_error' THEN 3
                              WHEN 'identity_check' THEN 4
                              ELSE 1
                          END
                      ELSE 0 END) AS riskscore
                 FROM {quizaccess_wg_events}
                WHERE quizid = :evquizid
             GROUP BY attemptid
               ) ev ON ev.attemptid = r.attemptid
         WHERE r.quizid = :quizid $statussql
      ORDER BY r.timemodified DESC";
$rows = $DB->get_records_sql($sql, $params);

$attemptviolationparams = ['quizid' => $quiz->id];
$attemptviolationstatussql = '';
if ($status !== '') {
    $attemptviolationstatussql = ' AND r.status = :status';
    $attemptviolationparams['status'] = $status;
}

$attemptviolationtypes = $DB->get_records_sql(
    "SELECT " . $DB->sql_concat('e.attemptid', "'-'", 'e.eventtype') . " AS uniqid,
            e.attemptid,
            e.eventtype,
            COUNT(1) AS violationcount
       FROM {quizaccess_wg_reviews} r
       JOIN {quizaccess_wg_events} e ON e.attemptid = r.attemptid AND e.quizid = r.quizid
      WHERE r.quizid = :quizid
        AND e.severity = 'violation'
            $attemptviolationstatussql
   GROUP BY e.attemptid, e.eventtype
   ORDER BY e.attemptid ASC, violationcount DESC, e.eventtype ASC",
    $attemptviolationparams
);

$topbyattempt = [];
foreach ($attemptviolationtypes as $type) {
    if (!isset($topbyattempt[$type->attemptid])) {
        $topbyattempt[$type->attemptid] = $type;
    }
}
foreach ($rows as $row) {
    $row->topviolationtype = null;
    $row->topviolationcount = 0;
    if (isset($topbyattempt[$row->attemptid])) {
        $row->topviolationtype = $topbyattempt[$row->attemptid]->eventtype;
        $row->topviolationcount = (int)$topbyattempt[$row->attemptid]->violationcount;
    }
}

$summaryparams = ['quizid' => $quiz->id];
$summarystatussql = '';
if ($status !== '') {
    $summarystatussql = ' AND r.status = :status';
    $summaryparams['status'] = $status;
}

$summary = $DB->get_record_sql(
    "SELECT COUNT(e.id) AS eventcount,
            SUM(CASE WHEN e.severity = 'violation' THEN 1 ELSE 0 END) AS violationcount,
            COUNT(DISTINCT CASE WHEN e.severity = 'violation' THEN e.attemptid ELSE NULL END) AS violatedattempts
       FROM {quizaccess_wg_reviews} r
  LEFT JOIN {quizaccess_wg_events} e ON e.attemptid = r.attemptid AND e.quizid = r.quizid
      WHERE r.quizid = :quizid $summarystatussql",
    $summaryparams
);

$violationtypes = $DB->get_records_sql(
    "SELECT e.eventtype, COUNT(1) AS violationcount
       FROM {quizaccess_wg_reviews} r
       JOIN {quizaccess_wg_events} e ON e.attemptid = r.attemptid AND e.quizid = r.quizid
      WHERE r.quizid = :quizid
        AND e.severity = 'violation'
            $summarystatussql
   GROUP BY e.eventtype
   ORDER BY violationcount DESC, e.eventtype ASC",
    $summaryparams
);

$webcamguardconfig = $DB->get_record('quizaccess_wg_config', ['quizid' => $quiz->id, 'enabled' => 1]);
$liveenabled = $webcamguardconfig
    && !empty($webcamguardconfig->liveenabled)
    && \quizaccess_webcamguard\livekit\token_service::is_configured();
$livecandidates = [];

if ($liveenabled) {
    $livecandidates = quizaccess_webcamguard_get_live_candidates($quiz, $cmid);
}

$options = [
    '' => get_string('all'),
    'pending' => get_string('pending', 'quizaccess_webcamguard'),
    'cleared' => get_string('cleared', 'quizaccess_webcamguard'),
    'suspicious' => get_string('suspicious', 'quizaccess_webcamguard'),
];

$form = html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-3']);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cmid]);
$form .= html_writer::label(get_string('status', 'quizaccess_webcamguard'), 'id_status', false, ['class' => 'mr-2']);
$form .= html_writer::select($options, 'status', $status, false, ['id' => 'id_status']);
$form .= html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary ml-2', 'value' => get_string('filter')]);
$form .= html_writer::end_tag('form');

require_once(__DIR__ . '/classes/output/report_page.php');

$output = $PAGE->get_renderer('core');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reporttitle', 'quizaccess_webcamguard'));
echo $form;
if ($liveenabled) {
    echo \quizaccess_webcamguard\output\report_page::render_live_monitor();
    $PAGE->requires->js_call_amd('quizaccess_webcamguard/live_dashboard', 'init', [[
        'courseid' => (int)$course->id,
        'cmid' => (int)$cm->id,
        'quizid' => (int)$quiz->id,
        'scriptUrl' => $CFG->wwwroot . '/mod/quiz/accessrule/webcamguard/livekit/livekit-client.umd.js',
        'emptyImageUrl' => $CFG->wwwroot . '/mod/quiz/accessrule/webcamguard/pix/live-monitor-empty.png',
        'limit' => 20,
        'candidates' => $livecandidates,
        'strings' => [
            'idle' => get_string('livestatusidle', 'quizaccess_webcamguard'),
            'starting' => get_string('livestarting', 'quizaccess_webcamguard'),
            'waiting' => get_string('livewaitingstudent', 'quizaccess_webcamguard'),
            'connected' => get_string('livestudentconnected', 'quizaccess_webcamguard'),
            'stopped' => get_string('livestopped', 'quizaccess_webcamguard'),
            'failed' => get_string('livefailed', 'quizaccess_webcamguard'),
            'empty' => get_string('livenoactiveattempts', 'quizaccess_webcamguard'),
            'emptyTitle' => get_string('liveemptytitle', 'quizaccess_webcamguard'),
            'emptyBody' => get_string('liveemptybody', 'quizaccess_webcamguard'),
            'activeAttempts' => get_string('liveactiveattempts', 'quizaccess_webcamguard'),
            'pollFailed' => get_string('livepollfailed', 'quizaccess_webcamguard'),
        ],
    ]]);
}
echo \quizaccess_webcamguard\output\report_page::render_summary($summary, array_values($violationtypes));
echo \quizaccess_webcamguard\output\report_page::render(array_values($rows), $cmid);
echo $OUTPUT->footer();
