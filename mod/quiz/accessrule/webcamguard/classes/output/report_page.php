<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Report page table helper.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_webcamguard\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds report page HTML.
 */
class report_page {
    /**
     * Render live monitor modal launcher.
     *
     * @return string
     */
    public static function render_live_monitor() {
        $output = self::render_live_monitor_styles();
        $output .= \html_writer::start_div('quizaccess-webcamguard-livebar');
        $output .= \html_writer::tag('button', get_string('livemonitor', 'quizaccess_webcamguard'), [
            'type' => 'button',
            'class' => 'btn btn-primary',
            'data-toggle' => 'modal',
            'data-target' => '#quizaccess-webcamguard-live-dashboard',
        ]);
        $output .= \html_writer::end_div();

        $output .= \html_writer::start_div('modal fade quizaccess-webcamguard-livedashboard', [
            'id' => 'quizaccess-webcamguard-live-dashboard',
            'tabindex' => '-1',
            'role' => 'dialog',
            'aria-labelledby' => 'quizaccess-webcamguard-live-dashboard-title',
            'aria-hidden' => 'true',
        ]);
        $output .= \html_writer::start_div('modal-dialog modal-xl', ['role' => 'document']);
        $output .= \html_writer::start_div('modal-content');

        $output .= \html_writer::start_div('modal-header');
        $output .= \html_writer::tag('h5', get_string('livemonitortitle', 'quizaccess_webcamguard'), [
            'class' => 'modal-title',
            'id' => 'quizaccess-webcamguard-live-dashboard-title',
        ]);
        $output .= \html_writer::tag('button',
            \html_writer::span('&times;', '', ['aria-hidden' => 'true']),
            [
                'type' => 'button',
                'class' => 'close',
                'data-dismiss' => 'modal',
                'aria-label' => get_string('closebuttontitle'),
            ]);
        $output .= \html_writer::end_div();

        $output .= \html_writer::start_div('modal-body');
        $output .= \html_writer::start_div('quizaccess-webcamguard-livecontrols');
        $output .= \html_writer::label(get_string('livemonitormode', 'quizaccess_webcamguard'),
            'quizaccess-webcamguard-live-filter', false, ['class' => 'sr-only']);
        $output .= \html_writer::select([
            'priority' => get_string('livefilterpriority', 'quizaccess_webcamguard'),
            'violations' => get_string('livefilterviolations', 'quizaccess_webcamguard'),
            'no_face' => get_string('event_no_face', 'quizaccess_webcamguard'),
            'multiple_faces' => get_string('event_multiple_faces', 'quizaccess_webcamguard'),
            'window_blur' => get_string('event_window_blur', 'quizaccess_webcamguard'),
            'camera' => get_string('livefiltercamera', 'quizaccess_webcamguard'),
            'unchecked' => get_string('livefilterunchecked', 'quizaccess_webcamguard'),
            'high' => get_string('livefilterhighrisk', 'quizaccess_webcamguard'),
            'medium' => get_string('livefiltermediumrisk', 'quizaccess_webcamguard'),
            'low' => get_string('livefilterlowrisk', 'quizaccess_webcamguard'),
            'all' => get_string('livefilterallactive', 'quizaccess_webcamguard'),
            'random' => get_string('livefilterrandom', 'quizaccess_webcamguard'),
        ], 'quizaccess-webcamguard-live-filter', 'priority', false, [
            'id' => 'quizaccess-webcamguard-live-filter',
            'class' => 'custom-select',
            'data-region' => 'webcamguard-live-filter',
        ]);
        $output .= \html_writer::tag('button', get_string('liverefreshselection', 'quizaccess_webcamguard'), [
            'type' => 'button',
            'class' => 'btn btn-outline-secondary',
            'data-action' => 'webcamguard-live-refresh',
        ]);
        $output .= \html_writer::tag('button', get_string('livestartselection', 'quizaccess_webcamguard'), [
            'type' => 'button',
            'class' => 'btn btn-primary',
            'data-action' => 'webcamguard-live-start-selection',
        ]);
        $output .= \html_writer::tag('button', get_string('livestopall', 'quizaccess_webcamguard'), [
            'type' => 'button',
            'class' => 'btn btn-outline-danger',
            'data-action' => 'webcamguard-live-stop-all',
        ]);
        $output .= \html_writer::div('', 'quizaccess-webcamguard-livecount text-muted', [
            'data-region' => 'webcamguard-live-count',
        ]);
        $output .= \html_writer::end_div();

        $output .= \html_writer::div('', 'quizaccess-webcamguard-livegrid', [
            'data-region' => 'webcamguard-live-grid',
        ]);
        $output .= \html_writer::end_div();

        $output .= \html_writer::start_div('modal-footer');
        $output .= \html_writer::tag('button', get_string('closebuttontitle'), [
            'type' => 'button',
            'class' => 'btn btn-secondary',
            'data-dismiss' => 'modal',
        ]);
        $output .= \html_writer::end_div();

        $output .= \html_writer::end_div();
        $output .= \html_writer::end_div();
        $output .= \html_writer::end_div();

        return $output;
    }

