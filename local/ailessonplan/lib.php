<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Library functions for AI Lesson Plan plugin.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add AI Lesson Plan link to course navigation.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 * @return void
 */
function local_ailessonplan_extend_navigation_course($navigation, $course, $context) {
    if (!has_capability('local/ailessonplan:generate', $context)) {
        return;
    }

    $url = new moodle_url('/local/ailessonplan/index.php', ['courseid' => $course->id]);
    $node = navigation_node::create(
        get_string('pluginname', 'local_ailessonplan'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'ailessonplan',
        new pix_icon('i/report', '')
    );
    $navigation->add_node($node);
}
