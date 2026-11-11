# Niveau validé à la soumission, date limite par classe, corrections espacées (demandes 39 et 40, D-040)

Approuvé par Emmanuel le 11/11/2026 (conversation, 16h20). Version visée : 2.14.0.

## Intention

Dans l'étape 4 (cahier des charges), l'élève demande des retours pour s'améliorer : retour formatif et bandeau « Niveau atteint »
(demande 37). Il n'a pas à demander la validation d'un niveau : **le niveau se valide à la soumission**, comme un quiz. Une copie non
soumise à sa date limite est soumise automatiquement, et son niveau validé de même. Emmanuel ne relit rien : la note et le niveau de
l'IA s'appliquent seuls (`ai_auto_apply = 1` posé sur les activités par l'agent des activités).

Les classes n'ont pas la même date limite (Projet PIEGE : 3A le 15/12, 3C et 3D le 17/12, 3B le 05/01) ; le pilotage les écrira.

## Objectifs

1. **Niveau validé à l'application de la correction (39).** Quand une évaluation d'étape 4 (non formative, hors mode paliers) est
   appliquée, le serveur calcule le niveau avec `palier::level_reached()` (règle du bandeau, seuil `step4_palier_seuil`) et met
   `gestionprojet_palier` en accord : une ligne par niveau acquis (F, puis S, puis TS), avec les membres de la copie à ce moment ;
   les niveaux qui ne sont plus atteints passent à `retire = 1` (jamais effacés). Une nouvelle correction ou une note retouchée par
   le professeur (application d'une évaluation) réaligne. Le bouton existant « retirer un niveau » reste au professeur.
2. **Affichage élève.** Copie soumise avec un niveau enregistré : le bandeau dit « ✓ Niveau SAT validé ». Plus aucun bouton
   « Faire valider le niveau » quand le mode paliers est désactivé (déjà le cas) ; rien ne change pour une activité qui garde le mode
   paliers.
3. **Date limite par groupe (40).** Nouvelle table : une date limite par (activité, étape, groupe Moodle), écrite par le pilotage.
   Date effective d'une copie : la plus tardive parmi les dates des groupes concernés — le groupe de travail de la copie et les groupes
   de ses membres (classes 3A…) ; sinon la date de l'étape (`deadline_date` du modèle enseignant). Elle sert partout où la date limite
   sert aujourd'hui : tâche `auto_submit_deadline`, soumission à l'ouverture de la page (`gestionprojet_check_deadline_submissions`),
   affichage de l'échéance à l'élève, fin des retours formatifs, refus d'une demande de palier.
4. **Corrections espacées.** Toute évaluation **non formative** (soumission manuelle ou automatique, re-correction) reçoit un
   créneau : une tâche toutes les N secondes (réglage du site, 6 s par défaut, soit 10 par minute), via `set_next_run_time` de la
   tâche adhoc. Les retours formatifs partent tout de suite. Le réglage se change sans nouvelle version.

## Non-objectifs

- Écran de saisie des dates par groupe (le pilotage écrit la table ; affichage en lecture seule possible plus tard).
- Maîtrise de la langue (demande 32, juste après : elle entrera dans le calcul du niveau validé).
- Lecture du niveau par la vie de classe (agent des activités).

## Anti-objectifs

- Un niveau validé qui contredit la note appliquée.
- Une copie soumise automatiquement avant la date de sa classe.
- Un pic de corrections qui dépasse la limite d'Albert et laisse des copies en échec.
- Abaisser un niveau en l'effaçant : on marque `retire = 1`, l'historique reste.

## Contraintes

- Règles du dépôt (CLAUDE.md) : table dans `install.xml` + `upgrade.php` + `delete_instance` + fournisseur de confidentialité si
  données personnelles (la table des dates ne contient que des groupes : pas de donnée personnelle), chaînes en/fr, pas de JS en ligne.
- D-014 : branche, tests écrits d'abord, préprod, relecture indépendante, production sur demande.
- Le lock par copie (`gestionprojet_palier_lock`) protège l'écriture des niveaux.

## Choix laissés au constructeur (marqués comme choix de l'agent)

- Nom et forme de la table (`gestionprojet_deadline_group` : gestionprojetid, step, groupid, deadline_date, timemodified).
- Stockage du prochain créneau (config du plugin, mise à jour atomique ou à peu près : un léger chevauchement est sans gravité).
- Libellés exacts.
