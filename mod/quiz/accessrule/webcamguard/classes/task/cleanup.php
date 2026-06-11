<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Cleanup old Webcam Guard evidence.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_webcamguard\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled cleanup task.
 */
class cleanup extends \core\task\scheduled_task {
    /** Retention in days. */
    const RETENTION_DAYS = 30;

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskcleanup', 'quizaccess_webcamguard');
    }

    /**
     * Execute cleanup.
     */
    public function execute() {
        global $DB;

        $cutoff = time() - (self::RETENTION_DAYS * DAYSECS);
        $fs = get_file_storage();

        $events = $DB->get_records_select('quizaccess_wg_events', 'timecreated < :cutoff', ['cutoff' => $cutoff], '', 'id, cmid');
        foreach ($events as $event) {
            try {
                $context = \context_module::instance($event->cmid, IGNORE_MISSING);
                if ($context) {
                    $fs->delete_area_files($context->id, 'quizaccess_webcamguard', 'snapshot', $event->id);
                }
            } catch (\Exception $e) {
                mtrace('Webcam Guard cleanup: could not delete files for event ' . $event->id . ': ' . $e->getMessage());
            }
        }

        $DB->delete_records_select('quizaccess_wg_events', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        $DB->delete_records_select('quizaccess_wg_reviews', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        $DB->delete_records_select('quizaccess_wg_live', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
    }
}
