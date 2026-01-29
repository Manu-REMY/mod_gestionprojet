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
$string['step8'] = 'Logbook';
$string['step8_desc'] = 'Daily project log (students)';

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
$string['export_pdf_notice'] = 'The PDF export generates a document with all entered information.';
$string['your_group'] = 'You are working in group';
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

// Logbook (Step 8)
$string['logbook_date'] = 'Date';
$string['logbook_tasks_today'] = 'Tasks of the day';
$string['logbook_tasks_future'] = 'Upcoming tasks';
$string['logbook_status'] = 'Status';
$string['logbook_status_ahead'] = 'Ahead';
$string['logbook_status_ontime'] = 'On time';
$string['logbook_status_late'] = 'Late';
$string['logbook_add_line'] = 'Add a line';
$string['logbook_remove_line'] = 'Remove line';

// Errors
$string['error_nosuchinstance'] = 'Project Management instance not found';
$string['error_nopermission'] = 'You do not have permission to perform this action';
$string['error_notingroup'] = 'You must be part of a group to submit';
$string['error_invaliddata'] = 'Invalid data';

// AI Configuration
$string['ai_settings'] = 'AI Evaluation';
$string['ai_enabled'] = 'Enable AI evaluation';
$string['ai_enabled_help'] = 'If enabled, student submissions will be automatically evaluated by AI by comparing them to the correction models provided by the teacher.';
$string['ai_provider'] = 'AI Provider';
$string['ai_provider_help'] = 'Select the AI provider to use for automatic evaluation. Each provider requires a valid API key.';
$string['ai_provider_select'] = '-- Select a provider --';
$string['ai_api_key'] = 'API Key';
$string['ai_api_key_help'] = 'Enter your API key for the selected provider. The key will be encrypted before storage for security reasons.';
$string['ai_test_connection'] = 'Test connection';
$string['ai_test_success'] = 'Connection successful! The API key is valid.';
$string['ai_test_failed'] = 'Connection test failed.';
$string['ai_connection_error'] = 'Unable to connect to the API server.';
$string['ai_provider_invalid'] = 'Invalid AI provider.';
$string['ai_provider_required'] = 'Please select an AI provider.';
$string['ai_api_key_required'] = 'Please enter an API key.';
$string['ai_api_key_builtin'] = 'Built-in API key';
$string['ai_api_key_builtin_notice'] = '<div class="alert alert-info"><i class="fa fa-info-circle"></i> This provider uses a built-in API key. No additional configuration is needed.</div>';
$string['ai_provider_builtin'] = 'Built-in key';

// Correction models (Phase 3)
$string['correction_models'] = 'Correction Models';
$string['correction_models_desc'] = 'Define expected answers for AI evaluation';
$string['correction_models_info'] = 'For automatic evaluation';
$string['correction_models_configure'] = 'Configure';
$string['correction_models_hub_title'] = 'Correction Models Hub';
$string['correction_models_hub_desc'] = 'Define model answers for each student step here. These models will serve as reference for automatic AI evaluation. You can also add specific instructions to guide the evaluation.';
$string['correction_model_complete'] = 'Model defined';
$string['correction_model_incomplete'] = 'To be defined';
$string['correction_model_configure'] = 'Configure model';
$string['ai_evaluation_enabled'] = 'AI evaluation enabled for this activity';
$string['ai_evaluation_disabled_hint'] = 'AI evaluation is not enabled. You can enable it in the activity settings.';
$string['ai_instructions_set'] = 'AI instructions set';
$string['no_student_steps_enabled'] = 'No student steps are enabled for this activity.';
$string['ai_instructions'] = 'AI Correction Instructions';
$string['ai_instructions_help'] = 'Provide specific instructions to guide automatic AI evaluation. For example: grading criteria, key points, mandatory or bonus elements.';
$string['ai_instructions_placeholder'] = 'E.g.: Check for the presence of 3 key elements. Award bonus points if student mentions concrete examples. Penalize off-topic answers...';

