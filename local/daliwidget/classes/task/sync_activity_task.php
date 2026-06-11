<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Adhoc task: sync a single activity to Dali Knowledge Base.
 *
 * @package     local_daliwidget
 * @copyright   2024 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_daliwidget\task;

defined('MOODLE_INTERNAL') || die();

use local_daliwidget\sync_helper;

/**
 * Adhoc task that uploads one activity's content to the Dali Knowledge Base.
 *
 * Custom data (set before queuing):
 *   - courseid (int)
 *   - cmid     (int)
 *   - queueid  (int) — row ID in local_daliwidget_sync_queue
 */
class sync_activity_task extends \core\task\adhoc_task {

    /**
     * Return the name shown in task admin UI.
     */
    public function get_name(): string {
        return get_string('task_sync_activity', 'local_daliwidget');
    }

    /**
     * Execute the task.
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $courseid = (int) $data->courseid;
        $cmid     = (int) $data->cmid;
        $queueid  = (int) $data->queueid;

        $now = time();

        // Mark as processing.
        $DB->update_record('local_daliwidget_sync_queue', (object)[
            'id'           => $queueid,
            'status'       => 'processing',
            'timemodified' => $now,
        ]);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        try {
            $result = sync_helper::sync_activity($course, $cmid);

            if (!empty($result['success'])) {
                $notemessage = trim((string) ($result['warning'] ?? ''));
                $DB->update_record('local_daliwidget_sync_queue', (object)[
                    'id'           => $queueid,
                    'status'       => 'done',
                    'errormessage' => $notemessage !== '' ? $notemessage : null,
                    'timemodified' => time(),
                ]);
            } else {
                $errormsg = $result['error'] ?? 'Unknown error';
                $DB->update_record('local_daliwidget_sync_queue', (object)[
                    'id'           => $queueid,
                    'status'       => 'failed',
                    'errormessage' => $errormsg,
                    'timemodified' => time(),
                ]);
            }
        } catch (\Throwable $e) {
            $DB->update_record('local_daliwidget_sync_queue', (object)[
                'id'           => $queueid,
                'status'       => 'failed',
                'errormessage' => $e->getMessage(),
                'timemodified' => time(),
            ]);
            // Re-throw so Moodle task runner logs it and can retry.
            throw $e;
        }
    }
}
