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
 * Plugin settings page.
 *
 * @package     local_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Admin management page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_siteframe_manage',
        get_string('manage_siteframes', 'local_siteframe'),
        new moodle_url('/local/siteframe/manage.php'),
        'local/siteframe:manage'
    ));

    $settings = new admin_settingpage('local_siteframe', get_string('pluginname', 'local_siteframe'));
    $ADMIN->add('localplugins', $settings);

    // Heading.
    $settings->add(new admin_setting_heading(
        'local_siteframe/heading',
        get_string('settings_heading', 'local_siteframe'),
        get_string('settings_heading_desc', 'local_siteframe')
    ));

    // Enable/Disable.
    $settings->add(new admin_setting_configcheckbox(
        'local_siteframe/enabled',
        get_string('enabled', 'local_siteframe'),
        get_string('enabled_desc', 'local_siteframe'),
        1
    ));


    // Allowed domains.
    $settings->add(new admin_setting_configtextarea(
        'local_siteframe/allowed_domains',
        get_string('allowed_domains', 'local_siteframe'),
        get_string('allowed_domains_desc', 'local_siteframe'),
        '',
        PARAM_TEXT
    ));

    // Display mode toggles.
    $settings->add(new admin_setting_heading(
        'local_siteframe/display_modes_heading',
        get_string('settings_heading', 'local_siteframe'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_siteframe/allow_fullpage',
        get_string('allow_fullpage', 'local_siteframe'),
        get_string('allow_fullpage_desc', 'local_siteframe'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_siteframe/allow_coursepage',
        get_string('allow_coursepage', 'local_siteframe'),
        get_string('allow_coursepage_desc', 'local_siteframe'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_siteframe/allow_widget',
        get_string('allow_widget', 'local_siteframe'),
        get_string('allow_widget_desc', 'local_siteframe'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_siteframe/allow_modal',
        get_string('allow_modal', 'local_siteframe'),
        get_string('allow_modal_desc', 'local_siteframe'),
        1
    ));

    // Widget settings.
    $settings->add(new admin_setting_heading(
        'local_siteframe/widget_heading',
        get_string('widget_title', 'local_siteframe'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'local_siteframe/widget_position',
        get_string('widget_position', 'local_siteframe'),
        get_string('widget_position_desc', 'local_siteframe'),
        'bottom-right',
        [
            'bottom-right' => get_string('widget_position_bottomright', 'local_siteframe'),
            'bottom-left'  => get_string('widget_position_bottomleft', 'local_siteframe'),
            'top-right'    => get_string('widget_position_topright', 'local_siteframe'),
            'top-left'     => get_string('widget_position_topleft', 'local_siteframe'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_siteframe/widget_icon',
        get_string('widget_icon', 'local_siteframe'),
        get_string('widget_icon_desc', 'local_siteframe'),
        '🌐'
    ));

    $settings->add(new admin_setting_configtext(
        'local_siteframe/widget_title',
        get_string('widget_title', 'local_siteframe'),
        get_string('widget_title_desc', 'local_siteframe'),
        'SiteFrame'
    ));

    // Sandbox flags.
    $settings->add(new admin_setting_configtextarea(
        'local_siteframe/sandbox_flags',
        get_string('sandbox_flags', 'local_siteframe'),
        get_string('sandbox_flags_desc', 'local_siteframe'),
        'allow-scripts allow-same-origin allow-popups',
        PARAM_TEXT
    ));

}
