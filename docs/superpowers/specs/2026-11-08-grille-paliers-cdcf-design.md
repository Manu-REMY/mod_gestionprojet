# Grille réglable, paliers SAT/TS et consigne verrouillable — étape 4 (CDCF)

Version cible : 2.11.x. Validé par Emmanuel le 8 novembre 2026. Rédigé le 8 novembre 2026 à partir de `docs/preconisations-2026-11-08-cdc-robot.md`
(préconisations 2, 8, 9, 10) et des réponses d'Emmanuel du même jour.

## 1. Pourquoi

Activité réelle : le cahier des charges du robot martien en 4e (prod, cm 6974, instance 3, groupes séparés,
Albert). Les élèves complètent une consigne pré-remplie. Le travail est partagé entre deux paliers :

- **SAT** : les fonctions de service et leurs critères d'appréciation ;
- **TS** : les niveaux d'exigence (valeur et unité dans le même champ `niveau`).

Aujourd'hui :

- la grille de notation de l'étape 4 est codée en dur (`ai_prompt_builder::STEP_CRITERIA[4]`) : une copie SAT
  complète obtient 13/20 en préprod parce que les contraintes et les niveaux sont notés alors que l'activité ne
  les demande pas au palier SAT ;
- la note sur 20 ne dit pas si le groupe a validé SAT ou TS ;
- « Soumettre » verrouille toute la copie, seul le professeur la rouvre ;
- la consigne est copiée telle quelle : l'élève peut effacer les interacteurs ou les lignes fournies, et les trous
  sont du texte libre « (A compléter) » noyé dans les phrases.

Succès : un groupe clique « Faire valider le niveau SAT », l'IA corrige seulement les fonctions et les critères,
le palier est validé ou non sans révéler le corrigé, la copie reste ouverte, puis le groupe fait valider TS sur
les niveaux. Le palier atteint est lisible par l'appli de vie de classe (`plan-classe`).

## 2. Objectifs, non-objectifs, anti-objectifs

**Objectifs**

1. Grille de l'étape 4 modifiable par le professeur (liste de parties : nom, poids, description, zone évaluée,
   palier), la grille actuelle étant proposée par défaut.
2. Une partie entièrement fournie par la consigne n'est pas notée (exclusion automatique, que le professeur peut
   forcer dans les deux sens).
3. Mode « validation par paliers » : un bouton par palier utilisé, verdict de l'IA seul, copie qui reste ouverte,
   palier acquis jamais retiré, nouvelle tentative possible après un échec.
4. Verrouillage de la consigne **élément par élément**, et vrais champs « à compléter ».
5. Exposition des paliers validés dans une table lisible par `plan-classe`.

**Non-objectifs**

- Pas de changement de la structure des fonctions de service : uniquement des FS, qui relient un interacteur au
  système (`interactor2Id = 0`) ou deux interacteurs. Pas de FP/FC, pas de champ unité séparé
  (décisions du 8 novembre, préconisations 5 et 6 abandonnées).
- Pas de modification de `plan-classe` dans ce projet : on fournit la table, l'ajout de la lecture côté
  `plan-classe` est un chantier à part (dans son propre dépôt).
- Les étapes 5 à 9 gardent leur grille codée en dur ; le modèle de données est prévu pour les ajouter plus tard.

**Anti-objectifs (échec même si « ça marche »)**

- Le retour d'un palier échoué révèle le corrigé (mots ou nombres du corrigé non écrits par l'élève).
- Un palier validé disparaît ou redescend après une correction suivante.
- Un élève peut modifier ou supprimer un élément verrouillé en contournant l'interface (requête directe).
- Les activités existantes changent de comportement sans action du professeur.

## 3. Décisions d'Emmanuel (8 novembre)

