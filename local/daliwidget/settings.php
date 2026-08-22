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

if (!class_exists('local_daliwidget_admin_setting_text')) {
    /** Text setting with a strict maximum length. */
    class local_daliwidget_admin_setting_text extends admin_setting_configtext {
        private int $maxlength;

        public function __construct($name, $visiblename, $description, $defaultsetting, int $maxlength) {
            $this->maxlength = $maxlength;
            parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_TEXT);
        }

        public function validate($data) {
            $error = parent::validate($data);
            if ($error !== true) {
                return $error;
            }
            return core_text::strlen(trim((string) $data)) <= $this->maxlength
                ? true
                : get_string('appearance_too_long', 'local_daliwidget', $this->maxlength);
        }
    }
}

if (!class_exists('local_daliwidget_admin_setting_color')) {
    /** Optional six-digit hexadecimal color setting. */
    class local_daliwidget_admin_setting_color extends admin_setting_configtext {
        public function validate($data) {
            $data = trim((string) $data);
            return $data === '' || preg_match('/^#[0-9a-fA-F]{6}$/', $data)
                ? true
                : get_string('accent_color_invalid', 'local_daliwidget');
        }
    }
}

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_daliwidget_global_knowledge',
        get_string('global_knowledge_base', 'local_daliwidget'),
        new moodle_url('/local/daliwidget/global_knowledge.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('localplugins', new admin_externalpage('local_daliwidget_wordpress_connections', get_string('wordpress_connections', 'local_daliwidget'), new moodle_url('/local/daliwidget/wordpress_connections.php'), 'moodle/site:config'));

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

    // Knowledge Access Mode.
    $settings->add(new admin_setting_heading(
        'local_daliwidget/knowledge_access_heading',
        get_string('knowledge_access_heading', 'local_daliwidget'),
        get_string('knowledge_access_heading_desc', 'local_daliwidget')
    ));

    $settings->add(new admin_setting_configselect(
        'local_daliwidget/knowledge_access_mode',
        get_string('knowledge_access_mode', 'local_daliwidget'),
        get_string('knowledge_access_mode_desc', 'local_daliwidget'),
        'course_scoped',
        [
            'course_scoped' => get_string('knowledge_access_mode_course_scoped', 'local_daliwidget'),
            'site_wide' => get_string('knowledge_access_mode_site_wide', 'local_daliwidget'),
        ]
    ));

    $settings->add(new admin_setting_heading(
        'local_daliwidget/appearance_heading',
        get_string('appearance_heading', 'local_daliwidget'),
        get_string('appearance_heading_desc', 'local_daliwidget')
    ));

    $settings->add(new local_daliwidget_admin_setting_text(
        'local_daliwidget/assistant_name',
        get_string('assistant_name', 'local_daliwidget'),
        get_string('assistant_name_desc', 'local_daliwidget'),
        '',
        60
    ));
    $settings->add(new local_daliwidget_admin_setting_text(
        'local_daliwidget/welcome_message',
        get_string('welcome_message', 'local_daliwidget'),
        get_string('welcome_message_desc', 'local_daliwidget'),
        '',
        500
    ));
    $settings->add(new admin_setting_configselect(
        'local_daliwidget/theme',
        get_string('appearance_theme', 'local_daliwidget'),
        get_string('appearance_default_desc', 'local_daliwidget'),
        '',
        [
            '' => get_string('appearance_default', 'local_daliwidget'),
            'light' => get_string('appearance_light', 'local_daliwidget'),
            'dark' => get_string('appearance_dark', 'local_daliwidget'),
        ]
    ));
    $settings->add(new local_daliwidget_admin_setting_color(
        'local_daliwidget/accent_color',
        get_string('accent_color', 'local_daliwidget'),
        get_string('accent_color_desc', 'local_daliwidget'),
        '',
        PARAM_RAW
    ));
    $settings->add(new admin_setting_configselect(
        'local_daliwidget/border_radius',
        get_string('border_radius', 'local_daliwidget'),
        get_string('appearance_default_desc', 'local_daliwidget'),
        '',
        [
            '' => get_string('appearance_default', 'local_daliwidget'),
            'sharp' => get_string('border_radius_square', 'local_daliwidget'),
            'rounded' => get_string('border_radius_slight', 'local_daliwidget'),
            'pill' => get_string('border_radius_rounded', 'local_daliwidget'),
        ]
    ));
    $settings->add(new admin_setting_configstoredfile(
        'local_daliwidget/avatar',
        get_string('avatar', 'local_daliwidget'),
        get_string('avatar_desc', 'local_daliwidget'),
        'avatar',
        0,
        ['maxfiles' => 1, 'maxbytes' => 2 * 1024 * 1024, 'accepted_types' => ['.png', '.jpg', '.jpeg', '.webp']]
    ));

    // Debug Mode
    $settings->add(new admin_setting_configcheckbox(
        'local_daliwidget/debug_mode',
        get_string('debug_mode', 'local_daliwidget'),
        get_string('debug_mode_desc', 'local_daliwidget'),
        0 // Default disabled
    ));
}
