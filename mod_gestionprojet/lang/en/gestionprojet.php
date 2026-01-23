<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English language strings for mod_gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Main module
$string['modulename'] = 'Project Management';
$string['modulenameplural'] = 'Project Managements';
$string['modulename_help'] = 'The Project Management module allows teachers to create structured educational projects in 6 steps. Teachers configure the first 3 steps, then students (individually or in groups) complete the last 3.';
$string['pluginname'] = 'Project Management';
$string['pluginadministration'] = 'Project Management Administration';

// Capabilities
$string['gestionprojet:addinstance'] = 'Add a new Project Management activity';
$string['gestionprojet:view'] = 'View Project Management activity';
$string['gestionprojet:configureteacherpages'] = 'Configure teacher pages';
$string['gestionprojet:submit'] = 'Submit work';
$string['gestionprojet:viewallsubmissions'] = 'View all submissions';
$string['gestionprojet:grade'] = 'Grade submissions';
$string['gestionprojet:lock'] = 'Lock/unlock pages';
$string['gestionprojet:viewhistory'] = 'View modification history';
$string['gestionprojet:exportall'] = 'Export all projects';

// Navigation
$string['home'] = 'Home';
$string['navigation_teacher'] = 'Teacher Configuration';
$string['navigation_student'] = 'Student Work';
$string['navigation_grading'] = 'Grading';

// The 6 steps
$string['step1'] = 'Descriptive Sheet';
$string['step2'] = 'Needs Expression';
$string['step3'] = 'Planning';
$string['step4'] = 'Requirements Specification';
$string['step5'] = 'Test Sheet';
$string['step6'] = 'Project Report';
$string['step7'] = 'Needs Expression (Student)';

$string['step1_desc'] = 'Define the project framework (teacher)';
$string['step2_desc'] = 'Horn Diagram (teacher)';
$string['step3_desc'] = 'Planning and timeline (teacher)';
$string['step4_desc'] = 'Stakeholder diagram (students)';
$string['step5_desc'] = 'Tests and validation (students)';
$string['step6_desc'] = 'Final report (students)';
$string['step7_desc'] = 'Horn Diagram (students)';

// Configuration form
$string['activesteps'] = 'Active Steps';
$string['submissionsettings'] = 'Submission Settings';
$string['groupsubmission'] = 'Group submission';
$string['enable_submission'] = 'Enable submission';
$string['autosave_interval'] = 'Auto-save interval';
$string['autosave_interval_help'] = 'Automatic save frequency in seconds (recommended: 30s)';

// Descriptive Sheet
$string['intitule'] = 'Project title';
$string['niveau'] = 'Educational level';
$string['support'] = 'Educational support';
$string['duree'] = 'Project duration';
$string['besoin'] = 'Needs expression';
$string['production'] = 'Expected production';
$string['outils'] = 'Tools and technical means';
$string['evaluation'] = 'Assessment methods';
$string['competences'] = 'Skills developed';
$string['image'] = 'Project image';

// Needs Expression
$string['aqui'] = 'Who does it serve?';
$string['surquoi'] = 'What does it act on?';
$string['dansquelbut'] = 'For what purpose?';
$string['bete_a_corne_title'] = 'What is the "Horn Diagram"?';
$string['bete_a_corne_description'] = 'The Horn Diagram is a graphical tool used to express project needs in a simple and clear way.';
$string['bete_a_corne_diagram'] = 'Horn Diagram';
$string['aqui_help'] = 'Identify the users or beneficiaries of the product';
$string['surquoi_help'] = 'Define the object or system on which the product operates';
$string['dansquelbut_help'] = 'Specify the main objective of the product';

// Planning
$string['projectname'] = 'Project name';
$string['startdate'] = 'Start date';
$string['enddate'] = 'End date';
$string['vacationzone'] = 'School vacation zone';
$string['vacationzone_none'] = 'None';
$string['vacationzone_a'] = 'Zone A';
$string['vacationzone_b'] = 'Zone B';
$string['vacationzone_c'] = 'Zone C';
$string['task1'] = 'Needs expression';
$string['task2'] = 'Requirements specification writing';
$string['task3'] = 'Technical solutions research';
$string['task4'] = 'Modeling/Simulation/Prototyping';
$string['task5'] = 'System validation';
$string['hours'] = 'hours';
$string['planning_description'] = 'Plan your project by defining dates and duration of each task.';
$string['project_planning'] = 'Project planning';
$string['task_durations'] = 'Task durations';
$string['hours_per_week_info'] = 'Base: 1.5h per week in class';
$string['timeline_preview'] = 'Timeline preview';
$string['finish'] = 'Finish';

