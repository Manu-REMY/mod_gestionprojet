# Consigne fiche essai (step 5) — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter une fiche consigne pour la fiche essai (step 5), calquée sur le pattern CDCF (step 4) / FAST (step 9) : l'enseignant remplit une consigne lecture-seule pour l'élève, dont le contenu est seedé au premier accès dans la fiche essai éditable de l'élève.

**Architecture:** Nouvelle table `gestionprojet_essai_provided` + flag `step5_provided` + page `pages/step5_provided.php` polymorphique (édition enseignant / lecture seule élève). Seeding dans `gestionprojet_get_or_create_submission` quand la fiche élève est vide. Activation depuis le Gantt home (pas de mod_form). Strict respect de la checklist Moodle (cf. `CLAUDE.md`).

**Tech Stack:** Moodle 5.0+ (PHP 8.1+), Moodle DML (`$DB`), XMLDB schema, AMD/RequireJS, Mustache templates. Pas de tests unitaires PHPUnit dans le repo — la vérification se fait par déploiement preprod (TESTING.md) et test manuel.

---

## File Structure

**Fichiers créés :**
- `gestionprojet/pages/step5_provided.php` — Page consigne enseignant (lecture seule pour élève)

**Fichiers modifiés :**
- `gestionprojet/db/install.xml` — schema (table + champ)
- `gestionprojet/db/upgrade.php` — migration
- `gestionprojet/version.php` — bump
- `gestionprojet/lib.php` — seeding + delete + tabs builder
- `gestionprojet/pages/step5.php` — fallback parsing `precautions`
- `gestionprojet/pages/home.php` — cellules Gantt step 5 (row docs + row consult)
- `gestionprojet/view.php` — routing `mode=provided`
- `gestionprojet/ajax/autosave.php` — whitelist `essai_provided`
- `gestionprojet/ajax/toggle_step.php` — autoriser `step=5` avec `flag=provided`
- `gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php` — backup
- `gestionprojet/backup/moodle2/restore_gestionprojet_stepslib.php` — restore
- `gestionprojet/lang/fr/gestionprojet.php` + `gestionprojet/lang/en/gestionprojet.php` — strings

---

### Task 1: Schéma de base — install.xml

**Files:**
- Modify: `gestionprojet/db/install.xml`

- [ ] **Step 1: Ajouter le champ `step5_provided` après `step9_provided`**

Dans `gestionprojet/db/install.xml`, repérer la ligne 30 (`step9_provided`) et ajouter une ligne juste après :

```xml
        <FIELD NAME="step5_provided" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="0" SEQUENCE="false" COMMENT="When 1, the teacher's Essai consigne is displayed read-only to students and seeded into their editable essai on first access."/>
```

- [ ] **Step 2: Ajouter la nouvelle table `gestionprojet_essai_provided`**

Ouvrir `gestionprojet/db/install.xml`, repérer la fin du bloc `<TABLE NAME="gestionprojet_fast_provided">` (ligne ~405). Insérer juste après ce bloc, avant la table FAST teacher correction model :

```xml
    <!-- Table: Essai teacher-provided consigne (read-only reference for students, seeded into student record) - Step 5 -->
    <TABLE NAME="gestionprojet_essai_provided" COMMENT="Teacher-provided Essai consigne (no ai_instructions, no dates) — seeded into student essai on first access">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="gestionprojetid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="nom_essai" TYPE="char" LENGTH="255" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="date_essai" TYPE="char" LENGTH="20" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="groupe_eleves" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="objectif" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="fonction_service" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="niveaux_reussite" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="etapes_protocole" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="materiel_outils" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="precautions" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="resultats_obtenus" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="observations_remarques" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="conclusion" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="gestionprojetid" TYPE="foreign-unique" FIELDS="gestionprojetid" REFTABLE="gestionprojet" REFFIELDS="id"/>
      </KEYS>
    </TABLE>
```

- [ ] **Step 3: Vérifier la syntaxe XML**

Run: `xmllint --noout "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/db/install.xml"`
Expected: aucune sortie (XML valide)

- [ ] **Step 4: Commit**

```bash
git add gestionprojet/db/install.xml
git commit -m "feat(db): add essai_provided table and step5_provided flag"
```

---

### Task 2: Migration — upgrade.php

**Files:**
- Modify: `gestionprojet/db/upgrade.php`

- [ ] **Step 1: Ajouter un nouveau bloc d'upgrade à la fin (avant `return true`)**

Repérer la dernière ligne `return true;` à la fin de `xmldb_gestionprojet_upgrade` (ligne ~673). Insérer juste avant ce `return true;` :