// Phase 3.5: Submission dates and timeline
$string['submission_dates'] = 'Submission Dates';
$string['submission_date'] = 'Expected submission date';
$string['submission_date_help'] = 'Date when students should submit this work. This date is automatically calculated from the planning if defined.';
$string['deadline_date'] = 'Deadline';
$string['deadline_date_help'] = 'Final submission deadline. After this date, the work will be automatically locked.';
$string['date_from_planning'] = 'Date calculated from planning';
$string['expected_submission'] = 'Expected submission';
$string['deadline'] = 'Deadline';
$string['overdue'] = 'Overdue';
$string['due_soon'] = 'Due soon';
$string['submission_section_title'] = 'Submission';
$string['submit_step'] = 'Submit this work';
$string['confirm_submit'] = 'Are you sure you want to submit this work? You will not be able to modify it after submission.';
$string['submitting'] = 'Submitting...';
$string['submissionsuccess'] = 'Work submitted successfully';
$string['submissionerror'] = 'Error during submission';
$string['already_submitted'] = 'Work already submitted';
$string['submitted_at'] = 'Submitted on';
$string['submissiondisabled'] = 'Submission is not enabled for this activity';
$string['alreadysubmitted'] = 'This work has already been submitted';
$string['submissionunlocked'] = 'Submission unlocked';
$string['submissionnotfound'] = 'Submission not found';

// Step 3 Timeline
$string['vacation_zone'] = 'Vacation zone';
$string['vacation_zone_help'] = 'Select the school vacation zone to correctly display vacation periods in the timeline.';
$string['working_weeks'] = 'Working weeks';
$string['total_hours'] = 'Total hours';
$string['hours_exceeded'] = 'Total hours exceed available time';
$string['auto_distribute'] = 'Auto-distribute';
$string['vacation_overlay'] = 'School holidays';

// Phase 4: AI Evaluation Engine
$string['ai_evaluation'] = 'AI Evaluation';
$string['ai_evaluating'] = 'AI evaluation in progress...';
$string['ai_evaluation_complete'] = 'AI evaluation complete';
$string['ai_evaluation_failed'] = 'AI evaluation failed';
$string['ai_evaluation_applied'] = 'AI evaluation applied';
$string['ai_evaluation_pending'] = 'AI evaluation pending';
$string['ai_evaluation_queued'] = 'AI evaluation queued';
$string['ai_evaluation_not_ready'] = 'AI evaluation is not ready yet';
$string['ai_grade'] = 'AI suggested grade';
$string['ai_feedback'] = 'AI feedback';
$string['ai_criteria'] = 'Evaluation criteria';
$string['ai_keywords_found'] = 'Key elements found';
$string['ai_keywords_missing'] = 'Missing elements';
$string['ai_suggestions'] = 'Suggestions for improvement';
$string['ai_confidence'] = 'Confidence level';
$string['ai_tokens_used'] = 'Tokens used';
$string['apply_ai_grade'] = 'Apply AI grade';
$string['apply_with_modifications'] = 'Apply with modifications';
$string['retry_evaluation'] = 'Retry AI evaluation';
$string['ai_auto_apply'] = 'Auto-apply AI grades';
$string['ai_auto_apply_help'] = 'If enabled, AI-generated grades will be automatically applied to submissions without teacher review.';
$string['task_evaluate_submission'] = 'Evaluate submission with AI';
$string['task_auto_submit_deadline'] = 'Auto-submit drafts at deadline';
$string['ai_not_enabled'] = 'AI evaluation is not enabled for this activity';
$string['ai_grade_applied'] = 'AI grade applied successfully';
$string['ai_grade_apply_failed'] = 'Failed to apply AI grade';
$string['ai_no_feedback'] = 'No feedback generated by AI';
$string['ai_parse_error'] = 'Error parsing AI response';
$string['ai_invalid_response'] = 'Invalid AI response';
$string['trigger_ai_evaluation'] = 'Trigger AI evaluation';
$string['ai_evaluation_section'] = 'Automatic AI Evaluation';
$string['no_ai_evaluation'] = 'No AI evaluation available';
$string['ai_review'] = 'AI Evaluation Review';

// Phase 5: Gradebook per-step integration
$string['gradebook_settings'] = 'Gradebook settings';
$string['grade_mode'] = 'Grading mode';
$string['grade_mode_help'] = 'Choose how grades appear in the Moodle gradebook. In "Single grade" mode, an average is calculated. In "Per-step grades" mode, each step has its own entry in the gradebook.';
$string['grade_mode_combined'] = 'Single grade (average of steps)';
$string['grade_mode_per_step'] = 'Per-step grades (separate)';

