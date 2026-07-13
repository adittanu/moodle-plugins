<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Legacy before_footer callback for Moodle 4.x.
 * Injects a "Fast Recalculate" button on the quiz statistics report page.
 *
 * @return string HTML to inject before footer, or empty string.
 */
function local_quiz_stats_cache_before_footer() {
    global $PAGE;

    // Only inject on quiz statistics report page.
    $url = $PAGE->url;
    if (!$url) {
        return '';
    }

    $path = $url->get_path();
    if ($path !== '/mod/quiz/report.php') {
        return '';
    }
    if ($url->get_param('mode') !== 'statistics') {
        return '';
    }

    // Use AMD module to inject button via JavaScript.
    $PAGE->requires->js_call_amd('local_quiz_stats_cache/inject_button', 'init');
    return '';
}

/**
 * Build the button HTML.
 */
function local_quiz_stats_cache_inject_button() {
    global $PAGE;

    // Only inject on quiz statistics report page.
    $url = $PAGE->url;
    if (!$url) {
        return '';
    }

    $path = $url->get_path();
    if ($path !== '/mod/quiz/report.php') {
        return '';
    }
    if ($url->get_param('mode') !== 'statistics') {
        return '';
    }

    $cmid = $url->get_param('id');
    if (!$cmid) {
        return '';
    }

    // Check permissions.
    $cm = get_coursemodule_from_id('quiz', $cmid);
    if (!$cm) {
        return '';
    }
    $context = context_module::instance($cm->id);
    if (!has_capability('mod/quiz:viewreports', $context)) {
        return '';
    }

    $sesskey = sesskey();
    $fasturl = new moodle_url('/local/quiz_stats_cache/recalculate.php', [
        'quizid' => $cm->instance,
        'sesskey' => $sesskey,
    ]);
    $fastforceurl = new moodle_url('/local/quiz_stats_cache/recalculate.php', [
        'quizid' => $cm->instance,
        'sesskey' => $sesskey,
        'force' => 1,
    ]);

    $html = html_writer::start_div('card mb-3', ['style' => 'border-left: 4px solid #28a745;']);
    $html .= html_writer::start_div('card-body py-2');
    $html .= html_writer::tag('strong', '&#9889; Fast Statistics Calculator', ['class' => 'text-success']);
    $html .= html_writer::tag('p', 'Recalculate statistics using SQL-based calculator (instant).', ['class' => 'mb-2 small text-muted']);
    $html .= html_writer::link($fasturl, '&#9889; Recalculate (fast)', [
        'class' => 'btn btn-sm btn-success mr-2',
        'title' => 'Only recalculates if there are changes',
    ]);
    $html .= html_writer::link($fastforceurl, '&#9889; Force Recalculate', [
        'class' => 'btn btn-sm btn-outline-success',
        'title' => 'Always recalculates, even if no changes',
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    return $html;
}
