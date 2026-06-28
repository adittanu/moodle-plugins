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
 * Upgrade script for quizaccess_webcamguard.
 *
 * @package    quizaccess_webcamguard
 * @copyright  2026 Dali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for quizaccess_webcamguard.
 *
 * @param int $oldversion Installed plugin version.
 * @return bool
 */
function xmldb_quizaccess_webcamguard_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026050613) {
        $table = new xmldb_table('quizaccess_wg_config');

        $field = new xmldb_field('identityenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0',
            'blurthreshold');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('identitythreshold', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '60',
            'identityenabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('identitymode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'flag',
            'identitythreshold');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026050613, 'quizaccess', 'webcamguard');
    }

    if ($oldversion < 2026050910) {
        $table = new xmldb_table('quizaccess_wg_config');

        $field = new xmldb_field('liveenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0',
            'identitymode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('quizaccess_wg_live');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('requestedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('roomname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'requested');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('attemptid', XMLDB_INDEX_NOTUNIQUE, ['attemptid']);
            $table->add_index('quizid', XMLDB_INDEX_NOTUNIQUE, ['quizid']);
            $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('expiresat', XMLDB_INDEX_NOTUNIQUE, ['expiresat']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026050910, 'quizaccess', 'webcamguard');
    }

    if ($oldversion < 2026062701) {
        $table = new xmldb_table('quizaccess_wg_live');

        // Add composite index for the common poll_live query pattern.
        $index = new xmldb_index('attemptid_status_expiresat', XMLDB_INDEX_NOTUNIQUE,
            ['attemptid', 'status', 'expiresat']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Remove redundant standalone attemptid index (covered by composite).
        $oldindex = new xmldb_index('attemptid', XMLDB_INDEX_NOTUNIQUE, ['attemptid']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }

        upgrade_plugin_savepoint(true, 2026062701, 'quizaccess', 'webcamguard');
    }

    if ($oldversion < 2026062702) {
        // Set default retention days if not already configured.
        if (get_config('quizaccess_webcamguard', 'retentiondays') === false) {
            set_config('retentiondays', '30', 'quizaccess_webcamguard');
        }
        upgrade_plugin_savepoint(true, 2026062702, 'quizaccess', 'webcamguard');
    }

    if ($oldversion < 2026062801) {
        $table = new xmldb_table('quizaccess_wg_live');
        $field = new xmldb_field('warning', XMLDB_TYPE_TEXT, null, null, null, null, null, 'expiresat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026062801, 'quizaccess', 'webcamguard');
    }

    return true;
}
