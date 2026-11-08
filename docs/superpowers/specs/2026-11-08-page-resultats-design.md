# Page « Résultats » de la classe — conception

Version cible : 2.13.0. Rédigé le 8 novembre 2026, d'après la demande d'Emmanuel : « Ai-je un tableau de bord pour ce
plugin pour voir où en sont tous les travaux des élèves d'une classe ? Un peu à la manière de la page résultats pour
un quiz. » Proposition acceptée, avec un ajout pour le dépôt de groupe.

## 1. Pourquoi

Aujourd'hui, l'enseignant a un tableau de bord par étape (chiffres et synthèse IA, en bas de chaque modèle de
correction) et une page de correction qui montre une copie à la fois. Il n'a pas de vue d'ensemble : qui a commencé,
qui a soumis, quelle note, à quelle étape.

Succès : en une page, l'enseignant voit l'avancement de toute la classe sur toutes les étapes, ouvre une copie d'un
clic et exporte le tableau.

## 2. Objectifs, non-objectifs, anti-objectifs

**Objectifs**

1. Une page « Résultats » par activité, réservée aux enseignants (`mod/gestionprojet:viewallsubmissions`), accessible
   depuis le menu de l'activité (comme « Résultats » d'un test).
2. **Travail individuel** : une ligne par élève ; colonnes : élève, date de début, dernière activité, une colonne par
   étape élève active, moyenne.
3. **Dépôt de groupe** (demande d'Emmanuel) : les élèves sont classés par groupe. Première colonne : le groupe
   (cellule fusionnée sur ses élèves) ; deuxième : les élèves du groupe, un par ligne ; puis la date de début, la
   dernière activité, les étapes et la moyenne du groupe (cellules fusionnées, puisque la copie est celle du groupe).
   Les élèves sans groupe sont listés à la fin sous « Sans groupe ».
4. Chaque case d'étape : l'état avec un symbole et un mot (« — Pas commencé », « ✎ Brouillon »,
   « … Correction en cours », « ✓ Soumis »), la note sur 20 (ou les paliers F/S/TS validés à l'étape 4 en mode
   paliers), et les retours formatifs utilisés (« Retours 2/3 »). Un clic ouvre la copie dans la page de correction.
5. Une ligne de synthèse par étape : copies soumises sur le total, moyenne des notes.
6. Filtre par groupe (sélecteur de groupes de Moodle) et export (CSV, Excel, ODS) du même tableau.

**Non-objectifs**

- Pas de saisie de note ni d'action groupée sur cette page (la page de correction le fait déjà).
- Pas de graphique.
- Le retour écrit par l'enseignant n'est pas affiché (décision du 8 novembre : pas une priorité).

**Anti-objectifs**

- Jamais un état distingué par la couleur seule : symbole + mot (règles de daltonisme d'Emmanuel).
- Jamais visible par un élève.

## 3. Choix du concepteur

- Ordre des étapes : celui de la page de correction (`gestionprojet_get_graded_steps_order()` : 7, 4, 9, 5, 8, 6),
  seulement les étapes élève activées.
- Date de début : la plus ancienne création de copie ; dernière activité : la plus récente modification.
- Moyenne : moyenne des notes sur 20 des étapes notées (les étapes en mode paliers n'y entrent pas).
- « Correction en cours » : copie soumise avec une évaluation IA de note en attente ou en cours.
- Les règles de calcul (état d'une case, regroupement, synthèse) sont dans une classe pure `results`, testée sans
  Moodle ; la page ne fait que lire la base et afficher.
