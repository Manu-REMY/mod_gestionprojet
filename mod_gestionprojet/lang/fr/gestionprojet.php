<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * French language strings for mod_gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Module principal
$string['modulename'] = 'Gestion de Projet';
$string['modulenameplural'] = 'Gestions de Projet';
$string['modulename_help'] = 'Le module Gestion de Projet permet aux enseignants de créer des projets pédagogiques structurés en 6 étapes. Les enseignants configurent les 3 premières étapes, puis les élèves (individuellement ou en groupe) complètent les 3 dernières.';
$string['pluginname'] = 'Gestion de Projet';
$string['pluginadministration'] = 'Administration Gestion de Projet';

// Capacités
$string['gestionprojet:addinstance'] = 'Ajouter une nouvelle activité Gestion de Projet';
$string['gestionprojet:view'] = 'Voir l\'activité Gestion de Projet';
$string['gestionprojet:configureteacherpages'] = 'Configurer les pages enseignant';
$string['gestionprojet:submit'] = 'Soumettre le travail';
$string['gestionprojet:viewallsubmissions'] = 'Voir toutes les soumissions';
$string['gestionprojet:grade'] = 'Noter les soumissions';
$string['gestionprojet:lock'] = 'Verrouiller/déverrouiller les pages';
$string['gestionprojet:viewhistory'] = 'Voir l\'historique des modifications';
$string['gestionprojet:exportall'] = 'Exporter tous les projets';

// Navigation
$string['home'] = 'Accueil';
$string['navigation_teacher'] = 'Configuration Enseignant';
$string['navigation_student'] = 'Travail Élève';
$string['navigation_grading'] = 'Correction';

// Les 6 étapes
$string['step1'] = 'Fiche Descriptive';
$string['step2'] = 'Expression du Besoin';
$string['step3'] = 'Planification';
$string['step4'] = 'Cahier des Charges';
$string['step5'] = 'Fiche Essai';
$string['step6'] = 'Rapport de Projet';
$string['step7'] = 'Expression du Besoin (Élève)';

$string['step1_desc'] = 'Définir le cadre du projet (enseignant)';
$string['step2_desc'] = 'Diagramme Bête à Corne (enseignant)';
$string['step3_desc'] = 'Planning et timeline (enseignant)';
$string['step4_desc'] = 'Diagramme des interacteurs (élèves)';
$string['step5_desc'] = 'Tests et validation (élèves)';
$string['step6_desc'] = 'Rapport final (élèves)';
$string['step7_desc'] = 'Diagramme Bête à Corne (élèves)';
$string['step8'] = 'Carnet de bord';
$string['step8_desc'] = 'Journal de bord du projet (élèves)';

// Formulaire de configuration
$string['activesteps'] = 'Étapes actives';
$string['submissionsettings'] = 'Paramètres de soumission';
$string['groupsubmission'] = 'Soumission de groupe';
$string['enable_submission'] = 'Activer la soumission';
$string['autosave_interval'] = 'Intervalle de sauvegarde automatique';
$string['autosave_interval_help'] = 'Fréquence de sauvegarde automatique en secondes (recommandé: 30s)';

// Fiche Descriptive
$string['intitule'] = 'Intitulé du projet';
$string['niveau'] = 'Niveau scolaire';
$string['support'] = 'Support pédagogique';
$string['duree'] = 'Durée du projet';
$string['besoin'] = 'Expression du besoin';
$string['production'] = 'Production attendue';
$string['outils'] = 'Outils et moyens techniques';
$string['evaluation'] = 'Modalités d\'évaluation';
$string['competences'] = 'Compétences travaillées';
$string['image'] = 'Image du projet';

// Expression du Besoin
$string['aqui'] = 'À qui rend-il service ?';
$string['surquoi'] = 'Sur quoi agit-il ?';
$string['dansquelbut'] = 'Dans quel but ?';
$string['bete_a_corne_title'] = 'Qu\'est-ce que la "Bête à Corne" ?';
$string['bete_a_corne_description'] = 'La Bête à Corne est un outil graphique utilisé pour exprimer le besoin d\'un projet de manière simple et claire.';
$string['bete_a_corne_diagram'] = 'Diagramme Bête à Corne';
$string['aqui_help'] = 'Identifier les utilisateurs ou bénéficiaires du produit';
$string['surquoi_help'] = 'Définir l\'objet ou le système sur lequel le produit intervient';
$string['dansquelbut_help'] = 'Préciser l\'objectif principal du produit';

