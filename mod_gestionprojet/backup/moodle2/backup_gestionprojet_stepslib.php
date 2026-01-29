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
 * Backup steps for mod_gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete gestionprojet structure for backup.
 */
class backup_gestionprojet_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure for the gestionprojet activity.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $gestionprojet = new backup_nested_element('gestionprojet', ['id'], [
            'name', 'intro', 'introformat', 'groupmode', 'group_submission',
            'autosave_interval', 'timecreated', 'timemodified',
            'enable_step1', 'enable_step2', 'enable_step3', 'enable_step4',
            'enable_step5', 'enable_step6', 'enable_step7', 'enable_step8',
            'ai_provider', 'ai_api_key', 'ai_enabled', 'ai_auto_apply',
            'enable_submission', 'grade_mode',
        ]);

        // Teacher configuration tables.
        $description = new backup_nested_element('description', ['id'], [
            'intitule', 'niveau', 'support', 'duree', 'besoin', 'production',
            'outils', 'evaluation', 'competences', 'imageid', 'locked',
            'timecreated', 'timemodified',
        ]);

        $besoin = new backup_nested_element('besoin', ['id'], [
            'aqui', 'surquoi', 'dansquelbut', 'locked', 'timecreated', 'timemodified',
        ]);

        $planning = new backup_nested_element('planning', ['id'], [
            'projectname', 'startdate', 'enddate', 'vacationzone',
            'task1_hours', 'task2_hours', 'task3_hours', 'task4_hours', 'task5_hours',
            'locked', 'timecreated', 'timemodified',
        ]);

        // Teacher correction models.
        $cdcfteacher = new backup_nested_element('cdcf_teacher', ['id'], [
            'produit', 'milieu', 'fp', 'interacteurs_data', 'ai_instructions',
            'submission_date', 'deadline_date', 'timecreated', 'timemodified',
        ]);

        $essaiteacher = new backup_nested_element('essai_teacher', ['id'], [
            'nom_essai', 'date_essai', 'groupe_eleves', 'fonction_service',
            'niveaux_reussite', 'etapes_protocole', 'materiel_outils', 'precautions',
            'resultats_obtenus', 'observations_remarques', 'conclusion', 'objectif',
            'ai_instructions', 'submission_date', 'deadline_date', 'timecreated', 'timemodified',
        ]);

        $rapportteacher = new backup_nested_element('rapport_teacher', ['id'], [
            'titre_projet', 'auteurs', 'besoin_projet', 'imperatifs', 'solutions',
            'justification', 'realisation', 'difficultes', 'validation', 'ameliorations',
            'bilan', 'perspectives', 'besoins', 'ai_instructions',
            'submission_date', 'deadline_date', 'timecreated', 'timemodified',
        ]);

        $besointeacher = new backup_nested_element('besoin_eleve_teacher', ['id'], [
            'aqui', 'surquoi', 'dansquelbut', 'ai_instructions',
            'submission_date', 'deadline_date', 'timecreated', 'timemodified',
        ]);

        $carnetteacher = new backup_nested_element('carnet_teacher', ['id'], [
            'tasks_data', 'ai_instructions', 'submission_date', 'deadline_date',
            'timecreated', 'timemodified',
        ]);

        // Student submission tables.
        $cdcfs = new backup_nested_element('cdcfs');
        $cdcf = new backup_nested_element('cdcf', ['id'], [
            'groupid', 'userid', 'produit', 'milieu', 'fp', 'interacteurs_data',
            'grade', 'feedback', 'status', 'timesubmitted', 'timecreated', 'timemodified',
        ]);

        $essais = new backup_nested_element('essais');
        $essai = new backup_nested_element('essai', ['id'], [
            'groupid', 'userid', 'nom_essai', 'date_essai', 'groupe_eleves',
            'fonction_service', 'niveaux_reussite', 'etapes_protocole', 'materiel_outils',
            'precautions', 'resultats_obtenus', 'observations_remarques', 'conclusion',
            'grade', 'feedback', 'status', 'timesubmitted', 'timecreated', 'timemodified',
        ]);

        $rapports = new backup_nested_element('rapports');
        $rapport = new backup_nested_element('rapport', ['id'], [
            'groupid', 'userid', 'titre_projet', 'auteurs', 'besoin_projet', 'imperatifs',
            'solutions', 'justification', 'realisation', 'difficultes', 'validation',
            'ameliorations', 'bilan', 'perspectives',
            'grade', 'feedback', 'status', 'timesubmitted', 'timecreated', 'timemodified',
        ]);

        $besoineleves = new backup_nested_element('besoin_eleves');
        $besoineleve = new backup_nested_element('besoin_eleve', ['id'], [
            'groupid', 'userid', 'aqui', 'surquoi', 'dansquelbut',
            'grade', 'feedback', 'status', 'timesubmitted', 'timecreated', 'timemodified',
        ]);

        $carnets = new backup_nested_element('carnets');
        $carnet = new backup_nested_element('carnet', ['id'], [
            'groupid', 'userid', 'tasks_data',
            'grade', 'feedback', 'status', 'timesubmitted', 'timecreated', 'timemodified',
        ]);

        // AI evaluations.
        $aievaluations = new backup_nested_element('ai_evaluations');
        $aievaluation = new backup_nested_element('ai_evaluation', ['id'], [
            'step', 'submissionid', 'groupid', 'userid', 'provider', 'model',
            'prompt_tokens', 'completion_tokens', 'raw_response', 'parsed_grade',
            'parsed_feedback', 'criteria_json', 'keywords_found', 'keywords_missing',
            'suggestions', 'status', 'error_message', 'applied_by', 'applied_at',
            'show_feedback', 'show_criteria', 'show_keywords_found',
            'show_keywords_missing', 'show_suggestions', 'timecreated', 'timemodified',
        ]);

        // Build the tree.
        $gestionprojet->add_child($description);
        $gestionprojet->add_child($besoin);
        $gestionprojet->add_child($planning);
        $gestionprojet->add_child($cdcfteacher);
        $gestionprojet->add_child($essaiteacher);
        $gestionprojet->add_child($rapportteacher);
        $gestionprojet->add_child($besointeacher);
        $gestionprojet->add_child($carnetteacher);

        $gestionprojet->add_child($cdcfs);
        $cdcfs->add_child($cdcf);

        $gestionprojet->add_child($essais);
        $essais->add_child($essai);

        $gestionprojet->add_child($rapports);
        $rapports->add_child($rapport);

        $gestionprojet->add_child($besoineleves);
        $besoineleves->add_child($besoineleve);

        $gestionprojet->add_child($carnets);
        $carnets->add_child($carnet);

        $gestionprojet->add_child($aievaluations);
        $aievaluations->add_child($aievaluation);

        // Define sources.
        $gestionprojet->set_source_table('gestionprojet', ['id' => backup::VAR_ACTIVITYID]);
        $description->set_source_table('gestionprojet_description', ['gestionprojetid' => backup::VAR_PARENTID]);
        $besoin->set_source_table('gestionprojet_besoin', ['gestionprojetid' => backup::VAR_PARENTID]);
        $planning->set_source_table('gestionprojet_planning', ['gestionprojetid' => backup::VAR_PARENTID]);
        $cdcfteacher->set_source_table('gestionprojet_cdcf_teacher', ['gestionprojetid' => backup::VAR_PARENTID]);
        $essaiteacher->set_source_table('gestionprojet_essai_teacher', ['gestionprojetid' => backup::VAR_PARENTID]);
        $rapportteacher->set_source_table('gestionprojet_rapport_teacher', ['gestionprojetid' => backup::VAR_PARENTID]);
        $besointeacher->set_source_table('gestionprojet_besoin_eleve_teacher', ['gestionprojetid' => backup::VAR_PARENTID]);
        $carnetteacher->set_source_table('gestionprojet_carnet_teacher', ['gestionprojetid' => backup::VAR_PARENTID]);

        // User data sources (only if userinfo is set).
        if ($userinfo) {
            $cdcf->set_source_table('gestionprojet_cdcf', ['gestionprojetid' => backup::VAR_PARENTID]);
            $essai->set_source_table('gestionprojet_essai', ['gestionprojetid' => backup::VAR_PARENTID]);
            $rapport->set_source_table('gestionprojet_rapport', ['gestionprojetid' => backup::VAR_PARENTID]);
            $besoineleve->set_source_table('gestionprojet_besoin_eleve', ['gestionprojetid' => backup::VAR_PARENTID]);
            $carnet->set_source_table('gestionprojet_carnet', ['gestionprojetid' => backup::VAR_PARENTID]);
            $aievaluation->set_source_table('gestionprojet_ai_evaluations', ['gestionprojetid' => backup::VAR_PARENTID]);
        }

        // Define id annotations.
        $cdcf->annotate_ids('group', 'groupid');
        $cdcf->annotate_ids('user', 'userid');
        $essai->annotate_ids('group', 'groupid');
        $essai->annotate_ids('user', 'userid');
        $rapport->annotate_ids('group', 'groupid');
        $rapport->annotate_ids('user', 'userid');
        $besoineleve->annotate_ids('group', 'groupid');
        $besoineleve->annotate_ids('user', 'userid');
        $carnet->annotate_ids('group', 'groupid');
        $carnet->annotate_ids('user', 'userid');
        $aievaluation->annotate_ids('group', 'groupid');
        $aievaluation->annotate_ids('user', 'userid');
        $aievaluation->annotate_ids('user', 'applied_by');

        // Define file annotations.
        $gestionprojet->annotate_files('mod_gestionprojet', 'intro', null);

        return $this->prepare_activity_structure($gestionprojet);
    }
}
