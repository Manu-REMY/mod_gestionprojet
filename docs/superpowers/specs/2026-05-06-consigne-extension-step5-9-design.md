# Extension du pattern consigne (intro_text + Reset) à step 5 (essai) et step 9 (FAST)

**Date** : 2026-05-06
**Statut** : Design approuvé, en attente de plan d'implémentation
**Auteur** : Emmanuel REMY (avec Claude Code)
**Périmètre** : Steps 5 (essai) et 9 (FAST). Step 7 hors scope (création complète du mode `provided` à venir dans une release ultérieure).
**Version cible** : 2.10.0

---

## 1. Contexte

La v2.9.0 a livré sur step 4 (CDCF) la refonte « consigne » : champ `intro_text` (Atto, lecture seule pour l'élève), bouton « Réinitialiser le formulaire », injection de l'intro dans le prompt IA, garde-fou « copie identique = 0/20 ». L'architecture livrée est conçue pour s'étendre :

- `\mod_gestionprojet\reset_helper::STEP_MAP` accueille de nouvelles entrées par step.
- L'endpoint `ajax/reset_to_provided.php` valide actuellement `step === 4` ; généralisable.
- `ai_prompt_builder` accepte déjà `?object $providedrec` et `bool $nomodifications` génériques.
- `ai_evaluator::process_evaluation` n'utilise le `providedrec` que pour `step === 4` — à généraliser.
- Le webservice autosave (`mode='provided'`) gère déjà steps 4/5/9 via `$providedtables`.

Les steps 5 (essai) et 9 (FAST) ont déjà leur mode `provided` (tables `gestionprojet_essai_provided` et `gestionprojet_fast_provided`) depuis v2.8.0 et v2.6.x respectivement. Il reste à appliquer le même pattern UI + IA + Reset.

## 2. Objectifs

1. Doter step 5 et step 9 d'un champ `intro_text` (HTML Atto) côté enseignant, lu en temps réel par l'élève.
2. Doter step 5 et step 9 d'un bouton « Réinitialiser le formulaire » qui réécrit le record élève depuis la consigne enseignant la plus récente.
3. Injecter `intro_text` dans le prompt IA et appliquer le garde-fou « copie identique = 0/20 » sur step 5 et step 9.
4. Réutiliser au maximum le code v2.9.0 ; factoriser ce qui ne l'est pas encore (module JS Reset).

## 3. Non-objectifs

- Step 7 (besoin élève) : pas dans cette release. Nécessite la création préalable du mode `provided` (table, page, flag, toggle Gantt).
- Pas de migration des records élèves existants.
- Pas de refonte du seeding initial (`gestionprojet_get_or_create_submission`) — il reste comme aujourd'hui.
- Pas de wording spécifique step 9 sur les strings (« formulaire » s'applique métaphoriquement au diagramme).

## 4. Modèle de données

Deux colonnes ajoutées, pas de nouvelle table :

| Table | Colonne | Type | Nullable | Default |
|---|---|---|---|---|
| `gestionprojet_essai_provided` | `intro_text` | `XMLDB_TYPE_TEXT` | oui | `null` |
| `gestionprojet_fast_provided` | `intro_text` | `XMLDB_TYPE_TEXT` | oui | `null` |

Format de stockage : HTML brut (sortie Atto). Assainissement à la lecture via `format_text(..., FORMAT_HTML, ['context' => $context])`. Convention Moodle.

### Migration `db/upgrade.php`

```php
$newversion = 2026050900; // À ajuster au bump réel.
if ($oldversion < $newversion) {
    foreach (['gestionprojet_essai_provided', 'gestionprojet_fast_provided'] as $tablename) {
        $table = new xmldb_table($tablename);
        $field = new xmldb_field('intro_text', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }
    upgrade_mod_savepoint(true, $newversion, 'gestionprojet');
}
```

`db/install.xml` : ajout de la même colonne sur les deux tables (pour les nouvelles instances).

## 5. Extension de `reset_helper::STEP_MAP`

Dans `classes/reset_helper.php` :

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

**Endpoint `ajax/reset_to_provided.php`** : la validation actuelle (`$step === 4`) devient `in_array($step, [4, 5, 9], true)`. Le reste (sesskey, capability, lock check, audit log, JSON response) reste identique.

**Reset semantics** : reset *total* — tous les champs listés dans `STEP_MAP[step]['fields']` sont écrasés par les valeurs du record `provided`. Cohérent avec le seeding initial. L'élève qui clique Reset retombe à l'état exact de la première ouverture.

**Note `precautions` (step 5)** : la copie est *raw text → raw text*. Aucune conversion JSON dans le helper. La page élève (`step5.php`) gère déjà la lecture (json_decode + fallback `preg_split` sur `\n`, clamp 6 entrées) — pas de modif.

## 6. UI enseignant

### 6.1 `pages/step5_provided.php`

Insertion d'un bloc Atto **avant** la première `<div class="model-form-section">` (« Informations générales ») :

```php
<div class="model-form-section gp-intro-section">
    <h3><?php echo icon::render('file-text', 'sm', 'blue'); ?> <?php echo get_string('intro_text_label', 'gestionprojet'); ?></h3>
    <p class="text-muted small"><?php echo get_string('intro_text_help', 'gestionprojet'); ?></p>
    <textarea name="intro_text" id="intro_text" rows="8" class="form-control gp-intro-textarea"><?php echo s($model->intro_text ?? ''); ?></textarea>
</div>
<?php
$editor = editors_get_preferred_editor(FORMAT_HTML);
$editor->set_text($model->intro_text ?? '');
$editor->use_editor('intro_text', ['context' => $context, 'autosave' => false]);
?>
```

Mode readonly : le wrapper `<div class="gp-fast-readonly">` existant rend le bloc Atto non éditable côté élève (CSS `pointer-events: none`).

### 6.2 `pages/step9_provided.php`

Insertion d'un bloc Atto identique, **immédiatement après `$OUTPUT->heading(...)`**, avant le bandeau `alert-info` (« step9_desc_title / step9_desc_text ») actuel. Ordre vertical : heading → bloc Atto intro_text (éditeur) → alert-info description → template step9_form.

### 6.3 Autosave de `intro_text` — module JS générique

Création d'un nouveau module `amd/src/intro_text_autosave.js` (~30 lignes) :

- Initialisé sur `#intro_text` s'il existe.
- Listener `change` (Atto → textarea sync) + autosave debounced.
- POST vers le webservice `mod_gestionprojet_autosave` avec `mode=provided`, `step`, `data={intro_text: ...}`.
- Réutilisable pour tous les steps qui ont un intro_text (4, 5, 9 ; futur 7).

**Conséquence sur step 4** : refacto pour utiliser `intro_text_autosave` à la place de la logique inline actuelle dans `cdcf_bootstrap.js` (la partie autosave, pas le reste). Découple la sauvegarde de l'intro de la logique CDCF.

`amd/src/essai_provided.js` reste inchangé. **Deux fichiers à modifier** pour la whitelist autosave :
- `ajax/autosave.php` ligne 73 (AJAX legacy) : ajouter `'intro_text'` aux `fields` des entrées 5 et 9.
- `classes/external/autosave.php` ligne 104 (webservice) : idem.

## 7. UI élève

### 7.1 Affichage du bloc `intro_text` lecture seule

**Step 5 (`pages/step5.php`)** : insertion en haut de `<div class="step-container gp-student">`, avant `$OUTPUT->heading(get_string('step5_page_title'))`. Encadré `alert-info` permanent affiché si `essai_provided.intro_text` non vide :

```php
$provided = $DB->get_record('gestionprojet_essai_provided', ['gestionprojetid' => $gestionprojet->id]);
if ($provided && !empty(trim(strip_tags($provided->intro_text ?? '')))) {
    echo html_writer::start_div('alert alert-info gp-consigne-intro');
    echo html_writer::tag('h4', get_string('intro_section_title', 'gestionprojet'));
    echo format_text($provided->intro_text, FORMAT_HTML, ['context' => $context]);
    echo html_writer::end_div();
}
```

**Step 9 (`pages/step9.php`)** : insertion entre la `description` et le `<div class="gp-student">`, avant le rendu du template `step9_form`. Même pattern, lecture depuis `fast_provided.intro_text`.

Lecture en temps réel : pas de copie dans le record élève. Modification enseignant → propagation immédiate au reload élève.

### 7.2 Bouton « Réinitialiser le formulaire »

**Step 5** : ajout dans `<div class="export-section">` existante (ligne 308 de `step5.php`), à côté de Submit. Visible si `step5_provided=1` ET un record `essai_provided` non-vide existe. Désactivé (tooltip) si `submission.status === 1`.

```php
<?php if ((int)$gestionprojet->step5_provided === 1):
    $hasprovided = $DB->record_exists_select(
        'gestionprojet_essai_provided',
        'gestionprojetid = :id AND (
            (objectif IS NOT NULL AND objectif <> \'\') OR
            (etapes_protocole IS NOT NULL AND etapes_protocole <> \'\')
        )',
        ['id' => $gestionprojet->id]
    );
    if ($hasprovided):
        // Render Reset button (id="resetButton", btn-warning, disabled-with-tooltip pattern from step 4).
    endif;
endif;
?>
```

**Step 9** : pas de section Submit côté élève actuellement. Ajout d'une section dédiée `<div class="export-section gp-fast-actions">` après le rendu de `step9_form` :

```php
echo html_writer::start_div('export-section gp-fast-actions');
if ((int)$gestionprojet->step9_provided === 1) {
    $hasprovided = $DB->record_exists_select(
        'gestionprojet_fast_provided',
        'gestionprojetid = :id AND data_json IS NOT NULL AND data_json <> \'\' AND data_json <> \'{}\'',
        ['id' => $gestionprojet->id]
    );
    if ($hasprovided) {
        // Render Reset button.
    }
}
echo html_writer::end_div();
```

### 7.3 Module JS `reset_button` factorisé

Aujourd'hui le listener Reset est dans `cdcf_bootstrap.js`. Pour ne pas dupliquer la logique modal+fetch dans 3 modules, on **extrait** dans un nouveau module `amd/src/reset_button.js` :

- Module générique : initialise sur `#resetButton` s'il existe.
- Paramètres : `cmid`, `step`, `groupid`, `sesskey`.
- Logique : `core/modal_factory` (Bootstrap modal) → POST `ajax/reset_to_provided.php` → toast + `window.location.reload()`.
- Chargement : `$PAGE->requires->js_call_amd('mod_gestionprojet/reset_button', 'init', [$params])` dans `step4.php`, `step5.php`, `step9.php`.

**Refacto step 4** : retrait de la logique Reset de `cdcf_bootstrap.js`, appel du nouveau module à la place. Test de non-régression preprod requis.

## 8. Intégration IA — `ai_evaluator.php`

Généralisation du bloc `if ((int)$evaluation->step === 4)` (lignes 193-211) :

```php
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
        $providedrec_for_prompt = $providedrec;
        $nomodifications = self::detect_no_modifications($cfg, $submission, $providedrec);
    }
}
```

### Comparators (méthode privée statique `detect_no_modifications`)

| Comparator | Logique | Step |
|---|---|---|
| `json_normalized` | `json_decode` les deux → `json_encode` → `===` (élimine ordre/whitespace) | 4 |
| `fields_strict` | Pour chaque champ : `(string)$submission->$f === (string)$providedrec->$f`. AND-ing tous. | 5 |
| `string_strict` | `(string)$submission->$f === (string)$providedrec->$f` | 9 |

**Garde-fou anti-faux-positif** : si tous les champs `provided` comparés sont vides (`trim` vide), `nomodifications = false` (rien à comparer). Couvre le cas où un enseignant active `step5_provided=1` sans rien remplir.

### `ai_prompt_builder` — pas de modif

Le builder accepte déjà `?object $providedrec` et `bool $nomodifications`. Les sections « Contexte fourni par l'enseignant » et « Alerte copie identique » sont déjà génériques.

## 9. Backup / Restore

### `backup/moodle2/backup_gestionprojet_stepslib.php`

Ajouter `intro_text` à la liste des champs des deux nested elements existants :

```php
$essaiprovided = new backup_nested_element('essai_provided', ['id'], [
    /* …existing fields…, */ 'intro_text', 'timecreated', 'timemodified',
]);

$fastprovided = new backup_nested_element('fast_provided', ['id'], [
    /* …existing fields…, */ 'intro_text', 'timecreated', 'timemodified',
]);
```

### `restore_gestionprojet_stepslib.php`

Aucun changement — `(array)$data` + `$DB->insert_record(...)` reportent automatiquement le nouveau champ.

## 10. delete_instance + strings + CSS

- **`gestionprojet_delete_instance` (lib.php)** : aucun changement, les deux tables sont déjà nettoyées.
- **Strings** : aucun nouveau string. Les 11 strings v2.9.0 (génériques `intro_text_label`, `reset_*`, etc.) couvrent step 5 et step 9.
- **CSS** : aucune addition. Les classes `.gp-consigne-intro` et `.gp-intro-section` v2.9.0 s'appliquent automatiquement.

## 11. Sécurité

| Surface | Protection |
|---|---|
| Stockage HTML brut | Aucun assainissement à l'écriture (convention Moodle). |
| Affichage côté élève | `format_text(..., FORMAT_HTML, ['context' => $context])` → assainissement Moodle natif. |
| Affichage côté enseignant (Atto) | Atto rend dans son sandbox iframe. |
| Envoi à l'IA | `strip_tags()` + `html_entity_decode()` (déjà fait dans le builder). |
| CSRF reset | `require_sesskey()` sur l'endpoint. |
| Capability reset | `require_capability('mod/gestionprojet:submit')` côté élève. |
| Lock | Garde-fou serveur `status === 1` dans `reset_helper`. |
| Step whitelist | `in_array($step, [4, 5, 9], true)` côté endpoint. |

## 12. Versioning

- `version.php` : `$plugin->version = 2026050900` (ou date du jour de release au format YYYYMMDDXX).
- `$plugin->release = '2.10.0'` (minor — nouvelle feature, schema change).

## 13. Plan de tests

### 13.1 Step 5 — preprod

1. Enseignant remplit `intro_text` (HTML riche) + tous les textareas du protocole. Recharger → persistance OK.
2. Élève jamais ouvert → encadré bleu intro + formulaire pré-rempli (seeding initial v2.8.0).
3. Élève avec draft → encadré bleu + ancien formulaire (pas de re-seeding).
4. Élève clique Reset → modal → confirme → 12 champs écrasés. Toast + reload OK.
5. Élève soumet → bouton Reset grisé, tooltip visible.
6. Enseignant fait revert → bouton Reset réactivé.
7. Enseignant modifie `intro_text` → reload élève → nouvelle version visible.
8. Évaluation IA : intro dans system prompt + alerte copie identique si soumission inchangée.
9. `precautions` : enseignant écrit 3 lignes texte → élève reset → 3 cellules pré-remplies + 3 vides. Saisie élève → autosave → recharger → JSON 6-cell.
10. Backup → restore → `essai_provided.intro_text` préservé.

### 13.2 Step 9 — preprod

1. Enseignant remplit `intro_text` + dessine FAST. Persistance OK.
2. Élève jamais ouvert → encadré bleu + diagramme pré-rempli.
3. Élève déplace nœud → autosave → reload → modif conservée.
4. Élève Reset → modal → confirme → diagramme remplacé. Toast + reload OK.
5. Évaluation IA : alerte copie identique si soumission sans toucher → IA renvoie 0/20.
6. Modification enseignant intro après éval IA → reload élève montre nouvelle intro, éval passée figée.
7. Backup → restore → `fast_provided.intro_text` préservé.

### 13.3 Bypass UI (sécurité)

- Élève soumis tente `POST /ajax/reset_to_provided.php` step=5 ou step=9 → 403.
- Utilisateur sans `submit` → 403.
- `step ∈ {1,2,3,6,7,8}` → 400 (`unsupported_step`).

### 13.4 PHPUnit

- `tests/reset_helper_test.php` : nominal step 5/9, locked step 5/9, missing provided step 5/9.
- `tests/ai_prompt_builder_test.php` : intro injection step 5/9, identical guard step 5/9.
- `tests/ai_evaluator_test.php` (si existant) : end-to-end avec mocks IA pour 5 et 9.

## 14. Workflow Git & déploiement

### Commits atomiques (branche `feat/consigne-extension-step5-9`)

1. `feat(db): add intro_text to essai_provided + fast_provided`
2. `feat(reset): extend STEP_MAP to steps 5 and 9`
3. `feat(step5_provided): add intro_text editor`
4. `feat(step9_provided): add intro_text editor`
5. `feat(step5): add reset button + intro display`
6. `feat(step9): add reset button + intro display`
7. `feat(amd): extract reset_button module + factor cdcf_bootstrap`
8. `feat(amd): add intro_text_autosave generic module`
9. `feat(ai): generalize intro injection + identical-copy guard to steps 5 and 9`
10. `feat(backup): include intro_text in essai_provided + fast_provided`
11. `test(reset+ai): cover steps 5 and 9 in unit tests`
12. `feat(version): bump to 2.10.0`

### Déploiement

1. Merge → push `main` sur Forge EDU.
2. SCP preprod (cf. `feedback_deploy_preprod_at_dev_end`) + purge caches + upgrade DB.
3. Validation manuelle complète sections 13.1 + 13.2 + 13.3.
4. Build ZIP : `zip -r gestionprojet.zip gestionprojet/ -x "*.git*" "*.claude*" "*lessons.md"`.
5. Upload via Moodle Admin (prod ent-occitanie.com) → validation Notifications.
6. Smoke test prod sur 1 instance step 5 et step 9 activés.

### Documentation

- `CHANGELOG.md` : entrée `## [2.10.0]` (Added : intro+reset step 5 et 9 ; Changed : refacto reset_button module).
- `RELEASE_NOTES_v2.10.0.md` : nouveau fichier (contenu utilisateur final).
- Auto-mémoire : marquer `feature_consigne_pattern_extension.md` comme partiellement résolu (5 et 9 livrés ; 7 reste à créer).

## 15. Risques résiduels & mitigations

| Risque | Mitigation |
|---|---|
| `essai_provided.js` casse après ajout du module générique `intro_text_autosave` | Test 13.1.1 ; les deux modules écoutent des sélecteurs disjoints. |
| `fast_editor.js` re-render le canvas pendant que l'utilisateur tape dans `#intro_text` | Test 13.2.1 ; nœuds DOM différents (canvas vs textarea hors canvas). |
| Diff stricte sur `data_json` JSON FAST instable selon ordre des clés | Test 13.2.5 ; si problème, fallback `json_normalized` (déjà disponible via le mapping). |
| Refacto Reset depuis `cdcf_bootstrap.js` casse step 4 v2.9.0 | Test de non-régression preprod sur step 4 inclus dans 13.1 (ajouter scénario). |
| `precautions` step 5 stocké en texte côté provided vs JSON 6-cell côté élève | Logique fallback existante dans `step5.php` (ligne 145) ; testée 13.1.9. |

## 16. Touchpoints code (récap)

| Fichier | Modification |
|---|---|
| `db/install.xml` | + `intro_text` sur `essai_provided` + `fast_provided` |
| `db/upgrade.php` | + étape add_field × 2 |
| `classes/reset_helper.php` | + entrées 5 et 9 dans `STEP_MAP` |
| `classes/ai_evaluator.php` | généralisation bloc step 4 → mapping multi-step + méthode `detect_no_modifications` |
| `pages/step5_provided.php` | + bloc Atto intro_text |
| `pages/step9_provided.php` | + bloc Atto intro_text |
| `pages/step5.php` | + bloc lecture seule intro + bouton Reset |
| `pages/step9.php` | + bloc lecture seule intro + section Reset dédiée |
| `ajax/reset_to_provided.php` | extension validation `in_array($step, [4,5,9])` |
| `ajax/autosave.php` | + `intro_text` dans `$providedtables[5]` et `$providedtables[9]` |
| `classes/external/autosave.php` | + `intro_text` dans `$providedtables[5]` et `$providedtables[9]` |
| `amd/src/reset_button.js` | nouveau fichier |
| `amd/src/intro_text_autosave.js` | nouveau fichier |
| `amd/src/cdcf_bootstrap.js` | refacto : retrait logique Reset, retrait autosave intro |
| `amd/build/*` | rebuilds correspondants (grunt amd) |
| `pages/step4.php` | appel du nouveau module `reset_button` |
| `backup/moodle2/backup_gestionprojet_stepslib.php` | + `intro_text` dans 2 nested elements |
| `tests/reset_helper_test.php` | + cas step 5 et 9 |
| `tests/ai_prompt_builder_test.php` | + cas step 5 et 9 |
| `version.php` | bump 2026050900 / 2.10.0 |
| `CHANGELOG.md` | + entrée 2.10.0 |
| `RELEASE_NOTES_v2.10.0.md` | nouveau |

## 17. Extensions futures (hors scope)

- **Step 7 (besoin élève)** — création complète du mode `provided` (table `besoin_eleve_provided`, flag `step7_provided`, page `step7_provided.php`, toggle Gantt) puis application du même pattern. Spec dédiée à venir.
- **Wording step 9 spécifique** — si retours utilisateurs montrent que « formulaire » sur diagramme est confusant, ajouter `reset_disabled_tooltip_diagram` etc.
- **Comparator structurel sur FAST** — si JSON instable observé, basculer step 9 sur `json_normalized` au lieu de `string_strict`.