```php
    if ($oldversion < 2026050700) {
        // Add Essai consigne support: new flag step5_provided + new table gestionprojet_essai_provided.

        // Add step5_provided flag to gestionprojet table (after step9_provided).
        $maintable = new xmldb_table('gestionprojet');
        $field = new xmldb_field(
            'step5_provided',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'step9_provided'
        );
        if (!$dbman->field_exists($maintable, $field)) {
            $dbman->add_field($maintable, $field);
        }

        // Create gestionprojet_essai_provided.
        $essaiprovided = new xmldb_table('gestionprojet_essai_provided');
        if (!$dbman->table_exists($essaiprovided)) {
            $essaiprovided->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $essaiprovided->add_field('gestionprojetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $essaiprovided->add_field('nom_essai', XMLDB_TYPE_CHAR, '255', null, null, null);
            $essaiprovided->add_field('date_essai', XMLDB_TYPE_CHAR, '20', null, null, null);
            $essaiprovided->add_field('groupe_eleves', XMLDB_TYPE_TEXT, null, null, null, null);
            $essaiprovided->add_field('objectif', XMLDB_TYPE_TEXT, null, null, null, null);
            $essaiprovided->add_field('fonction_service', XMLDB_TYPE_TEXT, null, null, null, null);
            $essaiprovided->add_field('niveaux_reussite', XMLDB_TYPE_TEXT, null, null, null, null);
            $essaiprovided->add_field('etapes_protocole', XMLDB_TYPE_TEXT, null, null, null, null);
            $essaiprovided->add_field('materiel_outils', XMLDB_TYPE_TEXT, null, null, null, null);
            $essaiprovided->add_field('precautions', XMLDB_TYPE_TEXT, null, null, null, null);
            $essaiprovided->add_field('resultats_obtenus', XMLDB_TYPE_TEXT, null, null, null, null);
            $essaiprovided->add_field('observations_remarques', XMLDB_TYPE_TEXT, null, null, null, null);
            $essaiprovided->add_field('conclusion', XMLDB_TYPE_TEXT, null, null, null, null);
            $essaiprovided->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $essaiprovided->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $essaiprovided->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $essaiprovided->add_key('gestionprojetid', XMLDB_KEY_FOREIGN_UNIQUE, ['gestionprojetid'], 'gestionprojet', ['id']);
            $dbman->create_table($essaiprovided);
        }

        upgrade_mod_savepoint(true, 2026050700, 'gestionprojet');
    }
```

- [ ] **Step 2: Vérifier la syntaxe PHP**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/db/upgrade.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add gestionprojet/db/upgrade.php
git commit -m "feat(db): upgrade adds essai_provided table and step5_provided flag"
```

---

### Task 3: Bump version

**Files:**
- Modify: `gestionprojet/version.php`

- [ ] **Step 1: Mettre à jour version + release**

Remplacer dans `gestionprojet/version.php` :
```php
$plugin->version = 2026050604;  // YYYYMMDDXX format
```
par :
```php
$plugin->version = 2026050700;  // YYYYMMDDXX format
```

Et :
```php
$plugin->release = '2.7.3';
```
par :
```php
$plugin->release = '2.8.0';
```

- [ ] **Step 2: Vérifier la syntaxe PHP**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/version.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add gestionprojet/version.php
git commit -m "chore(release): bump to 2.8.0 (essai consigne)"
```

---

### Task 4: Logique de seeding et delete_instance dans lib.php

**Files:**
- Modify: `gestionprojet/lib.php` (lib.php:160 pour delete_instance, lib.php:288 zone seeding, lib.php:1517-1590 pour build_step_tabs)

- [ ] **Step 1: Ajouter delete_records dans `gestionprojet_delete_instance`**

Dans `gestionprojet/lib.php`, repérer la ligne 160 :
```php
    $DB->delete_records('gestionprojet_fast_provided', ['gestionprojetid' => $id]);
```

Insérer **juste après** :
```php
    $DB->delete_records('gestionprojet_essai_provided', ['gestionprojetid' => $id]);
```

- [ ] **Step 2: Ajouter le bloc de seeding dans `gestionprojet_get_or_create_submission`**

Dans `gestionprojet/lib.php`, repérer la fin du bloc CDCF (ligne ~300, juste avant le `return $record;` de la fonction). Insérer juste avant `return $record;` :

```php
    // For Essai phase: when teacher provides a consigne, seed student submission with it.
    // "Empty" means all main text fields are blank — initial creation or never touched.
    // Once the student saves any of these fields, no more seeding (predictable behavior).
    if ($table === 'essai' && (int)($gestionprojet->step5_provided ?? 0) === 1) {
        $checkfields = [
            'fonction_service', 'niveaux_reussite', 'etapes_protocole',
            'materiel_outils', 'precautions', 'resultats_obtenus',
            'observations_remarques', 'conclusion', 'objectif',
        ];
        $isempty = true;
        foreach ($checkfields as $f) {
            if (!empty(trim((string)($record->{$f} ?? '')))) {
                $isempty = false;
                break;
            }
        }
        if ($isempty) {
            $provided = $DB->get_record('gestionprojet_essai_provided',
                ['gestionprojetid' => $gestionprojet->id]);
            if ($provided) {
                // Copy all consigne fields (text + meta) into the student record.
                $copyfields = array_merge($checkfields, ['nom_essai', 'date_essai', 'groupe_eleves']);
                $changed = false;
                foreach ($copyfields as $f) {
                    if (!empty($provided->{$f} ?? '')) {
                        $record->{$f} = $provided->{$f};
                        $changed = true;
                    }
                }
                if ($changed) {
                    $record->timemodified = time();
                    $DB->update_record('gestionprojet_essai', $record);
                }
            }
        }
    }
```

- [ ] **Step 3: Étendre `gestionprojet_build_step_tabs` pour inclure step 5 dans les consignes**

Dans `gestionprojet/lib.php`, repérer ligne 1530 :
```php
    if ($context === 'consignes') {
        $order = [1, 3, 2, 4, 9];
```
Remplacer par :
```php
    if ($context === 'consignes') {
        $order = [1, 3, 2, 4, 9, 5];
```