// Requirements Specification
$string['produit'] = 'Product';
$string['milieu'] = 'Environment of use';
$string['interacteurs'] = 'Stakeholders';
$string['fonction_principale'] = 'Main function (MF)';
$string['fonctions_contraintes'] = 'Constraint functions (CF)';

// Test Sheet (Step 5)
$string['nom_essai'] = 'Test name';
$string['nom_essai_placeholder'] = 'Enter the test name';
$string['date'] = 'Date';
$string['groupe_eleves'] = 'Group / Student(s)';
$string['groupe_eleves_placeholder'] = 'Enter student or group names';
$string['objectif'] = 'Test objective';
$string['protocole'] = 'Experimental protocol';
$string['precautions'] = 'Experimental precautions';
$string['resultats'] = 'Results and observations';
$string['conclusion'] = 'Conclusion';
$string['fonction_service'] = 'Service function/constraint';
$string['niveaux_reussite'] = 'Success levels';
$string['etapes_protocole'] = 'Protocol steps';
$string['materiel_outils'] = 'Materials and tools';
$string['resultats_obtenus'] = 'Results obtained';
$string['observations_remarques'] = 'Observations and remarks';
$string['fonctions_service'] = 'Service functions (SF)';
$string['critere'] = 'Appreciation criterion';
$string['niveau_attendu'] = 'Expected level';
$string['unite'] = 'Unit';

// Project Report (Step 6)
$string['titre_projet'] = 'Project title';
$string['membres_groupe'] = 'Group members';
$string['besoin_projet'] = 'What need does the project meet';
$string['imperatifs'] = 'What requirements had to be met?';
$string['solutions'] = 'What solutions did you choose to answer the problem?';
$string['justification'] = 'Why did you choose these solutions?';
$string['realisation'] = 'Description of the implementation';
$string['difficultes'] = 'Difficulties encountered';
$string['validation'] = 'Test results';
$string['ameliorations'] = 'Possible improvements';
$string['bilan'] = 'Project summary';
$string['perspectives'] = 'Perspectives and project continuation';
$string['auteurs'] = 'Authors';
$string['besoins'] = 'Needs';
$string['contraintes'] = 'Constraints';

// Grading interface
$string['grading_navigation'] = 'Grading navigation';
$string['grading_step'] = 'Step to grade';
$string['grading_group'] = 'Group';
$string['grading_previous'] = 'Previous group';
$string['grading_next'] = 'Next group';
$string['grading_feedback'] = 'Comments';
$string['grading_grade'] = 'Grade / 20';
$string['grading_save'] = 'Save grade';
$string['grading_context_maintained'] = 'Grading context is maintained';

// Actions
$string['next'] = 'Next';
$string['previous'] = 'Previous';
$string['lock'] = 'Lock';
$string['unlock'] = 'Unlock';
$string['locked'] = 'Locked by teacher';
$string['save'] = 'Save';
$string['saving'] = 'Saving...';
$string['saved'] = 'Auto-saved';
$string['export_pdf'] = 'Export to PDF';
$string['export_all'] = 'Export all projects';
$string['view_history'] = 'View history';

// Messages
$string['autosave_success'] = 'Auto-save completed';
$string['autosave_error'] = 'Error during auto-save';
$string['no_groups'] = 'No groups configured. Please create groups in this course.';
$string['no_submission'] = 'No submission for this group';
$string['submission_saved'] = 'Your work has been saved';
$string['teacher_pages_locked'] = 'Teacher pages are locked. You cannot modify them.';
$string['must_complete_teacher_pages'] = 'The teacher must first complete the first 3 steps.';

// Privacy
$string['privacy:metadata:gestionprojet_description'] = 'Information about the descriptive sheet configured by the teacher';
$string['privacy:metadata:gestionprojet_cdcf'] = 'Functional requirements specification submitted by groups';
$string['privacy:metadata:gestionprojet_essai'] = 'Test sheet submitted by groups';
$string['privacy:metadata:gestionprojet_rapport'] = 'Project report submitted by groups';
$string['privacy:metadata:gestionprojet_history'] = 'Modification history';
$string['privacy:metadata:gestionprojet_history:userid'] = 'The user who made the modification';
$string['privacy:metadata:gestionprojet_history:timecreated'] = 'Date and time of the modification';
$string['privacy:metadata:groupid'] = 'The group to which the submission belongs';
$string['privacy:metadata:grade'] = 'The grade given by the teacher';
$string['privacy:metadata:feedback'] = 'The teacher\'s comments';

// Errors
$string['error_nosuchinstance'] = 'Project Management instance not found';
$string['error_nopermission'] = 'You do not have permission to perform this action';
$string['error_notingroup'] = 'You must be part of a group to submit';
$string['error_invaliddata'] = 'Invalid data';
