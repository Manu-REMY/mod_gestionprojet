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
 * Upgrade script for mod_gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute mod_gestionprojet upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_gestionprojet_upgrade($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026012102) {
        // Define field group_submission to be added to gestionprojet
        $table = new xmldb_table('gestionprojet');
        $field = new xmldb_field('group_submission', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'groupmode');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define fields to be added to gestionprojet_cdcf, gestionprojet_essai, gestionprojet_rapport
        $tables = ['gestionprojet_cdcf', 'gestionprojet_essai', 'gestionprojet_rapport'];

        foreach ($tables as $tablename) {
            $table = new xmldb_table($tablename);

            // Add userid
            $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'feedback');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Add status
            $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'userid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Add timesubmitted
            $field = new xmldb_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Change index from gestionprojet_group_idx (gestionprojetid, groupid) to gestionprojet_submission_idx (gestionprojetid, groupid, userid)
            $index = new xmldb_index('gestionprojet_group_idx', XMLDB_INDEX_UNIQUE, ['gestionprojetid', 'groupid']);
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }

            $index = new xmldb_index('gestionprojet_submission_idx', XMLDB_INDEX_UNIQUE, ['gestionprojetid', 'groupid', 'userid']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // Gestionprojet savepoint reached.
        upgrade_mod_savepoint(true, 2026012102, 'gestionprojet');
    }

    if ($oldversion < 2026012103) {
        $tables = ['gestionprojet_cdcf', 'gestionprojet_essai', 'gestionprojet_rapport'];

        foreach ($tables as $tablename) {
            $table = new xmldb_table($tablename);

            // Add grade field
            $field = new xmldb_field('grade', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'id');
            // Note: 'after' param logic in add_field is last arg. 'id' is safe or use null to append.
            // Actually let's check field existence first.
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // Add feedback field
            $field = new xmldb_field('feedback', XMLDB_TYPE_TEXT, null, null, null, null, null, 'grade');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026012103, 'gestionprojet');
    }

    if ($oldversion < 2026012105) {
        $table = new xmldb_table('gestionprojet_besoin_eleve');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('gestionprojetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('aqui', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('surquoi', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('dansquelbut', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('grade', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        $table->add_field('feedback', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('gestionprojetid', XMLDB_KEY_FOREIGN, ['gestionprojetid'], 'gestionprojet', ['id']);
        $table->add_key('groupid', XMLDB_KEY_FOREIGN, ['groupid'], 'groups', ['id']);

        $table->add_index('gestionprojet_submission_idx', XMLDB_INDEX_UNIQUE, ['gestionprojetid', 'groupid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026012105, 'gestionprojet');
    }

    if ($oldversion < 2026012106) {
        $table = new xmldb_table('gestionprojet');

        // Add enable_step1 to enable_step7
        for ($i = 1; $i <= 7; $i++) {
            $default = ($i == 7) ? '0' : '1';
            $field = new xmldb_field("enable_step{$i}", XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, $default, "autosave_interval");

            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026012106, 'gestionprojet');
    }

    return true;
}
