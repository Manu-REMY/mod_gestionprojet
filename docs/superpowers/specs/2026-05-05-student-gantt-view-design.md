# Vue Gantt élève — Design

**Date** : 2026-05-05
**Auteur** : Emmanuel REMY (avec Claude)
**Status** : approuvé pour planification

## Contexte

L'interface élève actuelle (`pages/home.php`, branche `^isteacher`) affiche les étapes sous forme de cartes empilées (3 cartes « Consultation » + 6 cartes « Travail »). L'interface enseignant a été récemment refondue en simili-diagramme de Gantt à 3 lignes × 8 colonnes (`templates/home_gantt.mustache`) — bien plus lisible et pédagogiquement plus parlante.

L'objectif est d'aligner la vue élève sur le même format Gantt afin que l'élève visualise la totalité du projet et le positionnement de l'étape qu'il est en train de réaliser dans la chronologie globale.

## Objectifs

1. Remplacer les cartes empilées par un tableau Gantt 2 lignes × 8 colonnes.
2. Conserver l'alignement visuel strict avec le Gantt enseignant (mêmes colonnes, mêmes couleurs par ligne).
3. Supprimer ce qui n'a pas de sens pour l'élève : ligne « modèles de correction », cases à cocher, flèche `↑`, résumé « configuration / à corriger ».
4. Faire apparaître les **consignes fournies** par l'enseignant (mode `provided` des steps 4 et 9) comme cellules consultables sur la ligne 1.

## Non-objectifs

