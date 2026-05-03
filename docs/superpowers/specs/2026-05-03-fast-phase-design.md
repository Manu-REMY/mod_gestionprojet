# Phase FAST (étape 9) — Design

**Date** : 2026-05-03
**Auteur** : Emmanuel REMY (via brainstorming Claude Code)
**Statut** : design validé, à planifier
**Cible** : `mod_gestionprojet` v2.3.0
**Inspiration** : `sequence-manager` (`src/components/projets/tools/FastTool.tsx`)

## Contexte

Le plugin `mod_gestionprojet` propose 8 phases pédagogiques. Il manque la phase d'**analyse fonctionnelle FAST** (Function Analysis System Technique), qui traduit les fonctions de service du CDCF en solutions techniques concrètes. Cette phase existe déjà dans l'application `sequence-manager` (Next.js) et doit être portée dans le plugin Moodle.

L'objectif : ajouter une étape 9 "FAST" qui s'intègre au Gantt de pilotage enseignant sur les **3 lignes** (config / modèle correction / production élève) à l'image des étapes 4 (CDCF) et 2 (Besoin).

## Décisions validées

| # | Décision | Raison |
|---|----------|--------|
| 1 | Étape 9 (pas de renumérotation) | Évite migration/refonte des étapes 5-8. Le Gantt gère déjà l'ordre indépendamment du `stepnum`. |
| 2 | Position pédagogique : entre étape 4 (CDCF) et 5 (Essai) | Suit la chaîne besoin → CDCF → FAST → essai. |
| 3 | Pattern CDCF (ligne 1 = mode "fourni" via `step9_provided`) | Pas de version "enseignant finale" comme pour Besoin. Le FAST est par nature une analyse à mener par l'élève. |
| 4 | Pré-remplissage CDCF uniquement côté enseignant | L'enseignant choisit s'il fournit ou non le résultat à l'élève via `step9_provided`. |
| 5 | Évaluation IA standard (comme étapes 4-8) | Cohérence pédagogique. |
| 6 | Rendu diagramme : AMD + SVG dynamique côté client | Identique sequence-manager. Re-render live pendant la saisie. |
| 7 | Décomposition en 2 specs : (1) FAST, (2) assistant IA transverse | Permet livraison FAST en autonomie. L'assistant ciblera ensuite 6 phases stables (4-8 + 9). |

## Architecture

### Modèle de données

**Modifications de `gestionprojet`** :

| Champ | Type | Défaut | Sémantique |
|-------|------|--------|------------|
| `enable_step9` | TINYINT(1) NOTNULL | 1 | 0 = phase désactivée, 1 = activée |
| `step9_provided` | TINYINT(1) NOTNULL | 0 | 0 = élève vierge, 1 = élève reçoit le diagramme du teacher |

**Nouvelle table `gestionprojet_fast_teacher`** (modèle correction + contenu fourni) :

| Champ | Type | Note |
|-------|------|------|
| id | BIGINT PK auto-inc | |
| gestionprojetid | BIGINT NOTNULL FK | index |
| data_json | LONGTEXT | structure FAST sérialisée |
| ai_instructions | LONGTEXT | consignes pour le correcteur IA |
| timecreated | BIGINT NOTNULL | |
| timemodified | BIGINT NOTNULL | |

Index : `(gestionprojetid)` unique.

**Nouvelle table `gestionprojet_fast`** (production élève) :

| Champ | Type | Note |
|-------|------|------|
| id | BIGINT PK auto-inc | |
| gestionprojetid | BIGINT NOTNULL FK | |
| userid | BIGINT NOTNULL DEFAULT 0 | 0 si mode groupe |
| groupid | BIGINT NOTNULL DEFAULT 0 | 0 si mode individuel |
| data_json | LONGTEXT | diagramme FAST de l'élève |
| status | TINYINT(1) NOTNULL DEFAULT 0 | 0=brouillon, 1=soumis |
| grade | DECIMAL(5,2) NULL | note manuelle |
| timecreated | BIGINT NOTNULL | |
| timemodified | BIGINT NOTNULL | |
| timesubmitted | BIGINT NULL | |