// Planification
$string['projectname'] = 'Nom du projet';
$string['startdate'] = 'Date de début';
$string['enddate'] = 'Date de fin';
$string['vacationzone'] = 'Zone de vacances scolaires';
$string['vacationzone_none'] = 'Aucune';
$string['vacationzone_a'] = 'Zone A';
$string['vacationzone_b'] = 'Zone B';
$string['vacationzone_c'] = 'Zone C';
$string['task1'] = 'Expression du besoin';
$string['task2'] = 'Rédaction du cahier des charges';
$string['task3'] = 'Recherche de solutions techniques';
$string['task4'] = 'Modélisation/Simulation/Prototypage';
$string['task5'] = 'Validation du système';
$string['hours'] = 'heures';
$string['planning_description'] = 'Planifiez votre projet en définissant les dates et la durée de chaque tâche.';
$string['project_planning'] = 'Planification du projet';
$string['task_durations'] = 'Durée des tâches';
$string['hours_per_week_info'] = 'Base : 1,5h par semaine en classe';
$string['timeline_preview'] = 'Aperçu de la timeline';
$string['finish'] = 'Terminer';

// Cahier des Charges
$string['produit'] = 'Produit';
$string['milieu'] = 'Milieu d\'utilisation';
$string['interacteurs'] = 'Interacteurs';
$string['fonction_principale'] = 'Fonction principale (FP)';
$string['fonctions_contraintes'] = 'Fonctions contraintes (FC)';

// Fiche Essai (Step 5)
$string['nom_essai'] = 'Nom de l\'essai';
$string['nom_essai_placeholder'] = 'Entrez le nom de l\'essai';
$string['date'] = 'Date';
$string['groupe_eleves'] = 'Groupe / Élève(s)';
$string['groupe_eleves_placeholder'] = 'Entrez les noms des élèves ou du groupe';
$string['objectif'] = 'Objectif de l\'essai';
$string['protocole'] = 'Protocole expérimental';
$string['precautions'] = 'Précautions expérimentales';
$string['resultats'] = 'Résultats et observations';
$string['conclusion'] = 'Conclusion';
$string['fonction_service'] = 'Fonction de service/contrainte';
$string['niveaux_reussite'] = 'Niveaux de réussite';
$string['etapes_protocole'] = 'Étapes du protocole';
$string['materiel_outils'] = 'Matériel et outils';
$string['resultats_obtenus'] = 'Résultats obtenus';
$string['observations_remarques'] = 'Observations et remarques';
$string['fonctions_service'] = 'Fonctions de service (FS)';
$string['critere'] = 'Critère d\'appréciation';
$string['niveau_attendu'] = 'Niveau attendu';
$string['unite'] = 'Unité';

// Rapport de Projet (Step 6)
$string['titre_projet'] = 'Titre du projet';
$string['membres_groupe'] = 'Membres du groupe';
$string['besoin_projet'] = 'À quel besoin répond le projet';
$string['imperatifs'] = 'Quels impératifs devaient être respectés ?';
$string['solutions'] = 'Quelles solutions avez-vous choisies pour répondre à la problématique ?';
$string['justification'] = 'Pourquoi avez-vous choisi ces solutions ?';
$string['realisation'] = 'Description de la réalisation';
$string['difficultes'] = 'Difficultés rencontrées';
$string['validation'] = 'Résultats des tests';
$string['ameliorations'] = 'Améliorations possibles';
$string['bilan'] = 'Bilan du projet';
$string['perspectives'] = 'Perspectives et suite du projet';
$string['auteurs'] = 'Auteurs';
$string['besoins'] = 'Besoins';
$string['contraintes'] = 'Contraintes';

// Fiche Essai
$string['objectif'] = 'Objectif de l\'essai';
$string['protocole'] = 'Protocole expérimental';
$string['precautions'] = 'Précautions';
$string['resultats'] = 'Résultats et observations';
$string['conclusion'] = 'Conclusion';

// Rapport
$string['auteurs'] = 'Auteurs';
$string['besoins'] = 'Besoins et contraintes';
$string['contraintes'] = 'Contraintes identifiées';
$string['solutions'] = 'Solutions choisies';
$string['realisation'] = 'Réalisation';
$string['difficultes'] = 'Difficultés rencontrées';
$string['validation'] = 'Validation';

