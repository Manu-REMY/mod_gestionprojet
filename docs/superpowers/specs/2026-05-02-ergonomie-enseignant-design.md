# Améliorations ergonomiques de l'interface enseignant

**Date** : 2026-05-02
**Auteur** : Emmanuel REMY (avec Claude)
**Statut** : Design — en attente de validation
**Cible version** : 2.2.0

## Contexte

Trois frictions ergonomiques ont été identifiées par l'auteur en utilisant le plugin :

1. L'enseignant ne peut pas fournir un CDCF (étape 4) clé-en-main ; il peut seulement définir un modèle de correction. Toute classe doit donc passer par la production élève du CDCF, même quand le scénario pédagogique veut partir d'un CDCF imposé.
2. Les modèles de correction sont accessibles uniquement via un bouton menant à une page séparée (`correction_models.php`), ce qui ajoute un clic et fragmente la vue d'ensemble.
3. La barre de navigation directe entre phases (onglets) n'existe que sur l'écran de correction (`grading.php`). Sur les pages enseignant (1, 2, 3) et les modèles de correction (4-8), la navigation passe par un retour à la home.

## Objectif

Remettre toutes les actions enseignantes à portée de main depuis la home et permettre la circulation directe entre les 8 phases sur toutes les pages de configuration enseignant.

## Hors périmètre

- Le mode "fourni par l'enseignant" est introduit **uniquement pour le CDCF (étape 4)**. Aucune extension aux étapes 5, 6, 7, 8.
- Pas de refonte du formulaire d'activité (`mod_form.php`) au-delà du minimum requis (passage d'un checkbox à un select pour `enable_step4`).
- Pas de modification de l'écran `grading.php` ni de son template `grading_navigation.mustache` (le composant sera extrait sans impacter l'usage existant).
- Pas de changement côté élève hors page `step4.php` (mode lecture seule en mode fourni).

## Friction 1 — Mode "CDCF fourni par l'enseignant"

### Modèle de données

Le champ `enable_step4` change de sémantique :