Index : `(gestionprojetid, userid, groupid)` unique.

**Schéma `data_json`** (commun teacher/élève, port direct depuis sequence-manager) :

```json
{
  "fonctionsPrincipales": [
    {"id": 1, "description": "Permettre à l'utilisateur de..."}
  ],
  "fonctions": [
    {
      "id": 1,
      "description": "FT description",
      "originCdcf": "FS",
      "originIndex": 1,
      "originInteractor": "Utilisateur",
      "solution": "Solution technique en texte libre",
      "sousFonctions": [
        {"id": 1, "description": "Sous-fonction", "solution": "ST associée"}
      ]
    }
  ],
  "populatedFromCdcf": true
}
```

`originCdcf` est optionnel et trace la provenance d'une FT issue du CDCF (`'FP' | 'FS' | 'FC'`). `solution` au niveau FT est ignoré si `sousFonctions` est non vide (la solution descend au niveau sous-FT).

### Flux UI

**`pages/step9_teacher.php`** (modèle correction enseignant — accessible via Gantt ligne 1 ET ligne 2) :

- Header contextuel adapté au mode :
  - `step9_provided = 0` → "Définissez le modèle de correction attendu et les consignes pour le correcteur IA."
  - `step9_provided = 1` → "Ce contenu sera fourni à l'élève comme support de départ. Définissez aussi les consignes IA d'évaluation."
- Bouton **"Pré-remplir depuis le CDCF"** (visible si `gestionprojet_cdcf_teacher.data_json` contient des FS).
- Bouton **"Compléter depuis le CDCF"** (ajoute uniquement les FS manquantes par diff `originIndex`).
- Formulaire éditable des FT/sous-FT/ST.
- Diagramme FAST rendu en live sous le formulaire (AMD module).
- Champ `ai_instructions` (textarea) en bas de page.
- Autosave 30 s sur `data_json` et `ai_instructions`.

**`pages/step9.php`** (production élève) :

- À la 1re ouverture :
  - Si `step9_provided = 1` : `gestionprojet_get_or_create_submission` copie le `data_json` du teacher dans la submission élève.
  - Sinon : submission créée avec `data_json` vide. Overlay "Commencer" présenté à l'élève (un seul choix : "Commencer vierge", car le pré-remplissage CDCF n'est pas exposé à l'élève).
- Formulaire identique à la version teacher (mêmes composants AMD), sans champ `ai_instructions`.
- Diagramme live.
- Bouton "Soumettre" (passe `status=1`, `timesubmitted=time()`).
- Autosave 30 s.

### Intégration au Gantt (`pages/home.php`)

Modification de `$ganttcolumndefs` — colonne FAST insérée entre étape 4 et étape 5 :

```php
$ganttcolumndefs = [
    ['stepnum' => 1, 'mergedwith' => null],
    ['stepnum' => 3, 'mergedwith' => null],
    ['stepnum' => 2, 'mergedwith' => 7],
    ['stepnum' => 4, 'mergedwith' => null],
    ['stepnum' => 9, 'mergedwith' => null],   // ← nouveau
    ['stepnum' => 5, 'mergedwith' => null],
    ['stepnum' => 8, 'mergedwith' => null],
    ['stepnum' => 6, 'mergedwith' => null],
];
```

Étape 9 ajoutée à `$studentsteps` (rows 2 et 3) et au cas particulier "row 1 = mode provided" (clone du traitement étape 4).

**Critères de complétion (toutes les 3 cellules de la colonne 9)** :

