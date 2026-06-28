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
 * External API for sending warnings to participants.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_webcamguard\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * Send a warning message to a participant during live monitoring.
 */
class send_warning extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'quizid' => new external_value(PARAM_INT, 'Quiz id'),
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'message' => new external_value(PARAM_TEXT, 'Warning message', NULL_NOT_ALLOWED),
        ]);
    }

    /**
     * Execute send warning.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @param int $quizid Quiz id.
     * @param int $attemptid Attempt id.
     * @param string $message Warning message.
     * @return array
     */
    public static function execute($courseid, $cmid, $quizid, $attemptid, $message) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'quizid' => $quizid,
            'attemptid' => $attemptid,
            'message' => $message,
        ]);

        $cm = get_coursemodule_from_id('quiz', $params['cmid'], $params['courseid'], false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_login($params['courseid'], false, $cm);
        require_capability('quizaccess/webcamguard:viewreport', $context);

        $quiz = $DB->get_record('quiz', ['id' => $params['quizid'], 'course' => $params['courseid']], '*', MUST_EXIST);
        if ((int)$cm->instance !== (int)$quiz->id) {
            throw new \moodle_exception('invalidcoursemodule');
        }

        $attempt = $DB->get_record('quiz_attempts', [
            'id' => $params['attemptid'],
            'quiz' => $quiz->id,
        ], '*', MUST_EXIST);

        $message = clean_param($params['message'], PARAM_TEXT);
        if (empty($message)) {
            return ['success' => false];
        }

        // Find or create an active live row for this attempt.
        $now = time();
        [$insql, $inparams] = $DB->get_in_or_equal(['requested', 'active'], SQL_PARAMS_NAMED, 'livestatus');
        $live = $DB->get_records_select('quizaccess_wg_live',
            "attemptid = :attemptid AND expiresat > :now AND status $insql",
            $inparams + ['attemptid' => $attempt->id, 'now' => $now],
            'id DESC', '*', 0, 1);

        if ($live) {
            $live = reset($live);
            $live->warning = $message;
            $live->timemodified = $now;
            $DB->update_record('quizaccess_wg_live', $live);
        } else {
            // Create a short-lived live row just for the warning.
            $live = (object)[
                'courseid' => $params['courseid'],
                'cmid' => $params['cmid'],
                'quizid' => $params['quizid'],
                'attemptid' => $attempt->id,
                'userid' => $attempt->userid,
                'requestedby' => $USER->id,
                'roomname' => '',
                'status' => 'active',
                'warning' => $message,
                'timecreated' => $now,
                'timemodified' => $now,
                'expiresat' => $now + 60,
            ];
            $DB->insert_record('quizaccess_wg_live', $live);
        }

        return ['success' => true];
    }

    /**
     * Return shape.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the warning was sent'),
        ]);
    }
}
