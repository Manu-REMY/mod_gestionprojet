# Release Notes — Plugin Gestion de Projet v2.10.0

**Date** : 2026-05-06
**Compatibility** : Moodle 5.0+ / PHP 8.1+

## Highlights

The v2.9.0 "consigne pattern" introduced for step 4 (CDCF) is now extended to step 5 (Fiche d'essai) and step 9 (Diagramme FAST):

- **Texte de présentation aux élèves** : un éditeur Atto sur la page consigne enseignant, affiché en lecture seule au-dessus de l'activité élève. Modifications enseignant propagées en temps réel.
- **Bouton « Réinitialiser le formulaire »** côté élève : remplace le travail de l'élève par la dernière version de la consigne. Désactivé après soumission.
- **Évaluation IA** : la consigne est injectée dans le prompt, et l'IA force une note de 0/20 si l'élève soumet une copie identique à la consigne.

## What's new for teachers

- Sur step 5 et step 9 en mode `provided` : un éditeur de texte riche tout en haut pour rédiger une consigne pédagogique destinée aux élèves.
- Aucun changement sur les modèles de correction (`*_teacher`) ni sur les instructions IA.

## What's new for students

- Encadré bleu « Consignes de l'enseignant » au-dessus du formulaire / diagramme.
- Bouton « Réinitialiser le formulaire » à côté de Soumettre. Confirmation par fenêtre modale.

## Database migration

Une seule étape DB (`2026050900`) ajoute le champ `intro_text` sur `gestionprojet_essai_provided` et `gestionprojet_fast_provided`. Aucun backfill, le champ est optionnel.

## Known limitations

- Step 7 (Expression du besoin) : le mode `provided` n'existe pas encore. À traiter dans une prochaine release.

## Upgrade path

Standard : Moodle Admin → Notifications → valider l'upgrade DB. Aucune action manuelle requise.
