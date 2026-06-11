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
 * AI Quiz Generator plugin settings.
 *
 * @package    local_aiquizgen
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Load the test connection class.
require_once(__DIR__ . '/classes/admin_setting_testconnection.php');

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aiquizgen', get_string('pluginname', 'local_aiquizgen'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading(
        'local_aiquizgen/openaiheading',
        get_string('openaisettings', 'local_aiquizgen'),
        get_string('openaisettings_desc', 'local_aiquizgen')
    ));

    $settings->add(new admin_setting_configtext(
        'local_aiquizgen/apibaseurl',
        get_string('apibaseurl', 'local_aiquizgen'),
        get_string('apibaseurl_desc', 'local_aiquizgen'),
        'http://localhost:8000',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_aiquizgen/apikey',
        get_string('apikey', 'local_aiquizgen'),
        get_string('apikey_desc', 'local_aiquizgen'),
        ''
    ));

    // Test Connection button.
    $settings->add(new \local_aiquizgen\admin_setting_testconnection(
        'local_aiquizgen/testconnection',
        get_string('testconnection', 'local_aiquizgen'),
        get_string('testconnection_help', 'local_aiquizgen')
    ));

    $settings->add(new admin_setting_configtext(
        'local_aiquizgen/maxquestions',
        get_string('maxquestions', 'local_aiquizgen'),
        get_string('maxquestions_desc', 'local_aiquizgen'),
        '20',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_aiquizgen/enablelog',
        get_string('enablelog', 'local_aiquizgen'),
        get_string('enablelog_desc', 'local_aiquizgen'),
        1
    ));
}
