<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Privacy metadata for quizaccess_webcamguard.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_webcamguard\privacy;

use core_privacy\local\metadata\collection;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy metadata provider.
 */
class provider implements \core_privacy\local\metadata\provider {
    /**
     * Describe stored personal data.
     *
     * @param collection $items Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $items) : collection {
        $items->add_database_table('quizaccess_wg_events', [
            'courseid' => 'privacy:metadata:quizaccess_wg_events:courseid',
            'cmid' => 'privacy:metadata:quizaccess_wg_events:cmid',
            'quizid' => 'privacy:metadata:quizaccess_wg_events:quizid',
            'attemptid' => 'privacy:metadata:quizaccess_wg_events:attemptid',
            'userid' => 'privacy:metadata:quizaccess_wg_events:userid',
            'eventtype' => 'privacy:metadata:quizaccess_wg_events:eventtype',
            'metadata' => 'privacy:metadata:quizaccess_wg_events:metadata',
            'timecreated' => 'privacy:metadata:quizaccess_wg_events:timecreated',
        ], 'privacy:metadata:quizaccess_wg_events');

        $items->add_database_table('quizaccess_wg_reviews', [
            'attemptid' => 'privacy:metadata:quizaccess_wg_reviews:attemptid',
            'userid' => 'privacy:metadata:quizaccess_wg_reviews:userid',
            'status' => 'privacy:metadata:quizaccess_wg_reviews:status',
            'reviewedby' => 'privacy:metadata:quizaccess_wg_reviews:reviewedby',
            'reviewcomment' => 'privacy:metadata:quizaccess_wg_reviews:reviewcomment',
        ], 'privacy:metadata:quizaccess_wg_reviews');

        $items->add_database_table('quizaccess_wg_live', [
            'courseid' => 'privacy:metadata:quizaccess_wg_live:courseid',
            'cmid' => 'privacy:metadata:quizaccess_wg_live:cmid',
            'quizid' => 'privacy:metadata:quizaccess_wg_live:quizid',
            'attemptid' => 'privacy:metadata:quizaccess_wg_live:attemptid',
            'userid' => 'privacy:metadata:quizaccess_wg_live:userid',
            'requestedby' => 'privacy:metadata:quizaccess_wg_live:requestedby',
            'roomname' => 'privacy:metadata:quizaccess_wg_live:roomname',
            'status' => 'privacy:metadata:quizaccess_wg_live:status',
            'timecreated' => 'privacy:metadata:quizaccess_wg_live:timecreated',
        ], 'privacy:metadata:quizaccess_wg_live');

        $items->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');
        return $items;
    }
}
