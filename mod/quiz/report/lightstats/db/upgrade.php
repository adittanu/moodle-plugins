<?php
// This file is part of Moodle - http://moodle.org/.

function xmldb_quiz_lightstats_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2026080200) {
        $table = new xmldb_table('quiz_lightstats_jobs');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'queued');
        $table->add_field('progress', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('message', XMLDB_TYPE_CHAR, '255');
        $table->add_field('payload', XMLDB_TYPE_TEXT);
        $table->add_field('error', XMLDB_TYPE_TEXT);
        $table->add_field('requestedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('attemptcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('quizid_uix', XMLDB_INDEX_UNIQUE, ['quizid']);
        $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status', 'timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026080200, 'quiz', 'lightstats');
    }
    return true;
}
