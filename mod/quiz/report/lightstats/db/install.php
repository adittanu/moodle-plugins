<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

function xmldb_quiz_lightstats_install() {
    global $DB;
    if (!$DB->record_exists('quiz_reports', ['name' => 'lightstats'])) {
        $DB->insert_record('quiz_reports', (object)[
            'name' => 'lightstats',
            'displayorder' => 7000,
            'capability' => 'mod/quiz:viewreports',
        ]);
    }
}
