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

## Phase 2 — Friction 2 : Modèles de correction sur la home

### Task 8 : Préparer le contexte template pour les modèles dans `home.php`

**Files :**
- Modify: `gestionprojet/pages/home.php`

- [ ] **Step 1 : Étendre le bloc enseignant pour exposer les cartes de modèles**

Dans `gestionprojet/pages/home.php`, repérer le bloc qui calcule `modelscomplete` / `modelstotal` (vers les lignes 124-144). Juste après cette boucle, ajouter la construction des cartes :

```php
// Build correction models cards for the home template.
$correctionmodelsorder = [7, 4, 5, 8, 6];
$correctionmodels = [];
foreach ($correctionmodelsorder as $mstep) {
    if (!isset($modeltables[$mstep])) {
        continue;
    }
    $mfield = 'enable_step' . $mstep;
    $enableval = isset($gestionprojet->$mfield) ? (int)$gestionprojet->$mfield : 1;
    if ($enableval === 0) {
        continue;
    }
    $mrecord = $DB->get_record($modeltables[$mstep], ['gestionprojetid' => $gestionprojet->id]);
    $hasinstructions = $mrecord && !empty($mrecord->ai_instructions);
    $isprovided = ($mstep === 4 && $enableval === 2);
    // For step 4 in provided mode, completion is based on `produit` rather than `ai_instructions`.
    $iscomplete = $isprovided
        ? ($mrecord && !empty($mrecord->produit))
        : $hasinstructions;
    $correctionmodels[] = [
        'stepnum' => $mstep,
        'icon' => $stepicons[$mstep] ?? \mod_gestionprojet\output\icon::render_step($mstep, 'xl', 'purple'),
        'title' => get_string('step' . $mstep, 'gestionprojet'),
        'description' => get_string('step' . $mstep . '_desc', 'gestionprojet'),
        'iscomplete' => $iscomplete,
        'isprovided' => $isprovided,
        'url' => 'view.php?id=' . $cm->id . '&step=' . $mstep . '&mode=teacher',
    ];
}
$templatecontext['correctionmodels'] = $correctionmodels;
$templatecontext['hascorrectionmodels'] = !empty($correctionmodels);
```

> Note : `$stepicons` est déjà construit pour les steps 1-8 plus haut dans le fichier ; le fallback `??` n'est qu'une sécurité.

- [ ] **Step 2 : Étendre le calcul du compteur `modelscomplete`**

Toujours dans `home.php`, modifier le calcul existant pour appliquer la même règle (mode fourni → `produit`) :

Remplacer le bloc (vers les lignes 132-144) :

```php
$modelstotal++;
$mrecord = $DB->get_record($mtable, ['gestionprojetid' => $gestionprojet->id]);
if ($mrecord && !empty($mrecord->ai_instructions)) {
    $modelscomplete++;
}
```

par :

```php
$modelstotal++;
$mrecord = $DB->get_record($mtable, ['gestionprojetid' => $gestionprojet->id]);
$enableval = isset($gestionprojet->$mfield) ? (int)$gestionprojet->$mfield : 1;
$isprovided = ($mstep === 4 && $enableval === 2);
$iscomplete = $isprovided
    ? ($mrecord && !empty($mrecord->produit))
    : ($mrecord && !empty($mrecord->ai_instructions));
if ($iscomplete) {
    $modelscomplete++;
}
```

- [ ] **Step 3 : Vérifier la syntaxe**

