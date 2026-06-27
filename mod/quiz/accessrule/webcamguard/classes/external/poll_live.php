<?php
// This file is part of Moodle - http://moodle.org/

/**
 * External API for student LiveKit polling.
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
use quizaccess_webcamguard\livekit\token_service;

/**
 * Poll for a teacher live monitoring request.
 */
class poll_live extends external_api {
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
        ]);
    }

    /**
     * Execute poll.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @param int $quizid Quiz id.
     * @param int $attemptid Attempt id.
     * @return array
     */
    public static function execute($courseid, $cmid, $quizid, $attemptid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'quizid' => $quizid,
            'attemptid' => $attemptid,
        ]);

        $cm = get_coursemodule_from_id('quiz', $params['cmid'], $params['courseid'], false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_login($params['courseid'], false, $cm);
        require_capability('mod/quiz:attempt', $context);

        $quiz = $DB->get_record('quiz', ['id' => $params['quizid'], 'course' => $params['courseid']], '*', MUST_EXIST);
        if ((int)$cm->instance !== (int)$quiz->id) {
            throw new \moodle_exception('invalidcoursemodule');
        }

        $attempt = $DB->get_record('quiz_attempts', [
            'id' => $params['attemptid'],
            'quiz' => $quiz->id,
        ], '*', MUST_EXIST);
        if ((int)$attempt->userid !== (int)$USER->id) {
            throw new \required_capability_exception($context, 'mod/quiz:attempt', 'nopermissions', '');
        }

        $config = $DB->get_record('quizaccess_wg_config', [
            'quizid' => $quiz->id,
            'enabled' => 1,
        ], '*', IGNORE_MISSING);
        if (!$config || empty($config->liveenabled) || !token_service::is_configured()) {
            return self::empty_response('idle');
        }

        $now = time();
        [$insql, $inparams] = $DB->get_in_or_equal(['requested', 'active'], SQL_PARAMS_NAMED, 'livestatus');
        $sql = "attemptid = :attemptid AND expiresat > :now AND status $insql";
        $records = $DB->get_records_select('quizaccess_wg_live', $sql, $inparams + [
            'attemptid' => $attempt->id,
            'now' => $now,
        ], 'id DESC', '*', 0, 1);
        if (!$records) {
            return self::empty_response('idle');
        }

        $live = reset($records);
        if ($live->status === 'requested') {
            $DB->set_field_select('quizaccess_wg_live', 'status', 'active',
                'id = :id AND status = :st', ['id' => $live->id, 'st' => 'requested']);
            $DB->set_field('quizaccess_wg_live', 'timemodified', $now, ['id' => $live->id]);
            $live->status = 'active';
        }

        $identity = 'student-' . $USER->id . '-attempt-' . $attempt->id;
        $token = token_service::create_token($identity, fullname($USER), $live->roomname, true, false);

        return [
            'status' => 'active',
            'active' => true,
            'url' => token_service::get_url(),
            'token' => $token,
            'roomname' => $live->roomname,
            'expiresat' => (int)$live->expiresat,
        ];
    }

    /**
     * Return shape.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status'),
            'active' => new external_value(PARAM_BOOL, 'Whether live monitoring should be active'),
            'url' => new external_value(PARAM_RAW, 'LiveKit URL'),
            'token' => new external_value(PARAM_RAW, 'LiveKit token'),
            'roomname' => new external_value(PARAM_TEXT, 'LiveKit room name'),
            'expiresat' => new external_value(PARAM_INT, 'Expiry unix timestamp'),
        ]);
    }

    /**
     * Empty response.
     *
     * @param string $status Status.
     * @return array
     */
    protected static function empty_response($status) {
        return [
            'status' => $status,
            'active' => false,
            'url' => '',
            'token' => '',
            'roomname' => '',
            'expiresat' => 0,
        ];
    }
}
