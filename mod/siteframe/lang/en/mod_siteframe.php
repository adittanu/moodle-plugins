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
 * Language strings for mod_siteframe
 *
 * @package     mod_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['modulename'] = 'SiteFrame';
$string['modulenameplural'] = 'SiteFrames';
$string['modulename_help'] = 'Embed an external website as an iframe within a course.';
$string['pluginadministration'] = 'SiteFrame administration';
$string['pluginname'] = 'SiteFrame';

// Form labels.
$string['url'] = 'URL';
$string['url_help'] = 'The URL of the website to embed. Must be in the allowed domains list.';
$string['displaymode'] = 'Display Mode';
$string['displaymode_help'] = 'How the iframe should be displayed within the course.';
$string['displaymode_inline'] = 'Inline (within page)';
$string['displaymode_fullscreen'] = 'Fullscreen (fills viewport)';
$string['displaymode_responsive'] = 'Responsive (adapts to container)';
$string['height'] = 'Height (px)';
$string['height_help'] = 'iframe height in pixels. Default is 600.';
$string['width'] = 'Width';
$string['width_help'] = 'iframe width (e.g. 100%, 800px). Default is 100%.';
$string['scrolling'] = 'Scrolling';
$string['scrolling_auto'] = 'Auto';
$string['scrolling_yes'] = 'Yes';
$string['scrolling_no'] = 'No';

// Capabilities.
$string['siteframe:view'] = 'View SiteFrame activity';
$string['siteframe:addinstance'] = 'Add a new SiteFrame activity';

// Privacy.
$string['privacy:metadata'] = 'The SiteFrame plugin does not store any personal user data.';

// Events.
$string['eventcoursemoduleviewed'] = 'Course module viewed';
