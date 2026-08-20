<?php
// This file is part of Moodle - http://moodle.org/.
require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
if ($courseid) {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($courseid);
    require_capability('report/dalireport:view', $context);
    $heading = $course->fullname;
} else {
    require_login();
    $context = context_system::instance();
    require_capability('report/dalireport:viewsite', $context);
    $heading = get_string('pluginname', 'report_dalireport');
}

$PAGE->set_url(new moodle_url('/report/dalireport/index.php', $courseid ? ['courseid' => $courseid] : []));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'report_dalireport'));
$PAGE->set_heading($heading);
$PAGE->set_pagelayout('report');

try {
    $embedurl = (new \report_dalireport\api_client())->get_embed_url($courseid ?: null);
} catch (moodle_exception $exception) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification($exception->getMessage(), \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo html_writer::tag('iframe', '', [
    'src' => $embedurl,
    'title' => get_string('pluginname', 'report_dalireport'),
    'style' => 'width:100%;height:900px;border:0;',
    'loading' => 'lazy',
]);
echo $OUTPUT->footer();
