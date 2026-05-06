# Consigne fiche essai (step 5) — Design

**Date** : 2026-05-06
**Auteur** : Emmanuel REMY
**Status** : Validé pour implémentation
**Version cible** : 2.8.0

## Contexte

Les phases CDCF (step 4) et FAST (step 9) disposent déjà d'une "consigne enseignant" — un document distinct, éditable par l'enseignant, lecture seule pour l'élève, et qui pré-remplit la fiche élève au premier accès (pattern de seeding). Aucun équivalent n'existe pour la fiche essai (step 5), où les élèves démarrent toujours d'une fiche vierge.

L'enseignant veut pouvoir poser tout ou partie du protocole en amont (notamment la section *Conception du protocole*), pour que l'élève n'ait plus qu'à le suivre et compléter le reste de la fiche (résultats, observations, conclusion).

## Objectif

Ajouter une fiche consigne pour l'essai, calquée sur le pattern CDCF/FAST :

1. L'enseignant remplit une consigne (mêmes champs que la fiche essai).
2. Quand l'élève consulte la consigne, les champs sont **verrouillés** (lecture seule).
3. Quand l'élève ouvre sa fiche essai pour la première fois, ces champs sont **pré-remplis** depuis la consigne et **modifiables** — il complète librement.

## Comportement par acteur

| Acteur | Page Consigne (`step5_provided`) | Page Fiche Essai (`step5`) |
|---|---|---|
| Enseignant | Édite la consigne, autosave | Voit le travail élève (workspace) |
| Élève | Lecture seule — champs verrouillés | Pré-remplie au premier accès, éditable |

## Architecture

### A. Nouvelle table `gestionprojet_essai_provided`

Champs identiques à la fiche essai élève (sans dates de soumission ni instructions IA — ces éléments restent dans `gestionprojet_essai_teacher`, le modèle de correction).

| Champ | Type | Note |
|---|---|---|
| `id` | int auto-increment | PK |
| `gestionprojetid` | int | FK unique vers `gestionprojet.id` |
| `nom_essai` | char(255) | |
| `date_essai` | char(20) | |
| `groupe_eleves` | text | |
| `objectif` | text | |
| `fonction_service` | text | Section 1 |
| `niveaux_reussite` | text | Section 1 |
| `etapes_protocole` | text | Section 2 |
| `materiel_outils` | text | Section 2 |
| `precautions` | text | Section 2 — textarea libre, split au seeding |
| `resultats_obtenus` | text | Section 3 |
| `observations_remarques` | text | Section 3 |
| `conclusion` | text | Section 4 |
| `timecreated`, `timemodified` | int | |

### B. Nouveau flag `step5_provided`

Champ `int(1)` sur la table `gestionprojet`, default 0. Coché par l'enseignant dans `mod_form.php` pour activer la consigne. Tant que `step5_provided = 0`, la consigne et le seeding sont inactifs.

### C. Nouvelle page `pages/step5_provided.php`

Calquée sur `step4_provided.php` :

