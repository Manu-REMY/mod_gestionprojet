# Changelog

All notable changes to the mod_gestionprojet plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.8.0] — 2026-05-06

### Ajouts

- **Mode « consigne d'essai fournie par l'enseignant » pour l'étape 5** (`step5_provided`) — pattern identique à `step4_provided` (CDCF) et `step9_provided` (FAST) :
  - Nouveau flag `step5_provided` (INT) sur la table `gestionprojet`, contrôlé par les cellules Gantt sur la page d'accueil enseignant (case à cocher AJAX, sans rechargement).
  - Nouvelle table `gestionprojet_essai_provided` (12 champs métier alignés sur `gestionprojet_essai` : `nom_essai`, `date_essai`, `groupe_eleves`, `objectif`, `fonction_service`, `niveaux_reussite`, `etapes_protocole`, `materiel_outils`, `precautions`, `resultats_obtenus`, `observations_remarques`, `conclusion`).
  - Nouvelle page enseignant `pages/step5_provided.php` (consigne fiche d'essai) avec autosave + bouton « Enregistrer ».
  - Nouveau module AMD `mod_gestionprojet/essai_provided` (autosave + save button) — wrapper minimal autour de `mod_gestionprojet/autosave`.
  - Quand `step5_provided` est activé : les élèves voient la consigne en lecture seule et leur fiche d'essai (`gestionprojet_essai`) est pré-remplie avec le contenu de la consigne au premier accès (seeding dans `gestionprojet_get_or_create_submission`).

### Modifications

- `view.php` accepte désormais `mode=provided` pour l'étape 5 (routage vers `step5_provided.php`).
- `ajax/autosave.php` whitelist : 12 champs supportés pour le mode `provided` step 5.
- `ajax/toggle_step.php` accepte le toggle du flag `step5_provided` depuis la page d'accueil.
- `home.php` : nouvelles cellules Gantt pour la consigne d'essai (ligne « Documents enseignant », colonne « Fiche d'essai »).
- Backup/restore Moodle 2 : intégration de `gestionprojet_essai_provided` dans `backup_gestionprojet_stepslib.php` et `restore_gestionprojet_stepslib.php`.
- `gestionprojet_delete_instance()` purge désormais 22 tables (ajout de `gestionprojet_essai_provided`).

### Corrections

- `pages/step5.php` (élève) tolère désormais une valeur `precautions` au format texte libre (séparée par retours-ligne) lorsqu'elle est issue du seeding depuis la consigne — fallback automatique vers le format JSON historique sinon.

### Notes techniques

- Migration BDD idempotente (étape `2026050700`) : ajout du champ `step5_provided` sur la table `gestionprojet` et création de la table `gestionprojet_essai_provided`. Re-run safe via les guards `field_exists` / `table_exists`.
- 5 nouvelles chaînes de langue (FR + EN) — total `710` clés en parité.
- Conformité Moodle (CLAUDE.md §1-11) : header GPL deux paragraphes complet sur tous les nouveaux fichiers, aucun JS / CSS inline, aucune superglobale, aucun debug code.

## [2.7.3] — 2026-05-06

### Modifications

- **Refonte du dashboard enseignant** : les diagrammes (CDCF / FAST / bête à cornes) sont désormais affichés en haut de l'éditeur (cohérence avec le layout de l'étape 7).
- Le SVG du diagramme CDCF est borné à ~500 px de haut (`max-width: 100 %`) pour ne plus écraser les diagrammes besoin/FAST sur les écrans larges.
- Les dashboards d'étape côté enseignant deviennent des cartes Bootstrap pliables (fermées par défaut). `chart.resize()` est branché sur `shown.bs.collapse` pour que la distribution des notes se rende correctement à la première ouverture.

## [2.7.1] — 2026-05-05

Release de conformité à la checklist de contribution Moodle (sans changement fonctionnel).

### Conformité

