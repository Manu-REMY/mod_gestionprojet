# Plan d'implémentation — Améliorations ergonomiques v2.2.0

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** Implémenter les 3 frictions ergonomiques validées dans le spec : mode "CDCF fourni par l'enseignant" (step 4), modèles de correction directement sur la home, et barre de navigation directe à 8 onglets.

**Architecture :** Extension non-destructive de l'existant. Pas de nouvelle table, pas de migration de données. Réutilisation de `gestionprojet_cdcf_teacher` pour le mode fourni. Extraction d'un composant Mustache `step_tabs` factorisé depuis `grading_navigation.mustache`. Suppression de la page `correction_models.php` (transformée en redirect).

**Tech Stack :** PHP 8.1+, Moodle 5.0+, Mustache, Bootstrap 5, AMD/RequireJS, MySQL/PostgreSQL via XMLDB.

**Référence spec :** `docs/superpowers/specs/2026-05-02-ergonomie-enseignant-design.md`

**Particularité du projet :** Pas de PHPUnit dans cette codebase. Chaque tâche se conclut par une **vérification de code** (grep, lint phpcs si dispo) et/ou une **vérification manuelle** dans un Moodle de test. À chaque commit, exécuter `php admin/cli/purge_caches.php` côté serveur Moodle.

---

## Phase 1 — Friction 3 : Barre de navigation directe à 8 onglets

### Task 1 : Créer le partial Mustache `step_tabs`

**Files :**
- Create: `gestionprojet/templates/step_tabs.mustache`

- [ ] **Step 1 : Créer le fichier**

Contenu exact :

```mustache
{{!
    @template mod_gestionprojet/step_tabs

    Reusable step navigation tabs (8 phases).

    Used on:
    - grading.php (via grading_navigation.mustache partial include)
    - teacher pages step1, step2, step3
    - correction model pages step4_teacher .. step8_teacher

    Context variables required:
    * tabs - Array of 8 tab objects, in pedagogical order [1, 3, 2, 7, 4, 5, 8, 6]

    Each tab object:
    * stepnum - Step number (1-8)
    * icon - SVG HTML for step icon
    * name - Localized step name
    * isactive - Boolean if this is the current step
    * isenabled - Boolean if this step is enabled (false => greyed/disabled)
    * url - URL to navigate to this step (only used when isenabled is true)

    Example context (json):
    {
        "tabs": [
            {
                "stepnum": 1,
                "icon": "<span class=\"gp-icon\">...</span>",
                "name": "Description",
                "isactive": true,
                "isenabled": true,
                "url": "/mod/gestionprojet/view.php?id=123&step=1"
            }
        ]
    }
}}
<div class="step-tabs">
    {{#tabs}}
        {{#isenabled}}
            <a href="{{url}}" class="step-tab {{#isactive}}active{{/isactive}}">
                {{{icon}}} {{name}}
            </a>
        {{/isenabled}}
        {{^isenabled}}
            <span class="step-tab disabled" aria-disabled="true">
                {{{icon}}} {{name}}
            </span>
        {{/isenabled}}
    {{/tabs}}
</div>
```

- [ ] **Step 2 : Vérifier la syntaxe Mustache**

Run :
```bash
grep -c "{{" gestionprojet/templates/step_tabs.mustache
grep -c "}}" gestionprojet/templates/step_tabs.mustache
```
Expected : les deux comptes sont identiques (Mustache balanced).

- [ ] **Step 3 : Commit**

```bash
git add gestionprojet/templates/step_tabs.mustache
git commit -m "feat(nav): add reusable step_tabs Mustache partial"
```

---

### Task 2 : Ajouter la fonction `gestionprojet_build_step_tabs()` dans `lib.php`

**Files :**
- Modify: `gestionprojet/lib.php` (append at end, before `}` if applicable, or as standalone function)

- [ ] **Step 1 : Ajouter la fonction**

