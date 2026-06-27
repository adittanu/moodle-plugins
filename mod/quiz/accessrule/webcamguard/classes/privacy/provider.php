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
 * Privacy metadata for quizaccess_webcamguard.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_webcamguard\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy metadata and request provider.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

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

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing contexts with user data.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {quizaccess_wg_events} e ON e.cmid = cm.id
                 WHERE e.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ]);

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {quizaccess_wg_reviews} r ON r.userid = :userid
                  JOIN {quiz_attempts} qa ON qa.id = r.attemptid
                  JOIN {quiz} q ON q.id = qa.quiz
                  JOIN {course_modules} cm2 ON cm2.instance = q.id AND cm2.module = :modid
                  JOIN {context} ctx2 ON ctx2.instanceid = cm2.id AND ctx2.contextlevel = :contextlevel2
                 WHERE ctx2.id = ctx.id";

        $modid = \quizaccess_webcamguard\privacy\provider::get_quiz_module_id();
        $contextlist->add_from_sql($sql, [
            'userid' => $userid,
            'modid' => $modid,
            'contextlevel' => CONTEXT_MODULE,
            'contextlevel2' => CONTEXT_MODULE,
        ]);

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {quizaccess_wg_live} l ON l.cmid = cm.id
                 WHERE l.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users.
     */
    public static function get_users_in_context(userlist $userlist) : void {
        $sql = "SELECT e.userid
                  FROM {quizaccess_wg_events} e
                  JOIN {course_modules} cm ON cm.id = e.cmid
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, ['cmid' => $userlist->get_context()->instanceid]);

        $sql = "SELECT l.userid
                  FROM {quizaccess_wg_live} l
                  JOIN {course_modules} cm ON cm.id = l.cmid
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, ['cmid' => $userlist->get_context()->instanceid]);

        $sql = "SELECT r.userid
                  FROM {quizaccess_wg_reviews} r
                  JOIN {quiz_attempts} qa ON qa.id = r.attemptid
                  JOIN {quiz} q ON q.id = qa.quiz
                  JOIN {course_modules} cm ON cm.instance = q.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, ['cmid' => $userlist->get_context()->instanceid]);
    }

    /**
     * Export personal data for the given approved_contextlist.
     *
     * @param approved_contextlist $contextlist The approved contexts to export for.
     */
    public static function export_user_data(approved_contextlist $contextlist) : void {
        global $DB;

        if (empty($contextlist)) {
            return;
        }

        $fs = get_file_storage();

        foreach ($contextlist as $contextid) {
            $context = \context::instance_by_id($contextid);
            $cmid = $context->instanceid;

            // Export events.
            $events = $DB->get_records('quizaccess_wg_events', ['cmid' => $cmid], 'timecreated ASC');
            foreach ($events as $event) {
                $data = (object)[
                    'eventtype' => $event->eventtype,
                    'durationms' => $event->durationms,
                    'severity' => $event->severity,
                    'clienttime' => $event->clienttime,
                    'metadata' => $event->metadata,
                    'timecreated' => \core_privacy\local\request\transform::datetime($event->timecreated),
                ];
                $subcontext = [
                    \core_privacy\local\request\transform::string('webcamguard'),
                    \core_privacy\local\request\transform::string('events'),
                ];
                writer::with_context($context)->export_data(
                    array_merge($subcontext, ['event_' . $event->id]),
                    $data
                );

                // Export snapshot file if present.
                if ($event->hassnapshot) {
                    $files = $fs->get_area_files($context->id, 'quizaccess_webcamguard', 'snapshot', $event->id, 'filename', false);
                    if (!empty($files)) {
                        writer::with_context($context)->export_area_files(
                            $subcontext,
                            'snapshot',
                            'quizaccess_webcamguard',
                            'snapshot',
                            $event->id
                        );
                    }
                }
            }

            // Export reviews.
            $reviews = $DB->get_records_sql(
                "SELECT r.*
                   FROM {quizaccess_wg_reviews} r
                   JOIN {quiz_attempts} qa ON qa.id = r.attemptid
                   JOIN {quiz} q ON q.id = qa.quiz
                   JOIN {course_modules} cm ON cm.instance = q.id
                  WHERE cm.id = :cmid",
                ['cmid' => $cmid]
            );
            foreach ($reviews as $review) {
                $data = (object)[
                    'status' => $review->status,
                    'reviewcomment' => $review->reviewcomment,
                ];
                $subcontext = [
                    \core_privacy\local\request\transform::string('webcamguard'),
                    \core_privacy\local\request\transform::string('reviews'),
                ];
                writer::with_context($context)->export_data(
                    array_merge($subcontext, ['review_' . $review->id]),
                    $data
                );
            }

            // Export live monitoring records.
            $lives = $DB->get_records('quizaccess_wg_live', ['cmid' => $cmid], 'timecreated ASC');
            foreach ($lives as $live) {
                $data = (object)[
                    'status' => $live->status,
                    'roomname' => $live->roomname,
                    'timecreated' => \core_privacy\local\request\transform::datetime($live->timecreated),
                ];
                $subcontext = [
                    \core_privacy\local\request\transform::string('webcamguard'),
                    \core_privacy\local\request\transform::string('live'),
                ];
                writer::with_context($context)->export_data(
                    array_merge($subcontext, ['live_' . $live->id]),
                    $data
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) : void {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cmid = $context->instanceid;
        $fs = get_file_storage();

        // Delete event snapshot files.
        $events = $DB->get_records('quizaccess_wg_events', ['cmid' => $cmid], '', 'id, hassnapshot');
        foreach ($events as $event) {
            if ($event->hassnapshot) {
                $fs->delete_area_files($context->id, 'quizaccess_webcamguard', 'snapshot', $event->id);
            }
        }

        $DB->delete_records('quizaccess_wg_events', ['cmid' => $cmid]);
        $DB->delete_records('quizaccess_wg_live', ['cmid' => $cmid]);

        // Delete reviews linked to this module's quiz attempts.
        $DB->delete_records_sql(
            "DELETE FROM {quizaccess_wg_reviews}
              WHERE attemptid IN (
                  SELECT qa.id
                    FROM {quiz_attempts} qa
                    JOIN {quiz} q ON q.id = qa.quiz
                    JOIN {course_modules} cm ON cm.instance = q.id
                   WHERE cm.id = :cmid
              )",
            ['cmid' => $cmid]
        );
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) : void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cmid = $context->instanceid;
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        $fs = get_file_storage();
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete event snapshot files.
        $params = $inparams + ['cmid' => $cmid];
        $events = $DB->get_records_select('quizaccess_wg_events',
            "cmid = :cmid AND userid $insql", $params, '', 'id, hassnapshot');
        foreach ($events as $event) {
            if ($event->hassnapshot) {
                $fs->delete_area_files($context->id, 'quizaccess_webcamguard', 'snapshot', $event->id);
            }
        }

        $DB->delete_records_select('quizaccess_wg_events', "cmid = :cmid AND userid $insql", $params);
        $DB->delete_records_select('quizaccess_wg_live', "cmid = :cmid AND userid $insql", $params);

        // Delete reviews for these users linked to this module's quiz attempts.
        [$userinsql, $userinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $DB->delete_records_sql(
            "DELETE FROM {quizaccess_wg_reviews}
              WHERE userid $userinsql
                AND attemptid IN (
                    SELECT qa.id
                      FROM {quiz_attempts} qa
                      JOIN {quiz} q ON q.id = qa.quiz
                      JOIN {course_modules} cm ON cm.instance = q.id
                     WHERE cm.id = :cmid
                )",
            $userinparams + ['cmid' => $cmid]
        );
    }

    /**
     * Get the quiz module id.
     *
     * @return int Module id.
     */
    private static function get_quiz_module_id() : int {
        static $modid = null;
        if ($modid === null) {
            global $DB;
            $modid = (int)$DB->get_field('modules', 'id', ['name' => 'quiz']);
        }
        return $modid;
    }
}
