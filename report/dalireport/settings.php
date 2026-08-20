<?php
// This file is part of Moodle - http://moodle.org/.
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('report_dalireport', get_string('settings', 'report_dalireport'));
    $ADMIN->add('reports', $settings);
    $settings->add(new admin_setting_configtext(
        'report_dalireport/baseurl',
        get_string('baseurl', 'report_dalireport'),
        get_string('baseurl_desc', 'report_dalireport'),
        '',
        PARAM_URL
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'report_dalireport/apikey',
        get_string('apikey', 'report_dalireport'),
        get_string('apikey_desc', 'report_dalireport'),
        ''
    ));
}