| Sujet | Décision |
|---|---|
| Grille | Liste modifiable (pas seulement des cases à cocher). |
| Parties fournies | Exclusion automatique, corrigeable à la main. |
| Paliers | FRA / SAT / TS. |
| Qui valide | Le verdict de l'IA seul, sans confirmation du professeur. |
| Après validation | L'élève peut tout modifier sauf les éléments déjà validés. |
| Note sur 20 | Inutile en mode paliers : on ne la conserve pas. |
| Vie de classe | S'aligner sur `local_correcteurtableur` / `mod_redaction` sans écrire dans les tables de vie de classe. |
| Tentatives | Au choix du concepteur. |
| Verrouillage | Élément par élément. |
| Clé Albert | On laisse la clé actuelle. |
| Zone validée | Figée en entier : ni modification, ni suppression, ni ajout. |

## 4. Conception

### 4.1 Grille de l'étape 4

Nouveau champ `grille_json` (TEXT, nullable) dans `gestionprojet_cdcf_teacher`. Vide = grille par défaut.

Une partie :

```json
{"id": 1, "nom": "Fonctions de service", "description": "…", "poids": 6,
 "zones": ["fonctions"], "palier": "S", "evaluee": "auto"}
```

- `zones` : une ou plusieurs zones parmi `interacteurs`, `fonctions` (énoncés des FS et leurs interacteurs),
  `criteres` (descriptions des critères), `niveaux`, `flexibilite`, `contraintes`, `ensemble` (cohérence
  globale). *Choix du concepteur :* plusieurs zones par partie, car la partie par défaut « Critères » couvre
  `criteres`, `niveaux` et `flexibilite`. Les zones servent à l'exclusion automatique (4.2), au palier (4.3)
  et au verrouillage après validation (4.4).
- `palier` ∈ `F`, `S`, `TS` ou vide ; ignoré hors mode paliers.
- `evaluee` ∈ `auto`, `oui`, `non` (forçage du professeur).
- Grille par défaut = les 5 critères actuels (Interacteurs 4, Fonctions 6, Critères 4, Contraintes 3,
  Cohérence 3) avec leurs zones, sans palier : hors mode paliers, une activité existante est notée exactement
  comme aujourd'hui ; la mention de flexibilité suit l'option `step4_flexibility` (déjà livrée en
  2.10.4).
- Éditeur : sur la page du modèle de correction de l'étape 4, un tableau de parties (ajouter, supprimer,
  réordonner, éditer nom, description, poids, zone, palier, évaluée). Enregistré par l'autosave enseignant
  existant (ajouter `grille_json` à la liste blanche).
- Le prompt d'évaluation utilise la grille effective (4.2) à la place de `STEP_CRITERIA[4]`. La somme des poids
  évalués est ramenée sur 20 hors mode paliers.

### 4.2 Exclusion automatique des parties fournies

Une partie en `evaluee = auto` est exclue quand **tous** les éléments de **toutes** ses zones sont verrouillés
dans la consigne (4.5), et que ces zones ne sont pas vides. Exemple : interacteurs tous fournis et verrouillés
→ partie « Interacteurs » exclue. *Choix du concepteur :* une zone vide n'est jamais exclue automatiquement
(on ne peut pas distinguer « rien de demandé » de « tout à faire ») ; le professeur met la partie à `non`
(cas des contraintes du robot). Un élément ajouté par l'élève dans une zone dont tous les éléments fournis
sont verrouillés ne rend pas la partie évaluée. `oui` / `non` forcent. La page du modèle de correction affiche à côté de chaque partie
« évaluée » ou « exclue (fournie par la consigne) ».

### 4.3 Mode « validation par paliers »

Nouveaux champs de `gestionprojet` : `step4_paliers` (int 1, défaut 0) et `step4_palier_seuil` (int 3, défaut
**à décider par le constructeur**, voir 6). Réglés dans la section « Cahier des charges (étape 4) » de
`mod_form`.

En mode paliers, pour l'élève :

