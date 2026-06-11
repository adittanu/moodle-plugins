<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Attempt detail helper.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_webcamguard\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds attempt detail HTML.
 */
class attempt_detail {
    /**
     * Render deterministic risk summary for one attempt.
     *
     * @param array $events Events.
     * @return string
     */
    public static function render_summary(array $events) {
        $stats = self::build_stats($events);
        $level = self::risk_level($stats['riskscore']);

        $output = self::render_summary_styles();
        $output .= \html_writer::start_div('quizaccess-webcamguard-attemptsummary');
        $output .= self::render_metric_card(get_string('totalevents', 'quizaccess_webcamguard'), $stats['eventcount']);
        $output .= self::render_metric_card(get_string('totalviolations', 'quizaccess_webcamguard'),
            $stats['violationcount'], 'danger');
        $output .= self::render_metric_card(get_string('riskscore', 'quizaccess_webcamguard'),
            $stats['riskscore'] . ' - ' . $level['label'], $level['tone']);

        $output .= \html_writer::start_div('quizaccess-webcamguard-attemptsummarycard quizaccess-webcamguard-risksummary');
        $output .= \html_writer::div(get_string('risksummary', 'quizaccess_webcamguard'),
            'quizaccess-webcamguard-summarylabel');
        $output .= \html_writer::tag('p', s(self::summary_text($stats)), ['class' => 'mb-0']);
        $output .= \html_writer::end_div();
        $output .= \html_writer::end_div();

        return $output;
    }

    /**
     * Build deterministic event stats.
     *
     * @param array $events Events.
     * @return array
     */
    protected static function build_stats(array $events) {
        $stats = [
            'eventcount' => count($events),
            'violationcount' => 0,
            'riskscore' => 0,
            'counts' => [],
            'firstviolation' => null,
            'lastviolation' => null,
        ];

        foreach ($events as $event) {
            if ($event->severity !== 'violation') {
                continue;
            }
            $type = self::effective_eventtype($event);
            $stats['violationcount']++;
            $stats['riskscore'] += self::event_weight($type);
            if (!isset($stats['counts'][$type])) {
                $stats['counts'][$type] = 0;
            }
            $stats['counts'][$type]++;
            if ($stats['firstviolation'] === null || $event->timecreated < $stats['firstviolation']) {
                $stats['firstviolation'] = $event->timecreated;
            }
            if ($stats['lastviolation'] === null || $event->timecreated > $stats['lastviolation']) {
                $stats['lastviolation'] = $event->timecreated;
            }
        }

        arsort($stats['counts']);
        return $stats;
    }

    /**
     * Build risk summary text.
     *
     * @param array $stats Stats.
     * @return string
     */
    protected static function summary_text(array $stats) {
        if ($stats['violationcount'] <= 0) {
            return get_string('risksummaryclean', 'quizaccess_webcamguard');
        }

        $toptype = key($stats['counts']);
        $topcount = $toptype ? current($stats['counts']) : 0;
        $a = (object)[
            'violations' => $stats['violationcount'],
            'toptype' => $toptype ? self::event_name($toptype) : '-',
            'topcount' => $topcount,
            'first' => $stats['firstviolation'] ? userdate($stats['firstviolation'], get_string('strftimetime')) : '-',
            'last' => $stats['lastviolation'] ? userdate($stats['lastviolation'], get_string('strftimetime')) : '-',
        ];

        return get_string('risksummarytext', 'quizaccess_webcamguard', $a);
    }

