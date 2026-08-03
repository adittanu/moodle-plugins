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
 * Language strings for local_siteframe
 *
 * @package     local_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'SiteFrame';
$string['siteframe:view'] = 'View SiteFrame content';
$string['siteframe:manage'] = 'Manage SiteFrame items';
$string['siteframe:configurecourse'] = 'Configure SiteFrame for course';

// Settings
$string['settings_heading'] = 'SiteFrame Settings';
$string['settings_heading_desc'] = 'Configure iframe embedding settings for SiteFrame.';
$string['enabled'] = 'Enable SiteFrame';
$string['enabled_desc'] = 'When enabled, SiteFrame features are available across the site.';
$string['default_url'] = 'Default URL';
$string['default_url_desc'] = 'Default iframe URL used globally when no specific URL is configured.';
$string['allowed_domains'] = 'Allowed Domains';
$string['allowed_domains_desc'] = 'Domain allowlist, one per line (e.g. example.com). Leave empty to allow all domains (admin responsibility).';
$string['allow_fullpage'] = 'Allow Full Page mode';
$string['allow_fullpage_desc'] = 'Enable the full-page display mode for SiteFrame items.';
$string['allow_coursepage'] = 'Allow Course Page mode';
$string['allow_coursepage_desc'] = 'Enable the course-page display mode.';
$string['allow_widget'] = 'Allow Floating Widget mode';
$string['allow_widget_desc'] = 'Enable the floating widget display mode.';
$string['allow_modal'] = 'Allow Modal/Lightbox mode';
$string['allow_modal_desc'] = 'Enable the modal/lightbox display mode.';
$string['widget_position'] = 'Widget Position';
$string['widget_position_desc'] = 'Corner position of the floating widget button.';
$string['widget_position_bottomright'] = 'Bottom Right';
$string['widget_position_bottomleft'] = 'Bottom Left';
$string['widget_position_topright'] = 'Top Right';
$string['widget_position_topleft'] = 'Top Left';
$string['widget_icon'] = 'Widget Icon';
$string['widget_icon_desc'] = 'Icon or emoji displayed on the widget button.';
$string['widget_title'] = 'Widget Title';
$string['widget_title_desc'] = 'Title shown in the widget panel header.';
$string['sandbox_flags'] = 'Sandbox Flags';
$string['sandbox_flags_desc'] = 'iframe sandbox attribute flags (space-separated). Example: allow-scripts allow-same-origin allow-popups';
$string['extra_allowed_urls'] = 'Extra Allowed URLs';
$string['extra_allowed_urls_desc'] = 'Additional URLs teachers can choose from, one per line. Format: label|url';

// Display modes
$string['displaymode_fullpage'] = 'Full Page';
$string['displaymode_coursepage'] = 'Course Page';
$string['displaymode_widget'] = 'Floating Widget';
$string['displaymode_modal'] = 'Modal/Lightbox';

// Manage page
$string['content'] = 'Content and placement';
$string['manage_siteframes'] = 'Manage SiteFrame Items';
$string['manage_heading'] = 'SiteFrame Items';
$string['add_siteframe'] = 'Add SiteFrame Item';
$string['edit_siteframe'] = 'Edit SiteFrame Item';
$string['item_name'] = 'Name';
$string['item_name_desc'] = 'Display name for this SiteFrame item.';
$string['item_url'] = 'URL';
$string['item_url_desc'] = 'The iframe source URL.';
$string['item_displaymode'] = 'Display Mode';
$string['item_displaymode_desc'] = 'How this iframe should be displayed.';
$string['item_courseid'] = 'Course';
$string['item_courseid_desc'] = 'Leave empty or 0 for global (all courses). Select a course for course-specific.';
$string['item_height'] = 'Height (px)';
$string['item_height_desc'] = 'iframe height in pixels. 0 = auto/100%.';
$string['item_width'] = 'Width';
$string['item_width_desc'] = 'iframe width (e.g. 100%, 800px).';
$string['item_scrolling'] = 'Scrolling';
$string['item_scrolling_desc'] = 'Scrolling behavior of the iframe.';
$string['item_visible'] = 'Visible';
$string['item_visible_desc'] = 'Show or hide this item.';
$string['item_saved'] = 'SiteFrame item saved successfully.';
$string['item_deleted'] = 'SiteFrame item deleted.';
$string['no_items'] = 'No SiteFrame items configured yet.';
$string['actions'] = 'Actions';
$string['placement'] = 'Placement';
$string['preview'] = 'Preview';
$string['scope'] = 'Scope';
$string['scope_global'] = 'Global (all courses)';
$string['scrolling_auto'] = 'Automatic';
$string['status_active'] = 'Active';
$string['status_hidden'] = 'Hidden';
$string['error_widget_exists'] = 'An active floating widget already exists for this scope. Edit, hide, or delete it first.';
$string['visibility_updated'] = 'Visibility updated.';

// View page
$string['view_title'] = 'SiteFrame';
$string['course_page'] = 'SiteFrame';
$string['iframe_not_allowed'] = 'This site does not allow embedding. Contact the site administrator.';
$string['domain_not_allowed'] = 'The URL domain is not in the allowed domains list.';
$string['url_invalid'] = 'The provided URL is not valid.';
$string['error_mode_disabled'] = 'This display mode is disabled in settings.';
$string['error_course_not_found'] = 'Course does not exist.';
$string['iframe_blocked'] = 'This site cannot be embedded in an iframe (blocked by X-Frame-Options or CSP). Try opening it in a new tab.';
$string['item_hidden'] = 'This SiteFrame item is hidden.';
$string['sortorder'] = 'Sort order';

// Widget
$string['widget_open'] = 'Open SiteFrame';
$string['widget_close'] = 'Close';

// Privacy
$string['privacy:metadata'] = 'The SiteFrame plugin does not store any personal user data.';
