# Phase FAST (étape 9) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a 9th phase "FAST diagram" to the `mod_gestionprojet` Moodle plugin, enabling teachers to define a correction model and optionally provide a starting diagram, while students build their own functional analysis with a live SVG diagram.

**Architecture:** New step 9 with the same 3-line Gantt pattern as step 4 (CDCF) — `step9_provided` flag for "teacher-provided content" mode, two new tables (`gestionprojet_fast_teacher` and `gestionprojet_fast`), JSON-stored diagram data, AMD modules for live SVG rendering using DOM APIs (no innerHTML to avoid XSS), integration with existing AI evaluation and gradebook engines.

**Tech Stack:** PHP 8.1 + Moodle 5.0 DML/XMLDB, Mustache templates, AMD/RequireJS + jQuery, vanilla JS for SVG layout (port from `sequence-manager` React/TSX), Moodle PHPUnit for pure-function tests.

**Spec reference:** `docs/superpowers/specs/2026-05-03-fast-phase-design.md`

**Security note:** All DOM manipulation uses `createElement`/`createElementNS` + `textContent` for user-controlled strings. No string-concat HTML, no `innerHTML` with untrusted data. Form rows are rendered via small builder helpers; SVG is built node-by-node.

---

## File Structure

### Files to create

| Path | Responsibility |
|------|----------------|
| `gestionprojet/pages/step9.php` | Student page — form + live diagram |
| `gestionprojet/pages/step9_teacher.php` | Teacher page — correction model, AI instructions, populate-from-CDCF |
| `gestionprojet/templates/step9_form.mustache` | Shared template for form + diagram container |
| `gestionprojet/amd/src/fast_diagram.js` | SVG diagram renderer (DOM-safe port of `DiagrammeFast` from sequence-manager) |
| `gestionprojet/amd/src/fast_editor.js` | Form CRUD + autosave bridge + diagram event emitter (DOM-safe builders) |
| `gestionprojet/ajax/fast_populate_cdcf.php` | Endpoint returning CDCF teacher's FS for FAST pre-fill |
| `gestionprojet/pix/icon_step9.svg` | Lucide GitFork icon for step 9 |
| `gestionprojet/tests/fast_helpers_test.php` | PHPUnit unit tests for pure helper functions |

### Files to modify

| Path | Reason |
|------|--------|
| `gestionprojet/db/install.xml` | Add 2 new tables + 2 new fields on `gestionprojet` |
| `gestionprojet/db/upgrade.php` | Migration for 2026050400 |
| `gestionprojet/lib.php` | `delete_instance`, `get_or_create_submission`, helpers, grade-item updates |
| `gestionprojet/pages/home.php` | Add column 9 to Gantt + step 9 to student rows |
| `gestionprojet/view.php` | Route `step=9` |
| `gestionprojet/mod_form.php` | Add `enable_step9` checkbox |
| `gestionprojet/ajax/autosave.php` | Whitelist step 9 (teacher + student) |
| `gestionprojet/classes/ai/evaluator.php` (or equivalent) | Case 9 in evaluation engine |
| `gestionprojet/classes/privacy/provider.php` | Add `gestionprojet_fast` |
| `gestionprojet/classes/output/icon.php` | Step 9 icon reference |
| `gestionprojet/grading.php` | Step 9 filter + render |
| `gestionprojet/report.php` | Step 9 progress counters |
| `gestionprojet/export_pdf.php` | Render FAST diagram in PDF export |
| `gestionprojet/styles.css` | Namespaced styles for `.path-mod-gestionprojet-fast` |
| `gestionprojet/lang/en/gestionprojet.php` | All UI strings |
| `gestionprojet/lang/fr/gestionprojet.php` | All UI strings (FR) |
| `gestionprojet/version.php` | Bump to 2026050400 / release 2.3.0 |

---

## Task 1: Database schema

**Files:**
- Modify: `gestionprojet/db/install.xml`
- Modify: `gestionprojet/db/upgrade.php`
- Modify: `gestionprojet/version.php`

- [ ] **Step 1.1: Add `enable_step9` and `step9_provided` fields to `gestionprojet` in `install.xml`**

Locate the `<TABLE NAME="gestionprojet">` block. After `enable_step8` (around line 27), add:

```xml
        <FIELD NAME="enable_step9" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="1" SEQUENCE="false"/>
```

After `step4_provided`, add:

```xml
        <FIELD NAME="step9_provided" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="0" SEQUENCE="false" COMMENT="When 1, the teacher's FAST diagram is displayed read-only to students as a starting point."/>
```

- [ ] **Step 1.2: Add `gestionprojet_fast_teacher` table**

Append after the carnet_teacher table:

```xml
    <!-- Table: Modèle de correction FAST (enseignant) - Step 9 -->
    <TABLE NAME="gestionprojet_fast_teacher" COMMENT="Teacher correction model for FAST diagram">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="gestionprojetid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="data_json" TYPE="text" NOTNULL="false" SEQUENCE="false" COMMENT="JSON of FAST diagram (FP, FT, sous-FT, ST)"/>
        <FIELD NAME="ai_instructions" TYPE="text" NOTNULL="false" SEQUENCE="false" COMMENT="Instructions for AI evaluation"/>
        <FIELD NAME="submission_date" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="deadline_date" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="gestionprojetid" TYPE="foreign-unique" FIELDS="gestionprojetid" REFTABLE="gestionprojet" REFFIELDS="id"/>
      </KEYS>
    </TABLE>
```

- [ ] **Step 1.3: Add `gestionprojet_fast` table**

```xml
    <!-- Table: Production élève FAST - Step 9 -->
    <TABLE NAME="gestionprojet_fast" COMMENT="Student FAST diagram submission">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="gestionprojetid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="groupid" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="data_json" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="status" TYPE="int" LENGTH="2" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="grade" TYPE="number" LENGTH="10" NOTNULL="false" SEQUENCE="false" DECIMALS="2"/>
        <FIELD NAME="feedback" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="timesubmitted" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="gestionprojetid" TYPE="foreign" FIELDS="gestionprojetid" REFTABLE="gestionprojet" REFFIELDS="id"/>
        <KEY NAME="groupid" TYPE="foreign" FIELDS="groupid" REFTABLE="groups" REFFIELDS="id"/>
      </KEYS>
    </TABLE>
```

- [ ] **Step 1.4: Validate XML**

Run: `xmllint --noout gestionprojet/db/install.xml`
Expected: no output.

- [ ] **Step 1.5: Bump version**

Edit `gestionprojet/version.php`:

```php
$plugin->version = 2026050400;
$plugin->release = '2.3.0';
```

- [ ] **Step 1.6: Add upgrade step in `db/upgrade.php`**

Inside `xmldb_gestionprojet_upgrade($oldversion)`, before the final `return true;`:

```php
    if ($oldversion < 2026050400) {
        $dbman = $DB->get_manager();

        $maintable = new xmldb_table('gestionprojet');
        $field = new xmldb_field('enable_step9', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'enable_step8');
        if (!$dbman->field_exists($maintable, $field)) {
            $dbman->add_field($maintable, $field);
        }
        $field = new xmldb_field('step9_provided', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'step4_provided');
        if (!$dbman->field_exists($maintable, $field)) {
            $dbman->add_field($maintable, $field);
        }

        $teachertable = new xmldb_table('gestionprojet_fast_teacher');
        if (!$dbman->table_exists($teachertable)) {
            $teachertable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $teachertable->add_field('gestionprojetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $teachertable->add_field('data_json', XMLDB_TYPE_TEXT, null, null, null, null);
            $teachertable->add_field('ai_instructions', XMLDB_TYPE_TEXT, null, null, null, null);
            $teachertable->add_field('submission_date', XMLDB_TYPE_INTEGER, '10', null, null, null);
            $teachertable->add_field('deadline_date', XMLDB_TYPE_INTEGER, '10', null, null, null);
            $teachertable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $teachertable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $teachertable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $teachertable->add_key('gestionprojetid', XMLDB_KEY_FOREIGN_UNIQUE, ['gestionprojetid'], 'gestionprojet', ['id']);
            $dbman->create_table($teachertable);
        }

        $studenttable = new xmldb_table('gestionprojet_fast');
        if (!$dbman->table_exists($studenttable)) {
            $studenttable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $studenttable->add_field('gestionprojetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $studenttable->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $studenttable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $studenttable->add_field('data_json', XMLDB_TYPE_TEXT, null, null, null, null);
            $studenttable->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
            $studenttable->add_field('grade', XMLDB_TYPE_NUMBER, '10, 2', null, null, null);
            $studenttable->add_field('feedback', XMLDB_TYPE_TEXT, null, null, null, null);
            $studenttable->add_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $studenttable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $studenttable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $studenttable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $studenttable->add_key('gestionprojetid', XMLDB_KEY_FOREIGN, ['gestionprojetid'], 'gestionprojet', ['id']);
            $studenttable->add_key('groupid', XMLDB_KEY_FOREIGN, ['groupid'], 'groups', ['id']);
            $dbman->create_table($studenttable);
        }

        upgrade_mod_savepoint(true, 2026050400, 'gestionprojet');
    }
```