// Phase 6: UI Improvements - Progress indicators and notifications
$string['ai_progress_title'] = 'AI Evaluation in progress';
$string['ai_progress_initializing'] = 'Initializing...';
$string['ai_progress_sending'] = 'Sending evaluation request...';
$string['ai_progress_processing'] = 'Processing...';
$string['ai_progress_analyzing'] = 'Analyzing submission...';
$string['ai_progress_generating'] = 'Generating evaluation...';
$string['ai_progress_finalizing'] = 'Finalizing...';
$string['ai_progress_complete'] = 'Evaluation complete!';
$string['ai_progress_timeout'] = 'Timeout exceeded. Please try again.';
$string['ai_progress_elapsed'] = 'Time elapsed';
$string['ai_status_pending'] = 'Pending';
$string['ai_status_processing'] = 'Processing';
$string['ai_status_completed'] = 'Completed';
$string['ai_status_failed'] = 'Failed';
$string['ai_status_applied'] = 'Applied';
$string['toast_success'] = 'Success';
$string['toast_error'] = 'Error';
$string['toast_warning'] = 'Warning';
$string['toast_info'] = 'Information';
$string['toast_ai_title'] = 'AI Evaluation';
$string['toast_saved'] = 'Changes saved';
$string['toast_submitted'] = 'Submission sent';
$string['toast_grade_applied'] = 'Grade applied successfully';
$string['toast_evaluation_started'] = 'AI evaluation started';
$string['toast_evaluation_complete'] = 'Evaluation complete - Grade: {$a}/20';
$string['toast_network_error'] = 'Server connection error';
$string['toast_close'] = 'Close';

// Phase 6.3: Unlock submission and bulk re-evaluate
$string['submission_status_submitted'] = 'Work submitted';
$string['submission_status_draft'] = 'Draft';
$string['submission_status_draft_desc'] = 'The student has not yet submitted this work';
$string['unlock_submission'] = 'Cancel submission';
$string['unlock_submission_desc'] = 'Allow the student to edit their work again';
$string['confirm_unlock_submission'] = 'Are you sure you want to cancel the submission? The student will be able to edit their work again.';
$string['bulk_reevaluate'] = 'Re-evaluate all with AI';
$string['bulk_reevaluate_desc'] = 'Delete existing evaluations and re-run AI for all submissions of this step';
$string['confirm_bulk_reevaluate'] = 'Are you sure you want to re-run AI evaluation for all submissions of this step? Existing evaluations will be deleted.';
$string['bulk_reevaluate_processing'] = 'Processing...';
$string['bulk_reevaluate_success'] = '{$a->deleted} evaluation(s) deleted, {$a->queued} new evaluation(s) started';
$string['bulk_reevaluate_no_submissions'] = 'No submitted work for this step';
$string['ai_evaluation_deleted'] = 'AI evaluation deleted';
$string['ai_evaluation_delete_failed'] = 'Failed to delete AI evaluation';
$string['ai_evaluations_deleted'] = '{$a} AI evaluation(s) deleted';
$string['step_not_enabled'] = 'This step is not enabled';
$string['nousers'] = 'No users';

// Phase 6.4: Visibility options for student feedback
$string['visibility_options'] = 'Elements to show to students';
$string['visibility_options_desc'] = 'Select which information to share with students. All elements are shown by default.';
$string['show_feedback_to_student'] = 'Feedback';
$string['show_criteria_to_student'] = 'Evaluation criteria';
$string['show_keywords_found_to_student'] = 'Found elements';
$string['show_keywords_missing_to_student'] = 'Missing elements';
$string['show_suggestions_to_student'] = 'Improvement suggestions';

// Missing strings
$string['confirm_revert'] = 'Are you sure you want to revert to draft?';
$string['confirm_submission'] = 'Are you sure you want to submit this work?';
$string['export_pdf_coming_soon'] = 'PDF export coming soon';
$string['feedback'] = 'Feedback';
$string['grade'] = 'Grade';
$string['gradesaved'] = 'Grade saved';
$string['invalidstep'] = 'Invalid step';
$string['revert_to_draft'] = 'Revert to draft';
$string['submit'] = 'Submit';
$string['submitted_on'] = 'Submitted on';
$string['teacher_feedback'] = 'Teacher feedback';

