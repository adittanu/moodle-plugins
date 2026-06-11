<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Generate lesson plan page.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

use local_ailessonplan\api\mastra_client;
use local_ailessonplan\context_builder;
use local_ailessonplan\form\generate_form;
use local_ailessonplan\plan_renderer;

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/ailessonplan:generate', $context);

$PAGE->set_url('/local/ailessonplan/generate.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('generateplan', 'local_ailessonplan'));
$PAGE->set_heading($course->fullname);

if (optional_param('action', '', PARAM_ALPHA) === 'save' && confirm_sesskey()) {
    $planjson = required_param('planjson', PARAM_RAW);
    $plan = json_decode($planjson, true);
    if (!is_array($plan)) {
        throw new moodle_exception('invalidjson', 'local_ailessonplan');
    }

    $record = (object)[
        'userid' => $USER->id,
        'courseid' => $courseid,
        'title' => core_text::substr((string)($plan['course_title'] ?? $plan['title'] ?? get_string('pluginname', 'local_ailessonplan')), 0, 255),
        'topic' => optional_param('topic', '', PARAM_RAW),
        'language' => optional_param('language', 'indonesian', PARAM_ALPHANUMEXT),
        'meetings' => optional_param('meetings', 16, PARAM_INT),
        'planjson' => json_encode($plan, JSON_UNESCAPED_UNICODE),
        'timecreated' => time(),
        'timemodified' => time(),
    ];

    $id = $DB->insert_record('local_ailessonplan', $record);
    redirect(new moodle_url('/local/ailessonplan/view.php', ['id' => $id]), get_string('savedraftsuccess', 'local_ailessonplan'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$apikey = get_config('local_ailessonplan', 'apikey') ?: get_config('local_daliwidget', 'apikey');
if (empty($apikey)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('noapikey', 'local_ailessonplan'), 'error');
    echo $OUTPUT->footer();
    exit;
}

$mform = new generate_form(null, ['courseid' => $courseid]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/ailessonplan/index.php', ['courseid' => $courseid]));
} else if ($data = $mform->get_data()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('generating', 'local_ailessonplan'));

    try {
        $ragcontext = '';
        if (!empty($data->includesource) && !empty($data->knowledgesource)) {
            echo $OUTPUT->notification(get_string('retrievingsource', 'local_ailessonplan'), 'info');
            $source = context_builder::find_synced_knowledge_source($courseid, (string)$data->knowledgesource);
            $query = trim((string)($data->topic ?? '')) ?: ('lesson plan for ' . $course->fullname);
            $ragcontext = context_builder::retrieve_knowledge_source_context($source, $query);
            echo $OUTPUT->notification(get_string('source_retrieved', 'local_ailessonplan', strlen($ragcontext)), 'success');
        }

        $coursecontext = context_builder::build_course_context($course, [
            'include_metadata' => !empty($data->includemetadata),
            'include_sections' => !empty($data->includesections),
            'include_activities' => !empty($data->includeactivities),
        ]);

        $payload = [
            'topic' => trim((string)($data->topic ?? '')),
            'level' => trim((string)($data->level ?? '')),
            'duration' => trim((string)($data->duration ?? '')),
            'meetings' => (int)$data->meetings,
            'language' => (string)$data->language,
            'activity_density' => (string)($data->activitydensity ?? 'balanced'),
            'curriculum_reference' => trim((string)($data->curriculumreference ?? '')),
            'additionalinstructions' => trim((string)($data->additionalinstructions ?? '')),
            'course_context' => $coursecontext,
            'rag_context' => $ragcontext,
        ];

        $client = new mastra_client();
        $plan = $client->generate_lesson_plan($payload);

        echo $OUTPUT->notification(get_string('previewtitle', 'local_ailessonplan'), 'info');
        echo plan_renderer::render_plan($plan);

        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/local/ailessonplan/generate.php', ['courseid' => $courseid]))->out(false),
            'class' => 'mt-3',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'topic', 'value' => $data->topic ?? '']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'language', 'value' => $data->language]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'meetings', 'value' => (int)$data->meetings]);
        echo html_writer::tag('textarea', s(json_encode($plan, JSON_UNESCAPED_UNICODE)), [
            'name' => 'planjson',
            'style' => 'display:none',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('saveplan', 'local_ailessonplan')]);
        echo ' ' . html_writer::link(new moodle_url('/local/ailessonplan/generate.php', ['courseid' => $courseid]), get_string('generateplan', 'local_ailessonplan'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');
    } catch (Exception $e) {
        echo $OUTPUT->notification(get_string('apierror', 'local_ailessonplan', $e->getMessage()), 'error');
        echo $OUTPUT->single_button(new moodle_url('/local/ailessonplan/generate.php', ['courseid' => $courseid]), get_string('tryagain', 'core'), 'get');
    }

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('generateplan', 'local_ailessonplan'));
$mform->display();
echo $OUTPUT->footer();