Repérer ligne 1547 :
```php
        $isdualstep = in_array($stepnum, [4, 9], true);
```
Remplacer par :
```php
        $isdualstep = in_array($stepnum, [4, 5, 9], true);
```

- [ ] **Step 4: Vérifier la syntaxe PHP**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/lib.php"`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add gestionprojet/lib.php
git commit -m "feat(lib): seeding essai_provided + tabs/delete cleanup"
```

---

### Task 5: Routing — view.php

**Files:**
- Modify: `gestionprojet/view.php` (view.php:124-138)

- [ ] **Step 1: Étendre `$providedaccess` pour step 5**

Dans `gestionprojet/view.php`, repérer ligne 125 :
```php
        $providedaccess = ($step === 4 && (int)($gestionprojet->step4_provided ?? 0) === 1)
            || ($step === 9 && (int)($gestionprojet->step9_provided ?? 0) === 1);
```
Remplacer par :
```php
        $providedaccess = ($step === 4 && (int)($gestionprojet->step4_provided ?? 0) === 1)
            || ($step === 5 && (int)($gestionprojet->step5_provided ?? 0) === 1)
            || ($step === 9 && (int)($gestionprojet->step9_provided ?? 0) === 1);
```

- [ ] **Step 2: Étendre la liste des dual steps pour `mode=provided`**

Dans `gestionprojet/view.php`, repérer ligne 135 :
```php
    if ($mode === 'provided' && in_array($step, [4, 9], true)) {
```
Remplacer par :
```php
    if ($mode === 'provided' && in_array($step, [4, 5, 9], true)) {
```

- [ ] **Step 3: Vérifier la syntaxe PHP**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/view.php"`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add gestionprojet/view.php
git commit -m "feat(routing): allow mode=provided for step 5"
```

---

### Task 6: Page consigne enseignant — step5_provided.php + AMD module

**Files:**
- Create: `gestionprojet/pages/step5_provided.php`
- Create: `gestionprojet/amd/src/essai_provided.js`
- Create: `gestionprojet/amd/build/essai_provided.min.js` (rebuild via grunt OR manuelle non-minifiée)

- [ ] **Step 1: Créer la page**

Créer le fichier `gestionprojet/pages/step5_provided.php` avec ce contenu complet :

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
 * Step 5 Teacher Consigne (Provided Document): Essai (Test Sheet).
 *
 * This page lets the teacher fill in the Essai consigne (objective, protocol,
 * results, conclusion). The same content is shown read-only to students, AND
 * is seeded into the student's editable essai on first access (see
 * gestionprojet_get_or_create_submission in lib.php).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_gestionprojet\output\icon;

// Read-only when the user lacks teacher-edit capability — students see the brief but cannot edit it.
$canedit = has_capability('mod/gestionprojet:configureteacherpages', $context);
$readonly = !$canedit;

// Page setup.
$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 5, 'mode' => 'provided']);
$PAGE->set_title(get_string('step5', 'gestionprojet') . ' — ' . get_string('consigne', 'gestionprojet'));

// Get or create the provided consigne record.
$model = $DB->get_record('gestionprojet_essai_provided', ['gestionprojetid' => $gestionprojet->id]);
if (!$model) {
    $model = new stdClass();
    $model->gestionprojetid = $gestionprojet->id;
    $model->timecreated = time();
    $model->timemodified = time();
    $model->id = $DB->insert_record('gestionprojet_essai_provided', $model);
    $model = $DB->get_record('gestionprojet_essai_provided', ['id' => $model->id]);
}

echo $OUTPUT->header();

// Tabs: teacher gets consignes navigation; student gets work navigation.
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 5, $canedit ? 'consignes' : 'student')
);
echo $OUTPUT->heading(get_string('step5', 'gestionprojet') . ' — ' . get_string('consigne', 'gestionprojet'));

echo '<div class="alert alert-info">';
echo '<h4>' . get_string('step5_desc_title', 'gestionprojet') . '</h4>';
echo '<p>' . get_string('step5_desc_text', 'gestionprojet') . '</p>';
echo '</div>';

require_once(__DIR__ . '/teacher_model_styles.php');

// Get navigation for teacher consigne steps (only meaningful for editors).
$stepnav = $canedit ? gestionprojet_get_teacher_step_navigation($gestionprojet, 5) : ['prev' => null, 'next' => null];
?>

<div class="teacher-model-container gp-consigne">

    <?php if ($readonly): ?>
    <div class="gp-fast-readonly">
    <?php endif; ?>

    <form id="essaiProvidedForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="step" value="5">
        <input type="hidden" name="mode" value="provided">

        <div class="model-form-section">
            <h3><?php echo icon::render('flask-conical', 'sm', 'purple'); ?> <?php echo get_string('step5', 'gestionprojet'); ?></h3>

            <div class="form-group">
                <label for="nom_essai"><?php echo get_string('nom_essai', 'gestionprojet'); ?></label>
                <input type="text" id="nom_essai" name="nom_essai" value="<?php echo s($model->nom_essai ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="objectif"><?php echo get_string('objectif', 'gestionprojet'); ?></label>
                <textarea id="objectif" name="objectif"><?php echo s($model->objectif ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="fonction_service"><?php echo get_string('fonction_service', 'gestionprojet'); ?></label>
                <textarea id="fonction_service" name="fonction_service"><?php echo s($model->fonction_service ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="niveaux_reussite"><?php echo get_string('niveaux_reussite', 'gestionprojet'); ?></label>
                <textarea id="niveaux_reussite" name="niveaux_reussite"><?php echo s($model->niveaux_reussite ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="etapes_protocole"><?php echo get_string('etapes_protocole', 'gestionprojet'); ?></label>
                <textarea id="etapes_protocole" name="etapes_protocole"><?php echo s($model->etapes_protocole ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="materiel_outils"><?php echo get_string('materiel_outils', 'gestionprojet'); ?></label>
                <textarea id="materiel_outils" name="materiel_outils"><?php echo s($model->materiel_outils ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="precautions"><?php echo get_string('precautions', 'gestionprojet'); ?></label>
                <textarea id="precautions" name="precautions" placeholder="<?php echo s(get_string('step5_provided_precautions_placeholder', 'gestionprojet')); ?>"><?php echo s($model->precautions ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="resultats_obtenus"><?php echo get_string('resultats_obtenus', 'gestionprojet'); ?></label>
                <textarea id="resultats_obtenus" name="resultats_obtenus"><?php echo s($model->resultats_obtenus ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="observations_remarques"><?php echo get_string('observations_remarques', 'gestionprojet'); ?></label>
                <textarea id="observations_remarques" name="observations_remarques"><?php echo s($model->observations_remarques ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="conclusion"><?php echo get_string('conclusion', 'gestionprojet'); ?></label>
                <textarea id="conclusion" name="conclusion"><?php echo s($model->conclusion ?? ''); ?></textarea>
            </div>
        </div>

        <?php if (!$readonly): ?>
        <div class="save-section">
            <button type="button" class="btn-save" id="saveButton">
                <?php echo icon::render('save', 'sm', 'inherit'); ?> <?php echo get_string('save', 'gestionprojet'); ?>
            </button>
            <div id="saveStatus" class="save-status"></div>
        </div>

        <!-- Step navigation (editor only). -->
        <div class="step-navigation">
            <?php if ($stepnav['prev']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['prev'], 'mode' => 'provided']); ?>" class="btn-nav btn-prev">
                <?php echo icon::render('chevron-left', 'sm', 'inherit'); ?> <?php echo get_string('previous', 'gestionprojet'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>

            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]); ?>" class="btn-nav btn-hub">
                <?php echo get_string('consigne', 'gestionprojet'); ?>
            </a>

            <?php if ($stepnav['next']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['next'], 'mode' => 'provided']); ?>" class="btn-nav btn-next">
                <?php echo get_string('next', 'gestionprojet'); ?> <?php echo icon::render('chevron-right', 'sm', 'inherit'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($readonly): ?>
    </div>
    <?php endif; ?>

</div>

<?php
// Wire autosave (editor only — readonly mode posts nothing).
if (!$readonly) {
    $PAGE->requires->js_call_amd('mod_gestionprojet/essai_provided', 'init', [[
        'cmid' => (int)$cm->id,
        'autosaveInterval' => (int)($gestionprojet->autosave_interval ?? 30) * 1000,
    ]]);
}

echo $OUTPUT->footer();
```

- [ ] **Step 2: Créer le module AMD source**

Créer `gestionprojet/amd/src/essai_provided.js` :

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
 * Autosave + save button glue for the Essai consigne page (mode=provided).
 *
 * @module     mod_gestionprojet/essai_provided
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'mod_gestionprojet/autosave'], function($, Autosave) {
    return {
        /**
         * Initialise autosave on the consigne form and wire the manual save button.
         *
         * @param {Object} cfg
         * @param {number} cfg.cmid
         * @param {number} cfg.autosaveInterval Interval in milliseconds.
         */
        init: function(cfg) {
            Autosave.init({
                cmid: cfg.cmid,
                step: 5,
                groupid: 0,
                mode: 'provided',
                interval: cfg.autosaveInterval || 30000,
                formSelector: '#essaiProvidedForm'
            });

            var saveButton = document.getElementById('saveButton');
            if (saveButton) {
                saveButton.addEventListener('click', function() {
                    Autosave.save();
                });
            }
        }
    };
});
```

- [ ] **Step 3: Construire le module AMD**

Si `grunt` est disponible :
```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet" && grunt amd
```
Expected: `gestionprojet/amd/build/essai_provided.min.js` est créé.

Si `grunt` n'est PAS disponible : créer manuellement `gestionprojet/amd/build/essai_provided.min.js` comme une **copie identique du source** (Moodle accepte des builds non minifiés en dev). Le contenu doit être strictement le même que `essai_provided.js`.

Vérifier que le build existe :
```bash
ls -la "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/amd/build/essai_provided.min.js"
```
Expected: fichier présent.

- [ ] **Step 4: Vérifier la syntaxe PHP**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step5_provided.php"`
Expected: `No syntax errors detected`