- Le bouton « Soumettre » de l'étape 4 est remplacé par un bouton par palier présent dans la grille, dans
  l'ordre F, S, TS : « Faire valider le niveau SAT », etc. Un palier n'est proposé que si les paliers inférieurs
  utilisés sont validés.
- Clic → la copie n'est **pas** verrouillée (`status` reste 0) ; une évaluation IA est mise en file avec le
  palier visé. Pendant l'évaluation, le bouton est désactivé (une seule évaluation en cours par groupe).
- Le prompt ne note que les parties de ce palier qui ne sont pas exclues. *Choix du concepteur :* en mode
  paliers, une partie sans palier n'est jamais évaluée ; l'éditeur de grille le signale (« sans palier : non
  évaluée en mode paliers »).
- *Choix du concepteur :* au clic, le contenu de la copie est figé dans l'évaluation (nouveau champ
  `contenu_json` de `gestionprojet_ai_evaluations`) et les zones du palier visé sont désactivées dans l'éditeur
  jusqu'au verdict. C'est ce contenu figé qui est jugé, et c'est lui qui devient la référence des zones validées
  (4.4).
  Verdict = palier validé si la note de l'IA sur ces parties ≥ seuil (en % des points du palier).
- Validé : une ligne est ajoutée à `gestionprojet_palier` (4.6), les éléments des zones de ce palier sont
  verrouillés pour le groupe (4.4), le bandeau affiche « Niveau SAT validé » (forme + mot, pas la couleur seule).
- Échoué : retour filtré (option `ai_hide_answers` forcée en mode paliers), le groupe peut retenter. Pas de
  limite de tentatives ni de délai (choix du concepteur, réponse « au choix »).
- Un palier validé n'est jamais retiré, même si une tentative suivante échoue ou si l'IA serait plus sévère.
- Pas de note sur 20 : pas d'élément de carnet de notes pour l'étape 4 en mode paliers ; l'IA ne propose pas de
  note à appliquer. Le professeur voit dans `grading.php` le palier atteint et l'historique des tentatives.
- Le professeur peut retirer un palier à la main depuis `grading.php` (cas d'erreur de l'IA) ; c'est la seule
  façon de le retirer. *Choix du concepteur :* retirer un palier retire aussi les paliers supérieurs validés et
  rouvre leurs zones. `plan-classe` ne fait jamais redescendre un niveau : un retrait ne change pas la vie de
  classe, le professeur y corrige à la main.
- *Choix du concepteur :* activer ou désactiver le mode paliers est un simple réglage. Les copies déjà
  soumises gardent leur état (le professeur peut les rouvrir), les notes déjà au carnet restent ; en mode
  paliers, l'élément de carnet de l'étape 4 n'est plus mis à jour. Les paliers déjà posés restent en base si
  on désactive le mode, et réapparaissent si on le réactive. L'aide du réglage le dit.

### 4.4 Verrouillage après validation

Validation d'un palier → les zones de ce palier sont figées **en entier** pour ce groupe, avec la valeur
qu'elles avaient dans le contenu figé au clic (4.3) : on ne peut ni modifier, ni supprimer, ni **ajouter** un
élément dans une zone validée (décision d'Emmanuel du 8 novembre : une FS ajoutée après SAT n'aurait jamais été
validée). Robot : après SAT, impossible d'ajouter une FS ou un critère ; seuls les niveaux restent ouverts. Robot : SAT validé
→ énoncés des FS, interacteurs des FS et descriptions des critères verrouillés ; les niveaux restent modifiables
pour TS. Stockage : liste des zones verrouillées par soumission (nouveau champ `zones_validees` TEXT JSON dans
`gestionprojet_cdcf`), appliquée par l'éditeur (champs désactivés) **et** par le serveur (4.5).

### 4.5 Consigne verrouillable élément par élément et champs à compléter

Dans l'éditeur de consigne (`step4_provided`), chaque élément porte deux bascules :