- Détection capability : `has_capability('mod/gestionprojet:configureteacherpages', $context)` → édition complète. Sinon → wrap `<div class="gp-fast-readonly">` (CSS désactive `pointer-events`) pour rendre les champs visuellement verrouillés.
- Bandeau d'intro `alert-info` avec strings `step5_desc_title` / `step5_desc_text`.
- Formulaire identique à la fiche essai élève (même structure de sections numérotées 1-4) mais avec mode `provided`.
- Autosave : appel AMD `mod_gestionprojet/teacher_step_init` avec `mode=provided`.
- Bouton Enregistrer + nav step (prev/next dans l'ordre des consignes) — uniquement si éditeur.

### D. Routing dans `view.php`

Étendre la condition existante :
```php
if ($mode === 'provided' && in_array($step, [4, 9], true)) {
```
en :
```php
if ($mode === 'provided' && in_array($step, [4, 5, 9], true)) {
```

Étendre aussi le `$providedaccess` côté élève pour autoriser l'accès quand le flag est activé même si `enable_step5 = 0` (comme step 4 / 9 actuellement).

### E. Seeding dans `gestionprojet_get_or_create_submission`

Bloc à ajouter dans `lib.php`, cohérent avec le pattern CDCF actuel (ligne 288-300) :

```php
// For Essai phase: when teacher provides a consigne, seed student submission with it.
// "Empty" means all main text fields are blank — initial creation or never touched.
if ($table === 'essai' && (int)$gestionprojet->step5_provided === 1) {
    $checkfields = ['fonction_service', 'niveaux_reussite', 'etapes_protocole',
                    'materiel_outils', 'precautions', 'resultats_obtenus',
                    'observations_remarques', 'conclusion', 'objectif'];
    $isempty = true;
    foreach ($checkfields as $f) {
        if (!empty(trim($record->{$f} ?? ''))) { $isempty = false; break; }
    }
    if ($isempty) {
        $provided = $DB->get_record('gestionprojet_essai_provided',
                                    ['gestionprojetid' => $gestionprojet->id]);
        if ($provided) {
            foreach ($checkfields as $f) {
                if (!empty($provided->{$f} ?? '')) { $record->{$f} = $provided->{$f}; }
            }
            // nom_essai et date_essai sont copiés s'ils existent côté consigne.
            if (!empty($provided->nom_essai)) { $record->nom_essai = $provided->nom_essai; }
            if (!empty($provided->date_essai)) { $record->date_essai = $provided->date_essai; }
            if (!empty($provided->groupe_eleves)) { $record->groupe_eleves = $provided->groupe_eleves; }
            $record->timemodified = time();
            $DB->update_record('gestionprojet_essai', $record);
        }
    }
}
```

**Comportement** : tant que l'élève n'a rempli aucun des champs textes longs, la consigne est re-seedée. Dès qu'un champ est saisi, plus de re-seeding (cohérent avec CDCF).

**Cas particulier `precautions`** : côté consigne, c'est un textarea libre. Côté élève, c'est un tableau JSON de 6 cases. Le seeding stocke la chaîne brute dans `record->precautions`. La page élève (`step5.php`, ligne 117-120) tente déjà `json_decode` ; si ce n'est pas du JSON, on retombera sur un tableau vide. **Ajustement nécessaire** : étendre la logique pour fallback sur `explode("\n", $string)` quand `json_decode` échoue, et limiter à 6 entrées.

### F. Tabs / Navigation

Étendre `gestionprojet_build_step_tabs` pour inclure step 5 dans l'onglet consignes (enseignant + élève quand `step5_provided=1`). L'ordre des consignes côté enseignant devient : 1, 3, 2, 4, 5, 9.

### G. AJAX autosave

Étendre `ajax/autosave.php` pour whitelist les champs essai en mode `provided`. Probablement réutilise déjà le whitelist du step 5 standard — vérifier et brancher.

### H. mod_form.php

Ajouter une checkbox `step5_provided` à côté/dans la section "Consignes" :
```php
$mform->addElement('advcheckbox', 'step5_provided',
                   get_string('step5_provided', 'gestionprojet'));
$mform->setDefault('step5_provided', 0);
```

### I. Strings (lang)

Ajouter dans `lang/fr/gestionprojet.php` et `lang/en/gestionprojet.php` :
- `step5_provided` — Label de la checkbox dans le form
- `step5_desc_title` — Titre du bandeau de la page consigne
- `step5_desc_text` — Texte du bandeau (explique le seeding)
- (réutilise `consigne`, `correction_models`, etc. déjà existants)

### J. delete_instance

Ajouter dans `gestionprojet_delete_instance` (lib.php) :
```php
$DB->delete_records('gestionprojet_essai_provided', ['gestionprojetid' => $id]);
```
(Pour rester aligné avec la checklist Moodle — point 8 dans CLAUDE.md.)

## Points hors scope

- Les **dates de soumission** et **instructions IA** restent sur le modèle de correction (`gestionprojet_essai_teacher`) — la consigne ne contient que du contenu pédagogique.
- Pas de bouton "réinitialiser depuis la consigne" sur la fiche élève — YAGNI ; si l'élève a besoin de revoir la consigne, il a le lien lecture seule.
- Pas de PDF export spécifique pour la consigne — l'enseignant peut imprimer la page si besoin.

## Plan d'implémentation (10 étapes)

1. `db/install.xml` — ajouter table `gestionprojet_essai_provided` + champ `step5_provided`
2. `db/upgrade.php` — migration : ajout table + ajout champ + bump version
3. `version.php` — `$plugin->version` (2026050600) + `$plugin->release` (2.8.0)
4. `lib.php` — seeding dans `gestionprojet_get_or_create_submission` + `delete_records` dans `gestionprojet_delete_instance` + extension de la nav consignes
5. `pages/step5_provided.php` — nouvelle page (modèle = `step4_provided.php`, structure formulaire essai)
6. `pages/step5.php` — adapter la lecture de `precautions` (fallback split sur retours à la ligne)
7. `view.php` — étendre `mode=provided` à step 5 + élargir `$providedaccess`
8. `mod_form.php` — checkbox `step5_provided`
9. `lang/fr` + `lang/en` — strings nécessaires
10. `ajax/autosave.php` — whitelist champs essai pour `mode=provided`

## Tests / vérifications

Côté preprod (cf. `TESTING.md`) :

- [ ] Activer `step5_provided` dans une instance, remplir la consigne en tant qu'enseignant.
- [ ] Se connecter en élève : vérifier que la consigne est consultable mais en lecture seule.
- [ ] Ouvrir la fiche essai : champs pré-remplis depuis la consigne, **éditables**.
- [ ] Modifier un champ côté élève → enregistrer → recharger : la modification est conservée, pas de re-seeding.
- [ ] Vider tous les champs côté élève → recharger : re-seeding complet.
- [ ] Désactiver `step5_provided` côté enseignant : la consigne disparaît des onglets, les fiches élèves existantes restent intactes.
- [ ] Supprimer l'instance → vérifier que `gestionprojet_essai_provided` est nettoyée.
- [ ] Vérifier autosave côté consigne enseignant.
- [ ] Vérifier le format du champ `precautions` (texte libre côté consigne, 6 cases côté élève).
