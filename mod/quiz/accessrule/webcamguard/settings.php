<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Global settings placeholder for quizaccess_webcamguard.
 *
 * Per-quiz settings are added by rule.php to the quiz settings form.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('quizaccess_webcamguard', get_string('pluginname', 'quizaccess_webcamguard'));

    $settings->add(new admin_setting_heading('quizaccess_webcamguard_livekit',
        get_string('livekitsettings', 'quizaccess_webcamguard'),
        get_string('livekitsettings_desc', 'quizaccess_webcamguard')));

    $settings->add(new admin_setting_configtext('quizaccess_webcamguard/livekiturl',
        get_string('livekiturl', 'quizaccess_webcamguard'),
        get_string('livekiturl_desc', 'quizaccess_webcamguard'), '', PARAM_RAW_TRIMMED));

    $settings->add(new admin_setting_configtext('quizaccess_webcamguard/livekitapikey',
        get_string('livekitapikey', 'quizaccess_webcamguard'),
        get_string('livekitapikey_desc', 'quizaccess_webcamguard'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configpasswordunmask('quizaccess_webcamguard/livekitsecret',
        get_string('livekitsecret', 'quizaccess_webcamguard'),
        get_string('livekitsecret_desc', 'quizaccess_webcamguard'), ''));

    $settings->add(new admin_setting_configtext('quizaccess_webcamguard/livekitttl',
        get_string('livekitttl', 'quizaccess_webcamguard'),
        get_string('livekitttl_desc', 'quizaccess_webcamguard'), 300, PARAM_INT));


    if (strpos($CFG->wwwroot, 'http://') === 0) {
        $settings->add(new admin_setting_heading('quizaccess_webcamguard_httpwarning',
            '<span style="color:#c82333;font-weight:bold;">HTTPS Required</span>',
            '<span style="color:#c82333;">Webcam Guard requires HTTPS for getUserMedia(). The site is currently using HTTP. Webcam features will not work.</span>'));
    }

    $ADMIN->add('modsettings', $settings);
}
