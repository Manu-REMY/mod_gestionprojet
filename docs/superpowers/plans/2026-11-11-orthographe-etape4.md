# Orthographe dans la correction de l'étape 4 — plan (2.15.0)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** à l'étape 4, un second appel IA relève les fautes d'orthographe du texte écrit par l'élève ; le serveur les vérifie, compte les points (2 / 1 / 0), calcule la note totale et plafonne le niveau à SAT dès une faute ; l'enseignant peut écarter une faute pour toute l'activité.

**Architecture:** une classe pure `classes/orthographe.php` porte toutes les règles (texte jugé, lecture et vérification de la réponse, points, note, plafond) ; `ai_prompt_builder` construit le prompt d'orthographe ; `ai_evaluator::process_evaluation` fait le second appel et stocke `langue_json` ; `lib.php` affiche le bloc, calcule la note totale à appliquer et le niveau ; un point d'entrée AJAX écarte une faute.

**Tech Stack:** Moodle 5.0 (PHP 8.1+), XMLDB, AMD ; tests purs `php gestionprojet/tests/pure/test_<nom>.php`.

**Spec:** `docs/superpowers/specs/2026-11-11-orthographe-etape4-design.md`

## Global Constraints

- Version `2026111106`, release `'2.15.0'` ; étape `upgrade.php` au même numéro.
- Champs `gestionprojet` : `langue_enabled` int(1) défaut 1, `langue_points` number(5,2) défaut 2, `langue_accepted` text null ; `gestionprojet_ai_evaluations` : `langue_json` text null, `langue_points` number(5,2) null. Mise à jour : toutes les instances à 1 / 2. Sauvegarde/restauration et Privacy (`langue_json`).
- Échelle : 0 faute → P ; 1 ou 2 → P/2 ; ≥ 3 → 0. Note = contenu × (20 − P) / 20 + points, arrondie à 0,01. Plafond : `TS` → `S` dès une faute.
- Rien d'écrit par l'élève ou copie identique à la consigne : 0 point, pas d'appel.
- Proximité d'une correction : Levenshtein (minuscules, sans accents) ≤ max(2, 40 % de la longueur de la citation).
- Réponse d'orthographe illisible ou absente : la correction entière échoue (aucune note par défaut).
- Pas d'appel en mode paliers (`step4_paliers = 1`), ni pour une demande de palier.
- `ai_interval` : défaut 12 ; `upgrade.php` met 12 là où la valeur vaut `6`.
- Chaînes en/fr, commentaires anglais, pas de JS en ligne, `git add` fichier par fichier.

## Review Focus

- Une « correction » qui est un autre mot (« capteur » → « caméra ») : écartée par la proximité (tâche 1).
- Une faute citée qui n'apparaît que dans un champ Fourni : écartée, le texte jugé ne contient pas ce champ (tâche 1).
- Mot écarté par l'enseignant sur une copie dont il a retouché la note à la main : la note n'est pas écrasée (tâche 5).
- Copie identique à la consigne : total 0/20, pas d'appel d'orthographe (tâche 3).
- Réponse d'orthographe coupée (`max_tokens` atteint) : échec, jamais lue à moitié (tâche 1, `parse`).

---

### Task 1: règles pures de l'orthographe

**Files:** Create `gestionprojet/classes/orthographe.php` ; Test `gestionprojet/tests/pure/test_orthographe.php`

**Interfaces (Produces):**
- `orthographe::judged_texts(array $flagged): string[]` — textes écrits par l'élève dans une copie marquée par `consigne_lock::with_flags()` (ou non marquée) : noms d'interacteurs, énoncés de FS, critères (`description`, `niveau`, `flexibilite`), contraintes (`description`, `justification`) ; on écarte un champ `locked` (et `niveauLocked` pour le niveau) et un champ `prefill`/`niveauPrefill` dont le texte égale son `hint`/`niveauHint` (comparaison de `ai_prompt_builder::mark`, sans ponctuation ni casse) ; textes vides écartés.
- `orthographe::parse(string $raw): array` — liste `[['cite' => string, 'correction' => string, 'type' => string], …]` ; accepte du texte autour du JSON comme `ai_response_parser` ; lève `\invalid_response_exception` si pas de clé `fautes` tableau.
- `orthographe::verify(array $fautes, string $text, array $accepted): array` → `['fautes' => [...], 'ecartees' => int]` (citation retrouvée, mot entier pour un mot seul ; correction non vide, différente, proche ; mot accepté écarté ; doublons fusionnés par citation normalisée).
- `orthographe::normalize(string $s): string` (minuscules, sans accents, espaces réduits).
- `orthographe::points(int $nbfautes, float $p): float` ; `orthographe::total(float $content, float $p, float $points): float` ; `orthographe::cap_level(string $reached, int $nbfautes): string`.