- Ligne 1 (config provided) — `isenabled` = `step9_provided === 1` ; `iscomplete` = `data_json` du teacher contient au moins une FT (`count(fonctions) > 0`).
- Ligne 2 (modèle correction) — `iscomplete` = `!empty($rec->ai_instructions)`. Aligné sur le pattern existant pour étape 4 en mode normal (`home.php:96` : `return !empty($rec->ai_instructions);`).
- Ligne 3 (élève) — `iscomplete` = `data_json` de l'élève contient au moins une FT.

**Note importante** : `enable_step9` reste un flag 0/1 simple (pas de mode 2 comme étape 4). La distinction "fourni à l'élève" se fait uniquement via le flag séparé `step9_provided`.

### Côté élève (vue non-enseignant du home)

Ajout dans `$studentstepsraw`, entre clés `4` et `5` :

```php
9 => [
    'data' => $fast,
    'complete' => $fast && !empty($fast->data_json) && /* fonctions non vide */,
],
```

### Routing

`view.php` : ajout d'un case `step=9`, branchant sur `pages/step9.php` (mode élève) ou `pages/step9_teacher.php` (mode teacher) selon le param `mode`.

### Module JS — Diagramme

**`amd/src/fast_diagram.js`** (port direct du `DiagrammeFast` de sequence-manager) :

```javascript
define(['jquery'], function($) {
    return {
        init: function(containerId, dataInputId) { ... },
        render: function(containerId, data) { ... }
    };
});
```

Constantes layout identiques à sequence-manager :
- `FP_WIDTH=160, FP_HEIGHT=38`
- `FT_WIDTH=200, FT_HEIGHT=30`
- `SOUS_FT_WIDTH=180, SOUS_FT_HEIGHT=28`
- `ST_WIDTH=180, ST_HEIGHT=28`
- `H_GAP=60, V_GAP=5`

Algorithme de layout : colonnes alignées (FP → FT → sous-FT → ST), centrage vertical des FT par groupe de sous-fonctions, lignes de connexion en angle droit.

### Module JS — Éditeur

**`amd/src/fast_editor.js`** :

- Maintient l'état JS (objet `data` conforme au schéma).
- Sérialise dans un input hidden (`fast-data-{cmid}`) à chaque modification.
- Émet l'évent `fast:update` → `fast_diagram` re-render.
- CRUD FT/sous-FT/ST.
- Bouton "Scinder" (transforme une FT en FT avec 2 sous-FT).
- Bouton "Pré-remplir depuis CDCF" (teacher uniquement) : appelle `ajax/fast_populate_cdcf.php`.
- Bouton "Compléter depuis CDCF" (teacher uniquement) : ajoute uniquement les FS manquantes.

### Endpoint AJAX

**`ajax/fast_populate_cdcf.php`** :

```php
require_login(...);
require_sesskey();
require_capability('mod/gestionprojet:configureteacherpages', $context);
$cdcfteacher = $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gpid]);
// Extrait les FS du CDCF teacher.
// La structure exacte du CDCF teacher (champs JSON / colonnes) sera confirmée en phase plan
// (champs candidats : `interacteurs_data`, `produit`, ou un champ dédié aux fonctions de service).
echo json_encode(['fonctionsService' => [...], 'success' => true]);
```

### Autosave

`ajax/autosave.php` — ajout au switch :

```php
case 9:
    $allowed = ['data_json', 'ai_instructions']; // ai_instructions teacher uniquement
    $table = $isteachermode ? 'gestionprojet_fast_teacher' : 'gestionprojet_fast';
    // Logique standard de validation + update_record
    break;
```

### Évaluation IA

**Sérialisation pour le LLM** (`lib.php` — fonction `gestionprojet_fast_to_text($datajson)`) :

```
Fonction principale : <description FP>

FT1 — <description>
  Solution : <ST>
FT2 — <description>
  ├─ FT2.1 <description sous-FT>
  │   Solution : <ST>
  └─ FT2.2 <description sous-FT>
      Solution : <ST>
```

