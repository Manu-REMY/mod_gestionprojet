# Consigne Pattern Extension — Step 5 (essai) + Step 9 (FAST) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the v2.9.0 consigne refonte (intro_text Atto field + Reset button + AI prompt injection + identical-copy guard) from step 4 (CDCF) to steps 5 (essai) and 9 (FAST). Refactor reset/intro JS into reusable AMD modules. Release as v2.10.0.

**Architecture:** Strict mirroring of v2.9.0. Two new columns (`intro_text` on `essai_provided` + `fast_provided`), two extra entries in `reset_helper::STEP_MAP`, generalized `ai_evaluator` mapping with per-step comparator, and two factored AMD modules (`reset_button` + `intro_text_autosave`) replacing inline logic in `cdcf_bootstrap.js`.

**Tech Stack:** PHP 8.1+ / Moodle 5.0+ / XMLDB / Mustache / AMD modules (RequireJS) / PHPUnit / Atto editor / Bootstrap modal_factory.

**Reference spec:** `docs/superpowers/specs/2026-05-06-consigne-extension-step5-9-design.md`

---

## File Map

**Created:**
- `gestionprojet/amd/src/reset_button.js`
- `gestionprojet/amd/build/reset_button.min.js` (manual copy from src — grunt unavailable per commit `d13b75c`)
- `gestionprojet/amd/src/intro_text_autosave.js`
- `gestionprojet/amd/build/intro_text_autosave.min.js`
- `RELEASE_NOTES_v2.10.0.md`

**Modified:**
- `gestionprojet/db/install.xml` — add `intro_text` column to two tables
- `gestionprojet/db/upgrade.php` — add upgrade step
- `gestionprojet/version.php` — bump version + release
- `gestionprojet/classes/reset_helper.php` — add 2 STEP_MAP entries
- `gestionprojet/classes/ai_evaluator.php` — generalize step 4 block + add detect_no_modifications
- `gestionprojet/pages/step5_provided.php` — Atto editor block
- `gestionprojet/pages/step9_provided.php` — Atto editor block
- `gestionprojet/pages/step5.php` — intro display + Reset button in export-section
- `gestionprojet/pages/step9.php` — intro display + Reset section
- `gestionprojet/pages/step4.php` — call new reset_button module
- `gestionprojet/ajax/reset_to_provided.php` — extend step whitelist to {4,5,9}
- `gestionprojet/ajax/autosave.php` — add intro_text to providedtables[5] and [9]
- `gestionprojet/classes/external/autosave.php` — same
- `gestionprojet/amd/src/cdcf_bootstrap.js` — remove inline reset logic + intro autosave
- `gestionprojet/amd/build/cdcf_bootstrap.min.js` — recopy from src
- `gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php` — add intro_text to 2 nested elements
- `gestionprojet/tests/reset_helper_test.php` — add cases for steps 5 and 9
- `gestionprojet/tests/ai_prompt_builder_test.php` (or create if absent) — add cases
- `CHANGELOG.md` — add 2.10.0 entry
- Auto-memory `feature_consigne_pattern_extension.md` — mark partial resolution

---

## Branch Setup

- [ ] **Step 0.1: Create feature branch**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git checkout main
git pull
git checkout -b feat/consigne-extension-step5-9
```

- [ ] **Step 0.2: Verify clean working tree**

```bash
git status
```

Expected: `On branch feat/consigne-extension-step5-9` and either clean working tree, or only the pre-existing untracked items (`gestionprojet.zip` deleted, `RELEASE_NOTES_v2.8.0.md` untracked) — both are unrelated to this work.

---

### Task 1: Schema — add `intro_text` column to two `_provided` tables

**Files:**
- Modify: `gestionprojet/db/install.xml`
- Modify: `gestionprojet/db/upgrade.php`

- [ ] **Step 1.1: Locate the existing `essai_provided` and `fast_provided` blocks in install.xml**

```bash
grep -n "TABLE NAME=\"gestionprojet_essai_provided\"\|TABLE NAME=\"gestionprojet_fast_provided\"" gestionprojet/db/install.xml
```

Expected: two line numbers.

- [ ] **Step 1.2: Add `intro_text` field to `gestionprojet_essai_provided` in install.xml**

Add the field as the LAST entry of `<FIELDS>` block, just before the closing `</FIELDS>` and after the `timemodified` field already present. Use Edit tool to insert after the existing `timemodified` line within the `essai_provided` table:

```xml
        <FIELD NAME="intro_text" TYPE="text" NOTNULL="false" SEQUENCE="false" COMMENT="HTML intro text shown read-only to students at the top of the essai page"/>
```

(Adjust the placement if the existing layout puts `timecreated`/`timemodified` last — keep them last and put `intro_text` just before them, mirroring the `cdcf_provided` pattern.)

- [ ] **Step 1.3: Add `intro_text` field to `gestionprojet_fast_provided` in install.xml**

Same pattern, on the `fast_provided` table:

```xml
        <FIELD NAME="intro_text" TYPE="text" NOTNULL="false" SEQUENCE="false" COMMENT="HTML intro text shown read-only to students at the top of the FAST diagram page"/>
