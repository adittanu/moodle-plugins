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
 * Upgrade script for local_daliwidget.
 *
 * @package     local_daliwidget
 * @copyright   2024 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_daliwidget_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026030501) {
        // Create local_daliwidget_sync_queue table.
        $table = new xmldb_table('local_daliwidget_sync_queue');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'queued');
        $table->add_field('errormessage', XMLDB_TYPE_TEXT, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseid_cmid', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'cmid']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026030501, 'local', 'daliwidget');
    }

    if ($oldversion < 2026082200) {
        set_config('knowledge_access_mode', 'course_scoped', 'local_daliwidget');
        unset_config('strict_course_mode', 'local_daliwidget');

        upgrade_plugin_savepoint(true, 2026082200, 'local', 'daliwidget');
    }

    if ($oldversion < 2026082400) {
        $table = new xmldb_table('local_daliwidget_unsynced');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('sourceulid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('moodlefileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('sourcetype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'document');
        $table->add_field('scope', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lifecyclestatus', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL);
        $table->add_field('timesynced', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeunsynced', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('course_time', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'timeunsynced']);
        $table->add_index('sourceulid', XMLDB_INDEX_NOTUNIQUE, ['sourceulid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026082400, 'local', 'daliwidget');
    }

    if ($oldversion < 2026082602) {
        set_config('answer_source_policy', 'knowledge_only', 'local_daliwidget');
        upgrade_plugin_savepoint(true, 2026082602, 'local', 'daliwidget');
    }

    return true;
}
