<?php
namespace local_quiz_stats_cache\hook;

defined('MOODLE_INTERNAL') || die();

/**
 * Inject a "Fast Recalculate" button on the quiz statistics report page.
 */
class before_footer_callback {

    /**
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function inject_button(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $USER;

        // Only inject on quiz statistics report page.
        $url = $PAGE->url;
        if (!$url || $url->get_path() !== '/mod/quiz/report.php') {
            return;
        }
        if ($url->get_param('mode') !== 'statistics') {
            return;
        }

        $cmid = $url->get_param('id');
        if (!$cmid) {
            return;
        }

        // Check permissions.
        $cm = get_coursemodule_from_id('quiz', $cmid);
        if (!$cm) {
            return;
        }
        $context = \context_module::instance($cm->id);
        if (!has_capability('mod/quiz:viewreports', $context)) {
            return;
        }

        $sesskey = sesskey();
        $fasturl = new \moodle_url('/local/quiz_stats_cache/recalculate.php', [
            'quizid' => $cm->instance,
            'sesskey' => $sesskey,
        ]);
        $fastforceurl = new \moodle_url('/local/quiz_stats_cache/recalculate.php', [
            'quizid' => $cm->instance,
            'sesskey' => $sesskey,
            'force' => 1,
        ]);

        $html = \html_writer::start_div('card mb-3', ['style' => 'border-left: 4px solid #28a745;']);
        $html .= \html_writer::start_div('card-body py-2');
        $html .= \html_writer::tag('strong', '⚡ Fast Statistics Calculator', ['class' => 'text-success']);
        $html .= \html_writer::tag('p', 'Recalculate statistics using SQL-based calculator (instant).', ['class' => 'mb-2 small text-muted']);
        $html .= \html_writer::link($fasturl, '⚡ Recalculate (fast)', [
            'class' => 'btn btn-sm btn-success mr-2',
            'title' => 'Only recalculates if there are changes',
        ]);
        $html .= \html_writer::link($fastforceurl, '⚡ Force Recalculate', [
            'class' => 'btn btn-sm btn-outline-success',
            'title' => 'Always recalculates, even if no changes',
        ]);
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();

        $hook->add_html($html);
    }
}
