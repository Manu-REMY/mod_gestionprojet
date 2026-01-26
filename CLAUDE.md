# CLAUDE.md - Moodle Plugin Gestion de Projet

## Project Overview
Plugin de gestion de projet pédagogique en 8 phases. L'enseignant configure le cadre et fournit des modèles de correction. Les élèves complètent les phases activées. Un système IA évalue automatiquement les productions.

**Voir `ROADMAP.md` pour le plan d'action global.**

---

## Tech Stack
- **Core**: Moodle 5.0+ (PHP 8.1+)
- **DB**: Moodle DML ($DB global), XMLDB for schema
- **Frontend**: Mustache Templates, AMD Modules (RequireJS), Bootstrap 5, jQuery
- **Styles**: SCSS
- **AJAX**: Custom endpoints in `/ajax/` directory

---

## Coding Standards (Strict)

### PHP Globals
Use `$DB`, `$CFG`, `$OUTPUT`, `$PAGE`, `$USER`. Respect Moodle legacy patterns.

### Input Handling
```php
// ALWAYS use these - NEVER $_GET/$_POST
$id = required_param('id', PARAM_INT);
$step = optional_param('step', 1, PARAM_INT);
$data = optional_param('data', '', PARAM_RAW); // For JSON
```

### Security Checklist
- [ ] `require_login($course, false, $cm)` at page start
- [ ] `require_sesskey()` for form submissions
- [ ] `require_capability()` for permission checks
- [ ] `s()` or `p()` for output escaping
- [ ] `clean_param()` for data cleaning

### Strings
```php
// All text via lang files - NO hardcoded strings
get_string('stepname', 'gestionprojet');
get_string('error:notfound', 'gestionprojet', $param);
```

### Database
```php
// Use DML methods - NO raw SQL for CRUD
$DB->get_record('gestionprojet', ['id' => $id]);
$DB->insert_record('gestionprojet_cdcf', $data);
$DB->update_record('gestionprojet_essai', $record);
```

### Forms
Use `moodleform` class. Never write raw HTML forms.

---

## Project File Structure

```
mod/gestionprojet/
├── ajax/                    # AJAX endpoints
│   ├── autosave.php        # Auto-save handler
│   ├── submit.php          # Step submission
│   └── grade.php           # Grading operations
├── amd/                     # JavaScript AMD modules
│   ├── src/                # Source files
│   └── build/              # Minified (auto-generated)
├── classes/                 # PHP classes (autoloaded)
│   └── event/              # Moodle events
├── db/
│   ├── install.xml         # Database schema
│   ├── upgrade.php         # Migration scripts
│   ├── access.php          # Capabilities
│   └── services.php        # Web services (if needed)
├── lang/
│   ├── en/gestionprojet.php
│   └── fr/gestionprojet.php
├── pages/                   # Step pages
│   ├── home.php            # Navigation hub
│   ├── step1.php - step8.php
│   ├── step4_teacher.php - step8_teacher.php  # Teacher correction models
│   ├── correction_models.php  # Correction models hub
│   └── teacher_model_styles.php  # Shared styles for teacher pages
├── templates/               # Mustache templates
├── lib.php                  # Core functions
├── mod_form.php            # Activity settings form
├── view.php                # Main entry/router
├── grading.php             # Teacher grading interface
├── version.php             # Plugin metadata
├── ROADMAP.md              # Development plan
└── CLAUDE.md               # This file
```

---

## Database Tables (15 tables)

| Table | Purpose |
|-------|---------|
| `gestionprojet` | Main instance config |
| `gestionprojet_description` | Step 1 - Teacher |
| `gestionprojet_besoin` | Step 2 - Teacher |
| `gestionprojet_planning` | Step 3 - Teacher |
| `gestionprojet_cdcf` | Step 4 - Student |
| `gestionprojet_essai` | Step 5 - Student |
| `gestionprojet_rapport` | Step 6 - Student |
| `gestionprojet_besoin_eleve` | Step 7 - Student |
| `gestionprojet_carnet` | Step 8 - Student |
| `gestionprojet_cdcf_teacher` | Step 4 - Teacher correction model |
| `gestionprojet_essai_teacher` | Step 5 - Teacher correction model |
| `gestionprojet_rapport_teacher` | Step 6 - Teacher correction model |
| `gestionprojet_besoin_eleve_teacher` | Step 7 - Teacher correction model |
| `gestionprojet_carnet_teacher` | Step 8 - Teacher correction model |
| `gestionprojet_history` | Audit trail |

### Submission Pattern
```php
// Group mode: groupid set, userid = 0
// Individual mode: userid set, groupid = 0
$submission = gestionprojet_get_or_create_submission($gestionprojet, $step, $groupid, $userid);
```

---

## Key Functions (lib.php)