- [ ] **Step 1.7: Run upgrade and verify**

User runs: `php admin/cli/upgrade.php` from the parent Moodle directory.
Verify in DB: `DESCRIBE mdl_gestionprojet_fast;` shows expected columns.

- [ ] **Step 1.8: Commit**

```bash
git add gestionprojet/db/install.xml gestionprojet/db/upgrade.php gestionprojet/version.php
git commit -m "feat(db): add step9 (FAST) tables and migration"
```

---

## Task 2: Language strings — base set

**Files:**
- Modify: `gestionprojet/lang/en/gestionprojet.php`
- Modify: `gestionprojet/lang/fr/gestionprojet.php`

- [ ] **Step 2.1: Add EN strings**

Append to `gestionprojet/lang/en/gestionprojet.php`:

```php
$string['step9'] = 'FAST diagram';
$string['step9_desc'] = 'Translate functional requirements into technical solutions.';
$string['enable_step9'] = 'Enable step 9 (FAST diagram)';
$string['step9_provided'] = 'Provide a FAST diagram to students';
$string['step9_provided_desc'] = 'When checked, students see the teacher\'s FAST diagram as a starting point.';

$string['fast:diagram_title'] = 'FAST diagram';
$string['fast:diagram_subtitle'] = 'From need to technical solution';
$string['fast:populate_from_cdcf'] = 'Pre-fill from CDCF';
$string['fast:start_empty'] = 'Start empty';
$string['fast:add_function'] = 'Add a technical function';
$string['fast:add_subfunction'] = 'Add a sub-function';
$string['fast:split'] = 'Split';
$string['fast:remove'] = 'Remove';
$string['fast:no_cdcf_data'] = 'No service function defined in the functional specifications.';
$string['fast:ft_description_placeholder'] = 'Description of the technical function...';
$string['fast:sf_description_placeholder'] = 'Description of the sub-function...';
$string['fast:solution_placeholder'] = 'Describe the technical solution...';
$string['fast:placeholder'] = 'FAST diagram';
$string['fast:ai_instructions_label'] = 'AI evaluator instructions';
$string['fast:ai_instructions_help'] = 'Guide the AI evaluator on what to assess.';
$string['fast:no_data_to_evaluate'] = 'No FAST data to evaluate.';
```

- [ ] **Step 2.2: Add FR strings (mirror)**

Append to `gestionprojet/lang/fr/gestionprojet.php`:

```php
$string['step9'] = 'Diagramme FAST';
$string['step9_desc'] = 'Traduire les fonctions de service en solutions techniques.';
$string['enable_step9'] = 'Activer l\'étape 9 (Diagramme FAST)';
$string['step9_provided'] = 'Fournir un diagramme FAST aux élèves';
$string['step9_provided_desc'] = 'Si coché, les élèves voient le FAST de l\'enseignant comme point de départ.';

$string['fast:diagram_title'] = 'Diagramme FAST';
$string['fast:diagram_subtitle'] = 'Du besoin à la solution technique';
$string['fast:populate_from_cdcf'] = 'Pré-remplir depuis le CDCF';
$string['fast:start_empty'] = 'Commencer vierge';
$string['fast:add_function'] = 'Ajouter une fonction technique';
$string['fast:add_subfunction'] = 'Ajouter une sous-fonction';
$string['fast:split'] = 'Scinder';
$string['fast:remove'] = 'Supprimer';
$string['fast:no_cdcf_data'] = 'Aucune fonction de service définie dans le cahier des charges.';
$string['fast:ft_description_placeholder'] = 'Description de la fonction technique...';
$string['fast:sf_description_placeholder'] = 'Description de la sous-fonction...';
$string['fast:solution_placeholder'] = 'Décrivez la solution technique...';
$string['fast:placeholder'] = 'Diagramme FAST';
$string['fast:ai_instructions_label'] = 'Consignes pour le correcteur IA';
$string['fast:ai_instructions_help'] = 'Guidez le correcteur IA sur les critères d\'évaluation.';
$string['fast:no_data_to_evaluate'] = 'Aucune donnée FAST à évaluer.';
```

- [ ] **Step 2.3: Purge caches**

Run: `php admin/cli/purge_caches.php`

- [ ] **Step 2.4: Commit**

```bash
git add gestionprojet/lang/
git commit -m "lang: add step9 (FAST) strings (EN+FR)"
```

---

## Task 3: `lib.php` — core helpers, navigation, delete_instance

**Files:**
- Modify: `gestionprojet/lib.php`

- [ ] **Step 3.1: Add `gestionprojet_fast_to_text` helper**

At the end of `gestionprojet/lib.php`:

```php
/**
 * Serialize a FAST data_json structure into a human/LLM-readable hierarchical text.
 *
 * @param string|null $datajson JSON string from gestionprojet_fast(_teacher).data_json
 * @return string Multiline text representation, or empty string if no data
 */
function gestionprojet_fast_to_text($datajson) {
    if (empty($datajson)) {
        return '';
    }
    $data = json_decode($datajson, true);
    if (!is_array($data) || empty($data['fonctions'])) {
        return '';
    }

    $lines = [];
    if (!empty($data['fonctionsPrincipales'])) {
        $fps = array_map(function($fp) {
            return $fp['description'] ?? '';
        }, $data['fonctionsPrincipales']);
        $fps = array_filter($fps);
        if (!empty($fps)) {
            $lines[] = 'Fonction principale : ' . implode(' / ', $fps);
            $lines[] = '';
        }
    }

    foreach ($data['fonctions'] as $idx => $ft) {
        $ftnum = $idx + 1;
        $ftdesc = $ft['description'] ?? '';
        $lines[] = "FT{$ftnum} — {$ftdesc}";
        $sous = $ft['sousFonctions'] ?? [];
        if (!empty($sous)) {
            $count = count($sous);
            foreach ($sous as $sfidx => $sf) {
                $branch = ($sfidx === $count - 1) ? '└─' : '├─';
                $sfdesc = $sf['description'] ?? '';
                $sfsol = $sf['solution'] ?? '';
                $lines[] = "  {$branch} FT{$ftnum}.".($sfidx + 1)." {$sfdesc}";
                if (!empty($sfsol)) {
                    $vert = ($sfidx === $count - 1) ? '   ' : '  │';
                    $lines[] = "  {$vert}  Solution : {$sfsol}";
                }
            }
        } else if (!empty($ft['solution'])) {
            $lines[] = '  Solution : ' . $ft['solution'];
        }
    }

    return implode("\n", $lines);
}
```

- [ ] **Step 3.2: Update `gestionprojet_get_or_create_submission` to handle FAST + provided mode**

Read function around line 216 first. Add `'fast'` to the table-name resolution (mirroring `'cdcf'`, `'essai'`, etc.). Then, after a record is freshly created, copy from teacher when `step9_provided=1`:

```php
    if ($table === 'fast' && (int)$gestionprojet->step9_provided === 1 && empty($record->data_json)) {
        $teacher = $DB->get_record('gestionprojet_fast_teacher', ['gestionprojetid' => $gestionprojet->id]);
        if ($teacher && !empty($teacher->data_json)) {
            $record->data_json = $teacher->data_json;
            $record->timemodified = time();
            $DB->update_record('gestionprojet_fast', $record);
        }
    }
```

- [ ] **Step 3.3: Update `gestionprojet_delete_instance` to purge FAST tables**

Inside its body, after existing `$DB->delete_records` calls:

```php
    $DB->delete_records('gestionprojet_fast', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_fast_teacher', ['gestionprojetid' => $id]);
```

- [ ] **Step 3.4: Update grade-item helpers**

```bash
grep -n "4, 5, 6, 7, 8\|range(4, 8)\|\[4,5,6,7,8\]" gestionprojet/lib.php
```

For each match, extend to include 9 (e.g., `[4, 5, 6, 7, 8, 9]` or `range(4, 9)`).

- [ ] **Step 3.5: Update student step navigation**

```bash
grep -n "get_student_steps\|get_teacher_steps" gestionprojet/lib.php
```

In `gestionprojet_get_student_steps`, change the return value to insert step 9 between CDCF (4) and Essai (5):

```php
return [7, 4, 9, 5, 8, 6];
```

- [ ] **Step 3.6: Verify syntax**

