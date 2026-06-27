<?php
// This file is part of Moodle - http://moodle.org/

/**
 * External API for teacher live monitor dashboard polling.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_webcamguard\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/webcamguard/report.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * Poll latest stats for a list of in-progress attempts.
 *
 * Used by the teacher live dashboard modal to refresh badges, last-event
 * status, and violation highlights without a full page reload.
 */
class poll_live_stats extends external_api {
    /**
     * Event types that are excluded from the dashboard tiles.
     */
    const HIDDEN_TYPES = ['live_started', 'live_disconnected'];

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
            'attemptids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Attempt id'),
                'Attempt ids to refresh', VALUE_DEFAULT, []
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @param int $quizid Quiz id.
     * @param array $attemptids Attempt ids.
     * @return array
     */
    public static function execute($courseid, $cmid, $quizid, $attemptids) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'quizid' => $quizid,
            'attemptids' => $attemptids,
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

        $ids = array_values(array_unique(array_map('intval', $params['attemptids'])));
        if (count($ids) > 50) {
            $ids = array_slice($ids, 0, 50);
        }
        if (empty($ids)) {
            return ['attempts' => []];
        }

        // Make sure every attempt id belongs to this quiz.
        [$attemptsql, $attemptparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'attempt');
        $attemptparams['quizid'] = $quiz->id;
        $valid = $DB->get_records_select_menu('quiz_attempts',
            "id $attemptsql AND quiz = :quizid",
            $attemptparams, '', 'id, id AS aid');
        $validids = array_keys($valid);
        if (empty($validids)) {
            return ['attempts' => []];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($validids, SQL_PARAMS_NAMED, 'attemptid');
        $events = $DB->get_records_select('quizaccess_wg_events',
            "attemptid $insql",
            $inparams,
            'timecreated ASC, id ASC',
            'id, attemptid, eventtype, severity, timecreated'
        );

        $stats = [];
        foreach ($validids as $attemptid) {
            $stats[$attemptid] = [
                'eventcount' => 0,
                'violationcount' => 0,
                'riskscore' => 0,
                'topcounts' => [],
                'lasttype' => '',
                'lasttime' => 0,
                'lasteventid' => 0,
                'lastviolationeventid' => 0,
                'lastviolationtime' => 0,
            ];
        }

        foreach ($events as $event) {
            if (!isset($stats[$event->attemptid])) {
                continue;
            }
            if (in_array($event->eventtype, self::HIDDEN_TYPES, true)) {
                continue;
            }

            $bucket = &$stats[$event->attemptid];
            $bucket['eventcount']++;
            $bucket['lasttype'] = $event->eventtype;
            $bucket['lasttime'] = (int)$event->timecreated;
            if ((int)$event->id > $bucket['lasteventid']) {
                $bucket['lasteventid'] = (int)$event->id;
            }

            if ($event->severity === 'violation') {
                $bucket['violationcount']++;
                $bucket['riskscore'] += quizaccess_webcamguard_live_event_weight($event->eventtype);
                if (!isset($bucket['topcounts'][$event->eventtype])) {
                    $bucket['topcounts'][$event->eventtype] = 0;
                }
                $bucket['topcounts'][$event->eventtype]++;
                if ((int)$event->id > $bucket['lastviolationeventid']) {
                    $bucket['lastviolationeventid'] = (int)$event->id;
                    $bucket['lastviolationtime'] = (int)$event->timecreated;
                }
            }
            unset($bucket);
        }

        $out = [];
        foreach ($validids as $attemptid) {
            $entry = $stats[$attemptid];
            arsort($entry['topcounts']);
            $toptype = key($entry['topcounts']);
            $topcount = $toptype ? current($entry['topcounts']) : 0;
            $out[] = [
                'attemptid' => (int)$attemptid,
                'eventCount' => (int)$entry['eventcount'],
                'violationCount' => (int)$entry['violationcount'],
                'riskScore' => (int)$entry['riskscore'],
                'riskLevel' => quizaccess_webcamguard_live_risk_level((int)$entry['riskscore']),
                'topViolationType' => $toptype ?: '',
                'topViolationName' => $toptype ? quizaccess_webcamguard_live_event_name($toptype) : '-',
                'topViolationCount' => (int)$topcount,
                'lastEventType' => $entry['lasttype'],
                'lastEventName' => $entry['lasttype']
                    ? quizaccess_webcamguard_live_event_name($entry['lasttype']) : '-',
                'lastEventTime' => (int)$entry['lasttime'],
                'lastEventDisplay' => $entry['lasttime']
                    ? userdate($entry['lasttime'], get_string('strftimetime')) : '-',
                'lastEventId' => (int)$entry['lasteventid'],
                'lastViolationEventId' => (int)$entry['lastviolationeventid'],
                'lastViolationTime' => (int)$entry['lastviolationtime'],
            ];
        }

        return ['attempts' => $out];
    }

    /**
     * Return shape.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'attempts' => new external_multiple_structure(
                new external_single_structure([
                    'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
                    'eventCount' => new external_value(PARAM_INT, 'Total events'),
                    'violationCount' => new external_value(PARAM_INT, 'Violation count'),
                    'riskScore' => new external_value(PARAM_INT, 'Risk score'),
                    'riskLevel' => new external_value(PARAM_ALPHA, 'Risk level (none|low|medium|high)'),
                    'topViolationType' => new external_value(PARAM_TEXT, 'Top violation type'),
                    'topViolationName' => new external_value(PARAM_TEXT, 'Top violation name'),
                    'topViolationCount' => new external_value(PARAM_INT, 'Top violation count'),
                    'lastEventType' => new external_value(PARAM_TEXT, 'Last event type'),
                    'lastEventName' => new external_value(PARAM_TEXT, 'Last event name'),
                    'lastEventTime' => new external_value(PARAM_INT, 'Last event unix time'),
                    'lastEventDisplay' => new external_value(PARAM_TEXT, 'Last event time formatted'),
                    'lastEventId' => new external_value(PARAM_INT, 'Last event row id'),
                    'lastViolationEventId' => new external_value(PARAM_INT, 'Last violation event id'),
                    'lastViolationTime' => new external_value(PARAM_INT, 'Last violation unix time'),
                ])
            ),
        ]);
    }
}
