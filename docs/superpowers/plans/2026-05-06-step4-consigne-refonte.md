# Step 4 Consigne Refonte — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre à l'enseignant d'ajouter un texte d'intro pédagogique en lecture seule pour les élèves (lu en temps réel depuis `cdcf_provided`) et fournir un bouton « Réinitialiser le formulaire » côté élève qui re-seed depuis la dernière version de la consigne enseignant.

**Architecture:** Ajout d'une colonne `intro_text` (TEXT, HTML) sur `gestionprojet_cdcf_provided`. Côté enseignant, un éditeur Atto via `editors_get_preferred_editor`. Côté élève, un encadré `alert-info` lu en temps réel + un bouton qui appelle un nouvel endpoint AJAX `reset_to_provided.php`. La logique du reset est extraite dans `\mod_gestionprojet\reset_helper` pour être testable. Le builder de prompt IA reçoit le `intro_text` en plain text.

**Tech Stack:** Moodle 5.0+, PHP 8.1+, XMLDB, Mustache, AMD/RequireJS, jQuery, Bootstrap 5, Atto.

**Spec:** `docs/superpowers/specs/2026-05-06-step4-consigne-refonte-design.md`

---

## Task 1: Création de la branche de travail

**Files:** aucun (opération git uniquement)

- [ ] **Step 1: Créer la branche depuis main**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git checkout -b feat/step4-consigne-refonte
git status
```

Expected: branche `feat/step4-consigne-refonte` créée, working tree propre (le commit du spec est déjà sur main et est inclus dans la branche).

---

## Task 2: Schéma DB — colonne `intro_text` sur `gestionprojet_cdcf_provided`

**Files:**
- Modify: `gestionprojet/db/install.xml:379-391` (table `gestionprojet_cdcf_provided`)
- Modify: `gestionprojet/db/upgrade.php` (ajouter étape pour `intro_text`)
- Modify: `gestionprojet/version.php` (bump à `2026050800`)

- [ ] **Step 1: Ajouter le champ dans `install.xml`**

Dans `gestionprojet/db/install.xml`, remplacer le bloc `<TABLE NAME="gestionprojet_cdcf_provided">` (lignes 378-391) par :

```xml
    <!-- Table: CDCF teacher-provided consigne (read-only reference for students) - Step 4 -->
    <TABLE NAME="gestionprojet_cdcf_provided" COMMENT="Teacher-provided CDCF consigne (no ai_instructions, no dates)">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="gestionprojetid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="interacteurs_data" TYPE="text" NOTNULL="false" SEQUENCE="false" COMMENT="JSON array of interactors"/>
        <FIELD NAME="intro_text" TYPE="text" NOTNULL="false" SEQUENCE="false" COMMENT="Pedagogical intro text shown read-only to students at the top of step 4 (HTML, Atto editor)"/>
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="gestionprojetid" TYPE="foreign-unique" FIELDS="gestionprojetid" REFTABLE="gestionprojet" REFFIELDS="id"/>
      </KEYS>
    </TABLE>
```

- [ ] **Step 2: Ajouter l'étape upgrade dans `db/upgrade.php`**

Dans `gestionprojet/db/upgrade.php`, **juste avant** la ligne `return true;` finale (avant la dernière `}` de la fonction), insérer :

```php
    if ($oldversion < 2026050800) {
        $dbman = $DB->get_manager();

        // Add intro_text column to gestionprojet_cdcf_provided.
        $table = new xmldb_table('gestionprojet_cdcf_provided');
        $field = new xmldb_field(
            'intro_text',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null,
            'interacteurs_data'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026050800, 'gestionprojet');
    }
```

- [ ] **Step 3: Bumper `version.php`**

Dans `gestionprojet/version.php`, modifier :

```php
$plugin->version = 2026050800;  // YYYYMMDDXX format
$plugin->release = '2.9.0';
```

(C'est-à-dire passer `version` de `2026050700` à `2026050800` et `release` de `'2.8.0'` à `'2.9.0'`.)

- [ ] **Step 4: Vérifier la cohérence syntaxique**

```bash
php -l gestionprojet/db/upgrade.php
php -l gestionprojet/version.php
```

Expected: `No syntax errors detected` pour les deux fichiers.

- [ ] **Step 5: Commit**

```bash
git add gestionprojet/db/install.xml gestionprojet/db/upgrade.php gestionprojet/version.php
git commit -m "$(cat <<'EOF'
feat(db): add intro_text to gestionprojet_cdcf_provided

Bumps version to 2026050800 / release 2.9.0.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Strings i18n (FR + EN)

**Files:**
- Modify: `gestionprojet/lang/en/gestionprojet.php`
- Modify: `gestionprojet/lang/fr/gestionprojet.php`

- [ ] **Step 1: Ajouter les strings FR**

