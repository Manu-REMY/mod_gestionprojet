# Préconisations pour le plugin : retour d'usage sur le CDC du robot (8 novembre 2026)

Ce document s'adresse à l'agent qui fera évoluer `mod_gestionprojet`. Il part d'un cas réel : le cahier des charges du robot martien en 4e. Emmanuel décidera de ce qui est retenu.

## Contexte

- **Activité :** cm 6974, instance 3, cours 5, section 9 « 4 : ROBOT », intitulée « Ex01 ----> COMPLÉTER LE CAHIER DES CHARGES DU ROBOT ».
  - Seule l'étape 4 est active, avec une consigne pré-remplie (`step4_provided = 1`).
  - Groupes séparés, groupement 71 « Groupes 4ème ».
  - IA : Albert.
  - `ai_auto_apply` est **passé à 0** le 08/11 à cause des défauts de la section 1.
- **Origine du contenu :**
  - consigne : fiche élève papier `FICHE ELEVE CDC ROB4.pages` du 31/12/2025, transposée en sept fonctions de service (FS1 à FS7), avec les trous marqués « (A compléter) » ;
  - corrigé : `FICHE CORRIGEE ELEVE CDC ROB4.pdf`.
  - Les deux se trouvent dans `OneDrive NEW/1 - COURS/2 - ACTIVITES/4/100 - ROBOT 4/010 - CDC/RESSOURCES/`.
