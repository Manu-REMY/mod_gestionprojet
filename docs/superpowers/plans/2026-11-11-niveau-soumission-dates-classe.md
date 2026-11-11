# Niveau validé à la soumission, date limite par classe, corrections espacées — plan (2.14.0)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** à l'étape 4, le niveau de la copie s'enregistre quand sa correction est appliquée ; chaque classe a sa date limite de soumission automatique ; les corrections notées partent au plus une toutes les 6 s.

**Architecture:** deux petites classes pures (`classes/deadline.php`, ajouts à `classes/palier.php` et `classes/ai_evaluator.php`) portent les règles ; `lib.php` les branche sur la base (table nouvelle `gestionprojet_deadline_group`, table existante `gestionprojet_palier`) ; les pages et la tâche planifiée lisent la date effective au lieu de `deadline_date`.

**Tech Stack:** Moodle 5.0 (PHP 8.1+), DML, XMLDB, tâches adhoc ; tests purs `php gestionprojet/tests/pure/test_<nom>.php` (harnais `tests/pure/bootstrap.php`, fonction `test(label, fn)`).

**Spec:** `docs/superpowers/specs/2026-11-11-niveau-soumission-dates-classe-design.md`

## Global Constraints

- Version : `$plugin->version = 2026111105`, `$plugin->release = '2.14.0'` ; étape de mise à jour au même numéro.
- Toute table nouvelle : `install.xml` + `upgrade.php` + `delete_records` dans `gestionprojet_delete_instance()` (CLAUDE.md, point 8).
- Chaînes dans `lang/en` et `lang/fr` ; commentaires en anglais ; en-tête GPL complet ; pas de JS en ligne (test `test_no_inline_js.php`).
- Niveaux : codes `F`, `S`, `TS` (`palier::ORDER`) ; seuil `step4_palier_seuil` (défaut 80).
- Rythme des corrections : réglage du site `mod_gestionprojet/ai_interval`, en secondes, **6** par défaut ; 0 = pas d'espacement.
- Ne rien changer pour une activité en mode paliers (`step4_paliers = 1`) : ses niveaux restent ceux des demandes de validation.
- `git add <chemins>` fichier par fichier, jamais `-A`.

## Review Focus

- Élève dans deux classes ayant chacune une date : la plus tardive s'applique (test de `deadline::effective`, tâche 1).
- Date de classe déjà passée mais date de l'étape à 0 ou future : la copie est bien soumise (test de la requête de la tâche, tâche 2).
- Copie soumise puis re-corrigée plus bas : le niveau supérieur passe à `retire = 1`, la ligne reste (tâche 4).
- Réponse de l'IA où une partie à niveau n'est pas nommée (`level_reached` renvoie `null`), ou note retouchée par le professeur : aucun niveau touché (tâche 4, vérification de source des deux conditions de sortie).
- Horloge du créneau dans le passé (site inactif longtemps) : la correction part tout de suite, pas des heures plus tard (tâche 3).

---

### Task 1: date limite par groupe — table et règle

**Files:**
- Create: `gestionprojet/classes/deadline.php`
- Modify: `gestionprojet/db/install.xml` (table après `gestionprojet_palier`), `gestionprojet/db/upgrade.php` (étape 2026111105), `gestionprojet/lib.php` (`gestionprojet_delete_instance`, nouvelle fonction), `gestionprojet/version.php`
- Test: `gestionprojet/tests/pure/test_deadline_group.php`

**Interfaces:**
- Produces: `\mod_gestionprojet\deadline::effective(int $stepdeadline, array $groupdeadlines): int` — `$groupdeadlines` = dates (> 0) des groupes concernés ; renvoie la plus tardive, sinon `$stepdeadline`.
- Produces: `gestionprojet_effective_deadline(object $gestionprojet, int $step, object $submission): int` dans `lib.php` — lit `deadline_date` du modèle enseignant de l'étape (tables `gestionprojet_<cdcf|essai|rapport|besoin_eleve|carnet|fast>_teacher`), les lignes de `gestionprojet_deadline_group` pour (activité, étape) et les groupes concernés : `$submission->groupid` s'il est > 0, plus les groupes du cours de chaque membre (`groups_get_members($submission->groupid)` en travail de groupe, sinon `$submission->userid`) via `groups_get_user_groups($courseid, $userid)[0]`.
- Produces: table `gestionprojet_deadline_group` : `id`, `gestionprojetid` (int 10), `step` (int 2), `groupid` (int 10), `deadline_date` (int 10), `timemodified` (int 10) ; index unique (`gestionprojetid`, `step`, `groupid`).