// Interface de correction
$string['grading_navigation'] = 'Navigation correction';
$string['grading_step'] = 'Étape à corriger';
$string['grading_group'] = 'Groupe';
$string['grading_previous'] = 'Groupe précédent';
$string['grading_next'] = 'Groupe suivant';
$string['grading_feedback'] = 'Commentaires';
$string['grading_grade'] = 'Note / 20';
$string['grading_save'] = 'Enregistrer la note';
$string['grading_context_maintained'] = 'Le contexte de correction est conservé';

// Actions
$string['next'] = 'Suivant';
$string['previous'] = 'Précédent';
$string['lock'] = 'Verrouiller';
$string['unlock'] = 'Déverrouiller';
$string['locked'] = 'Verrouillé par l\'enseignant';
$string['save'] = 'Enregistrer';
$string['saving'] = 'Sauvegarde en cours...';
$string['saved'] = 'Sauvegardé automatiquement';
$string['export_pdf'] = 'Exporter en PDF';
$string['export_pdf_notice'] = 'L\'export PDF génère un document avec toutes les informations saisies.';
$string['your_group'] = 'Vous travaillez en groupe';
$string['export_all'] = 'Exporter tous les projets';
$string['view_history'] = 'Voir l\'historique';

// Messages
$string['autosave_success'] = 'Sauvegarde automatique effectuée';
$string['autosave_error'] = 'Erreur lors de la sauvegarde automatique';
$string['no_groups'] = 'Aucun groupe configuré. Veuillez créer des groupes dans ce cours.';
$string['no_submission'] = 'Aucune soumission pour ce groupe';
$string['submission_saved'] = 'Votre travail a été sauvegardé';
$string['teacher_pages_locked'] = 'Les pages enseignant sont verrouillées. Vous ne pouvez pas les modifier.';
$string['must_complete_teacher_pages'] = 'L\'enseignant doit d\'abord compléter les 3 premières étapes.';

// Privacy
$string['privacy:metadata:gestionprojet_description'] = 'Informations sur la fiche descriptive configurée par l\'enseignant';
$string['privacy:metadata:gestionprojet_cdcf'] = 'Cahier des charges fonctionnel soumis par les groupes';
$string['privacy:metadata:gestionprojet_essai'] = 'Fiche d\'essai soumise par les groupes';
$string['privacy:metadata:gestionprojet_rapport'] = 'Rapport de projet soumis par les groupes';
$string['privacy:metadata:gestionprojet_history'] = 'Historique des modifications';
$string['privacy:metadata:gestionprojet_history:userid'] = 'L\'utilisateur qui a effectué la modification';
$string['privacy:metadata:gestionprojet_history:timecreated'] = 'Date et heure de la modification';
$string['privacy:metadata:groupid'] = 'Le groupe auquel appartient la soumission';
$string['privacy:metadata:grade'] = 'La note attribuée par l\'enseignant';
$string['privacy:metadata:feedback'] = 'Les commentaires de l\'enseignant';

// Carnet de bord (Step 8)
$string['logbook_date'] = 'Date';
$string['logbook_tasks_today'] = 'Tâches du jour';
$string['logbook_tasks_future'] = 'Tâches à venir';
$string['logbook_status'] = 'Statut';
$string['logbook_status_ahead'] = 'En avance';
$string['logbook_status_ontime'] = 'À l\'heure';
$string['logbook_status_late'] = 'En retard';
$string['logbook_add_line'] = 'Ajouter une ligne';
$string['logbook_remove_line'] = 'Supprimer la ligne';

// Erreurs
$string['error_nosuchinstance'] = 'Instance de Gestion de Projet introuvable';
$string['error_nopermission'] = 'Vous n\'avez pas la permission d\'effectuer cette action';
$string['error_notingroup'] = 'Vous devez faire partie d\'un groupe pour soumettre';
$string['error_invaliddata'] = 'Données invalides';

