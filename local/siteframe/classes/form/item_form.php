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
 * Form for adding/editing SiteFrame items.
 *
 * @package     local_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_siteframe\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class item_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;
        $item = $this->_customdata['item'];

        // Hidden ID for edit mode.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Name.
        $mform->addElement('text', 'name', get_string('item_name', 'local_siteframe'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');

        // URL.
        $mform->addElement('text', 'url', get_string('item_url', 'local_siteframe'));
        $mform->setType('url', PARAM_RAW); // ponytail: PARAM_URL rejects underscores, custom validation in validation().
        $mform->addRule('url', null, 'required');

        // Display mode.
        $modes = [
            'fullpage'   => get_string('displaymode_fullpage', 'local_siteframe'),
            'coursepage' => get_string('displaymode_coursepage', 'local_siteframe'),
            'widget'     => get_string('displaymode_widget', 'local_siteframe'),
            'modal'      => get_string('displaymode_modal', 'local_siteframe'),
        ];
        $mform->addElement('select', 'displaymode', get_string('item_displaymode', 'local_siteframe'), $modes);
        $mform->setDefault('displaymode', 'fullpage');

        // Course ID.
        $mform->addElement('text', 'courseid', get_string('item_courseid', 'local_siteframe'));
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', 0);

        // Height.
        $mform->addElement('text', 'height', get_string('item_height', 'local_siteframe'));
        $mform->setType('height', PARAM_INT);
        $mform->setDefault('height', 0);

        // Width.
        $mform->addElement('text', 'width', get_string('item_width', 'local_siteframe'));
        $mform->setType('width', PARAM_TEXT);
        $mform->setDefault('width', '100%');

        // Scrolling.
        $scrolling = ['auto' => 'auto', 'yes' => 'yes', 'no' => 'no'];
        $mform->addElement('select', 'scrolling', get_string('item_scrolling', 'local_siteframe'), $scrolling);
        $mform->setDefault('scrolling', 'auto');

        // Visible.
        $mform->addElement('advcheckbox', 'visible', get_string('item_visible', 'local_siteframe'));
        $mform->setDefault('visible', 1);

        // Sort order.
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_siteframe'));
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        // Action buttons.
        $this->add_action_buttons(true, get_string('savechanges'));

        // Set data if editing.
        if (!empty($item->id)) {
            $this->set_data($item);
        }
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        // URL validation — sanitize_url tolerates underscores (Herd/Valet hostnames).
        $url = \local_siteframe\domain_helper::sanitize_url($data['url']);
        if ($url === false) {
            $errors['url'] = get_string('url_invalid', 'local_siteframe');
        } elseif (!\local_siteframe\domain_helper::is_domain_allowed($url)) {
            $errors['url'] = get_string('domain_not_allowed', 'local_siteframe');
        }

        // Display mode must be enabled in settings.
        $modekey = 'allow_' . $data['displaymode'];
        if ($data['displaymode'] === 'fullpage') {
            $modekey = 'allow_fullpage';
        } elseif ($data['displaymode'] === 'coursepage') {
            $modekey = 'allow_coursepage';
        } elseif ($data['displaymode'] === 'widget') {
            $modekey = 'allow_widget';
        } elseif ($data['displaymode'] === 'modal') {
            $modekey = 'allow_modal';
        }
        if (!get_config('local_siteframe', $modekey)) {
            $errors['displaymode'] = get_string('error_mode_disabled', 'local_siteframe');
        }

        // Course ID must exist when > 0.
        if (!empty($data['courseid']) && $data['courseid'] > 0) {
            if (!$DB->record_exists('course', ['id' => $data['courseid']])) {
                $errors['courseid'] = get_string('error_course_not_found', 'local_siteframe');
            }
        }

        return $errors;
    }
}
