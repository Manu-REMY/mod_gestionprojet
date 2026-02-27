# Changelog

All notable changes to the mod_gestionprojet plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
