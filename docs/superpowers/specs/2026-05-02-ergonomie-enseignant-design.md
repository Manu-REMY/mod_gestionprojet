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

## Friction 2 — Modèles de correction sur la home (Option A)

### Layout retenu : sections empilées

La home enseignant affiche désormais (de haut en bas) :

1. **Documents enseignant** — 3 cartes (steps 1, 3, 2) — palette indigo
2. **Modèles de correction** — 5 cartes (steps 7, 4, 5, 8, 6) — palette ambre
3. **Dashboard** — bloc actuel (config + complétion modèles + à corriger + soumissions par étape) — palette émeraude
4. **Corrections** — liens existants vers `grading.php` par étape (inchangé fonctionnellement)

Chaque carte d'un modèle de correction contient :
- L'icône de l'étape
- Le titre et la description
- Un badge de complétion : "Configuré" si `ai_instructions` est rempli, "À configurer" sinon
- Un badge spécifique "Fourni" si step 4 et `enable_step4 == 2`
- Lien vers `view.php?id=X&step=N&mode=teacher`

Les étapes désactivées (`enable_stepN == 0`) ne sont pas affichées.

### Suppression du bouton "Modèles de correction"

Le bouton qui pointait vers `correction_models.php` est retiré de `home.mustache` (vue enseignant).

### Sort de `correction_models.php`

La page est conservée mais transformée en redirection silencieuse vers la home (Option B des choix présentés). Concrètement, dans `view.php`, le routeur :

```
case 'correctionmodels':
    redirect(new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]));
```

Suppressions associées (vérifiées : usages limités à la page abandonnée) :
- Fichier `pages/correction_models.php`
- Template `templates/correction_models.mustache`
- Méthode `render_correction_models()` dans `classes/output/renderer.php`

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

- `step4_mode_disabled` — "Désactivé"
- `step4_mode_student` — "Production par les élèves"
- `step4_mode_provided` — "Fourni par l'enseignant"
- `step4_provided_badge` — "Fourni par l'enseignant"
- `step4_provided_notice_teacher` — Encart sur `step4_teacher.php` en mode fourni
- `step4_provided_notice_student` — Encart sur `step4.php` en mode fourni

(Les autres libellés réutilisent les chaînes existantes.)

## Conformité plugin Moodle

- Pas de balise `<style>` ou `<script>` inline (CSS dans `styles.css`, JS dans modules AMD si besoin).
- Les commentaires de code restent en anglais.
- Les chaînes utilisateur passent par les fichiers de langue.
- L'header GPL deux paragraphes est requis sur tout nouveau fichier PHP.
- Si une nouvelle table était introduite (ce n'est pas le cas), `gestionprojet_delete_instance()` devrait être mise à jour.
- Bump version : `version.php` → `2.2.0` avec un numéro `2026YYYYMM00` ; pas de changement de schéma → upgrade step minimal (juste la version).

## Critères de succès

- L'enseignant peut configurer le CDCF en mode "fourni" via les paramètres de l'activité ; le contenu saisi sur `step4_teacher.php` apparaît en lecture seule sur `step4.php` côté élève, sans bouton de soumission ni note.
- La home enseignant montre simultanément les documents enseignant (3 cartes), les modèles de correction (5 cartes) et le dashboard, sans clic intermédiaire.
- Sur n'importe quelle page enseignant (steps 1-3 ou modèles 4-8), la barre d'onglets permet de basculer en un clic vers une autre phase.
- Aucune régression sur la page `grading.php` ni sur la vue élève hors `step4.php`.
- L'ouverture de l'ancienne URL `view.php?id=X&page=correctionmodels` redirige vers la home sans erreur.

## Plan de validation manuelle

1. Créer une activité avec `enable_step4 = 2` ; remplir le CDCF côté enseignant ; vérifier l'affichage côté élève.
2. Repasser la même activité en `enable_step4 = 1` ; vérifier que le contenu reste éditable côté élève et que la note est de nouveau possible.
3. Sur la home enseignant, vérifier la présence des deux blocs et l'absence du bouton "Modèles de correction".
4. Sur les pages 1, 2, 3 (enseignant) et 4-8 (mode teacher), vérifier la barre d'onglets et la navigation directe.
5. Ouvrir `view.php?id=X&page=correctionmodels` → doit rediriger vers la home.
6. Vérifier que `grading.php` est intact (onglets, prev/next, sélecteur de groupe).