// Configuration IA
$string['ai_settings'] = 'Évaluation par IA';
$string['ai_enabled'] = 'Activer l\'évaluation IA';
$string['ai_enabled_help'] = 'Si activé, les productions élèves seront évaluées automatiquement par l\'IA en les comparant aux modèles de correction fournis par l\'enseignant.';
$string['ai_provider'] = 'Fournisseur IA';
$string['ai_provider_help'] = 'Sélectionnez le fournisseur d\'IA à utiliser pour l\'évaluation automatique. Chaque fournisseur nécessite une clé API valide.';
$string['ai_provider_select'] = '-- Sélectionnez un fournisseur --';
$string['ai_api_key'] = 'Clé API';
$string['ai_api_key_help'] = 'Entrez votre clé API pour le fournisseur sélectionné. La clé sera chiffrée avant stockage pour des raisons de sécurité.';
$string['ai_test_connection'] = 'Tester la connexion';
$string['ai_test_success'] = 'Connexion réussie ! La clé API est valide.';
$string['ai_test_failed'] = 'Échec du test de connexion.';
$string['ai_connection_error'] = 'Impossible de se connecter au serveur API.';
$string['ai_provider_invalid'] = 'Fournisseur IA invalide.';
$string['ai_provider_required'] = 'Veuillez sélectionner un fournisseur IA.';
$string['ai_api_key_required'] = 'Veuillez entrer une clé API.';
$string['ai_api_key_builtin'] = 'Clé API intégrée';
$string['ai_api_key_builtin_notice'] = '<div class="alert alert-info"><i class="fa fa-info-circle"></i> Ce fournisseur utilise une clé API intégrée. Aucune configuration supplémentaire n\'est nécessaire.</div>';
$string['ai_provider_builtin'] = 'Clé intégrée';

// Modèles de correction (Phase 3)
$string['correction_models'] = 'Modèles de correction';
$string['correction_models_desc'] = 'Définir les réponses attendues pour l\'évaluation IA';
$string['correction_models_info'] = 'Pour l\'évaluation automatique';
$string['correction_models_configure'] = 'Configurer';
$string['correction_models_hub_title'] = 'Hub des modèles de correction';
$string['correction_models_hub_desc'] = 'Définissez ici les réponses modèles pour chaque étape élève. Ces modèles serviront de référence pour l\'évaluation automatique par IA. Vous pouvez également ajouter des instructions spécifiques pour guider l\'évaluation.';
$string['correction_model_complete'] = 'Modèle défini';
$string['correction_model_incomplete'] = 'À définir';
$string['correction_model_configure'] = 'Configurer le modèle';
$string['ai_evaluation_enabled'] = 'Évaluation IA activée pour cette activité';
$string['ai_evaluation_disabled_hint'] = 'L\'évaluation IA n\'est pas activée. Vous pouvez l\'activer dans les paramètres de l\'activité.';
$string['ai_instructions_set'] = 'Instructions IA définies';
$string['no_student_steps_enabled'] = 'Aucune étape élève n\'est activée pour cette activité.';
$string['ai_instructions'] = 'Instructions de correction IA';
$string['ai_instructions_help'] = 'Fournissez des instructions spécifiques pour guider l\'évaluation automatique par IA. Par exemple : critères de notation, points d\'attention, éléments obligatoires ou bonus.';
$string['ai_instructions_placeholder'] = 'Ex: Vérifier la présence des 3 éléments clés. Accorder des points bonus si l\'élève mentionne des exemples concrets. Pénaliser les réponses hors sujet...';

// Phase 3.5: Dates de soumission et timeline
$string['submission_dates'] = 'Dates de soumission';
$string['submission_date'] = 'Date de soumission prévue';
$string['submission_date_help'] = 'Date à laquelle les élèves doivent soumettre ce travail. Cette date est automatiquement calculée depuis la planification si définie.';
$string['deadline_date'] = 'Date limite';
$string['deadline_date_help'] = 'Date limite de soumission. Après cette date, le travail sera automatiquement verrouillé.';
$string['date_from_planning'] = 'Date calculée depuis la planification';
$string['expected_submission'] = 'Soumission attendue';
$string['deadline'] = 'Date limite';
$string['overdue'] = 'En retard';
$string['due_soon'] = 'Bientôt dû';
$string['submission_section_title'] = 'Soumission';
$string['submit_step'] = 'Soumettre ce travail';
$string['confirm_submit'] = 'Êtes-vous sûr de vouloir soumettre ce travail ? Vous ne pourrez plus le modifier après la soumission.';
$string['submitting'] = 'Soumission en cours...';
$string['submissionsuccess'] = 'Travail soumis avec succès';
$string['submissionerror'] = 'Erreur lors de la soumission';
$string['already_submitted'] = 'Travail déjà soumis';
$string['submitted_at'] = 'Soumis le';
$string['submissiondisabled'] = 'La soumission n\'est pas activée pour cette activité';
$string['alreadysubmitted'] = 'Ce travail a déjà été soumis';
$string['submissionunlocked'] = 'Soumission déverrouillée';
$string['submissionnotfound'] = 'Soumission introuvable';

