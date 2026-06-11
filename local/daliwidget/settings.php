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
 * @package     local_daliwidget
 * @copyright   2024 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_daliwidget_global_knowledge',
        get_string('global_knowledge_base', 'local_daliwidget'),
        new moodle_url('/local/daliwidget/global_knowledge.php'),
        'moodle/site:config'
    ));

    $settings = new admin_settingpage('local_daliwidget', get_string('pluginname', 'local_daliwidget'));
    $ADMIN->add('localplugins', $settings);

    // Heading
    $settings->add(new admin_setting_heading(
        'local_daliwidget/heading',
        get_string('settings_heading', 'local_daliwidget'),
        get_string('settings_heading_desc', 'local_daliwidget')
    ));

    // Enable/Disable widget
    $settings->add(new admin_setting_configcheckbox(
        'local_daliwidget/enabled',
        get_string('enabled', 'local_daliwidget'),
        get_string('enabled_desc', 'local_daliwidget'),
        1 // Default enabled
    ));

    // API Key
    $settings->add(new admin_setting_configpasswordunmask(
        'local_daliwidget/apikey',
        get_string('apikey', 'local_daliwidget'),
        get_string('apikey_desc', 'local_daliwidget'),
        ''
    ));

    // Base URL
    $settings->add(new admin_setting_configtext(
        'local_daliwidget/baseurl',
        get_string('baseurl', 'local_daliwidget'),
        get_string('baseurl_desc', 'local_daliwidget'),
        'https://dali-app.test'
    ));

    // Max upload size in MB for knowledge sync/document uploads.
    $settings->add(new admin_setting_configtext(
        'local_daliwidget/maxuploadmb',
        get_string('maxuploadmb', 'local_daliwidget'),
        get_string('maxuploadmb_desc', 'local_daliwidget'),
        20,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_daliwidget/signed_url_enabled',
        get_string('signed_url_enabled', 'local_daliwidget'),
        get_string('signed_url_enabled_desc', 'local_daliwidget'),
        0
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_daliwidget/download_secret',
        get_string('download_secret', 'local_daliwidget'),
        get_string('download_secret_desc', 'local_daliwidget'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_daliwidget/signed_url_baseurl',
        get_string('signed_url_baseurl', 'local_daliwidget'),
        get_string('signed_url_baseurl_desc', 'local_daliwidget'),
        '',
        PARAM_URL
    ));

    // Sync Mode
    $settings->add(new admin_setting_configselect(
        'local_daliwidget/sync_mode',
        get_string('sync_mode', 'local_daliwidget'),
        get_string('sync_mode_desc', 'local_daliwidget'),
        'async', // default
        [
            'sync'  => get_string('sync_mode_sync', 'local_daliwidget'),
            'async' => get_string('sync_mode_async', 'local_daliwidget'),
        ]
    ));

    // Course Scope Settings Heading
    $settings->add(new admin_setting_heading(
        'local_daliwidget/course_scope_heading',
        get_string('course_scope_heading', 'local_daliwidget'),
        get_string('course_scope_heading_desc', 'local_daliwidget')
    ));

    // Strict Course Mode
    $settings->add(new admin_setting_configcheckbox(
        'local_daliwidget/strict_course_mode',
        get_string('strict_course_mode', 'local_daliwidget'),
        get_string('strict_course_mode_desc', 'local_daliwidget'),
        0 // Default disabled
    ));

    // Debug Mode
    $settings->add(new admin_setting_configcheckbox(
        'local_daliwidget/debug_mode',
        get_string('debug_mode', 'local_daliwidget'),
        get_string('debug_mode_desc', 'local_daliwidget'),
        0 // Default disabled
    ));
}