À la fin de `gestionprojet/lang/fr/gestionprojet.php`, **avant le `?>` final** (s'il existe ; sinon en fin de fichier), ajouter :

```php
// Step 4 — Refonte consigne (v2.9.0)
$string['intro_text_label'] = 'Texte de présentation aux élèves';
$string['intro_text_help'] = 'Affiché en lecture seule en haut de l\'activité élève. Utilisez-le pour expliquer ce qui est attendu, le contexte, les consignes méthodologiques.';
$string['intro_section_title'] = 'Consignes de l\'enseignant';
$string['reset_button_label'] = 'Réinitialiser le formulaire';
$string['reset_modal_title'] = 'Réinitialiser le formulaire ?';
$string['reset_modal_body'] = 'Toutes vos modifications actuelles seront perdues et remplacées par la dernière version de la consigne fournie par l\'enseignant. Cette action est irréversible.';
$string['reset_modal_confirm'] = 'Réinitialiser';
$string['reset_modal_cancel'] = 'Annuler';
$string['reset_disabled_tooltip'] = 'Le formulaire est verrouillé après soumission. Demandez à l\'enseignant de le déverrouiller pour réinitialiser.';
$string['reset_success'] = 'Formulaire réinitialisé à la dernière version de la consigne.';
$string['reset_error_locked'] = 'Impossible de réinitialiser un formulaire soumis.';
$string['reset_error_no_provided'] = 'Aucune consigne fournie par l\'enseignant pour cette étape.';
```

- [ ] **Step 2: Ajouter les strings EN**

À la fin de `gestionprojet/lang/en/gestionprojet.php`, ajouter :

```php
// Step 4 — Consigne refactor (v2.9.0)
$string['intro_text_label'] = 'Introduction text for students';
$string['intro_text_help'] = 'Displayed read-only at the top of the student activity. Use it to explain expectations, context, methodological guidelines.';
$string['intro_section_title'] = 'Teacher\'s instructions';
$string['reset_button_label'] = 'Reset form';
$string['reset_modal_title'] = 'Reset form?';
$string['reset_modal_body'] = 'All your current changes will be lost and replaced by the latest version of the teacher\'s instructions. This action cannot be undone.';
$string['reset_modal_confirm'] = 'Reset';
$string['reset_modal_cancel'] = 'Cancel';
$string['reset_disabled_tooltip'] = 'The form is locked after submission. Ask your teacher to unlock it to reset.';
$string['reset_success'] = 'Form reset to the latest teacher instructions.';
$string['reset_error_locked'] = 'Cannot reset a submitted form.';
$string['reset_error_no_provided'] = 'No teacher-provided instructions exist for this step.';
```

- [ ] **Step 3: Vérifier la syntaxe PHP**

```bash
php -l gestionprojet/lang/fr/gestionprojet.php
php -l gestionprojet/lang/en/gestionprojet.php
```

Expected: `No syntax errors detected` pour les deux.

- [ ] **Step 4: Commit**

```bash
git add gestionprojet/lang/fr/gestionprojet.php gestionprojet/lang/en/gestionprojet.php
git commit -m "$(cat <<'EOF'
feat(lang): add strings for step4 consigne refonte (intro_text + reset button)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Côté enseignant — éditeur Atto pour `intro_text`

**Files:**
- Modify: `gestionprojet/pages/step4_provided.php` (insérer le bloc Atto + JSON dans `cdcf_bootstrap`)
- Modify: `gestionprojet/ajax/autosave.php:74` (whitelist `intro_text`)

- [ ] **Step 1: Whitelist `intro_text` dans l'autosave**

Dans `gestionprojet/ajax/autosave.php`, ligne 74 actuellement :

```php
            4 => ['table' => 'gestionprojet_cdcf_provided', 'fields' => ['interacteurs_data']],
```

Remplacer par :

```php
            4 => ['table' => 'gestionprojet_cdcf_provided', 'fields' => ['interacteurs_data', 'intro_text']],
```

- [ ] **Step 2: Ajouter le bloc éditeur Atto dans `step4_provided.php`**

Dans `gestionprojet/pages/step4_provided.php`, après la ligne 84 (`<input type="hidden" name="interacteurs_data" id="cdcfDataField" value="..."/>`) et **avant** le bloc `<div class="model-form-section">` (ligne 86), insérer :

```php
        <!-- Texte d'intro affiché aux élèves (lecture seule). -->
        <div class="model-form-section gp-intro-section">
            <h3><?php echo icon::render('message-square', 'sm', 'blue'); ?> <?php echo get_string('intro_text_label', 'gestionprojet'); ?></h3>
            <p class="text-muted small"><?php echo get_string('intro_text_help', 'gestionprojet'); ?></p>
            <textarea name="intro_text" id="intro_text" rows="8" class="form-control gp-intro-textarea"><?php echo s($model->intro_text ?? ''); ?></textarea>
        </div>
        <?php
        // Activate the Moodle preferred rich-text editor (Atto/TinyMCE) on the textarea.
        $editor = editors_get_preferred_editor(FORMAT_HTML);
        $editor->set_text($model->intro_text ?? '');
        $editor->use_editor('intro_text', [
            'context' => $context,
            'autosave' => false,
        ]);
        ?>

```

- [ ] **Step 3: Passer `intro_text` au bootstrap JS**

Dans le même fichier `step4_provided.php`, dans le tableau passé à `$PAGE->requires->js_call_amd('mod_gestionprojet/cdcf_bootstrap', 'init', [[...]]);` (lignes 160-173), ajouter une nouvelle clé `introTextSelector`. Modifier ainsi :

```php
$PAGE->requires->js_call_amd('mod_gestionprojet/cdcf_bootstrap', 'init', [[
    'cmid'              => $cm->id,
    'step'              => 4,
    'groupid'           => 0,
    'mode'              => 'provided',
    'autosaveMs'        => (int)($gestionprojet->autosave_interval ?? 30) * 1000,
    'isLocked'          => $readonly,
    'canSubmit'         => false,
    'canRevert'         => false,
    'projetNom'         => $projetnom,
    'initial'           => $cdcfdata,
    'lang'              => $langstrings,
    'introTextSelector' => '#intro_text',
    'redirectAfterSave' => $readonly ? null : (new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]))->out(false),
]]);
```

- [ ] **Step 4: Brancher l'autosave sur le textarea Atto dans `cdcf_bootstrap.js`**

Dans `gestionprojet/amd/src/cdcf_bootstrap.js`, repérer la fonction qui collecte les données pour l'autosave (recherche `interacteurs_data` ou `JSON.stringify`).

**Sub-step 4a:** Lire le fichier `cdcf_bootstrap.js` pour identifier la zone d'autosave.

```bash
grep -n "interacteurs_data\|autosave\|payload" gestionprojet/amd/src/cdcf_bootstrap.js
```

**Sub-step 4b:** Dans la fonction qui prépare le payload (probablement `collectData()` ou inline dans `setInterval`), ajouter avant l'envoi (à adapter au nom de variable du payload réel) :

```javascript
    // Include intro_text from Atto editor if the selector was provided (mode=provided only).
    if (config.introTextSelector) {
        var introEl = document.querySelector(config.introTextSelector);
        if (introEl) {
            payload.intro_text = introEl.value;
        }
    }
```

**Note:** Atto sync son contenu vers le `<textarea>` source à chaque blur/submit. Si l'autosave se déclenche pendant l'édition active, il peut envoyer une version légèrement obsolète, mais le suivant rattrape. Acceptable pour la sauvegarde périodique. Vérifier comportement en validation manuelle (Task 14, étape 2).

- [ ] **Step 5: Validation syntaxe**

```bash
php -l gestionprojet/pages/step4_provided.php
php -l gestionprojet/ajax/autosave.php
ls -la gestionprojet/amd/src/cdcf_bootstrap.js
```

Expected: pas d'erreur PHP, le fichier JS existe et a été modifié.

- [ ] **Step 6: Commit**

```bash
git add gestionprojet/pages/step4_provided.php gestionprojet/ajax/autosave.php gestionprojet/amd/src/cdcf_bootstrap.js
git commit -m "$(cat <<'EOF'
feat(step4): add Atto editor for teacher intro_text on provided page

Whitelists intro_text in autosave for mode=provided step 4 and wires
the textarea to the cdcf_bootstrap autosave payload.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Côté élève — affichage du texte d'intro en lecture seule