// Privacy API - Complete metadata strings
$string['privacy:metadata:timecreated'] = 'The time when the record was created';
$string['privacy:metadata:timemodified'] = 'The time when the record was last modified';

// CDCF (Step 4)
$string['privacy:metadata:gestionprojet_cdcf:userid'] = 'The ID of the user who submitted the requirements specification';
$string['privacy:metadata:gestionprojet_cdcf:produit'] = 'The product defined by the student';
$string['privacy:metadata:gestionprojet_cdcf:milieu'] = 'The environment of use defined by the student';
$string['privacy:metadata:gestionprojet_cdcf:fp'] = 'The main function defined by the student';
$string['privacy:metadata:gestionprojet_cdcf:interacteurs_data'] = 'JSON data containing stakeholders, constraint functions and criteria';
$string['privacy:metadata:gestionprojet_cdcf:status'] = 'The submission status (draft or submitted)';
$string['privacy:metadata:gestionprojet_cdcf:timesubmitted'] = 'The time when the work was submitted';

// Essai (Step 5)
$string['privacy:metadata:gestionprojet_essai:userid'] = 'The ID of the user who submitted the test sheet';
$string['privacy:metadata:gestionprojet_essai:nom_essai'] = 'The name of the test';
$string['privacy:metadata:gestionprojet_essai:date_essai'] = 'The date of the test';
$string['privacy:metadata:gestionprojet_essai:groupe_eleves'] = 'The students involved in the test';
$string['privacy:metadata:gestionprojet_essai:fonction_service'] = 'The service function being tested';
$string['privacy:metadata:gestionprojet_essai:etapes_protocole'] = 'The protocol steps defined by the student';
$string['privacy:metadata:gestionprojet_essai:resultats_obtenus'] = 'The results obtained during the test';
$string['privacy:metadata:gestionprojet_essai:conclusion'] = 'The conclusion written by the student';
$string['privacy:metadata:gestionprojet_essai:status'] = 'The submission status (draft or submitted)';
$string['privacy:metadata:gestionprojet_essai:timesubmitted'] = 'The time when the work was submitted';

// Rapport (Step 6)
$string['privacy:metadata:gestionprojet_rapport:userid'] = 'The ID of the user who submitted the project report';
$string['privacy:metadata:gestionprojet_rapport:titre_projet'] = 'The project title';
$string['privacy:metadata:gestionprojet_rapport:auteurs'] = 'The authors of the report';
$string['privacy:metadata:gestionprojet_rapport:besoin_projet'] = 'The project need described by the student';
$string['privacy:metadata:gestionprojet_rapport:solutions'] = 'The solutions chosen by the student';
$string['privacy:metadata:gestionprojet_rapport:realisation'] = 'The implementation description';
$string['privacy:metadata:gestionprojet_rapport:difficultes'] = 'The difficulties encountered';
$string['privacy:metadata:gestionprojet_rapport:bilan'] = 'The project summary';
$string['privacy:metadata:gestionprojet_rapport:status'] = 'The submission status (draft or submitted)';
$string['privacy:metadata:gestionprojet_rapport:timesubmitted'] = 'The time when the work was submitted';

// Besoin Eleve (Step 7)
$string['privacy:metadata:gestionprojet_besoin_eleve'] = 'Student needs expression (Horn Diagram) submissions';
$string['privacy:metadata:gestionprojet_besoin_eleve:userid'] = 'The ID of the user who submitted the needs expression';
$string['privacy:metadata:gestionprojet_besoin_eleve:aqui'] = 'Who the product serves (student answer)';
$string['privacy:metadata:gestionprojet_besoin_eleve:surquoi'] = 'What the product acts on (student answer)';
$string['privacy:metadata:gestionprojet_besoin_eleve:dansquelbut'] = 'The purpose of the product (student answer)';
$string['privacy:metadata:gestionprojet_besoin_eleve:status'] = 'The submission status (draft or submitted)';
$string['privacy:metadata:gestionprojet_besoin_eleve:timesubmitted'] = 'The time when the work was submitted';