    /**
     * Render summary CSS.
     *
     * @return string
     */
    protected static function render_summary_styles() {
        return \html_writer::tag('style', '
.quizaccess-webcamguard-attemptsummary {
    display: grid;
    grid-template-columns: repeat(3, minmax(150px, 1fr)) minmax(280px, 1.6fr);
    gap: .75rem;
    margin: .75rem 0 1.25rem;
}
.quizaccess-webcamguard-attemptsummarycard {
    background: #fff;
    border: 1px solid #e7eaef;
    border-radius: 8px;
    padding: .85rem 1rem;
    box-shadow: 0 1px 5px rgba(20, 30, 50, .05);
}
.quizaccess-webcamguard-summarylabel {
    color: #6c757d;
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .02em;
    text-transform: uppercase;
}
.quizaccess-webcamguard-summaryvalue {
    margin-top: .25rem;
    color: #20252b;
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1.1;
}
.quizaccess-webcamguard-summaryvalue-danger {
    color: #c82333;
}
.quizaccess-webcamguard-summaryvalue-warning {
    color: #8a6200;
}
.quizaccess-webcamguard-summaryvalue-success {
    color: #2f8f46;
}
@media (max-width: 991.98px) {
    .quizaccess-webcamguard-attemptsummary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575.98px) {
    .quizaccess-webcamguard-attemptsummary {
        grid-template-columns: 1fr;
    }
}
');
    }

    /**
     * Render one summary metric card.
     *
     * @param string $label Label.
     * @param string|int $value Value.
     * @param string $tone Tone.
     * @return string
     */
    protected static function render_metric_card($label, $value, $tone = '') {
        $valueclass = 'quizaccess-webcamguard-summaryvalue';
        if ($tone !== '') {
            $valueclass .= ' quizaccess-webcamguard-summaryvalue-' . $tone;
        }

        $output = \html_writer::start_div('quizaccess-webcamguard-attemptsummarycard');
        $output .= \html_writer::div(s($label), 'quizaccess-webcamguard-summarylabel');
        $output .= \html_writer::div(s((string)$value), $valueclass);
        $output .= \html_writer::end_div();
        return $output;
    }

    /**
     * Render event timeline.
     *
     * @param array $events Events.
     * @param int $contextid Context id.
     * @return string
     */
    public static function render_events(array $events, $contextid) {
        if (empty($events)) {
            return \html_writer::div(get_string('noevents', 'quizaccess_webcamguard'), 'alert alert-info');
        }

        $output = self::render_grid_styles();
        $output .= \html_writer::start_div('quizaccess-webcamguard-eventcards');
        $monitoringstartedshown = false;
        $modals = '';

        // Hide noisy live monitoring lifecycle events from the timeline UI.
        $hiddentypes = ['live_started', 'live_disconnected', 'live_requested', 'live_stopped'];

        foreach ($events as $event) {
            if (in_array($event->eventtype, $hiddentypes, true)) {
                continue;
            }
            // Hide ambient "monitoring resumed" rows (green/info). Keep any rare
            // violation rows that were stored under this type (effective_eventtype
            // will re-derive them to no_face / multiple_faces).
            if ($event->eventtype === 'monitoring_resumed' && $event->severity !== 'violation') {
                continue;
            }
            if ($event->eventtype === 'monitoring_started') {
                if ($monitoringstartedshown) {
                    continue;
                }
                $monitoringstartedshown = true;
            }

            $state = self::event_state($event);
            $displaytype = self::effective_eventtype($event);
            $duration = $event->durationms ? format_time((int)ceil($event->durationms / 1000)) : '-';
            $modalid = 'quizaccess-webcamguard-event-' . (int)$event->id;
            $snapshot = self::render_snapshot($event, $contextid, $state, true);

            $output .= \html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'quizaccess-webcamguard-eventcard',
                'data-toggle' => 'modal',
                'data-target' => '#' . $modalid,
                'aria-label' => get_string('viewdetails', 'quizaccess_webcamguard') . ': ' .
                    self::event_name($displaytype),
            ]);
            $output .= $snapshot;

            $output .= \html_writer::start_div('quizaccess-webcamguard-eventcard-body');
            $output .= \html_writer::start_div('quizaccess-webcamguard-eventcard-badges');
            $output .= \html_writer::span(self::event_name($displaytype),
                'badge badge-' . $state['badge'] . ' quizaccess-webcamguard-eventbadge');
            $output .= \html_writer::span($state['label'],
                'quizaccess-webcamguard-eventstatus quizaccess-webcamguard-eventstatus-' . $event->severity);
            $output .= \html_writer::end_div();

            $output .= \html_writer::start_div('quizaccess-webcamguard-eventmeta');
            $output .= \html_writer::span(userdate($event->timecreated, get_string('strftimetime')), 'mr-2');
            if ($duration !== '-') {
                $output .= \html_writer::span($duration);
            }
            $output .= \html_writer::end_div();

            $output .= \html_writer::end_div();
            $output .= \html_writer::end_tag('button');

            $modals .= self::render_event_modal($event, $contextid, $state, $duration, $modalid);
        }