- **Script de création :** `MOODLE/8 - RESSOURCES/robot-cdc/creer_activite.php`.
- **Partage du travail entre paliers (décision d'Emmanuel) :**
  - **SAT** : les fonctions et les critères d'appréciation ;
  - **TS** : les niveaux d'exigence, avec leur unité.
- **Plugin déployé :** version 2.10.1. Le 08/11, `db/install.php` et `db/upgrade.php` ont été alignés sur le commit `4b3d879`, qui retire la clé écrite en dur.
- **Non vérifié :** l'affichage élève et le diagramme pieuvre. Je n'ai pas pu me connecter au site dans le navigateur.

## 1. À corriger d'abord : constaté le 08/11

Les essais suivants ont été faits sans rien écrire en base. Une copie fictive de niveau SAT, avec toutes les fonctions et tous les critères justes et aucun niveau, a été passée à `ai_prompt_builder::build_prompt()`, puis au fournisseur Albert.

1. **Albert répond HTTP 404 avec le modèle par défaut `albert-large`.** En l'état, aucune copie ne peut être corrigée.
   - Avec `deepseek-v4-flash`, sur le même endpoint et avec la même clé intégrée, la correction aboutit en 7 s. C'est le modèle que `mod_redaction` utilise en production.
   - À faire : mettre `deepseek-v4-flash` par défaut dans `albert_provider::MODELS` et `DEFAULT_MODEL`.
   - Vérifier aussi `TEST_MODEL` (`albert-base`), qui risque la même erreur 404.
   - Idéalement, rendre le modèle réglable dans les paramètres d'administration, sans le coder en dur.
2. **La grille de notation de l'étape 4 est codée en dur** (`ai_prompt_builder::STEP_CRITERIA[4]`) et contredit les consignes du professeur. Sur la copie d'essai :
   - « Interacteurs » a reçu 4/4, alors que les interacteurs étaient fournis par la consigne ;
   - « Contraintes » a reçu 0/3, alors que l'activité n'en demande aucune ;
   - la flexibilité est exigée, alors que les instructions disent de l'ignorer ;
   - résultat : **9/20 pour un travail SAT complet**.
   - À faire : permettre au professeur de définir sa grille (noms, poids, descriptions) dans `cdcf_teacher`, avec la grille actuelle par défaut. Ne jamais noter une partie fournie par la consigne ni une partie que l'activité ne demande pas.
3. **Le retour donne les réponses.** Le JSON contient `keywords_missing`, où l'on lit « temps de recharge », « hauteur de marche », « rayon de braquage », et le commentaire propose une formulation du corrigé (« être autonome »). Les instructions du professeur l'interdisaient pourtant.
   - À faire :
     - ne jamais afficher `keywords_missing` à l'élève ;
     - ajouter une option « ne pas révéler le corrigé » ;
     - filtrer après coup toute phrase du retour qui reprend un mot du corrigé que l'élève n'a pas écrit.
   - `mod_redaction` a déjà ce filtre, dans le même dépôt d'Emmanuel : le réutiliser.
4. **Le modèle a mal relu la copie.** Il a déclaré « à compléter » des critères déjà remplis (C1.2 par exemple). Au moment de l'essai, la consigne préfixait les descriptions par « FP1 : » ou « FC1 : », alors que le prompt numérote FS1, FS2… Ce décalage a pu embrouiller la comparaison. Les préfixes ont été retirés depuis.

## 2. Adapter le plugin au CDC tel qu'il est enseigné au collège

5. **Pas de colonne Unité.** Le cahier des charges enseigné a cinq colonnes : N°, fonction, critère, unité, niveau. Le plugin fusionne l'unité dans le niveau, comme le fait `cdcf_helper::migrate_legacy`.
   - À faire : ajouter un champ `unite` optionnel au critère, avec migration et affichage en colonne. Le palier TS demande justement le niveau et son unité.
6. **Fonctions de service : NE RIEN CHANGER.** Emmanuel l'a rappelé le 08/11 : la norme ne connaît que des fonctions de service (FS). Chacune relie un interacteur au système, ou deux interacteurs entre eux. Pas de FP ni de FC : la structure actuelle (`fonctionsService` avec `interactor1Id` et `interactor2Id`) est la bonne et doit rester telle quelle.
7. **La flexibilité doit pouvoir être masquée.** Option par instance « Demander la flexibilité (F0 à F3) » : quand elle est désactivée, le champ est caché et retiré du prompt.
8. **Le palier atteint n'est pas déterminé.** La note sur 20 ne dit pas si le groupe a validé SAT ou TS.
   - Aujourd'hui, « Soumettre » verrouille la copie (`status = 1`), et seul le professeur la rouvre, groupe par groupe.
   - À faire : un mode « paliers » où la grille du professeur rattache chaque partie à un palier, avec un seuil.
   - Validation en deux temps demandée par Emmanuel le 08/11 :
     - un bouton « Faire valider le niveau SAT » corrige seulement les fonctions et les critères ;
     - si le palier est validé, la copie **reste ouverte** pour la suite ;
     - un second bouton « Faire valider le niveau TS » corrige ensuite les niveaux et les unités ;
     - chaque palier garde son propre verdict ; un palier validé n'est jamais retiré par une correction suivante ;
     - un échec renvoie un retour sans réponse, et le groupe peut retenter.
   - Stocker le verdict par groupe et par palier dans une table lisible, par exemple `gestionprojet_palier` avec gestionprojetid, groupid, palier, statut et date.
   - Le résultat (FRA, SAT ou TS) doit être exposé pour qu'un autre composant le pose en vie de classe, par exemple dans le niveau des réussites. C'est ce que font déjà `local_correcteurtableur` et `mod_redaction` dans l'installation d'Emmanuel : s'aligner sur leur fonctionnement, sans écrire directement dans les tables de vie de classe.

## 3. Ergonomie de la consigne pré-remplie

9. **Pouvoir verrouiller des champs.**
   - Aujourd'hui, la consigne est copiée telle quelle et l'élève peut tout effacer : interacteurs, fonction donnée en entier (FS2, résister à l’atmosphère), unités imprimées.
   - Seul « aucune modification » est détecté, par comparaison stricte.
   - À faire : marquer des champs « fourni, non modifiable » dans `cdcf_provided`, et n'évaluer que les champs ouverts.
10. **Les trous sont du texte libre.** Les « (A compléter) » sont noyés dans les phrases (« Doit (A compléter) terrain »), et l'élève doit les effacer lui-même.
    - À faire : un vrai marqueur de trou, affiché comme une case à remplir. Le prompt saurait alors exactement ce que l'élève a écrit.
11. **Une instance ne se configure pas d'un seul formulaire.** `enable_step1` à `enable_step9` et `step4_provided` ne sont pas dans `mod_form`. Pour créer l'activité, il a fallu écrire en base par script.
    - À faire : mettre ces réglages dans le formulaire, ou proposer un préréglage « Cahier des charges seul » à la création.

17. **L'accueil élève exige les étapes 1 à 3, même désactivées.** `gestionprojet_teacher_pages_complete()` (lib.php) demande `description.intitule`, `besoin.aqui` et `planning.projectname`, sans regarder `enable_step1` à `enable_step3`. Sur cm 6975 (étape 3 désactivée, étapes 1 et 2 vides), l'élève voit « L'enseignant doit d'abord compléter les 3 premières étapes » sur `view.php?id=…`, alors que `&step=4` s'ouvre (le contrôle n'est que dans `pages/home.php`). Constaté le 08/11 avec un compte élève de test.
    - À faire : ne vérifier que les étapes enseignant activées, et ne jamais bloquer une étape élève dont la consigne est fournie (`step4_provided`).