Localiser la fin du fichier `gestionprojet/lib.php`. Ajouter juste avant la fermeture (le fichier n'a pas de classe — fonctions globales) :

```php
/**
 * Build the context for the step_tabs Mustache partial.
 *
 * @param stdClass $gestionprojet The activity instance (for enable_stepN flags)
 * @param int $cmid Course module ID
 * @param int $currentstep The currently active step (1-8)
 * @param string $context Tab context: 'teacher' (steps 1,2,3 view), 'model' (steps 4-8 teacher mode), 'grading' (grading.php)
 * @return array Context array with key 'tabs' suitable for the step_tabs partial
 */
function gestionprojet_build_step_tabs($gestionprojet, $cmid, $currentstep, $context) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/gestionprojet/classes/output/icon.php');

    $order = [1, 3, 2, 7, 4, 5, 8, 6];
    $tabs = [];

    foreach ($order as $stepnum) {
        $field = 'enable_step' . $stepnum;
        $enableval = isset($gestionprojet->$field) ? (int)$gestionprojet->$field : 1;
        $isenabled = ($enableval !== 0);

        // Build the URL according to the destination context.
        $params = ['id' => $cmid, 'step' => $stepnum];
        $isteacherstep = in_array($stepnum, [1, 2, 3], true);
        $isstudentstep = in_array($stepnum, [4, 5, 6, 7, 8], true);

        if ($context === 'grading' && $isstudentstep) {
            $url = (new \moodle_url('/mod/gestionprojet/grading.php', $params))->out(false);
        } else if ($isteacherstep) {
            // Teacher pages always go to view.php?step=N (no mode).
            $url = (new \moodle_url('/mod/gestionprojet/view.php', $params))->out(false);
        } else {
            // Student steps in non-grading context: go to teacher correction model page.
            $params['mode'] = 'teacher';
            $url = (new \moodle_url('/mod/gestionprojet/view.php', $params))->out(false);
        }

        $tabs[] = [
            'stepnum' => $stepnum,
            'icon' => \mod_gestionprojet\output\icon::render_step($stepnum, 'sm', 'inherit'),
            'name' => get_string('step' . $stepnum, 'gestionprojet'),
            'isactive' => ($stepnum === (int)$currentstep),
            'isenabled' => $isenabled,
            'url' => $url,
        ];
    }

    return ['tabs' => $tabs];
}
```

- [ ] **Step 2 : Vérifier qu'il n'y a pas de balise PHP en trop**

Run :
```bash
php -l "gestionprojet/lib.php"
```
Expected : `No syntax errors detected in gestionprojet/lib.php`

- [ ] **Step 3 : Commit**

```bash
git add gestionprojet/lib.php
git commit -m "feat(nav): add gestionprojet_build_step_tabs() helper"
```

---

### Task 3 : Brancher les onglets sur les pages enseignant 1, 2, 3

**Files :**
- Modify: `gestionprojet/pages/step1.php`
- Modify: `gestionprojet/pages/step2.php`
- Modify: `gestionprojet/pages/step3.php`

- [ ] **Step 1 : Inspecter step1.php pour identifier le point d'insertion**

Run :
```bash
grep -n "OUTPUT->header\|step-navigation-top\|set_title\|<div" gestionprojet/pages/step1.php | head -20
```

Repérer la première sortie HTML après `$OUTPUT->header()`. Les onglets doivent être insérés juste après `echo $OUTPUT->header();` et avant tout autre contenu.

- [ ] **Step 2 : Insérer le rendu des onglets dans step1.php**

Juste après la ligne `echo $OUTPUT->header();` dans `gestionprojet/pages/step1.php`, ajouter :

```php
// Render direct step navigation tabs.
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 1, 'teacher')
);
```

- [ ] **Step 3 : Idem pour step2.php**

Même insertion mais avec `currentstep = 2` :

```php
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 2, 'teacher')
);
```

- [ ] **Step 4 : Idem pour step3.php**

Avec `currentstep = 3` :

```php
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 3, 'teacher')
);
```

- [ ] **Step 5 : Vérifier la syntaxe**

Run :
```bash
php -l gestionprojet/pages/step1.php
php -l gestionprojet/pages/step2.php
php -l gestionprojet/pages/step3.php
```
Expected : `No syntax errors detected` pour chacun.

- [ ] **Step 6 : Vérifier qu'il n'y a pas de `<style>` ni `<script>` inline ajouté par erreur**

Run :
```bash
grep -n '<style\|<script' gestionprojet/pages/step1.php gestionprojet/pages/step2.php gestionprojet/pages/step3.php
```
Expected : aucun résultat ajouté par cette tâche (les éventuels résultats préexistants ne sont pas une régression).

- [ ] **Step 7 : Commit**

```bash
git add gestionprojet/pages/step1.php gestionprojet/pages/step2.php gestionprojet/pages/step3.php
git commit -m "feat(nav): add 8-step tab bar on teacher pages 1, 2, 3"
```

---

### Task 4 : Brancher les onglets sur les modèles de correction (step 4 à 8 teacher)

**Files :**
- Modify: `gestionprojet/pages/step4_teacher.php`
- Modify: `gestionprojet/pages/step5_teacher.php`
- Modify: `gestionprojet/pages/step6_teacher.php`
- Modify: `gestionprojet/pages/step7_teacher.php`
- Modify: `gestionprojet/pages/step8_teacher.php`

- [ ] **Step 1 : Insérer le rendu des onglets dans step4_teacher.php**

Juste après `echo $OUTPUT->header();` :

```php
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 4, 'model')
);
```

- [ ] **Step 2 : Idem step5_teacher.php** — `currentstep = 5`

- [ ] **Step 3 : Idem step6_teacher.php** — `currentstep = 6`

- [ ] **Step 4 : Idem step7_teacher.php** — `currentstep = 7`

- [ ] **Step 5 : Idem step8_teacher.php** — `currentstep = 8`

- [ ] **Step 6 : Vérifier la syntaxe**

Run :
```bash
for f in gestionprojet/pages/step{4,5,6,7,8}_teacher.php; do php -l "$f"; done
```
Expected : 5 lignes `No syntax errors detected`.

- [ ] **Step 7 : Commit**

```bash
git add gestionprojet/pages/step4_teacher.php gestionprojet/pages/step5_teacher.php gestionprojet/pages/step6_teacher.php gestionprojet/pages/step7_teacher.php gestionprojet/pages/step8_teacher.php
git commit -m "feat(nav): add 8-step tab bar on correction model pages 4-8"
```

---

### Task 5 : Refactor `grading_navigation.mustache` pour inclure le partial `step_tabs`

**Files :**
- Modify: `gestionprojet/templates/grading_navigation.mustache`

- [ ] **Step 1 : Lire le bloc actuel des onglets**

Run :
```bash
sed -n '76,92p' gestionprojet/templates/grading_navigation.mustache
```
Repérer le bloc `<div class="step-tabs">…</div>` qui contient `{{#steptabs}}…{{/steptabs}}`.

- [ ] **Step 2 : Remplacer ce bloc par l'inclusion du partial dans un wrapper de ligne**

Le partial `step_tabs` rend déjà son propre `<div class="step-tabs">…</div>`. Le bouton "Relancer toutes les évaluations IA" devient sibling. Pour préserver la disposition en ligne, on les enveloppe dans un nouveau wrapper `.step-tabs-row`.

Remplacer le bloc complet `<div class="step-tabs">…</div>` (lignes ~76-92) par :

```mustache
{{! Step tabs (shared partial) + grading-specific bulk action button }}
<div class="step-tabs-row">
    {{> mod_gestionprojet/step_tabs}}

    {{#aienabled}}
    <button type="button" class="bulk-reevaluate-btn" id="btn-bulk-reevaluate"
        data-cmid="{{cmid}}"
        data-step="{{currentstep}}"
        title="{{#str}} bulk_reevaluate_desc, gestionprojet {{/str}}">
        {{{icon_refresh}}} {{#str}} bulk_reevaluate, gestionprojet {{/str}}
    </button>
    {{/aienabled}}
</div>
```

- [ ] **Step 2bis : Ajuster les styles CSS pour le nouveau wrapper**

Dans `gestionprojet/styles.css`, repérer la règle existante :

```bash
grep -n "step-tabs .bulk-reevaluate-btn" gestionprojet/styles.css
```

Remplacer le sélecteur ancestré `.path-mod-gestionprojet .step-tabs .bulk-reevaluate-btn` par `.path-mod-gestionprojet .step-tabs-row .bulk-reevaluate-btn` (pour les deux occurrences : règle de base + règle :hover, vers les lignes 3749 et 3762).

Ajouter en complément la règle du wrapper, juste avant la première occurrence :

```css
.path-mod-gestionprojet .step-tabs-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
```

Vérifier également la media query responsive (vers la ligne 4717) — si elle cible `.step-tabs` pour le bouton, l'adapter en `.step-tabs-row`.

- [ ] **Step 3 : Adapter la construction du contexte côté `grading.php`**

Run :
```bash
grep -n "steptabs\|step_tabs\|grading_navigation" gestionprojet/grading.php
```

Repérer où `steptabs` est aujourd'hui injecté dans le template context. Remplacer cette construction par un appel à `gestionprojet_build_step_tabs($gestionprojet, $cm->id, $step, 'grading')` et fusionner le résultat (`array_merge($context, $tabs)`) dans le contexte passé à `render_from_template('mod_gestionprojet/grading_navigation', …)`.

Exemple de patch (à adapter à l'emplacement réel dans grading.php) :

```php
// Old:
// $templatecontext['steptabs'] = [...]; // remove this block

// New:
$templatecontext = array_merge(
    $templatecontext,
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, $step, 'grading')
);
```

- [ ] **Step 4 : Vérifier la syntaxe PHP**

Run :
```bash
php -l gestionprojet/grading.php
```
Expected : `No syntax errors detected`.

- [ ] **Step 5 : Vérification visuelle de cohérence Mustache**

Run :
```bash
grep -n "steptabs" gestionprojet/templates/grading_navigation.mustache
```
Expected : aucune occurrence (la variable `steptabs` est remplacée par le partial qui consomme `tabs`).

- [ ] **Step 6 : Commit**

```bash
git add gestionprojet/templates/grading_navigation.mustache gestionprojet/grading.php gestionprojet/styles.css
git commit -m "refactor(nav): use shared step_tabs partial in grading_navigation"
```

---

### Task 6 : Ajouter le style `step-tab.disabled` dans `styles.css`

**Files :**
- Modify: `gestionprojet/styles.css`

- [ ] **Step 1 : Vérifier que la classe n'existe pas déjà**

Run :
```bash
grep -n "step-tab\.disabled\|step-tab.disabled" gestionprojet/styles.css
```
Expected : aucun résultat (sinon adapter la règle existante).

- [ ] **Step 2 : Ajouter la règle**

Localiser la fin du bloc `.path-mod-gestionprojet .step-tab.active` (vers la ligne 3745). Ajouter juste après :

```css
.path-mod-gestionprojet .step-tab.disabled {
    opacity: 0.4;
    cursor: not-allowed;
    pointer-events: none;
}
```

- [ ] **Step 3 : Vérifier la cohérence du fichier**

Run :
```bash
grep -c "^\." gestionprojet/styles.css
```
(simple sanity check — le compte avant/après doit avoir augmenté de 1)

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/styles.css
git commit -m "style(nav): add disabled state for step-tab"
```

---

### Task 7 : Validation manuelle de la Phase 1

- [ ] **Step 1 : Purger les caches**

```bash
php admin/cli/purge_caches.php
```

- [ ] **Step 2 : Tester sur les pages enseignant**

Sur un Moodle de test :
1. Créer/ouvrir une activité gestionprojet
2. En tant qu'enseignant, naviguer sur step 1 (Description)
3. Vérifier la présence de la barre d'onglets en haut de la page
4. Vérifier que l'onglet "Description" est actif (surligné)
5. Cliquer sur l'onglet "Planification" → doit charger step 3
6. Cliquer sur l'onglet "Cahier des Charges" → doit charger step 4 en mode teacher (correction model)
7. Désactiver step 7 dans les paramètres → vérifier que l'onglet "Expression du Besoin (Élève)" est grisé/non cliquable

- [ ] **Step 3 : Tester `grading.php` (non-régression)**

1. Ouvrir grading.php pour une étape élève
2. Vérifier que la barre d'onglets fonctionne comme avant (mêmes URLs, sélecteur de groupe préservé, prev/next préservés)

---
## Phase 2 — Friction 2 : Refonte de la home enseignant en Gantt

### Task 8 : Construire le contexte Gantt dans `home.php`

**Files :**
- Modify: `gestionprojet/pages/home.php`

- [ ] **Step 1 : Identifier le bloc enseignant actuel**

```bash
grep -n "isteacher\|teachersteps\|gradingsteps\|dashboard" gestionprojet/pages/home.php | head -20
```

Repérer le bloc `if ($isteacher) {` qui construit `teachersteps`, `gradingsteps`, `dashboard` (vers les lignes 43-186).

- [ ] **Step 2 : Remplacer le bloc enseignant par la construction du Gantt**

Dans `home.php`, à l'intérieur du `if ($isteacher) {`, **remplacer entièrement** le contenu existant (qui construit `teachersteps`, `gradingsteps`, `dashboard`) par la construction du Gantt :

```php
// Build the Gantt dashboard for the teacher home view.
// Pedagogical column order: [1, 3, 2, 7, 4, 5, 8, 6].
$ganttorder = [1, 3, 2, 7, 4, 5, 8, 6];
$teacherdocsteps = [1, 2, 3];
$studentsteps = [4, 5, 6, 7, 8];

// Map step number to its data source for "is filled" computation.
$teacherdocs = [
    1 => $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]),
    2 => $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]),
    3 => $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]),
];
$teachermodels = [
    4 => $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]),
    5 => $DB->get_record('gestionprojet_essai_teacher', ['gestionprojetid' => $gestionprojet->id]),
    6 => $DB->get_record('gestionprojet_rapport_teacher', ['gestionprojetid' => $gestionprojet->id]),
    7 => $DB->get_record('gestionprojet_besoin_eleve_teacher', ['gestionprojetid' => $gestionprojet->id]),
    8 => $DB->get_record('gestionprojet_carnet_teacher', ['gestionprojetid' => $gestionprojet->id]),
];
$studenttables = [
    4 => 'gestionprojet_cdcf',
    5 => 'gestionprojet_essai',
    6 => 'gestionprojet_rapport',
    7 => 'gestionprojet_besoin_eleve',
    8 => 'gestionprojet_carnet',
];