**Intégration au moteur d'évaluation existant** (`classes/ai/evaluator.php` ou équivalent) :

```php
case 9:
    $student = $DB->get_record('gestionprojet_fast', [...]);
    $teacher = $DB->get_record('gestionprojet_fast_teacher', [...]);
    $studenttext = gestionprojet_fast_to_text($student->data_json);
    $teachertext = gestionprojet_fast_to_text($teacher->data_json);
    $aiinstructions = $teacher->ai_instructions;
    $prompt = build_fast_prompt($aiinstructions, $teachertext, $studenttext);
    break;
```

**Stockage** : `gestionprojet_ai_evaluations` et `gestionprojet_ai_summaries` existantes — colonne `step` porte la valeur 9. Pas de nouvelle table IA.

### Carnet de notes Moodle

`lib.php` :

- `gestionprojet_grade_item_update()` : ajouter un grade-item pour step 9.
- `gestionprojet_update_grades()` : inclure step 9 dans la boucle.
- `gestionprojet_get_user_grades()` : idem.

### Capacités

Aucune nouvelle capacité — réutilisation :
- `mod/gestionprojet:configureteacherpages` → `step9_teacher.php`
- `mod/gestionprojet:submit` → `step9.php`
- `mod/gestionprojet:grade` → `grading.php`

### Privacy provider

`classes/privacy/provider.php` :
- Déclarer `gestionprojet_fast` dans les metadata (table avec `userid`).
- Implémenter `export_user_data` et `delete_data_for_user` pour `gestionprojet_fast`.
- `gestionprojet_fast_teacher` ne contient pas de `userid` → pas concernée.

### lib.php — autres mises à jour

- `gestionprojet_get_student_steps()` : `[7, 4, 9, 5, 8, 6]`.
- `gestionprojet_delete_instance($id)` : ajouter `$DB->delete_records('gestionprojet_fast_teacher', ['gestionprojetid' => $id])` et `$DB->delete_records('gestionprojet_fast', ['gestionprojetid' => $id])`.
- `gestionprojet_get_or_create_submission(...)` : prendre en charge `step='fast'`. En mode `step9_provided=1`, copier `data_json` du teacher dans la submission élève à la création.

## Migration & version

**Version** : `2026050400` (release `2.3.0`).

**Upgrade step `db/upgrade.php`** :

```php
if ($oldversion < 2026050400) {
    // 1. Ajouter enable_step9 et step9_provided à la table principale
    $table = new xmldb_table('gestionprojet');
    $field = new xmldb_field('enable_step9', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }
    $field = new xmldb_field('step9_provided', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // 2. Créer gestionprojet_fast_teacher
    $table = new xmldb_table('gestionprojet_fast_teacher');
    if (!$dbman->table_exists($table)) {
        // (Construction XMLDB du table : id, gestionprojetid, data_json LONGTEXT,
        // ai_instructions LONGTEXT, timecreated, timemodified, index gestionprojetid)
        $dbman->create_table($table);
    }

    // 3. Créer gestionprojet_fast
    $table = new xmldb_table('gestionprojet_fast');
    if (!$dbman->table_exists($table)) {
        // (Construction XMLDB du table : id, gestionprojetid, userid, groupid,
        // data_json LONGTEXT, status, grade DECIMAL(5,2), timecreated, timemodified,
        // timesubmitted, index gestionprojetid+userid+groupid)
        $dbman->create_table($table);
    }

    upgrade_mod_savepoint(true, 2026050400, 'gestionprojet');
}
```

`db/install.xml` mis à jour en parallèle pour les installations fraîches.

## Conformité Moodle (checklist obligatoire)