Run: `php -l gestionprojet/lib.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3.7: Commit**

```bash
git add gestionprojet/lib.php
git commit -m "feat(lib): step9 helpers, delete_instance, grade-item integration"
```

---

## Task 4: PHPUnit tests for `gestionprojet_fast_to_text`

**Files:**
- Create: `gestionprojet/tests/fast_helpers_test.php`

- [ ] **Step 4.1: Write the failing test**

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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gestionprojet/lib.php');

/**
 * @group mod_gestionprojet
 */
class mod_gestionprojet_fast_helpers_test extends advanced_testcase {

    public function test_empty_input_returns_empty_string() {
        $this->assertSame('', gestionprojet_fast_to_text(null));
        $this->assertSame('', gestionprojet_fast_to_text(''));
        $this->assertSame('', gestionprojet_fast_to_text('null'));
        $this->assertSame('', gestionprojet_fast_to_text('{}'));
    }

    public function test_invalid_json_returns_empty_string() {
        $this->assertSame('', gestionprojet_fast_to_text('not json'));
    }

    public function test_simple_function_with_solution() {
        $json = json_encode([
            'fonctionsPrincipales' => [['id' => 1, 'description' => 'Permettre la mesure']],
            'fonctions' => [
                ['id' => 1, 'description' => 'Mesurer la température', 'solution' => 'Capteur DHT22', 'sousFonctions' => []],
            ],
        ]);
        $text = gestionprojet_fast_to_text($json);
        $this->assertStringContainsString('Fonction principale : Permettre la mesure', $text);
        $this->assertStringContainsString('FT1 — Mesurer la température', $text);
        $this->assertStringContainsString('Solution : Capteur DHT22', $text);
    }

    public function test_function_with_subfunctions() {
        $json = json_encode([
            'fonctionsPrincipales' => [],
            'fonctions' => [
                [
                    'id' => 1, 'description' => 'Acquérir les données', 'solution' => '',
                    'sousFonctions' => [
                        ['id' => 1, 'description' => 'Lire le capteur', 'solution' => 'I2C'],
                        ['id' => 2, 'description' => 'Convertir en JSON', 'solution' => 'json_encode'],
                    ],
                ],
            ],
        ]);
        $text = gestionprojet_fast_to_text($json);
        $this->assertStringContainsString('FT1 — Acquérir les données', $text);
        $this->assertStringContainsString('FT1.1 Lire le capteur', $text);
        $this->assertStringContainsString('FT1.2 Convertir en JSON', $text);
    }
}
```

- [ ] **Step 4.2: Run tests**

User runs: `vendor/bin/phpunit --group mod_gestionprojet`
Expected: 4 tests passing. If PHPUnit isn't initialized: `php admin/tool/phpunit/cli/init.php` first.

- [ ] **Step 4.3: Commit**

```bash
git add gestionprojet/tests/fast_helpers_test.php
git commit -m "test: cover gestionprojet_fast_to_text"
```

---

## Task 5: `mod_form.php` — checkbox `enable_step9`

**Files:**
- Modify: `gestionprojet/mod_form.php`

- [ ] **Step 5.1: Inspect current step checkboxes**

Run: `grep -n "enable_step" gestionprojet/mod_form.php`

- [ ] **Step 5.2: Add the FAST checkbox**

After the `enable_step8` block:

```php
$mform->addElement('advcheckbox', 'enable_step9', get_string('enable_step9', 'gestionprojet'));
$mform->setDefault('enable_step9', 1);
$mform->setType('enable_step9', PARAM_INT);
```

- [ ] **Step 5.3: Verify syntax**

Run: `php -l gestionprojet/mod_form.php`

- [ ] **Step 5.4: Manual smoke test**

In the browser, edit the activity and verify the new checkbox appears, checked by default.

- [ ] **Step 5.5: Commit**

```bash
git add gestionprojet/mod_form.php
git commit -m "feat(form): add enable_step9 checkbox"
```

---

## Task 6: `view.php` — route `step=9`

**Files:**
- Modify: `gestionprojet/view.php`

- [ ] **Step 6.1: Inspect routing**

Run: `grep -n "step ==\|case [0-9]" gestionprojet/view.php`

- [ ] **Step 6.2: Add case for step 9**

Following the pattern of step 4:

```php
} else if ($step === 9) {
    if ($mode === 'teacher') {
        require_once($CFG->dirroot . '/mod/gestionprojet/pages/step9_teacher.php');
    } else {
        require_once($CFG->dirroot . '/mod/gestionprojet/pages/step9.php');
    }
}
```

- [ ] **Step 6.3: Verify syntax**

Run: `php -l gestionprojet/view.php`

- [ ] **Step 6.4: Commit**

```bash
git add gestionprojet/view.php
git commit -m "feat(view): route step=9 to FAST pages"
```

---

## Task 7: `pages/step9_teacher.php`

**Files:**
- Create: `gestionprojet/pages/step9_teacher.php`

- [ ] **Step 7.1: Inspect a similar teacher page**

Run: `head -100 gestionprojet/pages/step4_teacher.php`

- [ ] **Step 7.2: Create the file**

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
 * Step 9 — FAST diagram (teacher correction model + provided content).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_capability('mod/gestionprojet:configureteacherpages', $context);

$teacher = $DB->get_record('gestionprojet_fast_teacher', ['gestionprojetid' => $gestionprojet->id]);
if (!$teacher) {
    $teacher = new stdClass();
    $teacher->gestionprojetid = $gestionprojet->id;
    $teacher->data_json = '';
    $teacher->ai_instructions = '';
    $teacher->submission_date = null;
    $teacher->deadline_date = null;
    $teacher->timecreated = time();
    $teacher->timemodified = time();
    $teacher->id = $DB->insert_record('gestionprojet_fast_teacher', $teacher);
}

$cdcfteacher = $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]);
$hascdcffs = $cdcfteacher && !empty($cdcfteacher->interacteurs_data);

$tplcontext = [
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
    'isteacher' => true,
    'datajson' => $teacher->data_json ?? '',
    'aiinstructions' => $teacher->ai_instructions ?? '',
    'canpopulatecdcf' => $hascdcffs,
    'isprovided' => (int)$gestionprojet->step9_provided === 1,
    'mode' => 'teacher',
];

