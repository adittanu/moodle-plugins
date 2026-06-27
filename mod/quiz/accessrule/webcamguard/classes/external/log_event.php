<?php
// This file is part of Moodle - http://moodle.org/

/**
 * External API for logging Webcam Guard events.
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
 * Log a monitoring event and optional snapshot.
 */
class log_event extends external_api {
    /** @var array Allowed event types. */
    const EVENT_TYPES = [
        'no_face',
        'multiple_faces',
        'camera_error',
        'camera_stopped',
        'window_blur',
        'interval_snapshot',
        'monitoring_error',
        'snapshot_failed',
        'monitoring_started',
        'monitoring_resumed',
        'identity_check',
        'live_requested',
        'live_started',
        'live_stopped',
        'live_failed',
        'live_disconnected',
    ];

    /**
     * Describes parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'quizid' => new external_value(PARAM_INT, 'Quiz id'),
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt id'),
            'eventtype' => new external_value(PARAM_ALPHANUMEXT, 'Monitoring event type'),
            'durationms' => new external_value(PARAM_INT, 'Event duration in milliseconds', VALUE_DEFAULT, 0),
            'clienttime' => new external_value(PARAM_INT, 'Client timestamp in milliseconds', VALUE_DEFAULT, 0),
            'metadata' => new external_value(PARAM_RAW, 'JSON metadata', VALUE_DEFAULT, ''),
            'snapshot' => new external_value(PARAM_RAW, 'Optional data URL snapshot', VALUE_DEFAULT, '', NULL_ALLOWED),
        ]);
    }

    /**
     * Execute event logging.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @param int $quizid Quiz id.
     * @param int $attemptid Attempt id.
     * @param string $eventtype Event type.
     * @param int $durationms Duration.
     * @param int $clienttime Client time.
     * @param string $metadata JSON metadata.
     * @param string $snapshot Data URL snapshot.
     * @return array
     */
    public static function execute($courseid, $cmid, $quizid, $attemptid, $eventtype,
            $durationms = 0, $clienttime = 0, $metadata = '', $snapshot = '') {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'quizid' => $quizid,
            'attemptid' => $attemptid,
            'eventtype' => $eventtype,
            'durationms' => $durationms,
            'clienttime' => $clienttime,
            'metadata' => $metadata,
            'snapshot' => $snapshot,
        ]);

        if (!in_array($params['eventtype'], self::EVENT_TYPES, true)) {
            throw new \invalid_parameter_exception('Invalid Webcam Guard event type.');
        }

        $cm = get_coursemodule_from_id('quiz', $params['cmid'], $params['courseid'], false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_login($params['courseid'], false, $cm);

        $quiz = $DB->get_record('quiz', ['id' => $params['quizid'], 'course' => $params['courseid']], '*', MUST_EXIST);
        if ((int)$cm->instance !== (int)$quiz->id) {
            throw new \moodle_exception('invalidcoursemodule');
        }

        $config = $DB->get_record('quizaccess_wg_config', [
            'quizid' => $quiz->id,
            'enabled' => 1,
        ], '*', MUST_EXIST);

        $attempt = $DB->get_record('quiz_attempts', [
            'id' => $params['attemptid'],
            'quiz' => $quiz->id,
        ], '*', MUST_EXIST);

        if ((int)$attempt->userid !== (int)$USER->id) {
            require_capability('mod/quiz:viewreports', $context);
        } else {
            require_capability('mod/quiz:attempt', $context);
        }

        $transaction = $DB->start_delegated_transaction();

        if ($params['eventtype'] === 'monitoring_started') {
            $existingeventid = $DB->get_field('quizaccess_wg_events', 'id', [
                'attemptid' => $attempt->id,
                'eventtype' => 'monitoring_started',
            ], IGNORE_MULTIPLE);
            if ($existingeventid) {
                $transaction->allow_commit();
                return [
                    'status' => 'duplicate',
                    'eventid' => $existingeventid,
                ];
            }
        }

        // Reclassify ambient monitoring events to face-count violation types so the
        // badge label, severity, and risk weighting all match what actually happened.
        $effectivetype = $params['eventtype'];
        if (in_array($effectivetype, ['interval_snapshot', 'monitoring_started', 'monitoring_resumed'], true)) {
            $faces = self::get_metadata_face_count($params['metadata']);
            if ($faces === 0) {
                $effectivetype = 'no_face';
            } else if ($faces !== null && $faces > 1) {
                $effectivetype = 'multiple_faces';
            }
        }

        $record = (object)[
            'courseid' => $params['courseid'],
            'cmid' => $cm->id,
            'quizid' => $quiz->id,
            'attemptid' => $attempt->id,
            'userid' => $attempt->userid,
            'eventtype' => $effectivetype,
            'durationms' => max(0, (int)$params['durationms']),
            'severity' => self::get_severity($effectivetype, $params['metadata']),
            'hassnapshot' => 0,
            'snapshotfailed' => self::metadata_has_snapshot_failure($params['metadata']) ? 1 : 0,
            'clienttime' => max(0, (int)floor($params['clienttime'] / 1000)),
            'metadata' => self::normalise_metadata($params['metadata']),
            'timecreated' => time(),
        ];

        $eventid = $DB->insert_record('quizaccess_wg_events', $record);

        if (!empty($params['snapshot'])) {
            $stored = self::store_snapshot($context, $eventid, $params['snapshot']);
            if ($stored) {
                $DB->set_field('quizaccess_wg_events', 'hassnapshot', 1, ['id' => $eventid]);
            } else {
                $DB->set_field('quizaccess_wg_events', 'snapshotfailed', 1, ['id' => $eventid]);
            }
        }

        self::ensure_review_row($attempt, $quiz->id);
        $transaction->allow_commit();

        return [
            'status' => 'ok',
            'eventid' => $eventid,
        ];
    }

    /**
     * Describes returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status'),
            'eventid' => new external_value(PARAM_INT, 'Stored event id'),
        ]);
    }

    /**
     * Normalise metadata to a safe JSON string.
     *
     * @param string $metadata Metadata.
     * @return string
     */
    protected static function normalise_metadata($metadata) {
        $metadata = trim((string)$metadata);
        if ($metadata === '') {
            return '';
        }
        json_decode($metadata);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_encode(['raw' => \core_text::substr($metadata, 0, 1000)]);
        }
        return \core_text::substr($metadata, 0, 4000);
    }

    /**
     * Whether browser metadata says a requested snapshot could not be captured.
     *
     * @param string $metadata Metadata JSON.
     * @return bool
     */
    protected static function metadata_has_snapshot_failure($metadata) {
        $metadata = trim((string)$metadata);
        if ($metadata === '') {
            return false;
        }

        $decoded = json_decode($metadata);
        if (!is_object($decoded)) {
            return false;
        }

        return !empty($decoded->snapshotRequested) && !empty($decoded->snapshot) && $decoded->snapshot === 'failed';
    }

    /**
     * Face count from browser metadata.
     *
     * @param string $metadata Metadata JSON.
     * @return int|null
     */
    protected static function get_metadata_face_count($metadata) {
        $metadata = trim((string)$metadata);
        if ($metadata === '') {
            return null;
        }

        $decoded = json_decode($metadata);
        if (!is_object($decoded) || !property_exists($decoded, 'faces') || !is_numeric($decoded->faces)) {
            return null;
        }

        return (int)$decoded->faces;
    }

    /**
     * Store snapshot data URL in Moodle File API.
     *
     * @param \context_module $context Module context.
     * @param int $eventid Event id.
     * @param string $snapshot Data URL.
     * @return bool
     */
    protected static function store_snapshot(\context_module $context, $eventid, $snapshot) {
        if (!preg_match('/^data:image\/(jpeg|jpg|png);base64,([A-Za-z0-9+\/]+=*)$/', $snapshot, $matches)) {
            return false;
        }

        $extension = $matches[1] === 'png' ? 'png' : 'jpg';
        $binary = base64_decode($matches[2], true);
        if ($binary === false || strlen($binary) > 2 * 1024 * 1024) {
            return false;
        }

        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'quizaccess_webcamguard',
            'filearea' => 'snapshot',
            'itemid' => $eventid,
            'filepath' => '/',
            'filename' => 'snapshot-' . $eventid . '.' . $extension,
        ];

        $fs->delete_area_files($context->id, 'quizaccess_webcamguard', 'snapshot', $eventid);
        $fs->create_file_from_string($filerecord, $binary);
        return true;
    }

    /**
     * Ensure review row exists for an attempt.
     *
     * @param object $attempt Attempt row.
     * @param int $quizid Quiz id.
     */
    protected static function ensure_review_row($attempt, $quizid) {
        global $DB;

        $now = time();
        $existing = $DB->get_record('quizaccess_wg_reviews', ['attemptid' => $attempt->id]);
        if ($existing) {
            if ($existing->status === 'pending') {
                $DB->set_field('quizaccess_wg_reviews', 'timemodified', $now, ['id' => $existing->id]);
            }
            return;
        }

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
     * Event severity.
     *
     * @param string $eventtype Event type.
     * @param string $metadata Metadata JSON.
     * @return string
     */
    protected static function get_severity($eventtype, $metadata = '') {
        if ($eventtype === 'interval_snapshot'
                || $eventtype === 'monitoring_started'
                || $eventtype === 'monitoring_resumed') {
            $faces = self::get_metadata_face_count($metadata);
            if ($faces !== null && $faces !== 1) {
                return 'violation';
            }
            return 'info';
        }
        if ($eventtype === 'monitoring_error' || $eventtype === 'snapshot_failed') {
            return 'warning';
        }
        if ($eventtype === 'live_requested'
                || $eventtype === 'live_started'
                || $eventtype === 'live_stopped') {
            return 'info';
        }
        if ($eventtype === 'live_failed' || $eventtype === 'live_disconnected') {
            return 'warning';
        }
        if ($eventtype === 'identity_check') {
            $metadata = trim((string)$metadata);
            if ($metadata !== '') {
                $decoded = json_decode($metadata);
                if (is_object($decoded) && !empty($decoded->status)) {
                    if ($decoded->status === 'match' || $decoded->status === 'disabled') {
                        return 'info';
                    }
                    if ($decoded->status === 'unavailable') {
                        return 'warning';
                    }
                }
            }
            return 'violation';
        }
        return 'violation';
    }
}
