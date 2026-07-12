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
 * SiteFrame activity mod_form.
 *
 * @package     mod_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_siteframe_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        // Standard name field.
        $mform->addElement('text', 'name', get_string('name', 'core'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Introduction (editor).
        $this->standard_intro_elements();

        // SiteFrame settings section.
        $mform->addElement('header', 'siteframe_settings', get_string('modulename', 'mod_siteframe'));

        // URL.
        $mform->addElement('text', 'url', get_string('url', 'mod_siteframe'), ['size' => '80']);
        $mform->setType('url', PARAM_URL);
        $mform->addRule('url', null, 'required', null, 'client');
        $mform->addHelpButton('url', 'url', 'mod_siteframe');

        // Display mode.
        $modes = [
            'inline'     => get_string('displaymode_inline', 'mod_siteframe'),
            'fullscreen' => get_string('displaymode_fullscreen', 'mod_siteframe'),
            'responsive' => get_string('displaymode_responsive', 'mod_siteframe'),
        ];
        $mform->addElement('select', 'displaymode', get_string('displaymode', 'mod_siteframe'), $modes);
        $mform->setDefault('displaymode', 'inline');
        $mform->addHelpButton('displaymode', 'displaymode', 'mod_siteframe');

        // Height.
        $mform->addElement('text', 'height', get_string('height', 'mod_siteframe'));
        $mform->setType('height', PARAM_INT);
        $mform->setDefault('height', 600);
        $mform->addHelpButton('height', 'height', 'mod_siteframe');

        // Width.
        $mform->addElement('text', 'width', get_string('width', 'mod_siteframe'));
        $mform->setType('width', PARAM_TEXT);
        $mform->setDefault('width', '100%');
        $mform->addHelpButton('width', 'width', 'mod_siteframe');

        // Scrolling.
        $scrolling = [
            'auto' => get_string('scrolling_auto', 'mod_siteframe'),
            'yes'  => get_string('scrolling_yes', 'mod_siteframe'),
            'no'   => get_string('scrolling_no', 'mod_siteframe'),
        ];
        $mform->addElement('select', 'scrolling', get_string('scrolling', 'mod_siteframe'), $scrolling);
        $mform->setDefault('scrolling', 'auto');

        // Standard course module elements.
        $this->standard_coursemodule_elements();

        // Standard buttons.
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate URL against domain allowlist.
        $url = \local_siteframe\domain_helper::sanitize_url($data['url']);
        if ($url === false) {
            $errors['url'] = get_string('url_invalid', 'local_siteframe');
        } elseif (!\local_siteframe\domain_helper::is_domain_allowed($url)) {
            $errors['url'] = get_string('domain_not_allowed', 'local_siteframe');
        }

        return $errors;
    }
}
