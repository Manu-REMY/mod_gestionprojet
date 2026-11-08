# Volet « Mon avancement » des pages élève — conception

Version cible : 2.12.0. Rédigé le 8 novembre 2026, d'après la demande d'Emmanuel et les maquettes
(https://claude.ai/artifact/KR5tjCTXpTkqyP2ZebFN2E, variante A retenue, avec la liste cliquable de la variante B).

## 1. Pourquoi

Depuis la 2.11.4, l'élève peut demander des retours formatifs. Le dernier retour et les éléments à compléter
sont aujourd'hui à deux endroits différents de la page : le bloc de retour sous le formulaire, les cases à
compléter dans le formulaire. L'élève doit faire défiler de l'un à l'autre. Emmanuel : « Le souci pour l'élève
c'est avoir à la fois le retour avec les conseils formatifs et les éléments à compléter. Sur desktop, un volet à
droite me semble pertinent. »

Succès : sur ordinateur, l'élève lit le conseil de l'IA, voit ce qui reste à compléter, clique sur un champ,
le remplit, sans jamais perdre le conseil de vue ; il demande un nouveau retour ou soumet depuis le même volet.

## 2. Objectifs, non-objectifs, anti-objectifs

**Objectifs**

1. Un volet à droite, « Mon avancement », sur les pages élève des étapes corrigées par l'IA (4 à 9), qui reste
   visible pendant le défilement du formulaire (ordinateur).
2. Il regroupe : statut de la copie, note (ou paliers), progression des éléments à compléter avec la liste
   cliquable, dernier retour de l'IA (formatif ou de la note appliquée), boutons « Demander un retour » et
   « Soumettre ».
3. Sur téléphone et tablette étroite : le volet passe sous le formulaire, et une barre collante en bas garde le
   statut et les deux boutons.

**Non-objectifs**

- Pas d'historique des retours (décision d'Emmanuel du 8 novembre).
- Aucun changement des règles : correction, limite de retours, paliers, verrous, notes restent ceux de 2.11.4.
- Pas de conseils rattachés à chaque section (variante C écartée).
- Pages enseignant inchangées (modèles de correction, consignes, page de correction).

**Anti-objectifs**

- Le volet ne doit jamais montrer un mot du corrigé que l'élève n'a pas écrit (même filtre `answer_leak_filter`).
- Ne jamais distinguer un état par la seule couleur (statut, champ ouvert, « ⊘ » : symbole + mot).
- Ne jamais cacher un champ à compléter derrière le volet ou une barre collante ; ne jamais faire perdre une saisie.

## 3. Le volet (variante A + liste de B)

De haut en bas :

1. **En-tête** « Mon avancement ».
2. **Statut** et **Note** (deux cases) :
   - statut : « ✎ Brouillon ouvert » / « ✓ Soumis le … » / « Correction en cours… » ;
   - note : la note appliquée (« 14/20, le … ») ; « — pas encore soumis » ; en mode paliers de l'étape 4,
     les paliers (« ✓ SAT validé · TS à demander ») au lieu de la note.
3. **Éléments à compléter** : « 3 sur 7 restants », barre de progression, puis la liste cliquable (un clic fait
   défiler jusqu'au champ, le met en évidence et y place le curseur). Mise à jour en direct pendant la saisie.
4. **Dernier retour de l'IA** : date, commentaire, pistes (filtrés). C'est le dernier retour formatif s'il est plus
   récent que la note appliquée, sinon le retour de la note appliquée. Pendant une demande : « Ton retour est en
   préparation… » puis « Ton retour est prêt » + bouton « Afficher le retour » (comportement 2.11.4).
5. **Boutons** : « Demander un retour (N restants) » (absent si la limite vaut 0, si la copie est soumise ou
   en mode paliers) ; « Soumettre ma version finale » (ou les boutons de palier en mode paliers).

Le contenu actuel des blocs « retour de la note appliquée », « retour avant soumission » et « paliers » passe
dans le volet ; ils ne s'affichent plus ailleurs. Le bouton « Réinitialiser le formulaire » reste dans le
formulaire.

## 3 bis. Cas particuliers (choix du concepteur, après relecture)

- **IA désactivée sur l'activité** : le volet est quand même là (une seule mise en page) : statut, note, éléments à
  compléter, « Soumettre ». Les sections « Dernier retour » et « Demander un retour » sont absentes.
- **Enseignant qui ouvre la page d'un groupe ou d'un élève** (`?groupid=`) : volet en lecture seule, sans
  « Demander un retour » ni « Soumettre » (un enseignant ne doit ni consommer les retours d'un groupe ni soumettre à
  sa place). Dans son propre espace de travail (`groupid = 0`), le volet est complet : c'est sa copie.
- **Note et retour affichés** :
  - la note est celle de la copie (`grade`), qu'elle vienne de l'IA ou de l'enseignant ;
  - le retour de la note est celui de l'évaluation IA appliquée, filtré ; s'il n'y en a pas, le commentaire saisi
    par l'enseignant ;
  - le dernier retour formatif l'emporte s'il est plus récent que la date d'application de la note
    (`applied_at`, ou la date de soumission pour une note saisie à la main) ;
  - copie remise en brouillon après une note : « Note précédente : 14/20 » ; « Demander un retour » et « Soumettre »
    reviennent selon les règles de 2.11.4.

## 4. Ce qui compte comme « à compléter »

- **Étape 4 (CDCF)** : les champs « À compléter » encore vides ; les champs « Pré-rempli » encore identiques à la
  consigne ou contenant « … » / « ... » / « A compléter » (même règle que l'avis 2.11.3) ; sans consigne fournie :
  les énoncés de FS et descriptions de critères vides.
- **Étapes 5, 6, 7** (fiche essai, rapport, expression du besoin) : les champs du formulaire encore vides, avec
  leur libellé.
- **Étapes 8 et 9** (carnet de bord, diagramme FAST) : pas de liste (pas de champs fixes) ; la section affiche
  seulement un lien « Aller au formulaire ». *Choix du concepteur.*

## 5. Contraintes

- Moodle 5.0 (prod) et 5.1 (préprod), thème Boost : le volet vit dans la zone de contenu de l'activité, à côté du
  formulaire ; il ne doit pas gêner le tiroir de blocs de droite de Moodle (s'il est ouvert, le volet passe sous
  le formulaire comme sur un écran étroit). *Choix du concepteur.*
- Règles du projet (`CLAUDE.md`) : AMD, pas de CSS/JS inline, chaînes en/fr, GPL, styles préfixés.
- Daltonisme : bleu pour le positif, orange pour ce qui reste à faire, toujours doublé d'un symbole ou d'un mot.
- Boutons ≥ 44 px ; navigation au clavier (liste de liens réels, boutons réels).

## 6. Laissé au constructeur

- Le point de bascule ordinateur / écran étroit, la largeur du volet (proposition : 380 px), les styles exacts.
- Le découpage en un gabarit Mustache commun et un module AMD commun aux six pages.
- La façon de recalculer la liste en direct pour chaque éditeur (CDCF, formulaires classiques).