// Step 3 Timeline
$string['vacation_zone'] = 'Zone de vacances';
$string['vacation_zone_help'] = 'Sélectionnez la zone de vacances scolaires pour afficher correctement les périodes de vacances dans la timeline.';
$string['working_weeks'] = 'Semaines travaillées';
$string['total_hours'] = 'Total heures';
$string['hours_exceeded'] = 'Le total des heures dépasse le temps disponible';
$string['auto_distribute'] = 'Répartir automatiquement';
$string['vacation_overlay'] = 'Vacances scolaires';

// Phase 4: AI Evaluation Engine
$string['ai_evaluation'] = 'Évaluation IA';
$string['ai_evaluating'] = 'Évaluation IA en cours...';
$string['ai_evaluation_complete'] = 'Évaluation IA terminée';
$string['ai_evaluation_failed'] = 'Échec de l\'évaluation IA';
$string['ai_evaluation_applied'] = 'Évaluation IA appliquée';
$string['ai_evaluation_pending'] = 'Évaluation IA en attente';
$string['ai_evaluation_queued'] = 'Évaluation IA mise en file d\'attente';
$string['ai_evaluation_not_ready'] = 'L\'évaluation IA n\'est pas encore prête';
$string['ai_grade'] = 'Note suggérée par l\'IA';
$string['ai_feedback'] = 'Commentaires de l\'IA';
$string['ai_criteria'] = 'Critères d\'évaluation';
$string['ai_keywords_found'] = 'Éléments clés trouvés';
$string['ai_keywords_missing'] = 'Éléments manquants';
$string['ai_suggestions'] = 'Suggestions d\'amélioration';
$string['ai_confidence'] = 'Niveau de confiance';
$string['ai_tokens_used'] = 'Tokens utilisés';
$string['apply_ai_grade'] = 'Appliquer la note IA';
$string['apply_with_modifications'] = 'Appliquer avec modifications';
$string['retry_evaluation'] = 'Relancer l\'évaluation IA';
$string['ai_auto_apply'] = 'Appliquer automatiquement les notes IA';
$string['ai_auto_apply_help'] = 'Si activé, les notes générées par l\'IA seront automatiquement appliquées aux soumissions sans révision enseignant.';
$string['task_evaluate_submission'] = 'Évaluer une soumission par IA';
$string['task_auto_submit_deadline'] = 'Soumettre automatiquement les brouillons à la date limite';
$string['ai_not_enabled'] = 'L\'évaluation IA n\'est pas activée pour cette activité';
$string['ai_grade_applied'] = 'Note IA appliquée avec succès';
$string['ai_grade_apply_failed'] = 'Échec de l\'application de la note IA';
$string['ai_no_feedback'] = 'Aucun commentaire généré par l\'IA';
$string['ai_parse_error'] = 'Erreur lors de l\'analyse de la réponse IA';
$string['ai_invalid_response'] = 'Réponse IA invalide';
$string['trigger_ai_evaluation'] = 'Lancer l\'évaluation IA';
$string['ai_evaluation_section'] = 'Évaluation automatique par IA';
$string['no_ai_evaluation'] = 'Aucune évaluation IA disponible';
$string['ai_review'] = 'Révision des évaluations IA';

// Phase 5: Gradebook per-step integration
$string['gradebook_settings'] = 'Paramètres du carnet de notes';
$string['grade_mode'] = 'Mode de notation';
$string['grade_mode_help'] = 'Choisissez comment les notes apparaissent dans le carnet de notes Moodle. En mode "Note unique", une moyenne est calculée. En mode "Notes par étape", chaque étape a sa propre entrée dans le carnet de notes.';
$string['grade_mode_combined'] = 'Note unique (moyenne des étapes)';
$string['grade_mode_per_step'] = 'Notes par étape (séparées)';