```

- [ ] **Step 1.4: Add upgrade step to `db/upgrade.php`**

Open `gestionprojet/db/upgrade.php` and append a new `if ($oldversion < $newversion)` block at the bottom of `xmldb_gestionprojet_upgrade()`. The version bumps to `2026050900`:

```php
    // 2.10.0 — extend consigne pattern (intro_text) to step 5 (essai) and step 9 (FAST).
    if ($oldversion < 2026050900) {
        foreach (['gestionprojet_essai_provided', 'gestionprojet_fast_provided'] as $tablename) {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field('intro_text', XMLDB_TYPE_TEXT, null, null, null, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2026050900, 'gestionprojet');
    }
```

Place this block after the most recent existing upgrade step. The version field on `version.php` will be bumped at the very end of the plan (Task 12) so the upgrade only fires once.

- [ ] **Step 1.5: Commit**

```bash
git add gestionprojet/db/install.xml gestionprojet/db/upgrade.php
git commit -m "feat(db): add intro_text to essai_provided + fast_provided"
```

---

### Task 2: Extend `reset_helper::STEP_MAP` (TDD) + endpoint whitelist

**Files:**
- Modify: `gestionprojet/classes/reset_helper.php`
- Modify: `gestionprojet/ajax/reset_to_provided.php`
- Modify: `gestionprojet/tests/reset_helper_test.php`

- [ ] **Step 2.1: Read the existing test file to understand the fixture pattern**

```bash
cat gestionprojet/tests/reset_helper_test.php
```

Note the helper pattern: `$this->getDataGenerator()->create_module('gestionprojet', [...])`, `$this->getDataGenerator()->create_user()`, etc.

- [ ] **Step 2.2: Add failing test for step 5 reset (nominal)**

Append the following method to `tests/reset_helper_test.php` inside the existing class:

```php
    public function test_reset_step5_overwrites_all_essai_fields(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step5_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $DB->insert_record('gestionprojet_essai_provided', (object) [
            'gestionprojetid'        => $instance->id,
            'nom_essai'              => 'TEACHER NAME',
            'date_essai'             => '2026-06-01',
            'groupe_eleves'          => 'TEACHER GROUP',
            'objectif'               => 'TEACHER OBJ',
            'fonction_service'       => 'TEACHER FS',
            'niveaux_reussite'       => 'TEACHER NR',
            'etapes_protocole'       => 'TEACHER EP',
            'materiel_outils'        => 'TEACHER MO',
            'precautions'            => 'TEACHER PREC',
            'resultats_obtenus'      => 'TEACHER RES',
            'observations_remarques' => 'TEACHER OBS',
            'conclusion'             => 'TEACHER CONCL',
            'timecreated'            => time(),
            'timemodified'           => time(),
        ]);

        $studentrec = (object) [
            'gestionprojetid' => $instance->id,
            'userid'          => $user->id,
            'groupid'         => 0,
            'nom_essai'       => 'STUDENT NAME',
            'objectif'        => 'STUDENT OBJ',
            'status'          => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ];
        $studentrec->id = $DB->insert_record('gestionprojet_essai', $studentrec);

        $result = reset_helper::reset_step_to_provided($instance, 5, 0, $user->id);
        $this->assertTrue($result['success']);

        $updated = $DB->get_record('gestionprojet_essai', ['id' => $studentrec->id]);
        $this->assertSame('TEACHER NAME', $updated->nom_essai);
        $this->assertSame('TEACHER OBJ', $updated->objectif);
        $this->assertSame('TEACHER FS', $updated->fonction_service);
        $this->assertSame('TEACHER NR', $updated->niveaux_reussite);
        $this->assertSame('TEACHER EP', $updated->etapes_protocole);
        $this->assertSame('TEACHER MO', $updated->materiel_outils);
        $this->assertSame('TEACHER PREC', $updated->precautions);
        $this->assertSame('TEACHER RES', $updated->resultats_obtenus);
        $this->assertSame('TEACHER OBS', $updated->observations_remarques);
        $this->assertSame('TEACHER CONCL', $updated->conclusion);
        $this->assertSame('TEACHER GROUP', $updated->groupe_eleves);
        $this->assertSame('2026-06-01', $updated->date_essai);
    }
```

- [ ] **Step 2.3: Run the test to verify it fails**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet"
# From the Moodle dirroot — adjust per local test setup. The team typically
# runs PHPUnit on preprod; if not available locally, skip the run and rely on
# preprod validation. Document the result either way.
php $MOODLE_DIRROOT/admin/tool/phpunit/cli/util.php --buildconfig 2>/dev/null || true
vendor/bin/phpunit --filter test_reset_step5_overwrites_all_essai_fields mod/gestionprojet/tests/reset_helper_test.php
```

Expected: **FAIL** with `unsupported_step` (since `STEP_MAP` doesn't yet include 5). If PHPUnit isn't runnable locally, capture the expected failure mode in the commit message and validate after deploy.

- [ ] **Step 2.4: Add failing test for step 9 reset (nominal)**

Append:

```php
    public function test_reset_step9_overwrites_data_json(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step9_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $providedjson = json_encode(['nodes' => [['id' => 'fp1', 'label' => 'TEACHER FP']]]);
        $DB->insert_record('gestionprojet_fast_provided', (object) [
            'gestionprojetid' => $instance->id,
            'data_json'       => $providedjson,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $studentrec = (object) [
            'gestionprojetid' => $instance->id,
            'userid'          => $user->id,
            'groupid'         => 0,
            'data_json'       => json_encode(['nodes' => [['id' => 'fp1', 'label' => 'STUDENT EDIT']]]),
            'status'          => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ];
        $studentrec->id = $DB->insert_record('gestionprojet_fast', $studentrec);

        $result = reset_helper::reset_step_to_provided($instance, 9, 0, $user->id);
        $this->assertTrue($result['success']);

        $updated = $DB->get_record('gestionprojet_fast', ['id' => $studentrec->id]);
        $this->assertSame($providedjson, $updated->data_json);
    }
```

- [ ] **Step 2.5: Add failing test for locked status (steps 5 and 9)**

Append:

```php
    public function test_reset_step5_blocked_when_submitted(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step5_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $DB->insert_record('gestionprojet_essai_provided', (object) [
            'gestionprojetid' => $instance->id,
            'objectif'        => 'TEACHER OBJ',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $studentrec = (object) [
            'gestionprojetid' => $instance->id,
            'userid'          => $user->id,
            'groupid'         => 0,
            'objectif'        => 'STUDENT OBJ',
            'status'          => 1, // submitted
            'timecreated'     => time(),
            'timemodified'    => time(),
        ];
        $studentrec->id = $DB->insert_record('gestionprojet_essai', $studentrec);

        $result = reset_helper::reset_step_to_provided($instance, 5, 0, $user->id);
        $this->assertFalse($result['success']);
        $this->assertSame('locked', $result['error']);

        $unchanged = $DB->get_record('gestionprojet_essai', ['id' => $studentrec->id]);
        $this->assertSame('STUDENT OBJ', $unchanged->objectif);
    }

    public function test_reset_step9_blocked_when_submitted(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step9_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $DB->insert_record('gestionprojet_fast_provided', (object) [
            'gestionprojetid' => $instance->id,
            'data_json'       => '{"nodes":[]}',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $studentrec = (object) [
            'gestionprojetid' => $instance->id,
            'userid'          => $user->id,
            'groupid'         => 0,
            'data_json'       => '{"student":"work"}',
            'status'          => 1,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ];
        $studentrec->id = $DB->insert_record('gestionprojet_fast', $studentrec);

        $result = reset_helper::reset_step_to_provided($instance, 9, 0, $user->id);
        $this->assertFalse($result['success']);
        $this->assertSame('locked', $result['error']);
    }
```

- [ ] **Step 2.6: Add failing test for missing provided record (step 5 and 9)**

Append:

```php
    public function test_reset_step5_no_provided_returns_error(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step5_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $result = reset_helper::reset_step_to_provided($instance, 5, 0, $user->id);
        $this->assertFalse($result['success']);
        $this->assertSame('no_provided', $result['error']);
    }

    public function test_reset_step9_no_provided_returns_error(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step9_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $result = reset_helper::reset_step_to_provided($instance, 9, 0, $user->id);
        $this->assertFalse($result['success']);
        $this->assertSame('no_provided', $result['error']);
    }
```

- [ ] **Step 2.7: Run all the new tests — verify they fail with `unsupported_step`**

```bash
vendor/bin/phpunit --filter "test_reset_step[59]" mod/gestionprojet/tests/reset_helper_test.php
```

Expected: **6 FAIL** (all return `unsupported_step` because STEP_MAP only has 4).

- [ ] **Step 2.8: Extend `STEP_MAP` in `classes/reset_helper.php`**

Edit `gestionprojet/classes/reset_helper.php` — replace the current `STEP_MAP` constant body with:

```php
    private const STEP_MAP = [
        4 => [
            'provided_table' => 'gestionprojet_cdcf_provided',
            'student_table'  => 'gestionprojet_cdcf',
            'table_key'      => 'cdcf',
            'fields'         => ['interacteurs_data'],
        ],
        5 => [
            'provided_table' => 'gestionprojet_essai_provided',
            'student_table'  => 'gestionprojet_essai',
            'table_key'      => 'essai',
            'fields'         => [
                'nom_essai', 'date_essai', 'groupe_eleves', 'objectif',
                'fonction_service', 'niveaux_reussite', 'etapes_protocole',
                'materiel_outils', 'precautions', 'resultats_obtenus',
                'observations_remarques', 'conclusion',
            ],
        ],
        9 => [
            'provided_table' => 'gestionprojet_fast_provided',
            'student_table'  => 'gestionprojet_fast',
            'table_key'      => 'fast',
            'fields'         => ['data_json'],
        ],
    ];
```

Update the docblock comment of `reset_step_to_provided` to drop the "5/7/9 future" hint:

```php
     * @param int    $step          Step number (4, 5, or 9).
```

- [ ] **Step 2.9: Run the tests — verify all 6 pass**

```bash
vendor/bin/phpunit --filter "test_reset_step[459]" mod/gestionprojet/tests/reset_helper_test.php
```

Expected: **9 PASS** (3 existing step 4 tests + 6 new). If any step 4 test fails, the regression must be fixed before continuing.

- [ ] **Step 2.10: Extend endpoint validation in `ajax/reset_to_provided.php`**

Find the validation line that restricts to step 4. It looks like:

```php
if ($step !== 4) {
```

Replace with:

```php
if (!in_array($step, [4, 5, 9], true)) {
```

(If the codebase uses a slightly different form, e.g. `if ($step != 4)`, update accordingly.)

- [ ] **Step 2.11: Commit**

```bash
git add gestionprojet/classes/reset_helper.php gestionprojet/ajax/reset_to_provided.php gestionprojet/tests/reset_helper_test.php
git commit -m "feat(reset): extend STEP_MAP to steps 5 and 9"
```

---

### Task 3: Autosave whitelist — accept `intro_text` for steps 5 and 9

**Files:**
- Modify: `gestionprojet/ajax/autosave.php`
- Modify: `gestionprojet/classes/external/autosave.php`

- [ ] **Step 3.1: Update `ajax/autosave.php` providedtables[5] and [9]**

Find the `$providedtables` array (around line 73). Replace the entries for 5 and 9 to include `intro_text`:

```php
        $providedtables = [
            4 => ['table' => 'gestionprojet_cdcf_provided', 'fields' => ['interacteurs_data', 'intro_text']],
            5 => ['table' => 'gestionprojet_essai_provided', 'fields' => [
                'nom_essai', 'date_essai', 'groupe_eleves', 'objectif',
                'fonction_service', 'niveaux_reussite',
                'etapes_protocole', 'materiel_outils', 'precautions',
                'resultats_obtenus', 'observations_remarques', 'conclusion',
                'intro_text',
            ]],
            9 => ['table' => 'gestionprojet_fast_provided', 'fields' => ['data_json', 'intro_text']],
        ];
```

- [ ] **Step 3.2: Update `classes/external/autosave.php` providedtables[5] and [9]**

Same change in the webservice (around line 104):

```php
                $providedtables = [
                    4 => [
                        'table' => 'gestionprojet_cdcf_provided',
                        'fields' => ['interacteurs_data', 'intro_text'],
                    ],
                    5 => [
                        'table' => 'gestionprojet_essai_provided',
                        'fields' => ['nom_essai', 'date_essai', 'groupe_eleves', 'objectif',
                                     'fonction_service', 'niveaux_reussite',
                                     'etapes_protocole', 'materiel_outils', 'precautions',
                                     'resultats_obtenus', 'observations_remarques', 'conclusion',
                                     'intro_text'],
                    ],
                    9 => [
                        'table' => 'gestionprojet_fast_provided',
                        'fields' => ['data_json', 'intro_text'],
                    ],
                ];
```

- [ ] **Step 3.3: Commit**

```bash
git add gestionprojet/ajax/autosave.php gestionprojet/classes/external/autosave.php
git commit -m "feat(autosave): whitelist intro_text on essai_provided + fast_provided"
```

---

### Task 4: Teacher UI — Atto editor on `step5_provided.php`

**Files:**
- Modify: `gestionprojet/pages/step5_provided.php`

- [ ] **Step 4.1: Locate the insertion point**

Open `gestionprojet/pages/step5_provided.php`. Find the line with `<div class="model-form-section">` that contains `<h3><?php echo icon::render('flask-conical', ...` (the "Informations générales" section header — around line 84-85).

- [ ] **Step 4.2: Insert the intro_text section block above it**

Add a new `<div class="model-form-section gp-intro-section">` block immediately before the existing `model-form-section`:

```php
        <!-- Intro text displayed read-only to students at the top of step 5. -->
        <div class="model-form-section gp-intro-section">
            <h3><?php echo icon::render('file-text', 'sm', 'blue'); ?> <?php echo get_string('intro_text_label', 'gestionprojet'); ?></h3>
            <p class="text-muted small"><?php echo get_string('intro_text_help', 'gestionprojet'); ?></p>
            <textarea name="intro_text" id="intro_text" rows="8" class="form-control gp-intro-textarea"><?php echo s($model->intro_text ?? ''); ?></textarea>
        </div>
        <?php
        // Activate the Moodle preferred rich-text editor (Atto/TinyMCE) on the textarea.
        $editor = editors_get_preferred_editor(FORMAT_HTML);
        $editor->set_text($model->intro_text ?? '');
        $editor->use_editor('intro_text', [
            'context'  => $context,
            'autosave' => false,
        ]);
        ?>

```

- [ ] **Step 4.3: Verify the readonly wrapper still envelops the new block**

Check that the `<?php if ($readonly): ?><div class="gp-fast-readonly"> ... </div><?php endif; ?>` wrapper (around lines 75-77 and 171-173) still surrounds the form, so students see the intro Atto block locked (CSS `pointer-events: none`).

If the new block was inserted INSIDE the wrapper (recommended), no change needed. If outside, move it inside.

- [ ] **Step 4.4: Commit**

```bash
git add gestionprojet/pages/step5_provided.php
git commit -m "feat(step5_provided): add Atto editor for teacher intro_text"
```

---

### Task 5: Teacher UI — Atto editor on `step9_provided.php`

**Files:**
- Modify: `gestionprojet/pages/step9_provided.php`

- [ ] **Step 5.1: Locate the insertion point**

Open `gestionprojet/pages/step9_provided.php`. Find the `$OUTPUT->heading(...)` line (around line 76) and the `<div class="alert alert-info">` line just after it (around line 78).

- [ ] **Step 5.2: Insert the intro_text section block between heading and alert-info**

After the `echo $OUTPUT->heading(...);` line, before the `echo '<div class="alert alert-info">'; ...` block, add:

```php
// Intro text editor (teacher-only effective; readonly wrapper hides editing for students).
if ($canedit) {
    echo '<div class="model-form-section gp-intro-section">';
    echo '<h3>' . \mod_gestionprojet\output\icon::render('file-text', 'sm', 'blue') . ' '
        . get_string('intro_text_label', 'gestionprojet') . '</h3>';
    echo '<p class="text-muted small">' . get_string('intro_text_help', 'gestionprojet') . '</p>';
    echo '<textarea name="intro_text" id="intro_text" rows="8" class="form-control gp-intro-textarea">'
        . s($provided->intro_text ?? '') . '</textarea>';
    echo '</div>';

    $editor = editors_get_preferred_editor(FORMAT_HTML);
    $editor->set_text($provided->intro_text ?? '');
    $editor->use_editor('intro_text', [
        'context'  => $context,
        'autosave' => false,
    ]);
}
```

Note: this block is editor-only (the student sees the read-only banner directly via `step9.php` — the Atto block is NOT shown to students on the provided page since they are redirected to the read-only flow). If the existing `step9_provided.php` shows form to students in readonly mode, wrap the block in `gp-fast-readonly` instead.

After verifying behavior, prefer the unconditional rendering (matching step 4/5 pattern) IF the readonly wrapper covers the entire layout. Inspect `pages/step9_provided.php` lines 81-89 to confirm — if the readonly wrapper is around the form template only, the block above must be conditional on `$canedit`. The conditional version above is safer.

- [ ] **Step 5.3: Confirm `use mod_gestionprojet\output\icon;` is imported at the top of the file**

If not, add it after the `defined('MOODLE_INTERNAL') || die();` line:

```php
use mod_gestionprojet\output\icon;
```

(If `icon::render` is referenced fully-qualified inline, the use statement is unnecessary.)

- [ ] **Step 5.4: Commit**

```bash
git add gestionprojet/pages/step9_provided.php
git commit -m "feat(step9_provided): add Atto editor for teacher intro_text"
```

---

### Task 6: Factor `reset_button.js` AMD module + refactor `cdcf_bootstrap.js` + wire step 4

**Files:**
- Create: `gestionprojet/amd/src/reset_button.js`
- Create: `gestionprojet/amd/build/reset_button.min.js` (manual copy)
- Modify: `gestionprojet/amd/src/cdcf_bootstrap.js`
- Modify: `gestionprojet/amd/build/cdcf_bootstrap.min.js` (manual copy)
- Modify: `gestionprojet/pages/step4.php`

- [ ] **Step 6.1: Read the current Reset logic in `cdcf_bootstrap.js`**

```bash
grep -n "resetButton\|reset_to_provided\|modal" gestionprojet/amd/src/cdcf_bootstrap.js
```

Note the lines. The Reset listener typically:
1. Binds to `#resetButton` click.
2. Opens a Bootstrap modal via `core/modal_factory` with strings `reset_modal_*`.
3. On confirm, POSTs to `/mod/gestionprojet/ajax/reset_to_provided.php`.
4. On success, shows toast + reloads.

- [ ] **Step 6.2: Create `gestionprojet/amd/src/reset_button.js`**

Write a new file with the GPL header and module definition. The module exports `init(cfg)` where `cfg` contains `cmid`, `step`, `groupid`, `sesskey`. Extract the existing Reset logic from `cdcf_bootstrap.js` verbatim and adapt to take params from `cfg`.

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
 * Reset button: confirms via modal, POSTs to reset_to_provided endpoint, reloads.
 *
 * Generic across steps that support reset (4 / 5 / 9). The button must have
 * id="resetButton" on the page.
 *
 * @module     mod_gestionprojet/reset_button
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/str',
    'core/modal_factory',
    'core/modal_events',
    'core/notification'
], function($, Str, ModalFactory, ModalEvents, Notification) {
    'use strict';

    function init(cfg) {
        var btn = document.getElementById('resetButton');
        if (!btn) {
            return;
        }

        btn.addEventListener('click', function(event) {
            event.preventDefault();
            if (btn.disabled) {
                return;
            }

            Str.get_strings([
                {key: 'reset_modal_title',   component: 'mod_gestionprojet'},
                {key: 'reset_modal_body',    component: 'mod_gestionprojet'},
                {key: 'reset_modal_confirm', component: 'mod_gestionprojet'},
                {key: 'reset_modal_cancel',  component: 'mod_gestionprojet'},
                {key: 'reset_success',       component: 'mod_gestionprojet'}
            ]).then(function(strings) {
                var titleStr   = strings[0];
                var bodyStr    = strings[1];
                var confirmStr = strings[2];
                var cancelStr  = strings[3];
                var successStr = strings[4];

                return ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: titleStr,
                    body: '<p>' + bodyStr + '</p>'
                }).then(function(modal) {
                    modal.setSaveButtonText(confirmStr);
                    modal.getRoot().on(ModalEvents.cancel, function() { /* no-op */ });
                    modal.getRoot().on(ModalEvents.save, function() {
                        var form = new FormData();
                        form.append('id', cfg.cmid);
                        form.append('step', cfg.step);
                        form.append('groupid', cfg.groupid || 0);
                        form.append('sesskey', cfg.sesskey);

                        fetch(M.cfg.wwwroot + '/mod/gestionprojet/ajax/reset_to_provided.php', {
                            method: 'POST',
                            body: form,
                            credentials: 'same-origin'
                        }).then(function(resp) {
                            return resp.json();
                        }).then(function(payload) {
                            if (payload.success) {
                                Notification.addNotification({
                                    message: payload.message || successStr,
                                    type: 'success'
                                });
                                window.location.reload();
                            } else {
                                Notification.addNotification({
                                    message: payload.message || 'Reset failed.',
                                    type: 'error'
                                });
                            }
                        }).catch(function(err) {
                            Notification.exception(err);
                        });
                    });
                    modal.show();
                    return modal;
                });
            }).catch(Notification.exception);
        });
    }

    return { init: init };
});
```

- [ ] **Step 6.3: Copy the source to `amd/build/reset_button.min.js` (grunt unavailable per commit `d13b75c`)**

```bash
cp gestionprojet/amd/src/reset_button.js gestionprojet/amd/build/reset_button.min.js
```

(This mirrors the convention used in commit `d13b75c build(amd): copy cdcf_bootstrap source to build (grunt unavailable)`.)

- [ ] **Step 6.4: Refactor `cdcf_bootstrap.js` — remove the Reset logic**

Open `gestionprojet/amd/src/cdcf_bootstrap.js`. Identify the Reset block (the one that binds to `#resetButton` and opens the modal). Delete it cleanly. Also remove unused imports if `core/modal_factory`, `core/modal_events`, `core/notification`, `core/str` are no longer used by the rest of the file. (They probably remain — check carefully.)