- **Fourni, non modifiable** (`locked: true`) : interacteur (nom), FS (énoncé et interacteurs), critère
  (description), niveau, contrainte.
- **À compléter** (`gap: true`) : le champ arrive vide chez l'élève, affiché comme une case à remplir
  (bordure en tirets + libellé « à compléter », pas la couleur seule). Remplace le texte « (A compléter) ».

Les drapeaux sont stockés dans le JSON de la consigne, sur les éléments existants : `cdcf_helper::normalize`
doit les conserver (aujourd'hui il supprime toute clé inconnue). La structure des FS ne change pas.

Côté élève :

- un élément verrouillé est affiché non modifiable et ne peut pas être supprimé ;
- **le serveur fait foi** : à chaque autosave et soumission de l'étape 4, les éléments verrouillés de la consigne
  (et ceux des zones validées, 4.4) sont rétablis depuis la consigne / la version validée, y compris s'ils ont
  été supprimés de la requête. *Choix du concepteur :* les critères sont identifiés par la paire (FS, critère),
  leurs ids n'étant uniques que dans une FS ; un nouvel élément qui reprend l'id d'un élément verrouillé reçoit
  un nouvel id ;
- le prompt indique à l'IA ce qui était fourni et ce que l'élève a écrit, champ par champ : il ne note que les
  champs ouverts.

Copies existantes : la consigne d'une activité existante n'a aucun drapeau, donc rien n'est verrouillé tant que
le professeur ne coche rien. Les copies déjà commencées ne sont pas réinitialisées.

### 4.6 Exposition à la vie de classe

Nouvelle table `gestionprojet_palier`, sur le modèle de `local_correcteurtableur` (lu par
`plan-classe/lib/reussites.php`) :

| Champ | Contenu |
|---|---|
| `id` | |
| `gestionprojetid` | instance |
| `step` | 4 |
| `groupid` / `userid` | groupe (userid 0) ou élève seul (groupid 0) |
| `palier` | `F`, `S` ou `TS` (mêmes codes que `plan-classe`) |
| `evaluationid` | évaluation IA qui l'a validé (0 si posé par le professeur) |
| `membres` | JSON `[[userid, username], …]` au moment de la validation |
| `retire` | 0, ou 1 si le professeur l'a retiré (la ligne reste pour l'historique) |
| `timecreated` / `timemodified` | |

Une ligne par validation, jamais supprimée sauf suppression de l'instance. Le plugin n'écrit rien ailleurs.
À ajouter partout : `install.xml`, `upgrade.php`, `delete_instance`, privacy provider (donnée personnelle via
`membres`), backup/restore (avec remap groupe, utilisateurs, évaluation).

## 5. Contraintes

- Moodle 5.0 (prod) et 5.1 (préprod), PHP 8.2 ; règles de `CLAUDE.md` (chaînes en/fr, pas de CSS/JS inline,
  AMD, en-têtes GPL, `delete_instance`, privacy).
- Daltonisme : jamais la teinte seule (bleu positif / orange négatif, doublé d'une forme ou d'un mot).
- Pas d'environnement PHPUnit local : tests en PHP pur (`tests/pure/`) + vérifications CLI en préprod ;
  déploiement préprod en fin de développement, prod sur demande.
- Albert `deepseek-v4-flash` ; le retour élève reste filtré par `answer_leak_filter`.

## 6. Laissé au constructeur

- Seuil de validation par défaut (proposition : 80 % des points du palier), réglable par activité.
- Rendu exact de l'éditeur de grille et des bascules (dans le style des pages du modèle de correction).
- Formulation du prompt de verdict, tant qu'il reste dans le format JSON actuel (`criteria` par partie) et que le
  verdict est calculé côté serveur à partir des points, pas lu dans un texte de l'IA.
- Découpage en versions (proposition : 2.11.0 grille + exclusion ; 2.11.1 verrouillage et trous ; 2.11.2
  paliers et table d'exposition), chacune déployée en préprod.
