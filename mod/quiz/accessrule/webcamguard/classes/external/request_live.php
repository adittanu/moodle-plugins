<?php
// This file is part of Moodle - http://moodle.org/

/**
 * External API for teacher live monitoring requests.
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
 * Start or stop a LiveKit request for one quiz attempt.
 */
class request_live extends external_api {
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
            'action' => new external_value(PARAM_ALPHANUMEXT, 'start or stop', VALUE_DEFAULT, 'start'),
        ]);
    }

    /**
     * Execute request.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @param int $quizid Quiz id.
     * @param int $attemptid Attempt id.
     * @param string $action Action.
     * @return array
     */
    public static function execute($courseid, $cmid, $quizid, $attemptid, $action = 'start') {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'quizid' => $quizid,
            'attemptid' => $attemptid,
            'action' => $action,
        ]);

        [$cm, $context, $quiz, $attempt, $config] = self::validate_attempt($params);
        if (empty($config->liveenabled)) {
            return self::empty_response('disabled');
        }
        if (!token_service::is_configured()) {
            return self::empty_response('notconfigured');
        }

        $action = strtolower($params['action']);
        if ($action !== 'start' && $action !== 'stop') {
            throw new \invalid_parameter_exception('Invalid live monitoring action.');
        }

        $now = time();
        if ($action === 'stop') {
            self::stop_active_requests($attempt->id, $now);
            self::insert_event($cm, $quiz, $attempt, 'live_stopped', [
                'stoppedBy' => (int)$USER->id,
            ]);
            return self::empty_response('stopped');
        }

        self::stop_active_requests($attempt->id, $now);

        $roomname = token_service::room_name($quiz->id, $attempt->id);
        $expiresat = $now + token_service::ttl();
        $live = (object)[
            'courseid' => $quiz->course,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => $attempt->id,
            'userid' => $attempt->userid,
            'requestedby' => $USER->id,
            'roomname' => $roomname,
            'status' => 'requested',
            'timecreated' => $now,
            'timemodified' => $now,
            'expiresat' => $expiresat,
        ];
        $DB->insert_record('quizaccess_wg_live', $live);

        self::insert_event($cm, $quiz, $attempt, 'live_requested', [
            'room' => $roomname,
            'requestedBy' => (int)$USER->id,
            'expiresAt' => $expiresat,
        ]);

        $identity = 'teacher-' . $USER->id . '-attempt-' . $attempt->id;
        $token = token_service::create_token($identity, fullname($USER), $roomname, false, true);

        return [
            'status' => 'requested',
            'active' => true,
            'url' => token_service::get_url(),
            'token' => $token,
            'roomname' => $roomname,
            'expiresat' => $expiresat,
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
            'active' => new external_value(PARAM_BOOL, 'Whether live request is active'),
            'url' => new external_value(PARAM_RAW, 'LiveKit URL'),
            'token' => new external_value(PARAM_RAW, 'LiveKit token'),
            'roomname' => new external_value(PARAM_TEXT, 'LiveKit room name'),
            'expiresat' => new external_value(PARAM_INT, 'Expiry unix timestamp'),
        ]);
    }

    /**
     * Validate teacher access to the attempt.
     *
     * @param array $params Validated params.
     * @return array
     */
    protected static function validate_attempt(array $params) {
        global $DB;

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

        $config = $DB->get_record('quizaccess_wg_config', [
            'quizid' => $quiz->id,
            'enabled' => 1,
        ], '*', MUST_EXIST);

        return [$cm, $context, $quiz, $attempt, $config];
    }

    /**
     * Stop all current requests for this attempt.
     *
     * @param int $attemptid Attempt id.
     * @param int $now Current timestamp.
     */
    protected static function stop_active_requests($attemptid, $now) {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal(['requested', 'active'], SQL_PARAMS_NAMED, 'livestatus');
        $params = $inparams + [
            'attemptid' => $attemptid,
        ];
        $records = $DB->get_records_select('quizaccess_wg_live',
            'attemptid = :attemptid AND status ' . $insql, $params);

        foreach ($records as $record) {
            $record->status = 'stopped';
            $record->timemodified = $now;
            $record->expiresat = min((int)$record->expiresat, $now);
            $DB->update_record('quizaccess_wg_live', $record);
        }
    }

    /**
     * Insert a Webcam Guard event without a snapshot.
     *
     * @param object $cm Course module.
     * @param object $quiz Quiz.
     * @param object $attempt Attempt.
     * @param string $eventtype Event type.
     * @param array $metadata Metadata.
     */
    protected static function insert_event($cm, $quiz, $attempt, $eventtype, array $metadata) {
        global $DB;

        $now = time();
        $DB->insert_record('quizaccess_wg_events', (object)[
            'courseid' => $quiz->course,
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => $attempt->id,
            'userid' => $attempt->userid,
            'eventtype' => $eventtype,
            'durationms' => 0,
            'severity' => 'info',
            'hassnapshot' => 0,
            'snapshotfailed' => 0,
            'clienttime' => $now,
            'metadata' => json_encode($metadata),
            'timecreated' => $now,
        ]);

        self::ensure_review_row($attempt, $quiz->id);
    }

    /**
     * Ensure review row exists.
     *
     * @param object $attempt Attempt.
     * @param int $quizid Quiz id.
     */
    protected static function ensure_review_row($attempt, $quizid) {
        global $DB;

        if ($DB->record_exists('quizaccess_wg_reviews', ['attemptid' => $attempt->id])) {
            return;
        }
        $now = time();
        $DB->insert_record('quizaccess_wg_reviews', (object)[
            'attemptid' => $attempt->id,
            'quizid' => $quizid,
            'userid' => $attempt->userid,
            'status' => 'pending',
            'reviewedby' => null,
            'reviewcomment' => null,
            'timecreated' => $now,
            'timemodified' => $now,
            'timereviewed' => null,
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
