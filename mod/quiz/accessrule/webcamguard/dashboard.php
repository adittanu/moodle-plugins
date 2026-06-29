<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Webcam Guard course dashboard — overview of all quizzes with webcamguard.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('quizaccess/webcamguard:viewreport', $context);

$PAGE->set_url('/mod/quiz/accessrule/webcamguard/dashboard.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('dashboardtitle', 'quizaccess_webcamguard'));
$PAGE->set_heading(format_string($course->fullname));

// Find all quizzes in this course that have webcamguard enabled.
$sql = "SELECT q.id AS quizid, q.name AS quizname, c.id AS cmid,
               COALESCE(evts.eventcount, 0) AS eventcount,
               COALESCE(evts.violationcount, 0) AS violationcount,
               COALESCE(evts.violatedattempts, 0) AS violatedattempts
          FROM {quizaccess_wg_config} cfg
          JOIN {quiz} q ON q.id = cfg.quizid
          JOIN {course_modules} c ON c.instance = q.id AND c.module = (SELECT id FROM {modules} WHERE name = 'quiz')
     LEFT JOIN (
               SELECT e.quizid,
                      COUNT(*) AS eventcount,
                      SUM(CASE WHEN e.severity = 'violation' THEN 1 ELSE 0 END) AS violationcount,
                      COUNT(DISTINCT CASE WHEN e.severity = 'violation' THEN e.attemptid END) AS violatedattempts
                 FROM {quizaccess_wg_events} e
             GROUP BY e.quizid
         ) evts ON evts.quizid = q.id
         WHERE cfg.enabled = 1 AND q.course = :courseid
      ORDER BY q.name";

$quizzes = $DB->get_records_sql($sql, ['courseid' => $courseid]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboardtitle', 'quizaccess_webcamguard'), 2);

if (empty($quizzes)) {
    echo html_writer::div(get_string('dashboardnoquizzes', 'quizaccess_webcamguard'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('quizname', 'quizaccess_webcamguard'),
        get_string('totalevents', 'quizaccess_webcamguard'),
        get_string('totalviolations', 'quizaccess_webcamguard'),
        get_string('violatedattempts', 'quizaccess_webcamguard'),
        '',
    ];
    $table->data = [];
    $table->class = 'generaltable table-striped';

    foreach ($quizzes as $row) {
        $reporturl = new moodle_url('/mod/quiz/accessrule/webcamguard/report.php', ['cmid' => $row->cmid]);
        $link = html_writer::link($reporturl, get_string('viewreport', 'quizaccess_webcamguard'));
        $table->data[] = [
            s($row->quizname),
            (int)$row->eventcount,
            (int)$row->violationcount,
            (int)$row->violatedattempts,
            $link,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