```bash
php -l gestionprojet/pages/home.php
```
Expected : `No syntax errors detected`.

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/pages/home.php
git commit -m "feat(home): expose correction models data to template"
```

---

### Task 9 : Mettre à jour `home.mustache` pour afficher la section "Modèles de correction"

**Files :**
- Modify: `gestionprojet/templates/home.mustache`

- [ ] **Step 1 : Repérer le bouton "Modèles de correction" actuel**

```bash
grep -n "correction_models\|correctionmodels\|page=correctionmodels" gestionprojet/templates/home.mustache
```

- [ ] **Step 2 : Supprimer le bouton et insérer la nouvelle section**

Localiser dans `home.mustache` le lien/bouton pointant vers `view.php?id=...&page=correctionmodels` et le **remplacer** par le bloc suivant (avant la section dashboard, après la section "Documents enseignant") :

```mustache
{{#hascorrectionmodels}}
<section class="gp-section gp-section-models">
    <h3 class="gp-section-title">
        <span class="gp-section-dot gp-dot-models"></span>
        {{#str}} correction_models, gestionprojet {{/str}}
    </h3>
    <div class="gp-models-grid">
        {{#correctionmodels}}
        <a href="{{url}}" class="gp-model-card{{#iscomplete}} is-complete{{/iscomplete}}{{#isprovided}} is-provided{{/isprovided}}">
            <div class="gp-model-icon">{{{icon}}}</div>
            <div class="gp-model-body">
                <div class="gp-model-title">{{title}}</div>
                <div class="gp-model-desc">{{description}}</div>
                <div class="gp-model-status">
                    {{#isprovided}}
                        <span class="gp-badge gp-badge-provided">{{#str}} step4_provided_badge, gestionprojet {{/str}}</span>
                    {{/isprovided}}
                    {{^isprovided}}
                        {{#iscomplete}}
                            <span class="gp-badge gp-badge-complete">{{#str}} model_configured, gestionprojet {{/str}}</span>
                        {{/iscomplete}}
                        {{^iscomplete}}
                            <span class="gp-badge gp-badge-todo">{{#str}} model_to_configure, gestionprojet {{/str}}</span>
                        {{/iscomplete}}
                    {{/isprovided}}
                </div>
            </div>
        </a>
        {{/correctionmodels}}
    </div>
</section>
{{/hascorrectionmodels}}
```

- [ ] **Step 3 : Vérifier la balance Mustache**

```bash
grep -c "{{" gestionprojet/templates/home.mustache
grep -c "}}" gestionprojet/templates/home.mustache
```
Expected : les deux comptes identiques.

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/templates/home.mustache
git commit -m "feat(home): render correction models section directly on home"
```

---

### Task 10 : Ajouter les styles CSS de la section "Modèles de correction"

**Files :**
- Modify: `gestionprojet/styles.css`

- [ ] **Step 1 : Ajouter le bloc CSS en fin de fichier**

À la fin de `gestionprojet/styles.css` (avant les media queries finales si elles existent, sinon en toute fin) :

```css
/* Home — Correction models section */
.path-mod-gestionprojet .gp-section-models {
    margin: 24px 0;
}
.path-mod-gestionprojet .gp-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b7280;
    margin: 0 0 14px;
}
.path-mod-gestionprojet .gp-section-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}
.path-mod-gestionprojet .gp-dot-models {
    background: #d97706;
}
.path-mod-gestionprojet .gp-models-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}
.path-mod-gestionprojet .gp-model-card {
    display: flex;
    gap: 12px;
    padding: 14px;
    background: linear-gradient(180deg, #fffbeb 0%, #fffefa 100%);
    border: 1px solid #fde68a;
    border-radius: 10px;
    text-decoration: none;
    color: inherit;
    transition: transform .12s ease, box-shadow .12s ease;
}
.path-mod-gestionprojet .gp-model-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    text-decoration: none;
}
.path-mod-gestionprojet .gp-model-title {
    font-weight: 700;
    color: #111827;
    font-size: 14px;
}
.path-mod-gestionprojet .gp-model-desc {
    color: #6b7280;
    font-size: 12px;
    margin-top: 2px;
}
.path-mod-gestionprojet .gp-model-status {
    margin-top: 8px;
}
.path-mod-gestionprojet .gp-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 999px;
    letter-spacing: 0.02em;
}
.path-mod-gestionprojet .gp-badge-complete {
    background: #d1fae5;
    color: #065f46;
}
.path-mod-gestionprojet .gp-badge-todo {
    background: #fef3c7;
    color: #92400e;
}
.path-mod-gestionprojet .gp-badge-provided {
    background: #4f46e5;
    color: #fff;
}
```

- [ ] **Step 2 : Vérifier qu'aucune balise `<style>` inline n'a été ajoutée à un PHP**

```bash
grep -rn '<style' gestionprojet --include="*.php"
```
Expected : aucun résultat (zéro CSS inline).

- [ ] **Step 3 : Commit**

```bash
git add gestionprojet/styles.css
git commit -m "style(home): add CSS for correction models section and badges"
```

---

### Task 11 : Ajouter les chaînes de langue pour la section et les badges

**Files :**
- Modify: `gestionprojet/lang/en/gestionprojet.php`
- Modify: `gestionprojet/lang/fr/gestionprojet.php`

- [ ] **Step 1 : Vérifier les chaînes existantes**

```bash
grep -n "correction_models\|model_configured\|model_to_configure" gestionprojet/lang/en/gestionprojet.php
```

`correction_models` existe déjà. `model_configured` et `model_to_configure` doivent être ajoutées.

- [ ] **Step 2 : Ajouter dans `lang/en/gestionprojet.php`**

À un emplacement cohérent avec l'ordre alphabétique (ou en fin de fichier avant la fermeture) :

```php
$string['model_configured'] = 'Configured';
$string['model_to_configure'] = 'To configure';
```

- [ ] **Step 3 : Ajouter dans `lang/fr/gestionprojet.php`**

```php
$string['model_configured'] = 'Configuré';
$string['model_to_configure'] = 'À configurer';
```

- [ ] **Step 4 : Vérifier la syntaxe**

```bash
php -l gestionprojet/lang/en/gestionprojet.php
php -l gestionprojet/lang/fr/gestionprojet.php
```
Expected : `No syntax errors detected` pour chacun.

- [ ] **Step 5 : Commit**

```bash
git add gestionprojet/lang/en/gestionprojet.php gestionprojet/lang/fr/gestionprojet.php
git commit -m "lang: add strings for correction model status badges"
```

---

### Task 12 : Transformer la route `correctionmodels` en redirect dans `view.php`

**Files :**
- Modify: `gestionprojet/view.php`

- [ ] **Step 1 : Repérer la route actuelle**

```bash
sed -n '100,116p' gestionprojet/view.php
```

Repérer le bloc :
```php
case 'correctionmodels':
    require_capability('mod/gestionprojet:configureteacherpages', $context);
    require_once(__DIR__ . '/pages/correction_models.php');
    ...
```

- [ ] **Step 2 : Remplacer le case par un redirect**

Remplacer le bloc `case 'correctionmodels':` par :

```php
case 'correctionmodels':
    // Deprecated route: correction models are now displayed directly on the home page.
    redirect(new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]));
    break;
```

- [ ] **Step 3 : Vérifier la syntaxe**

```bash
php -l gestionprojet/view.php
```
Expected : `No syntax errors detected`.

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/view.php
git commit -m "feat(routing): redirect deprecated correctionmodels route to home"
```

---

### Task 13 : Supprimer les artefacts obsolètes (page + template + méthode renderer)

**Files :**
- Delete: `gestionprojet/pages/correction_models.php`
- Delete: `gestionprojet/templates/correction_models.mustache`
- Modify: `gestionprojet/classes/output/renderer.php`

- [ ] **Step 1 : Vérifier les usages**

```bash
grep -rn "correction_models\.mustache\|render_correction_models\|pages/correction_models" gestionprojet --include="*.php" --include="*.mustache"
```

Attendu : seules occurrences dans les 3 fichiers ciblés (la route a déjà été remplacée par un redirect en Task 12).

- [ ] **Step 2 : Supprimer les deux fichiers**

```bash
git rm gestionprojet/pages/correction_models.php
git rm gestionprojet/templates/correction_models.mustache
```

- [ ] **Step 3 : Retirer `render_correction_models()` du renderer**

Dans `gestionprojet/classes/output/renderer.php`, supprimer la méthode `render_correction_models($data)` (commencement vers la ligne 44). Garder le reste de la classe intact.

- [ ] **Step 4 : Vérifier la syntaxe**

```bash
php -l gestionprojet/classes/output/renderer.php
```
Expected : `No syntax errors detected`.

- [ ] **Step 5 : Re-vérifier qu'aucune référence orpheline ne subsiste**

```bash
grep -rn "render_correction_models\|correction_models\.mustache" gestionprojet --include="*.php" --include="*.mustache"
```
Expected : aucun résultat.

- [ ] **Step 6 : Commit**

```bash
git add gestionprojet/classes/output/renderer.php
git commit -m "refactor: remove obsolete correction_models page, template, and renderer method"
```

---

### Task 14 : Retirer la liaison vers `correctionmodels` dans `step4_teacher.php` à `step8_teacher.php`

**Files :**
- Modify: `gestionprojet/pages/step4_teacher.php`
- Modify: `gestionprojet/pages/step5_teacher.php`
- Modify: `gestionprojet/pages/step6_teacher.php`
- Modify: `gestionprojet/pages/step7_teacher.php`
- Modify: `gestionprojet/pages/step8_teacher.php`

- [ ] **Step 1 : Repérer toutes les occurrences**

```bash
grep -n "page.*correctionmodels\|page=correctionmodels" gestionprojet/pages/step{4,5,6,7,8}_teacher.php
```

- [ ] **Step 2 : Remplacer chaque lien par un retour vers la home**

Pour chaque occurrence d'un lien du type :

```php
new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels'])
```

remplacer par :

```php
new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id])
```

> Et adapter la chaîne du label si elle dit "Retour au hub des modèles" — elle peut devenir simplement "Retour à la home" via `get_string('home', 'gestionprojet')` qui existe déjà.

- [ ] **Step 3 : Vérifier la syntaxe**

```bash
for f in gestionprojet/pages/step{4,5,6,7,8}_teacher.php; do php -l "$f"; done
```
Expected : `No syntax errors detected` pour les 5.

- [ ] **Step 4 : Commit**

```bash
git add gestionprojet/pages/step4_teacher.php gestionprojet/pages/step5_teacher.php gestionprojet/pages/step6_teacher.php gestionprojet/pages/step7_teacher.php gestionprojet/pages/step8_teacher.php
git commit -m "refactor: replace deprecated correctionmodels link with home link"
```

---

### Task 15 : Validation manuelle de la Phase 2

- [ ] **Step 1 : Purger les caches**

```bash
php admin/cli/purge_caches.php
```

- [ ] **Step 2 : Tester la home enseignant**

1. Ouvrir la home enseignant d'une activité
2. Vérifier la présence : (a) section "Documents enseignant" (3 cartes), (b) section "Modèles de correction" (5 cartes), (c) dashboard
3. Vérifier que **plus aucun bouton "Modèles de correction"** n'est présent
4. Cliquer sur une carte de modèle → doit ouvrir directement `step4_teacher.php` (etc.)

- [ ] **Step 3 : Tester le redirect**

1. Ouvrir manuellement `view.php?id=<cmid>&page=correctionmodels`
2. Vérifier que le navigateur est redirigé silencieusement vers la home

- [ ] **Step 4 : Vérifier le compteur du dashboard**

1. Configurer 2 modèles sur 5 (remplir `ai_instructions` pour 2)
2. Vérifier que le dashboard indique "2 / 5 modèles configurés"

---

## Phase 3 — Friction 1 : Mode "CDCF fourni par l'enseignant"

### Task 16 : Remplacer le checkbox `enable_step4` par un select 3-états dans `mod_form.php`

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

### Task 17 : Ajouter les chaînes de langue pour le mode fourni

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

### Task 18 : Afficher l'encart d'information sur `step4_teacher.php` en mode fourni

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

### Task 19 : Adapter la vue élève `step4.php` pour le mode fourni

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

### Task 20 : Mettre à jour la home élève pour le badge "Fourni par l'enseignant" sur step 4

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

### Task 21 : Validation manuelle de la Phase 3

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

### Task 22 : Bump version et upgrade step

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

### Task 23 : Mettre à jour CHANGELOG.md

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

### Task 24 : Vérification finale de conformité Moodle

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

**Créés (1)** :
- `gestionprojet/templates/step_tabs.mustache`

**Modifiés (15)** :
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