18. **Une case de consultation inutile pour l'élève.** Avec `step4_provided = 1`, le Gantt de l'accueil élève (`pages/home.php`) affiche deux cases pour l'étape 4 : « consulter » (`&mode=provided`, consigne en lecture seule) et « travailler » (`&step=4`). Or la page de travail contient déjà tout : le texte d'introduction en tête et le CDCF pré-rempli avec la consigne. Emmanuel, en élève de test le 08/11, a cliqué sur la première et s'est retrouvé devant une page où rien n'est modifiable.
    - À faire : pour un élève, quand la consigne est recopiée dans sa copie, ne pas afficher la case de consultation (ou la faire mener à la page de travail). Même logique pour les étapes 5 et 9 en mode fourni.

## 3 bis. Champs modifiables par l'élève : ce qui reste après la v2.11.1 (constaté le 08/11 après-midi)

État de la production : 2.11.1 (`2026110804`), `consigne_lock` présent. Sur cm 6975 (instance 4), la consigne ne porte **aucun drapeau** (`locked`, `gap`, `niveauLocked`, `niveauGap` : 0). Comme prévu par la v2.11.1, une consigne sans drapeau garde l'ancien comportement : en mode « travailler », tous les champs sont modifiables. Emmanuel l'a constaté avec le compte élève de test et le juge bloquant. Les points 9 et 10 sont donc en partie traités, mais pas encore utilisables tels quels pour ce CDC.

19. **Rendre le verrouillage visible et par défaut.**
    - Sur la page de la consigne, quand `step4_provided = 1` et qu'aucun drapeau n'est posé, afficher un avertissement au professeur : « Aucun élément n'est verrouillé : les élèves pourront tout modifier. »
    - Proposer une action « Tout marquer Fourni » puis laisser le professeur ouvrir les cases à compléter. Pour un CDC pré-rempli, le cas courant est « presque tout fourni, quelques trous ».
    - Option : traiter automatiquement un champ dont le texte contient « (A compléter) » ou « A compléter » comme un champ `gap`, avec le reste du texte en indication.
20. **Trous au milieu d'une phrase.** La granularité actuelle est le champ entier. Or la consigne a des trous dans des phrases fournies : « Doit (A compléter) terrain », « Permettre à l'utilisateur d'obtenir (A compléter) l'atmosphère », « Envoi des (A compléter) », « (A compléter) km/h ». Avec un `gap`, l'élève doit réécrire toute la phrase ; avec un `locked`, il ne peut rien écrire.
    - Constaté par Emmanuel le 08/11 avec les drapeaux posés : l'indication (« Doit … terrain. ») n'est qu'un `placeholder`, elle disparaît dès que l'élève tape, et la partie fournie de la phrase est perdue. Il demande que la partie fournie reste affichée.
    - À faire : un marqueur de trou dans le texte de la consigne (par exemple `[[...]]` ou `___`) rendu chez l'élève comme une petite case dans la phrase figée ; seul le contenu des cases est enregistré et noté.
    - Pour le niveau d'exigence : séparer valeur et unité (point 5) permet de fournir l'unité imprimée (« km/h », « cm », « degrés ») et de n'ouvrir que la valeur, ou l'inverse.