// Carnet (Step 8)
$string['privacy:metadata:gestionprojet_carnet'] = 'Project logbook entries submitted by students';
$string['privacy:metadata:gestionprojet_carnet:userid'] = 'The ID of the user who submitted the logbook';
$string['privacy:metadata:gestionprojet_carnet:tasks_data'] = 'JSON data containing daily log entries with tasks, status and observations';
$string['privacy:metadata:gestionprojet_carnet:status'] = 'The submission status (draft or submitted)';
$string['privacy:metadata:gestionprojet_carnet:timesubmitted'] = 'The time when the work was submitted';

// AI Evaluations
$string['privacy:metadata:gestionprojet_ai_evaluations'] = 'AI evaluation results for student submissions';
$string['privacy:metadata:gestionprojet_ai_evaluations:userid'] = 'The ID of the user whose work was evaluated';
$string['privacy:metadata:gestionprojet_ai_evaluations:step'] = 'The step number that was evaluated';
$string['privacy:metadata:gestionprojet_ai_evaluations:provider'] = 'The AI provider used for evaluation';
$string['privacy:metadata:gestionprojet_ai_evaluations:parsed_grade'] = 'The grade suggested by the AI';
$string['privacy:metadata:gestionprojet_ai_evaluations:parsed_feedback'] = 'The feedback generated by the AI';
$string['privacy:metadata:gestionprojet_ai_evaluations:status'] = 'The evaluation status (pending, completed, applied, etc.)';
$string['privacy:metadata:gestionprojet_ai_evaluations:applied_by'] = 'The ID of the teacher who applied the AI grade';
$string['privacy:metadata:gestionprojet_ai_evaluations:applied_at'] = 'The time when the grade was applied';

// History
$string['privacy:metadata:gestionprojet_history:tablename'] = 'The database table that was modified';
$string['privacy:metadata:gestionprojet_history:fieldname'] = 'The field that was changed';
$string['privacy:metadata:gestionprojet_history:oldvalue'] = 'The previous value before modification';
$string['privacy:metadata:gestionprojet_history:newvalue'] = 'The new value after modification';

// Phase 7: Teacher Dashboard
$string['dashboard:title'] = 'Dashboard';
$string['dashboard:submissionprogress'] = 'Submission Progress';
$string['dashboard:submitted'] = 'submitted';
$string['dashboard:draft'] = 'Draft';
$string['dashboard:graded'] = 'Graded';
$string['dashboard:pending'] = 'Pending';
$string['dashboard:pendinggrade'] = 'Pending grade';
$string['dashboard:gradeaverage'] = 'Grade Average';
$string['dashboard:aisummary'] = 'AI Summary';
$string['dashboard:refreshsummary'] = 'Refresh';
$string['dashboard:difficulties'] = 'Identified Difficulties';
$string['dashboard:strengths'] = 'Strengths';
$string['dashboard:recommendations'] = 'Teaching Recommendations';
$string['dashboard:analyzedfrom'] = 'Based on';
$string['dashboard:generatedat'] = 'Generated at';
$string['dashboard:tokenusage'] = 'Token Usage';
$string['dashboard:evaluations'] = 'evaluations';
$string['dashboard:totaltokens'] = 'Total tokens';
$string['dashboard:prompttokens'] = 'Prompt tokens';
$string['dashboard:completiontokens'] = 'Completion tokens';
$string['dashboard:avgpereval'] = 'Average per evaluation';
$string['dashboard:notenoughevaluations'] = 'Not enough evaluations to generate summary';
$string['dashboard:evaluationsrequired'] = 'minimum evaluations required';
$string['dashboard:nodata'] = 'No data';
$string['dashboard:ai_disabled'] = 'AI evaluation is not enabled for this activity';
$string['dashboard:ai_error'] = 'Error generating AI summary';
$string['dashboard:summary_generated'] = 'Summary generated successfully';
$string['dashboard:summary_cached'] = 'Summary available from cache';
$string['dashboard:summary_refreshed'] = 'Summary refreshed successfully';
$string['error:invalidstep'] = 'Invalid step number';
