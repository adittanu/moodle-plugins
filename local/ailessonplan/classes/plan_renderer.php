<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ailessonplan;

/**
 * HTML renderer helper for generated course skeletons.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class plan_renderer {
    /**
     * Render a generated course skeleton array.
     *
     * @param array $plan
     * @return string
     */
    public static function render_plan(array $plan): string {
        $sections = publisher::plan_sections($plan);
        $html = '';
        $title = $plan['course_title'] ?? $plan['title'] ?? get_string('previewtitle', 'local_ailessonplan');
        $summary = $plan['course_summary'] ?? $plan['description'] ?? '';

        $html .= \html_writer::tag('h3', format_string($title));

        if (trim((string)$summary) !== '') {
            $html .= \html_writer::tag('h4', get_string('coursesummary', 'local_ailessonplan'));
            $html .= \html_writer::tag('p', s((string)$summary));
        }

        if (!empty($plan['learning_outcomes']) && is_array($plan['learning_outcomes'])) {
            $html .= \html_writer::tag('h4', get_string('learningoutcomes', 'local_ailessonplan'));
            $html .= self::render_simple_list($plan['learning_outcomes']);
        }

        if (!empty($sections)) {
            $html .= \html_writer::tag('h4', get_string('courseskeleton', 'local_ailessonplan'));
            foreach ($sections as $section) {
                $html .= self::render_section($section);
            }
        }

        $html .= \html_writer::tag('details',
            \html_writer::tag('summary', get_string('copyjson', 'local_ailessonplan')) .
            \html_writer::tag('pre', s(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), ['style' => 'white-space:pre-wrap;']),
            ['class' => 'mt-3']
        );

        return \html_writer::div($html, 'local-ailessonplan-preview');
    }

    /**
     * Render one section preview.
     *
     * @param array $section
     * @return string
     */
    private static function render_section(array $section): string {
        $week = (int)($section['week'] ?? 0);
        $title = trim((string)($section['title'] ?? ''));
        $heading = get_string('week', 'local_ailessonplan') . ' ' . $week . ($title !== '' ? ': ' . $title : '');
        $html = \html_writer::tag('h5', s($heading));

        if (!empty($section['summary'])) {
            $html .= \html_writer::tag('p', s((string)$section['summary']), ['class' => 'mb-2']);
        }

        if (!empty($section['objectives']) && is_array($section['objectives'])) {
            $html .= \html_writer::tag('strong', get_string('objectives', 'local_ailessonplan'));
            $html .= self::render_simple_list($section['objectives'], ['class' => 'mb-2']);
        }

        $activities = array_values((array)($section['activities'] ?? []));
        if (!empty($activities)) {
            $table = new \html_table();
            $table->head = [
                get_string('activitytype', 'local_ailessonplan'),
                get_string('activitytitle', 'local_ailessonplan'),
                get_string('purpose', 'local_ailessonplan'),
                get_string('previewchange', 'local_ailessonplan'),
            ];
            $table->attributes['class'] = 'generaltable table table-bordered table-sm mb-0';

            foreach ($activities as $activity) {
                if (!is_array($activity)) {
                    continue;
                }
                $table->data[] = [
                    s((string)($activity['mod'] ?? 'page')),
                    s((string)($activity['title'] ?? get_string('activity', 'local_ailessonplan'))),
                    s((string)($activity['purpose'] ?? '')),
                    s(self::activity_preview_text($activity)),
                ];
            }
            $html .= \html_writer::table($table);
        }

        return \html_writer::div($html, 'border rounded p-3 mb-3');
    }

    /**
     * Return compact preview text for an activity.
     *
     * @param array $activity
     * @return string
     */
    private static function activity_preview_text(array $activity): string {
        foreach (['student_instruction', 'instruction', 'prompt', 'intro', 'text', 'description'] as $key) {
            if (!empty($activity[$key]) && is_scalar($activity[$key])) {
                return \core_text::substr((string)$activity[$key], 0, 180);
            }
        }
        if (($activity['mod'] ?? '') === 'quiz') {
            return get_string('quizplaceholder', 'local_ailessonplan');
        }
        if (($activity['mod'] ?? '') === 'scorm') {
            return get_string('scormplaceholder', 'local_ailessonplan');
        }
        return '';
    }

    /**
     * Render a list of scalar or structured items.
     *
     * @param array $items
     * @param array $attributes
     * @return string
     */
    private static function render_simple_list(array $items, array $attributes = []): string {
        $html = '';
        foreach ($items as $item) {
            $html .= \html_writer::tag('li', s(self::format_item($item)));
        }
        return \html_writer::tag('ul', $html, $attributes);
    }

    /**
     * Format scalar/structured values for preview lists.
     *
     * @param mixed $item
     * @return string
     */
    private static function format_item($item): string {
        if (is_scalar($item)) {
            return (string)$item;
        }
        if (is_array($item)) {
            $parts = [];
            foreach (['type', 'title', 'description', 'weight'] as $key) {
                if (isset($item[$key]) && is_scalar($item[$key]) && trim((string)$item[$key]) !== '') {
                    $parts[] = (string)$item[$key];
                }
            }
            if (!empty($parts)) {
                return implode(' - ', $parts);
            }
        }
        return json_encode($item, JSON_UNESCAPED_UNICODE);
    }
}