    /**
     * Render report summary cards.
     *
     * @param object|false $summary Summary row.
     * @param array $violationtypes Violation type count rows.
     * @return string
     */
    public static function render_summary($summary, array $violationtypes) {
        $eventcount = $summary ? (int)$summary->eventcount : 0;
        $violationcount = $summary ? (int)$summary->violationcount : 0;
        $violatedattempts = $summary ? (int)$summary->violatedattempts : 0;

        $output = self::render_summary_styles();
        $output .= \html_writer::start_div('quizaccess-webcamguard-reportsummary');
        $output .= self::render_metric_card(get_string('totalevents', 'quizaccess_webcamguard'), $eventcount);
        $output .= self::render_metric_card(get_string('totalviolations', 'quizaccess_webcamguard'), $violationcount,
            'danger');
        $output .= self::render_metric_card(get_string('violatedattempts', 'quizaccess_webcamguard'), $violatedattempts,
            'warning');

        $output .= \html_writer::start_div('quizaccess-webcamguard-summarycard quizaccess-webcamguard-summarytypes');
        $output .= \html_writer::div(get_string('topviolationtypes', 'quizaccess_webcamguard'),
            'quizaccess-webcamguard-summarylabel');

        if (empty($violationtypes)) {
            $output .= \html_writer::div(get_string('noviolations', 'quizaccess_webcamguard'),
                'quizaccess-webcamguard-emptytypes');
        } else {
            $output .= \html_writer::start_tag('ol', ['class' => 'quizaccess-webcamguard-typelist']);
            foreach ($violationtypes as $type) {
                $output .= \html_writer::start_tag('li');
                $output .= \html_writer::span(self::event_name($type->eventtype));
                $output .= \html_writer::span((int)$type->violationcount, 'badge badge-danger');
                $output .= \html_writer::end_tag('li');
            }
            $output .= \html_writer::end_tag('ol');
        }

        $output .= \html_writer::end_div();
        $output .= \html_writer::end_div();

        return $output;
    }

    /**
     * Render report summary CSS.
     *
     * @return string
     */
    protected static function render_summary_styles() {
        return \html_writer::tag('style', '
.quizaccess-webcamguard-reportsummary {
    display: grid;
    grid-template-columns: repeat(3, minmax(150px, 1fr)) minmax(260px, 1.4fr);
    gap: .75rem;
    margin: .75rem 0 1.25rem;
}
.quizaccess-webcamguard-summarycard {
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
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1;
}
.quizaccess-webcamguard-summaryvalue-danger {
    color: #c82333;
}
.quizaccess-webcamguard-summaryvalue-warning {
    color: #8a6200;
}
.quizaccess-webcamguard-typelist {
    margin: .45rem 0 0;
    padding-left: 1.2rem;
}
.quizaccess-webcamguard-typelist li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .2rem 0;
}
.quizaccess-webcamguard-emptytypes {
    margin-top: .45rem;
    color: #5f6b76;
}
@media (max-width: 991.98px) {
    .quizaccess-webcamguard-reportsummary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575.98px) {
    .quizaccess-webcamguard-reportsummary {
        grid-template-columns: 1fr;
    }
}
');
    }