- **Fin du JavaScript inline** : les onze pages PHP qui contenaient encore un `<script>` inline (`pages/step1.php`, `step2.php`, `step3.php`, `step5.php`, `step6.php`, `step7.php`, `step8.php`, `step5_teacher.php`, `step6_teacher.php`, `step7_teacher.php`, `step8_teacher.php`) sont désormais câblées via `$PAGE->requires->js_call_amd()` sur les modules AMD existants ou de nouveaux modules glue.
- Nouveaux modules AMD :
  - `mod_gestionprojet/teacher_step_init` — wrapper qui combine `teacher_model` + `generate_ai_instructions` pour les pages de modèles de correction (étapes 5 à 7).
  - `mod_gestionprojet/step8_teacher_init` — variante spécifique pour la page modèle de l'étape 8 (logbook avec tasks_data).
- Modules `step2`, `step6` et `step7` étendus pour accepter les chaînes localisées via `config.strings` (suppression des dernières chaînes françaises codées en dur).
- **Header GPL complet sur tous les fichiers PHP** — ajout du second paragraphe « distributed in the hope that it will be useful » sur les 18 fichiers où il manquait.
- **`gestionprojet_delete_instance()` purge les 21 tables** (au lieu de 14) : ajout des cinq tables `*_teacher` (cdcf, essai, rapport, besoin_eleve, carnet) ainsi que des deux tables `gestionprojet_ai_evaluations` et `gestionprojet_ai_summaries`.
- Boutons côté élève : remplacement des `onclick="..."` par des `id` câblés dans les modules AMD (`#exportPdfBtn`, `#addLogEntryButton`, `#exportPdfButton`, `#addEntryBtn`).

### Notes techniques

- Aucune modification de schéma de base de données — pas de nouvelle étape `db/upgrade.php`.
- Les modules AMD sont compilés avec `terser` et committés dans `amd/build/`.
- Tous les checks rapides de la checklist Moodle passent : pas de superglobales, pas de debug code, pas de CSS/JS inline, parité FR/EN sur 705 chaînes.

## [2.7.0] — 2026-05-05

### Ajouts

- **CDCF aligné sur la norme NF EN 16271** — refonte complète de l'étape 4 autour du vocabulaire normatif :
  - Nouvelle structure de données `{interactors, fonctionsService (FS), contraintes}` à l'intérieur de `interacteurs_data` (la colonne `fp` et les colonnes `produit`/`milieu` sont supprimées).
  - Chaque FS porte son nom, sa description, ses critères et son niveau de flexibilité (F0–F3).
  - Nouveau diagramme « pieuvre » (module AMD `cdcf_diagram`) qui dessine le produit au centre, les interacteurs autour et les courbes des FS.
  - Nouvel éditeur AMD `cdcf` : ajout/modification d'interacteurs, FS, critères et contraintes avec rendu live.
- **Module `cdcf_helper`** (`classes/cdcf_helper.php`) qui centralise `normalize`, `decode` et `migrate_legacy` pour garantir la cohérence des trois tables CDCF (élève, enseignant, fourni).
- **Migration BDD idempotente** (`db/upgrade.php`, étape `2026050601`) qui convertit les anciennes données « FC nichées dans interactors + FP séparé » vers la nouvelle structure pour les trois tables `gestionprojet_cdcf*`, puis supprime les colonnes obsolètes (`produit`, `milieu`, `fp`).
- **Bloc « Description » sur la page consigne FAST élève** (étape 9) — cohérent avec CDCF et besoin.

### Modifications

- Pages élève des étapes 4 à 8 unifiées sur le chrome `gp-student` (suppression de la nav legacy et du bandeau coloré, mise en pleine largeur).
- Prompt IA de l'étape 4 reconstruit pour la nouvelle structure (sections FS / flexibilité / contraintes), avec garde sur la section FS et résolution `linkedFsId` par index dans la liste des FS.
- Whitelist autosave de l'étape 4 réduite au seul champ `interacteurs_data`.
- Page `step4_provided` (consigne) et page `step4_teacher` (modèle de correction) réécrites avec l'éditeur normalisé (lecture seule pour l'élève sur la consigne).
- Vocabulaire FP/FC retiré de l'UI et des chaînes de langue (suppression des chaînes obsolètes `fp/fc/produit/milieu/unite`).
- PHPDoc reformulés en anglais (Moodle CS §4) sur les fichiers CDCF.

### Corrections