// Phase 6: UI Improvements - Progress indicators and notifications
$string['ai_progress_title'] = 'Évaluation IA en cours';
$string['ai_progress_initializing'] = 'Initialisation...';
$string['ai_progress_sending'] = 'Envoi de la demande d\'évaluation...';
$string['ai_progress_processing'] = 'Traitement en cours...';
$string['ai_progress_analyzing'] = 'Analyse de la soumission...';
$string['ai_progress_generating'] = 'Génération de l\'évaluation...';
$string['ai_progress_finalizing'] = 'Finalisation...';
$string['ai_progress_complete'] = 'Évaluation terminée !';
$string['ai_progress_timeout'] = 'Délai d\'attente dépassé. Veuillez réessayer.';
$string['ai_progress_elapsed'] = 'Temps écoulé';
$string['ai_status_pending'] = 'En attente';
$string['ai_status_processing'] = 'En cours';
$string['ai_status_completed'] = 'Terminé';
$string['ai_status_failed'] = 'Échoué';
$string['ai_status_applied'] = 'Appliqué';
$string['toast_success'] = 'Succès';
$string['toast_error'] = 'Erreur';
$string['toast_warning'] = 'Attention';
$string['toast_info'] = 'Information';
$string['toast_ai_title'] = 'Évaluation IA';
$string['toast_saved'] = 'Modifications sauvegardées';
$string['toast_submitted'] = 'Soumission envoyée';
$string['toast_grade_applied'] = 'Note appliquée avec succès';
$string['toast_evaluation_started'] = 'Évaluation IA lancée';
$string['toast_evaluation_complete'] = 'Évaluation terminée - Note: {$a}/20';
$string['toast_network_error'] = 'Erreur de connexion au serveur';
$string['toast_close'] = 'Fermer';

// Phase 6.3: Unlock submission and bulk re-evaluate
$string['submission_status_submitted'] = 'Travail soumis';
$string['submission_status_draft'] = 'Brouillon';
$string['submission_status_draft_desc'] = 'L\'élève n\'a pas encore soumis ce travail';
$string['unlock_submission'] = 'Annuler la soumission';
$string['unlock_submission_desc'] = 'Permet à l\'élève de modifier à nouveau son travail';
$string['confirm_unlock_submission'] = 'Êtes-vous sûr de vouloir annuler la soumission ? L\'élève pourra à nouveau modifier son travail.';
$string['bulk_reevaluate'] = 'Relancer toutes les évaluations IA';
$string['bulk_reevaluate_desc'] = 'Supprimer les évaluations existantes et relancer l\'IA pour toutes les soumissions de cette étape';
$string['confirm_bulk_reevaluate'] = 'Êtes-vous sûr de vouloir relancer l\'évaluation IA pour toutes les soumissions de cette étape ? Les évaluations existantes seront supprimées.';
$string['bulk_reevaluate_processing'] = 'Traitement en cours...';
$string['bulk_reevaluate_success'] = '{$a->deleted} évaluation(s) supprimée(s), {$a->queued} nouvelle(s) évaluation(s) lancée(s)';
$string['bulk_reevaluate_no_submissions'] = 'Aucune soumission validée pour cette étape';
$string['ai_evaluation_deleted'] = 'Évaluation IA supprimée';
$string['ai_evaluation_delete_failed'] = 'Échec de la suppression de l\'évaluation IA';
$string['ai_evaluations_deleted'] = '{$a} évaluation(s) IA supprimée(s)';
$string['step_not_enabled'] = 'Cette étape n\'est pas activée';
$string['nousers'] = 'Aucun utilisateur';

// Phase 6.4: Visibility options for student feedback
$string['visibility_options'] = 'Éléments à afficher aux élèves';
$string['visibility_options_desc'] = 'Sélectionnez les informations à partager avec les élèves. Par défaut, tous les éléments sont affichés.';
$string['show_feedback_to_student'] = 'Commentaire';
$string['show_criteria_to_student'] = 'Critères d\'évaluation';
$string['show_keywords_found_to_student'] = 'Éléments trouvés';
$string['show_keywords_missing_to_student'] = 'Éléments manquants';
$string['show_suggestions_to_student'] = 'Suggestions d\'amélioration';

// Chaînes manquantes
$string['confirm_revert'] = 'Êtes-vous sûr de vouloir revenir au brouillon ?';
$string['confirm_submission'] = 'Êtes-vous sûr de vouloir soumettre ce travail ?';
$string['export_pdf_coming_soon'] = 'Export PDF bientôt disponible';
$string['feedback'] = 'Commentaire';
$string['grade'] = 'Note';
$string['gradesaved'] = 'Note enregistrée';
$string['invalidstep'] = 'Étape invalide';
$string['revert_to_draft'] = 'Revenir au brouillon';
$string['submit'] = 'Soumettre';
$string['submitted_on'] = 'Soumis le';
$string['teacher_feedback'] = 'Commentaire de l\'enseignant';

// Privacy API - Chaînes de métadonnées complètes
$string['privacy:metadata:timecreated'] = 'Date et heure de création de l\'enregistrement';
$string['privacy:metadata:timemodified'] = 'Date et heure de dernière modification de l\'enregistrement';