    /**
     * Render live monitor CSS.
     *
     * @return string
     */
    protected static function render_live_monitor_styles() {
        return \html_writer::tag('style', '
.quizaccess-webcamguard-livebar {
    display: flex;
    justify-content: flex-end;
    margin: -.25rem 0 .75rem;
}
.quizaccess-webcamguard-livedashboard .modal-xl {
    max-width: min(1500px, calc(100vw - 32px));
}
.quizaccess-webcamguard-livecontrols {
    display: grid;
    grid-template-columns: minmax(220px, 320px) auto auto auto 1fr;
    gap: .5rem;
    align-items: center;
    margin-bottom: .75rem;
}
.quizaccess-webcamguard-livecount {
    justify-self: end;
    font-size: .9rem;
}
.quizaccess-webcamguard-livegrid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .75rem;
}
.quizaccess-webcamguard-livetile {
    overflow: hidden;
    border: 1px solid #e1e6ef;
    border-radius: 8px;
    background: #fff;
    transition: box-shadow .2s ease, border-color .2s ease;
}
.quizaccess-webcamguard-livetile-flash {
    animation: quizaccess-webcamguard-livetile-flash 1.6s ease-out 1;
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, .35), 0 4px 18px rgba(220, 53, 69, .25);
}
@keyframes quizaccess-webcamguard-livetile-flash {
    0%   { box-shadow: 0 0 0 0 rgba(220, 53, 69, .65); }
    40%  { box-shadow: 0 0 0 12px rgba(220, 53, 69, .15); }
    100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}
.quizaccess-webcamguard-livevideo {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 120px;
    aspect-ratio: 4 / 3;
    background: #111827;
    color: #d9dee7;
    font-size: .88rem;
    text-align: center;
}
.quizaccess-webcamguard-livevideo video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.quizaccess-webcamguard-livebody {
    padding: .65rem .75rem .75rem;
}
.quizaccess-webcamguard-livename {
    overflow: hidden;
    color: #20252b;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.quizaccess-webcamguard-livemeta {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    margin-top: .35rem;
    font-size: .78rem;
}
.quizaccess-webcamguard-livemeta .badge {
    font-size: .72rem;
}
.quizaccess-webcamguard-livestatus {
    margin-top: .45rem;
    color: #5f6b76;
    font-size: .82rem;
}
.quizaccess-webcamguard-liveempty {
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.25rem 1rem 1.5rem;
    border: 1px dashed #cbd3df;
    border-radius: 8px;
    color: #5f6b76;
    text-align: center;
}
.quizaccess-webcamguard-liveempty img {
    width: min(360px, 72vw);
    max-height: 190px;
    object-fit: contain;
    margin-bottom: .65rem;
}
.quizaccess-webcamguard-liveempty-title {
    color: #20252b;
    font-size: 1rem;
    font-weight: 700;
}
.quizaccess-webcamguard-liveempty-body {
    max-width: 560px;
    margin-top: .2rem;
    font-size: .9rem;
}
@media (max-width: 1199.98px) {
    .quizaccess-webcamguard-livegrid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 767.98px) {
    .quizaccess-webcamguard-livecontrols {
        grid-template-columns: 1fr;
    }
    .quizaccess-webcamguard-livecount {
        justify-self: start;
    }
    .quizaccess-webcamguard-livegrid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575.98px) {
    .quizaccess-webcamguard-livegrid {
        grid-template-columns: 1fr;
    }
}
');
    }

    /**
     * Render one metric card.
     *
     * @param string $label Card label.
     * @param int $value Card value.
     * @param string $tone Visual tone.
     * @return string
     */
    protected static function render_metric_card($label, $value, $tone = '') {
        $valueclass = 'quizaccess-webcamguard-summaryvalue';
        if ($tone !== '') {
            $valueclass .= ' quizaccess-webcamguard-summaryvalue-' . $tone;
        }

        $output = \html_writer::start_div('quizaccess-webcamguard-summarycard');
        $output .= \html_writer::div(s($label), 'quizaccess-webcamguard-summarylabel');
        $output .= \html_writer::div((string)(int)$value, $valueclass);
        $output .= \html_writer::end_div();
        return $output;
    }

    /**
     * Render review table.
     *
     * @param array $rows Report rows.
     * @param int $cmid Course module id.
     * @return string
     */
    public static function render(array $rows, $cmid) {
        if (empty($rows)) {
            return \html_writer::div(get_string('noreviews', 'quizaccess_webcamguard'), 'alert alert-info');
        }

        $table = new \html_table();
        $table->head = [
            get_string('student', 'quizaccess_webcamguard'),
            get_string('attempt', 'quizaccess_webcamguard'),
            get_string('status', 'quizaccess_webcamguard'),
            get_string('eventcount', 'quizaccess_webcamguard'),
            get_string('violationcount', 'quizaccess_webcamguard'),
            get_string('riskscore', 'quizaccess_webcamguard'),
            get_string('topviolationtype', 'quizaccess_webcamguard'),
            get_string('actions', 'quizaccess_webcamguard'),
        ];

        foreach ($rows as $row) {
            $detailurl = new \moodle_url('/mod/quiz/accessrule/webcamguard/attempt.php', [
                'cmid' => $cmid,
                'attemptid' => $row->attemptid,
            ]);
            $table->data[] = [
                fullname($row),
                $row->attempt,
                get_string($row->status, 'quizaccess_webcamguard'),
                $row->eventcount,
                $row->violationcount,
                self::render_risk_score((int)$row->riskscore),
                self::render_top_violation($row),
                \html_writer::link($detailurl, get_string('viewdetail', 'quizaccess_webcamguard')),
            ];
        }

        return \html_writer::table($table);
    }

    /**
     * Render risk score badge.
     *
     * @param int $score Score.
     * @return string
     */
    protected static function render_risk_score($score) {
        $level = self::risk_level($score);
        return \html_writer::span($level['label'] . ' (' . $score . ')', 'badge badge-' . $level['badge']);
    }

    /**
     * Risk level metadata.
     *
     * @param int $score Score.
     * @return array
     */
    protected static function risk_level($score) {
        if ($score <= 0) {
            return ['label' => get_string('risknone', 'quizaccess_webcamguard'), 'badge' => 'success'];
        }
        if ($score <= 4) {
            return ['label' => get_string('risklow', 'quizaccess_webcamguard'), 'badge' => 'info'];
        }
        if ($score <= 12) {
            return ['label' => get_string('riskmedium', 'quizaccess_webcamguard'), 'badge' => 'warning'];
        }
        return ['label' => get_string('riskhigh', 'quizaccess_webcamguard'), 'badge' => 'danger'];
    }

    /**
     * Render the most frequent violation for one attempt.
     *
     * @param object $row Report row.
     * @return string
     */
    protected static function render_top_violation($row) {
        if (empty($row->topviolationtype)) {
            return '-';
        }

        return \html_writer::span(self::event_name($row->topviolationtype), 'mr-2') .
            \html_writer::span((int)$row->topviolationcount, 'badge badge-danger');
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
