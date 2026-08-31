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
     * @param int $candidatecount Initial live candidate count.
     * @return string
     */
    public static function render_live_monitor($candidatecount = 0) {
        $output = self::render_live_monitor_styles();
        $output .= \html_writer::start_div('quizaccess-webcamguard-livebar');
        $label = get_string('livemonitor', 'quizaccess_webcamguard');
        if ($candidatecount > 0) {
            $label .= ' <span class="badge badge-light" data-region="webcamguard-live-badge">' . $candidatecount . '</span>';
        } else {
            $label .= ' <span class="badge badge-light" data-region="webcamguard-live-badge" style="display:none;">0</span>';
        }
        $output .= \html_writer::tag('button', $label, [
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
        $output .= \html_writer::label(get_string('livesearch', 'quizaccess_webcamguard'),
            'quizaccess-webcamguard-live-search', false, ['class' => 'sr-only']);
        $output .= \html_writer::tag('input', '', [
            'type' => 'search',
            'id' => 'quizaccess-webcamguard-live-search',
            'class' => 'form-control',
            'placeholder' => get_string('livesearchplaceholder', 'quizaccess_webcamguard'),
            'data-region' => 'webcamguard-live-search',
            'autocomplete' => 'off',
        ]);
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

        // Global warning bar — send to all visible participants.
        $output .= \html_writer::start_div('quizaccess-webcamguard-warningbar');
        $output .= \html_writer::tag('input', '', [
            'type' => 'text',
            'class' => 'form-control form-control-sm',
            'placeholder' => get_string('sendwarningplaceholder', 'quizaccess_webcamguard'),
            'data-region' => 'webcamguard-global-warning',
        ]);
        $output .= \html_writer::tag('button', get_string('sendwarningall', 'quizaccess_webcamguard'), [
            'type' => 'button',
            'class' => 'btn btn-sm btn-warning',
            'data-action' => 'webcamguard-send-warning-all',
        ]);
        $output .= \html_writer::end_div();


        $output .= \html_writer::div('', 'quizaccess-webcamguard-livegrid', [
            'data-region' => 'webcamguard-live-grid',
        ]);
        $output .= \html_writer::start_div('quizaccess-webcamguard-livepagination', [
            'data-region' => 'webcamguard-live-pagination',
        ]);
        $output .= \html_writer::tag('button', '&lsaquo;', [
            'type' => 'button',
            'class' => 'btn btn-outline-secondary quizaccess-webcamguard-pagebutton',
            'data-action' => 'webcamguard-live-prev',
            'aria-label' => get_string('previous'),
        ]);
        $output .= \html_writer::start_tag('span', ['class' => 'quizaccess-webcamguard-pageinfo']);
        $output .= \html_writer::span(get_string('livepage', 'quizaccess_webcamguard'),
            'quizaccess-webcamguard-pagelabel');
        $output .= ' <strong data-region="webcamguard-live-page">1</strong>';
        $output .= ' <span class="text-muted">' . get_string('livepageof', 'quizaccess_webcamguard') . '</span> ';
        $output .= '<strong data-region="webcamguard-live-pages">1</strong>';
        $output .= \html_writer::end_tag('span');
        $output .= \html_writer::tag('button', '&rsaquo;', [
            'type' => 'button',
            'class' => 'btn btn-outline-secondary quizaccess-webcamguard-pagebutton',
            'data-action' => 'webcamguard-live-next',
            'aria-label' => get_string('next'),
        ]);
        $output .= \html_writer::end_div();
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
        return '';
    }

    /**
     * Render live monitor CSS.
     *
     * @return string
     */
    protected static function render_live_monitor_styles() {
        return \html_writer::tag('style', '
.quizaccess-webcamguard-livevideo {
    position: relative;
}
.quizaccess-webcamguard-livevideo.quizaccess-webcamguard-loading::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 28px;
    height: 28px;
    margin: -14px 0 0 -14px;
    border: 3px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: quizaccess-webcamguard-spin .7s linear infinite;
    z-index: 2;
}
.quizaccess-webcamguard-livepagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    min-height: 64px;
    padding: 14px 0 4px;
}
.quizaccess-webcamguard-pagebutton {
    width: 36px;
    height: 36px;
    padding: 0;
    border-radius: 8px;
    font-size: 24px;
    line-height: 1;
}
.quizaccess-webcamguard-pagebutton:disabled {
    color: #98a2b3;
    background: #f2f4f7;
    border-color: #eaecf0;
    opacity: 1;
}
.quizaccess-webcamguard-pageinfo {
    min-width: 116px;
    color: #344054;
    text-align: center;
    font-size: 14px;
    font-variant-numeric: tabular-nums;
}
.quizaccess-webcamguard-pagelabel {
    margin-right: 3px;
    color: #667085;
}
@keyframes quizaccess-webcamguard-spin {
    to { transform: rotate(360deg); }
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
