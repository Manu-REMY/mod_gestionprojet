<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the module
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_gestionprojet_upgrade($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026012400) {

        // Define field enable_step8 to be added to gestionprojet.
        $table = new xmldb_table('gestionprojet');
        $field = new xmldb_field('enable_step8', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'enable_step7');

        // Conditionally launch add field enable_step8.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table gestionprojet_carnet to be created.
        $table = new xmldb_table('gestionprojet_carnet');

        // Adding fields to table gestionprojet_carnet.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('gestionprojetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('tasks_data', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('grade', XMLDB_TYPE_NUMBER, '10', null, null, null, null);
        $table->add_field('feedback', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table gestionprojet_carnet.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('gestionprojetid', XMLDB_KEY_FOREIGN, ['gestionprojetid'], 'gestionprojet', ['id']);
        $table->add_key('groupid', XMLDB_KEY_FOREIGN, ['groupid'], 'groups', ['id']);

        // Adding indexes to table gestionprojet_carnet.
        $table->add_index('gestionprojet_submission_idx', XMLDB_INDEX_UNIQUE, ['gestionprojetid', 'groupid', 'userid']);

        // Conditionally launch create table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Gestionprojet savepoint reached.
        upgrade_mod_savepoint(true, 2026012400, 'gestionprojet');
    }

    if ($oldversion < 2026012500) {

        // Define field ai_provider to be added to gestionprojet.
        $table = new xmldb_table('gestionprojet');

        // Add ai_provider field.
        $field = new xmldb_field('ai_provider', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'enable_step8');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add ai_api_key field.
        $field = new xmldb_field('ai_api_key', XMLDB_TYPE_TEXT, null, null, null, null, null, 'ai_provider');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add ai_enabled field.
        $field = new xmldb_field('ai_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'ai_api_key');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Gestionprojet savepoint reached.
        upgrade_mod_savepoint(true, 2026012500, 'gestionprojet');
    }

    return true;
}
