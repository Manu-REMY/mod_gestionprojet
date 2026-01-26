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

    if ($oldversion < 2026012501) {

        // Create teacher correction model tables for steps 4-8.

        // Table: gestionprojet_cdcf_teacher (Step 4).
        $table = new xmldb_table('gestionprojet_cdcf_teacher');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('gestionprojetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('produit', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('milieu', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('fp', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('interacteurs_data', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('ai_instructions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('gestionprojetid', XMLDB_KEY_FOREIGN_UNIQUE, ['gestionprojetid'], 'gestionprojet', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Table: gestionprojet_essai_teacher (Step 5).
        $table = new xmldb_table('gestionprojet_essai_teacher');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('gestionprojetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('nom_essai', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('date_essai', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('groupe_eleves', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('fonction_service', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('niveaux_reussite', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('etapes_protocole', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('materiel_outils', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('precautions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('resultats_obtenus', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('observations_remarques', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('conclusion', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('objectif', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('ai_instructions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('gestionprojetid', XMLDB_KEY_FOREIGN_UNIQUE, ['gestionprojetid'], 'gestionprojet', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Table: gestionprojet_rapport_teacher (Step 6).
        $table = new xmldb_table('gestionprojet_rapport_teacher');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('gestionprojetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('titre_projet', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('auteurs', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('besoin_projet', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('imperatifs', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('solutions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('justification', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('realisation', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('difficultes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('validation', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('ameliorations', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('bilan', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('perspectives', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('besoins', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('ai_instructions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('gestionprojetid', XMLDB_KEY_FOREIGN_UNIQUE, ['gestionprojetid'], 'gestionprojet', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Table: gestionprojet_besoin_eleve_teacher (Step 7).
        $table = new xmldb_table('gestionprojet_besoin_eleve_teacher');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('gestionprojetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('aqui', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('surquoi', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('dansquelbut', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('ai_instructions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('gestionprojetid', XMLDB_KEY_FOREIGN_UNIQUE, ['gestionprojetid'], 'gestionprojet', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Table: gestionprojet_carnet_teacher (Step 8).
        $table = new xmldb_table('gestionprojet_carnet_teacher');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('gestionprojetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tasks_data', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('ai_instructions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('gestionprojetid', XMLDB_KEY_FOREIGN_UNIQUE, ['gestionprojetid'], 'gestionprojet', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Gestionprojet savepoint reached.
        upgrade_mod_savepoint(true, 2026012501, 'gestionprojet');
    }

    if ($oldversion < 2026012502) {
        // Enable step7 and step8 by default for all existing instances.
        $DB->execute("UPDATE {gestionprojet} SET enable_step7 = 1 WHERE enable_step7 = 0");
        $DB->execute("UPDATE {gestionprojet} SET enable_step8 = 1 WHERE enable_step8 = 0");

        // Gestionprojet savepoint reached.
        upgrade_mod_savepoint(true, 2026012502, 'gestionprojet');
    }

    if ($oldversion < 2026012600) {
        // Phase 3.5: Add submission system fields.

        // Add enable_submission to main table.
        $table = new xmldb_table('gestionprojet');
        $field = new xmldb_field('enable_submission', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'ai_enabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add status and timesubmitted to gestionprojet_essai (missing fields).
        $table = new xmldb_table('gestionprojet_essai');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add status and timesubmitted to gestionprojet_rapport (missing fields).
        $table = new xmldb_table('gestionprojet_rapport');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add submission_date and deadline_date to teacher tables.
        $teachertables = [
            'gestionprojet_cdcf_teacher',
            'gestionprojet_essai_teacher',
            'gestionprojet_rapport_teacher',
            'gestionprojet_besoin_eleve_teacher',
            'gestionprojet_carnet_teacher',
        ];

        foreach ($teachertables as $tablename) {
            $table = new xmldb_table($tablename);

            $field = new xmldb_field('submission_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'ai_instructions');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('deadline_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'submission_date');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Gestionprojet savepoint reached.
        upgrade_mod_savepoint(true, 2026012600, 'gestionprojet');
    }

    if ($oldversion < 2026012700) {
        // Phase 4: AI Evaluation Engine.

        // Add ai_auto_apply field to main table.
        $table = new xmldb_table('gestionprojet');
        $field = new xmldb_field('ai_auto_apply', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'ai_enabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Create gestionprojet_ai_evaluations table.
        $table = new xmldb_table('gestionprojet_ai_evaluations');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('gestionprojetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('step', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('submissionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('provider', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('model', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('prompt_tokens', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('completion_tokens', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('raw_response', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('parsed_grade', XMLDB_TYPE_NUMBER, '10', null, null, null, null);
        $table->add_field('parsed_feedback', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('criteria_json', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('keywords_found', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('keywords_missing', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('suggestions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('applied_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('applied_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('gestionprojetid', XMLDB_KEY_FOREIGN, ['gestionprojetid'], 'gestionprojet', ['id']);

        $table->add_index('submission_idx', XMLDB_INDEX_NOTUNIQUE, ['step', 'submissionid']);
        $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Gestionprojet savepoint reached.
        upgrade_mod_savepoint(true, 2026012700, 'gestionprojet');
    }

    if ($oldversion < 2026012800) {
        // Phase 5: Gradebook per-step integration.

        // Add grade_mode field to main table.
        $table = new xmldb_table('gestionprojet');
        $field = new xmldb_field('grade_mode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'enable_submission');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Gestionprojet savepoint reached.
        upgrade_mod_savepoint(true, 2026012800, 'gestionprojet');
    }

    if ($oldversion < 2026012900) {
        // Add Albert provider support with secure built-in API key.
        require_once(__DIR__ . '/../classes/ai_config.php');

        // Set up the Albert API key securely for existing installations.
        // The key is encrypted before storage and cannot be retrieved by users.
        $albertkey = 'sk-eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo5NTMxLCJ0b2tlbl9pZCI6MTA0MzcsImV4cGlyZXMiOjE4MDA5ODU0MDV9.h4Kx-kUU4yXzEifV3-L63f791urH2owDSai5n8Ru7eo';

        \mod_gestionprojet\ai_config::set_builtin_api_key('albert', $albertkey);

        // Gestionprojet savepoint reached.
        upgrade_mod_savepoint(true, 2026012900, 'gestionprojet');
    }

    return true;
}