- `step4` : suppression du bloc « provided » dupliqué et amorçage du formulaire éditable quand le CDCF est effectivement vide.
- `step9` : reliage des callbacks legacy + bouton « Enregistrer » déplacé dans la section bas de page.
- Recordset `cdcf_helper` enveloppé dans un `try/finally` (fermeture garantie) avec log des migrations vides (`mtrace`).
- Préfixe « + » dupliqué retiré des chaînes `addX` de l'étape 4.

### Notes techniques

- Conformité Moodle (CLAUDE.md §1-11) : tous les nouveaux fichiers PHP ont l'en-tête GPL deux paragraphes complet ; aucun JS ou CSS inline ; aucune superglobale ; aucun debug code.
- Tests PHPUnit couvrant `cdcf_helper::migrate_legacy` et `cdcf_helper::normalize` (`tests/cdcf_helper_test.php`).

## [2.6.4] — 2026-05-05

### Modifications

- Modale de soumission élève + bandeau de progression IA branchés sur les pages d'étapes 4 à 8 (Phase 2 du chantier C1).
- Webservice `submit_step` étendu : déclenche l'évaluation IA et inclut désormais l'étape 9 (FAST).
- Compatibilité Moodle 5+ : utilisation de `MESSAGE_DEFAULT_ENABLED` dans le provider de messages.

### Documentation

- Scénarios de tests manuels v2.6.3 ajoutés à `TESTING.md` (sans credentials).

## [2.6.3] — 2026-05-05

### Ajouts

- **Bouton « Soumettre pour évaluation » côté élève** avec déclenchement automatique de l'évaluation IA :
  - Modale Moodle de confirmation avec checkbox d'engagement (template `submit_modal.mustache`).
  - Module AMD `student_ai_progress` qui sonde l'état de l'évaluation et affiche un bandeau de progression.
  - Endpoint `ajax/get_evaluation_status.php` autorisant l'élève à interroger sa propre évaluation.
- **Notifications enseignants en cas d'échec d'évaluation IA** :
  - Nouveau message provider `ai_evaluation_failed` (lang FR + EN).
  - Helper `notify_teachers_of_failure()` dans le moteur d'évaluation IA.

### Modifications

- Réécriture de `submission.js` autour de la modale Moodle et du gate « checkbox cochée ».
- Étape 9 (FAST) ajoutée à la table de soumission.
- Styles CSS du bandeau de progression IA.

### Corrections

- Suffixe `_student` ajouté aux clés des chaînes du bandeau IA pour éviter les conflits côté enseignant.

## [2.6.2] — 2026-05-05

### Corrections

- Étape 9 (FAST) : bouton d'enregistrement manuel restauré, autosave passé à 10 s, retour visuel via toast.

## [2.6.1] — 2026-05-05

### Corrections

- Mode « contenu fourni » accessible en lecture seule pour les élèves sur CDCF et FAST.
- Navigation par onglets cohérente entre les pages d'étapes côté élève.

## [2.6.0] — 2026-05-05

### Ajouts

- **Vue Gantt côté élève** sur la page d'accueil — l'élève visualise l'avancement du projet sous forme de tableau de cellules par étape.
  - Nouveau template Mustache `home_gantt_student.mustache`.
  - Helper pur `gestionprojet_build_student_gantt_cells()` (lib.php) — extrait des définitions de colonnes via `gestionprojet_get_gantt_columns()`.
  - Variantes CSS pour les cellules désactivées (sans padding de checkbox), rendu via `<span>`.
  - Chaînes de langue dédiées (FR + EN).

### Documentation

- Spécification de design + plan d'implémentation pour la vue Gantt élève.

## [2.5.0] — 2026-05-04

### Ajouts

- **Boutons de génération du prompt IA** dans les modèles de correction enseignant (étapes 4-9) :
  - « Modèle par défaut » : insère le texte d'instructions par défaut de l'étape dans le textarea, en un clic.
  - « Générer depuis le modèle » : appelle l'IA configurée pour produire des instructions de correction adaptées au modèle de correction actuellement rempli (champs métier de l'étape).
