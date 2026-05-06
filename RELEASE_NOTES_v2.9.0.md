# Release Notes — v2.9.0 (2026-05-06)

## Refonte de la consigne CDCF (Step 4)

### Problème résolu

Lorsque l'enseignant modifiait la consigne CDCF d'une activité après que les élèves aient ouvert au moins une fois la page, **les modifications n'étaient pas propagées** : chaque élève conservait une copie figée de la consigne d'origine. Ce comportement, bien que volontaire (préserver le travail élève), n'offrait aucun mécanisme pour récupérer une consigne mise à jour.

### Solution

1. **Nouveau champ « Texte de présentation aux élèves »** sur la page consigne enseignant (mode=provided), avec éditeur Atto. Affiché en lecture seule en haut de l'activité élève.
2. **Lecture en temps réel** : modifier ce texte côté enseignant se reflète immédiatement chez tous les élèves au prochain reload (pas de copie).
3. **Bouton « Réinitialiser le formulaire »** côté élève : permet à l'élève de remplacer son brouillon par la dernière version de la consigne, après confirmation modale. Désactivé si le formulaire est soumis (l'enseignant peut faire un revert pour le réactiver).
4. **IA contextualisée** : le texte d'intro enseignant est désormais injecté dans le prompt d'évaluation IA, pour une évaluation mieux contextualisée.

### Compatibilité

- Aucune action requise sur les activités existantes : le nouveau champ est vide par défaut.
- Aucune migration des records élèves : les drafts existants sont préservés tels quels.
- Le mécanisme de pré-remplissage initial (« seed ») existant reste en place pour les nouveaux élèves.

### Mise à jour DB

- Nouvelle colonne `intro_text` (TEXT, nullable) sur `gestionprojet_cdcf_provided`. Étape d'upgrade automatique à `2026050800`.

### Périmètre futur

Le pattern (intro + bouton Reset) sera étendu aux étapes :
- Step 5 (Essai) — `essai_provided`
- Step 9 (FAST) — `fast_provided`
- Step 7 (Expression du besoin) — création du mode provided à venir

La classe `\mod_gestionprojet\reset_helper` est conçue pour cette extension.

---

## Validation preprod — fixes additionnels

La validation manuelle en preprod a révélé plusieurs bugs (certains pré-existants, masqués jusque-là). Ils sont fixés dans la même release :

### Bugs corrigés

| Bug | Cause | Fix |
|---|---|---|
| Modifications de la consigne enseignant non sauvegardées | Webservice `mod_gestionprojet_autosave` ne gérait pas `mode='provided'` (pré-existant) | Ajout du handler `provided` pour steps 4 / 5 / 9 |
| Suppression de FS / critère / interacteur ignorée par l'autosave | `dataField.value` modifié programmatiquement → pas d'event `input` (pré-existant) | Dispatch d'un event `input` après chaque update |
| Erreur de sauvegarde sur le modèle de correction (mode=teacher) | Dates ISO envoyées dans des colonnes `bigint` (pré-existant) | Coerce serveur-side ISO → timestamp |
| Tooltip absent sur le bouton Reset désactivé | HTML natif n'affiche pas le `title` sur `disabled` | Wrapper `<span>` portant le tooltip |
| Bouton Reset en désaccord avec la charte | Bootstrap par défaut au lieu de la pilule projet | CSS aligné sur `btn-submit-large` |
| Modale Reset hand-rolled | Pattern divergent du reste du plugin | Refacto vers `core/modal_factory` |
| IA notait positivement un travail vide | Production élève contient la consigne pré-remplie ; IA ne distinguait pas | Injection de la consigne dans le prompt + détection serveur de « zéro modification » → 0/20 forcé |

### Bugs pré-existants tracké pour traitement séparé

- **Re-render CDCF rompt le focus / oblige double-clic** : `cdcf.js` reconstruit tout l'arbre DOM à chaque modification. À refactoriser dans une PR dédiée.
- **Bouton « Revert to draft » dans grading.php** retourne `error/invalidparams` : le payload côté client manque `groupid` ou `userid`. Contournement : revert direct en DB. À corriger dans une PR dédiée.
