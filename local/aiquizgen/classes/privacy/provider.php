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
 * Privacy provider for AI Quiz Generator plugin.
 *
 * @package    local_aiquizgen
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiquizgen\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

class provider implements 
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_aiquizgen_log',
            [
                'userid' => 'privacy:metadata:local_aiquizgen_log:userid',
                'topic' => 'privacy:metadata:local_aiquizgen_log:topic',
                'questioncount' => 'privacy:metadata:local_aiquizgen_log:questioncount',
                'timecreated' => 'privacy:metadata:local_aiquizgen_log:timecreated',
            ],
            'privacy:metadata:local_aiquizgen_log'
        );

        $collection->add_external_location_link('openai', [
            'topic' => 'privacy:metadata:openai:topic',
            'instructions' => 'privacy:metadata:openai:instructions',
        ], 'privacy:metadata:openai');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course} c ON c.id = ctx.instanceid AND ctx.contextlevel = :contextcourse
                  JOIN {local_aiquizgen_log} l ON l.courseid = c.id
                 WHERE l.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextcourse' => CONTEXT_COURSE,
            'userid' => $userid
        ]);

        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $logs = $DB->get_records('local_aiquizgen_log', [
                'userid' => $user->id,
                'courseid' => $context->instanceid
            ]);

            if (!empty($logs)) {
                $data = [];
                foreach ($logs as $log) {
                    $data[] = [
                        'topic' => $log->topic,
                        'questioncount' => $log->questioncount,
                        'questiontype' => $log->questiontype,
                        'success' => $log->success ? 'Yes' : 'No',
                        'timecreated' => transform::datetime($log->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_aiquizgen')],
                    (object)['logs' => $data]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $DB->delete_records('local_aiquizgen_log', ['courseid' => $context->instanceid]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $DB->delete_records('local_aiquizgen_log', [
                'userid' => $userid,
                'courseid' => $context->instanceid
            ]);
        }
    }
}
