<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Library callbacks for quizaccess_webcamguard.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve Webcam Guard snapshot files.
 *
 * @param stdClass $course Course.
 * @param stdClass $cm Course module.
 * @param context $context Context.
 * @param string $filearea File area.
 * @param array $args URL args.
 * @param bool $forcedownload Forced download.
 * @param array $options Options.
 * @return bool
 */
function quizaccess_webcamguard_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload,
        array $options = []) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE || $filearea !== 'snapshot') {
        return false;
    }

    require_login($course, true, $cm);
    if (!has_capability('quizaccess/webcamguard:viewreport', $context)) {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    if (!$itemid || !$filename) {
        return false;
    }

    $event = $DB->get_record('quizaccess_wg_events', ['id' => $itemid, 'cmid' => $cm->id]);
    if (!$event) {
        return false;
    }

    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'quizaccess_webcamguard', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Add Webcam Guard dashboard link to course navigation.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $course The course.
 * @param context $context Course context.
 */
function quizaccess_webcamguard_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('quizaccess/webcamguard:viewreport', $context)) {
        $url = new moodle_url('/mod/quiz/accessrule/webcamguard/dashboard.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('dashboardtitle', 'quizaccess_webcamguard'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'webcamguard_dashboard',
            new pix_icon('i/report', '')
        );
    }
}
