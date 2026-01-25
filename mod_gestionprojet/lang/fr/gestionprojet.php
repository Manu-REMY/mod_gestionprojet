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
