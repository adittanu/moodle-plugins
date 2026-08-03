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
 * Library functions for local_siteframe.
 *
 * @package     local_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend course navigation to add SiteFrame link.
 *
 * @param navigation_node $navigation The navigation node
 * @param stdClass $course The course object
 * @param context $context The course context
 */
function local_siteframe_extend_navigation_course($navigation, $course, $context) {
    if (!get_config('local_siteframe', 'enabled')) {
        return;
    }
    if (!has_capability('local/siteframe:view', $context)) {
        return;
    }

    $url = new moodle_url('/local/siteframe/course_page.php', ['courseid' => $course->id]);
    $navigation->add(
        get_string('course_page', 'local_siteframe'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'siteframe_course',
        new pix_icon('i/iframe', '')
    );
}

/**
 * Legacy callback for before footer (Moodle 4.1 - 4.4).
 * Injects the SiteFrame floating widget into page footer.
 *
 * @return string
 */
function local_siteframe_before_footer() {
    global $PAGE;

    // Only use this callback if hook system is not available (Moodle 4.x).
    if (class_exists('\core\hook\output\before_footer_html_generation')) {
        return '';
    }

    return local_siteframe_render_widget();
}

/**
 * Render the floating widget HTML.
 *
 * @return string HTML for the widget, or empty if disabled.
 */
function local_siteframe_render_widget() {
    global $DB, $PAGE;

    if (!get_config('local_siteframe', 'enabled') || !get_config('local_siteframe', 'allow_widget')) {
        return '';
    }
    $context = context_system::instance();
    if (!has_capability('local/siteframe:view', $context)) {
        return '';
    }
    $pagetype = $PAGE->pagetype ?? '';
    if (preg_match('/^(mod-quiz-attempt|mod-quiz-review|login|admin-search)/', $pagetype)) {
        return '';
    }

    $courseid = !empty($PAGE->course->id) && (int)$PAGE->course->id !== SITEID ? (int)$PAGE->course->id : 0;
    $params = ['mode' => 'widget', 'courseid' => $courseid];
    $item = $DB->get_record_sql(
        "SELECT *
           FROM {local_siteframe_items}
          WHERE displaymode = :mode AND visible = 1 AND (courseid = :courseid OR courseid = 0)
       ORDER BY courseid DESC, sortorder ASC, id ASC",
        $params,
        IGNORE_MULTIPLE
    );
    if (!$item) {
        return '';
    }
    $url = \local_siteframe\domain_helper::sanitize_url($item->url);
    if ($url === false || !\local_siteframe\domain_helper::is_domain_allowed($url)) {
        return '';
    }

    $position = get_config('local_siteframe', 'widget_position') ?: 'bottom-right';
    $icon = get_config('local_siteframe', 'widget_icon') ?: '🌐';
    $title = format_string($item->name);
    $sandbox = \local_siteframe\domain_helper::get_sandbox_attr();
    $config = json_encode([
        'url' => $url,
        'title' => $title,
        'icon' => $icon,
        'position' => $position,
        'sandboxFlags' => $sandbox,
    ]);
    $PAGE->requires->js_call_amd('local_siteframe/widget', 'init', [$config]);

    return html_writer::div(
        html_writer::div(
            html_writer::span($icon, 'siteframe-widget-icon') . html_writer::span($title, 'siteframe-widget-title'),
            'siteframe-widget-button'
        ) .
        html_writer::div(
            html_writer::div($title, 'siteframe-widget-panel-header') .
            html_writer::tag('iframe', '', [
                'src' => 'about:blank',
                'sandbox' => $sandbox,
                'frameborder' => '0',
                'class' => 'siteframe-widget-iframe',
                'loading' => 'lazy',
            ]),
            'siteframe-widget-panel'
        ),
        'siteframe-widget-container siteframe-position-' . $position
    );
}
