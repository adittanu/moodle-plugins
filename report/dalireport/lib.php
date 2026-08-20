<?php
// This file is part of Moodle - http://moodle.org/.
defined('MOODLE_INTERNAL') || die();

/** Add the report to course navigation for authorised users. */
function report_dalireport_extend_navigation_course($navigation, $course, $context): void {
    if (!has_capability('report/dalireport:view', $context)) {
        return;
    }
    $navigation->add(
        get_string('pluginname', 'report_dalireport'),
        new moodle_url('/report/dalireport/index.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'report_dalireport',
        new pix_icon('i/report', '')
    );
}
