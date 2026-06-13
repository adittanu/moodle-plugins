<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Publish one edited lesson-plan section.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
@ini_set('display_errors', '0');

require_once(__DIR__ . '/../../config.php');

use local_ailessonplan\publisher;

$id = required_param('id', PARAM_INT);
$sectionindex = required_param('sectionindex', PARAM_INT);
$targetsection = required_param('targetsection', PARAM_INT);
$sectionjson = required_param('sectionjson', PARAM_RAW);
$selectionsent = optional_param('selectionsent', 0, PARAM_BOOL);

require_sesskey();

$record = $DB->get_record('local_ailessonplan', ['id' => $id], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $record->courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('moodle/course:manageactivities', $context);

$section = json_decode($sectionjson, true);
if (!is_array($section)) {
    throw new moodle_exception('invalidjson', 'local_ailessonplan');
}

try {
    $selectedactivities = $selectionsent ? optional_param_array('selectedactivities', [], PARAM_ALPHANUMEXT) : null;
    $result = publisher::publish_single_section($record, $course, $section, $targetsection, $selectedactivities);
} catch (\Throwable $e) {
    header('Content-Type: application/json; charset=utf-8', true, 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$plan = json_decode($record->planjson, true);
if (is_array($plan)) {
    $sections = publisher::plan_sections($plan);
    $sectiontosave = $section;
    unset($sectiontosave['_target_sectionnum']);
    if (isset($sections[$sectionindex])) {
        $sections[$sectionindex] = $sectiontosave;
    } else {
        $sections[] = $sectiontosave;
    }
    foreach ($sections as $key => $savedsection) {
        unset($savedsection['_target_sectionnum']);
        $sections[$key] = $savedsection;
    }
    $plan['sections'] = $sections;
    $plan['course_title'] = (string)($plan['course_title'] ?? $plan['title'] ?? get_string('pluginname', 'local_ailessonplan'));
    $plan['title'] = $plan['course_title'];
    $record->planjson = json_encode($plan, JSON_UNESCAPED_UNICODE);
}

if (!empty($result['cmid'])) {
    $record->publishedcmid = $result['cmid'];
}
$record->publishedat = time();
$record->timemodified = time();
$DB->update_record('local_ailessonplan', $record);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'sectionindex' => $sectionindex,
    'targetsection' => $targetsection,
    'result' => $result,
], JSON_UNESCAPED_UNICODE);