// Helper closures for cell completion logic.
$teacherdocfilled = function($stepnum) use ($teacherdocs) {
    $rec = $teacherdocs[$stepnum] ?? null;
    if (!$rec) {
        return false;
    }
    if ($stepnum === 1) {
        return !empty($rec->intitule);
    }
    if ($stepnum === 2) {
        return !empty($rec->aqui);
    }
    if ($stepnum === 3) {
        return !empty($rec->projectname);
    }
    return false;
};
$teachermodelfilled = function($stepnum) use ($teachermodels, $gestionprojet) {
    $rec = $teachermodels[$stepnum] ?? null;
    if (!$rec) {
        return false;
    }
    // For step 4 in provided mode, completion is based on `produit` rather than `ai_instructions`.
    if ($stepnum === 4 && (int)$gestionprojet->enable_step4 === 2) {
        return !empty($rec->produit);
    }
    return !empty($rec->ai_instructions);
};

// Build column headers and cells for each row.
$ganttcolumns = [];
$rowdocs = [];
$rowmodels = [];
$rowstudent = [];

$totalconfigured = 0;
$totalconfigtargets = 0;
$totalungraded = 0;

foreach ($ganttorder as $stepnum) {
    $field = 'enable_step' . $stepnum;
    $enableval = isset($gestionprojet->$field) ? (int)$gestionprojet->$field : 1;
    $isenabled = ($enableval !== 0);
    $isteacherdocstep = in_array($stepnum, $teacherdocsteps, true);
    $isstudentstep = in_array($stepnum, $studentsteps, true);

    // Column header — shows checkbox only for teacher doc steps (independent control).
    $ganttcolumns[] = [
        'stepnum' => $stepnum,
        'name' => get_string('step' . $stepnum, 'gestionprojet'),
        'icon' => \mod_gestionprojet\output\icon::render_step($stepnum, 'sm', 'inherit'),
    ];

    // Row 1 cells — only fill for teacher doc steps.
    if ($isteacherdocstep) {
        $iscomplete = $teacherdocfilled($stepnum);
        if ($isenabled) {
            $totalconfigtargets++;
            if ($iscomplete) {
                $totalconfigured++;
            }
        }
        $rowdocs[] = [
            'stepnum' => $stepnum,
            'isfilled' => true,
            'isenabled' => $isenabled,
            'iscomplete' => $iscomplete,
            'haschexkbox' => true,
            'name' => get_string('step' . $stepnum, 'gestionprojet'),
            'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnum]))->out(false),
        ];
    } else {
        $rowdocs[] = ['isfilled' => false];
    }

    // Row 2 cells — only fill for student steps. Checkbox is here (shared with row 3).
    if ($isstudentstep) {
        $iscomplete = $teachermodelfilled($stepnum);
        $isprovided = ($stepnum === 4 && $enableval === 2);
        if ($isenabled) {
            $totalconfigtargets++;
            if ($iscomplete) {
                $totalconfigured++;
            }
        }
        $rowmodels[] = [
            'stepnum' => $stepnum,
            'isfilled' => true,
            'isenabled' => $isenabled,
            'iscomplete' => $iscomplete,
            'isprovided' => $isprovided,
            'hascheckbox' => true,
            'name' => get_string('step' . $stepnum, 'gestionprojet'),
            'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnum, 'mode' => 'teacher']))->out(false),
        ];
    } else {
        $rowmodels[] = ['isfilled' => false];
    }

    // Row 3 cells — only fill for student steps. No checkbox here (linked via row 2).
    if ($isstudentstep) {
        $table = $studenttables[$stepnum];
        $totalsubmitted = $DB->count_records_select(
            $table,
            'gestionprojetid = :gid AND status = 1',
            ['gid' => $gestionprojet->id]
        );
        $totalgraded = $DB->count_records_select(
            $table,
            'gestionprojetid = :gid AND grade IS NOT NULL',
            ['gid' => $gestionprojet->id]
        );
        $ungraded = max(0, $totalsubmitted - $totalgraded);
        if ($isenabled) {
            $totalungraded += $ungraded;
        }
        $rowstudent[] = [
            'stepnum' => $stepnum,
            'isfilled' => true,
            'isenabled' => $isenabled,
            'submitted' => $totalsubmitted,
            'graded' => $totalgraded,
            'ungraded' => $ungraded,
            'hasungraded' => $ungraded > 0,
            'name' => get_string('step' . $stepnum, 'gestionprojet'),
            'url' => (new \moodle_url('/mod/gestionprojet/grading.php', ['id' => $cm->id, 'step' => $stepnum]))->out(false),
        ];
    } else {
        $rowstudent[] = ['isfilled' => false];
    }
}

$templatecontext['gantt'] = [
    'columns' => $ganttcolumns,
    'rowdocs' => $rowdocs,
    'rowmodels' => $rowmodels,
    'rowstudent' => $rowstudent,
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
    'summary' => [
        'configured' => $totalconfigured,
        'total' => $totalconfigtargets,
        'ungraded' => $totalungraded,
        'hasungraded' => $totalungraded > 0,
    ],
];
```

> Note : la suppression des blocs `teachersteps`/`gradingsteps`/`dashboard` est intentionnelle — leurs données sont remplacées par la structure `gantt`. Cependant, **conserver** les variables d'icônes en fin de fichier (`$templatecontext['icon_*']`) car elles servent à d'autres parties du template.

- [ ] **Step 3 : Vérifier la syntaxe**

```bash
/usr/local/bin/php -l gestionprojet/pages/home.php
```
Expected : `No syntax errors detected`.

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/pages/home.php
git commit -m "feat(home): build Gantt context (3 rows × 8 columns) for teacher view"
```

---

### Task 9 : Créer le template `home_gantt.mustache`

**Files :**
- Create: `gestionprojet/templates/home_gantt.mustache`

- [ ] **Step 1 : Créer le fichier**

Contenu exact :

