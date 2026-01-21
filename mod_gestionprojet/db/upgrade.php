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

    return true;
}