- [ ] **Step 6.5: Recopy `cdcf_bootstrap.js` to build**

```bash
cp gestionprojet/amd/src/cdcf_bootstrap.js gestionprojet/amd/build/cdcf_bootstrap.min.js
```

- [ ] **Step 6.6: Wire `reset_button` module on `pages/step4.php`**

Open `gestionprojet/pages/step4.php`. Find where `cdcf_bootstrap` is loaded via `$PAGE->requires->js_call_amd(...)`. After that call, add:

```php
// Reset button (extracted from cdcf_bootstrap for reuse on step 5/9).
$PAGE->requires->js_call_amd('mod_gestionprojet/reset_button', 'init', [[
    'cmid'    => (int)$cm->id,
    'step'    => 4,
    'groupid' => (int)$effectivegroupid,
    'sesskey' => sesskey(),
]]);
```

(Adjust `$effectivegroupid` to the actual variable name used in step4.php — likely the same as in step5.php.)

- [ ] **Step 6.7: Manual smoke test (preprod) — to be done at deploy time**

Mark this step done after preprod validation in Task 13. The check: open step 4 as student, click Reset, confirm modal, verify form clears to consigne, verify autosave still works after reload.

- [ ] **Step 6.8: Commit**

```bash
git add gestionprojet/amd/src/reset_button.js gestionprojet/amd/build/reset_button.min.js \
        gestionprojet/amd/src/cdcf_bootstrap.js gestionprojet/amd/build/cdcf_bootstrap.min.js \
        gestionprojet/pages/step4.php
git commit -m "refactor(amd): extract reset_button module from cdcf_bootstrap"
```