| Règle | Application FAST |
|-------|------------------|
| 1. GPL header complet | Tous les nouveaux PHP : 2 paragraphes complets |
| 2. No inline CSS | Styles dans `styles.css`, namespace `.path-mod-gestionprojet-fast` |
| 3. No inline JS | `$PAGE->requires->js_call_amd('mod_gestionprojet/fast_diagram', 'init', [...])` |
| 4. English-only comments | PHPDoc + commentaires en anglais |
| 5. No debug code | Pas d'`error_log`, `print_r`, `file_put_contents` de debug |
| 6. No superglobals | `required_param` / `optional_param` partout |
| 7. Sécurité entrée | `require_login` + `require_capability` (+ `require_sesskey` AJAX) sur les 3 nouveaux endpoints |
| 8. delete_instance | 2 `$DB->delete_records` ajoutés (fast + fast_teacher) |
| 9. Strings via lang | Toutes les chaînes UI dans `lang/en/` ET `lang/fr/` |
| 10. Version bump | `2026050400`, release `2.3.0`, upgrade step présente |
| 11. Privacy provider | `gestionprojet_fast` ajoutée aux metadata + export/delete user data |

## Fichiers impactés

### Créés

- `pages/step9.php`
- `pages/step9_teacher.php`
- `templates/step9_form.mustache`
- `amd/src/fast_diagram.js`
- `amd/src/fast_editor.js`
- `ajax/fast_populate_cdcf.php`
- `pix/icon_step9.svg` (Lucide GitFork)

### Modifiés

- `db/install.xml`
- `db/upgrade.php`
- `lib.php` (`get_student_steps`, `delete_instance`, `grade_item_update`, `update_grades`, `get_user_grades`, helpers `fast_to_text`, `get_or_create_submission`)
- `pages/home.php` (Gantt + studentstepsraw)
- `view.php`
- `mod_form.php` (checkbox `enable_step9`)
- `ajax/autosave.php` (whitelist step 9)
- `classes/ai/evaluator.php` (case 9)
- `classes/privacy/provider.php` (table `gestionprojet_fast`)
- `classes/output/icon.php` (référence icône step 9 si nécessaire)
- `grading.php`
- `report.php`
- `export_pdf.php`
- `styles.css`
- `lang/en/gestionprojet.php`
- `lang/fr/gestionprojet.php`
- `version.php`

## Critères de succès

1. L'enseignant active la phase via mod_form (checkbox `enable_step9`).
2. L'enseignant peut décider de fournir le diagramme à l'élève via la cellule ligne 1 du Gantt (toggle `step9_provided`).
3. L'enseignant rédige son modèle de correction et ses consignes IA ; il peut pré-remplir depuis le CDCF teacher.
4. L'élève voit la phase entre CDCF et Essai dans son menu d'accueil.
5. L'élève édite son diagramme FAST, le voit se redessiner en live à chaque modification.
6. L'autosave fonctionne (30 s).
7. La soumission élève est gradable manuellement (0-20).
8. L'évaluation IA produit une note et un retour, stockés dans les tables IA existantes.
9. La note remonte dans le carnet de notes Moodle.
10. La suppression de l'instance purge les 2 nouvelles tables.
11. La désinstallation du plugin est propre (privacy provider).

## Hors-scope (Spec 2)

- Bouton "Suggérer des consignes IA" sur les 6 modèles de correction enseignant (étapes 4-9). Spec dédiée, à rédiger après la livraison de la phase FAST.

## Tests manuels

(Pas de framework PHPUnit dans le plugin — tests manuels.)

1. Activation/désactivation phase via mod_form
2. Mode `step9_provided=0` : élève démarre vierge
3. Mode `step9_provided=1` : élève reçoit le contenu du teacher
4. Pré-remplissage CDCF teacher (avec et sans CDCF teacher déjà rempli)
5. Édition live : ajout/suppression/scission FT, diagramme se redessine
6. Autosave (30 s)
7. Soumission élève
8. Évaluation IA d'une copie
9. Note dans le gradebook Moodle
10. Suppression instance : tables purgées
11. Désinstallation plugin propre