// CDCF (Étape 4)
$string['privacy:metadata:gestionprojet_cdcf:userid'] = 'L\'identifiant de l\'utilisateur qui a soumis le cahier des charges';
$string['privacy:metadata:gestionprojet_cdcf:produit'] = 'Le produit défini par l\'élève';
$string['privacy:metadata:gestionprojet_cdcf:milieu'] = 'Le milieu d\'utilisation défini par l\'élève';
$string['privacy:metadata:gestionprojet_cdcf:fp'] = 'La fonction principale définie par l\'élève';
$string['privacy:metadata:gestionprojet_cdcf:interacteurs_data'] = 'Données JSON contenant les interacteurs, fonctions contraintes et critères';
$string['privacy:metadata:gestionprojet_cdcf:status'] = 'Le statut de la soumission (brouillon ou soumis)';
$string['privacy:metadata:gestionprojet_cdcf:timesubmitted'] = 'Date et heure de soumission du travail';

// Essai (Étape 5)
$string['privacy:metadata:gestionprojet_essai:userid'] = 'L\'identifiant de l\'utilisateur qui a soumis la fiche d\'essai';
$string['privacy:metadata:gestionprojet_essai:nom_essai'] = 'Le nom de l\'essai';
$string['privacy:metadata:gestionprojet_essai:date_essai'] = 'La date de l\'essai';
$string['privacy:metadata:gestionprojet_essai:groupe_eleves'] = 'Les élèves impliqués dans l\'essai';
$string['privacy:metadata:gestionprojet_essai:fonction_service'] = 'La fonction de service testée';
$string['privacy:metadata:gestionprojet_essai:etapes_protocole'] = 'Les étapes du protocole définies par l\'élève';
$string['privacy:metadata:gestionprojet_essai:resultats_obtenus'] = 'Les résultats obtenus pendant l\'essai';
$string['privacy:metadata:gestionprojet_essai:conclusion'] = 'La conclusion rédigée par l\'élève';
$string['privacy:metadata:gestionprojet_essai:status'] = 'Le statut de la soumission (brouillon ou soumis)';
$string['privacy:metadata:gestionprojet_essai:timesubmitted'] = 'Date et heure de soumission du travail';

// Rapport (Étape 6)
$string['privacy:metadata:gestionprojet_rapport:userid'] = 'L\'identifiant de l\'utilisateur qui a soumis le rapport de projet';
$string['privacy:metadata:gestionprojet_rapport:titre_projet'] = 'Le titre du projet';
$string['privacy:metadata:gestionprojet_rapport:auteurs'] = 'Les auteurs du rapport';
$string['privacy:metadata:gestionprojet_rapport:besoin_projet'] = 'Le besoin du projet décrit par l\'élève';
$string['privacy:metadata:gestionprojet_rapport:solutions'] = 'Les solutions choisies par l\'élève';
$string['privacy:metadata:gestionprojet_rapport:realisation'] = 'La description de la réalisation';
$string['privacy:metadata:gestionprojet_rapport:difficultes'] = 'Les difficultés rencontrées';
$string['privacy:metadata:gestionprojet_rapport:bilan'] = 'Le bilan du projet';
$string['privacy:metadata:gestionprojet_rapport:status'] = 'Le statut de la soumission (brouillon ou soumis)';
$string['privacy:metadata:gestionprojet_rapport:timesubmitted'] = 'Date et heure de soumission du travail';

// Besoin Élève (Étape 7)
$string['privacy:metadata:gestionprojet_besoin_eleve'] = 'Soumissions de l\'expression du besoin (Bête à Corne) par les élèves';
$string['privacy:metadata:gestionprojet_besoin_eleve:userid'] = 'L\'identifiant de l\'utilisateur qui a soumis l\'expression du besoin';
$string['privacy:metadata:gestionprojet_besoin_eleve:aqui'] = 'À qui le produit rend service (réponse de l\'élève)';
$string['privacy:metadata:gestionprojet_besoin_eleve:surquoi'] = 'Sur quoi le produit agit (réponse de l\'élève)';
$string['privacy:metadata:gestionprojet_besoin_eleve:dansquelbut'] = 'Le but du produit (réponse de l\'élève)';
$string['privacy:metadata:gestionprojet_besoin_eleve:status'] = 'Le statut de la soumission (brouillon ou soumis)';
$string['privacy:metadata:gestionprojet_besoin_eleve:timesubmitted'] = 'Date et heure de soumission du travail';