---

### Task 7: Create `intro_text_autosave.js` generic module

**Files:**
- Create: `gestionprojet/amd/src/intro_text_autosave.js`
- Create: `gestionprojet/amd/build/intro_text_autosave.min.js`
- Modify: `gestionprojet/pages/step5_provided.php` (add js_call_amd)
- Modify: `gestionprojet/pages/step9_provided.php` (add js_call_amd)

- [ ] **Step 7.1: Create the module source file**

Create `gestionprojet/amd/src/intro_text_autosave.js`:

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
 * Generic autosave for the teacher intro_text Atto editor on consigne pages.
 *
 * Watches #intro_text (textarea backing the Atto editor) for changes and posts
 * to the autosave webservice with mode=provided. Works on step 4, 5, 9.
 *
 * @module     mod_gestionprojet/intro_text_autosave
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    function init(cfg) {
        var textarea = document.getElementById('intro_text');
        if (!textarea) {
            return;
        }

        var debounceMs = cfg.autosaveMs || 30000;
        var timer = null;
        var lastSent = textarea.value;

        function sendNow() {
            var current = textarea.value;
            if (current === lastSent) {
                return;
            }
            lastSent = current;
            Ajax.call([{
                methodname: 'mod_gestionprojet_autosave',
                args: {
                    cmid: cfg.cmid,
                    step: cfg.step,
                    mode: 'provided',
                    data: JSON.stringify({intro_text: current})
                }
            }])[0].catch(Notification.exception);
        }

        function schedule() {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(sendNow, debounceMs);
        }

        // Atto syncs to the underlying textarea via 'change' event.
        textarea.addEventListener('change', schedule);
        textarea.addEventListener('input', schedule);

        // Flush on page unload.
        window.addEventListener('beforeunload', sendNow);
    }

    return { init: init };
});
```

**Note on the webservice signature**: the args (`cmid`, `step`, `mode`, `data`) must match the webservice declaration in `classes/external/autosave.php`. If the existing signature uses different param names (e.g., `id` instead of `cmid`, or a flat payload instead of `data` JSON), align this module accordingly. Inspect `external_function_parameters` in `classes/external/autosave.php` before finalizing.

- [ ] **Step 7.2: Verify webservice signature matches**

```bash
grep -A 20 "execute_parameters\|external_function_parameters" gestionprojet/classes/external/autosave.php | head -40
```

Adjust `intro_text_autosave.js` `args` to match exactly. Common mismatches:
- `id` vs `cmid`
- `groupid` may be required (default 0)
- `data` may require a specific JSON-encoded shape

Run a single test save manually before continuing.

- [ ] **Step 7.3: Copy to build**

```bash
cp gestionprojet/amd/src/intro_text_autosave.js gestionprojet/amd/build/intro_text_autosave.min.js
```

- [ ] **Step 7.4: Wire on `pages/step5_provided.php`**

At the bottom of `step5_provided.php`, where `essai_provided` AMD is already loaded, add a second `js_call_amd` (only when `!$readonly` to skip students):

```php
if (!$readonly) {
    $PAGE->requires->js_call_amd('mod_gestionprojet/intro_text_autosave', 'init', [[
        'cmid'        => (int)$cm->id,
        'step'        => 5,
        'autosaveMs'  => (int)($gestionprojet->autosave_interval ?? 30) * 1000,
    ]]);
}
```

- [ ] **Step 7.5: Wire on `pages/step9_provided.php`**

Add inside the `if ($canedit) { ... }` block created in Task 5, OR at the bottom of the file:

```php
if ($canedit) {
    $PAGE->requires->js_call_amd('mod_gestionprojet/intro_text_autosave', 'init', [[
        'cmid'        => (int)$cm->id,
        'step'        => 9,
        'autosaveMs'  => (int)($gestionprojet->autosave_interval ?? 30) * 1000,
    ]]);
}
```

- [ ] **Step 7.6: Optional refactor — wire step 4 to the new module**

If `cdcf_bootstrap.js` previously handled intro_text autosave inline, remove that logic and add a parallel `js_call_amd` in `step4_provided.php` for `intro_text_autosave`. Otherwise skip.

```bash
grep -n "intro_text" gestionprojet/amd/src/cdcf_bootstrap.js
```

If the grep returns autosave-related lines (not just the configuration `introTextSelector`), remove them and wire the new module on `step4_provided.php`. If the only reference is the `introTextSelector` config (which feeds CDCF's redraw guard), leave it alone.

- [ ] **Step 7.7: Commit**

```bash
git add gestionprojet/amd/src/intro_text_autosave.js gestionprojet/amd/build/intro_text_autosave.min.js \
        gestionprojet/pages/step5_provided.php gestionprojet/pages/step9_provided.php \
        gestionprojet/amd/src/cdcf_bootstrap.js gestionprojet/amd/build/cdcf_bootstrap.min.js \
        gestionprojet/pages/step4_provided.php