```php
// Instance management
gestionprojet_add_instance($data)
gestionprojet_update_instance($data)
gestionprojet_delete_instance($id)

// Submissions
gestionprojet_get_or_create_submission($gestionprojet, $step, $groupid, $userid)
gestionprojet_submit_step($gestionprojet, $step, $groupid, $userid)

// Grading
gestionprojet_grade_item_update($gestionprojet, $grades)
gestionprojet_update_grades($gestionprojet, $userid, $nullifnone)
gestionprojet_get_user_grades($gestionprojet, $userid)

// Navigation
gestionprojet_get_teacher_steps()  // Returns [1, 3, 2]
gestionprojet_get_student_steps()  // Returns [7, 4, 5, 8, 6]
```

---

## Capabilities (db/access.php)

| Capability | Role | Description |
|------------|------|-------------|
| `mod/gestionprojet:addinstance` | Manager | Create activity |
| `mod/gestionprojet:view` | All | View activity |
| `mod/gestionprojet:configureteacherpages` | Teacher | Edit steps 1-3 |
| `mod/gestionprojet:grade` | Teacher | Grade submissions |
| `mod/gestionprojet:submit` | Student | Submit steps 4-8 |
| `mod/gestionprojet:lock` | Teacher | Lock/unlock pages |
| `mod/gestionprojet:viewhistory` | Teacher | View audit trail |
| `mod/gestionprojet:exportall` | Teacher | Export all projects |
| `mod/gestionprojet:viewallsubmissions` | Teacher | View all submissions |

---

## AJAX Patterns

### Autosave Endpoint
```php
// ajax/autosave.php
require_sesskey();
$step = required_param('step', PARAM_INT);
$data = required_param('data', PARAM_RAW);
$data = json_decode($data, true);
// Validate fields per step, save, return JSON
```

### JavaScript AMD Module
```javascript
// amd/src/autosave.js
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    return {
        init: function(cmid, step, interval) {
            // Setup autosave timer
        }
    };
});
```

---

## Commands

```bash
# Purge caches (required after template/JS changes)
php admin/cli/purge_caches.php

# Run cron
php admin/cli/cron.php

# Code checker
phpcs --standard=moodle .

# Build AMD modules
grunt amd

# Upgrade database
php admin/cli/upgrade.php
```

---

## Development Guidelines

### Adding a New Field
1. Add column in `db/install.xml`
2. Create upgrade step in `db/upgrade.php`
3. Bump version in `version.php`
4. Add to form in relevant `pages/stepX.php`
5. Add to autosave whitelist in `ajax/autosave.php`
6. Add string in `lang/en/gestionprojet.php` and `lang/fr/gestionprojet.php`
7. Purge caches and upgrade

### Adding a New Step
1. Create table in `db/install.xml`
2. Add `enable_stepX` field to main table
3. Create `pages/stepX.php`
4. Add to navigation arrays in `lib.php`
5. Add to autosave handler
6. Add strings for all labels
7. Update `mod_form.php` checkbox

### Modifying JavaScript
1. Edit source in `amd/src/`
2. Run `grunt amd` to rebuild
3. Purge caches
4. Test in browser with `?debug=1`

---

## Current Status

**Version**: 1.4.0 (2026012600)

**Implemented**:
- All 8 phases with full UI
- Autosave system (10-120s intervals) - student + teacher pages
- Group/individual submission modes
- Manual grading interface (0-20 scale)
- Teacher correction models for Steps 4-8 (with AI instructions)
- Correction models hub page with completion indicators
- Audit trail
- AI configuration fields (provider, API key, enabled)
- All steps enabled by default (step7, step8 included)
- Step 3 timeline with school holidays API (zones A/B/C)
- Submission dates configuration in teacher models
- Dates display for students (with overdue/due soon indicators)

**In Progress** (see ROADMAP.md):
- Phase 4: AI evaluation engine
- Phase 5: Moodle gradebook integration per step
- Phase 6: Quality & documentation

---

## Common Patterns

### Permission Check
```php
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:submit', $context);
```

### JSON Data Handling
```php
// Storing complex data
$record->interacteurs_data = json_encode($interacteurs);
$DB->update_record('gestionprojet_cdcf', $record);

// Retrieving
$data = json_decode($record->interacteurs_data, true);
```

### Group/User Submission Logic
```php
$groupmode = groups_get_activity_groupmode($cm);
$groupid = groups_get_activity_group($cm, true);
$isGroupSubmission = ($gestionprojet->group_submission && $groupid != 0);

if ($isGroupSubmission) {
    $submission = $DB->get_record($table, ['gestionprojetid' => $id, 'groupid' => $groupid, 'userid' => 0]);
} else {
    $submission = $DB->get_record($table, ['gestionprojetid' => $id, 'userid' => $USER->id, 'groupid' => 0]);
}
```
