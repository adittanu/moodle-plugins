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
 * SiteFrame plugin upgrade steps.
 *
 * @package     local_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_siteframe_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026072000: drop unused extra_params column (dead since form never wrote it).
    if ($oldversion < 2026072000) {
        $table = new xmldb_table('local_siteframe_items');
        $field = new xmldb_field('extra_params');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026072000, 'local', 'siteframe');
    }

    // 2026080201: make stored items the single runtime source for the widget.
    if ($oldversion < 2026080201) {
        $defaulturl = get_config('local_siteframe', 'default_url');
        if ($defaulturl && !$DB->record_exists('local_siteframe_items', ['displaymode' => 'widget'])) {
            $DB->insert_record('local_siteframe_items', (object)[
                'name' => get_config('local_siteframe', 'widget_title') ?: 'SiteFrame',
                'url' => $defaulturl,
                'displaymode' => 'widget',
                'courseid' => 0,
                'height' => 0,
                'width' => '100%',
                'scrolling' => 'auto',
                'visible' => 1,
                'sortorder' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
        unset_config('default_url', 'local_siteframe');
        upgrade_plugin_savepoint(true, 2026080201, 'local', 'siteframe');
    }

    return true;
}