$PAGE->requires->js_call_amd('mod_gestionprojet/fast_editor', 'init', [
    'cmid' => (int)$cm->id,
    'mode' => 'teacher',
    'sesskey' => sesskey(),
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('step9', 'gestionprojet'));
if ((int)$gestionprojet->step9_provided === 1) {
    echo $OUTPUT->notification(
        get_string('step9_provided_desc', 'gestionprojet'),
        \core\output\notification::NOTIFY_INFO
    );
}
echo $OUTPUT->render_from_template('mod_gestionprojet/step9_form', $tplcontext);
echo $OUTPUT->footer();
```

- [ ] **Step 7.3: Verify syntax**

Run: `php -l gestionprojet/pages/step9_teacher.php`

- [ ] **Step 7.4: Commit**

```bash
git add gestionprojet/pages/step9_teacher.php
git commit -m "feat(pages): step9_teacher (FAST teacher view)"
```

---

## Task 8: `pages/step9.php` (student)

**Files:**
- Create: `gestionprojet/pages/step9.php`

- [ ] **Step 8.1: Create the file**

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
 * Step 9 — FAST diagram (student production).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_capability('mod/gestionprojet:submit', $context);

$groupmode = groups_get_activity_groupmode($cm);
$groupid = groups_get_activity_group($cm, true);
$isgroup = ($gestionprojet->group_submission && $groupid != 0);
$effectivegroupid = $isgroup ? $groupid : 0;
$effectiveuserid = $isgroup ? 0 : $USER->id;

$submission = gestionprojet_get_or_create_submission(
    $gestionprojet, $effectivegroupid, $effectiveuserid, 'fast'
);

$tplcontext = [
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
    'isteacher' => false,
    'datajson' => $submission->data_json ?? '',
    'aiinstructions' => '',
    'canpopulatecdcf' => false,
    'isprovided' => (int)$gestionprojet->step9_provided === 1,
    'submitted' => (int)$submission->status === 1,
    'mode' => 'student',
];

$PAGE->requires->js_call_amd('mod_gestionprojet/fast_editor', 'init', [
    'cmid' => (int)$cm->id,
    'mode' => 'student',
    'sesskey' => sesskey(),
    'groupid' => (int)$effectivegroupid,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('step9', 'gestionprojet'));
echo $OUTPUT->render_from_template('mod_gestionprojet/step9_form', $tplcontext);
echo $OUTPUT->footer();
```

- [ ] **Step 8.2: Verify syntax**

Run: `php -l gestionprojet/pages/step9.php`

- [ ] **Step 8.3: Commit**

```bash
git add gestionprojet/pages/step9.php
git commit -m "feat(pages): step9 (FAST student view)"
```

---

## Task 9: Mustache template `step9_form.mustache`

**Files:**
- Create: `gestionprojet/templates/step9_form.mustache`

- [ ] **Step 9.1: Create the template**

```mustache
{{!
    @template mod_gestionprojet/step9_form

    Form + diagram container for step 9 (FAST).

    Context variables:
    * cmid - course module id
    * sesskey - Moodle session key
    * isteacher - true if teacher view
    * datajson - JSON-encoded FAST data
    * aiinstructions - AI evaluator instructions text (teacher only)
    * canpopulatecdcf - whether the populate-from-CDCF button is shown
    * isprovided - whether step9_provided is enabled
    * mode - 'teacher' or 'student'
    * submitted - whether the student already submitted

    Example context (json):
    { "cmid": 42, "sesskey": "abc", "isteacher": true, "datajson": "{}",
      "aiinstructions": "", "canpopulatecdcf": true, "isprovided": false,
      "mode": "teacher" }
}}
<div class="path-mod-gestionprojet path-mod-gestionprojet-fast" data-cmid="{{cmid}}" data-mode="{{mode}}" data-sesskey="{{sesskey}}">

    <div class="fast-toolbar mb-3">
        {{#canpopulatecdcf}}
            <button type="button" class="btn btn-outline-primary" data-action="populate-cdcf">
                {{#str}}fast:populate_from_cdcf, mod_gestionprojet{{/str}}
            </button>
        {{/canpopulatecdcf}}
    </div>

    <div class="fast-form" id="fast-form-{{cmid}}"></div>

    <div class="fast-diagram-card card mt-4">
        <div class="card-header">
            <strong>{{#str}}fast:diagram_title, mod_gestionprojet{{/str}}</strong>
            <div class="text-muted small">{{#str}}fast:diagram_subtitle, mod_gestionprojet{{/str}}</div>
        </div>
        <div class="card-body">
            <div class="fast-diagram" id="fast-diagram-{{cmid}}"></div>
        </div>
    </div>

    {{#isteacher}}
    <div class="fast-ai-instructions mt-4">
        <label for="fast-ai-{{cmid}}" class="font-weight-bold">
            {{#str}}fast:ai_instructions_label, mod_gestionprojet{{/str}}
        </label>
        <textarea id="fast-ai-{{cmid}}" name="ai_instructions" rows="6" class="form-control">{{aiinstructions}}</textarea>
        <small class="form-text text-muted">{{#str}}fast:ai_instructions_help, mod_gestionprojet{{/str}}</small>
    </div>
    {{/isteacher}}

    <input type="hidden" id="fast-data-{{cmid}}" name="data_json" value="{{datajson}}">
</div>
```

- [ ] **Step 9.2: Validate render**

Run: `php admin/cli/purge_caches.php`

- [ ] **Step 9.3: Commit**

```bash
git add gestionprojet/templates/step9_form.mustache
git commit -m "feat(templates): step9_form mustache template"
```

---

## Task 10: AMD module `fast_diagram.js` — DOM-safe SVG renderer

**Files:**
- Create: `gestionprojet/amd/src/fast_diagram.js`

The renderer constructs the SVG using `createElementNS` + `setAttribute` + `textContent` — never string concatenation with user data. This avoids XSS even if user inputs contain HTML metacharacters.

- [ ] **Step 10.1: Create the module**

```javascript
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
 * FAST diagram SVG renderer.
 * Layout: FP -> FT -> (sub-FT) -> ST, left-to-right tree.
 *
 * @module     mod_gestionprojet/fast_diagram
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var XHTML_NS = 'http://www.w3.org/1999/xhtml';

    var FP_WIDTH = 160, FP_HEIGHT = 38;
    var FT_WIDTH = 200, FT_HEIGHT = 30;
    var SOUS_FT_WIDTH = 180, SOUS_FT_HEIGHT = 28;
    var ST_WIDTH = 180, ST_HEIGHT = 28;
    var H_GAP = 60, V_GAP = 5;
    var LINE_COLOR = '#94a3b8';

    var COLORS = {
        fpFill: '#dbeafe', fpStroke: '#3b82f6',
        ftFill: '#dcfce7', ftStroke: '#22c55e',
        sfFill: '#f0fdf4', sfStroke: '#86efac',
        stFill: '#ffedd5', stStroke: '#f97316'
    };

    function computeLayout(data) {
        var nodes = [], lines = [];
        var fonctions = (data && data.fonctions) || [];
        var fps = (data && data.fonctionsPrincipales) || [];
        var fpLabel = fps.map(function(fp) { return fp.description; }).filter(Boolean).join(' / ') || 'FP';

        if (fonctions.length === 0) {
            return { nodes: [], lines: [], width: 400, height: 200 };
        }

        var hasSousFonctions = fonctions.some(function(ft) { return ft.sousFonctions && ft.sousFonctions.length > 0; });

        var fpX = 30;
        var ftX = fpX + FP_WIDTH + H_GAP;
        var sfX = ftX + FT_WIDTH + H_GAP;
        var stX = hasSousFonctions ? sfX + SOUS_FT_WIDTH + H_GAP : ftX + FT_WIDTH + H_GAP;

        var totalRows = 0;
        var ftRowStarts = [];
        fonctions.forEach(function(ft) {
            ftRowStarts.push(totalRows);
            totalRows += (ft.sousFonctions && ft.sousFonctions.length > 0) ? ft.sousFonctions.length : 1;
        });

        var rowHeight = Math.max(FT_HEIGHT, SOUS_FT_HEIGHT, ST_HEIGHT) + V_GAP;
        var topPadding = 20;
        var fpY = topPadding;

        nodes.push({
            x: fpX, y: fpY, w: FP_WIDTH, h: FP_HEIGHT,
            label: fpLabel, fill: COLORS.fpFill, stroke: COLORS.fpStroke
        });

        var fpCenterY = fpY + FP_HEIGHT / 2;
        var fpRight = fpX + FP_WIDTH;

        fonctions.forEach(function(ft, ftIdx) {
            var rowStart = ftRowStarts[ftIdx];
            var subCount = (ft.sousFonctions && ft.sousFonctions.length) || 0;
            var rowCount = subCount > 0 ? subCount : 1;
            var groupTopY = topPadding + rowStart * rowHeight;
            var groupCenterY = groupTopY + (rowCount * rowHeight) / 2 - FT_HEIGHT / 2;
            var ftY = groupCenterY;
            var ftCenterY = ftY + FT_HEIGHT / 2;

            nodes.push({
                x: ftX, y: ftY, w: FT_WIDTH, h: FT_HEIGHT,
                label: ft.description || ('FT' + (ftIdx + 1)),
                fill: COLORS.ftFill, stroke: COLORS.ftStroke
            });

            var midX1 = fpRight + H_GAP / 2;
            lines.push({ x1: fpRight, y1: fpCenterY, x2: midX1, y2: fpCenterY });
            lines.push({ x1: midX1, y1: fpCenterY, x2: midX1, y2: ftCenterY });
            lines.push({ x1: midX1, y1: ftCenterY, x2: ftX, y2: ftCenterY });

            var ftRight = ftX + FT_WIDTH;

            if (subCount > 0) {
                ft.sousFonctions.forEach(function(sf, sfIdx) {
                    var sfRowY = topPadding + (rowStart + sfIdx) * rowHeight;
                    var sfY = sfRowY + (rowHeight - SOUS_FT_HEIGHT) / 2;
                    var sfCenterY = sfY + SOUS_FT_HEIGHT / 2;

                    nodes.push({
                        x: sfX, y: sfY, w: SOUS_FT_WIDTH, h: SOUS_FT_HEIGHT,
                        label: sf.description || ('FT' + (ftIdx + 1) + '.' + (sfIdx + 1)),
                        fill: COLORS.sfFill, stroke: COLORS.sfStroke
                    });

                    var midX2 = ftRight + H_GAP / 2;
                    lines.push({ x1: ftRight, y1: ftCenterY, x2: midX2, y2: ftCenterY });
                    lines.push({ x1: midX2, y1: ftCenterY, x2: midX2, y2: sfCenterY });
                    lines.push({ x1: midX2, y1: sfCenterY, x2: sfX, y2: sfCenterY });

                    if (sf.solution) {
                        var sfRight = sfX + SOUS_FT_WIDTH;
                        var stY = sfY + (SOUS_FT_HEIGHT - ST_HEIGHT) / 2;
                        var stCenterY = stY + ST_HEIGHT / 2;
                        nodes.push({
                            x: stX, y: stY, w: ST_WIDTH, h: ST_HEIGHT,
                            label: sf.solution,
                            fill: COLORS.stFill, stroke: COLORS.stStroke
                        });
                        lines.push({ x1: sfRight, y1: sfCenterY, x2: stX, y2: stCenterY });
                    }
                });
            } else if (ft.solution) {
                var stY2 = ftY + (FT_HEIGHT - ST_HEIGHT) / 2;
                var stCenterY2 = stY2 + ST_HEIGHT / 2;
                nodes.push({
                    x: stX, y: stY2, w: ST_WIDTH, h: ST_HEIGHT,
                    label: ft.solution,
                    fill: COLORS.stFill, stroke: COLORS.stStroke
                });
                lines.push({ x1: ftRight, y1: ftCenterY, x2: stX, y2: stCenterY2 });
            }
        });

        var maxX = 0, maxY = 0;
        nodes.forEach(function(n) {
            if (n.x + n.w > maxX) { maxX = n.x + n.w; }
            if (n.y + n.h > maxY) { maxY = n.y + n.h; }
        });

        return { nodes: nodes, lines: lines, width: maxX + 40, height: maxY + 40 };
    }

    function buildSvg(layout) {
        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + layout.width + ' ' + layout.height);
        svg.setAttribute('class', 'fast-svg');
        svg.setAttribute('preserveAspectRatio', 'xMinYMin meet');
        svg.style.minHeight = Math.min(layout.height, 600) + 'px';

        layout.lines.forEach(function(l) {
            var line = document.createElementNS(SVG_NS, 'line');
            line.setAttribute('x1', l.x1);
            line.setAttribute('y1', l.y1);
            line.setAttribute('x2', l.x2);
            line.setAttribute('y2', l.y2);
            line.setAttribute('stroke', LINE_COLOR);
            line.setAttribute('stroke-width', '1.5');
            svg.appendChild(line);
        });

        layout.nodes.forEach(function(n) {
            var group = document.createElementNS(SVG_NS, 'g');

            var rect = document.createElementNS(SVG_NS, 'rect');
            rect.setAttribute('x', n.x);
            rect.setAttribute('y', n.y);
            rect.setAttribute('width', n.w);
            rect.setAttribute('height', n.h);
            rect.setAttribute('rx', '6');
            rect.setAttribute('fill', n.fill);
            rect.setAttribute('stroke', n.stroke);
            rect.setAttribute('stroke-width', '1.5');
            group.appendChild(rect);

            var fo = document.createElementNS(SVG_NS, 'foreignObject');
            fo.setAttribute('x', n.x + 6);
            fo.setAttribute('y', n.y + 4);
            fo.setAttribute('width', n.w - 12);
            fo.setAttribute('height', n.h - 8);

            var div = document.createElementNS(XHTML_NS, 'div');
            div.setAttribute('class', 'fast-node-label');
            div.textContent = n.label;
            fo.appendChild(div);

            group.appendChild(fo);
            svg.appendChild(group);
        });

        return svg;
    }

    function buildEmpty(text) {
        var div = document.createElement('div');
        div.setAttribute('class', 'fast-diagram-empty');
        div.textContent = text || 'FAST';
        return div;
    }

    function render(containerId, data) {
        var container = document.getElementById(containerId);
        if (!container) { return; }

        var fonctions = (data && data.fonctions) || [];
        if (fonctions.length === 0) {
            container.replaceChildren(buildEmpty('FAST'));
            return;
        }

        var layout = computeLayout(data);
        container.replaceChildren(buildSvg(layout));
    }

    return {
        render: render,
        computeLayout: computeLayout
    };
});
```

- [ ] **Step 10.2: Build AMD**

Run: `cd gestionprojet && grunt amd`
Expected: `amd/build/fast_diagram.min.js` produced.

- [ ] **Step 10.3: Commit**

```bash
git add gestionprojet/amd/src/fast_diagram.js gestionprojet/amd/build/fast_diagram.min.js
git commit -m "feat(amd): fast_diagram DOM-safe SVG renderer"
```

---

## Task 11: AMD module `fast_editor.js` — DOM-safe form CRUD + autosave

**Files:**
- Create: `gestionprojet/amd/src/fast_editor.js`

The form is built using `createElement` + `textContent`/`value`. User-controlled strings never reach an HTML parser — they go into `value` (auto-escaped by the browser) or `textContent`.

- [ ] **Step 11.1: Create the module**

```javascript
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
 * FAST editor — form CRUD, autosave bridge, diagram event emitter.
 *
 * @module     mod_gestionprojet/fast_editor
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/notification', 'core/str',
        'mod_gestionprojet/fast_diagram'],
function($, Notification, Str, FastDiagram) {

    var STEP = 9;

    function emptyData() {
        return {
            fonctionsPrincipales: [],
            fonctions: [],
            populatedFromCdcf: false
        };
    }

    function nextId(items) {
        var max = 0;
        items.forEach(function(it) { if (it.id > max) { max = it.id; } });
        return max + 1;
    }

    // DOM builder helpers — never string-concat user data.

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function(k) {
                node.setAttribute(k, attrs[k]);
            });
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function input(value, dataField, placeholder) {
        var i = el('input', {
            type: 'text',
            'class': 'form-control',
            'data-field': dataField,
            placeholder: placeholder || ''
        });
        i.value = value || '';
        return i;
    }

    function btn(action, label, classes) {
        return el('button', {
            type: 'button',
            'class': 'btn btn-sm ' + (classes || ''),
            'data-action': action
        }, label);
    }

    function badge(text, classes) {
        return el('span', { 'class': 'badge ' + (classes || '') }, text);
    }

    function buildFt(ft, idx, strings) {
        var ftWrap = el('div', { 'class': 'fast-ft', 'data-ft-id': ft.id });
        var row = el('div', { 'class': 'd-flex align-items-start gap-2' });

        row.appendChild(badge('FT' + (idx + 1), 'badge-success'));

        var middle = el('div', { 'class': 'flex-grow-1' });
        var descInput = input(ft.description, 'ft-description', strings.ftPlaceholder);
        descInput.classList.add('mb-2');
        middle.appendChild(descInput);

        var hasSubs = ft.sousFonctions && ft.sousFonctions.length > 0;
        if (!hasSubs) {
            middle.appendChild(input(ft.solution, 'ft-solution', strings.solPlaceholder));
        }
        row.appendChild(middle);

        if (!hasSubs) {
            row.appendChild(btn('split', strings.split, 'btn-outline-info'));
        }
        row.appendChild(btn('remove-ft', '×', 'btn-outline-danger'));

        ftWrap.appendChild(row);

        if (hasSubs) {
            var sfList = el('div', { 'class': 'fast-sf-list ml-4 mt-2' });
            ft.sousFonctions.forEach(function(sf, sfIdx) {
                var sfWrap = el('div', { 'class': 'fast-sf p-2 mb-2 bg-light rounded', 'data-sf-id': sf.id });
                var sfRow = el('div', { 'class': 'd-flex align-items-start gap-2' });

                sfRow.appendChild(badge('FT' + (idx + 1) + '.' + (sfIdx + 1), 'badge-light'));

                var sfMid = el('div', { 'class': 'flex-grow-1' });
                var sfDesc = input(sf.description, 'sf-description', strings.sfPlaceholder);
                sfDesc.classList.add('mb-1');
                sfMid.appendChild(sfDesc);
                sfMid.appendChild(input(sf.solution, 'sf-solution', strings.solPlaceholder));
                sfRow.appendChild(sfMid);

                sfRow.appendChild(btn('remove-sf', '×', 'btn-outline-danger'));
                sfWrap.appendChild(sfRow);
                sfList.appendChild(sfWrap);
            });
            sfList.appendChild(btn('add-sf', '+ ' + strings.addSub, 'btn-outline-success'));
            ftWrap.appendChild(sfList);
        }

        return ftWrap;
    }

    function buildForm(data, strings) {
        var frag = document.createDocumentFragment();
        var list = el('div', { 'class': 'fast-fonctions' });

        if (!data.fonctions || data.fonctions.length === 0) {
            list.appendChild(el('p', { 'class': 'text-muted text-center py-3' }, strings.placeholder));
        } else {
            data.fonctions.forEach(function(ft, idx) {
                list.appendChild(buildFt(ft, idx, strings));
            });
        }
        frag.appendChild(list);

        var addBtn = btn('add-ft', '+ ' + strings.addFn, 'btn-outline-success mt-2');
        frag.appendChild(addBtn);
        return frag;
    }

    function init(opts) {
        var cmid = opts.cmid;
        var mode = opts.mode;
        var sesskey = opts.sesskey;
        var groupid = opts.groupid || 0;

        var formContainer = document.getElementById('fast-form-' + cmid);
        var dataInput = document.getElementById('fast-data-' + cmid);
        var diagramId = 'fast-diagram-' + cmid;

        if (!formContainer || !dataInput) { return; }

        var stringRequests = [
            { key: 'fast:placeholder', component: 'mod_gestionprojet' },
            { key: 'fast:ft_description_placeholder', component: 'mod_gestionprojet' },
            { key: 'fast:sf_description_placeholder', component: 'mod_gestionprojet' },
            { key: 'fast:solution_placeholder', component: 'mod_gestionprojet' },
            { key: 'fast:add_function', component: 'mod_gestionprojet' },
            { key: 'fast:add_subfunction', component: 'mod_gestionprojet' },
            { key: 'fast:split', component: 'mod_gestionprojet' }
        ];

        Str.get_strings(stringRequests).then(function(loaded) {
            var strings = {
                placeholder: loaded[0],
                ftPlaceholder: loaded[1],
                sfPlaceholder: loaded[2],
                solPlaceholder: loaded[3],
                addFn: loaded[4],
                addSub: loaded[5],
                split: loaded[6]
            };
            startEditor(strings);
            return null;
        }).catch(Notification.exception);

        function startEditor(strings) {
            var data;
            try {
                data = JSON.parse(dataInput.value || '{}');
                if (!data.fonctions) { data = emptyData(); }
            } catch (e) {
                data = emptyData();
            }

            function rerender() {
                formContainer.replaceChildren(buildForm(data, strings));
                FastDiagram.render(diagramId, data);
                dataInput.value = JSON.stringify(data);
            }

            function autosave() {
                var payload = { data_json: JSON.stringify(data) };
                if (mode === 'teacher') {
                    var aiInput = document.getElementById('fast-ai-' + cmid);
                    if (aiInput) {
                        payload.ai_instructions = aiInput.value;
                    }
                }
                $.post(M.cfg.wwwroot + '/mod/gestionprojet/ajax/autosave.php', {
                    cmid: cmid,
                    step: STEP,
                    mode: mode === 'teacher' ? 'teacher' : '',
                    groupid: groupid,
                    sesskey: sesskey,
                    data: JSON.stringify(payload)
                });
            }

            var saveTimer = null;
            function scheduleSave() {
                if (saveTimer) { clearTimeout(saveTimer); }
                saveTimer = setTimeout(autosave, 30000);
            }

            $(formContainer).on('input', 'input', function(e) {
                var $input = $(e.target);
                var field = $input.data('field');
                var $ft = $input.closest('.fast-ft');
                var $sf = $input.closest('.fast-sf');
                var ftId = parseInt($ft.attr('data-ft-id'), 10);
                var ft = data.fonctions.find(function(f) { return f.id === ftId; });
                if (!ft) { return; }
                if (field === 'ft-description') { ft.description = $input.val(); }
                else if (field === 'ft-solution') { ft.solution = $input.val(); }
                else if ($sf.length) {
                    var sfId = parseInt($sf.attr('data-sf-id'), 10);
                    var sf = (ft.sousFonctions || []).find(function(s) { return s.id === sfId; });
                    if (!sf) { return; }
                    if (field === 'sf-description') { sf.description = $input.val(); }
                    else if (field === 'sf-solution') { sf.solution = $input.val(); }
                }
                FastDiagram.render(diagramId, data);
                dataInput.value = JSON.stringify(data);
                scheduleSave();
            });

            $(formContainer).on('click', '[data-action]', function(e) {
                var action = $(e.currentTarget).data('action');
                var $ft = $(e.currentTarget).closest('.fast-ft');
                var ftId = parseInt($ft.attr('data-ft-id'), 10);
                var ft = data.fonctions.find(function(f) { return f.id === ftId; });

                if (action === 'add-ft') {
                    data.fonctions.push({
                        id: nextId(data.fonctions), description: '', solution: '', sousFonctions: []
                    });
                } else if (action === 'remove-ft') {
                    data.fonctions = data.fonctions.filter(function(f) { return f.id !== ftId; });
                } else if (action === 'split' && ft) {
                    ft.solution = '';
                    ft.sousFonctions = [
                        { id: 1, description: '', solution: '' },
                        { id: 2, description: '', solution: '' }
                    ];
                } else if (action === 'add-sf' && ft) {
                    ft.sousFonctions.push({
                        id: nextId(ft.sousFonctions), description: '', solution: ''
                    });
                } else if (action === 'remove-sf' && ft) {
                    var $sf = $(e.currentTarget).closest('.fast-sf');
                    var sfId = parseInt($sf.attr('data-sf-id'), 10);
                    ft.sousFonctions = ft.sousFonctions.filter(function(s) { return s.id !== sfId; });
                    if (ft.sousFonctions.length === 0) { ft.solution = ''; }
                }
                rerender();
                scheduleSave();
            });

            $('[data-action="populate-cdcf"]').on('click', function() {
                $.getJSON(M.cfg.wwwroot + '/mod/gestionprojet/ajax/fast_populate_cdcf.php', {
                    cmid: cmid, sesskey: sesskey
                }, function(resp) {
                    if (!resp || !resp.success) { return; }
                    var fps = resp.fonctionsPrincipales || [];
                    var fts = resp.fonctionsService || [];
                    data.fonctionsPrincipales = fps;
                    data.fonctions = fts.map(function(fs, idx) {
                        return {
                            id: idx + 1,
                            description: fs.description,
                            originCdcf: 'FS',
                            originIndex: fs.id,
                            solution: '',
                            sousFonctions: []
                        };
                    });
                    data.populatedFromCdcf = true;
                    rerender();
                    autosave();
                });
            });

            rerender();
        }
    }

    return { init: init };
});
```

- [ ] **Step 11.2: Build AMD**

Run: `cd gestionprojet && grunt amd`
Expected: `amd/build/fast_editor.min.js` produced.

- [ ] **Step 11.3: Commit**

```bash
git add gestionprojet/amd/src/fast_editor.js gestionprojet/amd/build/fast_editor.min.js
git commit -m "feat(amd): fast_editor DOM-safe form + autosave bridge"
```

---

## Task 12: AJAX endpoint `fast_populate_cdcf.php`

**Files:**
- Create: `gestionprojet/ajax/fast_populate_cdcf.php`

- [ ] **Step 12.1: Create the endpoint**

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
 * AJAX endpoint — return CDCF teacher's FS data for FAST pre-fill.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

header('Content-Type: application/json');

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:configureteacherpages', $context);

$cdcfteacher = $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]);

