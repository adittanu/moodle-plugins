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
 * SiteFrame activity view page.
 *
 * @package     mod_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('siteframe', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$siteframe = $DB->get_record('siteframe', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/siteframe:view', $context);

// Validate URL.
use local_siteframe\domain_helper;
$url = domain_helper::sanitize_url($siteframe->url);
if ($url === false) {
    throw new moodle_exception('url_invalid', 'local_siteframe');
}
if (!domain_helper::is_domain_allowed($url)) {
    throw new moodle_exception('domain_not_allowed', 'local_siteframe');
}

// Trigger module viewed event.
$event = \mod_siteframe\event\course_module_viewed::create([
    'objectid' => $siteframe->id,
    'context' => $context,
]);
$event->add_record_snapshot('siteframe', $siteframe);
$event->trigger();

// Completion.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Build iframe.
$height = $siteframe->height > 0 ? (int) $siteframe->height : 600;
$width = domain_helper::sanitize_css_dimension($siteframe->width ?? '', '100%');
$sandbox = domain_helper::get_sandbox_attr();

$iframe = html_writer::tag('iframe', '', [
    'src'       => $url,
    'width'     => $width,
    'height'    => $height,
    'frameborder' => '0',
    'allowfullscreen' => 'true',
    'sandbox'   => $sandbox,
    'scrolling' => $siteframe->scrolling,
    'class'     => 'siteframe-iframe siteframe-display-' . $siteframe->displaymode,
]);

$PAGE->set_url('/mod/siteframe/view.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($siteframe->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($siteframe->name));
echo format_module_intro('siteframe', $siteframe, $cm->id);
echo $iframe;
echo $OUTPUT->footer();