- [ ] **Step 1: tests** — `deadline::effective(0, [])` vaut 0 ; `effective(100, [])` vaut 100 ; `effective(100, [50])` vaut 50 (une date de classe remplace celle de l'étape, même plus tôt) ; `effective(0, [50, 80])` vaut 80 ; `effective(100, [0, 80])` vaut 80 (une date nulle est ignorée). Vérifications de source : `install.xml` déclare la table et son index unique ; `upgrade.php` crée la table sous `if ($oldversion < 2026111105)` ; `gestionprojet_delete_instance` contient `delete_records('gestionprojet_deadline_group'`.
- [ ] **Step 2:** `php gestionprojet/tests/pure/test_deadline_group.php` → FAIL.
- [ ] **Step 3:** écrire `deadline::effective`, la table (install + upgrade), `delete_records`, `gestionprojet_effective_deadline`, la version.
- [ ] **Step 4:** le test passe ; toute la suite `tests/pure/test_*.php` passe.
- [ ] **Step 5:** commit `feat(deadline): a deadline per group for each step (request 40)`.

### Task 2: la date effective partout

**Files:**
- Modify: `gestionprojet/classes/task/auto_submit_deadline.php`, `gestionprojet/lib.php` (`gestionprojet_check_deadline_submissions`, `gestionprojet_progress_state`, demande de palier vers la ligne 511, retour formatif vers la ligne 955), `gestionprojet/pages/student_dates_display.php`, `gestionprojet/pages/student_submission_section.php`
- Test: `gestionprojet/tests/pure/test_deadline_group.php` (suite)

**Interfaces:**
- Consumes: `gestionprojet_effective_deadline()` (tâche 1).

- [ ] **Step 1: tests (source)** — aucun fichier listé ci-dessus ne compare encore `deadline_date` à l'heure pour décider (motif `deadline_date > $now`, `time() > $deadline` sur une valeur lue de `deadline_date`) ; chacun appelle `gestionprojet_effective_deadline(`. La tâche choisit ses activités par `t.deadline_date > 0 AND t.deadline_date <= :now` **OU** une ligne de `gestionprojet_deadline_group` pour l'étape avec `deadline_date <= :now`, puis décide copie par copie avec la date effective.
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** brancher la date effective ; dans la tâche et dans `gestionprojet_check_deadline_submissions`, une copie en brouillon n'est soumise que si sa date effective est > 0 et passée.
- [ ] **Step 4:** PASS, suite complète verte.
- [ ] **Step 5:** commit `feat(deadline): the effective deadline drives auto-submission, display and formative feedback`.

### Task 3: corrections notées espacées

**Files:**
- Modify: `gestionprojet/classes/ai_evaluator.php` (`queue_evaluation`), `gestionprojet/settings.php`, `lang/en|fr/gestionprojet.php`
- Test: `gestionprojet/tests/pure/test_ai_rate.php`

**Interfaces:**
- Produces: `ai_evaluator::slot_time(int $now, int $lastslot, int $interval): int` = `max($now, $lastslot + $interval)` ; `$interval <= 0` renvoie `$now`.
- Produces: réglage `mod_gestionprojet/ai_interval` (`admin_setting_configtext`, `PARAM_INT`, défaut `6`), chaînes `setting_ai_interval` et `setting_ai_interval_desc` (fr : « Écart entre deux corrections notées (secondes) » / « Les corrections des copies soumises partent une à une, espacées de ce nombre de secondes, pour rester sous la limite de requêtes du fournisseur. 0 : pas d'espacement. Les retours formatifs ne sont pas espacés. »).
- Produces: config `mod_gestionprojet/ai_next_slot` (horodatage du dernier créneau donné).

- [ ] **Step 1: tests** — `slot_time(1000, 0, 6)` = 1000 ; `slot_time(1000, 998, 6)` = 1004 ; `slot_time(1000, 5000, 6)` = 5006 ; `slot_time(1000, 1004, 0)` = 1000 ; source : `queue_evaluation` appelle `set_next_run_time(` seulement quand `!$formatif`, et met `ai_next_slot` à jour.
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** dans `queue_evaluation`, pour une évaluation non formative : `$slot = self::slot_time(time(), (int)get_config('mod_gestionprojet', 'ai_next_slot'), (int)(get_config('mod_gestionprojet', 'ai_interval') ?? 6))` (valeur absente = 6), `set_config('ai_next_slot', $slot, 'mod_gestionprojet')`, `$task->set_next_run_time($slot)`.
- [ ] **Step 4:** PASS, suite verte.
- [ ] **Step 5:** commit `feat(ai): graded corrections leave one every 6 s (request 40)`.

### Task 4: niveau enregistré à l'application d'une correction

**Files:**
- Modify: `gestionprojet/classes/palier.php`, `gestionprojet/lib.php`, `gestionprojet/classes/ai_evaluator.php` (`apply_evaluation`)
- Test: `gestionprojet/tests/pure/test_level_record.php`

**Interfaces:**
- Consumes: `palier::level_reached(array $criteria, array $parts, int $seuil): ?array` (existant).
- Produces: `palier::levels_up_to(string $reached, array $ingrid): string[]` — niveaux de `$ingrid` (ordre `ORDER`) jusqu'à `$reached` inclus ; `''` donne `[]`.
- Produces: `gestionprojet_level_sync(object $gestionprojet, object $evaluation): void` dans `lib.php` — ne fait rien si l'étape n'est pas 4, si `step4_paliers = 1`, si l'évaluation est formative ou de palier, si la note appliquée diffère de `parsed_grade` de plus de 0,01 (note retouchée par le professeur : les niveaux restent tels quels, il peut en retirer un à la main ; anti-objectif « niveau qui contredit la note »), ou si `level_reached` renvoie `null` ; sinon, sous `gestionprojet_palier_lock`, pour le propriétaire de la copie (`gestionprojet_palier_owner`) : insère une ligne (membres comme `gestionprojet_palier_record_locked`, `evaluationid` = l'évaluation) pour chaque niveau gardé absent, passe à `retire = 1` les lignes actives des autres niveaux. Ne gèle aucune zone (`zones_validees` reste propre au mode paliers).

- [ ] **Step 1: tests** — `levels_up_to('S', ['F','S','TS'])` = `['F','S']` ; `levels_up_to('S', ['S','TS'])` = `['S']` ; `levels_up_to('', ['F','S'])` = `[]` ; `levels_up_to('TS', ['F','TS'])` = `['F','TS']`. Source : `apply_evaluation` appelle `gestionprojet_level_sync(` après la mise à jour de la copie ; `gestionprojet_level_sync` contient `'retire' => 1` via `set_field` et jamais `delete_records`.
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** écrire `levels_up_to`, `gestionprojet_level_sync`, l'appel dans `apply_evaluation` (avec `require_once` de `lib.php` comme ailleurs dans la classe).
- [ ] **Step 4:** PASS, suite verte.
- [ ] **Step 5:** commit `feat(level): the level is recorded when the correction is applied (request 39)`.

### Task 5: « Niveau … validé » pour l'élève

**Files:**
- Modify: `gestionprojet/lib.php` (`gestionprojet_progress_state`, `gestionprojet_level_banner_html`), `lang/en|fr/gestionprojet.php`
- Test: `gestionprojet/tests/pure/test_level_record.php` (suite)

**Interfaces:**
- Consumes: lignes actives de `gestionprojet_palier` du propriétaire de la copie.
- Produces: clé `'validated'` (bool) dans le tableau `level` de `$feedback` ; chaîne `level_validated` (fr : « Niveau validé : {$a->acronym} ({$a->name}) » ; en : « Level validated: {$a->acronym} ({$a->name}) »).

- [ ] **Step 1: tests (source)** — copie soumise (`status = 1`) hors mode paliers avec une ligne active : `level` = niveau le plus haut des lignes actives, `validated = true`, `next`/`missing` vides ; le bandeau affiche `✓` puis `level_validated`, échappé par `s()` comme les autres phrases ; chaînes présentes en/fr.
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** implémenter.
- [ ] **Step 4:** PASS, suite verte.
- [ ] **Step 5:** commit `feat(level): the student reads "Niveau … validé" after submission (request 39)`.

### Task 6: essai en préprod et livraison

- [ ] **Step 1:** déployer en préprod (`/Volumes/DONNEES/.claude-tmp/gp-deploy/deploy.sh preprod`).
- [ ] **Step 2:** script CLI en préprod sur une **instance temporaire créée puis supprimée** (jamais une copie existante, mémoire « tests CLI sur données réelles ») : deux groupes avec des dates différentes ; vérifier `gestionprojet_effective_deadline` ; lancer la tâche `auto_submit_deadline` : seules les copies du groupe dont la date est passée sont soumises ; leurs tâches de correction ont des `nextruntime` espacés de 6 s ; appliquer une évaluation construite (critères complets) : lignes F et S créées ; réappliquer plus bas : S passe à `retire = 1`. Supprimer l'instance.
- [ ] **Step 3:** relecture indépendante (D-014), corrections, puis demande de fusion, fusion, suite sur `main`, production sur demande d'Emmanuel.
- [ ] **Step 4:** vault : demandes 39-40 dans `mod-gestionprojet-demandes.md`, ligne de `a-traiter.md`, journal ; prévenir l'agent des activités du nom et de la forme de la table des dates (ligne « après les demandes 39 et 40 »).
