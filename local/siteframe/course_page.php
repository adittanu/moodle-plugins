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
 * Course page iframe view for SiteFrame.
 *
 * @package     local_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$itemid = optional_param('id', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course, true);

$context = context_course::instance($course->id);
$canedit = has_capability('local/siteframe:configurecourse', $context);
require_capability('local/siteframe:view', $context);

use local_siteframe\domain_helper;

$PAGE->set_url('/local/siteframe/course_page.php', ['courseid' => $courseid, 'id' => $itemid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('course_page', 'local_siteframe'));

$PAGE->navbar->add($course->fullname, new moodle_url('/course/view.php', ['id' => $course->id]));
$PAGE->navbar->add(get_string('course_page', 'local_siteframe'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('course_page', 'local_siteframe'));

if ($itemid > 0) {
    // Show specific item.
    $item = $DB->get_record('local_siteframe_items', ['id' => $itemid], '*', MUST_EXIST);
    $items = [$item];
} else {
    // Show all items for this course + global items.
    $items = $DB->get_records_select(
        'local_siteframe_items',
        '(courseid = :courseid OR courseid = 0) AND visible = 1',
        ['courseid' => $course->id],
        'sortorder ASC'
    );
}

if (empty($items)) {
    echo html_writer::div(get_string('no_items', 'local_siteframe'), 'alert alert-info');
} else {
    $hasmodal = false;
    foreach ($items as $item) {
        $url = domain_helper::sanitize_url($item->url);
        if ($url === false || !domain_helper::is_domain_allowed($url)) {
            echo html_writer::div(
                get_string('domain_not_allowed', 'local_siteframe'),
                'alert alert-warning'
            );
            continue;
        }

        $height = $item->height > 0 ? $item->height . 'px' : '600px';
        $width = !empty($item->width) ? $item->width : '100%';
        $sandbox = domain_helper::get_sandbox_attr();

        echo $OUTPUT->heading(format_string($item->name), 3);

        if ($item->displaymode === 'modal') {
            // Render a trigger button; the AMD module opens the modal on click.
            $hasmodal = true;
            echo html_writer::tag('button',
                get_string('widget_open', 'local_siteframe'),
                [
                    'class'       => 'siteframe-modal-trigger btn btn-primary mb-2',
                    'data-url'    => $url,
                    'data-title'  => format_string($item->name),
                    'data-sandbox' => $sandbox,
                ]
            );
        } else {
            // Inline / fullpage / coursepage — render iframe directly.
            echo html_writer::tag('iframe', '', [
                'src'         => $url,
                'width'       => $width,
                'height'      => $height,
                'frameborder' => '0',
                'allowfullscreen' => 'true',
                'sandbox'     => $sandbox,
                'scrolling'   => $item->scrolling,
                'class'       => 'siteframe-iframe siteframe-display-coursepage',
            ]);
        }
        echo html_writer::tag('hr', '');
    }
    // Load modal AMD module only when at least one modal item exists.
    if ($hasmodal) {
        $PAGE->requires->js_call_amd('local_siteframe/modal_launcher', 'init');
    }
}

echo $OUTPUT->footer();
