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
 * Shared helper functions for quizaccess_webcamguard.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Build active attempt candidates for the live monitor modal.
 *
 * @param object $quiz Quiz row.
 * @param int $cmid Course module id.
 * @return array
 */
function quizaccess_webcamguard_get_live_candidates($quiz, $cmid) {
    global $DB;

    $cutoff = time() - 120;
    $hiddentypes = ['heartbeat', 'live_started', 'live_disconnected', 'live_requested', 'live_stopped', 'warning_sent'];
    [$eventtypesql, $eventtypeparams] = $DB->get_in_or_equal($hiddentypes, SQL_PARAMS_NAMED, 'evtype', false, false);
    $namefields = get_all_user_name_fields(true, 'u');
    $attempts = $DB->get_records_sql(
        "SELECT qa.id AS attemptid, qa.userid, qa.attempt, qa.timestart, qa.timemodified,
                $namefields
           FROM {quiz_attempts} qa
           JOIN {user} u ON u.id = qa.userid
          WHERE qa.quiz = :quizid
            AND qa.state = :state
            AND (
                qa.timemodified >= :cutoff1
                OR qa.timestart >= :cutoff2
                OR EXISTS (
                    SELECT 1 FROM {quizaccess_wg_events} e
                     WHERE e.attemptid = qa.id
                       AND e.eventtype {$eventtypesql}
                       AND e.timecreated >= :cutoff3
                )
            )
       ORDER BY qa.timemodified DESC, qa.id DESC",
        array_merge(
            ['quizid' => $quiz->id, 'state' => 'inprogress',
             'cutoff1' => $cutoff, 'cutoff2' => $cutoff, 'cutoff3' => $cutoff],
            $eventtypeparams
        )
    );

    if (!$attempts) {
        return [];
    }

    $attemptids = array_keys($attempts);
    [$attemptsql, $attemptparams] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'attemptid');
    $events = $DB->get_records_select('quizaccess_wg_events',
        "attemptid $attemptsql",
        $attemptparams,
        'timecreated ASC, id ASC',
        'id, attemptid, eventtype, severity, timecreated'
    );

    $stats = [];
    foreach ($attemptids as $attemptid) {
        $stats[$attemptid] = [
            'eventcount' => 0,
            'violationcount' => 0,
            'riskscore' => 0,
            'topcounts' => [],
            'lasttype' => '',
            'lastname' => '-',
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

        // Hide noisy live monitoring lifecycle events from report UI.
        if ($event->eventtype === 'heartbeat' || $event->eventtype === 'live_started'
                || $event->eventtype === 'live_disconnected') {
            continue;
        }

        $stats[$event->attemptid]['eventcount']++;
        $stats[$event->attemptid]['lasttype'] = $event->eventtype;
        $stats[$event->attemptid]['lastname'] = quizaccess_webcamguard_live_event_name($event->eventtype);
        $stats[$event->attemptid]['lasttime'] = (int)$event->timecreated;
        if ((int)$event->id > $stats[$event->attemptid]['lasteventid']) {
            $stats[$event->attemptid]['lasteventid'] = (int)$event->id;
        }

        if ($event->severity === 'violation') {
            $stats[$event->attemptid]['violationcount']++;
            $stats[$event->attemptid]['riskscore'] += quizaccess_webcamguard_live_event_weight($event->eventtype);
            if (!isset($stats[$event->attemptid]['topcounts'][$event->eventtype])) {
                $stats[$event->attemptid]['topcounts'][$event->eventtype] = 0;
            }
            $stats[$event->attemptid]['topcounts'][$event->eventtype]++;
            if ((int)$event->id > $stats[$event->attemptid]['lastviolationeventid']) {
                $stats[$event->attemptid]['lastviolationeventid'] = (int)$event->id;
                $stats[$event->attemptid]['lastviolationtime'] = (int)$event->timecreated;
            }
        }
    }

    $livechecks = $DB->get_records_select('quizaccess_wg_live',
        "attemptid $attemptsql",
        $attemptparams,
        '',
        'id, attemptid'
    );
    $checked = [];
    foreach ($livechecks as $livecheck) {
        $checked[$livecheck->attemptid] = true;
    }

    $candidates = [];
    foreach ($attempts as $attempt) {
        $attemptstats = $stats[$attempt->attemptid];
        arsort($attemptstats['topcounts']);
        $toptype = key($attemptstats['topcounts']);
        $topcount = $toptype ? current($attemptstats['topcounts']) : 0;
        $detailurl = new moodle_url('/mod/quiz/accessrule/webcamguard/attempt.php', [
            'cmid' => $cmid,
            'attemptid' => $attempt->attemptid,
        ]);

        $candidates[] = [
            'attemptid' => (int)$attempt->attemptid,
            'userid' => (int)$attempt->userid,
            'attempt' => (int)$attempt->attempt,
            'fullname' => fullname($attempt),
            'eventCount' => (int)$attemptstats['eventcount'],
            'violationCount' => (int)$attemptstats['violationcount'],
            'riskScore' => (int)$attemptstats['riskscore'],
            'riskLevel' => quizaccess_webcamguard_live_risk_level((int)$attemptstats['riskscore']),
            'topViolationType' => $toptype ?: '',
            'topViolationName' => $toptype ? quizaccess_webcamguard_live_event_name($toptype) : '-',
            'topViolationCount' => (int)$topcount,
            'lastEventType' => $attemptstats['lasttype'],
            'lastEventName' => $attemptstats['lastname'],
            'lastEventTime' => (int)$attemptstats['lasttime'],
            'lastEventDisplay' => $attemptstats['lasttime'] ?
                userdate($attemptstats['lasttime'], get_string('strftimetime')) : '-',
            'lastEventId' => (int)$attemptstats['lasteventid'],
            'lastViolationEventId' => (int)$attemptstats['lastviolationeventid'],
            'lastViolationTime' => (int)$attemptstats['lastviolationtime'],
            'liveChecked' => !empty($checked[$attempt->attemptid]),
            'detailUrl' => $detailurl->out(false),
        ];
    }

    return $candidates;
}

/**
 * Risk level for JS filtering.
 *
 * @param int $score Risk score.
 * @return string
 */
function quizaccess_webcamguard_live_risk_level($score) {
    if ($score <= 0) {
        return 'none';
    }
    if ($score <= 4) {
        return 'low';
    }
    if ($score <= 12) {
        return 'medium';
    }
    return 'high';
}

/**
 * Event weight.
 *
 * @param string $eventtype Event type.
 * @return int
 */
function quizaccess_webcamguard_live_event_weight($eventtype) {
    $weights = [
        'no_face' => 2,
        'multiple_faces' => 4,
        'window_blur' => 3,
        'camera_stopped' => 5,
        'camera_error' => 3,
        'identity_check' => 4,
    ];
    return $weights[$eventtype] ?? 1;
}

/**
 * Friendly event name.
 *
 * @param string $eventtype Event type.
 * @return string
 */
function quizaccess_webcamguard_live_event_name($eventtype) {
    $key = 'event_' . $eventtype;
    if (get_string_manager()->string_exists($key, 'quizaccess_webcamguard')) {
        return get_string($key, 'quizaccess_webcamguard');
    }
    return $eventtype;
}