git commit -m "feat(amd): add intro_text_autosave generic module"
```

(If step4_provided.php and cdcf_bootstrap.js are unchanged, drop them from the `git add` list.)

---

### Task 8: Student UI — `step5.php` intro display + Reset button

**Files:**
- Modify: `gestionprojet/pages/step5.php`

- [ ] **Step 8.1: Add intro_text read-only block at the top of the student container**

Open `gestionprojet/pages/step5.php`. Find `<div class="step-container gp-student" ...>` (around line 134). Just BEFORE the `$OUTPUT->heading(...)` call (around line 140), insert:

```php
    <?php
    // Display teacher intro_text read-only at the top (live-read from essai_provided).
    $providedrec = $DB->get_record('gestionprojet_essai_provided', ['gestionprojetid' => $gestionprojet->id]);
    if ($providedrec && !empty(trim(strip_tags($providedrec->intro_text ?? '')))) {
        echo html_writer::start_div('alert alert-info gp-consigne-intro');
        echo html_writer::tag('h4', get_string('intro_section_title', 'gestionprojet'));
        echo format_text($providedrec->intro_text, FORMAT_HTML, ['context' => $context]);
        echo html_writer::end_div();
    }
    ?>
```

(Use the same indentation as surrounding PHP. The block must be inside the `step-container` div but before the heading.)

- [ ] **Step 8.2: Add Reset button in the existing `export-section`**

Find the `<div class="export-section">` block (around line 308). Inside, after the Submit button block, add:

```php
            <?php
            if ((int)$gestionprojet->step5_provided === 1) {
                $hasprovided = $DB->record_exists_select(
                    'gestionprojet_essai_provided',
                    'gestionprojetid = :id AND (
                        (objectif IS NOT NULL AND objectif <> \'\') OR
                        (etapes_protocole IS NOT NULL AND etapes_protocole <> \'\') OR
                        (fonction_service IS NOT NULL AND fonction_service <> \'\')
                    )',
                    ['id' => $gestionprojet->id]
                );
                if ($hasprovided) {
                    $btnattrs = [
                        'type'  => 'button',
                        'class' => 'btn btn-warning btn-lg',
                        'id'    => 'resetButton',
                    ];
                    if ($isLocked) {
                        $btnattrs['disabled']     = 'disabled';
                        $btnattrs['title']        = get_string('reset_disabled_tooltip', 'gestionprojet');
                        $btnattrs['data-toggle']  = 'tooltip';
                    }
                    echo html_writer::start_span('d-inline-block',
                        $isLocked ? ['title' => get_string('reset_disabled_tooltip', 'gestionprojet'),
                                     'data-toggle' => 'tooltip'] : []);
                    echo html_writer::tag('button', '🔄 ' . get_string('reset_button_label', 'gestionprojet'), $btnattrs);
                    echo html_writer::end_span();
                }
            }
            ?>
```

(Mirror the exact pattern used in step4.php lines 191-228 — adapt the wrapper span and conditional `disabled` attributes to match.)

- [ ] **Step 8.3: Wire `reset_button` AMD module**

At the bottom of `step5.php`, where other `js_call_amd` calls live (around line 332-334), add:

```php
$PAGE->requires->js_call_amd('mod_gestionprojet/reset_button', 'init', [[
    'cmid'    => (int)$cm->id,
    'step'    => 5,
    'groupid' => (int)$groupid,
    'sesskey' => sesskey(),
]]);
```

- [ ] **Step 8.4: Commit**

```bash
git add gestionprojet/pages/step5.php
git commit -m "feat(step5): add intro display + reset button on student page"
```

---

### Task 9: Student UI — `step9.php` intro display + Reset section

**Files:**
- Modify: `gestionprojet/pages/step9.php`

- [ ] **Step 9.1: Add intro_text read-only block before the form template render**

Open `gestionprojet/pages/step9.php`. Between `echo html_writer::end_div();` (closing `description`, line 74) and `echo html_writer::start_div('gp-student');` (line 75), insert:

```php
// Display teacher intro_text read-only above the FAST canvas.
$providedrec = $DB->get_record('gestionprojet_fast_provided', ['gestionprojetid' => $gestionprojet->id]);
if ($providedrec && !empty(trim(strip_tags($providedrec->intro_text ?? '')))) {
    echo html_writer::start_div('alert alert-info gp-consigne-intro');
    echo html_writer::tag('h4', get_string('intro_section_title', 'gestionprojet'));
    echo format_text($providedrec->intro_text, FORMAT_HTML, ['context' => $context]);
    echo html_writer::end_div();
}
```

- [ ] **Step 9.2: Add Reset section after the form template render**

After `echo $OUTPUT->render_from_template('mod_gestionprojet/step9_form', $tplcontext);` (line 76), and before the closing `gp-student` div, insert:

```php
// Reset button section (visible only when step9_provided is enabled and a non-empty diagram exists).
if ((int)$gestionprojet->step9_provided === 1) {
    $hasprovided = $DB->record_exists_select(
        'gestionprojet_fast_provided',
        'gestionprojetid = :id AND data_json IS NOT NULL AND data_json <> \'\' AND data_json <> \'{}\'',
        ['id' => $gestionprojet->id]
    );
    if ($hasprovided) {
        $islocked = ((int)$submission->status === 1);
        echo html_writer::start_div('export-section gp-fast-actions');
        $btnattrs = [
            'type'  => 'button',
            'class' => 'btn btn-warning btn-lg',
            'id'    => 'resetButton',
        ];
        if ($islocked) {
            $btnattrs['disabled'] = 'disabled';
        }
        echo html_writer::start_span('d-inline-block',
            $islocked ? ['title' => get_string('reset_disabled_tooltip', 'gestionprojet'),
                         'data-toggle' => 'tooltip'] : []);
        echo html_writer::tag('button', '🔄 ' . get_string('reset_button_label', 'gestionprojet'), $btnattrs);
        echo html_writer::end_span();
        echo html_writer::end_div();
    }
}
```

- [ ] **Step 9.3: Wire `reset_button` AMD module**

After the existing `$PAGE->requires->js_call_amd('mod_gestionprojet/fast_editor', ...)` call (around line 58), add:

```php
$PAGE->requires->js_call_amd('mod_gestionprojet/reset_button', 'init', [[
    'cmid'    => (int)$cm->id,
    'step'    => 9,
    'groupid' => (int)$effectivegroupid,
    'sesskey' => sesskey(),
]]);
```

- [ ] **Step 9.4: Commit**

```bash
git add gestionprojet/pages/step9.php
git commit -m "feat(step9): add intro display + reset section on student page"
```

---

### Task 10: AI integration — generalize `ai_evaluator` for steps 5 and 9

**Files:**
- Modify: `gestionprojet/classes/ai_evaluator.php`
- Modify: `gestionprojet/tests/ai_prompt_builder_test.php` (or create if absent)

- [ ] **Step 10.1: Read the existing step 4 block**

```bash
sed -n '188,212p' gestionprojet/classes/ai_evaluator.php
```

Confirm the structure matches the spec section 8.

- [ ] **Step 10.2: Locate (or create) the test file `tests/ai_prompt_builder_test.php`**

```bash
ls gestionprojet/tests/ai_prompt_builder_test.php 2>/dev/null
```

If the file exists, append new test methods in **Step 10.5**. If it does not exist, create it with the GPL header and a class declaration:

```php
<?php
// (full GPL header)

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gestionprojet/classes/ai_prompt_builder.php');

/**
 * @covers \mod_gestionprojet\ai_prompt_builder
 */
final class ai_prompt_builder_test extends \advanced_testcase {
    // tests added below
}
```

- [ ] **Step 10.3: Add failing tests for intro injection (steps 5 and 9)**

Append to `tests/ai_prompt_builder_test.php`:

```php
    public function test_step5_intro_text_injected_in_system_prompt(): void {
        $builder = new ai_prompt_builder();
        $student = (object)['fonction_service' => 'X'];
        $teacher = (object)['ai_instructions' => 'Eval criteria'];
        $intro = '<p>Consigne pédagogique step 5</p>';
        $prompts = $builder->build_prompt(5, $student, $teacher, $intro, null, false);
        $this->assertStringContainsString('Consigne pédagogique step 5', $prompts['system']);
    }

    public function test_step9_intro_text_injected_in_system_prompt(): void {
        $builder = new ai_prompt_builder();
        $student = (object)['data_json' => '{}'];
        $teacher = (object)['ai_instructions' => 'Eval FAST'];
        $intro = '<p>Consigne FAST</p>';
        $prompts = $builder->build_prompt(9, $student, $teacher, $intro, null, false);
        $this->assertStringContainsString('Consigne FAST', $prompts['system']);
    }

    public function test_step5_identical_copy_alert_in_user_prompt(): void {
        $builder = new ai_prompt_builder();
        $providedrec = (object)['objectif' => 'TEACHER'];
        $student = (object)['objectif' => 'TEACHER'];
        $teacher = (object)['ai_instructions' => ''];
        $prompts = $builder->build_prompt(5, $student, $teacher, null, $providedrec, true);
        // The user prompt should contain a top-level alert about identical submission.
        $this->assertMatchesRegularExpression('/identique|identical|0\s*\/\s*20/i', $prompts['user']);
    }

    public function test_step9_identical_copy_alert_in_user_prompt(): void {
        $builder = new ai_prompt_builder();
        $providedrec = (object)['data_json' => '{"a":1}'];
        $student = (object)['data_json' => '{"a":1}'];
        $teacher = (object)['ai_instructions' => ''];
        $prompts = $builder->build_prompt(9, $student, $teacher, null, $providedrec, true);
        $this->assertMatchesRegularExpression('/identique|identical|0\s*\/\s*20/i', $prompts['user']);
    }
```

- [ ] **Step 10.4: Run the tests — verify they PASS already (the builder is generic)**

```bash
vendor/bin/phpunit --filter "test_step[59]_(intro|identical)" mod/gestionprojet/tests/ai_prompt_builder_test.php
```

Expected: **4 PASS** if `ai_prompt_builder` was made generic in v2.9.0. If any FAIL, the builder still has step-4-only logic — fix it by generalizing the conditional sections.

- [ ] **Step 10.5: Generalize the step 4 block in `ai_evaluator.php`**

Open `gestionprojet/classes/ai_evaluator.php`. Replace lines 189-211 (the `if ((int)$evaluation->step === 4)` block) with the generalized mapping:

```php
            // Mapping per step: provided table + comparator strategy for "no modifications" detection.
            $providedmap = [
                4 => [
                    'table'      => 'gestionprojet_cdcf_provided',
                    'comparator' => 'json_normalized',
                    'field'      => 'interacteurs_data',
                ],
                5 => [
                    'table'      => 'gestionprojet_essai_provided',
                    'comparator' => 'fields_strict',
                    'fields'     => [
                        'nom_essai', 'date_essai', 'groupe_eleves', 'objectif',
                        'fonction_service', 'niveaux_reussite', 'etapes_protocole',
                        'materiel_outils', 'precautions', 'resultats_obtenus',
                        'observations_remarques', 'conclusion',
                    ],
                ],
                9 => [
                    'table'      => 'gestionprojet_fast_provided',
                    'comparator' => 'string_strict',
                    'field'      => 'data_json',
                ],
            ];

            $teacherintro = null;
            $providedrec_for_prompt = null;
            $nomodifications = false;

            if (isset($providedmap[$evaluation->step])) {
                $cfg = $providedmap[$evaluation->step];
                $providedrec = $DB->get_record($cfg['table'], ['gestionprojetid' => $evaluation->gestionprojetid]);
                if ($providedrec) {
                    if (!empty(trim(strip_tags($providedrec->intro_text ?? '')))) {
                        $teacherintro = $providedrec->intro_text;
                    }
                    if (self::provided_has_content($cfg, $providedrec)) {
                        $providedrec_for_prompt = $providedrec;
                        $nomodifications = self::detect_no_modifications($cfg, $submission, $providedrec);
                    }
                }
            }
```

- [ ] **Step 10.6: Add private static methods `provided_has_content` and `detect_no_modifications` on `ai_evaluator`**

After the `process_evaluation` method (or at the bottom of the class before the final `}`), add:

```php
    /**
     * Check whether the provided record has any non-empty content in the comparable fields.
     *
     * @param array $cfg     Provided map entry for the current step.
     * @param object $rec    Provided record.
     * @return bool          True when at least one field is non-empty.
     */
    private static function provided_has_content(array $cfg, object $rec): bool {
        $fields = $cfg['fields'] ?? [$cfg['field']];
        foreach ($fields as $f) {
            if (!empty(trim((string)($rec->$f ?? '')))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compare the student submission to the provided record using the per-step comparator.
     *
     * @param array $cfg            Provided map entry.
     * @param object $submission    Student submission record.
     * @param object $providedrec   Teacher provided record.
     * @return bool                 True when the student record matches the provided record exactly.
     */
    private static function detect_no_modifications(array $cfg, object $submission, object $providedrec): bool {
        switch ($cfg['comparator']) {
            case 'json_normalized':
                $f = $cfg['field'];
                $studentjson  = json_decode($submission->$f ?? '', true);
                $providedjson = json_decode($providedrec->$f ?? '', true);
                if (is_array($studentjson) && is_array($providedjson)) {
                    return json_encode($studentjson) === json_encode($providedjson);
                }
                return false;

            case 'string_strict':
                $f = $cfg['field'];
                return (string)($submission->$f ?? '') === (string)($providedrec->$f ?? '');

            case 'fields_strict':
                foreach ($cfg['fields'] as $f) {
                    if ((string)($submission->$f ?? '') !== (string)($providedrec->$f ?? '')) {
                        return false;
                    }
                }
                return true;

            default:
                return false;
        }
    }
```

- [ ] **Step 10.7: Verify all existing tests still pass**

```bash
vendor/bin/phpunit mod/gestionprojet/tests/
```

Expected: all green. The step 4 path uses `json_normalized` (same logic as before), so behavior is preserved.

- [ ] **Step 10.8: Commit**

```bash
git add gestionprojet/classes/ai_evaluator.php gestionprojet/tests/ai_prompt_builder_test.php
git commit -m "feat(ai): generalize intro injection + identical-copy guard to steps 5 and 9"
```

---

### Task 11: Backup — extend nested elements

**Files:**
- Modify: `gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php`

- [ ] **Step 11.1: Locate the existing nested elements**

```bash
grep -n "essai_provided\|fast_provided" gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php
```

Identify the two `backup_nested_element('essai_provided', ...)` and `backup_nested_element('fast_provided', ...)` calls.

- [ ] **Step 11.2: Add `intro_text` to the field arrays of both nested elements**

For each, locate the `[`...`]` of fields and append `'intro_text'` immediately before `'timecreated'` (matching the order on `cdcf_provided` after v2.9.0). Use Edit tool for exactness.

Example (replace with actual existing field list):

```php
$essaiprovided = new backup_nested_element('essai_provided', ['id'], [
    'nom_essai', 'date_essai', 'groupe_eleves', 'objectif',
    'fonction_service', 'niveaux_reussite', 'etapes_protocole',
    'materiel_outils', 'precautions', 'resultats_obtenus',
    'observations_remarques', 'conclusion', 'intro_text',
    'timecreated', 'timemodified',
]);

$fastprovided = new backup_nested_element('fast_provided', ['id'], [
    'data_json', 'intro_text', 'timecreated', 'timemodified',
]);
```

- [ ] **Step 11.3: Verify restore is automatic (no change required)**

```bash
grep -n "process_gestionprojet_essai_provided\|process_gestionprojet_fast_provided" gestionprojet/backup/moodle2/restore_gestionprojet_stepslib.php
```

Confirm both functions use `(array)$data` + `$DB->insert_record(...)`. The new field is restored automatically because it's listed in the backup definition.

- [ ] **Step 11.4: Commit**

```bash
git add gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php
git commit -m "feat(backup): include intro_text in essai_provided + fast_provided"
```

---

### Task 12: Version bump + CHANGELOG + RELEASE_NOTES + memory update

**Files:**
- Modify: `gestionprojet/version.php`
- Modify: `CHANGELOG.md` (project root)
- Create: `RELEASE_NOTES_v2.10.0.md` (project root)
- Modify: `/Users/remyemmanuel/.claude/projects/-Volumes-DONNEES-Claude-code-mod-gestionprojet/memory/feature_consigne_pattern_extension.md`

- [ ] **Step 12.1: Bump version**

Edit `gestionprojet/version.php`:

```php
$plugin->version = 2026050900;  // 2.10.0
$plugin->release = '2.10.0';
```

(Adjust the date suffix to today's date if it's later than 2026-05-09 — format is `YYYYMMDDXX` where `XX` is a daily counter.)

- [ ] **Step 12.2: Add entry to `CHANGELOG.md`**

Read the existing `CHANGELOG.md` to follow the existing format, then prepend a new section under `## [Unreleased]` or as a new top section:

```markdown
## [2.10.0] - 2026-05-09

### Added
- Step 5 (essai): teacher intro_text rich-text editor on the consigne page, displayed read-only at the top of the student essai page (live-read from `essai_provided`).
- Step 5 (essai): "Reset form" button on the student page that overwrites all 12 essai fields from the latest teacher consigne. Disabled when submitted.
- Step 9 (FAST): teacher intro_text rich-text editor + read-only display on the student page above the diagram canvas.
- Step 9 (FAST): "Reset form" button replacing the student diagram with the latest teacher version.
- AI evaluation: teacher intro_text injected into the system prompt for steps 5 and 9 (mirroring step 4).
- AI evaluation: identical-copy guard (forces 0/20 alert) when the student submission matches the consigne for steps 5 and 9.

### Changed
- Extracted reset modal/fetch logic from `cdcf_bootstrap.js` into reusable `mod_gestionprojet/reset_button` AMD module (used by step 4, 5, 9).
- Added generic `mod_gestionprojet/intro_text_autosave` AMD module for the teacher intro editor across all consigne pages.
- `ai_evaluator` mapping generalized: per-step comparator (`json_normalized` / `fields_strict` / `string_strict`).

### Database
- New column `intro_text` (TEXT, nullable) on `gestionprojet_essai_provided` and `gestionprojet_fast_provided`. Migration `2026050900`.
```

- [ ] **Step 12.3: Create `RELEASE_NOTES_v2.10.0.md`**

At project root, create the file:

```markdown
# Release Notes — Plugin Gestion de Projet v2.10.0

**Date** : 2026-05-09
**Compatibility** : Moodle 5.0+ / PHP 8.1+

## Highlights

The v2.9.0 "consigne pattern" introduced for step 4 (CDCF) is now extended to step 5 (Fiche d'essai) and step 9 (Diagramme FAST):

- **Texte de présentation aux élèves** : un éditeur Atto sur la page consigne enseignant, affiché en lecture seule au-dessus de l'activité élève. Modifications enseignant propagées en temps réel.
- **Bouton « Réinitialiser le formulaire »** côté élève : remplace le travail de l'élève par la dernière version de la consigne. Désactivé après soumission.
- **Évaluation IA** : la consigne est injectée dans le prompt, et l'IA force une note de 0/20 si l'élève soumet une copie identique à la consigne.

## What's new for teachers

- Sur step 5 et step 9 en mode `provided` : un éditeur de texte riche tout en haut pour rédiger une consigne pédagogique destinée aux élèves.
- Aucun changement sur les modèles de correction (`*_teacher`) ni sur les instructions IA.

## What's new for students

- Encadré bleu « Consignes de l'enseignant » au-dessus du formulaire / diagramme.
- Bouton 🔄 « Réinitialiser le formulaire » à côté de Soumettre. Confirmation par fenêtre modale.

## Database migration

Une seule étape DB (`2026050900`) ajoute le champ `intro_text` sur `gestionprojet_essai_provided` et `gestionprojet_fast_provided`. Aucun backfill, le champ est optionnel.

## Known limitations

- Step 7 (Expression du besoin) : le mode `provided` n'existe pas encore. À traiter dans une prochaine release.

## Upgrade path

Standard : Moodle Admin → Notifications → valider l'upgrade DB. Aucune action manuelle requise.
```

- [ ] **Step 12.4: Update auto-memory file to mark partial resolution**

Edit `/Users/remyemmanuel/.claude/projects/-Volumes-DONNEES-Claude-code-mod-gestionprojet/memory/feature_consigne_pattern_extension.md`. Update the body to reflect that step 5 and step 9 are delivered in v2.10.0; only step 7 remains.

Replace the bullet list and surrounding text to leave only step 7 as outstanding:

```markdown
**Statut au 2026-05-09** :

- ✅ **Step 4 (CDCF)** — livré v2.9.0
- ✅ **Step 5 (Essai)** — livré v2.10.0
- ✅ **Step 9 (FAST)** — livré v2.10.0
- ⏳ **Step 7 (Expression du besoin)** — non démarré. Nécessite création préalable du mode `provided` (table `besoin_eleve_provided`, flag `step7_provided`, page, toggle Gantt). Spec dédiée à venir.

(Le reste du fichier reste pertinent comme guide d'extension pour step 7.)
```

- [ ] **Step 12.5: Commit**

```bash
git add gestionprojet/version.php CHANGELOG.md RELEASE_NOTES_v2.10.0.md
git commit -m "feat(version): bump to 2.10.0"
```

(The auto-memory file lives outside the repo — the memory edit is not part of the git commit. It is updated separately.)

---

### Task 13: Preprod deployment + manual validation

**Files:** none modified — operational task.

- [ ] **Step 13.1: Read preprod credentials and paths**

Per auto-memory `preprod_access`, the credentials and paths are in `TESTING.md` at the repo root. Open it.

- [ ] **Step 13.2: SCP the plugin folder to preprod**

Use the SCP command documented in `TESTING.md`. Example shape (DO NOT execute without verifying the actual paths):

```bash
scp -r gestionprojet/ <user>@<host>:<preprod-moodle-root>/mod/
```

- [ ] **Step 13.3: Purge caches + run upgrade on preprod**

```bash
ssh <user>@<host> "cd <preprod-moodle-root> && php admin/cli/purge_caches.php && php admin/cli/upgrade.php --non-interactive"
```

Expected output: upgrade step `2026050900` runs successfully on `mod_gestionprojet`.

- [ ] **Step 13.4: Run preprod validation checklist (spec sections 13.1, 13.2, 13.3)**

Walk through the spec test scenarios:

**Step 5 (essai)**:
1. Teacher fills `intro_text` (rich HTML) + protocol textareas. Reload → persistence OK.
2. Student never opened → blue intro banner + form pre-filled (initial seeding).
3. Student with draft → blue banner + old form (no re-seeding).
4. Student clicks Reset → modal → confirm → 12 fields overwritten. Toast + reload OK.
5. Student submits → Reset button greyed, tooltip visible.
6. Teacher reverts → Reset button re-enabled.
7. Teacher modifies `intro_text` → student reload shows new version.
8. AI evaluation: intro in system prompt + identical-copy alert if applicable.
9. `precautions` text from teacher → student sees 3 prefilled cells + 3 empty (clamp 6). Edit one cell → autosave → reload → JSON 6-cell.
10. Backup → restore → `essai_provided.intro_text` preserved.

**Step 9 (FAST)**:
1. Teacher fills `intro_text` + draws diagram. Persistence OK.
2. Student never opened → blue banner + diagram pre-filled.
3. Student moves a node → autosave → reload → modification kept.
4. Student clicks Reset → modal → confirm → diagram replaced. Toast + reload OK.
5. AI evaluation: identical-copy alert if untouched submission → 0/20.
6. Teacher modifies intro after evaluation → student reload shows new intro, past evaluation frozen.
7. Backup → restore → `fast_provided.intro_text` preserved.

**Step 4 regression** (from refactor in Task 6):
1. Open step 4 as student. Click Reset. Confirm modal. Verify form clears to consigne.
2. Verify autosave still works after reload.

**Bypass UI (security)**:
1. Submitted student tries `POST /ajax/reset_to_provided.php` step=5 or step=9 via dev tools → 403.
2. User without `submit` capability → 403.
3. Step ∈ {1,2,3,6,7,8} → 400 (`unsupported_step`).

- [ ] **Step 13.5: Document preprod validation outcome**

Either confirm all green and proceed to merge/prod, or open a fix branch from the failing scenario. If a regression is found, do NOT proceed to Task 14 — fix first.

---

### Task 14: Merge + push + production deployment

**Files:** none modified — operational task.

- [ ] **Step 14.1: Push the feature branch and merge to main**

```bash
git push origin feat/consigne-extension-step5-9
git checkout main
git merge --no-ff feat/consigne-extension-step5-9 -m "Merge branch 'feat/consigne-extension-step5-9' into main"
git push origin main
```

- [ ] **Step 14.2: Build production ZIP**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
rm -f gestionprojet.zip
zip -r gestionprojet.zip gestionprojet/ -x "*.git*" "*.claude*" "*lessons.md"
ls -lh gestionprojet.zip
```

Expected: a `gestionprojet.zip` artefact at the repo root, ~1-3 MB.

- [ ] **Step 14.3: Upload to production via Moodle Admin**

Per auto-memory `prod_access`, the production server is `ent-occitanie.com/moodle`. Upload the ZIP via Administration → Plugins → Install plugins → Upload ZIP. Validate the upgrade on the Notifications page (DB step `2026050900`).

- [ ] **Step 14.4: Production smoke test**

On a real instance with `step5_provided=1` and `step9_provided=1` :
- Verify the intro editor saves and reloads.
- Verify the student Reset button works end-to-end.
- Trigger one AI evaluation on each step → check the prompt log for intro injection.

- [ ] **Step 14.5: Tag the release in git**

```bash
git tag -a v2.10.0 -m "v2.10.0 — consigne pattern extension to steps 5 and 9"
git push origin v2.10.0
```

---

## Self-Review Checklist (run after writing the plan, fix inline)

**Spec coverage** (sections of `2026-05-06-consigne-extension-step5-9-design.md`):
- §4 (data model) → Task 1 ✓
- §5 (STEP_MAP + endpoint whitelist) → Task 2 ✓
- §6 (teacher UI) → Tasks 4, 5, 7 ✓
- §7 (student UI + reset_button refactor) → Tasks 6, 8, 9 ✓
- §8 (AI integration) → Task 10 ✓
- §9 (backup/restore) → Task 11 ✓
- §10 (delete_instance + strings + CSS) → no-op (already covered in v2.9.0) ✓
- §11 (security) → Task 13 includes bypass tests ✓
- §12 (versioning) → Task 12 ✓
- §13 (test plan) → Tasks 2, 10, 13 ✓
- §14 (workflow + deploy) → Tasks 12, 13, 14 ✓
- §15 (risks) → mitigations covered in tasks 6, 7, 13 ✓
- §16 (touchpoints recap) → Tasks 1-12 cover all listed files ✓
- §17 (future) → no-op (out of scope) ✓

**Placeholder scan**: no TBD/TODO/"implement later". Step 5.2 has a note about wrapping options that is conditional on observed behavior — acceptable since the executor must verify on the actual file. Step 7.2 documents how to verify the webservice signature explicitly with the grep command.

**Type consistency**:
- `STEP_MAP` field names match between Task 2 (reset_helper) and Task 10 (ai_evaluator providedmap) for steps 5 and 9. ✓
- `reset_button` AMD module signature: `init({cmid, step, groupid, sesskey})` — same in Tasks 6, 8, 9. ✓
- `intro_text_autosave` signature: `init({cmid, step, autosaveMs})` — consistent in Task 7 wiring. ✓

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-06-consigne-extension-step5-9.md`.

Two execution options :

1. **Subagent-Driven (recommended)** — A fresh subagent per task, review between tasks, fast iteration. Recommended for plans with refactor + new code (Tasks 6 and 7 in particular).

2. **Inline Execution** — Tasks executed in this session via `superpowers:executing-plans`, batch execution with checkpoints for review.

Which approach do you want?
