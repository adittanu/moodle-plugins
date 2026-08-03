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
        global $DB;

        $mform = $this->_form;
        $item = $this->_customdata['item'];
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'contentheader', get_string('content', 'local_siteframe'));
        $mform->addElement('text', 'name', get_string('item_name', 'local_siteframe'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'item_name', 'local_siteframe');

        $mform->addElement('text', 'url', get_string('item_url', 'local_siteframe'), ['size' => 70]);
        $mform->setType('url', PARAM_RAW_TRIMMED);
        $mform->addRule('url', null, 'required', null, 'client');
        $mform->addHelpButton('url', 'item_url', 'local_siteframe');

        $modes = [];
        foreach (['fullpage', 'coursepage', 'widget'] as $mode) {
            if (get_config('local_siteframe', 'allow_' . $mode)) {
                $modes[$mode] = get_string('displaymode_' . $mode, 'local_siteframe');
            }
        }
        if (($item->displaymode ?? '') === 'modal') {
            $modes['modal'] = get_string('displaymode_modal', 'local_siteframe');
        }
        $mform->addElement('select', 'displaymode', get_string('item_displaymode', 'local_siteframe'), $modes);
        $mform->addHelpButton('displaymode', 'item_displaymode', 'local_siteframe');
        $mform->setDefault('displaymode', array_key_first($modes) ?: 'fullpage');

        $courses = [0 => get_string('scope_global', 'local_siteframe')];
        foreach ($DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC', 'id,fullname') as $course) {
            $courses[$course->id] = format_string($course->fullname);
        }
        $mform->addElement('autocomplete', 'courseid', get_string('item_courseid', 'local_siteframe'), $courses);
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', 0);
        $mform->addHelpButton('courseid', 'item_courseid', 'local_siteframe');

        $mform->addElement('advcheckbox', 'visible', get_string('item_visible', 'local_siteframe'));
        $mform->setDefault('visible', 1);
        $mform->addHelpButton('visible', 'item_visible', 'local_siteframe');

        $mform->addElement('header', 'advancedheader', get_string('advanced', 'core'));
        $mform->setAdvanced('advancedheader');
        $mform->addElement('text', 'height', get_string('item_height', 'local_siteframe'));
        $mform->setType('height', PARAM_INT);
        $mform->setDefault('height', 0);
        $mform->addHelpButton('height', 'item_height', 'local_siteframe');
        $mform->setAdvanced('height');

        $mform->addElement('text', 'width', get_string('item_width', 'local_siteframe'));
        $mform->setType('width', PARAM_TEXT);
        $mform->setDefault('width', '100%');
        $mform->addHelpButton('width', 'item_width', 'local_siteframe');
        $mform->setAdvanced('width');

        $mform->addElement('select', 'scrolling', get_string('item_scrolling', 'local_siteframe'), [
            'auto' => get_string('scrolling_auto', 'local_siteframe'),
            'yes' => get_string('yes'),
            'no' => get_string('no'),
        ]);
        $mform->setDefault('scrolling', 'auto');
        $mform->addHelpButton('scrolling', 'item_scrolling', 'local_siteframe');
        $mform->setAdvanced('scrolling');

        $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_siteframe'));
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
        $mform->setAdvanced('sortorder');
        $this->add_action_buttons(true, get_string('savechanges'));

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

        if (($data['displaymode'] ?? '') === 'widget' && !empty($data['visible'])) {
            $params = ['mode' => 'widget', 'courseid' => (int)($data['courseid'] ?? 0)];
            $select = 'displaymode = :mode AND courseid = :courseid AND visible = 1';
            if (!empty($data['id'])) {
                $select .= ' AND id <> :id';
                $params['id'] = (int)$data['id'];
            }
            if ($DB->record_exists_select('local_siteframe_items', $select, $params)) {
                $errors['displaymode'] = get_string('error_widget_exists', 'local_siteframe');
            }
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