- [ ] **Step 5: Vérifier l'absence de `<style>` ou `<script>` inline dans la page PHP**

Run: `grep -n '<style\|<script' "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step5_provided.php"`
Expected: aucune sortie

- [ ] **Step 6: Commit**

```bash
git add gestionprojet/pages/step5_provided.php gestionprojet/amd/src/essai_provided.js gestionprojet/amd/build/essai_provided.min.js
git commit -m "feat(step5): teacher consigne page (provided mode) + AMD module"
```

---

### Task 7: Adapter pages/step5.php — fallback parsing precautions

**Files:**
- Modify: `gestionprojet/pages/step5.php` (step5.php:117-120)

- [ ] **Step 1: Adapter le parsing de `precautions`**

Dans `gestionprojet/pages/step5.php`, repérer le bloc actuel (ligne 117-120) :
```php
    // Parse precautions (stored as JSON array)
    $precautions = [];
    if ($submission->precautions) {
        $precautions = json_decode($submission->precautions, true) ?? [];
    }
```

Remplacer par :
```php
    // Parse precautions: historically stored as a JSON array of 6 strings (one per cell).
    // When seeded from the consigne (essai_provided.precautions), the value is a
    // free-form text — split on newlines and clamp to 6 entries for back-compat with
    // the 6-cell student layout. Trailing/empty lines are kept positionally to preserve
    // the cell mapping.
    $precautions = [];
    if (!empty($submission->precautions)) {
        $decoded = json_decode($submission->precautions, true);
        if (is_array($decoded)) {
            $precautions = $decoded;
        } else {
            $lines = preg_split('/\r\n|\r|\n/', $submission->precautions);
            $precautions = array_slice($lines, 0, 6);
        }
    }
```

