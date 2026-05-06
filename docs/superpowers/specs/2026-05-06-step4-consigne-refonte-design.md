# Refonte Step 4 — Séparation Consigne enseignant / Travail élève

**Date** : 2026-05-06
**Statut** : Design approuvé, en attente de plan d'implémentation
**Auteur** : Emmanuel REMY (avec Claude Code)
**Périmètre** : Step 4 (CDCF) en mode `provided`. Extension future à step 5 (essai), step 7 (besoin élève à venir), step 9 (FAST).

---

## 1. Contexte & problème

Aujourd'hui, lorsque `step4_provided=1`, la consigne saisie par l'enseignant dans `gestionprojet_cdcf_provided` est **copiée** dans le record de chaque élève (`gestionprojet_cdcf`) à la première ouverture de la page step 4 par l'élève (mécanisme « seed » dans `gestionprojet_get_or_create_submission`). Le seed ne se déclenche que si le record élève est *vide* (pas de `fonctionsService` ni de `contraintes`).

**Conséquence (bug perçu)** : Si l'enseignant modifie la consigne *après* qu'un élève ait ouvert step 4 ne serait-ce qu'une fois, la modification n'atteint jamais l'élève. Son record contient une copie figée de l'ancienne consigne. C'est le comportement par design (éviter d'écraser le travail de l'élève), mais aucun mécanisme ne permet à l'élève (ou à l'enseignant) de récupérer la version la plus récente.

## 2. Objectifs

1. Permettre aux élèves de récupérer la dernière version de la consigne enseignant **explicitement**, via un bouton « Réinitialiser le formulaire ».
2. Ajouter un **texte d'intro pédagogique** rédigé par l'enseignant, visible en lecture seule par les élèves en haut de la page step 4, **lu en temps réel** depuis `cdcf_provided` (donc dynamique : modifications enseignant propagées immédiatement).
3. Conserver le mécanisme de pré-remplissage initial existant (seed) sans modification.
4. Préserver l'intégrité des soumissions (pas de reset possible sur un travail soumis).

## 3. Non-objectifs

- Pas de migration des records élèves existants (option 1 retenue).
- Pas d'extension immédiate à step 5 / step 9 / step 7. Le design est conçu pour être réutilisable, mais l'implémentation se limite à step 4.
- Pas de refonte de la logique de seed dans `gestionprojet_get_or_create_submission` — elle reste comme aujourd'hui.
- Pas d'éditeur d'intro côté élève (lecture seule stricte).

## 4. Modèle de données

### Schéma

Une seule colonne ajoutée :

| Table | Colonne | Type | Nullable | Default |
|---|---|---|---|---|
| `gestionprojet_cdcf_provided` | `intro_text` | `XMLDB_TYPE_TEXT` | oui | `null` |

Format de stockage : HTML brut (sortie Atto). Aucun assainissement à l'écriture, conformément à la convention Moodle. Assainissement à la lecture via `format_text(..., FORMAT_HTML, ['context' => $context])`.

### Upgrade DB

Nouvelle étape dans `db/upgrade.php` versionnée à la nouvelle version (`$plugin->version` actuelle = `2026050700`, donc nouvelle valeur typiquement `2026050800` ou supérieure selon l'ordre des merges). Pas de backfill : le champ est optionnel et vide par défaut.

```php
$newversion = 2026050800; // À ajuster au moment du bump réel.
if ($oldversion < $newversion) {
    $table = new xmldb_table('gestionprojet_cdcf_provided');
    $field = new xmldb_field('intro_text', XMLDB_TYPE_TEXT, null, null, null, null, null, 'interacteurs_data');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }
    upgrade_mod_savepoint(true, $newversion, 'gestionprojet');
}
```

### Aucune migration des records élèves

Les records existants dans `gestionprojet_cdcf` ne sont pas touchés. Les élèves qui veulent récupérer la dernière consigne cliquent eux-mêmes sur le bouton Reset.

## 5. Côté enseignant — `pages/step4_provided.php`

### Ajout d'un bloc « Texte de présentation aux élèves »

Position : au-dessus des sections existantes (Interacteurs / FS / Contraintes), juste après le bloc `gp-cdcf-norm-block` de la norme NF EN 16271.

Layout :

```
┌─────────────────────────────────────────┐
│ NF EN 16271 (intro existante)            │
├─────────────────────────────────────────┤
│ ┌─ Texte de présentation aux élèves ──┐ │  ← NOUVEAU
│ │ [éditeur Atto]                       │ │
│ │ Help: « Visible en haut de l'activité│ │
│ │ élève en lecture seule »             │ │
│ └─────────────────────────────────────┘ │
├─────────────────────────────────────────┤
│ Interacteurs                              │
│ Fonctions de service                      │
│ Contraintes                               │
└─────────────────────────────────────────┘
```

### Implémentation

- Activation Atto via l'API Moodle :
  ```php
  $editor = editors_get_preferred_editor(FORMAT_HTML);
  $editor->use_editor('intro_text', ['context' => $context]);
  ```
- Champ HTML : `<textarea name="intro_text" id="intro_text">...</textarea>`.
- Pré-remplissage : valeur courante de `$model->intro_text` (HTML brut).
- Sauvegarde : autosave existant via `cdcf_bootstrap.js` → `ajax/autosave.php`. On ajoute `intro_text` à la whitelist `fields` de la branche `mode === 'provided'` step 4 dans `ajax/autosave.php` :
  ```php
  4 => ['table' => 'gestionprojet_cdcf_provided', 'fields' => ['interacteurs_data', 'intro_text']],
  ```
- Le bouton « Enregistrer » existant déclenche aussi la sauvegarde du champ.

### Validation serveur

- `optional_param` n'est pas applicable côté autosave (le payload est JSON).
- Le décodage existant utilise déjà `clean_param` sur les champs whitelisted. `intro_text` sera traité avec `PARAM_RAW` (HTML autorisé, assainissement à la lecture, pas à l'écriture — convention Moodle).

### Points d'attention

- Atto est lourd ; l'éditeur ne doit s'initialiser qu'une fois. À vérifier que `cdcf_bootstrap.js` ne re-render pas la zone `#intro_text` lors de ses redraws.
- Le mode `provided` ne déclenche aucune notification IA / gradebook (cohérent avec le fait que c'est de la config enseignant).

## 6. Côté élève — `pages/step4.php`

### 6.1 Affichage du texte d'intro (lecture seule)

Position : tout en haut de la zone `<div class="step4-container gp-student">`, avant le titre Moodle (`$OUTPUT->heading`).

Encadré `alert-info` permanent (non repliable, pas de fermeture). Affiché uniquement si `cdcf_provided.intro_text` est non vide.

```php
$provided = $DB->get_record('gestionprojet_cdcf_provided', ['gestionprojetid' => $gestionprojet->id]);
if ($provided && !empty(trim(strip_tags($provided->intro_text ?? '')))) {
    echo html_writer::start_div('alert alert-info gp-consigne-intro');
    echo html_writer::tag('h4', get_string('intro_section_title', 'gestionprojet'));
    echo format_text($provided->intro_text, FORMAT_HTML, ['context' => $context]);
    echo html_writer::end_div();
}
```

**Lecture en temps réel** : pas de copie dans le record élève. Toute modification enseignant est visible immédiatement par tous les élèves au prochain reload.

### 6.2 Bouton « Réinitialiser le formulaire »

**Position** : sous le formulaire CDCF, dans la section `export-section`, à côté des boutons Submit / Revert existants.

**Style** : `btn btn-warning` (cohérent avec Revert).

**États** :

- Visible toujours (tant que `step4_provided=1` ET un record `cdcf_provided` existe pour l'instance).
- Désactivé (`disabled` + `cursor: not-allowed` + tooltip) si `submission.status === 1`.

**Tooltip désactivé** (string `reset_disabled_tooltip`) :
> « Le formulaire est verrouillé après soumission. Demandez à l'enseignant de le déverrouiller pour réinitialiser. »

### 6.3 Modal de confirmation

Réutilisation du pattern modal Bootstrap déjà utilisé par submit/revert (`student_submit_helper.php` + `student_submission.js`).

Contenu :

> **Réinitialiser le formulaire ?**
>
> Toutes vos modifications actuelles seront perdues et remplacées par la dernière version de la consigne fournie par l'enseignant.
>
> Cette action est irréversible.
>
> [Annuler] [Réinitialiser]

### 6.4 Module JS

Ajout d'un listener dans `amd/src/cdcf_bootstrap.js` par défaut (pour minimiser le nombre de modules). Décision réversible : si la logique Reset dépasse ~50 lignes utiles ou rend `cdcf_bootstrap.js` difficile à lire, extraire dans un nouveau module `amd/src/cdcf_reset.js`.

Logique :
1. Listener sur `#resetButton`.
2. Affichage modal Bootstrap (réutilisation du pattern existant).
3. Sur confirmation : `fetch('/mod/gestionprojet/ajax/reset_to_provided.php', { method: 'POST', body: FormData{ id, step, groupid, sesskey } })`.
4. Au succès : toast de confirmation puis `window.location.reload()` (pour redessiner CDCF avec les nouvelles données).
5. En cas d'erreur : toast d'erreur, pas de reload.

## 7. Endpoint AJAX nouveau — `ajax/reset_to_provided.php`

### Contrat

```
POST /mod/gestionprojet/ajax/reset_to_provided.php
Body (form-urlencoded) :
  id      = cmid (PARAM_INT)
  step    = 4 (PARAM_INT)
  groupid = group ID, ou 0 (PARAM_INT)
  sesskey = standard Moodle (PARAM_RAW)

Response : application/json
  Success : { success: true, message: <localized string> }
  Error   : { success: false, error: <code>, message: <localized string> }
            HTTP 403 si verrouillé/sans capability, 400 si payload invalide
```

### Logique serveur

1. `require_login($course, false, $cm)` + `require_sesskey()`.
2. `require_capability('mod/gestionprojet:submit', $context)`.
3. Validation : `step === 4` (les autres steps restent à implémenter). Sinon 400.
4. Charge `cdcf_provided` pour l'instance. Si absent ou `interacteurs_data` vide → 400 (rien à reset depuis).
5. Charge le record élève via `gestionprojet_get_or_create_submission($gestionprojet, $groupid, $USER->id, 'cdcf')`.
6. **Garde-fou** : si `submission.status === 1` → 403 (cohérent avec UI désactivée).
7. Écrase :
   ```php
   $record->interacteurs_data = $provided->interacteurs_data;
   $record->timemodified = time();
   $DB->update_record('gestionprojet_cdcf', $record);
   ```
8. Log dans `gestionprojet_history` via `gestionprojet_log_change` (audit trail).
9. Renvoie `{success: true, message: get_string('reset_success', 'gestionprojet')}`.

### Pourquoi un endpoint dédié

Plutôt que d'étendre `autosave.php` :
- Sémantique différente : action explicite, pas une sauvegarde silencieuse.
- Audit trail souhaitable (ligne dans `history`).
- Garde-fou serveur dédié sur le statut de soumission.
- Le client n'a pas à reconstruire les données provided côté JS — le serveur fait la copie.

## 8. Intégration IA — `ajax/evaluate.php`

Lors de la construction du prompt CDCF, si `cdcf_provided.intro_text` est non vide, ajouter une section **avant** les `ai_instructions` du `cdcf_teacher` :

```
## Contexte fourni par l'enseignant
{{strip_tags(html_entity_decode(intro_text))}}

## Instructions d'évaluation
{{ai_instructions}}

...
```

- **Plain text** envoyé à l'IA : `strip_tags()` + `html_entity_decode()` (pas de HTML).
- Purement additif : ne modifie pas la structure des `ai_instructions` ni des autres champs du prompt.
- Le texte d'intro est **contexte**, pas instruction. Les `ai_instructions` du modèle de correction (`cdcf_teacher`) restent l'unique source d'instructions évaluatives pour l'IA.

## 9. Backup / Restore

### Backup — `backup/moodle2/backup_gestionprojet_stepslib.php`

Ajouter `intro_text` à la liste de champs du `backup_nested_element('cdcf_provided', ...)` (ligne 105 actuellement).

### Restore — `backup/moodle2/restore_gestionprojet_stepslib.php`

La fonction `process_gestionprojet_cdcf_provided` (ligne 248 actuellement) utilise déjà `(array)$data` puis `$DB->insert_record('gestionprojet_cdcf_provided', $data)`. Le nouveau champ sera porté automatiquement *à condition* qu'il soit listé dans le backup. Aucune modification supplémentaire requise dans le restore.

## 10. Sécurité

### XSS

| Surface | Protection |
|---|---|
| Stockage HTML brut | Aucun assainissement à l'écriture (convention Moodle). |
| Affichage côté élève | `format_text(..., FORMAT_HTML, ['context' => $context])` → assainissement Moodle natif (anti-XSS). |
| Affichage côté enseignant (Atto) | Atto rend dans un sandbox iframe, gère ses propres protections. |
| Envoi à l'IA | `strip_tags()` + `html_entity_decode()` → plain text strict. |

### CSRF / Capabilities

- `require_sesskey()` sur l'endpoint reset.
- `require_capability('mod/gestionprojet:submit')` côté élève.
- `require_capability('mod/gestionprojet:configureteacherpages')` côté enseignant (déjà en place dans autosave pour mode=provided).
- Garde-fou serveur sur `status === 1` (anti-bypass via dev tools).

## 11. Internationalisation

Tous les nouveaux strings sont à ajouter dans `lang/en/gestionprojet.php` ET `lang/fr/gestionprojet.php`.

| Key | Usage | EN | FR |
|---|---|---|---|
| `intro_text_label` | Label éditeur enseignant | Introduction text for students | Texte de présentation aux élèves |
| `intro_text_help` | Help text éditeur | Displayed read-only at the top of the student activity. Use it to explain expectations, context, methodological guidelines. | Affiché en lecture seule en haut de l'activité élève. Utilisez-le pour expliquer ce qui est attendu, le contexte, les consignes méthodologiques. |
| `intro_section_title` | Titre du bloc lecture seule élève | Teacher's instructions | Consignes de l'enseignant |
| `reset_button_label` | Texte du bouton | Reset form | Réinitialiser le formulaire |
| `reset_modal_title` | Titre modal | Reset form? | Réinitialiser le formulaire ? |
| `reset_modal_body` | Corps modal | All your current changes will be lost and replaced by the latest version of the teacher's instructions. This action cannot be undone. | Toutes vos modifications actuelles seront perdues et remplacées par la dernière version de la consigne fournie par l'enseignant. Cette action est irréversible. |
| `reset_modal_confirm` | Bouton confirmer modal | Reset | Réinitialiser |
| `reset_modal_cancel` | Bouton annuler modal | Cancel | Annuler |
| `reset_disabled_tooltip` | Tooltip si soumis | The form is locked after submission. Ask your teacher to unlock it to reset. | Le formulaire est verrouillé après soumission. Demandez à l'enseignant de le déverrouiller pour réinitialiser. |
| `reset_success` | Toast après reset | Form reset to the latest teacher instructions. | Formulaire réinitialisé à la dernière version de la consigne. |
| `reset_error_locked` | Erreur garde-fou serveur | Cannot reset a submitted form. | Impossible de réinitialiser un formulaire soumis. |

## 12. Versioning

- `version.php` : `$plugin->version` actuel = `2026050700`. Bumper à `2026050800` (ou date du jour de release au format YYYYMMDDXX si postérieure).
- `$plugin->release` : passer de `2.8.0` à `2.9.0` (changement fonctionnel + nouveau champ DB).

## 13. Plan de tests

### 13.1 Tests automatisés (PHPUnit)

Localisation : `tests/`.

- **Test unitaire** (non-régression) : `gestionprojet_get_or_create_submission()` reste inchangé pour les cas existants (seed sur record vide, pas de seed sur record non vide).
- **Test unitaire endpoint** `reset_to_provided.php` :
  - Cas nominal : élève en draft → record écrasé par `cdcf_provided.interacteurs_data`.
  - Garde-fou : élève soumis (status=1) → 403, record inchangé.
  - Capability : utilisateur sans `submit` → 403.
  - Sesskey invalide → erreur.
  - `cdcf_provided` absent → 400.
- **Test PHPUnit** sur le builder de prompt IA : `intro_text` injecté quand non vide, omis quand vide ou null.

### 13.2 Validation manuelle (préprod)

Checklist déroulée sur `preprod.ent-occitanie.com` :

1. Enseignant : ouvrir step 4 / mode=provided, remplir le nouveau champ d'intro avec du HTML riche (gras, liste, lien), enregistrer, recharger → vérifier persistance.
2. Élève (compte test) qui n'a *jamais* ouvert step 4 : ouvre la page → voit l'encadré bleu + formulaire pré-rempli depuis la consigne actuelle.
3. Élève qui *a déjà* un draft (cas du bug rapporté) : ouvre la page → voit l'encadré bleu + son ancien formulaire (inchangé). Le seed n'est pas re-déclenché.
4. Élève clique sur Reset, confirme dans la modal → formulaire remplacé par la dernière consigne.
5. Élève soumet → bouton Reset grisé, tooltip visible, clic sans effet.
6. Enseignant fait revert → bouton Reset réactivé.
7. Enseignant modifie le texte d'intro → tous les élèves voient la nouvelle version au prochain reload (lecture en temps réel, pas de copie).
8. Enseignant déclenche évaluation IA → vérifier dans les logs serveur que `intro_text` apparaît bien dans le prompt envoyé à l'IA.
9. Backup d'un cours puis restore dans un autre cours → `intro_text` préservé sur la copie.
10. Anciens élèves dont le record est encore vide (jamais ouvert) : seed normal au prochain accès.

### 13.3 Tentative de bypass UI (sécurité)

- Élève soumis (status=1) tente de forcer `POST /ajax/reset_to_provided.php` via dev tools → 403.
- Utilisateur sans capability `submit` tente le même appel → 403.

## 14. Déploiement

Selon la convention du projet (CLAUDE.md + auto-memory `feedback_deploy_preprod_at_dev_end`) :

1. Bump `version.php` (version + release `2.9.0`).
2. `db/upgrade.php` : ajout de l'étape pour `intro_text`.
3. `git push origin <branch>:main` sur Forge EDU.
4. SCP preprod → validation manuelle 13.2 complète.
5. Build ZIP : `zip -r gestionprojet.zip gestionprojet/ -x "*.git*" "*.claude*" "*lessons.md"`.
6. Upload via Moodle Admin → Plugins → Install plugins (production), validation des notifications upgrade.
7. Mise à jour `CHANGELOG.md` + `RELEASE_NOTES_v2.9.0.md`.

## 15. Risques résiduels & mitigations

| Risque | Mitigation |
|---|---|
| Atto se réinitialise mal après autosave | Validation manuelle 13.2.1 ; si problème, ajouter un guard dans `cdcf_bootstrap.js` pour ne pas re-render la zone `#intro_text`. |
| `format_text` HTML cassé sur certaines balises | Test avec listes, liens, images dans l'éditeur (la config Atto Moodle filtre par défaut). |
| Élève déjà soumis qui appuie sur Reset via dev tools (bypass UI) | Garde-fou serveur 403 dans `reset_to_provided.php` — couvert. |
| Régression du seed initial sur nouveaux élèves | Test 13.2.2 + test PHPUnit 13.1. |
| Conflits autosave / Reset | Reset déclenche `window.location.reload()` → tout autosave en cours est invalidé, ordre OK. |
| Backup avec un ancien Moodle (pré-upgrade) | Le champ `intro_text` est nullable et restauré comme `null` ; comportement = pas de bloc d'intro affiché. Aucun crash. |

## 16. Extension future (hors scope)

Le pattern décrit ici (`intro_text` + bouton Reset + endpoint dédié) sera dupliqué pour :

- **Step 5 (essai)** — `gestionprojet_essai_provided` + endpoint `reset_to_provided.php?step=5`.
- **Step 9 (FAST)** — `gestionprojet_fast_provided` + endpoint `reset_to_provided.php?step=9`.
- **Step 7 (besoin élève)** — création du mode `provided` à venir, intégration dans la même logique.

Le endpoint `reset_to_provided.php` sera étendu pour gérer `step ∈ {4, 5, 7, 9}` au moment de l'extension. La structure actuelle (validation `step === 4`) est conçue pour être étendue facilement (table de mapping similaire à celle de `autosave.php`).

## 17. Touchpoints code (récap)

| Fichier | Modification |
|---|---|
| `db/install.xml` | + `intro_text` sur `gestionprojet_cdcf_provided` |
| `db/upgrade.php` | + étape `add_field` |
| `pages/step4_provided.php` | + bloc éditeur Atto |
| `pages/step4.php` | + bloc lecture seule + bouton Reset |
| `ajax/autosave.php` | + `intro_text` dans la whitelist mode=provided step 4 |
| `ajax/reset_to_provided.php` | nouveau fichier |
| `ajax/evaluate.php` | + injection `intro_text` dans prompt IA |
| `amd/src/cdcf_bootstrap.js` | + listener bouton Reset + modal |
| `amd/build/cdcf_bootstrap.min.js` | rebuild via grunt |
| `backup/moodle2/backup_gestionprojet_stepslib.php` | + `intro_text` dans nested element |
| `lang/en/gestionprojet.php` | + 11 strings |
| `lang/fr/gestionprojet.php` | + 11 strings |
| `version.php` | bump version + release `2.9.0` |
| `tests/` | + tests PHPUnit (3 fichiers ou ajout dans existants) |
| `styles.css` | + style `.gp-consigne-intro`, `#resetButton:disabled` (optionnel selon overrides nécessaires) |
| `CHANGELOG.md` | + entrée 2.9.0 |
| `RELEASE_NOTES_v2.9.0.md` | nouveau |
