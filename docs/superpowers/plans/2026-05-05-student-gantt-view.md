# Vue Gantt élève — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer les cartes empilées de l'interface élève par un simili-Gantt 2 lignes × 8 colonnes, aligné sur la vue enseignant, sans checkbox ni ligne « modèles de correction ».

**Architecture:** Extraction d'un helper partagé pour la définition des colonnes Gantt (utilisé par les vues enseignant et élève), construction d'un nouveau helper `gestionprojet_build_student_gantt_data()` produisant la structure de contexte du template, nouveau template Mustache `home_gantt_student.mustache`. Aucune modification de DB, AJAX, ou JS.

**Tech Stack:** PHP 8.1+ (Moodle 5.0+), Mustache, CSS namespaced `.path-mod-gestionprojet`, PHPUnit Moodle (`advanced_testcase`).

**Spec:** `docs/superpowers/specs/2026-05-05-student-gantt-view-design.md`

---

## File Structure

| Fichier | Action | Responsabilité |
|---|---|---|
| `gestionprojet/lib.php` | Modifier | Ajouter `gestionprojet_get_gantt_column_defs()` et `gestionprojet_build_student_gantt_cell()` (helpers purs) |
| `gestionprojet/pages/home.php` | Modifier | Branche teacher : utiliser le helper de colonnes ; branche student : construire `gantt_student` au lieu des tableaux de cartes |
| `gestionprojet/templates/home_gantt_student.mustache` | Créer | Template Gantt élève (2 lignes × 8 colonnes, sans checkbox) |
| `gestionprojet/templates/home.mustache` | Modifier | Inclure `home_gantt_student` dans la branche `^isteacher` sous `hasusergroup` |
| `gestionprojet/lang/en/gestionprojet.php` | Modifier | 6 nouvelles clés |
| `gestionprojet/lang/fr/gestionprojet.php` | Modifier | 6 nouvelles clés |
| `gestionprojet/styles.css` | Modifier | Variante `gp-cell-link-nocheck` (sans padding-left checkbox) + résumé élève |
| `gestionprojet/tests/gantt_helpers_test.php` | Créer | Tests PHPUnit des helpers purs |
| `gestionprojet/version.php` | Modifier | Bump `version` + `release` |

---

## Task 1 — Extraire la définition des colonnes Gantt dans `lib.php`