        $output .= \html_writer::end_div();
        $output .= $modals;
        return $output;
    }

    /**
     * Render timeline grid CSS.
     *
     * @return string
     */
    protected static function render_grid_styles() {
        return \html_writer::tag('style', '
.quizaccess-webcamguard-eventcards {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: .75rem;
    align-items: start;
}
.quizaccess-webcamguard-eventcard {
    display: block;
    width: 100%;
    padding: 0;
    overflow: hidden;
    text-align: left;
    background: #fff;
    border: 1px solid #e7eaef;
    border-radius: 8px;
    box-shadow: 0 1px 5px rgba(20, 30, 50, .06);
    cursor: pointer;
}
.quizaccess-webcamguard-eventcard:hover,
.quizaccess-webcamguard-eventcard:focus {
    box-shadow: 0 6px 18px rgba(20, 30, 50, .14);
    outline: 2px solid rgba(15, 93, 191, .18);
    outline-offset: 2px;
}
.quizaccess-webcamguard-eventcard-body {
    padding: .55rem .65rem .65rem;
}
.quizaccess-webcamguard-eventcard-badges {
    display: flex;
    align-items: center;
    gap: .35rem;
    min-width: 0;
}
.quizaccess-webcamguard-eventbadge {
    max-width: 72%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.quizaccess-webcamguard-eventstatus {
    font-size: .78rem;
    font-weight: 700;
    white-space: nowrap;
}
.quizaccess-webcamguard-eventstatus-info {
    color: #2f8f46;
}
.quizaccess-webcamguard-eventstatus-warning {
    color: #8a6200;
}
.quizaccess-webcamguard-eventstatus-violation {
    color: #c82333;
}
.quizaccess-webcamguard-eventmeta {
    margin-top: .35rem;
    color: #6c757d;
    font-size: .78rem;
    line-height: 1.2;
}
@media (max-width: 1199.98px) {
    .quizaccess-webcamguard-eventcards {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
@media (max-width: 991.98px) {
    .quizaccess-webcamguard-eventcards {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 767.98px) {
    .quizaccess-webcamguard-eventcards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 479.98px) {
    .quizaccess-webcamguard-eventcards {
        grid-template-columns: 1fr;
    }
}
');
    }

    /**
     * Render event snapshot with review-friendly status border.
     *
     * @param object $event Event row.
     * @param int $contextid Context id.
     * @param array $state Event state.
     * @return string
     */
    protected static function render_snapshot($event, $contextid, array $state, $compact = false) {
        $imagestyle = 'display: block; width: 100%; aspect-ratio: 4 / 3; height: auto; ' .
            'background: #f8f9fa; border-bottom: 4px solid ' . $state['color'] . ';';
        $imagestyle .= $compact ? ' object-fit: cover;' : ' object-fit: contain;';

        if (!empty($event->hassnapshot)) {
            $filename = self::get_snapshot_filename($contextid, $event->id);
            if ($filename) {
                $url = \moodle_url::make_pluginfile_url($contextid, 'quizaccess_webcamguard', 'snapshot',
                    $event->id, '/', $filename);
                return \html_writer::empty_tag('img', [
                    'src' => $url,
                    'alt' => get_string('snapshot', 'quizaccess_webcamguard'),
                    'style' => $imagestyle,
                ]);
            }
        }

        if (!empty($event->snapshotfailed)) {
            return \html_writer::div(get_string('event_snapshot_failed', 'quizaccess_webcamguard'),
                'alert alert-warning mb-0 text-center', [
                    'style' => 'aspect-ratio: 4 / 3; display: flex; align-items: center; justify-content: center; ' .
                        'border-radius: 0; border-left: 0; border-right: 0; border-top: 0; border-bottom: 4px solid ' .
                        $state['color'] . ';',
                ]);
        }

        return \html_writer::div(get_string('nosnapshot', 'quizaccess_webcamguard'),
            'text-muted p-3 text-center', [
                'style' => 'aspect-ratio: 4 / 3; display: flex; align-items: center; justify-content: center; ' .
                    'background: #f8f9fa; border-bottom: 4px solid ' . $state['color'] . ';',
            ]);
    }

    /**
     * Render complete event detail modal.
     *
     * @param object $event Event row.
     * @param int $contextid Context id.
     * @param array $state Event state.
     * @param string $duration Duration label.
     * @param string $modalid Modal HTML id.
     * @return string
     */
    protected static function render_event_modal($event, $contextid, array $state, $duration, $modalid) {
        $metadata = self::render_metadata($event->metadata);
        $metadata = $metadata === '' ? '-' : $metadata;

        $output = \html_writer::start_div('modal fade', [
            'id' => $modalid,
            'tabindex' => '-1',
            'role' => 'dialog',
            'aria-hidden' => 'true',
        ]);
        $output .= \html_writer::start_div('modal-dialog modal-lg', ['role' => 'document']);
        $output .= \html_writer::start_div('modal-content');

        $displaytype = self::effective_eventtype($event);
        $output .= \html_writer::start_div('modal-header');
        $output .= \html_writer::tag('h5', self::event_name($displaytype), ['class' => 'modal-title']);
        $output .= \html_writer::tag('button',
            \html_writer::span('&times;', '', ['aria-hidden' => 'true']),
            [
                'type' => 'button',
                'class' => 'close',
                'data-dismiss' => 'modal',
                'aria-label' => get_string('close', 'moodle'),
            ]);
        $output .= \html_writer::end_div();

        $output .= \html_writer::start_div('modal-body');
        $output .= self::render_snapshot($event, $contextid, $state);
        $output .= \html_writer::start_div('mt-3');
        $output .= self::render_detail_row(get_string('eventtype', 'quizaccess_webcamguard'),
            self::event_name($displaytype));
        $output .= self::render_detail_row(get_string('status', 'moodle'), $state['label']);
        $output .= self::render_detail_row(get_string('time', 'quizaccess_webcamguard'), userdate($event->timecreated));
        $output .= self::render_detail_row(get_string('duration', 'quizaccess_webcamguard'), $duration);
        $output .= self::render_detail_row(get_string('metadata', 'quizaccess_webcamguard'), $metadata, true);
        $output .= \html_writer::end_div();
        $output .= \html_writer::end_div();

        $output .= \html_writer::end_div();
        $output .= \html_writer::end_div();
        $output .= \html_writer::end_div();

        return $output;
    }

    /**
     * Render one modal detail row.
     *
     * @param string $label Row label.
     * @param string $value Row value.
     * @param bool $htmlvalue Whether value is already safe HTML.
     * @return string
     */
    protected static function render_detail_row($label, $value, $htmlvalue = false) {
        $output = \html_writer::start_div('row py-2 border-bottom');
        $output .= \html_writer::div(s($label), 'col-sm-3 font-weight-bold text-muted');
        $output .= \html_writer::div($htmlvalue ? $value : s($value), 'col-sm-9');
        $output .= \html_writer::end_div();
        return $output;
    }

    /**
     * Event visual state.
     *
     * @param object $event Event row.
     * @return array
     */
    protected static function event_state($event) {
        if ($event->severity === 'info') {
            return [
                'badge' => 'success',
                'color' => '#28a745',
                'label' => get_string('normal', 'quizaccess_webcamguard'),
            ];
        }

        if ($event->severity === 'warning') {
            return [
                'badge' => 'warning',
                'color' => '#ffc107',
                'label' => get_string('warning', 'quizaccess_webcamguard'),
            ];
        }

        return [
            'badge' => 'danger',
            'color' => '#dc3545',
            'label' => get_string('violation', 'quizaccess_webcamguard'),
        ];
    }

    /**
     * Suspicion score weight for one violation event.
     *
     * @param string $eventtype Event type.
     * @return int
     */
    protected static function event_weight($eventtype) {
        $weights = [
            'no_face' => 2,
            'multiple_faces' => 4,
            'window_blur' => 3,
            'camera_stopped' => 5,
            'camera_error' => 3,
            'identity_check' => 4,
        ];
        return isset($weights[$eventtype]) ? $weights[$eventtype] : 1;
    }

    /**
     * Risk level metadata.
     *
     * @param int $score Score.
     * @return array
     */
    protected static function risk_level($score) {
        if ($score <= 0) {
            return ['label' => get_string('risknone', 'quizaccess_webcamguard'), 'tone' => 'success'];
        }
        if ($score <= 4) {
            return ['label' => get_string('risklow', 'quizaccess_webcamguard'), 'tone' => ''];
        }
        if ($score <= 12) {
            return ['label' => get_string('riskmedium', 'quizaccess_webcamguard'), 'tone' => 'warning'];
        }
        return ['label' => get_string('riskhigh', 'quizaccess_webcamguard'), 'tone' => 'danger'];
    }

    /**
     * Render compact metadata badges.
     *
     * @param string $metadata Metadata JSON.
     * @return string
     */
    protected static function render_metadata($metadata) {
        $metadata = trim((string)$metadata);
        if ($metadata === '') {
            return '';
        }

        $decoded = json_decode($metadata, true);
        if (!is_array($decoded)) {
            return \html_writer::tag('small', s($metadata), ['class' => 'text-muted']);
        }

        $items = [];
        foreach ($decoded as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } else if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            $items[] = \html_writer::span(s($key) . ': ' . s((string)$value), 'badge badge-light mr-1 mb-1');
        }

        return implode(' ', $items);
    }

    /**
     * Get snapshot filename for an event.
     *
     * @param int $contextid Context id.
     * @param int $eventid Event id.
     * @return string|null
     */
    protected static function get_snapshot_filename($contextid, $eventid) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'quizaccess_webcamguard', 'snapshot', $eventid, 'filename', false);
        foreach ($files as $file) {
            return $file->get_filename();
        }
        return null;
    }

    /**
     * Effective event type for display.
     *
     * Older rows stored ambient monitoring events (interval_snapshot,
     * monitoring_started, monitoring_resumed) verbatim even when the snapshot
     * actually captured a face-count violation. Re-derive the meaningful type
     * from severity + metadata so historical rows render with the correct badge.
     *
     * @param object $event Event row.
     * @return string
     */
    protected static function effective_eventtype($event) {
        $type = $event->eventtype;
        if ($event->severity !== 'violation') {
            return $type;
        }
        if (!in_array($type, ['interval_snapshot', 'monitoring_started', 'monitoring_resumed'], true)) {
            return $type;
        }

        $faces = null;
        $metadata = trim((string)$event->metadata);
        if ($metadata !== '') {
            $decoded = json_decode($metadata);
            if (is_object($decoded) && property_exists($decoded, 'faces') && is_numeric($decoded->faces)) {
                $faces = (int)$decoded->faces;
            }
        }

        if ($faces === 0) {
            return 'no_face';
        }
        if ($faces !== null && $faces > 1) {
            return 'multiple_faces';
        }
        return $type;
    }

    /**
     * Friendly event name.
     *
     * @param string $eventtype Event type.
     * @return string
     */
    protected static function event_name($eventtype) {
        $key = 'event_' . $eventtype;
        if (get_string_manager()->string_exists($key, 'quizaccess_webcamguard')) {
            return get_string($key, 'quizaccess_webcamguard');
        }
        return s($eventtype);
    }
}