```mustache
{{!
    @template mod_gestionprojet/home_gantt

    Teacher home Gantt-style dashboard: 3 rows × 8 columns.
    Row 1: teacher documents (steps 1, 3, 2)
    Row 2: correction models (steps 7, 4, 5, 8, 6)
    Row 3: student activities (steps 7, 4, 5, 8, 6)

    Context variables required:
    * cmid - Course module ID
    * sesskey - Moodle sesskey for AJAX
    * columns - Array of 8 column header objects {stepnum, name, icon}
    * rowdocs, rowmodels, rowstudent - Arrays of 8 cell objects
    * summary - {configured, total, ungraded, hasungraded}

    Each cell object (filled): {stepnum, isfilled:true, isenabled, iscomplete?, isprovided?, hascheckbox, name, url, ...}
    Each cell object (empty): {isfilled:false}
}}
<div class="gp-gantt" data-cmid="{{cmid}}" data-sesskey="{{sesskey}}">

    <div class="gp-gantt-summary">
        <span class="gp-gantt-summary-config">
            {{#str}}gantt_progress_summary, gestionprojet, {"configured": "{{summary.configured}}", "total": "{{summary.total}}"}{{/str}}
        </span>
        {{#summary.hasungraded}}
        <span class="gp-gantt-summary-ungraded">
            {{#str}}gantt_ungraded_summary, gestionprojet, {{summary.ungraded}}{{/str}}
        </span>
        {{/summary.hasungraded}}
        {{^summary.hasungraded}}
        <span class="gp-gantt-summary-ungraded gp-summary-ok">
            {{#str}}gantt_ungraded_summary_zero, gestionprojet{{/str}}
        </span>
        {{/summary.hasungraded}}
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

        {{! Row 1 — Teacher documents }}
        <div class="gp-row-label gp-row-label-docs">
            {{#str}}gantt_row_teacher_docs, gestionprojet{{/str}}
        </div>
        {{#rowdocs}}
            {{#isfilled}}
                <div class="gp-cell gp-cell-docs {{^isenabled}}gp-cell-disabled{{/isenabled}}">
                    <input type="checkbox" class="gp-cell-cb" data-stepnum="{{stepnum}}" data-row="docs" {{#isenabled}}checked{{/isenabled}} title="{{name}}">
                    <a href="{{url}}" class="gp-cell-link">
                        <div class="gp-cell-name">{{name}}</div>
                        {{#isenabled}}
                            {{#iscomplete}}<div class="gp-cell-status gp-status-done">{{#str}}gantt_cell_status_done, gestionprojet{{/str}}</div>{{/iscomplete}}
                            {{^iscomplete}}<div class="gp-cell-status gp-status-todo">{{#str}}gantt_cell_status_todo, gestionprojet{{/str}}</div>{{/iscomplete}}
                        {{/isenabled}}
                        {{^isenabled}}<div class="gp-cell-status gp-status-disabled">{{#str}}gantt_cell_status_disabled, gestionprojet{{/str}}</div>{{/isenabled}}
                    </a>
                </div>
            {{/isfilled}}
            {{^isfilled}}<div class="gp-cell gp-cell-empty"></div>{{/isfilled}}
        {{/rowdocs}}

        {{! Row 2 — Correction models }}
        <div class="gp-row-label gp-row-label-models">
            {{#str}}gantt_row_correction_models, gestionprojet{{/str}}
        </div>
        {{#rowmodels}}
            {{#isfilled}}
                <div class="gp-cell gp-cell-models {{^isenabled}}gp-cell-disabled{{/isenabled}}" data-stepnum="{{stepnum}}">
                    <input type="checkbox" class="gp-cell-cb gp-cell-cb-shared" data-stepnum="{{stepnum}}" data-row="models" {{#isenabled}}checked{{/isenabled}} title="{{name}}">
                    <a href="{{url}}" class="gp-cell-link">
                        <div class="gp-cell-name">{{name}}</div>
                        {{#isenabled}}
                            {{#isprovided}}<div class="gp-cell-status gp-status-provided">{{#str}}step4_provided_badge, gestionprojet{{/str}}</div>{{/isprovided}}
                            {{^isprovided}}
                                {{#iscomplete}}<div class="gp-cell-status gp-status-done">{{#str}}gantt_cell_status_done, gestionprojet{{/str}}</div>{{/iscomplete}}
                                {{^iscomplete}}<div class="gp-cell-status gp-status-todo">{{#str}}gantt_cell_status_todo, gestionprojet{{/str}}</div>{{/iscomplete}}
                            {{/isprovided}}
                        {{/isenabled}}
                        {{^isenabled}}<div class="gp-cell-status gp-status-disabled">{{#str}}gantt_cell_status_disabled, gestionprojet{{/str}}</div>{{/isenabled}}
                    </a>
                </div>
            {{/isfilled}}
            {{^isfilled}}<div class="gp-cell gp-cell-empty"></div>{{/isfilled}}
        {{/rowmodels}}

        {{! Row 3 — Student activities }}
        <div class="gp-row-label gp-row-label-student">
            {{#str}}gantt_row_student_activities, gestionprojet{{/str}}
        </div>
        {{#rowstudent}}
            {{#isfilled}}
                <div class="gp-cell gp-cell-student {{^isenabled}}gp-cell-disabled{{/isenabled}}" data-stepnum="{{stepnum}}">
                    <span class="gp-cell-link-arrow" aria-hidden="true">↑</span>
                    <a href="{{url}}" class="gp-cell-link">
                        <div class="gp-cell-name">{{name}}</div>
                        {{#isenabled}}
                            <div class="gp-cell-status">{{submitted}} / {{graded}} notés</div>
                            {{#hasungraded}}<div class="gp-cell-status gp-status-todo">{{ungraded}} à corriger</div>{{/hasungraded}}
                        {{/isenabled}}
                        {{^isenabled}}<div class="gp-cell-status gp-status-disabled">{{#str}}gantt_cell_status_disabled, gestionprojet{{/str}}</div>{{/isenabled}}
                    </a>
                </div>
            {{/isfilled}}
            {{^isfilled}}<div class="gp-cell gp-cell-empty"></div>{{/isfilled}}
        {{/rowstudent}}

    </div>
</div>
```

- [ ] **Step 2 : Vérifier la balance Mustache**

```bash
grep -c "{{" gestionprojet/templates/home_gantt.mustache
grep -c "}}" gestionprojet/templates/home_gantt.mustache
```
Expected : balanced.

- [ ] **Step 3 : Commit**

```bash
git add gestionprojet/templates/home_gantt.mustache
git commit -m "feat(home): add home_gantt Mustache template (3×8 layout)"
```

---

### Task 10 : Brancher le rendu du Gantt dans `home.mustache`

**Files :**
- Modify: `gestionprojet/templates/home.mustache`

- [ ] **Step 1 : Repérer le bloc enseignant existant**

```bash
grep -n "isteacher\|teachersteps\|gradingsteps\|dashboard\|correction_models\|page=correctionmodels" gestionprojet/templates/home.mustache | head -20
```

- [ ] **Step 2 : Remplacer le bloc enseignant**

Dans `gestionprojet/templates/home.mustache`, repérer le bloc `{{#isteacher}}…{{/isteacher}}` qui contient les sections existantes (teachersteps + gradingsteps + dashboard + bouton Modèles de correction). **Remplacer son contenu** par :

```mustache
{{#isteacher}}
    {{#teacherpagescomplete}}
    {{/teacherpagescomplete}}

    {{> mod_gestionprojet/home_gantt}}
{{/isteacher}}
```

> Note : le bloc `{{> mod_gestionprojet/home_gantt}}` consomme la clé `gantt` du contexte (déjà construite en Task 8) car le partial accède à `cmid`, `sesskey`, `columns`, `rowdocs`, `rowmodels`, `rowstudent`, `summary` au niveau parent — les références doivent être `{{gantt.cmid}}`, etc. Adapter le template `home_gantt.mustache` créé en Task 9 pour préfixer ses variables par `gantt.`, OU appeler le partial dans un bloc `{{#gantt}}{{> ...}}{{/gantt}}` pour ouvrir un scope local.

Préférer la seconde option (plus lisible) :

```mustache
{{#isteacher}}
    {{#gantt}}
        {{> mod_gestionprojet/home_gantt}}
    {{/gantt}}
{{/isteacher}}
```

Avec ce wrapper, les variables internes du partial (`cmid`, `columns`, etc.) sont résolues directement.

- [ ] **Step 3 : Vérifier la balance Mustache et la syntaxe**

```bash
grep -c "{{" gestionprojet/templates/home.mustache
grep -c "}}" gestionprojet/templates/home.mustache
```

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/templates/home.mustache
git commit -m "feat(home): include home_gantt partial in teacher view"
```

---

### Task 11 : Ajouter les styles CSS du Gantt

**Files :**
- Modify: `gestionprojet/styles.css`

- [ ] **Step 1 : Ajouter le bloc CSS en fin de fichier**

À la fin de `gestionprojet/styles.css` :

```css
/* ============================================================
   Home Gantt dashboard (teacher view)
   ============================================================ */