**Files:**
- Modify: `gestionprojet/pages/step4.php` (insérer l'encadré `alert-info` au début de `step4-container`)

- [ ] **Step 1: Insérer le bloc lecture seule**

Dans `gestionprojet/pages/step4.php`, **juste après** la ligne 137 (`<div class="step4-container gp-student">`), insérer :

```php
    <?php
    // Display teacher's pedagogical intro text (read-only, live-read from cdcf_provided).
    if ((int)($gestionprojet->step4_provided ?? 0) === 1) {
        $providedforintro = $DB->get_record('gestionprojet_cdcf_provided', ['gestionprojetid' => $gestionprojet->id]);
        if ($providedforintro && !empty(trim(strip_tags($providedforintro->intro_text ?? '')))) {
            echo html_writer::start_div('alert alert-info gp-consigne-intro');
            echo html_writer::tag('h4', get_string('intro_section_title', 'gestionprojet'));
            echo format_text($providedforintro->intro_text, FORMAT_HTML, ['context' => $context]);
            echo html_writer::end_div();
        }
    }
    ?>
```

- [ ] **Step 2: Validation syntaxe**

```bash
php -l gestionprojet/pages/step4.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add gestionprojet/pages/step4.php
git commit -m "$(cat <<'EOF'
feat(step4): display teacher intro_text read-only at top of student page

Live-read from gestionprojet_cdcf_provided (no copy into student record),
so teacher edits propagate to students on next reload.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Classe `reset_helper` + tests PHPUnit

**Files:**
- Create: `gestionprojet/classes/reset_helper.php`
- Create: `gestionprojet/tests/reset_helper_test.php`

- [ ] **Step 1: Écrire le test failing FIRST**

Créer `gestionprojet/tests/reset_helper_test.php` :

```php
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
 * Tests for reset_helper.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gestionprojet/lib.php');
require_once($CFG->dirroot . '/mod/gestionprojet/classes/reset_helper.php');

/**
 * @covers \mod_gestionprojet\reset_helper
 */
final class reset_helper_test extends \advanced_testcase {

    public function test_reset_step4_overwrites_student_record(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step4_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // Seed teacher provided record.
        $providedjson = json_encode(['fonctionsService' => [['id' => 1, 'description' => 'NEW FROM TEACHER']]]);
        $DB->insert_record('gestionprojet_cdcf_provided', (object) [
            'gestionprojetid' => $instance->id,
            'interacteurs_data' => $providedjson,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Seed an existing student draft with old data.
        $studentrec = (object) [
            'gestionprojetid' => $instance->id,
            'userid' => $user->id,
            'groupid' => 0,
            'status' => 0,
            'interacteurs_data' => json_encode(['fonctionsService' => [['id' => 1, 'description' => 'OLD STUDENT WORK']]]),
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $studentrec->id = $DB->insert_record('gestionprojet_cdcf', $studentrec);

        // Act.
        $gp = $DB->get_record('gestionprojet', ['id' => $instance->id], '*', MUST_EXIST);
        $result = reset_helper::reset_step_to_provided($gp, 4, 0, $user->id);

        // Assert.
        $this->assertTrue($result['success']);
        $updated = $DB->get_record('gestionprojet_cdcf', ['id' => $studentrec->id]);
        $this->assertSame($providedjson, $updated->interacteurs_data);
    }

    public function test_reset_step4_blocked_when_submitted(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step4_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $providedjson = json_encode(['fonctionsService' => [['id' => 1, 'description' => 'NEW']]]);
        $DB->insert_record('gestionprojet_cdcf_provided', (object) [
            'gestionprojetid' => $instance->id,
            'interacteurs_data' => $providedjson,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $oldjson = json_encode(['fonctionsService' => [['id' => 1, 'description' => 'OLD STUDENT (SUBMITTED)']]]);
        $studentrec = (object) [
            'gestionprojetid' => $instance->id,
            'userid' => $user->id,
            'groupid' => 0,
            'status' => 1, // Submitted.
            'interacteurs_data' => $oldjson,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $studentrec->id = $DB->insert_record('gestionprojet_cdcf', $studentrec);

        $gp = $DB->get_record('gestionprojet', ['id' => $instance->id], '*', MUST_EXIST);
        $result = reset_helper::reset_step_to_provided($gp, 4, 0, $user->id);

        $this->assertFalse($result['success']);
        $this->assertSame('locked', $result['error']);
        $unchanged = $DB->get_record('gestionprojet_cdcf', ['id' => $studentrec->id]);
        $this->assertSame($oldjson, $unchanged->interacteurs_data);
    }

    public function test_reset_step4_returns_error_when_no_provided(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step4_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // No cdcf_provided record at all.
        $gp = $DB->get_record('gestionprojet', ['id' => $instance->id], '*', MUST_EXIST);
        $result = reset_helper::reset_step_to_provided($gp, 4, 0, $user->id);

        $this->assertFalse($result['success']);
        $this->assertSame('no_provided', $result['error']);
    }

    public function test_reset_rejects_unsupported_step(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $gp = $DB->get_record('gestionprojet', ['id' => $instance->id], '*', MUST_EXIST);
        $result = reset_helper::reset_step_to_provided($gp, 7, 0, $user->id);

        $this->assertFalse($result['success']);
        $this->assertSame('unsupported_step', $result['error']);
    }
}
```

- [ ] **Step 2: Run the test, expect FAIL**

Run (sur l'environnement Moodle où PHPUnit est configuré, dépend du setup local) :

```bash
vendor/bin/phpunit mod/gestionprojet/tests/reset_helper_test.php
```

Expected: erreur `Class "mod_gestionprojet\reset_helper" not found`.

**Note pour l'exécutant :** si le PHPUnit Moodle n'est pas disponible localement, marquer cette étape comme « test rédigé, exécution déférée à la validation manuelle preprod ». Le code doit être écrit pour faire passer ces tests, et la validation s'effectuera manuellement (Task 14).

- [ ] **Step 3: Implémenter `reset_helper`**

Créer `gestionprojet/classes/reset_helper.php` :

```php
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
 * Reset helper: rebuilds a student submission from the latest teacher-provided
 * consigne. Extracted for testability.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

class reset_helper {

    /**
     * Mapping of supported steps to the provided table and the student table + payload fields.
     *
     * @var array<int, array{provided_table: string, student_table: string, fields: array<string>}>
     */
    private const STEP_MAP = [
        4 => [
            'provided_table' => 'gestionprojet_cdcf_provided',
            'student_table'  => 'gestionprojet_cdcf',
            'fields'         => ['interacteurs_data'],
        ],
    ];

    /**
     * Reset a student record to the latest teacher-provided consigne.
     *
     * @param object $gestionprojet Plugin instance record.
     * @param int    $step          Step number (4 currently; 5/7/9 future).
     * @param int    $groupid       Group ID (0 for individual mode).
     * @param int    $userid        User ID.
     * @return array{success: bool, error?: string}
     */
    public static function reset_step_to_provided(object $gestionprojet, int $step, int $groupid, int $userid): array {
        global $DB;

        if (!isset(self::STEP_MAP[$step])) {
            return ['success' => false, 'error' => 'unsupported_step'];
        }

        $map = self::STEP_MAP[$step];

        $provided = $DB->get_record($map['provided_table'], ['gestionprojetid' => $gestionprojet->id]);
        if (!$provided) {
            return ['success' => false, 'error' => 'no_provided'];
        }

        // Determine submission record (use existing helper logic).
        require_once(__DIR__ . '/../lib.php');
        $tablekey = ($step === 4) ? 'cdcf' : 'cdcf'; // Future: map by step.
        $record = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $userid, $tablekey);

        if ((int)$record->status === 1) {
            return ['success' => false, 'error' => 'locked'];
        }

        // Overwrite the configured fields from the provided record.
        foreach ($map['fields'] as $field) {
            if (property_exists($provided, $field)) {
                $record->$field = $provided->$field;
            }
        }
        $record->timemodified = time();
        $DB->update_record($map['student_table'], $record);

        // Audit log.
        if (function_exists('gestionprojet_log_change')) {
            gestionprojet_log_change(
                $gestionprojet->id,
                $tablekey,
                $record->id,
                'reset_to_provided',
                '',
                'reset',
                $userid,
                $groupid
            );
        }

        return ['success' => true];
    }
}
```

- [ ] **Step 4: Run the tests again, expect PASS**

```bash
vendor/bin/phpunit mod/gestionprojet/tests/reset_helper_test.php
```

Expected: 4 tests OK.

(Si pas de PHPUnit local : Task 14 validera fonctionnellement.)

- [ ] **Step 5: Commit**

```bash
git add gestionprojet/classes/reset_helper.php gestionprojet/tests/reset_helper_test.php
git commit -m "$(cat <<'EOF'
feat(reset): add reset_helper class + PHPUnit tests

Encapsulates the logic for resetting a student record to the latest
teacher-provided consigne. Currently supports step 4; designed to be
extended to steps 5, 7, 9 via STEP_MAP.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Endpoint AJAX `reset_to_provided.php`

**Files:**
- Create: `gestionprojet/ajax/reset_to_provided.php`

- [ ] **Step 1: Créer l'endpoint**

Créer `gestionprojet/ajax/reset_to_provided.php` :

```php
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
 * AJAX endpoint: reset a student step to the latest teacher-provided consigne.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/reset_helper.php');

global $DB, $USER;

$id = required_param('id', PARAM_INT);
$step = required_param('step', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:submit', $context);

header('Content-Type: application/json');

try {
    $result = \mod_gestionprojet\reset_helper::reset_step_to_provided(
        $gestionprojet,
        $step,
        $groupid,
        (int)$USER->id
    );

    if (!$result['success']) {
        $errorcode = $result['error'] ?? 'unknown';
        $errormap = [
            'locked'           => ['msg' => get_string('reset_error_locked', 'gestionprojet'),       'http' => 403],
            'no_provided'      => ['msg' => get_string('reset_error_no_provided', 'gestionprojet'),  'http' => 400],
            'unsupported_step' => ['msg' => 'Unsupported step',                                       'http' => 400],
        ];
        $info = $errormap[$errorcode] ?? ['msg' => 'Unknown error', 'http' => 500];
        http_response_code($info['http']);
        echo json_encode([
            'success' => false,
            'error'   => $errorcode,
            'message' => $info['msg'],
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => get_string('reset_success', 'gestionprojet'),
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'exception',
        'message' => $e->getMessage(),
    ]);
}
```

- [ ] **Step 2: Validation syntaxe**

```bash
php -l gestionprojet/ajax/reset_to_provided.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add gestionprojet/ajax/reset_to_provided.php
git commit -m "$(cat <<'EOF'
feat(ajax): add reset_to_provided endpoint

POST endpoint that calls reset_helper::reset_step_to_provided after
require_login + sesskey + capability checks. Returns localized error
messages with proper HTTP codes (403 locked, 400 invalid).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: Bouton Reset côté élève + JS modal

**Files:**
- Modify: `gestionprojet/pages/step4.php` (insérer le bouton dans la section `export-section`)
- Modify: `gestionprojet/amd/src/cdcf_bootstrap.js` (listener bouton + modal + fetch)

- [ ] **Step 1: Ajouter le bouton dans `step4.php`**

Dans `gestionprojet/pages/step4.php`, dans le bloc `<div class="export-section">` (ligne 179 environ), **après** les boutons existants `submitButton` et `revertButton` mais **avant** la `</div>` de fermeture, insérer :

```php
            <?php if ($showstudentform && (int)($gestionprojet->step4_provided ?? 0) === 1): ?>
                <button type="button"
                        class="btn btn-warning"
                        id="resetButton"
                        <?php echo $isLocked ? 'disabled title="' . s(get_string('reset_disabled_tooltip', 'gestionprojet')) . '"' : ''; ?>>
                    <?php echo get_string('reset_button_label', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>
```

- [ ] **Step 2: Passer la config Reset au bootstrap JS**

Dans le même fichier `step4.php`, modifier l'appel à `js_call_amd` (lignes 229-242) pour ajouter les nouvelles clés :

```php
$PAGE->requires->js_call_amd('mod_gestionprojet/cdcf_bootstrap', 'init', [[
    'cmid'          => (int)$cm->id,
    'step'          => 4,
    'groupid'       => (int)$groupid,
    'autosaveMs'    => (int)$gestionprojet->autosave_interval * 1000,
    'isLocked'      => (bool)$isLocked,
    'canSubmit'     => (bool)$canSubmit,
    'canRevert'     => (bool)$canRevert,
    'projetNom'     => $projetnom,
    'initial'       => $cdcfdata,
    'lang'          => $langstrings,
    'confirmSubmit' => get_string('confirm_submission', 'gestionprojet'),
    'confirmRevert' => get_string('confirm_revert', 'gestionprojet'),
    'resetEnabled'  => (bool)((int)($gestionprojet->step4_provided ?? 0) === 1) && !$isLocked,
    'resetUrl'      => (new moodle_url('/mod/gestionprojet/ajax/reset_to_provided.php'))->out(false),
    'sesskey'       => sesskey(),
    'resetLang'     => [
        'modalTitle'   => get_string('reset_modal_title', 'gestionprojet'),
        'modalBody'    => get_string('reset_modal_body', 'gestionprojet'),
        'modalConfirm' => get_string('reset_modal_confirm', 'gestionprojet'),
        'modalCancel'  => get_string('reset_modal_cancel', 'gestionprojet'),
        'success'      => get_string('reset_success', 'gestionprojet'),
        'genericError' => get_string('error', 'core'),
    ],
]]);
```

- [ ] **Step 3: Ajouter le listener + modal dans `cdcf_bootstrap.js`**

Dans `gestionprojet/amd/src/cdcf_bootstrap.js`, à la fin de la fonction `init` (juste avant le `}` final de `init`), ajouter :

```javascript
    // Reset-to-provided button.
    var resetBtn = document.getElementById('resetButton');
    if (resetBtn && config.resetEnabled && config.resetUrl) {
        resetBtn.addEventListener('click', function() {
            var lang = config.resetLang || {};
            var modalHtml = '' +
                '<div class="modal fade" id="gpResetModal" tabindex="-1" role="dialog">' +
                '  <div class="modal-dialog" role="document">' +
                '    <div class="modal-content">' +
                '      <div class="modal-header">' +
                '        <h5 class="modal-title">' + escapeHtml(lang.modalTitle || 'Reset?') + '</h5>' +
                '        <button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                '          <span aria-hidden="true">&times;</span>' +
                '        </button>' +
                '      </div>' +
                '      <div class="modal-body"><p>' + escapeHtml(lang.modalBody || '') + '</p></div>' +
                '      <div class="modal-footer">' +
                '        <button type="button" class="btn btn-secondary" data-dismiss="modal">' +
                          escapeHtml(lang.modalCancel || 'Cancel') + '</button>' +
                '        <button type="button" class="btn btn-warning" id="gpResetConfirm">' +
                          escapeHtml(lang.modalConfirm || 'Reset') + '</button>' +
                '      </div>' +
                '    </div>' +
                '  </div>' +
                '</div>';
            // Inject modal markup into DOM.
            var existing = document.getElementById('gpResetModal');
            if (existing) { existing.parentNode.removeChild(existing); }
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            var modalEl = document.getElementById('gpResetModal');
            // Use jQuery + Bootstrap modal API (consistent with rest of plugin).
            require(['jquery'], function($) {
                $(modalEl).modal('show');
                $('#gpResetConfirm').on('click', function() {
                    var fd = new FormData();
                    fd.append('id', config.cmid);
                    fd.append('step', config.step);
                    fd.append('groupid', config.groupid || 0);
                    fd.append('sesskey', config.sesskey);
                    $('#gpResetConfirm').prop('disabled', true);
                    fetch(config.resetUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd,
                    }).then(function(r) {
                        return r.json().then(function(j) { return { ok: r.ok, body: j }; });
                    }).then(function(res) {
                        $(modalEl).modal('hide');
                        if (res.ok && res.body.success) {
                            window.location.reload();
                        } else {
                            window.alert(res.body.message || (lang.genericError || 'Error'));
                            $('#gpResetConfirm').prop('disabled', false);
                        }
                    }).catch(function(err) {
                        $(modalEl).modal('hide');
                        window.alert(lang.genericError || 'Error');
                        // eslint-disable-next-line no-console
                        console.error(err);
                    });
                });
            });
        });
    }

    // Local helper to escape HTML in user-facing strings.
    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
```

**Note d'intégration :** la fonction `escapeHtml` doit être au scope `init` (déclarée avec `function`). Si `cdcf_bootstrap.js` utilise un module pattern strict (e.g. `define(...)` avec `'use strict'`), placer `escapeHtml` à l'intérieur de `init` mais avant son utilisation, ou en haut du fichier au scope du module.

- [ ] **Step 4: Validation**

```bash
php -l gestionprojet/pages/step4.php
ls -la gestionprojet/amd/src/cdcf_bootstrap.js
```

Expected : pas d'erreur PHP, le fichier JS a bien été modifié (la validation JS de fond se fera au runtime via Moodle/Atto sur preprod en Task 14).

- [ ] **Step 5: Commit**

```bash
git add gestionprojet/pages/step4.php gestionprojet/amd/src/cdcf_bootstrap.js
git commit -m "$(cat <<'EOF'
feat(step4): add reset button + confirmation modal on student page

Bouton « Réinitialiser le formulaire » qui appelle reset_to_provided
après confirmation modale. Disabled si soumission verrouillée.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 9: Build AMD (Grunt)

**Files:**
- Modify: `gestionprojet/amd/build/cdcf_bootstrap.min.js` (généré par grunt)

- [ ] **Step 1: Vérifier la disponibilité de grunt**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
which grunt || npm list -g --depth=0 | grep grunt || echo "grunt not installed globally"
```

Si grunt n'est pas disponible, deux options :
- Installer : `npm install -g grunt-cli` puis dans une copie complète d'un Moodle dev (pas le plugin seul) `cd $MOODLE_DIR && npm install`.
- Déférer le build au déploiement : Moodle servira le `src` en mode debug, mais en prod il faut le `min.js`. Marquer alors la sub-step 2 comme « TODO sur la machine de build ».

- [ ] **Step 2: Lancer grunt amd sur le plugin**

Depuis le répertoire racine d'une installation Moodle dev :

```bash
cd $MOODLE_DIR  # racine d'une install Moodle dev
npx grunt amd --root=mod/gestionprojet
```

Expected: génération de `gestionprojet/amd/build/cdcf_bootstrap.min.js` mis à jour.

**Alternative (si pas de Moodle dev local)** : copier `cdcf_bootstrap.js` vers `cdcf_bootstrap.min.js` (Moodle accepte le fichier non minifié comme fallback en mode debug). Acceptable temporairement, mais le `grunt amd` final reste requis avant la release.

- [ ] **Step 3: Commit du build**

```bash
git add gestionprojet/amd/build/cdcf_bootstrap.min.js
git commit -m "$(cat <<'EOF'
build(amd): rebuild cdcf_bootstrap.min.js after reset button changes

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 10: Intégration prompt IA (`ai_prompt_builder` + `ai_evaluator`)

**Files:**
- Modify: `gestionprojet/classes/ai_prompt_builder.php` (lignes 147-224 — `build_prompt` et `build_system_prompt`)
- Modify: `gestionprojet/classes/ai_evaluator.php` (lignes 179-191 — récupération du teacher model)
- Modify: `gestionprojet/tests/ai_meta_prompt_test.php` (ajout d'un test pour `intro_text`)

- [ ] **Step 1: Écrire le test failing FIRST**

Ouvrir `gestionprojet/tests/ai_meta_prompt_test.php` et ajouter à la fin de la classe (avant la `}` finale) :

```php
    public function test_build_system_prompt_includes_intro_text_when_provided(): void {
        $teachermodel = (object) [
            'ai_instructions' => 'Évaluer la qualité des FS.',
        ];
        $teacherintro = 'Pour ce projet, vous travaillerez sur un système de tri sélectif.';

        $builder = new \mod_gestionprojet\ai_prompt_builder();
        $prompt = $builder->build_system_prompt(4, $teachermodel, $teacherintro);

        $this->assertStringContainsString('Pour ce projet, vous travaillerez', $prompt);
        $this->assertStringContainsString('CONTEXTE FOURNI PAR L\'ENSEIGNANT', $prompt);
        $this->assertStringContainsString('Évaluer la qualité des FS.', $prompt);
    }

    public function test_build_system_prompt_omits_intro_text_when_null_or_empty(): void {
        $teachermodel = (object) ['ai_instructions' => 'Eval.'];
        $builder = new \mod_gestionprojet\ai_prompt_builder();

        $promptnull = $builder->build_system_prompt(4, $teachermodel, null);
        $promptempty = $builder->build_system_prompt(4, $teachermodel, '   ');

        $this->assertStringNotContainsString('CONTEXTE FOURNI PAR L\'ENSEIGNANT', $promptnull);
        $this->assertStringNotContainsString('CONTEXTE FOURNI PAR L\'ENSEIGNANT', $promptempty);
    }

    public function test_build_system_prompt_strips_html_from_intro_text(): void {
        $teachermodel = (object) ['ai_instructions' => 'Eval.'];
        $teacherintro = '<p>Travail sur <strong>la sécurité</strong> électrique.</p>';

        $builder = new \mod_gestionprojet\ai_prompt_builder();
        $prompt = $builder->build_system_prompt(4, $teachermodel, $teacherintro);

        $this->assertStringContainsString('Travail sur la sécurité électrique.', $prompt);
        $this->assertStringNotContainsString('<strong>', $prompt);
    }
```

- [ ] **Step 2: Run the test, expect FAIL (signature mismatch)**

```bash
vendor/bin/phpunit mod/gestionprojet/tests/ai_meta_prompt_test.php
```

Expected: `Too few arguments` ou test failures.

- [ ] **Step 3: Mettre à jour `ai_prompt_builder.php`**

Dans `gestionprojet/classes/ai_prompt_builder.php` :

**3a — Modifier la signature de `build_prompt` (ligne 147)** :

```php
    public function build_prompt(int $step, object $studentdata, object $teachermodel, ?string $teacherintro = null): array {
        $systemprompt = $this->build_system_prompt($step, $teachermodel, $teacherintro);
        $userprompt = $this->build_user_prompt($step, $studentdata, $teachermodel);

        return [
            'system' => $systemprompt,
            'user' => $userprompt,
        ];
    }
```

**3b — Modifier `build_system_prompt` (ligne 164)** :

Remplacer la signature et la logique par :

```php
    public function build_system_prompt(int $step, object $teachermodel, ?string $teacherintro = null): string {
        $stepcontext = self::STEP_CONTEXT[$step] ?? 'Évaluation de production élève';
        $aiinstructions = $teachermodel->ai_instructions ?? '';
        $criteriatext = $this->build_criteria_text($step);

        $prompt = <<<PROMPT
Tu es un assistant d'évaluation pédagogique expert en technologie pour le collège et le lycée.

CONTEXTE DE L'ÉVALUATION:
- Étape: $stepcontext
- Barème: Note sur 20 points
- Public: Élèves de collège/lycée

CRITÈRES D'ÉVALUATION:
$criteriatext

INSTRUCTIONS GÉNÉRALES:
1. Compare objectivement la production de l'élève avec le modèle de correction fourni
2. Évalue chaque critère de manière indépendante
3. Attribue une note sur 20 proportionnelle aux critères remplis
4. Fournis un feedback constructif, bienveillant et pédagogique
5. Identifie les points forts et les axes d'amélioration

PROMPT;

        // Add teacher's pedagogical intro (visible to students) as additional context for the AI.
        if ($teacherintro !== null) {
            $cleanintro = trim(html_entity_decode(strip_tags($teacherintro)));
            if ($cleanintro !== '') {
                $prompt .= <<<PROMPT

CONTEXTE FOURNI PAR L'ENSEIGNANT (présentation aux élèves):
$cleanintro

PROMPT;
            }
        }

        // Add teacher-specific instructions if provided.
        if (!empty($aiinstructions)) {
            $prompt .= <<<PROMPT

INSTRUCTIONS SPÉCIFIQUES DU PROFESSEUR:
$aiinstructions

PROMPT;
        }

        $prompt .= <<<PROMPT

FORMAT DE RÉPONSE OBLIGATOIRE:
Tu DOIS répondre UNIQUEMENT avec un objet JSON valide respectant exactement cette structure:
{
  "grade": <nombre entre 0 et 20>,
  "max_grade": 20,
  "feedback": "<texte de feedback détaillé et bienveillant>",
  "criteria": [
    {"name": "<nom du critère>", "score": <score>, "max": <max>, "comment": "<commentaire>"}
  ],
  "keywords_found": ["<mot-clé trouvé>"],
  "keywords_missing": ["<mot-clé attendu mais absent>"],
  "suggestions": ["<suggestion d'amélioration>"],
  "confidence": <nombre entre 0 et 1>
}

IMPORTANT:
- La réponse doit être UNIQUEMENT du JSON valide, sans texte avant ou après
- Sois juste mais bienveillant dans ton évaluation
- Les erreurs mineures ne doivent pas pénaliser lourdement
- Valorise les efforts et les bonnes idées de l'élève
PROMPT;

        return $prompt;
    }
```

- [ ] **Step 4: Mettre à jour `ai_evaluator.php` pour fetcher `cdcf_provided`**

Dans `gestionprojet/classes/ai_evaluator.php`, autour de la ligne 189-191. Remplacer :

```php
            // Build prompts.
            $promptbuilder = new ai_prompt_builder();
            $prompts = $promptbuilder->build_prompt($evaluation->step, $submission, $teachermodel);
```

par :

```php
            // Fetch teacher pedagogical intro (step 4 only for now, future: 5/7/9).
            $teacherintro = null;
            if ((int)$evaluation->step === 4) {
                $providedrec = $DB->get_record('gestionprojet_cdcf_provided', ['gestionprojetid' => $evaluation->gestionprojetid]);
                if ($providedrec && !empty(trim(strip_tags($providedrec->intro_text ?? '')))) {
                    $teacherintro = $providedrec->intro_text;
                }
            }

            // Build prompts.
            $promptbuilder = new ai_prompt_builder();
            $prompts = $promptbuilder->build_prompt($evaluation->step, $submission, $teachermodel, $teacherintro);
```

- [ ] **Step 5: Run the tests, expect PASS**

```bash
vendor/bin/phpunit mod/gestionprojet/tests/ai_meta_prompt_test.php
```

Expected: les 3 nouveaux tests passent + ceux existants restent verts.

- [ ] **Step 6: Validation syntaxe**

```bash
php -l gestionprojet/classes/ai_prompt_builder.php
php -l gestionprojet/classes/ai_evaluator.php
```

- [ ] **Step 7: Commit**

```bash
git add gestionprojet/classes/ai_prompt_builder.php gestionprojet/classes/ai_evaluator.php gestionprojet/tests/ai_meta_prompt_test.php
git commit -m "$(cat <<'EOF'
feat(ai): inject teacher intro_text into evaluation system prompt

build_prompt and build_system_prompt accept an optional \$teacherintro
parameter. When non-empty, it is appended as plain-text context (HTML
stripped) before the teacher's ai_instructions. ai_evaluator fetches
intro_text from cdcf_provided for step 4 evaluations.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 11: Backup / Restore

**Files:**
- Modify: `gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php:104-108` (ajouter `intro_text` au `cdcf_provided`)

- [ ] **Step 1: Ajouter `intro_text` au backup**

Dans `gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php`, lignes 104-108 actuellement :

```php
        $cdcfprovided = new backup_nested_element('cdcf_provided', ['id'], [
            'interacteurs_data',
            'timecreated', 'timemodified',
        ]);
```

Remplacer par :

```php
        $cdcfprovided = new backup_nested_element('cdcf_provided', ['id'], [
            'interacteurs_data', 'intro_text',
            'timecreated', 'timemodified',
        ]);
```

- [ ] **Step 2: Vérifier le restore**

`gestionprojet/backup/moodle2/restore_gestionprojet_stepslib.php` ligne 248 (`process_gestionprojet_cdcf_provided`) utilise déjà `(array)$data` et `$DB->insert_record(...)`. Le nouveau champ est porté automatiquement. **Aucune modification nécessaire**.

Vérifier en lecture pour confirmation :

```bash
sed -n '244,260p' gestionprojet/backup/moodle2/restore_gestionprojet_stepslib.php
```

Expected: la fonction itère générique sur `(array)$data`, pas de mention explicite des colonnes.

- [ ] **Step 3: Validation syntaxe**

```bash
php -l gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php
```

- [ ] **Step 4: Commit**

```bash
git add gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php
git commit -m "$(cat <<'EOF'
feat(backup): include intro_text in cdcf_provided backup

Restore is generic ((array)\$data + insert_record) so no change needed
on the restore side.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 12: Styles CSS

**Files:**
- Modify: `gestionprojet/styles.css` (ajouter classes `.gp-consigne-intro`, `.gp-intro-section`, `.gp-intro-textarea`)

- [ ] **Step 1: Ajouter les classes CSS**

À la fin de `gestionprojet/styles.css`, ajouter :

```css
/* === Step 4 consigne refonte (v2.9.0) === */

/* Read-only intro displayed at top of student step 4. */
.path-mod-gestionprojet .gp-consigne-intro {
    margin-bottom: 1.5rem;
    border-left: 4px solid #1976d2;
}
.path-mod-gestionprojet .gp-consigne-intro h4 {
    margin-top: 0;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
    color: #1976d2;
}

/* Teacher-side intro section (above interacteurs). */
.path-mod-gestionprojet .gp-intro-section {
    margin-bottom: 1.5rem;
}
.path-mod-gestionprojet .gp-intro-textarea {
    min-height: 8rem;
}

/* Disabled reset button styling. */
.path-mod-gestionprojet #resetButton:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
```

- [ ] **Step 2: Commit**

```bash
git add gestionprojet/styles.css
git commit -m "$(cat <<'EOF'
style(step4): add CSS for consigne intro block + reset button

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 13: CHANGELOG + RELEASE_NOTES

**Files:**
- Modify: `gestionprojet/CHANGELOG.md`
- Create: `RELEASE_NOTES_v2.9.0.md`

- [ ] **Step 1: Ajouter entrée 2.9.0 dans CHANGELOG**

Ouvrir `gestionprojet/CHANGELOG.md` pour repérer l'emplacement de la dernière entrée et insérer **au-dessus** :

```markdown
## [2.9.0] - 2026-05-06

### Added
- **Step 4 (CDCF) — Refonte consigne enseignant** : nouveau champ texte d'introduction (éditeur Atto) sur la page « Consigne », visible en lecture seule en haut de l'activité élève. Lecture en temps réel : les modifications enseignant se propagent immédiatement à tous les élèves au prochain reload.
- **Bouton « Réinitialiser le formulaire »** côté élève (step 4) : permet à l'élève de remplacer son brouillon par la dernière version de la consigne fournie par l'enseignant. Action confirmée par modal, désactivée si le formulaire est soumis.
- **Endpoint AJAX** `ajax/reset_to_provided.php` (capability `submit`, garde-fou serveur sur `status === 1`).
- **Classe `\mod_gestionprojet\reset_helper`** (testable via PHPUnit) — extensible aux steps 5/7/9 dans une prochaine itération.
- **IA** : injection du texte d'intro enseignant dans le prompt système (plain text, après stripping HTML) pour mieux contextualiser l'évaluation.

### Database
- Nouvelle colonne `intro_text` (TEXT, nullable) sur `gestionprojet_cdcf_provided`.

### Migration
- Aucune migration des records élèves existants. Le seed initial existant continue de fonctionner pour les élèves dont le record est encore vide. Les autres peuvent cliquer sur « Réinitialiser le formulaire » pour récupérer la dernière consigne.

### Internal
- `ai_prompt_builder::build_prompt` et `build_system_prompt` acceptent un nouveau paramètre optionnel `?string $teacherintro = null`.
```

- [ ] **Step 2: Créer RELEASE_NOTES_v2.9.0.md**

Créer `/Volumes/DONNEES/Claude code/mod_gestionprojet/RELEASE_NOTES_v2.9.0.md` :

```markdown
# Release Notes — v2.9.0 (2026-05-06)

## Refonte de la consigne CDCF (Step 4)

### Problème résolu

Lorsque l'enseignant modifiait la consigne CDCF d'une activité après que les élèves aient ouvert au moins une fois la page, **les modifications n'étaient pas propagées** : chaque élève conservait une copie figée de la consigne d'origine. Ce comportement, bien que volontaire (préserver le travail élève), n'offrait aucun mécanisme pour récupérer une consigne mise à jour.

### Solution

1. **Nouveau champ « Texte de présentation aux élèves »** sur la page consigne enseignant (mode=provided), avec éditeur Atto. Affiché en lecture seule en haut de l'activité élève.
2. **Lecture en temps réel** : modifier ce texte côté enseignant se reflète immédiatement chez tous les élèves au prochain reload (pas de copie).
3. **Bouton « Réinitialiser le formulaire »** côté élève : permet à l'élève de remplacer son brouillon par la dernière version de la consigne, après confirmation modale. Désactivé si le formulaire est soumis (l'enseignant peut faire un revert pour le réactiver).
4. **IA contextualisée** : le texte d'intro enseignant est désormais injecté dans le prompt d'évaluation IA, pour une évaluation mieux contextualisée.

### Compatibilité

- Aucune action requise sur les activités existantes : le nouveau champ est vide par défaut.
- Aucune migration des records élèves : les drafts existants sont préservés tels quels.
- Le mécanisme de pré-remplissage initial (« seed ») existant reste en place pour les nouveaux élèves.

### Mise à jour DB

- Nouvelle colonne `intro_text` (TEXT, nullable) sur `gestionprojet_cdcf_provided`. Étape d'upgrade automatique à `2026050800`.

### Périmètre futur

Le pattern (intro + bouton Reset) sera étendu aux étapes :
- Step 5 (Essai) — `essai_provided`
- Step 9 (FAST) — `fast_provided`
- Step 7 (Expression du besoin) — création du mode provided à venir

La classe `\mod_gestionprojet\reset_helper` est conçue pour cette extension.
```

- [ ] **Step 3: Commit**

```bash
git add gestionprojet/CHANGELOG.md RELEASE_NOTES_v2.9.0.md
git commit -m "$(cat <<'EOF'
docs(changelog): add 2.9.0 entry + release notes

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 14: Validation manuelle preprod (avant push prod)

**Files:** aucun (validation fonctionnelle)

- [ ] **Step 1: Déployer sur preprod via SCP**

Suivre la procédure documentée dans `TESTING.md` (cf. mémoire `preprod_access`). Concrètement :

```bash
# Adapter les chemins selon TESTING.md.
rsync -avz --delete \
  --exclude='.git' --exclude='.claude' --exclude='lessons.md' \
  "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/" \
  <user>@preprod.ent-occitanie.com:<chemin_preprod>/mod/gestionprojet/
```

Puis sur preprod :

```bash
ssh <user>@preprod.ent-occitanie.com
cd <chemin_preprod>
sudo -u www-data php admin/cli/upgrade.php
sudo -u www-data php admin/cli/purge_caches.php
```

Expected : upgrade applique `2026050800`, pas d'erreur.

- [ ] **Step 2: Test fonctionnel #1 — Persistance enseignant**

1. Connexion compte enseignant.
2. Activité gestionprojet existante avec `step4_provided=1`. Aller sur `view.php?id=<cmid>&step=4&mode=provided`.
3. Remplir le nouveau champ d'intro avec : « Test **gras**, *italique*, • liste, [lien](https://example.com) ».
4. Enregistrer (bouton Save). Recharger la page.
5. **Attendu** : Atto re-rend le HTML correctement, contenu persisté.

- [ ] **Step 3: Test fonctionnel #2 — Pré-remplissage nouvel élève**

1. Connexion compte élève **n'ayant jamais ouvert step 4** sur cette activité.
2. Aller sur step 4.
3. **Attendu** : encadré bleu en haut avec le texte d'intro de l'enseignant. Formulaire CDCF pré-rempli depuis la consigne.

- [ ] **Step 4: Test fonctionnel #3 — Élève existant (cas du bug rapporté)**

1. Connexion compte élève **ayant déjà un draft** avec ancienne consigne.
2. Aller sur step 4.
3. **Attendu** : encadré bleu en haut (le texte d'intro vient d'être ajouté par l'enseignant donc nouveau). Formulaire CDCF reste avec l'ancienne consigne (le seed n'a pas été re-déclenché — comportement existant inchangé).

- [ ] **Step 5: Test fonctionnel #4 — Bouton Reset (cas nominal)**

1. Sur le compte de l'élève précédent (step 4 ouvert).
2. Cliquer sur « Réinitialiser le formulaire » → modal s'ouvre.
3. Cliquer « Réinitialiser ».
4. **Attendu** : la page se recharge ; le formulaire CDCF affiche maintenant la consigne actuelle (la nouvelle version, pas l'ancienne).

- [ ] **Step 6: Test fonctionnel #5 — Bouton Reset désactivé après soumission**

1. L'élève soumet son CDCF.
2. **Attendu** : bouton « Réinitialiser » grisé, tooltip visible au survol (« Le formulaire est verrouillé… »).
3. Tenter un POST direct sur `ajax/reset_to_provided.php` via curl ou DevTools :
   ```bash
   curl -X POST "https://preprod.ent-occitanie.com/mod/gestionprojet/ajax/reset_to_provided.php" \
     -d "id=<cmid>&step=4&groupid=0&sesskey=<sk>" \
     -b "MoodleSession=<cookie>"
   ```
4. **Attendu** : HTTP 403, JSON `{success: false, error: "locked"}`. Données élève inchangées.

- [ ] **Step 7: Test fonctionnel #6 — Revert + Reset à nouveau actif**

1. Compte enseignant : faire « revert to draft » sur le CDCF de l'élève soumis.
2. Compte élève : recharger step 4.
3. **Attendu** : bouton Reset à nouveau actif.

- [ ] **Step 8: Test fonctionnel #7 — Modification dynamique du texte d'intro**

1. Compte enseignant : modifier le texte d'intro (page consigne).
2. Compte élève (autre fenêtre) : recharger step 4 sans cliquer sur Reset.
3. **Attendu** : encadré bleu reflète le nouveau texte d'intro **immédiatement** (lecture en temps réel). Le formulaire CDCF, lui, reste inchangé (sauf si reset).

- [ ] **Step 9: Test fonctionnel #8 — IA reçoit l'intro_text**

1. Soumettre une CDCF élève. Déclencher l'évaluation IA depuis l'interface enseignant.
2. Aller dans le dashboard IA / consulter le `prompt_system` enregistré sur l'évaluation (table `gestionprojet_ai_evaluations`).
3. **Attendu** : la section `CONTEXTE FOURNI PAR L'ENSEIGNANT:` apparaît dans le prompt avec le texte d'intro en plain text.

- [ ] **Step 10: Test fonctionnel #9 — Backup/Restore**

1. Backup d'un cours contenant l'activité.
2. Restore dans un autre cours.
3. Compte enseignant : ouvrir step 4 mode=provided sur la copie.
4. **Attendu** : le texte d'intro est présent (le `intro_text` a été préservé dans le backup).

- [ ] **Step 11: En cas de bug détecté**

Reprendre la tâche correspondante dans le plan, corriger, recommit avec `fix(...)`. Re-déployer en preprod et reprendre la validation depuis le test concerné.

---

## Task 15: Push final + déploiement prod

**Files:** aucun (opérations git + Moodle Admin)

- [ ] **Step 1: Vérifier l'état du repo**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git status
git log --oneline main..feat/step4-consigne-refonte
```

Expected : working tree propre, ~10-12 commits sur la branche.

- [ ] **Step 2: Merge dans main et push Forge EDU**

```bash
git checkout main
git merge --no-ff feat/step4-consigne-refonte -m "Merge branch 'feat/step4-consigne-refonte' into main"
git push origin main
```

- [ ] **Step 3: Build le ZIP**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
rm -f gestionprojet-v2.9.0.zip
zip -r gestionprojet-v2.9.0.zip gestionprojet/ \
    -x "*.git*" "*.claude*" "*lessons.md" "*node_modules*"
ls -lh gestionprojet-v2.9.0.zip
```

Expected : ZIP créé, taille raisonnable (~1-2 Mo, comparable à la v2.8.0).

- [ ] **Step 4: Déploiement production**

1. Aller sur `ent-occitanie.com/moodle` admin.
2. Administration → Plugins → Install plugins → Upload ZIP.
3. Valider l'upgrade sur la page Notifications. Vérifier que l'étape `2026050800` s'applique sans erreur.
4. Spot-check : ouvrir une activité gestionprojet existante, vérifier que les CDCF élèves ne sont pas affectés (toujours leurs données), et que le nouveau champ est dispo côté enseignant en mode=provided.

- [ ] **Step 5: Communication / closure**

Le déploiement est terminé. Mettre à jour la mémoire si pertinent (par exemple, mémoire `bugs_fast_pending` n'est pas concernée ; éventuellement créer une mémoire de feature complete pour cette refonte si on souhaite tracker l'extension future aux steps 5/7/9).

---

## Notes pour l'exécutant

- **TDD strict** sur les tâches 6 (reset_helper) et 10 (ai_prompt_builder). Le test échoue d'abord, puis le code le fait passer.
- **Validation syntaxe PHP** à chaque étape (`php -l <fichier>`) — c'est rapide et capture les erreurs grossières.
- **Commits fréquents** : un par étape logique, jamais de commit géant en fin de plan.
- **Si PHPUnit Moodle indisponible localement** : exécuter les tests en preprod ou marquer `[deferred]`. Ne pas sauter l'écriture des tests pour autant — ils restent une assurance pour les futures extensions.
- **Atto et autosave** : le point le plus sensible. Si Atto se ré-initialise mal après autosave (test 14.2), envisager un guard dans cdcf_bootstrap.js qui n'envoie le payload `intro_text` que si la valeur a changé depuis la dernière sauvegarde, et qui ne touche jamais au DOM autour de l'éditeur.
- **Pas de migration DB** des données élèves existantes — c'est un choix de design (cf. spec section 3 « non-objectifs » et section 4 « aucune migration »).