$fonctionsprincipales = [];
$fonctionsservice = [];

if ($cdcfteacher) {
    if (!empty($cdcfteacher->fp)) {
        $fonctionsprincipales[] = ['id' => 1, 'description' => $cdcfteacher->fp];
    }

    if (!empty($cdcfteacher->interacteurs_data)) {
        $interacteurs = json_decode($cdcfteacher->interacteurs_data, true);
        if (is_array($interacteurs)) {
            $idcounter = 1;
            foreach ($interacteurs as $interacteur) {
                $intname = $interacteur['nom'] ?? '';
                $fcs = $interacteur['fcs'] ?? $interacteur['fonctions'] ?? [];
                if (is_array($fcs)) {
                    foreach ($fcs as $fc) {
                        $desc = is_string($fc) ? $fc : ($fc['description'] ?? $fc['nom'] ?? '');
                        if (!empty($desc)) {
                            $fonctionsservice[] = [
                                'id' => $idcounter++,
                                'description' => $desc,
                                'interactor' => $intname,
                            ];
                        }
                    }
                }
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'fonctionsPrincipales' => $fonctionsprincipales,
    'fonctionsService' => $fonctionsservice,
]);
```

- [ ] **Step 12.2: Verify syntax**

Run: `php -l gestionprojet/ajax/fast_populate_cdcf.php`

- [ ] **Step 12.3: Manual smoke test**

On step9_teacher, click "Pré-remplir depuis CDCF". Check the network tab : `200 OK` + JSON `{success: true, fonctionsService: [...]}`.

- [ ] **Step 12.4: Commit**

```bash
git add gestionprojet/ajax/fast_populate_cdcf.php
git commit -m "feat(ajax): fast_populate_cdcf endpoint"
```

---

## Task 13: Extend `ajax/autosave.php` with step 9

**Files:**
- Modify: `gestionprojet/ajax/autosave.php`

- [ ] **Step 13.1: Add step 9 to teacher tables map**

In `ajax/autosave.php`, locate the `$teachertables` array (around lines 65-75) and add:

```php
            9 => ['table' => 'gestionprojet_fast_teacher', 'fields' => ['data_json', 'ai_instructions', 'submission_date', 'deadline_date']],
```

- [ ] **Step 13.2: Add step 9 to student tables map**

Locate the equivalent student-mode block (search for `gestionprojet_cdcf` outside the `$teachertables` block). Add:

```php
            9 => ['table' => 'gestionprojet_fast', 'fields' => ['data_json']],
```

- [ ] **Step 13.3: Remove existing debug code**

Current `ajax/autosave.php` violates Moodle checklist rule 5. Remove these lines:
- `$debug_log = __DIR__ . '/../../../moodledata/temp/autosave_debug.log';`
- All `@file_put_contents($debug_log, ...)` calls
- Any `print_r` of `$_POST`

This is a focused cleanup of code touched in this task. Do not refactor anything else.

- [ ] **Step 13.4: Verify syntax**

Run: `php -l gestionprojet/ajax/autosave.php`

- [ ] **Step 13.5: Commit**

```bash
git add gestionprojet/ajax/autosave.php
git commit -m "feat(autosave): add step9 (FAST) + remove debug logging"
```

---

## Task 14: Integrate column 9 in Gantt (`pages/home.php`)

**Files:**
- Modify: `gestionprojet/pages/home.php`

- [ ] **Step 14.1: Add step 9 to teacher arrays**

Locate (line 47): `$studentsteps = [4, 5, 6, 7, 8];` → change to `[4, 5, 6, 7, 8, 9]`.

In `$teachermodels` (lines 55-61), add:

```php
        9 => $DB->get_record('gestionprojet_fast_teacher', ['gestionprojetid' => $gestionprojet->id]),
```

In `$studenttables` (lines 62-68), add:

```php
        9 => 'gestionprojet_fast',
```

- [ ] **Step 14.2: Insert column 9 in `$ganttcolumndefs`**

Replace (lines 111-119) with the column 9 inserted between 4 and 5:

```php
    $ganttcolumndefs = [
        ['stepnum' => 1, 'mergedwith' => null],
        ['stepnum' => 3, 'mergedwith' => null],
        ['stepnum' => 2, 'mergedwith' => 7],
        ['stepnum' => 4, 'mergedwith' => null],
        ['stepnum' => 9, 'mergedwith' => null],
        ['stepnum' => 5, 'mergedwith' => null],
        ['stepnum' => 8, 'mergedwith' => null],
        ['stepnum' => 6, 'mergedwith' => null],
    ];
```

- [ ] **Step 14.3: Add step 9 row 1 cell (mode provided)**

After the `else if ($stepnum === 4)` branch (around line 154), add:

```php
        } else if ($stepnum === 9) {
            // Special case: FAST row 1 cell controls step9_provided.
            $providedval = isset($gestionprojet->step9_provided) ? (int)$gestionprojet->step9_provided : 0;
            $providedenabled = ($providedval === 1);
            $providedrec = $teachermodels[9] ?? null;
            $providedcomplete = false;
            if ($providedrec && !empty($providedrec->data_json)) {
                $decoded = json_decode($providedrec->data_json, true);
                $providedcomplete = is_array($decoded) && !empty($decoded['fonctions']);
            }
            if ($providedenabled) {
                $totalconfigtargets++;
                if ($providedcomplete) {
                    $totalconfigured++;
                }
            }
            $rowdocs[] = [
                'stepnum' => 9,
                'isfilled' => true,
                'isenabled' => $providedenabled,
                'iscomplete' => $providedcomplete,
                'flag' => 'provided',
                'name' => get_string('step9', 'gestionprojet'),
                'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 9, 'mode' => 'teacher']))->out(false),
            ];
```

- [ ] **Step 14.4: Add step 9 to student-view section**

In the student section (around line 305), after the existing fetches:

```php
            $fast = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'fast');
```

In `$studentstepsraw` (lines 311-317), add `9` between `4` and `5`:

```php
                4 => ['data' => $cdcf, 'complete' => $cdcf && !empty($cdcf->produit)],
                9 => ['data' => $fast, 'complete' => (function() use ($fast) {
                    if (!$fast || empty($fast->data_json)) { return false; }
                    $d = json_decode($fast->data_json, true);
                    return is_array($d) && !empty($d['fonctions']);
                })()],
                5 => ['data' => $essai, 'complete' => $essai && !empty($essai->objectif)],
```

- [ ] **Step 14.5: Verify syntax**

Run: `php -l gestionprojet/pages/home.php`

- [ ] **Step 14.6: Manual smoke test**

Reload home page as teacher : a 9th column "Diagramme FAST" appears between CDCF and Essai. As student : step 9 appears between CDCF and Essai.

- [ ] **Step 14.7: Commit**

```bash
git add gestionprojet/pages/home.php
git commit -m "feat(home): add FAST column to Gantt + student navigation"
```

---

## Task 15: Toggle endpoint support for `step9_provided`

**Files:**
- Modify: existing toggle AJAX endpoint (path TBD by inspection)

- [ ] **Step 15.1: Locate the endpoint**

Run: `grep -rn "step4_provided\|toggle_step\|toggle.*provided" gestionprojet/ajax/ gestionprojet/amd/src/ | head -10`

- [ ] **Step 15.2: Whitelist `step9_provided`**

Add `'step9_provided'` to the allowed flag values, mirroring `'step4_provided'`. Exact change depends on the existing code shape (a switch, an `in_array`, or a regex check).

- [ ] **Step 15.3: Manual smoke test**

In Gantt as teacher, click ligne 1 of FAST column to toggle it. Verify in DB that `gestionprojet.step9_provided` flips between 0 and 1.

- [ ] **Step 15.4: Commit**

```bash
git add gestionprojet/ajax/
git commit -m "feat(ajax): support step9_provided toggle"
```

---

## Task 16: Styles in `styles.css`

**Files:**
- Modify: `gestionprojet/styles.css`

- [ ] **Step 16.1: Append namespaced styles**

```css
/* ============================================================
   Step 9 — FAST diagram
   ============================================================ */

.path-mod-gestionprojet-fast .fast-toolbar {
    display: flex;
    gap: 0.5rem;
}

.path-mod-gestionprojet-fast .fast-form {
    margin-bottom: 1rem;
}

.path-mod-gestionprojet-fast .fast-ft {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.75rem;
    margin-bottom: 0.75rem;
    background: #fff;
}

.path-mod-gestionprojet-fast .fast-sf-list {
    border-left: 2px solid #c6f6d5;
    padding-left: 0.75rem;
}

.path-mod-gestionprojet-fast .fast-sf {
    background: #f0fdf4;
}

.path-mod-gestionprojet-fast .fast-diagram {
    overflow-x: auto;
    padding: 0.5rem;
}

.path-mod-gestionprojet-fast .fast-diagram-empty {
    text-align: center;
    color: #94a3b8;
    padding: 3rem 0;
    font-style: italic;
}

.path-mod-gestionprojet-fast .fast-svg {
    width: 100%;
    height: auto;
    min-width: 600px;
    display: block;
}

.path-mod-gestionprojet-fast .fast-node-label {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-size: 9px;
    font-weight: 500;
    line-height: 1.2;
    color: #1e293b;
    overflow: hidden;
}

.path-mod-gestionprojet-fast .fast-ai-instructions textarea {
    font-family: monospace;
    font-size: 0.875rem;
}
```

- [ ] **Step 16.2: Purge caches**

Run: `php admin/cli/purge_caches.php`

- [ ] **Step 16.3: Commit**

```bash
git add gestionprojet/styles.css
git commit -m "style(fast): namespaced CSS for step 9"
```

---

## Task 17: AI evaluation — case 9

**Files:**
- Modify: `gestionprojet/classes/ai/evaluator.php` (path/name to confirm)

- [ ] **Step 17.1: Locate the evaluator**

Run: `grep -rln "ai_evaluations\|case 4\|case 5" gestionprojet/classes/ | head`

- [ ] **Step 17.2: Add case 9**

Add a new switch arm. Read case 4 first and mirror its structure :

```php
case 9:
    $student = $DB->get_record('gestionprojet_fast', [
        'gestionprojetid' => $gestionprojet->id,
        'groupid' => $groupid,
        'userid' => $userid,
    ]);
    $teacher = $DB->get_record('gestionprojet_fast_teacher', ['gestionprojetid' => $gestionprojet->id]);
    if (!$student || empty($student->data_json)) {
        return ['success' => false, 'message' => get_string('fast:no_data_to_evaluate', 'mod_gestionprojet')];
    }
    $studenttext = gestionprojet_fast_to_text($student->data_json);
    $teachertext = $teacher ? gestionprojet_fast_to_text($teacher->data_json) : '';
    $aiinstructions = $teacher && !empty($teacher->ai_instructions) ? $teacher->ai_instructions : '';

    $prompt  = "Tu évalues un diagramme FAST (analyse fonctionnelle) d'élève.\n\n";
    if (!empty($aiinstructions)) {
        $prompt .= "Consignes du correcteur :\n" . $aiinstructions . "\n\n";
    }
    if (!empty($teachertext)) {
        $prompt .= "Référence attendue (modèle de correction) :\n" . $teachertext . "\n\n";
    }
    $prompt .= "Production de l'élève :\n" . $studenttext . "\n\n";
    $prompt .= "Évalue le travail de l'élève en attribuant une note sur 20 et un retour structuré (points forts, points à améliorer).";

    // Pass $prompt to the existing AI client (same pattern as steps 4-8).
    // Persist result into gestionprojet_ai_evaluations with step=9.
    break;
```

The exact dispatch pattern (how `$prompt` is sent to the AI provider and how the result is stored) must follow case 4's implementation. Read case 4 first and mirror.

- [ ] **Step 17.3: Verify syntax**

Run: `php -l <evaluator-file>`

- [ ] **Step 17.4: Commit**

```bash
git add gestionprojet/classes/
git commit -m "feat(ai): step9 (FAST) evaluation"
```

---

## Task 18: Privacy provider

**Files:**
- Modify: `gestionprojet/classes/privacy/provider.php`
- Modify: `gestionprojet/lang/en/gestionprojet.php`
- Modify: `gestionprojet/lang/fr/gestionprojet.php`

- [ ] **Step 18.1: Declare `gestionprojet_fast` in metadata**

Inside `get_metadata()`, mirror the `gestionprojet_cdcf` block :

```php
$collection->add_database_table(
    'gestionprojet_fast',
    [
        'gestionprojetid' => 'privacy:metadata:gestionprojet_fast:gestionprojetid',
        'userid' => 'privacy:metadata:gestionprojet_fast:userid',
        'groupid' => 'privacy:metadata:gestionprojet_fast:groupid',
        'data_json' => 'privacy:metadata:gestionprojet_fast:data_json',
        'status' => 'privacy:metadata:gestionprojet_fast:status',
        'grade' => 'privacy:metadata:gestionprojet_fast:grade',
        'feedback' => 'privacy:metadata:gestionprojet_fast:feedback',
        'timesubmitted' => 'privacy:metadata:gestionprojet_fast:timesubmitted',
        'timecreated' => 'privacy:metadata:gestionprojet_fast:timecreated',
        'timemodified' => 'privacy:metadata:gestionprojet_fast:timemodified',
    ],
    'privacy:metadata:gestionprojet_fast'
);
```

- [ ] **Step 18.2: Add corresponding strings**

EN (`lang/en/gestionprojet.php`):

```php
$string['privacy:metadata:gestionprojet_fast'] = 'Student FAST diagram submissions.';
$string['privacy:metadata:gestionprojet_fast:gestionprojetid'] = 'Activity instance ID.';
$string['privacy:metadata:gestionprojet_fast:userid'] = 'User ID (individual mode).';
$string['privacy:metadata:gestionprojet_fast:groupid'] = 'Group ID (group mode).';
$string['privacy:metadata:gestionprojet_fast:data_json'] = 'FAST diagram data (JSON).';
$string['privacy:metadata:gestionprojet_fast:status'] = 'Submission status.';
$string['privacy:metadata:gestionprojet_fast:grade'] = 'Numerical grade.';
$string['privacy:metadata:gestionprojet_fast:feedback'] = 'Teacher feedback.';
$string['privacy:metadata:gestionprojet_fast:timesubmitted'] = 'Submission time.';
$string['privacy:metadata:gestionprojet_fast:timecreated'] = 'Creation time.';
$string['privacy:metadata:gestionprojet_fast:timemodified'] = 'Last modification time.';
```

FR (`lang/fr/gestionprojet.php`):

```php
$string['privacy:metadata:gestionprojet_fast'] = 'Productions élèves de diagrammes FAST.';
$string['privacy:metadata:gestionprojet_fast:gestionprojetid'] = 'ID de l\'instance.';
$string['privacy:metadata:gestionprojet_fast:userid'] = 'ID utilisateur (mode individuel).';
$string['privacy:metadata:gestionprojet_fast:groupid'] = 'ID du groupe (mode groupe).';
$string['privacy:metadata:gestionprojet_fast:data_json'] = 'Données du diagramme FAST (JSON).';
$string['privacy:metadata:gestionprojet_fast:status'] = 'Statut de soumission.';
$string['privacy:metadata:gestionprojet_fast:grade'] = 'Note.';
$string['privacy:metadata:gestionprojet_fast:feedback'] = 'Retour de l\'enseignant.';
$string['privacy:metadata:gestionprojet_fast:timesubmitted'] = 'Date de soumission.';
$string['privacy:metadata:gestionprojet_fast:timecreated'] = 'Date de création.';
$string['privacy:metadata:gestionprojet_fast:timemodified'] = 'Dernière modification.';
```

- [ ] **Step 18.3: Implement `export_user_data` and `delete_data_for_user`**

Mirror the existing implementation for `gestionprojet_cdcf` in the same provider file. Add a parallel block for `gestionprojet_fast` (same structure, same loops).

- [ ] **Step 18.4: Verify syntax**

Run: `php -l gestionprojet/classes/privacy/provider.php`

- [ ] **Step 18.5: Commit**

```bash
git add gestionprojet/classes/privacy/provider.php gestionprojet/lang/
git commit -m "feat(privacy): declare gestionprojet_fast in privacy provider"
```

---

## Task 19: Grading interface, report.php, export_pdf.php

**Files:**
- Modify: `gestionprojet/grading.php`
- Modify: `gestionprojet/report.php`
- Modify: `gestionprojet/export_pdf.php`

- [ ] **Step 19.1: `grading.php`**

Run: `grep -n "step ==\|step =\|range(4" gestionprojet/grading.php`

Add step 9 to the gradable steps list. For rendering, use the same logic as step 4 — load `gestionprojet_fast` record, render diagram (read-only) + grading form.

- [ ] **Step 19.2: `report.php`**

Same pattern: add step 9 to the steps array driving the per-step progress display.

- [ ] **Step 19.3: `export_pdf.php`**

Render the FAST as hierarchical text using `gestionprojet_fast_to_text` :

```php
$fast = $DB->get_record('gestionprojet_fast', [
    'gestionprojetid' => $gestionprojet->id,
    'groupid' => $groupid,
    'userid' => $userid,
]);
if ($fast && !empty($fast->data_json)) {
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->Cell(0, 10, get_string('step9', 'gestionprojet'), 0, 1);
    $pdf->SetFont('Courier', '', 9);
    $pdf->MultiCell(0, 5, gestionprojet_fast_to_text($fast->data_json));
}
```

Adapt method names (`AddPage`, `Cell`, `MultiCell`) to the actual PDF library used.

- [ ] **Step 19.4: Verify syntax**

```bash
php -l gestionprojet/grading.php
php -l gestionprojet/report.php
php -l gestionprojet/export_pdf.php
```

- [ ] **Step 19.5: Commit**

```bash
git add gestionprojet/grading.php gestionprojet/report.php gestionprojet/export_pdf.php
git commit -m "feat(integration): step9 in grading, report, PDF export"
```

---

## Task 20: Step icon

**Files:**
- Create: `gestionprojet/pix/icon_step9.svg`
- Modify: `gestionprojet/classes/output/icon.php`

- [ ] **Step 20.1: Add the Lucide GitFork icon SVG**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <circle cx="12" cy="18" r="3"/>
  <circle cx="6" cy="6" r="3"/>
  <circle cx="18" cy="6" r="3"/>
  <path d="M18 9v2c0 .6-.4 1-1 1H7c-.6 0-1-.4-1-1V9"/>
  <path d="M12 12v3"/>
</svg>
```

- [ ] **Step 20.2: Register in `classes/output/icon.php`**

Run: `grep -n "step1\|step8\|render_step" gestionprojet/classes/output/icon.php | head`

Add `9 => 'icon_step9'` to the step icon map.

- [ ] **Step 20.3: Manual smoke test**

Purge caches, reload Gantt — the step 9 column header displays the GitFork icon.

- [ ] **Step 20.4: Commit**

```bash
git add gestionprojet/pix/icon_step9.svg gestionprojet/classes/output/icon.php
git commit -m "feat(icon): add step9 GitFork icon"
```

---

## Task 21: End-to-end manual test + release

**Files:** none (manual verification + release artifacts)

- [ ] **Step 21.1: Activate phase**

In Moodle, edit the activity → check `enable_step9` → save. Verify the FAST column appears.

- [ ] **Step 21.2: Teacher correction model — empty start**

Open ligne 2 of the FAST column → step9_teacher loads. Add a couple of FT manually. Verify the diagram redraws live.

- [ ] **Step 21.3: Teacher correction model — populate from CDCF**

Pre-fill CDCF teacher (step 4). Return to step9_teacher → click "Pré-remplir depuis le CDCF". Verify FT list and diagram populate from FS.

- [ ] **Step 21.4: AI instructions**

Fill in `ai_instructions`. Wait 30 s → verify autosave (network panel POST 200 + reload preserves text).

- [ ] **Step 21.5: Provided mode**

Toggle ligne 1 (provided) on. Open step9.php as student → verify the diagram is pre-populated from teacher's content.

- [ ] **Step 21.6: Provided mode off**

Toggle ligne 1 off. Re-open step9.php as a fresh student → verify empty start.

- [ ] **Step 21.7: Student submission**

Edit FT, add solutions, click "Soumettre". Verify `status=1` in DB.

- [ ] **Step 21.8: AI evaluation**

Run AI evaluation on the student submission. Verify a record appears in `gestionprojet_ai_evaluations` with `step=9`.

- [ ] **Step 21.9: Manual grading**

Open `grading.php` → step 9 → grade a student to 15.5/20. Verify the grade appears in the Moodle gradebook.

- [ ] **Step 21.10: Delete instance**

Delete the activity. Verify in DB that `gestionprojet_fast` and `gestionprojet_fast_teacher` rows for the deleted instance are gone.

- [ ] **Step 21.11: PHPCS**

Run: `phpcs --standard=moodle gestionprojet/`
Expected: 0 errors on new files.

- [ ] **Step 21.12: Tag release**

```bash
git tag v2.3.0
git push origin v2.3.0
```

- [ ] **Step 21.13: Build deployment ZIP**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
rm -f gestionprojet.zip
zip -r gestionprojet.zip gestionprojet/ -x "*.git*" "*.claude*" "*lessons.md" "*.DS_Store" "*backup*"
```

- [ ] **Step 21.14: Deploy via Moodle admin (manual user action)**

User uploads `gestionprojet.zip` to Moodle Admin → Plugins → Install plugins → confirms the upgrade.

---

## Self-review checklist

- ✅ **Spec coverage** — tasks 1-21 cover all spec sections : DB schema (1), lang (2, 18.2), helpers + nav + delete (3), unit tests (4), form (5), routing (6), pages (7, 8), template (9), AMD diagram (10), AMD editor (11), populate-CDCF AJAX (12), autosave AJAX (13), Gantt + student nav (14), provided toggle (15), CSS (16), AI eval (17), privacy (18), grading/report/PDF (19), icon (20), e2e + release (21).
- ✅ **No placeholders** — every code-bearing step contains the actual code or an explicit "mirror existing case 4" instruction (where reading existing code first is required).
- ✅ **Type/method consistency** — `gestionprojet_fast_to_text`, `populate-cdcf` action, `data_json` field, `step9_provided` flag, `fast` table key, `mod_gestionprojet/fast_diagram` and `mod_gestionprojet/fast_editor` AMD module names — consistent across all tasks.
- ✅ **Security** — no `innerHTML` with user data; SVG built via `createElementNS` + `setAttribute` + `textContent`; form built via `createElement` + `value` + `textContent`. No string-concat HTML.
- ✅ **Moodle checklist** — GPL headers in every new PHP file (1, 7, 8, 12), no inline JS/CSS (10, 11, 16), AMD only, English comments, `require_login`/`require_sesskey`/`require_capability` on all entry points (7, 8, 12), `delete_instance` updated (3.3), strings via lang (2, 18.2), version bump (1.5), privacy provider (18).
- ✅ **Frequent commits** — each task ends with a focused commit.

## Hors-scope

- Spec 2 — Assistant IA "Suggérer consignes IA" (transverse aux 6 phases élèves). À planifier après livraison de cette implémentation.
