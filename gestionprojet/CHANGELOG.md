# Changelog

All notable changes to the mod_gestionprojet plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
