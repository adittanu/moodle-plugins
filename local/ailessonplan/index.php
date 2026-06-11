<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Saved AI lesson plans index.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/ailessonplan:generate', $context);

$PAGE->set_url('/local/ailessonplan/index.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_ailessonplan'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_ailessonplan'));

echo html_writer::div(
    $OUTPUT->single_button(new moodle_url('/local/ailessonplan/generate.php', ['courseid' => $courseid]), get_string('generateplan', 'local_ailessonplan'), 'get') .
    $OUTPUT->single_button(new moodle_url('/course/view.php', ['id' => $courseid]), get_string('backtocourse', 'local_ailessonplan'), 'get'),
    'mb-3'
);

$plans = $DB->get_records('local_ailessonplan', ['courseid' => $courseid], 'timecreated DESC');

if (empty($plans)) {
    echo $OUTPUT->notification(get_string('noplan', 'local_ailessonplan'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [get_string('plantitle', 'local_ailessonplan'), get_string('topic', 'local_ailessonplan'), get_string('meetings', 'local_ailessonplan'), get_string('timecreated', 'core'), ''];
$table->attributes['class'] = 'generaltable table table-striped';

foreach ($plans as $plan) {
    $viewurl = new moodle_url('/local/ailessonplan/view.php', ['id' => $plan->id]);
    $table->data[] = [
        html_writer::link($viewurl, format_string($plan->title)),
        s(core_text::substr(strip_tags($plan->topic ?? ''), 0, 120)),
        (int)$plan->meetings,
        userdate($plan->timecreated),
        $OUTPUT->single_button($viewurl, get_string('viewplan', 'local_ailessonplan'), 'get'),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
