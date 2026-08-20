<?php
// This file is part of Moodle - http://moodle.org/.
namespace report_dalireport\privacy;

defined('MOODLE_INTERNAL') || die();

/** Declares that this plugin stores no personal data in Moodle. */
class provider implements \core_privacy\local\metadata\null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