- Aucun changement à la base de données.
- Aucun changement aux pages `step1.php`–`step9.php` (la navigation reste identique).
- Aucun changement aux endpoints AJAX.
- Aucun nouveau module JS (le Gantt élève est statique — pas d'interactivité).

## Layout cible

### Structure
- **2 lignes × 8 colonnes** + colonne d'en-tête de ligne (180px), même grille CSS que la vue enseignant.
- **Ordre des colonnes** identique à la vue enseignant : `1 · 3 · 2(fusion 7) · 4 · 9 · 5 · 8 · 6`.
- La colonne 3 reste fusionnée : la ligne 1 montre Step 2 (Expression du Besoin enseignant), la ligne 2 montre Step 7 (Expression du besoin élève).

### Ligne 1 — « Consultation » (couleur bleu/violet, `gp-cell-docs`)

| Colonne | Contenu de la cellule |
|---|---|
| Step 1 | Lien « Consulter » + statut « Complété » (vert) ou « En attente » (orange) selon `description.intitule` |
| Step 3 | Idem, basé sur `planning.projectname` |
| Step 2 | Idem, basé sur `besoin.aqui` |
| Step 4 — `step4_provided = 1` | Lien « Voir la consigne » + badge « Consigne fournie » + statut basé sur `cdcf_provided.produit` |
| Step 4 — `step4_provided = 0` | Cellule vide (`gp-cell-empty`) |
| Step 9 — `step9_provided = 1` | Lien « Voir la consigne » + badge « Consigne fournie » + statut basé sur `fast_provided.data_json` |
| Step 9 — `step9_provided = 0` | Cellule vide |
| Steps 5, 8, 6 | Cellule vide |
| Step désactivé (`enable_stepX = 0`) | Cellule visible mais grisée (`gp-cell-disabled`, `opacity: 0.45`), statut « Désactivée », lien inactif |

### Ligne 2 — « Mes activités » (couleur vert, `gp-cell-student`)

| Colonne | Contenu de la cellule |
|---|---|
| Step 1, 3, 2 | Cellule vide (pas d'activité élève sur les steps enseignant) |
| Step 7 (col fusionnée) | Lien « Travailler » + statut + note si présente |
| Step 4 | Lien « Travailler » + statut + note si présente |
| Step 9 | Lien « Travailler » + statut + note si présente |
| Step 5 | Idem |
| Step 8 | Idem |
| Step 6 | Idem |
| Step désactivé | Grisé, statut « Désactivée » |

**Statuts élève** :
- « À compléter » (orange, `gp-status-todo`) si la submission est vide ou non soumise.
- « Complété » (vert, `gp-status-done`) si la submission a son champ-clé rempli (mêmes règles que les cartes actuelles : `cdcf.produit`, `essai.objectif`, `rapport.besoins`, `besoin_eleve.aqui`, `carnet.tasks_data`, `fast.data_json` avec `fonctions` non vides).
- Note `X.X / 20` affichée en-dessous si `submission.grade !== null`.

**Règles complétion** : reprendre exactement la logique présente dans `pages/home.php` lignes 341-353 (clés `complete` du tableau `studentstepsraw`).

### Bandeau résumé (au-dessus de la grille)

Remplace `gp-gantt-summary` par une variante élève :
- Compteur principal : `X / Y étapes complétées` (vert si X = Y, orange sinon).
- Pas de compteur « à corriger » (concerne le travail enseignant).
- Y = nombre d'étapes élève actives (`enable_stepX != 0` parmi 4, 5, 6, 7, 8, 9).

### Aucun ajout (suppressions explicites)
- Pas de checkbox sur les cellules (la mécanique d'activation/désactivation reste réservée à l'enseignant via `mod_form` / Gantt enseignant).
- Pas de `gp-cell-link-arrow` (la flèche reliait modèle ↔ activité dans le Gantt enseignant).
- Pas de ligne « Modèles de correction ».

## Architecture

### Nouveaux fichiers
- `templates/home_gantt_student.mustache` — partial Gantt élève (équivalent simplifié de `home_gantt.mustache`).

### Fichiers modifiés
- `pages/home.php` — branche `else` (`!$isteacher`), à l'intérieur de `if ($teacherpagescomplete && $usergroup > 0)` quand `$groupinfo` existe : remplacer la construction des tableaux `consultationsteps`/`studentsteps` par la construction d'un tableau `gantt_student` (colonnes, rowdocs, rowwork, summary).
- `templates/home.mustache` — dans la branche `^isteacher`, sous `{{#hasusergroup}}`, remplacer le bloc `gestionprojet-cards` par l'inclusion du partial `home_gantt_student`. Conserver les blocs d'erreur (`nogrouperror`, `groupnotfounderror`, `^teacherpagescomplete`) et le bandeau « Vous travaillez en groupe ».
- `lang/en/gestionprojet.php` + `lang/fr/gestionprojet.php` — ajouter les clés :
  - `gantt_student_row_consult` — « Consultation »
  - `gantt_student_row_work` — « Mes activités »
  - `gantt_student_summary` — « {$a->done} / {$a->total} étapes complétées »
  - `gantt_student_status_pending` — « En attente »
  - `gantt_student_cell_consult` — « Consulter »
  - `gantt_student_cell_work` — « Travailler »
  - `gantt_student_cell_view_brief` — « Voir la consigne »
- `styles.css` — ajouts mineurs si besoin :
  - Variante `.gp-gantt-summary-student` (couleur informative neutre).
  - `.gp-cell-link` ajusté pour fonctionner sans le `padding-left: 24px` réservé à la checkbox absente (créer `.gp-cell-link-nocheck` ou ajuster via classe sur la cellule).

### Données contextuelles construites par `home.php`

```php
$templatecontext['gantt_student'] = [
    'columns' => [...],   // 8 colonnes, mêmes que le Gantt enseignant
    'rowconsult' => [...], // 8 cellules ligne 1
    'rowwork' => [...],    // 8 cellules ligne 2
    'summary' => [
        'done' => int,
        'total' => int,
        'allcomplete' => bool,
    ],
    'cmid' => $cm->id,
];
```

Chaque cellule respecte le contrat :
- Cellule vide : `['isfilled' => false]`
- Cellule pleine : `['isfilled' => true, 'isenabled' => bool, 'iscomplete' => bool, 'name' => string, 'url' => string, 'isprovided' => bool (ligne 1 only), 'hasgrade' => bool, 'gradeformatted' => string (ligne 2 only), 'status_label' => string]`

### Définition des colonnes (réutilisée)
Le tableau `$ganttcolumndefs` du bloc enseignant définit l'ordre des colonnes. **Refactor optionnel** : extraire ce tableau dans une fonction utilitaire `gestionprojet_get_gantt_column_defs()` dans `lib.php` afin que la branche enseignant et la branche élève partagent la même source de vérité.

→ Recommandé pour éviter la duplication, faible coût (5 lignes).

## Cas d'erreur / edge cases

- **Aucun groupe** (`nogrouperror`) : pas de Gantt, message d'erreur conservé tel quel.
- **Groupe introuvable** (`groupnotfounderror`) : idem.
- **Pages enseignant incomplètes** (`^teacherpagescomplete`) : pas de Gantt, alerte conservée.
- **Tous les steps élèves désactivés** : Gantt rendu, ligne 2 entièrement grisée — c'est cohérent avec la vue enseignant.
- **Step 4 ou 9 en mode `provided` mais consigne pas encore remplie par l'enseignant** : la cellule de la ligne 1 affiche « En attente » + lien « Voir la consigne » (la page `step4_provided.php`/`step9_provided.php` gère elle-même l'affichage du contenu vide).

## Plan de tests

- **Visuel** : vérifier en preprod sous compte élève que le Gantt rend bien sur Firefox + Chrome, en grand écran et sur tablette (overflow-x: auto déjà présent sur `.gp-gantt`).
- **États** :
  - Élève sans aucune submission → ligne 2 toute orange « À compléter ».
  - Élève avec quelques steps complétés → mix vert/orange.
  - Élève avec notes → afficher `X.X / 20`.
  - Step 4 désactivé (`enable_step4 = 0`) → cellules colonne 4 grisées sur les 2 lignes.
  - Step 4 actif + consigne fournie activée (`enable_step4 = 1` et `step4_provided = 1`) → ligne 1 col 4 montre « Voir la consigne », ligne 2 col 4 montre l'activité élève.
  - Step 4 actif sans consigne fournie (`enable_step4 = 1` et `step4_provided = 0`) → ligne 1 col 4 vide, ligne 2 col 4 affiche l'activité élève.
  - Step 4 « modèle fourni » (`enable_step4 = 2`) → indépendant de l'élève : ligne 1 col 4 dépend toujours de `step4_provided`, ligne 2 col 4 affiche l'activité.
  - Step 9 : mêmes combinaisons avec `enable_step9` et `step9_provided`.
- **Régression** : les liens de navigation des cellules pointent vers les bonnes URLs (`view.php?id=X&step=Y`).

## Compatibilité Moodle plugin checklist

- [x] Aucune `<style>` inline (CSS dans `styles.css`).
- [x] Aucun `<script>` inline (pas de JS ajouté).
- [x] Tous les libellés via `get_string()` (clés ajoutées en/fr).
- [x] Commentaires en anglais.
- [x] Pas de superglobales `$_GET` / `$_POST`.
- [x] `require_login()` déjà présent dans `view.php` (point d'entrée).
- [x] En-tête GPL préservée sur le nouveau template (Mustache n'a pas d'en-tête PHP, seulement les commentaires `{{! ... }}`).

## Déploiement

Conformément à la mémoire `feedback_deploy_preprod_at_dev_end.md` : SCP en preprod (`preprod.ent-occitanie.com`) en fin d'implémentation, avant de déclarer terminé.

Pas de bump version DB nécessaire (aucun changement de schéma). Un bump de `version.php` sera tout de même utile pour identifier la release (`2.x.0`).
