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
 * Full page iframe view for SiteFrame.
 *
 * @package     local_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$item = $DB->get_record('local_siteframe_items', ['id' => $id], '*', MUST_EXIST);

require_login();

$context = context_system::instance();
require_capability('local/siteframe:view', $context);

// Validate domain.
use local_siteframe\domain_helper;
$url = domain_helper::sanitize_url($item->url);
if ($url === false) {
    throw new moodle_exception('url_invalid', 'local_siteframe');
}
if (!domain_helper::is_domain_allowed($url)) {
    throw new moodle_exception('domain_not_allowed', 'local_siteframe');
}

$PAGE->set_url('/local/siteframe/view.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($item->name));
$PAGE->set_heading(format_string($item->name));
$PAGE->set_pagelayout('standard');

$PAGE->navbar->add(get_string('pluginname', 'local_siteframe'), new moodle_url('/local/siteframe/'));
$PAGE->navbar->add(format_string($item->name));

$height = $item->height > 0 ? $item->height . 'px' : '100%';
$width = !empty($item->width) ? $item->width : '100%';
$sandbox = domain_helper::get_sandbox_attr();

echo $OUTPUT->header();
echo html_writer::tag('iframe', '', [
    'src'       => $url,
    'width'     => $width,
    'height'    => $height,
    'frameborder' => '0',
    'allowfullscreen' => 'true',
    'sandbox'   => $sandbox,
    'scrolling' => $item->scrolling,
    'class'     => 'siteframe-iframe siteframe-display-fullpage',
    'style'     => 'border: none; width: ' . $width . '; height: ' . $height . ';',
]);
echo $OUTPUT->footer();
