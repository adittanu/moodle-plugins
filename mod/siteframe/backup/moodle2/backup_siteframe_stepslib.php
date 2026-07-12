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
 * Backup steps for mod_siteframe.
 *
 * @package     mod_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class backup_siteframe_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $siteframe = new backup_nested_element('siteframe', ['id'], [
            'name', 'intro', 'introformat', 'url', 'displaymode',
            'height', 'width', 'scrolling', 'extra_allowed',
            'timecreated', 'timemodified',
        ]);

        $siteframe->set_source_table('siteframe', ['id' => backup::VAR_ACTIVITYID]);

        $siteframe->annotate_files('mod_siteframe', 'intro', null);

        return $this->prepare_activity_structure($siteframe);
    }
}