- [ ] **Step 2: Vérifier la syntaxe PHP**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step5.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add gestionprojet/pages/step5.php
git commit -m "fix(step5): tolerate plain-text precautions when seeded from consigne"
```

---

### Task 8: Étendre ajax/autosave.php

**Files:**
- Modify: `gestionprojet/ajax/autosave.php` (autosave.php:73-76)

- [ ] **Step 1: Ajouter step 5 dans `$providedtables`**

Dans `gestionprojet/ajax/autosave.php`, repérer ligne 73 :
```php
        $providedtables = [
            4 => ['table' => 'gestionprojet_cdcf_provided', 'fields' => ['interacteurs_data']],
            9 => ['table' => 'gestionprojet_fast_provided', 'fields' => ['data_json']],
        ];
```

Remplacer par :
```php
        $providedtables = [
            4 => ['table' => 'gestionprojet_cdcf_provided', 'fields' => ['interacteurs_data']],
            5 => ['table' => 'gestionprojet_essai_provided', 'fields' => [
                'nom_essai', 'date_essai', 'groupe_eleves', 'objectif',
                'fonction_service', 'niveaux_reussite',
                'etapes_protocole', 'materiel_outils', 'precautions',
                'resultats_obtenus', 'observations_remarques', 'conclusion',
            ]],
            9 => ['table' => 'gestionprojet_fast_provided', 'fields' => ['data_json']],
        ];
```

- [ ] **Step 2: Vérifier la syntaxe PHP**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/autosave.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add gestionprojet/ajax/autosave.php
git commit -m "feat(autosave): whitelist essai_provided fields"
```

---

### Task 9: Étendre ajax/toggle_step.php

**Files:**
- Modify: `gestionprojet/ajax/toggle_step.php` (toggle_step.php:57)

- [ ] **Step 1: Élargir la liste des steps autorisés pour `flag=provided`**

Dans `gestionprojet/ajax/toggle_step.php`, repérer ligne 57 :
```php
if ($flag === 'provided' && !in_array($stepnum, [4, 9], true)) {
    throw new \moodle_exception('invalidparameter', 'error');
}
```

Remplacer par :
```php
if ($flag === 'provided' && !in_array($stepnum, [4, 5, 9], true)) {
    throw new \moodle_exception('invalidparameter', 'error');
}
```

- [ ] **Step 2: Vérifier la syntaxe PHP**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/toggle_step.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add gestionprojet/ajax/toggle_step.php
git commit -m "feat(toggle): allow provided flag for step 5"
```

---

### Task 10: Cellules Gantt step 5 — home.php

**Files:**
- Modify: `gestionprojet/pages/home.php` (home.php:165-210 zone enseignant, home.php:416-448 zone élève, home.php:317-318 chargement record)

- [ ] **Step 1: Ajouter une row docs cell pour step 5 (vue enseignant)**

Dans `gestionprojet/pages/home.php`, repérer la fin du bloc step 9 (ligne ~210, juste avant le `} else {` qui correspond au cas par défaut) :

```php
        } else if ($stepnum === 9) {
            // ...
            $rowdocs[] = [
                ...
                'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 9, 'mode' => 'provided']))->out(false),
            ];
        } else {
            $rowdocs[] = ['isfilled' => false];
        }