- Méthode `ai_prompt_builder::build_meta_prompt()` qui assemble le méta-prompt envoyé à l'IA (rôle « expert pédagogique » + critères de l'étape + modèle rempli).
- Endpoint AJAX `ajax/generate_ai_instructions.php` (sécurisé : `require_login` + `require_sesskey` + capacité `mod/gestionprojet:configureteacherpages`).
- Module AMD `mod_gestionprojet/generate_ai_instructions` qui câble les deux boutons (gestion d'état désactivé, confirmation avant écrasement, spinner pendant l'appel).
- Chaînes de langue par défaut `ai_instructions_default_step{5,6,8,9}` (FR + EN) — alignées sur la structure existante des step4/step7 (Rôle / Contexte / Critères / Tonalité).
- 11 chaînes UI supplémentaires (libellés boutons, tooltips, messages d'erreur, message de succès) en FR + EN.
- Tests PHPUnit pour `build_meta_prompt()` (tests/ai_meta_prompt_test.php) couvrant les 6 étapes et le cas du modèle vide.

### Modifications

- Refactorisation : extraction d'une méthode privée `ai_prompt_builder::build_criteria_text()` (élimine la duplication entre `build_system_prompt` et `build_meta_prompt`).
- `STEP_FIELDS[6]` inclut désormais le champ `besoins` (utilisé par le modèle de correction enseignant pour l'étape 6 — `gestionprojet_rapport_teacher.besoins`). Sans cet ajout, le contenu saisi par l'enseignant dans ce champ était silencieusement filtré par le whitelist du méta-prompt.

### Notes techniques

- Aucune modification de schéma de base de données.
- Conformité Moodle (CLAUDE.md §1-11) : tous les nouveaux fichiers PHP ont l'en-tête GPL deux paragraphes complet ; aucun JS ou CSS inline ; aucune superglobale ; aucun debug code.
- L'endpoint retourne HTTP 400 sur les chemins d'erreur (cohérent avec les autres endpoints `ajax/`) et un message d'erreur générique localisé (pas de fuite du message brut du provider IA).

## [2.2.0] — 2026-05-03

### Ajouts

- **Mode "CDCF fourni par l'enseignant"** pour l'étape 4 — un nouveau flag `step4_provided` permet à l'enseignant de fournir un Cahier des Charges Fonctionnel clé-en-main. Quatre combinaisons sont possibles via deux cases à cocher indépendantes : désactivé, élève seul, fourni intégralement (lecture seule pour l'élève), et hybride (référence partielle prof + production élève).
- **Refonte de la home enseignant en tableau Gantt** (3 lignes × 7 colonnes) — Documents enseignant / Modèles de correction / Activités élèves. La colonne "Expression du Besoin" fusionne les anciens steps 2 et 7 en une seule colonne. La colonne "Cahier des Charges" expose les modes via les cases à cocher.
- **Configuration des étapes actives directement depuis la home** via cases à cocher AJAX (mise à jour live, sans rechargement). La section "Étapes actives" est retirée du formulaire d'activité.
- **Barre de navigation directe à 8 phases** sur les pages enseignant (étapes 1, 2, 3) et les modèles de correction (étapes 4-8 teacher), avec bouton "Accueil" en début de barre. Le composant `step_tabs.mustache` est réutilisé par `grading.php` (refactor sans régression).

### Modifications

- Ajout de la colonne `step4_provided` (INT NOT NULL DEFAULT 0) à la table `gestionprojet`.
- Le champ `enable_step4` redevient un simple booléen (le mode "fourni" est porté par `step4_provided`, indépendant).
- La page `step4.php` (vue élève) gère désormais l'affichage combiné référence prof + formulaire élève selon les flags.
- La page `step4_teacher.php` affiche un encart contextuel selon les modes.
- La page `correction_models.php` est dépréciée — son URL redirige silencieusement vers la home.

### Suppressions internes

- `pages/correction_models.php`
- `templates/correction_models.mustache`
- Méthode `render_correction_models()` du renderer
- Section "Étapes actives" du formulaire d'activité

### Architecture

- Nouveau partial Mustache `step_tabs.mustache` (composant réutilisable).
- Nouveau partial Mustache `home_gantt.mustache` (Gantt enseignant).
- Nouveau endpoint AJAX `ajax/toggle_step.php` (sécurisé : require_login, require_sesskey, require_capability).
- Nouveau module AMD `mod_gestionprojet/gantt` (gestion des cases à cocher live).
- Nouveau helper PHP `gestionprojet_build_step_tabs()` dans `lib.php`.

## [2.1.0] - 2026-02-25

### Added
- Teacher dashboard with submission progress overview and AI evaluation summary
- Lucide SVG icon system replacing emoji icons (classes/output/icon.php)
- AI usage report with request/response logging accessible from activity page
- Auto-submit at deadline with automatic AI summary generation
- Sequential navigation and save redirect for teacher correction models
- 14 AMD modules for client-side functionality

### Changed
- Migrated all AJAX to 9 External API classes (classes/external/) declared in db/services.php
- Replaced legacy jQuery $.ajax() with core/ajax AMD module calls
- Created 4 Mustache templates (home, dashboard_teacher, grading_navigation, correction_models)
- Implemented Output API renderer (classes/output/renderer.php)

### Fixed
- Bootstrap 5 compatibility for expand/collapse buttons
- Teacher access to AI report from activity page

## [2.0.0] - 2026-02-24

### Added
- 9 External Web Services declared in db/services.php (autosave, submit_step, evaluate, get_evaluation_status, apply_ai_grade, save_grade, generate_ai_summary, bulk_reevaluate, test_api_connection)
- 4 Mustache templates in templates/ directory
- Output API renderer and icon helper classes
- Public bug tracker on Forge Apps Education

### Changed
- Complete Moodle plugin checklist compliance audit (CONTRIB-10279)
- All user-facing strings externalized via get_string() (775 EN + 1023 FR strings)
- All code comments, variable names and function names converted to English
- All CSS extracted from PHP files to styles.css (1,278 lines, namespaced with .path-mod-gestionprojet)
- All inline JavaScript moved to AMD modules
- GPL v3 headers verified on all PHP files
- Removed all debug code and PHP superglobals

### Fixed
- All broken plugin documentation links
- All missing language string definitions
- Cron task date handling (userdate replaced with date)

## [1.8.0] - 2026-01-29

### Added
- Privacy API implementation for GDPR compliance
- Complete backup/restore support
- LICENSE.md and CHANGELOG.md files

### Changed
- Improved documentation for Moodle plugin repository submission

## [1.7.3] - 2026-01-28

### Added
- Unlock submission feature for teachers
- Bulk AI re-evaluation for all submissions
- Delete AI evaluation functionality

### Fixed
- Minor UI improvements

## [1.7.1] - 2026-01-27

### Added
- AI progress indicators with animated feedback
- Toast notifications system
- Responsive design improvements
- Loading states for AI operations

## [1.7.0] - 2026-01-26

### Added
- Per-step gradebook integration
- Grade mode selection (combined vs per-step)
- Automatic grade sync with Moodle gradebook

## [1.6.0] - 2026-01-25

### Added
- AI evaluation engine with multiple providers
- OpenAI, Anthropic, Mistral, and Albert (Etalab) support
- Built-in Albert API key for French government AI
- Automatic submission evaluation
- AI feedback visibility options

## [1.5.0] - 2026-01-24

### Added
- Teacher correction models for steps 4-8
- AI instructions field for evaluation guidance
- Submission dates configuration
- Due date indicators for students

## [1.4.0] - 2026-01-23

### Added
- Step 3 timeline with French school holidays API
- Vacation zones A/B/C support
- Interactive Gantt-style timeline

## [1.3.0] - 2026-01-22

### Added
- Step 7 (Student needs expression - Horn Diagram)
- Step 8 (Project logbook)
- All 8 steps enabled by default

## [1.2.0] - 2026-01-20

### Added
- Autosave system (10-120s configurable intervals)
- Group and individual submission modes
- Manual grading interface (0-20 scale)

## [1.1.0] - 2026-01-18

### Added
- Complete UI for all 8 phases
- Teacher configuration pages (steps 1-3)
- Student submission pages (steps 4-8)

## [1.0.0] - 2026-01-15

### Added
- Initial release
- Core plugin structure
- Database schema with 16 tables
- Basic navigation and permissions
