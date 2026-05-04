# Séparation Consignes / Modèles de correction — Design

**Date** : 2026-05-04
**Auteur** : Emmanuel REMY (via brainstorming Claude Code)
**Statut** : design validé, à planifier
**Cible** : `mod_gestionprojet` v2.4.0
**Prérequis** : v2.3.0 (FAST) déjà déployé

## Contexte

Aujourd'hui, sur les phases 4 (CDCF) et 9 (FAST), la table `gestionprojet_<phase>_teacher` joue **deux rôles confus** :
1. Document fourni à l'élève comme consigne (mode `provided`)
2. Modèle de correction utilisé par le correcteur IA

Le mode hybride force l'enseignant à dévoiler aux élèves tout son modèle de correction (ou rien). Cela ne convient pas pédagogiquement : l'enseignant veut souvent fournir un *cadre* (par exemple le produit + milieu) tout en gardant pour lui la version complète attendue.

L'autre conséquence du mélange : la barre de navigation `step_tabs` mélange phases « consignes » et « modèles de correction », ce qui rend confus l'usage côté enseignant.

## Typologie validée

| Type | Phases concernées | Rôle |
|------|---|---|
| **Consignes** (documents fournis par l'enseignant) | 1 (Fiche descriptive), 3 (Planification), 2 (Expression du besoin), 4 (CDCF), 9 (FAST) | Support/référence donné à l'élève. Pas de modèle de correction, pas d'`ai_instructions`. |
| **Modèles de correction** (pour évaluation IA) | 4 (CDCF), 5 (Essai), 6 (Rapport), 7 (Besoin élève), 8 (Carnet), 9 (FAST) | Référence attendue + `ai_instructions`. Jamais montré aux élèves. |

Phases avec **2 facettes** : 4 (CDCF) et 9 (FAST).

## Décisions validées (brainstorming)

| # | Décision | Raison |
|---|---|---|
| 1 | Création de 2 nouvelles tables `_provided` pour 4 et 9 (pas pour 1, 2, 3, 5, 6, 7, 8) | 1/2/3 sont déjà des consignes pures (table existante = consigne). 5/6/7/8 sont des corrections pures. Seules 4 et 9 ont les 2 facettes. |
| 2 | La table `_teacher` existante devient strictement « modèle de correction » | Conserve `ai_instructions`, perd son rôle de consigne. |
| 3 | La nouvelle table `_provided` ne contient **pas** `ai_instructions` ni dates de soumission | Une consigne n'est pas notée. |
| 4 | À l'upgrade : si `stepN_provided = 1` et `_teacher` non vide, copie le contenu pertinent de `_teacher` → `_provided` | Préserve l'usage actuel de l'enseignant. |
| 5 | À la création de la submission élève (mode provided actif) : pré-remplir depuis `_provided` (pas depuis `_teacher`) | L'élève reçoit la consigne, pas le modèle de correction. |
| 6 | `step_tabs` a 2 nouveaux contextes : `consignes` (tabs {1, 3, 2, 4, 9}) et `correction` (tabs {4, 5, 6, 7, 8, 9}) | Cohérence pédagogique. Plus d'inclusion de tous les steps dans toutes les pages. |
| 7 | Les pages « consignes » ne contiennent **ni** tableau de bord des soumissions **ni** champ `ai_instructions` | Wording propre : aucune confusion possible. |

## Architecture

### Modèle de données

**Modifications de `gestionprojet`** : aucune (les flags `step4_provided` et `step9_provided` existent déjà).

**Nouvelle table `gestionprojet_cdcf_provided`** :

| Champ | Type | Note |
|-------|------|------|
| id | INT PK | |
| gestionprojetid | INT FK | unique |
| produit | CHAR(255) | |
| milieu | CHAR(255) | |
| fp | TEXT | Fonction principale |
| interacteurs_data | TEXT | JSON des interacteurs/FCs (sans criteres complets) |
| timecreated | INT | |
| timemodified | INT | |

Schéma identique à `gestionprojet_cdcf_teacher` **moins** `ai_instructions`, `submission_date`, `deadline_date`.

**Nouvelle table `gestionprojet_fast_provided`** :

| Champ | Type | Note |
|-------|------|------|
| id | INT PK | |
| gestionprojetid | INT FK | unique |
| data_json | LONGTEXT | Structure FAST (FP + FT + sous-FT + ST) |
| timecreated | INT | |
| timemodified | INT | |

Schéma identique à `gestionprojet_fast_teacher` moins `ai_instructions`, `submission_date`, `deadline_date`.

### Migration des données

Dans `db/upgrade.php` (savepoint 2026050500) :

1. Créer les 2 nouvelles tables.
2. Pour chaque instance avec `step4_provided = 1` :
   - Lire `gestionprojet_cdcf_teacher` correspondant.
   - Insérer dans `gestionprojet_cdcf_provided` les champs `produit`, `milieu`, `fp`, `interacteurs_data`.
3. Pour chaque instance avec `step9_provided = 1` :
   - Lire `gestionprojet_fast_teacher` correspondant.
   - Insérer dans `gestionprojet_fast_provided` le champ `data_json`.

Les tables `_teacher` ne sont **pas** modifiées (on garde le champ `ai_instructions` qui les distingue désormais clairement comme « modèle de correction »).

### Routing

`view.php` :

```php
// Modes valides : 'teacher' (modèle correction), 'provided' (consigne), '' (élève)
// Steps 4 et 9 acceptent 'provided' et 'teacher'
// Step 5, 6, 7, 8 acceptent uniquement 'teacher'
// Step 1, 2, 3 acceptent uniquement le mode par défaut (consigne)

if ($step === 4 || $step === 9) {
    if ($mode === 'provided') {
        require_once($CFG->dirroot . '/mod/gestionprojet/pages/step' . $step . '_provided.php');
    } else if ($mode === 'teacher') {
        require_once($CFG->dirroot . '/mod/gestionprojet/pages/step' . $step . '_teacher.php');
    } else {
        require_once($CFG->dirroot . '/mod/gestionprojet/pages/step' . $step . '.php');
    }
}
```

### Pages créées

**`pages/step4_provided.php`** :
- En-tête : « Consigne — Cahier des charges fonctionnel »
- Formulaire identique à step4_teacher mais **sans** champ `ai_instructions`.
- Pas de tableau de bord soumissions.
- step_tabs avec contexte `consignes` (tabs {1, 3, 2, 4, 9}).
- Lit/écrit dans `gestionprojet_cdcf_provided`.

**`pages/step9_provided.php`** :
- En-tête : « Consigne — Diagramme FAST »
- Formulaire identique à step9_teacher mais **sans** champ `ai_instructions`.
- Pas de tableau de bord soumissions.
- step_tabs avec contexte `consignes`.
- Lit/écrit dans `gestionprojet_fast_provided`.

### Pages modifiées

**`pages/step4_teacher.php`, `pages/step5_teacher.php`, ..., `pages/step9_teacher.php`** :
- Wording « Modèle de correction » dans le header.
- step_tabs avec contexte `correction` (tabs {4, 5, 6, 7, 8, 9}).
- Tableau de bord soumissions affiché (déjà présent ailleurs).
- Pour 4 et 9 : suppression du basculement « hybride » — la page sert STRICTEMENT au modèle de correction.

**`pages/step1.php`, `pages/step2.php`, `pages/step3.php`** :
- step_tabs avec contexte `consignes` (tabs {1, 3, 2, 4, 9}).
- Wording « Consigne » si applicable.

**`pages/home.php`** (Gantt) :
- Ligne 1 cellule pour step 4 → URL `view.php?step=4&mode=provided` (au lieu de `mode=teacher`).
- Ligne 1 cellule pour step 9 → URL `view.php?step=9&mode=provided`.
- Ligne 2 inchangée (continue de pointer `mode=teacher`).
- Critère de complétion ligne 1 lu depuis `_provided` (au lieu de `_teacher`).

### `lib.php` — modifications

**`gestionprojet_get_or_create_submission`** : pour les phases 4 et 9, quand `stepN_provided = 1`, copier le contenu de `_provided` au lieu de `_teacher` lors de la création de la submission élève.

**`gestionprojet_build_step_tabs($gp, $cmid, $currentstep, $context)`** : étendre le paramètre `$context` pour accepter `'consignes'` et `'correction'`.

```php
$tabsbycontext = [
    'consignes'  => [1, 3, 2, 4, 9],
    'correction' => [4, 9, 5, 8, 6, 7],
    // 'student' et 'grading' inchangés.
];
```

Dans le contexte `'consignes'`, l'URL d'un tab vers 4 ou 9 utilise `mode=provided` ; dans `'correction'`, c'est `mode=teacher`.

**`gestionprojet_delete_instance`** : ajouter purge des 2 nouvelles tables.

### `ajax/autosave.php`

Ajouter le mode `'provided'` au switch teacher :

```php
$providedtables = [
    4 => ['table' => 'gestionprojet_cdcf_provided', 'fields' => ['produit', 'milieu', 'fp', 'interacteurs_data']],
    9 => ['table' => 'gestionprojet_fast_provided', 'fields' => ['data_json']],
];

if ($mode === 'provided') {
    if (!isset($providedtables[$step])) {
        throw new moodle_exception('invalidstep');
    }
    // (logique standard d'upsert mirror du teacher mode)
}
```

### Toggle endpoint `ajax/toggle_step.php`

Inchangé. Le flag `stepN_provided` toggle juste 0/1 ; le contenu est désormais dans la nouvelle table `_provided`.

## Conformité Moodle (checklist)

| Règle | Application |
|-------|-------------|
| 1. GPL header | Tous les nouveaux PHP : header complet 2 paragraphes |
| 2. No inline CSS | RAS (pas de nouvelle UI lourde) |
| 3. No inline JS | Pages provided réutilisent `fast_editor` / structure CDCF existante |
| 4. English-only comments | RAS |
| 5. No debug code | RAS |
| 6. No superglobals | `required_param` / `optional_param` |
| 7. Sécurité entrée | `require_login` + `require_capability` sur les nouvelles pages |
| 8. delete_instance | 2 nouveaux `$DB->delete_records` ajoutés |
| 9. Strings via lang | Nouveaux strings `consigne`, `modele_correction`, etc. (EN+FR) |
| 10. Version bump | 2026050500, release 2.4.0, upgrade step présent |
| 11. Privacy provider | RAS (les tables `_provided` ne contiennent pas de userid) |

## Fichiers impactés

### Créés

- `pages/step4_provided.php`
- `pages/step9_provided.php`
- (Mustache : `templates/step9_provided_form.mustache` ou réutilisation du form mustache existant en filtrant `isteacher`)

### Modifiés

- `db/install.xml` (2 nouvelles tables)
- `db/upgrade.php` (savepoint 2026050500 + migration des données)
- `lib.php` (`get_or_create_submission`, `build_step_tabs`, `delete_instance`)
- `pages/home.php` (URL ligne 1 → `mode=provided`)
- `pages/step1.php`, `pages/step2.php`, `pages/step3.php` (step_tabs context = `consignes`)
- `pages/step4_teacher.php`, `step5_teacher.php`, ..., `step9_teacher.php` (step_tabs context = `correction`, wording « Modèle de correction »)
- `view.php` (route `mode=provided`)
- `ajax/autosave.php` (mode `'provided'`)
- `lang/en/gestionprojet.php` + `lang/fr/gestionprojet.php` (strings)
- `version.php`
- `backup/moodle2/backup_gestionprojet_stepslib.php` + `restore_gestionprojet_stepslib.php` (2 nouvelles tables)

## Critères de succès

1. Les pages 1, 2, 3, 4_provided, 9_provided affichent la nav « consignes » uniquement.
2. Les pages 4_teacher, 5_teacher, 6_teacher, 7_teacher, 8_teacher, 9_teacher affichent la nav « correction » uniquement.
3. Le contenu fourni à l'élève vient désormais de `_provided`, pas de `_teacher`.
4. L'enseignant peut éditer les 2 versions séparément via le Gantt (ligne 1 et ligne 2).
5. La migration upgrade.php ne perd aucune donnée pour les instances existantes en mode `provided`.
6. Aucun tableau de bord soumissions sur les pages consignes.
7. Aucune mention `ai_instructions` sur les pages consignes.
8. Suppression d'instance purge les 2 nouvelles tables.

## Hors-scope

- L'assistant IA « Suggérer consignes IA » (Spec 2 — toujours à planifier après).
- Pour les phases 1, 2, 3 (consignes pures) : pas de fork structurel — juste ajustement du wording et du context `step_tabs`.
- La fusion des numéros 2 et 7 en une phase logique unique « Expression du besoin » (consigne 2 + correction 7) : reste 2 numéros distincts pour ne pas casser la migration.

## Tests manuels (préprod après déploiement)

1. Connexion enseignant : vérifier que ligne 1 du Gantt pour 4 et 9 ouvre la nouvelle page `_provided`.
2. Saisir contenu dans `_provided`, sauvegarder, vérifier autosave → DB.
3. Connexion élève : vérifier que sa submission CDCF/FAST est pré-remplie depuis `_provided` (pas `_teacher`).
4. Modifier `_teacher` (modèle correction) → l'élève ne voit pas ces modifications.
5. Vérifier la nav `consignes` sur step1/step2/step3/step4_provided/step9_provided.
6. Vérifier la nav `correction` sur step4_teacher/step5_teacher/.../step9_teacher.
7. Désinstaller le plugin → toutes les tables purgées.
