<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Upgrade steps for AI Lesson Plan plugin.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_ailessonplan upgrade steps.
 *
 * @param int $oldversion Previously installed version.
 * @return bool
 */
function xmldb_local_ailessonplan_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026050400) {
        $table = new xmldb_table('local_ailessonplan');

        $field = new xmldb_field('publishedcmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'planjson');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('publishedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'publishedcmid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026050400, 'local', 'ailessonplan');
    }

    return true;
}