| Valeur | Ancienne sémantique | Nouvelle sémantique |
|---|---|---|
| `0` | désactivé | désactivé (inchangé) |
| `1` | activé | activé en mode élève (l'élève produit son CDCF) |
| `2` | — (n'existait pas) | activé en mode fourni (l'enseignant fournit le CDCF, l'élève consulte) |

- Type SQL actuel : `int(1) NOTNULL DEFAULT 1` dans `db/install.xml`. Capacité suffisante pour stocker 0..2 — **aucune modification de schéma requise**.
- Migration `db/upgrade.php` : aucune migration de données nécessaire (les valeurs existantes 0 et 1 conservent leur sens). Seul le bump de version est ajouté.

Les autres `enable_stepN` (1, 2, 3, 5, 6, 7, 8) restent en booléen.

### Stockage du contenu fourni

Réutilisation de la table existante `gestionprojet_cdcf_teacher`. Cette table contient déjà la structure complète d'un CDCF (produit, fonctions, contraintes, etc.) et un champ `ai_instructions` pour la correction IA. En mode fourni :

- Les champs métier (produit, fonctions, contraintes, ...) jouent un double rôle : contenu affiché à l'élève **et** référence pour l'IA lors de la correction des étapes en aval.
- Le champ `ai_instructions` reste réservé à l'IA et n'est jamais affiché à l'élève (filtrage côté template comme aujourd'hui).
- Aucune nouvelle table, aucun nouveau champ.

### Comportement

**Page `step4.php` (vue élève)** :
- Si `enable_step4 == 2` : afficher le contenu de `gestionprojet_cdcf_teacher` en lecture seule, badge "Fourni par l'enseignant", masquer le bouton "Soumettre", ne pas afficher de zone de note.
- Si `enable_step4 == 1` : comportement actuel inchangé.
- Si `enable_step4 == 0` : la page n'est pas accessible (comportement actuel).

**Page `step4_teacher.php`** :
- Si `enable_step4 == 2` : un encart d'information (div stylé via `styles.css`, classe `path-mod-gestionprojet provided-mode-notice`, pas de balise `<style>` inline) en haut du formulaire, texte du type *"Mode fourni : ce contenu sera visible par les élèves en lecture seule. Le champ Instructions IA n'est pas visible aux élèves."*. Le formulaire reste éditable.
- Si `enable_step4 == 1` : encart actuel ("modèle de correction") inchangé.

**Page `home.php` (vue enseignant)** :
- En mode fourni, la carte step 4 du bloc "Modèles de correction" affiche un badge "Fourni" au lieu de l'indicateur de complétion habituel.

**Page `home.php` (vue élève)** :
- En mode fourni, la carte step 4 affiche le badge "Fourni par l'enseignant" et reste cliquable (ouvre la page en lecture seule).

**Logique de notation et d'évaluation IA** :
- En mode fourni, step 4 ne génère aucune note (pas de soumission élève).
- L'évaluation IA des étapes 5, 6, 7, 8 utilise déjà le contenu de `gestionprojet_cdcf_teacher` comme référence ; aucun changement de ce côté.

**Comptage de complétion (dashboard)** :
- En mode fourni, la carte step 4 du bloc "Modèles de correction" est considérée comme complète dès que le champ `produit` est renseigné (et non plus `ai_instructions`, qui devient optionnel en mode fourni). Le compteur `modelscomplete/modelstotal` du dashboard reflète cette règle.

### Configuration (mod_form)

Dans `mod_form.php`, le `advcheckbox` pour `enable_step4` devient un `select` à 3 valeurs :

- `0` : Désactivé
- `1` : Production par les élèves
- `2` : Fourni par l'enseignant

Les libellés sont placés dans `lang/en/gestionprojet.php` et `lang/fr/gestionprojet.php`.

## Friction 2 — Refonte de la home enseignant en Gantt

> **Note** : ce design remplace la version "sections empilées" initialement validée. Il découle d'un retour utilisateur après le déploiement de la Phase 1 et étend significativement le périmètre — notamment la migration de la configuration des étapes actives depuis `mod_form.php` vers la home (édition live AJAX).

### Layout retenu : tableau Gantt 3 lignes × 8 colonnes

La home enseignant affiche un tableau type Gantt en lieu et place des cartes existantes :

- **8 colonnes** = les 8 phases en ordre pédagogique `[1, 3, 2, 7, 4, 5, 8, 6]`. L'en-tête de chaque colonne contient le numéro de step, le nom court, et (selon la ligne, voir ci-dessous) une case à cocher.
- **3 lignes**, chacune représentant un rôle dans le projet :
  1. **Documents enseignant** (palette indigo) — cellules sur les colonnes 1, 3, 2 uniquement
  2. **Modèles de correction** (palette ambre) — cellules sur les colonnes 7, 4, 5, 8, 6 uniquement
  3. **Activités élèves** (palette émeraude) — cellules sur les colonnes 7, 4, 5, 8, 6 uniquement

Les cellules vides (intersection ligne × colonne sans rôle) restent transparentes pour préserver l'alignement visuel.

### Cases à cocher : sémantique précise

- **Ligne 1 (Documents enseignant)** : chaque cellule contient sa propre case à cocher, indépendante (correspond à `enable_step1`, `enable_step2`, `enable_step3`).
- **Lignes 2 + 3 (Modèles + Activités élèves)** : pour chacune des 5 colonnes 4-8, **une seule case à cocher** est rendue, placée dans la cellule de la ligne 2 (Modèle de correction). Cette case pilote `enable_stepN` et détermine simultanément l'activation du modèle de correction et de l'activité élève. La cellule de la ligne 3 affiche une discrète flèche montante (↑) pour signaler le lien.

### États visuels d'une cellule

- **Active + non remplie** : palette de la ligne, badge "À configurer".
- **Active + remplie** (selon le contexte : modèle = `ai_instructions` non vide ; document = champ principal `intitule`/`projectname`/`aqui` non vide) : palette de la ligne, badge "Configuré".
- **Active + step 4 en mode fourni** : palette spéciale, badge "Fourni" (cf. Friction 1).
- **Désactivée** (case décochée) : cellule grisée (opacité 0.4), badge "Désactivé". La case à cocher reste visible et active pour permettre de réactiver.
- **Cellule vide** (intersection sans rôle) : transparente, sans bordure, sans contenu.

### Activation/désactivation live (AJAX)

Quand l'enseignant coche/décoche une case sur la home :
- Un appel AJAX vers le nouveau endpoint `ajax/toggle_step.php` met à jour `enable_stepN` en base immédiatement.
- L'UI met à jour les cellules concernées (greyed/dégrayed) sans rechargement de page.
- Toast de confirmation/erreur (composant `core/notification` Moodle ou toast existant `mod_gestionprojet/toast`).

#### Endpoint `ajax/toggle_step.php`

- Accepte `cmid` (PARAM_INT), `stepnum` (PARAM_INT 1..8), `enabled` (PARAM_INT 0..2 ; valeur 2 réservée à step 4 mode fourni — non utilisée par les checkboxes Gantt).
- `require_login`, `require_sesskey`, `require_capability('mod/gestionprojet:configureteacherpages', $context)`.
- Met à jour le champ `enable_stepN` de la table `gestionprojet`.
- Retour JSON `{ success: bool, message?: string }`.

#### Module AMD `mod_gestionprojet/gantt`

- Initialisé sur la home enseignant.
- Liaison `change` sur les inputs `[type=checkbox][data-stepnum]`.
- Pour les checkboxes des colonnes 4-8 (cellules ligne 2), met à jour visuellement les cellules de ligne 2 ET ligne 3.
- Pour les checkboxes des colonnes 1, 2, 3 (ligne 1), met à jour uniquement la cellule de ligne 1.

### Bandeau "À corriger" (au-dessus du Gantt)

Un petit bandeau d'information condensé reste utile :
- Compteur global "X soumissions à corriger" (lien vers la première étape avec ungraded > 0)
- Indicateur d'avancement de la configuration (ex. "5/8 phases configurées")

Ce bandeau remplace la zone "Dashboard" actuelle de la home (config + complétion modèles + à corriger). Les compteurs détaillés par étape (soumis/notés/à noter) sont **déplacés dans les cellules de la ligne 3** (Activités élèves) — chaque cellule active affiche son ratio "X/Y soumis".

### Suppression de la section "Étapes actives" dans `mod_form.php`

La section `header('activesteps', ...)` et la boucle de checkboxes `enable_stepN` est entièrement retirée de `mod_form.php`. La home devient la seule source de vérité pour activer/désactiver les étapes. Les valeurs par défaut à la création de l'instance restent gérées par le `default` du schéma SQL (`enable_stepN = 1` pour 1-6, `0` pour 7-8 ; à confirmer/aligner).

> Conséquence : à la création d'une nouvelle activité, l'enseignant arrive sur la home avec un Gantt initial reflétant les valeurs par défaut, et peut tout configurer en place.

### Cliquabilité des cellules

- **Cliquer sur la zone de contenu** (en dehors de la case à cocher) : ouvre la page d'édition correspondante :
  - Ligne 1, cellule step N : `view.php?id=X&step=N`
  - Ligne 2, cellule step N : `view.php?id=X&step=N&mode=teacher` (modèle de correction)
  - Ligne 3, cellule step N : `grading.php?id=X&step=N` (interface de correction)
- **Cliquer sur la case à cocher** : toggle l'activation, sans suivre de lien.

### Sort de `correction_models.php` (inchangé)

La page reste transformée en redirect silencieux vers la home, comme dans la version initiale du spec :

```php
case 'correctionmodels':
    redirect(new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]));
```

Suppressions associées (inchangées) :
- Fichier `pages/correction_models.php`
- Template `templates/correction_models.mustache`
- Méthode `render_correction_models()` dans `classes/output/renderer.php`

### Vue élève (inchangée)

Le Gantt s'applique uniquement à la **vue enseignant** de la home. La vue élève conserve son layout actuel (consultation steps + student steps + grades). Aucune modification du côté élève au titre de Friction 2.

## Friction 3 — Barre de navigation directe à 8 phases

### Composant réutilisable

Création d'un nouveau template `templates/step_tabs.mustache` extrait de `grading_navigation.mustache`. Le nouveau composant ne contient **que les onglets** (pas le bouton retour, pas la nav prev/next, pas le sélecteur de groupe — qui restent spécifiques à `grading.php`).

Variables de contexte :
```
{
  "tabs": [
    {
      "stepnum": 1,
      "icon": "<svg>...</svg>",
      "name": "Description",
      "isactive": true,
      "url": "view.php?id=123&step=1"
    },
    ...
  ]
}
```

L'ordre des onglets reflète l'ordre pédagogique du projet : `[1, 3, 2, 7, 4, 5, 8, 6]` (déjà utilisé dans `mod_form.php`).

### Branchement

| Page | URL des tabs | Mode actif | Affichage en mode désactivé |
|---|---|---|---|
| `step1.php`, `step2.php`, `step3.php` (vue enseignant) | `view.php?id=X&step=N` | onglet de l'étape courante | onglet désactivé visuellement (pas de lien) |
| `step4_teacher.php` à `step8_teacher.php` | `view.php?id=X&step=N&mode=teacher` | onglet de l'étape courante | onglet désactivé visuellement |
| `grading.php` | `grading.php?id=X&step=N` (inchangé) | inchangé | inchangé |

Les 8 onglets sont **toujours affichés**, même quand une étape est désactivée (cohérence visuelle), mais l'onglet est rendu non cliquable et grisé. La navigation directe couvre ainsi systématiquement les 8 phases.

### Refactor de `grading_navigation.mustache`

`grading_navigation.mustache` continue d'exister mais inclut désormais `{{> mod_gestionprojet/step_tabs}}` plutôt que de définir les onglets en interne. Cela garantit la cohérence visuelle et un seul point de maintenance pour les onglets.

### Routage des modes

Côté `view.php`, le routeur distingue déjà les modes via `?mode=teacher`. Aucun changement de routeur nécessaire — les URLs des onglets sont construites différemment selon le contexte (vue enseignant pure : sans `mode`; modèle de correction : avec `mode=teacher`).

## Internationalisation

Nouvelles chaînes à ajouter dans `lang/en/gestionprojet.php` **et** `lang/fr/gestionprojet.php` :

**Friction 1 — mode CDCF fourni**
- `step4_mode_disabled` — "Désactivé"
- `step4_mode_student` — "Production par les élèves"
- `step4_mode_provided` — "Fourni par l'enseignant"
- `step4_provided_badge` — "Fourni par l'enseignant"
- `step4_provided_notice_teacher` — Encart sur `step4_teacher.php` en mode fourni
- `step4_provided_notice_student` — Encart sur `step4.php` en mode fourni

**Friction 2 — Gantt home**
- `gantt_row_teacher_docs` — "Documents enseignant"
- `gantt_row_correction_models` — "Modèles de correction"
- `gantt_row_student_activities` — "Activités élèves"
- `gantt_cell_status_done` — "Configuré"
- `gantt_cell_status_todo` — "À configurer"
- `gantt_cell_status_disabled` — "Désactivé"
- `gantt_toggle_success` — "Étape mise à jour"
- `gantt_toggle_error` — "Erreur lors de la mise à jour"
- `gantt_progress_summary` — "{$a->configured}/{$a->total} phases configurées"
- `gantt_ungraded_summary` — "{$a} soumissions à corriger"
- `gantt_ungraded_summary_zero` — "Aucune soumission en attente"

(Les autres libellés réutilisent les chaînes existantes.)

## Conformité plugin Moodle

- Pas de balise `<style>` ou `<script>` inline (CSS dans `styles.css`, JS dans modules AMD).
- Les commentaires de code restent en anglais.
- Les chaînes utilisateur passent par les fichiers de langue.
- L'header GPL deux paragraphes est requis sur tout nouveau fichier PHP (notamment `ajax/toggle_step.php` et `amd/src/gantt.js`).
- Le nouveau endpoint `ajax/toggle_step.php` doit appliquer le triptyque sécurité : `require_login`, `require_sesskey`, `require_capability('mod/gestionprojet:configureteacherpages', $context)`. Inputs via `required_param` / `optional_param` — jamais `$_GET`/`$_POST`.
- Si une nouvelle table était introduite (ce n'est pas le cas), `gestionprojet_delete_instance()` devrait être mise à jour.
- Bump version : `version.php` → `2.2.0` avec un numéro `2026YYYYMM00` ; pas de changement de schéma → upgrade step minimal (juste la version).
- Nouveau module AMD à compiler via `grunt amd` avant build du ZIP final.

## Critères de succès

**Friction 1**
- L'enseignant peut configurer le CDCF en mode "fourni" via les paramètres de l'activité ; le contenu saisi sur `step4_teacher.php` apparaît en lecture seule sur `step4.php` côté élève, sans bouton de soumission ni note.

**Friction 2 — Gantt home**
- La home enseignant affiche le tableau Gantt 3 lignes × 8 colonnes avec les bons croisements remplis et les autres en cellules vides transparentes.
- L'enseignant active/désactive une étape **directement depuis la home** via case à cocher ; la mise à jour est immédiate (AJAX) sans rechargement.
- Cocher/décocher la case d'une cellule de ligne 1 (Documents enseignant) modifie uniquement la cellule de la ligne 1 correspondante.
- Cocher/décocher la case d'une cellule de ligne 2 (Modèles de correction) grise/dégrise simultanément les cellules de la ligne 2 ET de la ligne 3 de la même colonne.
- Aucune section "Étapes actives" n'est plus présente dans le formulaire de l'activité (`mod_form.php`).
- Le bandeau de tête de la home affiche le compteur "X/Y phases configurées" et "X soumissions à corriger".

**Friction 3**
- Sur n'importe quelle page enseignant (steps 1-3 ou modèles 4-8), la barre d'onglets permet de basculer en un clic vers une autre phase, et un onglet "Accueil" en début de barre ramène à la home.

**Non-régression**
- Aucune régression sur la page `grading.php` ni sur la vue élève hors `step4.php`.
- L'ouverture de l'ancienne URL `view.php?id=X&page=correctionmodels` redirige vers la home sans erreur.
- À la création d'une nouvelle activité, l'enseignant arrive sur la home Gantt avec les valeurs par défaut (`enable_step1..6 = 1`, `enable_step7..8 = 0`).

## Plan de validation manuelle

1. **Friction 1** : créer une activité avec `enable_step4 = 2` (sélectionnable via la case Gantt — voir ci-dessous) ; remplir le CDCF côté enseignant ; vérifier l'affichage côté élève en lecture seule sans bouton soumettre.
2. Repasser à `enable_step4 = 1` ; vérifier que le contenu redevient éditable et notable côté élève.
3. **Friction 2** : sur la home enseignant, vérifier la disposition Gantt 3×8 et le bandeau de tête.
4. Décocher une cellule de ligne 1 (ex. step 2) → la cellule grise immédiatement ; recharger la page → la cellule reste grisée.
5. Décocher une case de ligne 2 (ex. step 4) → cellules de ligne 2 ET ligne 3 colonne 4 grises simultanément.
6. Cliquer sur le contenu (hors checkbox) d'une cellule de ligne 2 → ouvre la page modèle de correction en mode teacher. Idem ligne 3 → ouvre `grading.php`.
7. Ouvrir les paramètres de l'activité → la section "Étapes actives" est absente.
8. **Friction 3** : sur les pages 1, 2, 3 (enseignant) et 4-8 (mode teacher), vérifier la barre d'onglets, la navigation directe et le bouton Accueil.
9. Ouvrir `view.php?id=X&page=correctionmodels` → redirige vers la home.
10. **Non-régression** : `grading.php` intact (onglets, prev/next, sélecteur de groupe, bouton Relancer évaluations IA).