// Carnet (Étape 8)
$string['privacy:metadata:gestionprojet_carnet'] = 'Entrées du carnet de bord soumises par les élèves';
$string['privacy:metadata:gestionprojet_carnet:userid'] = 'L\'identifiant de l\'utilisateur qui a soumis le carnet de bord';
$string['privacy:metadata:gestionprojet_carnet:tasks_data'] = 'Données JSON contenant les entrées quotidiennes avec tâches, statut et observations';
$string['privacy:metadata:gestionprojet_carnet:status'] = 'Le statut de la soumission (brouillon ou soumis)';
$string['privacy:metadata:gestionprojet_carnet:timesubmitted'] = 'Date et heure de soumission du travail';

// Évaluations IA
$string['privacy:metadata:gestionprojet_ai_evaluations'] = 'Résultats des évaluations IA pour les soumissions des élèves';
$string['privacy:metadata:gestionprojet_ai_evaluations:userid'] = 'L\'identifiant de l\'utilisateur dont le travail a été évalué';
$string['privacy:metadata:gestionprojet_ai_evaluations:step'] = 'Le numéro de l\'étape évaluée';
$string['privacy:metadata:gestionprojet_ai_evaluations:provider'] = 'Le fournisseur IA utilisé pour l\'évaluation';
$string['privacy:metadata:gestionprojet_ai_evaluations:parsed_grade'] = 'La note suggérée par l\'IA';
$string['privacy:metadata:gestionprojet_ai_evaluations:parsed_feedback'] = 'Le commentaire généré par l\'IA';
$string['privacy:metadata:gestionprojet_ai_evaluations:status'] = 'Le statut de l\'évaluation (en attente, terminée, appliquée, etc.)';
$string['privacy:metadata:gestionprojet_ai_evaluations:applied_by'] = 'L\'identifiant de l\'enseignant qui a appliqué la note IA';
$string['privacy:metadata:gestionprojet_ai_evaluations:applied_at'] = 'Date et heure d\'application de la note';

// Historique
$string['privacy:metadata:gestionprojet_history:tablename'] = 'La table de base de données modifiée';
$string['privacy:metadata:gestionprojet_history:fieldname'] = 'Le champ qui a été modifié';
$string['privacy:metadata:gestionprojet_history:oldvalue'] = 'La valeur précédente avant modification';
$string['privacy:metadata:gestionprojet_history:newvalue'] = 'La nouvelle valeur après modification';

// Phase 7: Teacher Dashboard
$string['dashboard:title'] = 'Tableau de bord';
$string['dashboard:submissionprogress'] = 'État des soumissions';
$string['dashboard:submitted'] = 'soumis';
$string['dashboard:draft'] = 'Brouillon';
$string['dashboard:graded'] = 'Noté';
$string['dashboard:pending'] = 'En attente';
$string['dashboard:pendinggrade'] = 'En attente de note';
$string['dashboard:gradeaverage'] = 'Moyenne des notes';
$string['dashboard:aisummary'] = 'Synthèse IA';
$string['dashboard:refreshsummary'] = 'Actualiser';
$string['dashboard:difficulties'] = 'Difficultés identifiées';
$string['dashboard:strengths'] = 'Points forts';
$string['dashboard:recommendations'] = 'Recommandations pédagogiques';
$string['dashboard:analyzedfrom'] = 'Basé sur';
$string['dashboard:generatedat'] = 'Généré le';
$string['dashboard:tokenusage'] = 'Consommation de tokens';
$string['dashboard:evaluations'] = 'évaluations';
$string['dashboard:totaltokens'] = 'Tokens totaux';
$string['dashboard:prompttokens'] = 'Tokens (prompt)';
$string['dashboard:completiontokens'] = 'Tokens (réponse)';
$string['dashboard:avgpereval'] = 'Moyenne par évaluation';
$string['dashboard:notenoughevaluations'] = 'Pas assez d\'évaluations pour générer une synthèse';
$string['dashboard:evaluationsrequired'] = 'évaluations minimum requises';
$string['dashboard:nodata'] = 'Aucune donnée';
$string['dashboard:ai_disabled'] = 'L\'évaluation IA n\'est pas activée pour cette activité';
$string['dashboard:ai_error'] = 'Erreur lors de la génération de la synthèse IA';
$string['dashboard:summary_generated'] = 'Synthèse générée avec succès';
$string['dashboard:summary_cached'] = 'Synthèse disponible en cache';
$string['dashboard:summary_refreshed'] = 'Synthèse actualisée avec succès';
$string['error:invalidstep'] = 'Numéro d\'étape invalide';