.path-mod-gestionprojet .gp-gantt {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 22px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.04);
    margin: 16px 0 24px;
    overflow-x: auto;
}
.path-mod-gestionprojet .gp-gantt-summary {
    display: flex;
    gap: 24px;
    padding: 12px 16px;
    margin-bottom: 18px;
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    font-size: 13px;
    color: #065f46;
    font-weight: 600;
    flex-wrap: wrap;
}
.path-mod-gestionprojet .gp-gantt-summary-ungraded {
    color: #92400e;
}
.path-mod-gestionprojet .gp-gantt-summary-ungraded.gp-summary-ok {
    color: #065f46;
}
.path-mod-gestionprojet .gp-gantt-grid {
    display: grid;
    grid-template-columns: 180px repeat(8, minmax(110px, 1fr));
    gap: 6px;
    min-width: 1100px;
}
.path-mod-gestionprojet .gp-gantt-corner {
    background: transparent;
}
.path-mod-gestionprojet .gp-col-head {
    text-align: center;
    padding: 10px 6px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px 8px 0 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 4px;
}
.path-mod-gestionprojet .gp-col-name {
    font-size: 11px;
    color: #4b5563;
    font-weight: 600;
    line-height: 1.2;
}
.path-mod-gestionprojet .gp-row-label {
    display: flex;
    align-items: center;
    font-weight: 700;
    font-size: 13px;
    color: #111827;
    padding: 12px 14px;
    background: #f3f4f6;
    border-radius: 8px;
    border-left: 4px solid;
}
.path-mod-gestionprojet .gp-row-label-docs { border-color: #4f46e5; }
.path-mod-gestionprojet .gp-row-label-models { border-color: #d97706; }
.path-mod-gestionprojet .gp-row-label-student { border-color: #059669; }
.path-mod-gestionprojet .gp-cell {
    padding: 10px 8px;
    border-radius: 8px;
    font-size: 12px;
    border: 1px solid transparent;
    min-height: 70px;
    position: relative;
    display: flex;
    flex-direction: column;
}
.path-mod-gestionprojet .gp-cell-docs {
    background: linear-gradient(180deg, #eef2ff 0%, #fafbff 100%);
    border-color: #c7d2fe;
}
.path-mod-gestionprojet .gp-cell-models {
    background: linear-gradient(180deg, #fffbeb 0%, #fffefa 100%);
    border-color: #fde68a;
}
.path-mod-gestionprojet .gp-cell-student {
    background: linear-gradient(180deg, #ecfdf5 0%, #f7fefb 100%);
    border-color: #a7f3d0;
}
.path-mod-gestionprojet .gp-cell-empty {
    background: transparent;
    border: none;
}
.path-mod-gestionprojet .gp-cell-disabled {
    opacity: 0.45;
}
.path-mod-gestionprojet .gp-cell-cb {
    position: absolute;
    top: 6px;
    left: 6px;
    width: 16px;
    height: 16px;
    accent-color: #4f46e5;
    cursor: pointer;
    z-index: 2;
}
.path-mod-gestionprojet .gp-cell-cb-shared {
    accent-color: #d97706;
}
.path-mod-gestionprojet .gp-cell-link {
    display: block;
    text-decoration: none;
    color: inherit;
    padding: 6px 4px 4px 24px;
    text-align: center;
    height: 100%;
}
.path-mod-gestionprojet .gp-cell-link:hover {
    text-decoration: none;
    color: inherit;
}
.path-mod-gestionprojet .gp-cell-name {
    font-weight: 600;
    color: #111827;
    font-size: 12px;
}
.path-mod-gestionprojet .gp-cell-status {
    font-size: 10px;
    color: #6b7280;
    margin-top: 4px;
}
.path-mod-gestionprojet .gp-cell-status.gp-status-done {
    color: #065f46;
    font-weight: 700;
}
.path-mod-gestionprojet .gp-cell-status.gp-status-todo {
    color: #92400e;
    font-weight: 700;
}
.path-mod-gestionprojet .gp-cell-status.gp-status-disabled {
    color: #6b7280;
    font-weight: 600;
    font-style: italic;
}
.path-mod-gestionprojet .gp-cell-status.gp-status-provided {
    background: #4f46e5;
    color: #fff;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    display: inline-block;
}
.path-mod-gestionprojet .gp-cell-link-arrow {
    position: absolute;
    top: -4px;
    left: 50%;
    transform: translateX(-50%);
    color: #d97706;
    font-weight: 700;
    font-size: 14px;
}
```

- [ ] **Step 2 : Vérifier l'absence de CSS inline ailleurs**

```bash
grep -rn '<style' gestionprojet --include="*.php" --include="*.mustache"
```
Expected : aucun nouveau résultat.

- [ ] **Step 3 : Commit**

```bash
git add gestionprojet/styles.css
git commit -m "style(home): add CSS for Gantt dashboard layout"
```

---

### Task 12 : Ajouter les chaînes de langue Gantt

**Files :**
- Modify: `gestionprojet/lang/en/gestionprojet.php`
- Modify: `gestionprojet/lang/fr/gestionprojet.php`

- [ ] **Step 1 : Ajouter dans `lang/en/gestionprojet.php`**

```php
$string['gantt_row_teacher_docs'] = 'Teacher documents';
$string['gantt_row_correction_models'] = 'Correction models';
$string['gantt_row_student_activities'] = 'Student activities';
$string['gantt_cell_status_done'] = 'Configured';
$string['gantt_cell_status_todo'] = 'To configure';
$string['gantt_cell_status_disabled'] = 'Disabled';
$string['gantt_toggle_success'] = 'Step updated';
$string['gantt_toggle_error'] = 'Error while updating step';
$string['gantt_progress_summary'] = '{$a->configured}/{$a->total} phases configured';
$string['gantt_ungraded_summary'] = '{$a} submission(s) to grade';
$string['gantt_ungraded_summary_zero'] = 'No pending submissions';
```

- [ ] **Step 2 : Ajouter dans `lang/fr/gestionprojet.php`**

```php
$string['gantt_row_teacher_docs'] = 'Documents enseignant';
$string['gantt_row_correction_models'] = 'Modèles de correction';
$string['gantt_row_student_activities'] = 'Activités élèves';
$string['gantt_cell_status_done'] = 'Configuré';
$string['gantt_cell_status_todo'] = 'À configurer';
$string['gantt_cell_status_disabled'] = 'Désactivé';
$string['gantt_toggle_success'] = 'Étape mise à jour';
$string['gantt_toggle_error'] = 'Erreur lors de la mise à jour';
$string['gantt_progress_summary'] = '{$a->configured}/{$a->total} phases configurées';
$string['gantt_ungraded_summary'] = '{$a} soumission(s) à corriger';
$string['gantt_ungraded_summary_zero'] = 'Aucune soumission en attente';
```

- [ ] **Step 3 : Vérifier la syntaxe**

```bash
/usr/local/bin/php -l gestionprojet/lang/en/gestionprojet.php
/usr/local/bin/php -l gestionprojet/lang/fr/gestionprojet.php
```

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/lang/en/gestionprojet.php gestionprojet/lang/fr/gestionprojet.php
git commit -m "lang: add Gantt dashboard strings"
```

---

### Task 13 : Créer le endpoint AJAX `ajax/toggle_step.php`

**Files :**
- Create: `gestionprojet/ajax/toggle_step.php`

- [ ] **Step 1 : Créer le fichier**

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
 * AJAX endpoint: toggle the activation of a single step from the home Gantt.
 *
 * Inputs (POST):
 *   cmid    int  Course module ID
 *   stepnum int  Step number (1..8)
 *   enabled int  0 to disable, 1 to enable (mode student); 2 reserved for step4 provided mode
 *
 * Output: JSON {success: bool, message?: string}
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

require_login();
require_sesskey();

$cmid = required_param('cmid', PARAM_INT);
$stepnum = required_param('stepnum', PARAM_INT);
$enabled = required_param('enabled', PARAM_INT);

if ($stepnum < 1 || $stepnum > 8) {
    throw new \moodle_exception('invalidparameter', 'error');
}
if (!in_array($enabled, [0, 1, 2], true)) {
    throw new \moodle_exception('invalidparameter', 'error');
}
if ($enabled === 2 && $stepnum !== 4) {
    throw new \moodle_exception('invalidparameter', 'error');
}

$cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_capability('mod/gestionprojet:configureteacherpages', $context);

$field = 'enable_step' . $stepnum;
$update = new stdClass();
$update->id = $gestionprojet->id;
$update->$field = $enabled;
$DB->update_record('gestionprojet', $update);

echo json_encode([
    'success' => true,
    'message' => get_string('gantt_toggle_success', 'gestionprojet'),
]);
```

- [ ] **Step 2 : Vérifier la syntaxe**

```bash
/usr/local/bin/php -l gestionprojet/ajax/toggle_step.php
```

- [ ] **Step 3 : Vérifier les patterns sécurité**

```bash
grep -E "require_login|require_sesskey|require_capability|required_param" gestionprojet/ajax/toggle_step.php
```
Expected : 4 lignes (les 4 patterns présents).

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/ajax/toggle_step.php
git commit -m "feat(ajax): add toggle_step.php endpoint for live step activation"
```

---

### Task 14 : Créer le module AMD `amd/src/gantt.js`

**Files :**
- Create: `gestionprojet/amd/src/gantt.js`
- Create: `gestionprojet/amd/build/gantt.min.js` (minified copy — see note)

- [ ] **Step 1 : Créer le fichier source**

Contenu de `gestionprojet/amd/src/gantt.js` :

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
 * Gantt home dashboard — live step activation via AJAX.
 *
 * @module     mod_gestionprojet/gantt
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification', 'core/str', 'core/config'], function($, Notification, Str, Config) {

    var ENDPOINT = '/mod/gestionprojet/ajax/toggle_step.php';

    var Gantt = {

        init: function() {
            var $root = $('.gp-gantt');
            if (!$root.length) {
                return;
            }
            var cmid = $root.data('cmid');
            var sesskey = $root.data('sesskey');
            $root.on('change', '.gp-cell-cb', function() {
                Gantt.handleToggle($(this), cmid, sesskey);
            });
        },

        handleToggle: function($cb, cmid, sesskey) {
            var stepnum = $cb.data('stepnum');
            var row = $cb.data('row');
            var enabled = $cb.is(':checked') ? 1 : 0;
            var $cell = $cb.closest('.gp-cell');

            // Optimistic UI update.
            Gantt.applyVisualState($cell, $cb, row, enabled);

            $.ajax({
                url: Config.wwwroot + ENDPOINT,
                method: 'POST',
                dataType: 'json',
                data: {
                    cmid: cmid,
                    stepnum: stepnum,
                    enabled: enabled,
                    sesskey: sesskey
                }
            }).done(function(response) {
                if (!response.success) {
                    Gantt.revertVisualState($cell, $cb, row, !enabled);
                    Notification.alert('Erreur', response.message || 'Erreur lors de la mise à jour');
                }
            }).fail(function() {
                Gantt.revertVisualState($cell, $cb, row, !enabled);
                Str.get_string('gantt_toggle_error', 'mod_gestionprojet').done(function(s) {
                    Notification.alert('Erreur', s);
                });
            });
        },

        applyVisualState: function($cell, $cb, row, enabled) {
            if (enabled) {
                $cell.removeClass('gp-cell-disabled');
            } else {
                $cell.addClass('gp-cell-disabled');
            }
            // For shared (row=models) checkbox, mirror the visual change on row 3 (student).
            if (row === 'models') {
                var stepnum = $cb.data('stepnum');
                $('.gp-cell-student[data-stepnum="' + stepnum + '"]').toggleClass('gp-cell-disabled', !enabled);
            }
        },

        revertVisualState: function($cell, $cb, row, restoreEnabled) {
            $cb.prop('checked', restoreEnabled);
            this.applyVisualState($cell, $cb, row, restoreEnabled);
        }
    };

    return Gantt;
});
```

- [ ] **Step 2 : Créer la version minifiée**

Copier le contenu source en l'état (sans minification réelle — Moodle accepte un `.min.js` non minifié comme fallback) dans `gestionprojet/amd/build/gantt.min.js`.

> **Note importante** : la pipeline AMD officielle est `grunt amd`. Si `grunt` est disponible localement, utiliser `cd gestionprojet && grunt amd` pour générer la version minifiée propre. Sinon, copier le source en l'état suffit pour le préprod ; à la finalisation v2.2.0 (Phase 4), passer un `grunt amd` propre avant le ZIP de release.

```bash
cp gestionprojet/amd/src/gantt.js gestionprojet/amd/build/gantt.min.js
```

- [ ] **Step 3 : Vérifier**

```bash
ls gestionprojet/amd/src/gantt.js gestionprojet/amd/build/gantt.min.js
node -c gestionprojet/amd/src/gantt.js 2>&1 || echo "Note: node not available — skip JS lint"
```

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/amd/src/gantt.js gestionprojet/amd/build/gantt.min.js
git commit -m "feat(amd): add gantt.js module for live step toggle"
```

---

### Task 15 : Charger le module AMD `gantt` depuis `home.php`

**Files :**
- Modify: `gestionprojet/pages/home.php`

- [ ] **Step 1 : Ajouter le chargement AMD**

Dans `gestionprojet/pages/home.php`, dans le bloc `if ($isteacher) {`, à la fin de la construction du Gantt (juste avant de fermer le `if`), ajouter :

```php
$PAGE->requires->js_call_amd('mod_gestionprojet/gantt', 'init');
```

- [ ] **Step 2 : Vérifier la syntaxe**

```bash
/usr/local/bin/php -l gestionprojet/pages/home.php
```

- [ ] **Step 3 : Commit**

```bash
git add gestionprojet/pages/home.php
git commit -m "feat(home): load gantt AMD module on teacher home"
```

---

### Task 16 : Supprimer la section "Étapes actives" de `mod_form.php`

**Files :**
- Modify: `gestionprojet/mod_form.php`

- [ ] **Step 1 : Identifier le bloc à supprimer**

```bash
sed -n '94,102p' gestionprojet/mod_form.php
```

- [ ] **Step 2 : Supprimer le bloc**

Dans `gestionprojet/mod_form.php`, supprimer entièrement les lignes suivantes (vers les lignes 93-101) :

```php
        // Active steps settings
        $mform->addElement('header', 'activesteps', get_string('activesteps', 'gestionprojet'));

        $steps_order = [1, 3, 2, 7, 4, 5, 8, 6];
        foreach ($steps_order as $i) {
            $mform->addElement('advcheckbox', 'enable_step' . $i, get_string('step' . $i, 'gestionprojet'));
            $default = ($i == 7 || $i == 8) ? 0 : 1;
            $mform->setDefault('enable_step' . $i, $default);
        }
```

> Note importante : les valeurs par défaut (`enable_step7=0`, `enable_step8=0`, autres = 1) sont déjà couvertes par les `DEFAULT="1"` / `DEFAULT="0"` dans `db/install.xml`. Vérifier que `db/install.xml` a bien `DEFAULT="0"` pour `enable_step7` et `enable_step8` ; sinon les ajuster (autre commit séparé).

```bash
grep -n "enable_step7\|enable_step8" gestionprojet/db/install.xml
```

- [ ] **Step 3 : Vérifier la syntaxe**

```bash
/usr/local/bin/php -l gestionprojet/mod_form.php
```

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/mod_form.php
git commit -m "refactor(mod_form): remove 'active steps' section (now on home Gantt)"
```

---

### Task 17 : Transformer la route `correctionmodels` en redirect dans `view.php`

**Files :**
- Modify: `gestionprojet/view.php`

- [ ] **Step 1 : Repérer le case existant**

```bash
sed -n '100,116p' gestionprojet/view.php
```

- [ ] **Step 2 : Remplacer le case**

Remplacer le bloc `case 'correctionmodels':` par :

```php
case 'correctionmodels':
    // Deprecated route: correction models are now displayed directly on the home Gantt.
    redirect(new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]));
    break;
```

- [ ] **Step 3 : Vérifier la syntaxe**

```bash
/usr/local/bin/php -l gestionprojet/view.php
```

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/view.php
git commit -m "feat(routing): redirect deprecated correctionmodels route to home"
```

---

### Task 18 : Supprimer les artefacts obsolètes et les liens vers la page supprimée

**Files :**
- Delete: `gestionprojet/pages/correction_models.php`
- Delete: `gestionprojet/templates/correction_models.mustache`
- Modify: `gestionprojet/classes/output/renderer.php`
- Modify: `gestionprojet/pages/step4_teacher.php` à `step8_teacher.php` (5 fichiers)

- [ ] **Step 1 : Vérifier qu'aucun usage ne subsiste hors des fichiers à retirer**

```bash
grep -rn "correction_models\.mustache\|render_correction_models\|pages/correction_models" gestionprojet --include="*.php" --include="*.mustache"
```

Attendu : seuls 3 fichiers ciblés cités (la route est déjà un redirect en Task 17, et les liens des step4-8 teacher sont retirés en Task 19).

- [ ] **Step 2 : Supprimer les deux fichiers**

```bash
git rm gestionprojet/pages/correction_models.php
git rm gestionprojet/templates/correction_models.mustache
```

- [ ] **Step 3 : Retirer `render_correction_models()` du renderer**

Dans `gestionprojet/classes/output/renderer.php`, supprimer la méthode `render_correction_models($data)` (commence vers la ligne 44). Garder le reste de la classe intact.

- [ ] **Step 4 : Vérifier la syntaxe**

```bash
/usr/local/bin/php -l gestionprojet/classes/output/renderer.php
```

- [ ] **Step 5 : Re-vérifier**

```bash
grep -rn "render_correction_models\|correction_models\.mustache" gestionprojet --include="*.php" --include="*.mustache"
```
Expected : aucun résultat.

- [ ] **Step 6 : Repérer et remplacer les liens correctionmodels dans les 5 pages step_teacher**

```bash
grep -n "page.*correctionmodels\|page=correctionmodels" gestionprojet/pages/step{4,5,6,7,8}_teacher.php
```

Pour chaque occurrence d'un lien du type :

```php
new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels'])
```

remplacer par :

```php
new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id])
```

- [ ] **Step 7 : Vérifier la syntaxe**

```bash
for f in gestionprojet/pages/step{4,5,6,7,8}_teacher.php; do /usr/local/bin/php -l "$f"; done
```

- [ ] **Step 8 : Commit**

```bash
git add gestionprojet/classes/output/renderer.php gestionprojet/pages/step4_teacher.php gestionprojet/pages/step5_teacher.php gestionprojet/pages/step6_teacher.php gestionprojet/pages/step7_teacher.php gestionprojet/pages/step8_teacher.php
git commit -m "refactor: remove obsolete correctionmodels page, template, renderer method, and links"
```

---

### Task 19 : Validation manuelle de la Phase 2

- [ ] **Step 1 : Bumper version pour le déploiement préprod**

Dans `gestionprojet/version.php` :
- `$plugin->version = 2026050203;` → `2026050204`
- `$plugin->release = '2.2.0-dev (phase 1 hotfix 2)';` → `'2.2.0-dev (phase 2)'`

- [ ] **Step 2 : Commit + push + déploiement préprod**

(via le workflow de déploiement habituel : push origin, ZIP, SCP, unzip, upgrade CLI, purge caches.)

- [ ] **Step 3 : Tests fonctionnels**

1. Sur la home enseignant : vérifier le Gantt 3×8 (3 lignes : docs/modèles/élèves), bandeau de tête (X/Y phases configurées + soumissions à corriger).
2. Cocher/décocher case d'une cellule de ligne 1 (ex. step 2) : la cellule grise/dégrise immédiatement, recharger la page → état persisté.
3. Cocher/décocher case d'une cellule de ligne 2 (ex. step 4) : cellule de ligne 2 ET de ligne 3 grisent simultanément.
4. Cliquer sur le contenu (hors checkbox) : ouvre la bonne page (édition doc, modèle, ou grading).
5. Ouvrir les paramètres d'activité → confirmer que la section "Étapes actives" est absente.
6. Ouvrir `view.php?id=X&page=correctionmodels` → redirige sur la home.
7. Non-régression Phase 1 : barre d'onglets toujours présente sur les 8 pages step.

---

## Phase 3 — Friction 1 : Mode "CDCF fourni par l'enseignant"

### Task 20 : Remplacer le checkbox `enable_step4` par un select 3-états dans `mod_form.php`

**Files :**
- Modify: `gestionprojet/mod_form.php`

- [ ] **Step 1 : Localiser la boucle des étapes actives**

```bash
sed -n '94,102p' gestionprojet/mod_form.php
```

Repérer le bloc :
```php
$steps_order = [1, 3, 2, 7, 4, 5, 8, 6];
foreach ($steps_order as $i) {
    $mform->addElement('advcheckbox', 'enable_step' . $i, get_string('step' . $i, 'gestionprojet'));
    ...
}
```

- [ ] **Step 2 : Remplacer le bloc**

Remplacer le bloc complet par :

```php
$steps_order = [1, 3, 2, 7, 4, 5, 8, 6];
foreach ($steps_order as $i) {
    if ($i === 4) {
        $modes = [
            0 => get_string('step4_mode_disabled', 'gestionprojet'),
            1 => get_string('step4_mode_student', 'gestionprojet'),
            2 => get_string('step4_mode_provided', 'gestionprojet'),
        ];
        $mform->addElement('select', 'enable_step4', get_string('step4', 'gestionprojet'), $modes);
        $mform->setDefault('enable_step4', 1);
    } else {
        $mform->addElement('advcheckbox', 'enable_step' . $i, get_string('step' . $i, 'gestionprojet'));
        $default = ($i == 7 || $i == 8) ? 0 : 1;
        $mform->setDefault('enable_step' . $i, $default);
    }
}
```

- [ ] **Step 3 : Vérifier la syntaxe**

```bash
php -l gestionprojet/mod_form.php
```
Expected : `No syntax errors detected`.

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/mod_form.php
git commit -m "feat(step4): switch enable_step4 to 3-state select (disabled/student/provided)"
```

---

### Task 21 : Ajouter les chaînes de langue pour le mode fourni

**Files :**
- Modify: `gestionprojet/lang/en/gestionprojet.php`
- Modify: `gestionprojet/lang/fr/gestionprojet.php`

- [ ] **Step 1 : Ajouter les chaînes EN**

Dans `gestionprojet/lang/en/gestionprojet.php` :

```php
$string['step4_mode_disabled'] = 'Disabled';
$string['step4_mode_student'] = 'Produced by students';
$string['step4_mode_provided'] = 'Provided by the teacher';
$string['step4_provided_badge'] = 'Provided';
$string['step4_provided_notice_teacher'] = 'Provided mode: this content will be visible to students in read-only mode. The "AI Instructions" field is never shown to students.';
$string['step4_provided_notice_student'] = 'This Functional Specification has been provided by your teacher. You cannot modify it.';
```

- [ ] **Step 2 : Ajouter les chaînes FR**

Dans `gestionprojet/lang/fr/gestionprojet.php` :

```php
$string['step4_mode_disabled'] = 'Désactivé';
$string['step4_mode_student'] = 'Production par les élèves';
$string['step4_mode_provided'] = 'Fourni par l\'enseignant';
$string['step4_provided_badge'] = 'Fourni';
$string['step4_provided_notice_teacher'] = 'Mode fourni : ce contenu sera visible par les élèves en lecture seule. Le champ « Instructions IA » n\'est jamais affiché aux élèves.';
$string['step4_provided_notice_student'] = 'Ce Cahier des Charges Fonctionnel a été fourni par votre enseignant. Vous ne pouvez pas le modifier.';
```

- [ ] **Step 3 : Vérifier la syntaxe**

```bash
php -l gestionprojet/lang/en/gestionprojet.php
php -l gestionprojet/lang/fr/gestionprojet.php
```
Expected : `No syntax errors detected` pour chacun.

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/lang/en/gestionprojet.php gestionprojet/lang/fr/gestionprojet.php
git commit -m "lang: add strings for step4 provided mode"
```

---

### Task 22 : Afficher l'encart d'information sur `step4_teacher.php` en mode fourni

**Files :**
- Modify: `gestionprojet/pages/step4_teacher.php`

- [ ] **Step 1 : Repérer un emplacement après les onglets et avant le formulaire**

```bash
grep -n "OUTPUT->header\|step_tabs\|<form\|<div class=\"step4-container" gestionprojet/pages/step4_teacher.php | head -10
```

Cible : juste après l'appel à `gestionprojet_build_step_tabs(...)` (ajouté en Task 4) et avant le formulaire.

- [ ] **Step 2 : Insérer l'encart conditionnel**

Insérer le bloc PHP suivant :

```php
// Provided mode notice for the teacher.
if ((int)$gestionprojet->enable_step4 === 2) {
    echo html_writer::div(
        get_string('step4_provided_notice_teacher', 'gestionprojet'),
        'gp-provided-notice'
    );
}
```

- [ ] **Step 3 : Ajouter le style CSS dans `styles.css`**

Ajouter en fin de `gestionprojet/styles.css` :

```css
.path-mod-gestionprojet .gp-provided-notice {
    background: #eef2ff;
    border-left: 4px solid #4f46e5;
    color: #1e1b4b;
    padding: 12px 16px;
    border-radius: 6px;
    margin: 12px 0 18px;
    font-size: 14px;
    line-height: 1.5;
}
```

- [ ] **Step 4 : Vérifier la syntaxe**

```bash
php -l gestionprojet/pages/step4_teacher.php
```
Expected : `No syntax errors detected`.

- [ ] **Step 5 : Commit**

```bash
git add gestionprojet/pages/step4_teacher.php gestionprojet/styles.css
git commit -m "feat(step4): show teacher notice when CDCF is in provided mode"
```

---

### Task 23 : Adapter la vue élève `step4.php` pour le mode fourni

**Files :**
- Modify: `gestionprojet/pages/step4.php`

- [ ] **Step 1 : Étudier la structure actuelle**

```bash
sed -n '60,120p' gestionprojet/pages/step4.php
```

Identifier :
- L'endroit où la submission élève est récupérée (`$submission`)
- Les variables `$isLocked`, `$canSubmit`
- Le rendu du formulaire et du bouton "Soumettre"

- [ ] **Step 2 : Détecter le mode fourni en début de page**

Juste après la récupération de `$submission` (vers la ligne ~90), insérer :

```php
$isProvidedMode = ((int)$gestionprojet->enable_step4 === 2);

if ($isProvidedMode) {
    // Use the teacher's CDCF as read-only data source.
    $teachercdcf = $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]);
    if ($teachercdcf) {
        // Map teacher fields onto $submission so the rest of the page renders the same fields.
        $submission->produit = $teachercdcf->produit ?? '';
        $submission->milieu = $teachercdcf->milieu ?? '';
        $submission->fp = $teachercdcf->fp ?? '';
        $submission->interacteurs_data = $teachercdcf->interacteurs_data ?? '';
    }
    // Force read-only behavior.
    $isLocked = true;
    $canSubmit = false;
    $canRevert = false;
}
```

- [ ] **Step 3 : Afficher l'encart d'information côté élève**

Juste après `echo $OUTPUT->header();` (et après la barre d'onglets si elle existe sur cette page — la barre d'onglets est sur les pages enseignant, pas sur `step4.php` côté élève — confirmer en lisant le haut du fichier) :

```php
if ($isProvidedMode) {
    echo html_writer::div(
        get_string('step4_provided_notice_student', 'gestionprojet'),
        'gp-provided-notice'
    );
}
```

- [ ] **Step 4 : Désactiver l'autosave en mode fourni**

Repérer l'éventuel appel à `js_call_amd('mod_gestionprojet/autosave', ...)` dans `step4.php`. L'envelopper dans :

```php
if (!$isProvidedMode) {
    $PAGE->requires->js_call_amd('mod_gestionprojet/autosave', 'init', [...existing...]);
}
```

- [ ] **Step 5 : Vérifier que les boutons d'action ne s'affichent pas en mode fourni**

Le rendu existant utilise déjà `$canSubmit` et `$isLocked` pour conditionner les boutons. Comme on a forcé `$canSubmit = false` et `$isLocked = true`, **rien d'autre à modifier** sur ce point. Vérifier en relisant le fichier qu'aucun bouton d'action n'échappe à ces conditions.

- [ ] **Step 6 : Vérifier la syntaxe**

```bash
php -l gestionprojet/pages/step4.php
```
Expected : `No syntax errors detected`.

- [ ] **Step 7 : Commit**

```bash
git add gestionprojet/pages/step4.php
git commit -m "feat(step4): render teacher-provided CDCF read-only on student page"
```

---

### Task 24 : Mettre à jour la home élève pour le badge "Fourni par l'enseignant" sur step 4

**Files :**
- Modify: `gestionprojet/pages/home.php`
- Modify: `gestionprojet/templates/home.mustache`

- [ ] **Step 1 : Adapter la construction des cartes élève dans `home.php`**

Dans `home.php`, repérer la boucle qui construit `$studentstepsraw` et `$studentsteps` (vers les lignes ~236-265). Pour `stepnum = 4`, ajouter le marqueur `isprovided` :

Juste avant le `$studentsteps[] = [...]` final, à l'intérieur de la boucle, ajouter :

```php
$isstep4provided = ($stepnum === 4 && (int)$gestionprojet->enable_step4 === 2);
```

et étendre le tableau résultant :

```php
$studentsteps[] = [
    'stepnum' => $stepnum,
    'icon' => $stepicons[$stepnum],
    'title' => get_string('step' . $stepnum, 'gestionprojet'),
    'description' => get_string('step' . $stepnum . '_desc', 'gestionprojet'),
    'iscomplete' => $stepdata['complete'],
    'hasgrade' => $hasgrade,
    'gradeformatted' => $gradeformatted,
    'isprovided' => $isstep4provided,
    'url' => 'view.php?id=' . $cm->id . '&step=' . $stepnum,
];
```

- [ ] **Step 2 : Mettre à jour la carte élève dans `home.mustache`**

Dans la section qui rend `{{#studentsteps}}…{{/studentsteps}}`, à l'endroit où s'affiche aujourd'hui le badge "complété/à faire" pour la carte step 4, ajouter une variante "fourni" prioritaire :

```mustache
{{#isprovided}}
    <span class="gp-badge gp-badge-provided">{{#str}} step4_provided_badge, gestionprojet {{/str}}</span>
{{/isprovided}}
{{^isprovided}}
    {{! existing complete/incomplete badge logic — unchanged }}
{{/isprovided}}
```

> Adapter au markup réel de `home.mustache`. Si le markup actuel utilise un seul bloc conditionnel, le wrapper avec `{{^isprovided}}...{{/isprovided}}`.

- [ ] **Step 3 : Vérifier la syntaxe**

```bash
php -l gestionprojet/pages/home.php
grep -c "{{" gestionprojet/templates/home.mustache
grep -c "}}" gestionprojet/templates/home.mustache
```
Expected : pas d'erreur PHP, balance Mustache OK.

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/pages/home.php gestionprojet/templates/home.mustache
git commit -m "feat(home): show 'Provided' badge on student step 4 card in provided mode"
```

---

### Task 25 : Validation manuelle de la Phase 3

- [ ] **Step 1 : Purger les caches**

```bash
php admin/cli/purge_caches.php
```

- [ ] **Step 2 : Mode élève (mode actuel inchangé)**

1. Configurer une activité avec `enable_step4 = 1` (Production par les élèves)
2. En tant qu'élève, ouvrir step 4 → formulaire éditable, bouton Soumettre visible
3. Soumettre → étape se verrouille, note possible
4. ✅ Aucune régression du comportement existant

- [ ] **Step 3 : Mode fourni (nouveau)**

1. Repasser l'activité en `enable_step4 = 2`
2. Côté enseignant : ouvrir step4_teacher → encart "Mode fourni" en haut
3. Remplir le CDCF côté enseignant et sauvegarder
4. Côté élève : ouvrir step 4 → contenu visible en lecture seule, encart d'information, **pas de bouton Soumettre, pas de note**
5. Sur la home élève : carte step 4 montre le badge "Fourni par l'enseignant"
6. Sur la home enseignant : carte step 4 du bloc Modèles de correction montre le badge "Fourni"

- [ ] **Step 4 : Mode désactivé**

1. Repasser en `enable_step4 = 0`
2. Vérifier que step 4 n'est plus accessible côté élève (erreur `stepdisabled` attendue)
3. Vérifier que l'onglet step 4 dans la barre de nav est grisé/non cliquable

---

## Phase 4 — Finalisation

### Task 26 : Bump version et upgrade step

**Files :**
- Modify: `gestionprojet/version.php`
- Modify: `gestionprojet/db/upgrade.php`

- [ ] **Step 1 : Mettre à jour `version.php`**

Remplacer dans `gestionprojet/version.php` :

```php
$plugin->version = 2026022500;  // YYYYMMDDXX format
$plugin->release = '2.1.0';
```

par :

```php
$plugin->version = 2026050200;  // YYYYMMDDXX format
$plugin->release = '2.2.0';
```

- [ ] **Step 2 : Ajouter un upgrade step**

À la fin du `function xmldb_gestionprojet_upgrade($oldversion)` dans `gestionprojet/db/upgrade.php`, juste avant `return true;`, ajouter :

```php
    if ($oldversion < 2026050200) {
        // v2.2.0: enable_step4 now supports value 2 (provided by teacher).
        // No schema change required — existing int(1) field already accepts 0..9.
        upgrade_mod_savepoint(true, 2026050200, 'gestionprojet');
    }
```

- [ ] **Step 3 : Vérifier la syntaxe**

```bash
php -l gestionprojet/version.php
php -l gestionprojet/db/upgrade.php
```
Expected : `No syntax errors detected` pour chacun.

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/version.php gestionprojet/db/upgrade.php
git commit -m "chore: bump version to 2.2.0"
```

---

### Task 27 : Mettre à jour CHANGELOG.md

**Files :**
- Modify: `gestionprojet/CHANGELOG.md` (ou `CHANGELOG.md` racine — vérifier l'emplacement)

- [ ] **Step 1 : Localiser le CHANGELOG**

```bash
ls gestionprojet/CHANGELOG.md CHANGELOG.md 2>/dev/null
```

- [ ] **Step 2 : Ajouter une entrée v2.2.0 en haut du fichier**

Insérer après l'en-tête principal :

```markdown
## [2.2.0] — 2026-05-02

### Ajouts
- Mode "CDCF fourni par l'enseignant" pour l'étape 4 — l'enseignant peut désormais fournir un Cahier des Charges Fonctionnel clé-en-main que les élèves consultent en lecture seule.
- Modèles de correction directement visibles sur la home enseignant (suppression du clic intermédiaire).
- Barre de navigation directe à 8 onglets sur les pages enseignant (étapes 1, 2, 3) et les modèles de correction (étapes 4-8 teacher).

### Modifications
- Le paramètre "Étapes actives" pour l'étape 4 est désormais un menu à 3 valeurs (Désactivé / Production par les élèves / Fourni par l'enseignant) au lieu d'un simple checkbox.
- La page `correction_models.php` est dépréciée ; son URL redirige silencieusement vers la home.

### Suppressions internes
- Fichier `pages/correction_models.php`
- Template `templates/correction_models.mustache`
- Méthode `render_correction_models()` du renderer
```

- [ ] **Step 3 : Commit**

```bash
git add CHANGELOG.md
git commit -m "docs: changelog entry for v2.2.0"
```

---

### Task 28 : Vérification finale de conformité Moodle

- [ ] **Step 1 : Aucun `<style>` ou `<script>` inline en PHP**

```bash
grep -rln '<style' gestionprojet --include="*.php"
grep -rln '<script' gestionprojet --include="*.php"
```
Expected : aucun fichier listé pour les changements de cette PR (les éventuels résultats préexistants à investiguer hors scope).

- [ ] **Step 2 : Aucun `$_GET`/`$_POST`/`$_REQUEST` ajouté**

```bash
git diff main..HEAD -- "*.php" | grep -E "\\\$_GET|\\\$_POST|\\\$_REQUEST"
```
Expected : aucun ajout (les `-` qui retirent ces patterns sont OK ; seuls les `+` posent problème).

- [ ] **Step 3 : Aucune chaîne hardcodée user-facing**

Inspecter le diff des templates et fichiers PHP pour vérifier que toute chaîne visible utilisateur passe par `get_string()` ou `{{#str}}`.

```bash
git diff main..HEAD -- "*.mustache" "*.php" | grep -E '^\+.*"[A-Z][a-z]+ [a-z]+' | head -20
```
Expected : aucun résultat pertinent (filtrer manuellement les commentaires et data-attributes techniques).

- [ ] **Step 4 : `phpcs --standard=moodle` (si disponible)**

```bash
phpcs --standard=moodle gestionprojet/ 2>/dev/null | tail -20
```
Expected : pas de nouvelle erreur introduite par cette PR. Si phpcs n'est pas installé, sauter cette étape.

- [ ] **Step 5 : Validation manuelle complète**

Reprendre tous les critères de succès du spec :
1. Mode fourni du CDCF fonctionne ✅
2. Home enseignant montre les 2 sections + dashboard ✅
3. Barre d'onglets sur les 8 pages concernées ✅
4. `grading.php` intact ✅
5. Redirect `correctionmodels` ✅

- [ ] **Step 6 : Pas de commit ici** — la validation est l'étape finale de revue.

---

## Synthèse des fichiers touchés

**Créés (5)** :
- `gestionprojet/templates/step_tabs.mustache` — barre d'onglets réutilisable (Phase 1)
- `gestionprojet/templates/home_gantt.mustache` — partial Gantt 3×8 (Phase 2)
- `gestionprojet/ajax/toggle_step.php` — endpoint AJAX d'activation live (Phase 2)
- `gestionprojet/amd/src/gantt.js` — module JS source (Phase 2)
- `gestionprojet/amd/build/gantt.min.js` — module JS minifié (Phase 2 ; régénérer via `grunt amd` avant release)

**Modifiés (16)** :
- `gestionprojet/lib.php`
- `gestionprojet/mod_form.php`
- `gestionprojet/version.php`
- `gestionprojet/view.php`
- `gestionprojet/grading.php`
- `gestionprojet/styles.css`
- `gestionprojet/db/upgrade.php`
- `gestionprojet/lang/en/gestionprojet.php`
- `gestionprojet/lang/fr/gestionprojet.php`
- `gestionprojet/templates/home.mustache`
- `gestionprojet/templates/grading_navigation.mustache`
- `gestionprojet/classes/output/renderer.php`
- `gestionprojet/pages/home.php`
- `gestionprojet/pages/step1.php`, `step2.php`, `step3.php`
- `gestionprojet/pages/step4.php`
- `gestionprojet/pages/step4_teacher.php` à `step8_teacher.php`
- `CHANGELOG.md`

**Supprimés (2)** :
- `gestionprojet/pages/correction_models.php`
- `gestionprojet/templates/correction_models.mustache`

**Aucune migration de schéma DB.**