20 bis. **Troisième état « Pré-rempli, modifiable » (demande d'Emmanuel du 08/11, prioritaire sur le point 20).** Emmanuel veut que le champ reste entièrement modifiable, mais que le texte de la consigne y soit **déjà écrit** (valeur initiale du champ, pas une indication grisée qui s'efface à la frappe). Exemple : le champ arrive avec « Doit … terrain. », l'élève remplace « … » et garde le reste ; il peut aussi tout réécrire.
    - Aujourd'hui, ce comportement n'existe que de façon implicite : un champ sans drapeau est recopié tel quel par `consigne_lock::seed`. Mais l'éditeur de la consigne ne le présente pas comme un choix, et l'IA ne sait pas quelle partie vient de la consigne.
    - À faire :
      - dans l'éditeur de la consigne, trois états exclusifs par champ réglable (énoncé de FS, description de critère, niveau, interacteur, contrainte) : « 🔒 Fourni » (verrouillé), « ✎ Pré-rempli » (texte de la consigne écrit dans le champ, modifiable), « ▢ À compléter » (case vide avec indication) ; drapeau `prefill` (et `niveauPrefill` pour le niveau), écrit seulement quand vrai, comme les autres ; doubler l'état par l'icône et le mot (daltonisme) ;
      - chez l'élève, un champ `prefill` arrive rempli avec le texte de la consigne et reste éditable ; style discret qui le distingue d'un champ fourni (pas de cadenas) ;
      - IA : dans le prompt, marquer ces champs `[pré-rempli : « texte de la consigne »]` pour que le modèle évalue seulement ce que l'élève a changé ou ajouté, et ne crédite jamais un texte laissé tel quel ;
      - détection « aucune modification » : un champ `prefill` resté identique à la consigne compte comme non fait ; signaler à l'élève, avant « Soumettre », les champs pré-remplis encore identiques ou contenant encore « … » ;
      - `enforce` ne touche pas aux champs `prefill` (l'élève peut tout réécrire) ;
      - réamorçage (point 22) : une copie non commencée reçoit les champs `prefill` remplis.
    - Pour cm 6975 : énoncés des FS1 et FS3 à FS7, critères « Envoi des … » et « Mesurer … », niveaux « > … », « … km/h », « … cm », « … degrés », « … m », « Temps … » passent en `prefill` ; les trois critères entièrement à trouver restent « À compléter » ; le reste reste « Fourni ».
21. **Verrouiller les interacteurs liés indépendamment de l'énoncé.** Aujourd'hui, une FS `locked` fige énoncé ET interacteurs. Pour ce CDC, les liaisons (FS1 utilisateur ↔ atmosphère, FS2 atmosphère ↔ robot…) sont données par le diagramme, mais l'énoncé de plusieurs FS est à compléter. Il faut un drapeau propre aux interacteurs liés (`linksLocked`).
22. **Copies déjà créées.** `seed` n'est appliqué qu'à la création de la copie. Si le professeur pose les drapeaux après coup, les copies existantes gardent les « (A compléter) » en texte au lieu de cases vides (les verrous, eux, sont rétablis à l'enregistrement). Proposer au professeur « Réamorcer les copies non commencées » (copies identiques à l'ancienne copie de départ), sans toucher à celles où un élève a écrit.

## 4. À vérifier

12. **Fiche HTML :** confirmer que le lien profond `view.php?id=6974&step=4` mène bien l'élève à son groupe. La fiche élève (cm 749) y renverra au lieu du devoir 1347.
13. **Diagramme pieuvre :** vérifier qu'une fonction rattachée à un seul interacteur (`interactor2Id = 0`, c'est-à-dire reliée au produit) s'y affiche correctement. Six des sept fonctions sont dans ce cas.
14. **Bibliothèque `curl` :** les fournisseurs d'IA utilisent la classe `curl` sans `require_once($CFG->libdir . '/filelib.php')`. En CLI, cela donne « Class "curl" not found ». Vérifier que la tâche `evaluate_submission` la charge, sinon l'ajouter dans `base_provider`.
15. **Rotation de la clé Albert :** le commit `4b3d879` indique que la clé publiée doit être changée chez Albert. La clé chiffrée dans `mod_gestionprojet/gestionprojet_albert_api_key` est-elle la nouvelle ? Ne jamais l'afficher pour le vérifier : comparer des empreintes.
16. **Tests :** ajouter un test du prompt de l'étape 4 avec la consigne et le corrigé du robot comme jeu de données. Il doit vérifier qu'une copie SAT complète n'est pas pénalisée pour l'absence de contraintes et de flexibilité.