**Files:**
- Modify: `gestionprojet/lib.php` (ajout en fin de fichier ou près des autres helpers de navigation)
- Modify: `gestionprojet/pages/home.php:113-122` (utiliser l'helper)
- Create: `gestionprojet/tests/gantt_helpers_test.php`

- [ ] **Step 1.1 : Écrire le test attendu**

Créer `gestionprojet/tests/gantt_helpers_test.php` :

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
 * Unit tests for Gantt helper functions.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gestionprojet/lib.php');

/**
 * Tests for gantt helper functions.
 *
 * @group mod_gestionprojet
 */
class mod_gestionprojet_gantt_helpers_test extends advanced_testcase {

    public function test_column_defs_has_eight_columns(): void {
        $defs = gestionprojet_get_gantt_column_defs();
        $this->assertCount(8, $defs);
    }

    public function test_column_defs_order(): void {
        $defs = gestionprojet_get_gantt_column_defs();
        $stepnums = array_column($defs, 'stepnum');
        $this->assertSame([1, 3, 2, 4, 9, 5, 8, 6], $stepnums);
    }

    public function test_step_two_merges_with_step_seven(): void {
        $defs = gestionprojet_get_gantt_column_defs();
        $col2 = null;
        foreach ($defs as $def) {
            if ($def['stepnum'] === 2) {
                $col2 = $def;
                break;
            }
        }
        $this->assertNotNull($col2);
        $this->assertSame(7, $col2['mergedwith']);
    }

    public function test_other_columns_have_null_merged(): void {
        $defs = gestionprojet_get_gantt_column_defs();
        foreach ($defs as $def) {
            if ($def['stepnum'] !== 2) {
                $this->assertNull($def['mergedwith'], "Column {$def['stepnum']} should have mergedwith=null");
            }
        }
    }
}
```

- [ ] **Step 1.2 : Lancer le test, vérifier qu'il échoue**

```bash
cd /var/www/moodle  # ou path Moodle preprod
php admin/tool/phpunit/cli/init.php  # une seule fois
vendor/bin/phpunit --group mod_gestionprojet --filter test_column_defs_has_eight_columns
```

Attendu : `Error: Call to undefined function gestionprojet_get_gantt_column_defs()`.

(Si l'environnement local PHPUnit n'est pas dispo, exécuter le test après déploiement preprod via la même commande sur le serveur.)

- [ ] **Step 1.3 : Implémenter `gestionprojet_get_gantt_column_defs()` dans `lib.php`**

Ajouter à la fin de `gestionprojet/lib.php` (avant la dernière accolade de fichier ou après les autres helpers de navigation) :

```php
/**
 * Returns the column definitions for the Gantt-style home view.
 *
 * Each entry: ['stepnum' => N, 'mergedwith' => M|null].
 * 'mergedwith' indicates that the column also represents another step's row 2/3 cells.
 *
 * Order matches the visual layout: 1 · 3 · 2(+7) · 4 · 9 · 5 · 8 · 6.
 *
 * @return array
 */
function gestionprojet_get_gantt_column_defs(): array {
    return [
        ['stepnum' => 1, 'mergedwith' => null],
        ['stepnum' => 3, 'mergedwith' => null],
        ['stepnum' => 2, 'mergedwith' => 7],
        ['stepnum' => 4, 'mergedwith' => null],
        ['stepnum' => 9, 'mergedwith' => null],
        ['stepnum' => 5, 'mergedwith' => null],
        ['stepnum' => 8, 'mergedwith' => null],
        ['stepnum' => 6, 'mergedwith' => null],
    ];
}
```

- [ ] **Step 1.4 : Lancer les tests, vérifier qu'ils passent**

```bash
vendor/bin/phpunit --group mod_gestionprojet --filter mod_gestionprojet_gantt_helpers_test
```

Attendu : 4 tests OK.

- [ ] **Step 1.5 : Refactorer `pages/home.php` pour utiliser l'helper**

Dans `gestionprojet/pages/home.php`, remplacer le bloc :

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

par :

```php
$ganttcolumndefs = gestionprojet_get_gantt_column_defs();
```

- [ ] **Step 1.6 : Vérifier la non-régression Gantt enseignant**

Ouvrir l'activité gestionprojet en tant qu'enseignant après déploiement et vérifier que le Gantt s'affiche identique (8 colonnes, 3 lignes, mêmes données).

- [ ] **Step 1.7 : Commit**

```bash
git add gestionprojet/lib.php gestionprojet/tests/gantt_helpers_test.php gestionprojet/pages/home.php
git commit -m "refactor(gantt): extract column definitions to lib.php helper

Allows the upcoming student Gantt view to share the same column
ordering and merging logic as the teacher view."
```

---

## Task 2 — Helper `gestionprojet_build_student_gantt_cell()`

Construit une cellule (ligne 1 ou ligne 2) à partir des données déjà résolues. Fonction pure, testable sans DB.

**Files:**
- Modify: `gestionprojet/lib.php`
- Modify: `gestionprojet/tests/gantt_helpers_test.php`

- [ ] **Step 2.1 : Ajouter les tests pour la fonction**

Ajouter dans `gestionprojet/tests/gantt_helpers_test.php`, dans la même classe :

```php
public function test_build_cell_empty_when_not_filled(): void {
    $cell = gestionprojet_build_student_gantt_cell([
        'isfilled' => false,
    ]);
    $this->assertSame(['isfilled' => false], $cell);
}

public function test_build_cell_consult_complete(): void {
    $cell = gestionprojet_build_student_gantt_cell([
        'isfilled' => true,
        'role' => 'consult',
        'isenabled' => true,
        'iscomplete' => true,
        'name' => 'Step 1',
        'url' => '/view.php?id=1&step=1',
        'isprovided' => false,
    ]);
    $this->assertTrue($cell['isfilled']);
    $this->assertTrue($cell['isenabled']);
    $this->assertTrue($cell['iscomplete']);
    $this->assertSame('/view.php?id=1&step=1', $cell['url']);
    $this->assertFalse($cell['isprovided']);
}

public function test_build_cell_consult_disabled(): void {
    $cell = gestionprojet_build_student_gantt_cell([
        'isfilled' => true,
        'role' => 'consult',
        'isenabled' => false,
        'iscomplete' => false,
        'name' => 'Step 1',
        'url' => '#',
        'isprovided' => false,
    ]);
    $this->assertTrue($cell['isfilled']);
    $this->assertFalse($cell['isenabled']);
}

public function test_build_cell_work_with_grade(): void {
    $cell = gestionprojet_build_student_gantt_cell([
        'isfilled' => true,
        'role' => 'work',
        'isenabled' => true,
        'iscomplete' => true,
        'name' => 'Step 4',
        'url' => '/view.php?id=1&step=4',
        'grade' => 14.5,
    ]);
    $this->assertTrue($cell['hasgrade']);
    $this->assertSame('14.5 / 20', $cell['gradeformatted']);
}

public function test_build_cell_work_no_grade(): void {
    $cell = gestionprojet_build_student_gantt_cell([
        'isfilled' => true,
        'role' => 'work',
        'isenabled' => true,
        'iscomplete' => false,
        'name' => 'Step 4',
        'url' => '/view.php?id=1&step=4',
        'grade' => null,
    ]);
    $this->assertFalse($cell['hasgrade']);
    $this->assertArrayNotHasKey('gradeformatted', $cell);
}

public function test_build_cell_consult_provided_badge(): void {
    $cell = gestionprojet_build_student_gantt_cell([
        'isfilled' => true,
        'role' => 'consult',
        'isenabled' => true,
        'iscomplete' => true,
        'name' => 'Step 4',
        'url' => '/view.php?id=1&step=4&mode=provided',
        'isprovided' => true,
    ]);
    $this->assertTrue($cell['isprovided']);
}
```

- [ ] **Step 2.2 : Lancer les tests, vérifier qu'ils échouent**

```bash
vendor/bin/phpunit --group mod_gestionprojet --filter mod_gestionprojet_gantt_helpers_test
```

Attendu : `Call to undefined function gestionprojet_build_student_gantt_cell()`.

- [ ] **Step 2.3 : Implémenter le helper dans `lib.php`**

Ajouter dans `gestionprojet/lib.php` après `gestionprojet_get_gantt_column_defs()` :

```php
/**
 * Builds a single cell for the student Gantt view.
 *
 * Pure function — accepts pre-resolved data, returns the array consumed by the
 * home_gantt_student Mustache template.
 *
 * Expected $input keys when isfilled is true:
 * - role: 'consult' or 'work'
 * - isenabled: bool
 * - iscomplete: bool
 * - name: string
 * - url: string
 * - isprovided: bool (only for role='consult', columns 4 and 9)
 * - grade: float|null (only for role='work')
 *
 * @param array $input
 * @return array
 */
function gestionprojet_build_student_gantt_cell(array $input): array {
    if (empty($input['isfilled'])) {
        return ['isfilled' => false];
    }

    $cell = [
        'isfilled' => true,
        'isenabled' => (bool)($input['isenabled'] ?? false),
        'iscomplete' => (bool)($input['iscomplete'] ?? false),
        'name' => $input['name'] ?? '',
        'url' => $input['url'] ?? '#',
    ];

    if (($input['role'] ?? '') === 'consult') {
        $cell['isprovided'] = (bool)($input['isprovided'] ?? false);
    }

    if (($input['role'] ?? '') === 'work') {
        $grade = $input['grade'] ?? null;
        if ($grade !== null) {
            $cell['hasgrade'] = true;
            $cell['gradeformatted'] = number_format((float)$grade, 1) . ' / 20';
        } else {
            $cell['hasgrade'] = false;
        }
    }

    return $cell;
}
```

- [ ] **Step 2.4 : Lancer les tests, vérifier qu'ils passent**

```bash
vendor/bin/phpunit --group mod_gestionprojet --filter mod_gestionprojet_gantt_helpers_test
```

Attendu : 10 tests OK (4 colonnes + 6 cellules).

- [ ] **Step 2.5 : Commit**

```bash
git add gestionprojet/lib.php gestionprojet/tests/gantt_helpers_test.php
git commit -m "feat(gantt): add pure helper to build student Gantt cells

Returns the Mustache context for one cell of the upcoming student
Gantt view (consult row or work row), with optional grade formatting."
```

---

## Task 3 — Strings i18n (en + fr)

**Files:**
- Modify: `gestionprojet/lang/en/gestionprojet.php`
- Modify: `gestionprojet/lang/fr/gestionprojet.php`

- [ ] **Step 3.1 : Ajouter les clés en anglais**

Repérer la zone des strings `gantt_*` (autour de la ligne 839 du fichier français — chercher `gantt_row_teacher_docs` dans `lang/en/gestionprojet.php` pour trouver l'emplacement). Ajouter à la suite des strings Gantt existantes :

```php
$string['gantt_student_row_consult'] = 'Consult';
$string['gantt_student_row_work'] = 'My activities';
$string['gantt_student_summary'] = '{$a->done}/{$a->total} steps completed';
$string['gantt_student_summary_all_done'] = 'All steps completed';
$string['gantt_student_status_pending'] = 'Pending';
$string['gantt_student_status_todo'] = 'To complete';
$string['gantt_student_status_done'] = 'Completed';
$string['gantt_student_cell_consult'] = 'View';
$string['gantt_student_cell_work'] = 'Work';
$string['gantt_student_cell_view_brief'] = 'View brief';
$string['gantt_student_provided_badge'] = 'Brief provided';
$string['gantt_student_grade_label'] = 'Grade: {$a}';
```

- [ ] **Step 3.2 : Ajouter les clés en français**

Dans `gestionprojet/lang/fr/gestionprojet.php`, à la même position (après `gantt_ungraded_summary_zero`, ligne ~849) :

```php
$string['gantt_student_row_consult'] = 'Consultation';
$string['gantt_student_row_work'] = 'Mes activités';
$string['gantt_student_summary'] = '{$a->done}/{$a->total} étapes complétées';
$string['gantt_student_summary_all_done'] = 'Toutes les étapes sont complétées';
$string['gantt_student_status_pending'] = 'En attente';
$string['gantt_student_status_todo'] = 'À compléter';
$string['gantt_student_status_done'] = 'Complété';
$string['gantt_student_cell_consult'] = 'Consulter';
$string['gantt_student_cell_work'] = 'Travailler';
$string['gantt_student_cell_view_brief'] = 'Voir la consigne';
$string['gantt_student_provided_badge'] = 'Consigne fournie';
$string['gantt_student_grade_label'] = 'Note : {$a}';
```

- [ ] **Step 3.3 : Vérifier la syntaxe PHP**

```bash
php -l gestionprojet/lang/en/gestionprojet.php
php -l gestionprojet/lang/fr/gestionprojet.php
```

Attendu : `No syntax errors detected` pour les deux.

- [ ] **Step 3.4 : Commit**

```bash
git add gestionprojet/lang/en/gestionprojet.php gestionprojet/lang/fr/gestionprojet.php
git commit -m "lang(gantt): add student Gantt view strings (en + fr)"
```

---

## Task 4 — Template Mustache `home_gantt_student.mustache`

**Files:**
- Create: `gestionprojet/templates/home_gantt_student.mustache`

- [ ] **Step 4.1 : Créer le template**

Créer `gestionprojet/templates/home_gantt_student.mustache` :

```mustache
{{!
    @template mod_gestionprojet/home_gantt_student

    Student home Gantt-style dashboard: 2 rows × 8 columns.
    Row 1: consultation (teacher docs steps 1, 3, 2 and provided briefs steps 4, 9)
    Row 2: student activities (steps 7, 4, 9, 5, 8, 6) — step 7 merged into column 3

    Context variables required:
    * cmid - Course module ID
    * columns - Array of 8 column header objects {stepnum, name, icon}
    * rowconsult - Array of 8 cell objects (row 1)
    * rowwork - Array of 8 cell objects (row 2)
    * summary - {done, total, allcomplete}

    Each cell object (filled): {isfilled:true, isenabled, iscomplete, name, url, isprovided?, hasgrade?, gradeformatted?}
    Each cell object (empty): {isfilled:false}
}}
<div class="gp-gantt gp-gantt-student" data-cmid="{{cmid}}">

    <div class="gp-gantt-summary {{#summary.allcomplete}}gp-summary-ok{{/summary.allcomplete}}">
        {{#summary.allcomplete}}
            {{#str}}gantt_student_summary_all_done, gestionprojet{{/str}}
        {{/summary.allcomplete}}
        {{^summary.allcomplete}}
            {{#str}}gantt_student_summary, gestionprojet, {"done": "{{summary.done}}", "total": "{{summary.total}}"}{{/str}}
        {{/summary.allcomplete}}
    </div>

    <div class="gp-gantt-grid">

        {{! Header row }}
        <div class="gp-gantt-corner"></div>
        {{#columns}}
            <div class="gp-col-head">
                <div class="gp-col-icon">{{{icon}}}</div>
                <div class="gp-col-name">{{name}}</div>
            </div>
        {{/columns}}

        {{! Row 1 — Consultation }}
        <div class="gp-row-label gp-row-label-docs">
            {{#str}}gantt_student_row_consult, gestionprojet{{/str}}
        </div>
        {{#rowconsult}}
            {{#isfilled}}
                <div class="gp-cell gp-cell-docs {{^isenabled}}gp-cell-disabled{{/isenabled}}">
                    <a href="{{#isenabled}}{{url}}{{/isenabled}}{{^isenabled}}#{{/isenabled}}" class="gp-cell-link gp-cell-link-nocheck" {{^isenabled}}aria-disabled="true" tabindex="-1"{{/isenabled}}>
                        {{#isenabled}}
                            {{#isprovided}}<div class="gp-cell-status gp-status-provided">{{#str}}gantt_student_provided_badge, gestionprojet{{/str}}</div>{{/isprovided}}
                            {{^isprovided}}
                                {{#iscomplete}}<div class="gp-cell-status gp-status-done">{{#str}}gantt_student_status_done, gestionprojet{{/str}}</div>{{/iscomplete}}
                                {{^iscomplete}}<div class="gp-cell-status gp-status-todo">{{#str}}gantt_student_status_pending, gestionprojet{{/str}}</div>{{/iscomplete}}
                            {{/isprovided}}
                            <div class="gp-cell-action">{{#str}}gantt_student_cell_consult, gestionprojet{{/str}}</div>
                        {{/isenabled}}
                        {{^isenabled}}<div class="gp-cell-status gp-status-disabled">{{#str}}gantt_cell_status_disabled, gestionprojet{{/str}}</div>{{/isenabled}}
                    </a>
                </div>
            {{/isfilled}}
            {{^isfilled}}<div class="gp-cell gp-cell-empty"></div>{{/isfilled}}
        {{/rowconsult}}

        {{! Row 2 — Student work }}
        <div class="gp-row-label gp-row-label-student">
            {{#str}}gantt_student_row_work, gestionprojet{{/str}}
        </div>
        {{#rowwork}}
            {{#isfilled}}
                <div class="gp-cell gp-cell-student {{^isenabled}}gp-cell-disabled{{/isenabled}}">
                    <a href="{{#isenabled}}{{url}}{{/isenabled}}{{^isenabled}}#{{/isenabled}}" class="gp-cell-link gp-cell-link-nocheck" {{^isenabled}}aria-disabled="true" tabindex="-1"{{/isenabled}}>
                        {{#isenabled}}
                            {{#iscomplete}}<div class="gp-cell-status gp-status-done">{{#str}}gantt_student_status_done, gestionprojet{{/str}}</div>{{/iscomplete}}
                            {{^iscomplete}}<div class="gp-cell-status gp-status-todo">{{#str}}gantt_student_status_todo, gestionprojet{{/str}}</div>{{/iscomplete}}
                            {{#hasgrade}}<div class="gp-cell-grade">{{#str}}gantt_student_grade_label, gestionprojet, {{gradeformatted}}{{/str}}</div>{{/hasgrade}}
                            <div class="gp-cell-action">{{#str}}gantt_student_cell_work, gestionprojet{{/str}}</div>
                        {{/isenabled}}
                        {{^isenabled}}<div class="gp-cell-status gp-status-disabled">{{#str}}gantt_cell_status_disabled, gestionprojet{{/str}}</div>{{/isenabled}}
                    </a>
                </div>
            {{/isfilled}}
            {{^isfilled}}<div class="gp-cell gp-cell-empty"></div>{{/isfilled}}
        {{/rowwork}}

    </div>
</div>
```

- [ ] **Step 4.2 : Commit**

```bash
git add gestionprojet/templates/home_gantt_student.mustache
git commit -m "feat(gantt): add student Gantt Mustache template

Two-row layout (consultation / student activities) with no checkboxes
and no correction-models row. Mirrors the teacher Gantt structure for
column alignment."
```

---

## Task 5 — CSS variantes pour le Gantt élève

**Files:**
- Modify: `gestionprojet/styles.css`

- [ ] **Step 5.1 : Ajouter les styles**

À la fin du bloc Gantt existant (après la ligne `.gp-cell-link-arrow { ... }` ~ligne 5015), ajouter :

```css

/* ============================================================
   Student Gantt view — variants without checkbox.
   ============================================================ */
.path-mod-gestionprojet .gp-gantt-student .gp-cell-link-nocheck {
    padding: 8px 6px 6px 6px;
}
.path-mod-gestionprojet .gp-gantt-student .gp-cell-action {
    margin-top: 6px;
    font-size: 11px;
    font-weight: 700;
    color: #4f46e5;
    text-decoration: underline;
}
.path-mod-gestionprojet .gp-gantt-student .gp-cell-student .gp-cell-action {
    color: #047857;
}
.path-mod-gestionprojet .gp-gantt-student .gp-cell-grade {
    font-size: 11px;
    color: #1f2937;
    font-weight: 600;
    margin-top: 2px;
}
.path-mod-gestionprojet .gp-gantt-student .gp-cell-link[aria-disabled="true"] {
    cursor: not-allowed;
    pointer-events: none;
}
.path-mod-gestionprojet .gp-gantt-student .gp-gantt-summary.gp-summary-ok {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border-color: #6ee7b7;
}
```

- [ ] **Step 5.2 : Vérifier la cohérence CSS**

```bash
grep -n "gp-gantt-student\|gp-cell-link-nocheck\|gp-cell-action\|gp-cell-grade" gestionprojet/styles.css
```

Attendu : voir les nouvelles règles listées.

- [ ] **Step 5.3 : Commit**

```bash
git add gestionprojet/styles.css
git commit -m "style(gantt): add student Gantt cell variants (no checkbox padding)"
```

---

## Task 6 — Construction des données dans `pages/home.php`

**Files:**
- Modify: `gestionprojet/pages/home.php` (branche student, lignes 290-380)

- [ ] **Step 6.1 : Lire le bloc actuel pour repérer la zone à remplacer**

Ouvrir `gestionprojet/pages/home.php` et localiser le bloc :

```php
} else {
    // Student section.
    if ($teacherpagescomplete && $usergroup == 0) {
        $templatecontext['nogrouperror'] = true;
    } else if ($teacherpagescomplete && $usergroup > 0) {
        // Safe retrieval of group info.
        $groupinfo = $DB->get_record('groups', ['id' => $usergroup]);

        if (!$groupinfo) {
            $templatecontext['groupnotfounderror'] = true;
            $templatecontext['groupnotfounderrorid'] = $usergroup;
        } else {
            $templatecontext['hasusergroup'] = true;
            $templatecontext['usergroupname'] = s($groupinfo->name);

            // ... existing code that builds $consultationsteps and $studentsteps ...
        }
    }
}
```

- [ ] **Step 6.2 : Remplacer le contenu du `else` (après `usergroupname`) par la construction du Gantt**

Remplacer tout le bloc à partir de `// Consultation steps (read-only for students).` jusqu'à la fin du `else` (avant la fermeture de la branche `groupinfo`) par :

```php
            // Consultation row data sources.
            $description = $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]);
            $besoin = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);
            $planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);
            $cdcfprovided = $DB->get_record('gestionprojet_cdcf_provided', ['gestionprojetid' => $gestionprojet->id]);
            $fastprovided = $DB->get_record('gestionprojet_fast_provided', ['gestionprojetid' => $gestionprojet->id]);

            // Student work data sources.
            $cdcf = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'cdcf');
            $essai = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'essai');
            $rapport = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'rapport');
            $besoineleve = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'besoin_eleve');
            $carnet = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'carnet');
            $fast = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'fast');

            // Helper: column "is enabled" check.
            $stepenabled = function($stepnum) use ($gestionprojet) {
                $field = 'enable_step' . $stepnum;
                $val = isset($gestionprojet->$field) ? (int)$gestionprojet->$field : 1;
                return $val !== 0;
            };

            // Helper: completion check for consultation steps (1, 2, 3).
            $consultcomplete = function($stepnum) use ($description, $besoin, $planning) {
                if ($stepnum === 1) {
                    return $description && !empty($description->intitule);
                }
                if ($stepnum === 2) {
                    return $besoin && !empty($besoin->aqui);
                }
                if ($stepnum === 3) {
                    return $planning && !empty($planning->projectname);
                }
                return false;
            };

            // Helper: provided brief completion (steps 4 and 9).
            $providedcomplete = function($stepnum) use ($cdcfprovided, $fastprovided) {
                if ($stepnum === 4) {
                    return $cdcfprovided && !empty($cdcfprovided->produit);
                }
                if ($stepnum === 9) {
                    if (!$fastprovided || empty($fastprovided->data_json)) {
                        return false;
                    }
                    $decoded = json_decode($fastprovided->data_json, true);
                    return is_array($decoded) && !empty($decoded['fonctions']);
                }
                return false;
            };

            // Helper: student work completion + grade.
            $workdata = function($stepnum) use ($cdcf, $essai, $rapport, $besoineleve, $carnet, $fast) {
                $rec = null;
                $complete = false;
                switch ($stepnum) {
                    case 4: $rec = $cdcf; $complete = $rec && !empty($rec->produit); break;
                    case 5: $rec = $essai; $complete = $rec && !empty($rec->objectif); break;
                    case 6: $rec = $rapport; $complete = $rec && !empty($rec->besoins); break;
                    case 7: $rec = $besoineleve; $complete = $rec && !empty($rec->aqui); break;
                    case 8: $rec = $carnet; $complete = $rec && !empty($rec->tasks_data); break;
                    case 9:
                        $rec = $fast;
                        if ($rec && !empty($rec->data_json)) {
                            $decoded = json_decode($rec->data_json, true);
                            $complete = is_array($decoded) && !empty($decoded['fonctions']);
                        }
                        break;
                }
                $grade = ($rec && $rec->grade !== null) ? (float)$rec->grade : null;
                return ['complete' => $complete, 'grade' => $grade];
            };

            // Build Gantt columns and cells.
            $ganttcolumndefs = gestionprojet_get_gantt_column_defs();
            $ganttcolumns = [];
            $rowconsult = [];
            $rowwork = [];
            $totaldone = 0;
            $totalwork = 0;

            foreach ($ganttcolumndefs as $coldef) {
                $stepnum = $coldef['stepnum'];
                $mergedwith = $coldef['mergedwith'];

                // Column header (primary step identity).
                $ganttcolumns[] = [
                    'stepnum' => $stepnum,
                    'name' => get_string('step' . $stepnum, 'gestionprojet'),
                    'icon' => icon::render_step($stepnum, 'sm', 'inherit'),
                ];

                // Row 1 — consultation cell.
                if (in_array($stepnum, [1, 2, 3], true)) {
                    $rowconsult[] = gestionprojet_build_student_gantt_cell([
                        'isfilled' => true,
                        'role' => 'consult',
                        'isenabled' => $stepenabled($stepnum),
                        'iscomplete' => $consultcomplete($stepnum),
                        'name' => get_string('step' . $stepnum, 'gestionprojet'),
                        'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnum]))->out(false),
                        'isprovided' => false,
                    ]);
                } else if ($stepnum === 4) {
                    $providedflag = isset($gestionprojet->step4_provided) ? (int)$gestionprojet->step4_provided : 0;
                    if ($providedflag === 1) {
                        $rowconsult[] = gestionprojet_build_student_gantt_cell([
                            'isfilled' => true,
                            'role' => 'consult',
                            'isenabled' => $stepenabled(4),
                            'iscomplete' => $providedcomplete(4),
                            'name' => get_string('step4', 'gestionprojet'),
                            'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 4, 'mode' => 'provided']))->out(false),
                            'isprovided' => true,
                        ]);
                    } else {
                        $rowconsult[] = ['isfilled' => false];
                    }
                } else if ($stepnum === 9) {
                    $providedflag = isset($gestionprojet->step9_provided) ? (int)$gestionprojet->step9_provided : 0;
                    if ($providedflag === 1) {
                        $rowconsult[] = gestionprojet_build_student_gantt_cell([
                            'isfilled' => true,
                            'role' => 'consult',
                            'isenabled' => $stepenabled(9),
                            'iscomplete' => $providedcomplete(9),
                            'name' => get_string('step9', 'gestionprojet'),
                            'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 9, 'mode' => 'provided']))->out(false),
                            'isprovided' => true,
                        ]);
                    } else {
                        $rowconsult[] = ['isfilled' => false];
                    }
                } else {
                    $rowconsult[] = ['isfilled' => false];
                }

                // Row 2 — student work cell. Use mergedwith if set (column 3 → step 7).
                $workstep = $mergedwith !== null ? $mergedwith : $stepnum;
                if (in_array($workstep, [4, 5, 6, 7, 8, 9], true)) {
                    $work = $workdata($workstep);
                    $isenabled = $stepenabled($workstep);
                    if ($isenabled) {
                        $totalwork++;
                        if ($work['complete']) {
                            $totaldone++;
                        }
                    }
                    $rowwork[] = gestionprojet_build_student_gantt_cell([
                        'isfilled' => true,
                        'role' => 'work',
                        'isenabled' => $isenabled,
                        'iscomplete' => $work['complete'],
                        'name' => get_string('step' . $workstep, 'gestionprojet'),
                        'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $workstep]))->out(false),
                        'grade' => $work['grade'],
                    ]);
                } else {
                    $rowwork[] = ['isfilled' => false];
                }
            }

            $templatecontext['gantt_student'] = [
                'columns' => $ganttcolumns,
                'rowconsult' => $rowconsult,
                'rowwork' => $rowwork,
                'cmid' => $cm->id,
                'summary' => [
                    'done' => $totaldone,
                    'total' => $totalwork,
                    'allcomplete' => $totalwork > 0 && $totaldone === $totalwork,
                ],
            ];
```

- [ ] **Step 6.3 : Vérifier la syntaxe PHP**

```bash
php -l gestionprojet/pages/home.php
```

Attendu : `No syntax errors detected`.

- [ ] **Step 6.4 : Commit**

```bash
git add gestionprojet/pages/home.php
git commit -m "feat(gantt): build student Gantt context data in home.php

Replaces the consultationsteps + studentsteps card arrays with a
single gantt_student structure (columns, rowconsult, rowwork, summary)
ready for the new template."
```

---

## Task 7 — Intégration template `home.mustache`

**Files:**
- Modify: `gestionprojet/templates/home.mustache`

- [ ] **Step 7.1 : Remplacer le bloc des cartes élèves**

Dans `gestionprojet/templates/home.mustache`, repérer le bloc dans la branche `^isteacher` puis `{{#hasusergroup}}` :

```mustache
                <div class="gestionprojet-cards">
                    {{! Consultation steps (read-only for students) }}
                    {{#consultationsteps}}
                        ...
                    {{/consultationsteps}}

                    {{! Student work steps }}
                    {{#studentsteps}}
                        ...
                    {{/studentsteps}}
                </div>
```

Remplacer **uniquement** le bloc `<div class="gestionprojet-cards">...</div>` (lignes ~139-186) par :

```mustache
                {{#gantt_student}}
                    {{> mod_gestionprojet/home_gantt_student}}
                {{/gantt_student}}
```

Conserver les blocs au-dessus (`{{#nogrouperror}}`, `{{#groupnotfounderror}}`, l'alerte « Vous travaillez en groupe »).

- [ ] **Step 7.2 : Mettre à jour la doc en tête du template**

Toujours dans `gestionprojet/templates/home.mustache`, dans le commentaire `{{!`, retirer ou marquer obsolètes les références à `consultationsteps` et `studentsteps`, ajouter `gantt_student`. Cible la ligne 21-22 et 56-67 ; remplacer ces deux entrées par :

```mustache
    * gantt_student - Student Gantt context (only for student with group):
    *   * columns, rowconsult, rowwork, summary, cmid
    *   * see home_gantt_student.mustache for cell structure
```

- [ ] **Step 7.3 : Purger les caches Moodle (sur preprod après déploiement)**

```bash
ssh favi5410@favi5410.odns.fr 'cd /preprod.ent-occitanie.com && php admin/cli/purge_caches.php'
```

(À faire à l'étape de déploiement, pas en local.)

- [ ] **Step 7.4 : Commit**

```bash
git add gestionprojet/templates/home.mustache
git commit -m "feat(gantt): include student Gantt template in home view"
```

---

## Task 8 — Bump de version

**Files:**
- Modify: `gestionprojet/version.php`

- [ ] **Step 8.1 : Lire la version actuelle**

```bash
grep -n "version\|release" gestionprojet/version.php
```

- [ ] **Step 8.2 : Incrémenter `version` et `release`**

Version actuelle : `2026050501` / release `2.5.0`.

Dans `gestionprojet/version.php`, mettre à jour :

```php
$plugin->version = 2026050502;  // YYYYMMDDXX format
$plugin->release = '2.6.0';
```

Aucune migration DB n'est nécessaire (pas de changement de `install.xml`).

- [ ] **Step 8.3 : Commit**

```bash
git add gestionprojet/version.php
git commit -m "chore(version): bump to 2.6.0 (2026050502) for student Gantt view"
```

---

## Task 9 — Déploiement preprod et validation visuelle

**Files:** aucun (déploiement et test).

- [ ] **Step 9.1 : Déployer sur preprod via SCP**

Selon `TESTING.md` :

```bash
# Synchroniser le dossier plugin
rsync -avz --delete \
  --exclude '.git*' --exclude '.claude*' --exclude 'lessons.md' \
  gestionprojet/ \
  favi5410@favi5410.odns.fr:/preprod.ent-occitanie.com/mod/gestionprojet/
```

- [ ] **Step 9.2 : Lancer l'upgrade Moodle (pour le bump version)**

```bash
ssh favi5410@favi5410.odns.fr 'cd /preprod.ent-occitanie.com && php admin/cli/upgrade.php --non-interactive'
```

- [ ] **Step 9.3 : Purger les caches**

```bash
ssh favi5410@favi5410.odns.fr 'cd /preprod.ent-occitanie.com && php admin/cli/purge_caches.php'
```

- [ ] **Step 9.4 : Lancer les tests PHPUnit sur preprod**

```bash
ssh favi5410@favi5410.odns.fr 'cd /preprod.ent-occitanie.com && vendor/bin/phpunit --group mod_gestionprojet --filter mod_gestionprojet_gantt_helpers_test'
```

Attendu : 10 tests OK.

- [ ] **Step 9.5 : Validation visuelle élève**

Se connecter en tant que `3a1` / `3a1@Preprod2026` sur https://preprod.ent-occitanie.com/, ouvrir l'activité gestionprojet du cours TEST (id=2), et vérifier :

  - [ ] Le bandeau « Vous travaillez en groupe : 3A » est toujours présent
  - [ ] Le Gantt 2 lignes × 8 colonnes s'affiche
  - [ ] Ligne 1 : steps 1, 3, 2 visibles avec statut « Complété »/« En attente »
  - [ ] Ligne 1 : si `step4_provided=1`, la cellule colonne 4 montre le badge « Consigne fournie »
  - [ ] Ligne 1 : si `step9_provided=1`, la cellule colonne 9 montre le badge
  - [ ] Ligne 1 : steps 5, 8, 6 ont des cellules vides
  - [ ] Ligne 2 : tous les steps élève actifs sont visibles avec statut « À compléter » / « Complété »
  - [ ] Ligne 2 : note `X.X / 20` affichée si la submission est notée
  - [ ] Aucune case à cocher ne s'affiche
  - [ ] Aucune flèche `↑` ne s'affiche
  - [ ] Le résumé affiche `X / Y étapes complétées` (ou « Toutes les étapes sont complétées » si X=Y)
  - [ ] Cliquer sur une cellule de la ligne 2 mène à la page step correspondante
  - [ ] Cliquer sur une cellule de la ligne 1 mène à la page de consultation correspondante

- [ ] **Step 9.6 : Validation visuelle non-régression enseignant**

Se connecter en tant que `prof` / `Prof@Preprod2026`, ouvrir la même activité, vérifier :

  - [ ] Le Gantt enseignant 3 lignes × 8 colonnes s'affiche
  - [ ] Les checkboxes fonctionnent (toggle activate/désactiver)
  - [ ] Le résumé `X/Y phases configurées` est inchangé
  - [ ] Les cellules cliquables mènent aux bonnes pages

- [ ] **Step 9.7 : Cas désactivation**

En tant qu'enseignant, désactiver Step 5 via la mod_form. Reconnecter en tant que `3a1` :

  - [ ] Colonne 5 visible mais grisée sur les 2 lignes
  - [ ] Statut « Désactivée » affiché
  - [ ] Le lien sur la cellule grisée n'est pas cliquable (`aria-disabled`)

- [ ] **Step 9.8 : Cas tablet/mobile**

Réduire la fenêtre du navigateur ou utiliser DevTools mode tablette :

  - [ ] L'overflow horizontal est actif (scrolling) si l'écran est étroit
  - [ ] Les cellules restent lisibles

- [ ] **Step 9.9 : Si tout est OK, marquer le plan complet**

```bash
git push origin main:main
```

(Le push vers Forge EDU n'auto-déploie pas — la preprod est déjà à jour via SCP.)

---

## Récapitulatif des commits attendus

```
refactor(gantt): extract column definitions to lib.php helper
feat(gantt): add pure helper to build student Gantt cells
lang(gantt): add student Gantt view strings (en + fr)
feat(gantt): add student Gantt Mustache template
style(gantt): add student Gantt cell variants (no checkbox padding)
feat(gantt): build student Gantt context data in home.php
feat(gantt): include student Gantt template in home view
chore(version): bump to 2.6.0 for student Gantt view
```

Soit 8 commits, plus le tag de release si souhaité.
