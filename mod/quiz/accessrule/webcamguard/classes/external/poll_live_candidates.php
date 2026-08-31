<?php
// This file is part of Moodle - http://moodle.org/

namespace quizaccess_webcamguard\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/webcamguard/locallib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

class poll_live_candidates extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'quizid' => new external_value(PARAM_INT, 'Quiz id'),
        ]);
    }

    public static function execute($courseid, $cmid, $quizid) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'quizid' => $quizid,
        ]);

        $cm = get_coursemodule_from_id('quiz', $params['cmid'], $params['courseid'], false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_login($params['courseid'], false, $cm);
        require_capability('quizaccess/webcamguard:viewreport', $context);

        $quiz = $DB->get_record('quiz', ['id' => $params['quizid'], 'course' => $params['courseid']], '*', MUST_EXIST);

        $candidates = quizaccess_webcamguard_get_live_candidates($quiz, $cm->id);

        $out = [];
        foreach ($candidates as $candidate) {
            $out[] = [
                'attemptid' => (int)$candidate['attemptid'],
                'userid' => (int)$candidate['userid'],
                'fullname' => $candidate['fullname'],
                'email' => $candidate['email'],
                'attempt' => (int)$candidate['attempt'],
                'riskScore' => (int)($candidate['riskScore'] ?? 0),
                'violationCount' => (int)($candidate['violationCount'] ?? 0),
                'riskLevel' => $candidate['riskLevel'] ?? 'none',
                'topViolationType' => $candidate['topViolationType'] ?? '',
                'topViolationName' => $candidate['topViolationName'] ?? '-',
                'topViolationCount' => (int)($candidate['topViolationCount'] ?? 0),
                'lastEventType' => $candidate['lastEventType'] ?? '',
                'lastEventName' => $candidate['lastEventName'] ?? '-',
                'lastEventTime' => (int)($candidate['lastEventTime'] ?? 0),
                'lastEventDisplay' => $candidate['lastEventDisplay'] ?? '-',
                'lastEventId' => (int)($candidate['lastEventId'] ?? 0),
                'lastViolationEventId' => (int)($candidate['lastViolationEventId'] ?? 0),
                'lastViolationTime' => (int)($candidate['lastViolationTime'] ?? 0),
            ];
        }

        return ['candidates' => $out];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'candidates' => new external_multiple_structure(
                new external_single_structure([
                    'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
                    'userid' => new external_value(PARAM_INT, 'User id'),
                    'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                    'email' => new external_value(PARAM_EMAIL, 'Email address'),
                    'attempt' => new external_value(PARAM_INT, 'Attempt number'),
                    'riskScore' => new external_value(PARAM_INT, 'Risk score'),
                    'violationCount' => new external_value(PARAM_INT, 'Violation count'),
                    'riskLevel' => new external_value(PARAM_ALPHA, 'Risk level'),
                    'topViolationType' => new external_value(PARAM_TEXT, 'Top violation type'),
                    'topViolationName' => new external_value(PARAM_TEXT, 'Top violation name'),
                    'topViolationCount' => new external_value(PARAM_INT, 'Top violation count'),
                    'lastEventType' => new external_value(PARAM_TEXT, 'Last event type'),
                    'lastEventName' => new external_value(PARAM_TEXT, 'Last event name'),
                    'lastEventTime' => new external_value(PARAM_INT, 'Last event time'),
                    'lastEventDisplay' => new external_value(PARAM_TEXT, 'Last event display'),
                    'lastEventId' => new external_value(PARAM_INT, 'Last event id'),
                    'lastViolationEventId' => new external_value(PARAM_INT, 'Last violation event id'),
                    'lastViolationTime' => new external_value(PARAM_INT, 'Last violation time'),
                ])
            ),
        ]);
    }
}