```

Insérer un nouveau `else if` pour step 5 **juste avant** le `} else {` final :

```php
        } else if ($stepnum === 5) {
            // Special case: Essai row 1 cell controls step5_provided (teacher-provided consigne).
            $providedval = isset($gestionprojet->step5_provided) ? (int)$gestionprojet->step5_provided : 0;
            $providedenabled = ($providedval === 1);
            $providedrec = $DB->get_record('gestionprojet_essai_provided', ['gestionprojetid' => $gestionprojet->id]);
            // "Complete" = at least one consigne field filled.
            $providedcomplete = false;
            if ($providedrec) {
                foreach (['fonction_service', 'niveaux_reussite', 'etapes_protocole',
                          'materiel_outils', 'precautions', 'resultats_obtenus',
                          'observations_remarques', 'conclusion', 'objectif'] as $f) {
                    if (!empty(trim((string)($providedrec->{$f} ?? '')))) {
                        $providedcomplete = true;
                        break;
                    }
                }
            }
            if ($providedenabled) {
                $totalconfigtargets++;
                if ($providedcomplete) {
                    $totalconfigured++;
                }
            }
            $rowdocs[] = [
                'stepnum' => 5,
                'isfilled' => true,
                'isenabled' => $providedenabled,
                'iscomplete' => $providedcomplete,
                'flag' => 'provided',
                'name' => get_string('step5', 'gestionprojet'),
                'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 5, 'mode' => 'provided']))->out(false),
            ];
```

- [ ] **Step 2: Charger `essai_provided` côté élève**

Dans `gestionprojet/pages/home.php`, repérer ligne 318 :
```php
            $fastprovided = $DB->get_record('gestionprojet_fast_provided', ['gestionprojetid' => $gestionprojet->id]);
```

Insérer juste après :
```php
            $essaiprovided = $DB->get_record('gestionprojet_essai_provided', ['gestionprojetid' => $gestionprojet->id]);
```

- [ ] **Step 3: Étendre `$providedcomplete` pour step 5 dans la zone élève**

Dans `gestionprojet/pages/home.php`, repérer (ligne ~350) :
```php
            // Helper: provided-brief completion (steps 4 and 9).
            $providedcomplete = function($stepnum) use ($cdcfprovided, $fastprovided, $cdcfstarted) {
                if ($stepnum === 4) {
                    return $cdcfstarted($cdcfprovided);
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
```

Remplacer par (ajouter `$essaiprovided` au `use`, ajouter le cas step 5) :
```php
            // Helper: provided-brief completion (steps 4, 5, 9).
            $providedcomplete = function($stepnum) use ($cdcfprovided, $fastprovided, $essaiprovided, $cdcfstarted) {
                if ($stepnum === 4) {
                    return $cdcfstarted($cdcfprovided);
                }
                if ($stepnum === 5) {
                    if (!$essaiprovided) {
                        return false;
                    }
                    foreach (['fonction_service', 'niveaux_reussite', 'etapes_protocole',
                              'materiel_outils', 'precautions', 'resultats_obtenus',
                              'observations_remarques', 'conclusion', 'objectif'] as $f) {
                        if (!empty(trim((string)($essaiprovided->{$f} ?? '')))) {
                            return true;
                        }
                    }
                    return false;
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
```

- [ ] **Step 4: Ajouter une row consult cell step 5 (vue élève)**

Dans `gestionprojet/pages/home.php`, repérer le bloc des cellules row 1 élève (ligne ~431, fin du bloc `else if ($stepnum === 9)`) :

```php
                } else if ($stepnum === 9) {
                    $providedflag = isset($gestionprojet->step9_provided) ? (int)$gestionprojet->step9_provided : 0;
                    if ($providedflag === 1) {
                        $rowconsult[] = gestionprojet_build_student_gantt_cell([
                            ...
                            'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 9, 'mode' => 'provided']))->out(false),
                            'isprovided' => true,
                        ]);
                    } else {
                        $rowconsult[] = ['isfilled' => false];
                    }
                } else {
                    $rowconsult[] = ['isfilled' => false];
                }
```

Insérer un nouveau `else if` pour step 5 **juste avant** le `} else {` final :

```php
                } else if ($stepnum === 5) {
                    $providedflag = isset($gestionprojet->step5_provided) ? (int)$gestionprojet->step5_provided : 0;
                    if ($providedflag === 1) {
                        $rowconsult[] = gestionprojet_build_student_gantt_cell([
                            'isfilled' => true,
                            'role' => 'consult',
                            'isenabled' => $stepenabled(5),
                            'iscomplete' => $providedcomplete(5),
                            'name' => get_string('step5', 'gestionprojet'),
                            'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 5, 'mode' => 'provided']))->out(false),
                            'isprovided' => true,
                        ]);
                    } else {
                        $rowconsult[] = ['isfilled' => false];
                    }
```

- [ ] **Step 5: Vérifier la syntaxe PHP**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/home.php"`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add gestionprojet/pages/home.php
git commit -m "feat(home): gantt cells for essai consigne (step5_provided)"
```

---

### Task 11: Backup / Restore — essai_provided

**Files:**
- Modify: `gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php`
- Modify: `gestionprojet/backup/moodle2/restore_gestionprojet_stepslib.php`

- [ ] **Step 1: Déclarer `essai_provided` dans le backup**

Dans `gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php`, repérer ligne 110-112 :
```php
        $fastprovided = new backup_nested_element('fast_provided', ['id'], [
            'data_json', 'timecreated', 'timemodified',
        ]);
```

Insérer juste après :
```php
        $essaiprovided = new backup_nested_element('essai_provided', ['id'], [
            'nom_essai', 'date_essai', 'groupe_eleves', 'objectif',
            'fonction_service', 'niveaux_reussite',
            'etapes_protocole', 'materiel_outils', 'precautions',
            'resultats_obtenus', 'observations_remarques', 'conclusion',
            'timecreated', 'timemodified',
        ]);
```

Ensuite repérer ligne 177 :
```php
        $gestionprojet->add_child($fastprovided);
```

Insérer juste après :
```php
        $gestionprojet->add_child($essaiprovided);
```

Enfin repérer ligne 212 :
```php
        $fastprovided->set_source_table('gestionprojet_fast_provided', ['gestionprojetid' => backup::VAR_PARENTID]);
```

Insérer juste après :
```php
        $essaiprovided->set_source_table('gestionprojet_essai_provided', ['gestionprojetid' => backup::VAR_PARENTID]);
```

- [ ] **Step 2: Déclarer `essai_provided` dans le restore**

Dans `gestionprojet/backup/moodle2/restore_gestionprojet_stepslib.php`, repérer ligne 52 :
```php
        $paths[] = new restore_path_element('gestionprojet_fast_provided', '/activity/gestionprojet/fast_provided');
```

Insérer juste après :
```php
        $paths[] = new restore_path_element('gestionprojet_essai_provided', '/activity/gestionprojet/essai_provided');
```

Ensuite repérer ligne 263-272 (la méthode `process_gestionprojet_fast_provided`) :
```php
    /**
     * Process the fast_provided element (teacher consigne, no ai_instructions, no dates).
     *
     * @param array $data
     */
    protected function process_gestionprojet_fast_provided($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_fast_provided', $data);
    }
```

Insérer juste après cette méthode :
```php
    /**
     * Process the essai_provided element (teacher consigne, no ai_instructions, no dates).
     *
     * @param array $data
     */
    protected function process_gestionprojet_essai_provided($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_essai_provided', $data);
    }
```

- [ ] **Step 3: Vérifier la syntaxe PHP**

Run:
```
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php" && php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/backup/moodle2/restore_gestionprojet_stepslib.php"
```
Expected: deux fois `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add gestionprojet/backup/moodle2/backup_gestionprojet_stepslib.php gestionprojet/backup/moodle2/restore_gestionprojet_stepslib.php
git commit -m "feat(backup): include essai_provided in backup/restore"
```

---

### Task 12: Strings (lang FR + EN)

**Files:**
- Modify: `gestionprojet/lang/fr/gestionprojet.php`
- Modify: `gestionprojet/lang/en/gestionprojet.php`

- [ ] **Step 1: Ajouter les strings français**

Dans `gestionprojet/lang/fr/gestionprojet.php`, repérer la ligne `step9_provided_desc` (ligne ~921) :
```php
$string['step9_provided_desc'] = 'Définissez le diagramme FAST que vous souhaitez fournir à vos élèves comme point de départ.';
```

Insérer **juste après** :
```php
$string['step5_provided'] = 'Fournir une consigne d\'essai aux élèves';
$string['step5_provided_desc'] = 'Si coché, les élèves voient la consigne de l\'essai (protocole, etc.) en lecture seule, et leur fiche essai démarre pré-remplie avec ce contenu.';
$string['step5_desc_title'] = 'Qu\'est-ce que la consigne de la fiche essai ?';
$string['step5_desc_text'] = 'Cette consigne sera affichée en lecture seule aux élèves et utilisée pour pré-remplir leur fiche essai au premier accès. L\'élève peut ensuite la compléter et la modifier librement.';
$string['step5_provided_precautions_placeholder'] = 'Une précaution par ligne (jusqu\'à 6).';
```

- [ ] **Step 2: Ajouter les strings anglais**

Repérer le même point d'ancrage dans `gestionprojet/lang/en/gestionprojet.php` (`step9_provided_desc`) :

Insérer **juste après** :
```php
$string['step5_provided'] = 'Provide an essai consigne to students';
$string['step5_provided_desc'] = 'If checked, students see the essai consigne (protocol, etc.) read-only, and their essai sheet starts pre-filled with this content.';
$string['step5_desc_title'] = 'What is the essai consigne?';
$string['step5_desc_text'] = 'This consigne is shown read-only to students and seeded into their editable essai on first access. Students can then complete and modify it freely.';
$string['step5_provided_precautions_placeholder'] = 'One precaution per line (up to 6).';
```

- [ ] **Step 3: Vérifier la syntaxe PHP**

Run:
```
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/lang/fr/gestionprojet.php" && php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/lang/en/gestionprojet.php"
```
Expected: deux fois `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add gestionprojet/lang/fr/gestionprojet.php gestionprojet/lang/en/gestionprojet.php
git commit -m "lang: add strings for essai consigne (step5)"
```

---

### Task 13: Vérifications globales (lint + checklist Moodle)

**Files:** (read-only checks across the whole plugin)

- [ ] **Step 1: Vérifier que tous les nouveaux fichiers ont l'en-tête GPL complet (deux paragraphes)**

Run: `grep -L "distributed in the hope" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step5_provided.php"`
Expected: aucune sortie (le grep -L liste les fichiers qui *ne* contiennent pas la chaîne)

- [ ] **Step 2: Vérifier l'absence de `<style>` ou `<script>` inline dans la nouvelle page**

Run: `grep -n '<style\|<script' "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step5_provided.php"`
Expected: aucune sortie

- [ ] **Step 3: Vérifier l'absence de superglobales et de debug code dans les fichiers modifiés**

Run:
```
grep -n '\$_GET\|\$_POST\|\$_REQUEST\|var_dump\|print_r\|file_put_contents.*log' \
  "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step5_provided.php" \
  "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step5.php" \
  "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/home.php" \
  "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/lib.php" \
  "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/view.php" \
  "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/autosave.php" \
  "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/toggle_step.php"
```
Expected: aucune sortie

- [ ] **Step 4: Vérifier que le seeding tourne quand attendu (revue manuelle)**

Re-lire `gestionprojet/lib.php` zone `gestionprojet_get_or_create_submission` (ligne 240+) : confirmer visuellement que :
- le bloc essai n'est exécuté que si `$table === 'essai'` ET `step5_provided === 1`,
- la condition d'`empty` ne déclenche le seed que quand TOUS les `$checkfields` sont vides,
- l'`update_record` n'est appelé que si au moins un champ a effectivement été copié (`$changed`),
- la fonction retourne bien le `$record` mis à jour.

- [ ] **Step 5: Re-vérifier le contenu du delete_instance**

Run: `grep -c "delete_records" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/lib.php"`
Expected: 18 (le compteur passe de 17 à 18 avec la nouvelle table — cohérent avec la checklist Moodle, point 8 dans CLAUDE.md).

Si le total observé est différent : refaire `grep -n "delete_records" gestionprojet/lib.php | head -25` et reconcilier avec la liste des tables (17 historiques + 1 nouvelle = 18).

- [ ] **Step 6: Aucun commit nécessaire à cette étape (vérifications uniquement)**

---

### Task 14: Déploiement preprod + smoke test manuel

**Files:** (deploy package + manual checks)

> ⚠️ Cette tâche obligatoire vient de la mémoire utilisateur :
> *"toujours SCP en preprod en fin d'implémentation avant de déclarer « done »"*
> Voir `TESTING.md` à la racine pour les credentials et chemins.

- [ ] **Step 1: Lire les credentials et la procédure**

Run: `cat "/Volumes/DONNEES/Claude code/mod_gestionprojet/TESTING.md"`
Expected: section Preprod avec hôte, utilisateur, chemin, et commande SCP.

- [ ] **Step 2: Construire le ZIP**

Run:
```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet" && \
rm -f gestionprojet.zip && \
zip -r gestionprojet.zip gestionprojet/ -x "*.git*" "*.claude*" "*lessons.md"
```
Expected: création de `gestionprojet.zip`

- [ ] **Step 3: SCP vers la preprod**

Suivre la commande exacte donnée par `TESTING.md`. Typiquement :
```bash
scp gestionprojet.zip <user>@<host>:<remote-path>
```

(Ne pas hardcoder host/user dans le plan : ces valeurs vivent dans `TESTING.md`.)

- [ ] **Step 4: Déclencher l'upgrade Moodle sur la preprod**

Soit via l'admin Moodle (Plugins → Install plugins → Upload ZIP), soit via CLI sur la preprod :
```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```
Expected: upgrade qui ajoute `gestionprojet_essai_provided` + champ `step5_provided`.

- [ ] **Step 5: Smoke test côté enseignant**

1. Aller sur une instance gestionprojet existante.
2. Sur la home : la cellule step 5 ligne 1 (consignes) doit être présente, désactivée par défaut.
3. Cocher la case « Fournir une consigne d'essai » → la cellule devient activable.
4. Cliquer la cellule → page consigne s'ouvre. Bandeau bleu d'introduction visible.
5. Saisir au moins « Étapes du protocole » et « Matériel et outils ». Attendre l'autosave (30s par défaut).
6. Recharger la page → les valeurs sont conservées.

- [ ] **Step 6: Smoke test côté élève**

1. Se reconnecter avec un compte élève membre d'un groupe.
2. Sur la home : la cellule consultation step 5 doit apparaître. Cliquer dessus → vue lecture seule (les champs ne doivent pas accepter la frappe). Le bandeau bleu est visible.
3. Aller sur la fiche essai (lien depuis la home, row 2) → les champs doivent être pré-remplis avec ce qu'a saisi l'enseignant.
4. Modifier un champ pré-rempli (ex. `etapes_protocole`) → autosave 30s → recharger → modification conservée.
5. Recharger plusieurs fois la fiche essai : pas de re-seeding parasite (le contenu modifié reste).
6. Vérifier l'affichage des `precautions` : les 6 cases côté élève sont alimentées (jusqu'à 6 lignes du textarea consigne).

- [ ] **Step 7: Vérifier le tab consignes côté enseignant**

Sur n'importe quelle page consigne (ex. step 4 provided), vérifier que step 5 apparaît dans la barre d'onglets, à droite de step 9.

- [ ] **Step 8: Vérifier la suppression d'instance**

Sur la preprod : supprimer l'activité gestionprojet de test → vérifier (via PHPMyAdmin ou requête SQL) que la table `gestionprojet_essai_provided` ne contient plus de ligne pour cet `gestionprojetid`.

```sql
SELECT * FROM mdl_gestionprojet_essai_provided WHERE gestionprojetid = <id>;
-- Expected: 0 row.
```

- [ ] **Step 9: Si OK, signaler "done" à l'utilisateur**

Récapituler en une phrase ce qui a été déployé et que les smoke tests sont passés.

---

## Notes

- **Pas de tests automatisés** dans ce plan : le projet n'a pas d'infrastructure PHPUnit en place et le pattern de validation utilisé jusqu'ici est le smoke test manuel sur preprod (cf. mémoire utilisateur). Si un jour des tests sont ajoutés, la fonction `gestionprojet_get_or_create_submission` est le point d'entrée naturel pour tester le seeding (mock du `$DB` requis).
- **Pas de `mod_form.php`** : l'activation se fait via le Gantt home (cohérent avec step4_provided / step9_provided existants).
- **Cas particulier `precautions`** : le textarea consigne est libre, le code `pages/step5.php` est rendu tolérant (Task 7) pour gérer à la fois l'ancien JSON et le nouveau split-par-ligne au seeding.
- **Backups existants** : les sauvegardes faites avant 2.8.0 ne contiennent pas `essai_provided` ; le restore les ignore silencieusement (Moodle accepte les `restore_path_element` absents du XML source).
