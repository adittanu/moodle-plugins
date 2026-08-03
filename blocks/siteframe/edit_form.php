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
 * SiteFrame block edit form.
 *
 * @package     block_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_siteframe_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        $mform->addElement('header', 'configheader', get_string('pluginname', 'block_siteframe'));

        $mform->addElement('text', 'config_url', get_string('item_url', 'local_siteframe'));
        $mform->setType('config_url', PARAM_URL);

        $mform->addElement('text', 'config_height', get_string('item_height', 'local_siteframe'));
        $mform->setType('config_height', PARAM_INT);
        $mform->setDefault('config_height', 400);

        $mform->addElement('text', 'config_width', get_string('item_width', 'local_siteframe'));
        $mform->setType('config_width', PARAM_TEXT);
        $mform->setDefault('config_width', '100%');

        $scrolling = ['auto' => 'auto', 'yes' => 'yes', 'no' => 'no'];
        $mform->addElement('select', 'config_scrolling', get_string('item_scrolling', 'local_siteframe'), $scrolling);
        $mform->setDefault('config_scrolling', 'auto');
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['config_url'])) {
            $url = \local_siteframe\domain_helper::sanitize_url($data['config_url']);
            if ($url === false) {
                $errors['config_url'] = get_string('url_invalid', 'local_siteframe');
            } else if (!\local_siteframe\domain_helper::is_domain_allowed($url)) {
                $errors['config_url'] = get_string('domain_not_allowed', 'local_siteframe');
            }
        }
        return $errors;
    }
}
