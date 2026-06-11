<?php
// This file is part of Moodle - http://moodle.org/

/**
 * AI Lesson Plan plugin settings.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ailessonplan', get_string('pluginname', 'local_ailessonplan'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading(
        'local_ailessonplan/apiheading',
        get_string('apisettings', 'local_ailessonplan'),
        get_string('apisettings_desc', 'local_ailessonplan')
    ));

    $settings->add(new admin_setting_configtext(
        'local_ailessonplan/apibaseurl',
        get_string('apibaseurl', 'local_ailessonplan'),
        get_string('apibaseurl_desc', 'local_ailessonplan'),
        'http://localhost:8000',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_ailessonplan/apikey',
        get_string('apikey', 'local_ailessonplan'),
        get_string('apikey_desc', 'local_ailessonplan'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_ailessonplan/enablelog',
        get_string('enablelog', 'local_ailessonplan'),
        get_string('enablelog_desc', 'local_ailessonplan'),
        1
    ));
}