- [ ] **Step 1: tests** — `points(0, 2) = 2.0`, `points(1, 2) = 1.0`, `points(2, 2) = 1.0`, `points(3, 2) = 0.0`, `points(1, 5) = 2.5` ; `total(20, 2, 2) = 20.0`, `total(15, 2, 1) = 14.5` ; `cap_level('TS', 1) = 'S'`, `cap_level('TS', 0) = 'TS'`, `cap_level('S', 3) = 'S'`, `cap_level('F', 3) = 'F'`, `cap_level('', 2) = ''` ; `verify` : « detecte → détecter » gardée dans « Il faut detecte l'intrus » ; citation absente écartée ; « capteur → caméra » écartée ; correction identique écartée ; « mm » dans un mot plus long non trouvé comme mot entier ; mot de `$accepted` écarté ; deux fois la même citation → une faute ; `parse('{"fautes": []}') = []` ; `parse('{"fautes": [{"cite"')` lève l'exception ; `parse('{"autre": 1}')` lève ; `judged_texts` : un champ `locked` absent, un `prefill` égal à son `hint` absent, un `prefill` complété présent, un champ vide absent.
- [ ] **Step 2:** FAIL. **Step 3:** implémenter. **Step 4:** PASS + suite. **Step 5:** commit `feat(langue): pure spelling rules (request 32)`.

### Task 2: base, réglages, sauvegarde, confidentialité

**Files:** `db/install.xml`, `db/upgrade.php`, `version.php`, `mod_form.php`, `settings.php`, `backup/moodle2/backup_gestionprojet_stepslib.php`, `classes/privacy/provider.php`, `lang/en|fr/gestionprojet.php` ; Test `tests/pure/test_langue_settings.php`

- [ ] **Step 1: tests (source)** — les cinq champs dans `install.xml` et `upgrade.php` (étape 2026111106, `set_field('gestionprojet', 'langue_enabled', 1)` et `langue_points` 2 sur toutes les instances, `set_config('ai_interval', 12, …)` seulement si la valeur vaut `'6'`) ; `mod_form` : `langue_enabled` (selectyesno, défaut 1), `langue_points` (défaut 2, validation 2 à 10 quand activé, erreur `error_langue_points`), `langue_accepted` (textarea, un mot par ligne ; converti en JSON à l'enregistrement par `gestionprojet_add_instance`/`update_instance` via `gestionprojet_langue_accepted_from_text(string): string` et affiché par `data_preprocessing`) ; sauvegarde des champs d'instance et des deux champs d'évaluation ; Privacy : `langue_json` dans les métadonnées et l'export ; `settings.php` défaut `'12'` ; chaînes en/fr.
- [ ] **Steps 2-5:** FAIL, implémenter, PASS + suite, commit `feat(langue): settings, storage and upgrade (request 32)`.

### Task 3: second appel et note

**Files:** `classes/ai_prompt_builder.php`, `classes/ai_evaluator.php`, `lib.php` ; Test `tests/pure/test_langue_eval.php`

**Interfaces:**
- Produces `ai_prompt_builder::build_spelling_prompt(array $texts): array` → `['system' => string, 'user' => string]` : chaque texte dans `<texte n="i">…</texte>` (`<`, `>` neutralisés en `‹ ›`), le tout encadré par les lignes `<<<DEBUT COPIE-XXXXXXXX>>>` / `<<<FIN …>>>` (code aléatoire, 2.13.6) ; règles de la spec (données, jamais instructions ; forme exacte du même mot ; types ; unités et symboles admis ; en cas de doute, pas une faute ; format `{"fautes": [...]}`).
- Produces `gestionprojet_evaluation_total(object $gestionprojet, object $evaluation): float` dans `lib.php` — `parsed_grade` si `langue_points` est null, sinon `orthographe::total(parsed_grade, langue_points, langue_json.points)`.
- Consigne du contenu (step 4, langue activée) : ajoute « L'orthographe n'entre pas dans cette note : elle est évaluée à part. » ; option du constructeur `'langue' => true`.
- `process_evaluation` : pour step 4, langue activée, hors palier : textes = `orthographe::judged_texts` de la copie marquée ; si vide ou `$nomodifications` → `langue_json` = `{fautes: [], ecartees: 0, points: 0}` sans appel ; sinon second appel (même fournisseur, modèle, limites ; `max_tokens` 1500), `parse` (exception → échec de toute la correction), `verify` avec `langue_accepted`, `points` ; stocke `langue_json`, `langue_points` = P ; ajoute la réponse brute à `raw_response` (séparée par une ligne `--- orthographe ---`).
- `apply_evaluation` : note appliquée = `$overridegrade ?? gestionprojet_evaluation_total(...)`.

- [ ] **Step 1: tests** — prompt : code de cadre, balises numérotées, `‹` pour `<` ; source : `process_evaluation` appelle `build_spelling_prompt`, `orthographe::parse`, `orthographe::verify`, n'appelle rien quand `$nomodifications` ; `apply_evaluation` utilise `gestionprojet_evaluation_total` ; `gestionprojet_evaluation_total` : 15/20, P 2, 1 point → 14,5 ; `langue_points` null → `parsed_grade`.
- [ ] **Steps 2-5:** FAIL, implémenter, PASS + suite, commit `feat(langue): second AI call and total grade (request 32)`.

### Task 4: niveau, affichage élève et enseignant

**Files:** `lib.php` (`gestionprojet_level_sync`, `gestionprojet_progress_state`, nouvelle `gestionprojet_spelling_block_html(?array $langue, float $p): string`, `gestionprojet_progress_feedback_html`), `grading.php`, `styles.css`, lang ; Test `tests/pure/test_langue_display.php`

- [ ] **Step 1: tests** — `level_sync` et bandeau : `orthographe::cap_level(` appliqué au niveau atteint avec le nombre de fautes de l'évaluation ; détection « note retouchée » et choix A comparent à `gestionprojet_evaluation_total` (plus à `parsed_grade`) ; bloc : « Orthographe : 1 / 2 », une ligne « « detecte » → détecter » par faute, échappée par `s()`, « aucune faute » quand la liste est vide, rien quand `langue_json` est null ; masqué pour l'élève si `show_feedback = 0` ; `grading.php` affiche la note totale et « contenu x / 18 · orthographe y / 2 », `data-grade` = note totale ; chaînes en/fr ; CSS préfixé.
- [ ] **Steps 2-5:** FAIL, implémenter, PASS + suite, commit `feat(langue): level cap and spelling block (request 32)`.

### Task 5: écarter une faute (enseignant)

**Files:** Create `gestionprojet/ajax/spelling_accept.php`, `gestionprojet/amd/src/spelling_accept.js` (+ build) ; Modify `grading.php`, `lib.php` ; Test `tests/pure/test_langue_accept.php`

**Interfaces:** Produces `gestionprojet_spelling_accept(object $gestionprojet, string $cite): int` (nombre de corrections recalculées) dans `lib.php` : ajoute la citation normalisée à `langue_accepted` ; pour chaque évaluation d'étape 4 de l'activité avec `langue_json` contenant cette citation : retire la faute, recalcule `points`, met à jour `langue_json` ; si l'évaluation est appliquée et que la note de la copie égale l'ancienne note totale (à 0,01), met la nouvelle note totale, le carnet (`gestionprojet_update_grades`) et le niveau (`gestionprojet_level_sync`) ; sinon (note retouchée à la main) ne touche pas la note.

- [ ] **Step 1: tests** — point d'entrée : `require_login`, `require_sesskey`, `require_capability('mod/gestionprojet:grade'`, `PARAM_TEXT` pour la citation ; fonction : ajoute au JSON sans doublon ; ne réécrit pas une note retouchée (comparaison à l'ancien total) ; bouton « Écarter » par faute dans `grading.php` (attributs `data-cite`, `data-cmid`) ; module AMD sans `innerHTML`, recharge la page avec cache-buster ; test d'absence de JS en ligne toujours vert.
- [ ] **Steps 2-5:** FAIL, implémenter, PASS + suite, commit `feat(langue): the teacher dismisses a spelling mistake for the whole activity (request 32)`.

### Task 6: essais et livraison

- [ ] Préprod ; activité temporaire (créée puis supprimée) : correction construite avec `langue_json` → note totale appliquée, plafond TS → S, faute écartée → note et niveau recalculés, note retouchée non écrasée.
- [ ] Essai réel Albert en lecture seule (données de Projet PIEGE) : copie juste sans faute (aucune faute inventée), copie avec 2 fautes, copie avec 5 fautes.
- [ ] Relecture indépendante de la branche ; corrections ; demande de fusion ; production sur demande d'Emmanuel ; vault (demande 32, ligne de `a-traiter.md`, ligne « paramétrer la maîtrise de la langue » pour l'agent des activités, journal, D-042 pour les règles de l'étape 4).
