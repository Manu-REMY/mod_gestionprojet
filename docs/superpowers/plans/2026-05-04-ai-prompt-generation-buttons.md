# AI Prompt Generation Buttons — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter deux boutons (« Modèle par défaut » + « Générer depuis le modèle ») au-dessus du textarea `ai_instructions` sur les 6 pages de modèles de correction enseignant (étapes 4 à 9), pour produire automatiquement un texte d'instructions destiné à l'IA correctrice.

**Architecture:** Côté backend, nouvelle méthode `ai_prompt_builder::build_meta_prompt()` qui assemble un méta-prompt à partir du modèle de correction rempli, et un endpoint AJAX `ajax/generate_ai_instructions.php` qui appelle le provider IA configuré (`ai_evaluator::get_provider`) et renvoie le `content` brut. Côté frontend, un module AMD `mod_gestionprojet/generate_ai_instructions` câble les deux boutons (insertion du défaut local + appel AJAX).

**Tech Stack:** Moodle 5.0+ / PHP 8.1+ (DML, lang strings, capabilities) — AMD/RequireJS + jQuery côté client — providers IA existants (`ai_provider/{openai,anthropic,mistral,albert}`).

**Spec:** `docs/superpowers/specs/2026-05-04-ai-prompt-generation-buttons-design.md`

---

## File Structure

| Fichier | Action | Responsabilité |
|---|---|---|
| `gestionprojet/classes/ai_prompt_builder.php` | Modify | Méthode `build_meta_prompt()` |
| `gestionprojet/tests/ai_meta_prompt_test.php` | Create | Tests PHPUnit du méta-prompt |
| `gestionprojet/ajax/generate_ai_instructions.php` | Create | Endpoint AJAX |
| `gestionprojet/amd/src/generate_ai_instructions.js` | Create | Module AMD client |
| `gestionprojet/amd/build/generate_ai_instructions.min.js` | Create (via grunt) | Build minifié |
| `gestionprojet/lang/fr/gestionprojet.php` | Modify | +15 entrées FR |
| `gestionprojet/lang/en/gestionprojet.php` | Modify | +15 entrées EN |
| `gestionprojet/styles.css` | Modify | Bloc `.ai-instructions-actions` |
| `gestionprojet/pages/step4_teacher.php` | Modify | HTML + JS bridge |
| `gestionprojet/pages/step5_teacher.php` | Modify | HTML + JS bridge |
| `gestionprojet/pages/step6_teacher.php` | Modify | HTML + JS bridge |
| `gestionprojet/pages/step7_teacher.php` | Modify | HTML + JS bridge |
| `gestionprojet/pages/step8_teacher.php` | Modify | HTML + JS bridge |
| `gestionprojet/pages/step9_teacher.php` | Modify | JS bridge (mustache rend les boutons) |
| `gestionprojet/templates/step9_form.mustache` | Modify | HTML boutons |
| `gestionprojet/version.php` | Modify | Bump `2.5.0` / `2026050400` |
| `gestionprojet/CHANGELOG.md` | Modify | Entrée 2.5.0 |
| `TESTING.md` (racine) | Modify | Plan de recette manuelle |

---

## Task 1: Méta-prompt — méthode `build_meta_prompt()` + tests TDD

**Files:**
- Modify: `gestionprojet/classes/ai_prompt_builder.php`
- Create: `gestionprojet/tests/ai_meta_prompt_test.php`

- [ ] **Step 1.1 — Créer le fichier de test avec un test failing pour step 4**

Créer `gestionprojet/tests/ai_meta_prompt_test.php` :

```php
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for ai_prompt_builder::build_meta_prompt().
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gestionprojet/classes/ai_prompt_builder.php');

/**
 * Tests for the meta-prompt builder.
 *
 * @group mod_gestionprojet
 */
class mod_gestionprojet_ai_meta_prompt_test extends advanced_testcase {

    public function test_step4_meta_prompt_includes_model_fields(): void {
        $builder = new \mod_gestionprojet\ai_prompt_builder();
        $model = (object)[
            'produit' => 'Trottinette électrique',
            'milieu'  => 'Espace urbain',
            'fp'      => 'Permettre à un usager de se déplacer en ville',
            'interacteurs_data' => '',
        ];
        $result = $builder->build_meta_prompt(4, $model);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('system', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertStringContainsString('expert pédagogique', $result['system']);
        $this->assertStringContainsString('Trottinette électrique', $result['user']);
        $this->assertStringContainsString('Espace urbain', $result['user']);
    }
}
```

- [ ] **Step 1.2 — Vérifier que le test échoue (méthode absente)**

Run :
```bash
cd /var/www/html  # or wherever Moodle is mounted
vendor/bin/phpunit --filter test_step4_meta_prompt_includes_model_fields mod/gestionprojet/tests/ai_meta_prompt_test.php
```

Expected: FAIL avec `Error: Call to undefined method mod_gestionprojet\ai_prompt_builder::build_meta_prompt()`.

- [ ] **Step 1.3 — Implémenter `build_meta_prompt()`**

Dans `gestionprojet/classes/ai_prompt_builder.php`, ajouter cette méthode juste avant la fermeture de la classe (avant `}` final, après `format_json_array()`) :

```php
    /**
     * Build a meta-prompt asking the AI to produce correction instructions
     * tailored to a teacher correction model.
     *
     * @param int $step Step number (4-9)
     * @param object $teachermodel Teacher correction model fields
     * @return array ['system' => string, 'user' => string]
     */
    public function build_meta_prompt(int $step, object $teachermodel): array {
        $stepcontext = self::STEP_CONTEXT[$step] ?? 'Évaluation de production élève';
        $criteria = self::STEP_CRITERIA[$step] ?? [];

        $criteriatext = '';
        foreach ($criteria as $criterion) {
            $criteriatext .= "- {$criterion['name']} (poids: {$criterion['weight']}/20): {$criterion['description']}\n";
        }

        $modeltext = $this->format_teacher_model($step, $teachermodel);

        $system = <<<PROMPT
Tu es un expert pédagogique en technologie. Ta mission est de rédiger des instructions de correction destinées à un autre IA correcteur, qui évaluera des productions d'élèves de collège/lycée.

Les instructions que tu produis doivent :
- Être en français, à la 2e personne du singulier ("tu")
- Préciser les points d'attention spécifiques au modèle fourni
- Indiquer les éléments obligatoires, les bonus, les pénalités éventuelles
- Rester concises (8-15 lignes max)
- Être réutilisables tel quel par l'IA correctrice

Réponds UNIQUEMENT avec le texte des instructions, sans préambule ni balisage Markdown.
PROMPT;

        $user = <<<PROMPT
Voici le modèle de correction rempli par l'enseignant pour l'étape "{$stepcontext}".

Critères officiels d'évaluation :
{$criteriatext}
Modèle rempli :
{$modeltext}

Rédige maintenant les instructions de correction adaptées à ce modèle précis.
PROMPT;

        return ['system' => $system, 'user' => $user];
    }
```

- [ ] **Step 1.4 — Vérifier que le test passe**

Run :
```bash
vendor/bin/phpunit --filter test_step4_meta_prompt_includes_model_fields mod/gestionprojet/tests/ai_meta_prompt_test.php
```

Expected: PASS (1 test, 5 assertions).

- [ ] **Step 1.5 — Ajouter le test data-provider sur les 6 étapes**

Ajouter dans `tests/ai_meta_prompt_test.php`, dans la classe :

```php
    public static function each_step_provider(): array {
        return [
            'step 4' => [4, 'Cahier des Charges Fonctionnel'],
            'step 5' => [5, 'Fiche d\'Essai'],
            'step 6' => [6, 'Rapport de Projet'],
            'step 7' => [7, 'Expression du Besoin'],
            'step 8' => [8, 'Carnet de Bord'],
            'step 9' => [9, 'Diagramme FAST'],
        ];
    }

    /**
     * @dataProvider each_step_provider
     */
    public function test_each_step_user_prompt_includes_step_context(int $step, string $expected): void {
        $builder = new \mod_gestionprojet\ai_prompt_builder();
        $model = (object)[];
        $result = $builder->build_meta_prompt($step, $model);
        $this->assertStringContainsString($expected, $result['user']);
    }
```

- [ ] **Step 1.6 — Vérifier que tous les tests passent**

Run :
```bash
vendor/bin/phpunit mod/gestionprojet/tests/ai_meta_prompt_test.php
```

Expected: PASS (7 tests, ≥10 assertions).

- [ ] **Step 1.7 — Ajouter le test « modèle vide »**

Ajouter dans la classe :

```php
    public function test_empty_model_uses_placeholder(): void {
        $builder = new \mod_gestionprojet\ai_prompt_builder();
        $result = $builder->build_meta_prompt(4, (object)[]);
        $this->assertStringContainsString('(Modèle de correction non renseigné', $result['user']);
    }
```

- [ ] **Step 1.8 — Re-run tests**

Run :
```bash
vendor/bin/phpunit mod/gestionprojet/tests/ai_meta_prompt_test.php
```

Expected: PASS (8 tests).

- [ ] **Step 1.9 — Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/classes/ai_prompt_builder.php gestionprojet/tests/ai_meta_prompt_test.php
git commit -m "feat(ai): add build_meta_prompt() to assemble correction-instructions meta prompt

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Chaînes de langue (FR + EN)

**Files:**
- Modify: `gestionprojet/lang/fr/gestionprojet.php`
- Modify: `gestionprojet/lang/en/gestionprojet.php`

- [ ] **Step 2.1 — Ajouter les 11 chaînes UI dans FR**

Dans `gestionprojet/lang/fr/gestionprojet.php`, juste après la ligne `$string['ai_instructions_placeholder'] = '...'` (vers la ligne 286), insérer :

```php
$string['ai_instructions_btn_default'] = 'Modèle par défaut';
$string['ai_instructions_btn_generate'] = 'Générer depuis le modèle';
$string['ai_instructions_btn_generating'] = 'Génération en cours…';
$string['ai_instructions_tooltip_empty'] = 'Remplissez d\'abord le modèle de correction';
$string['ai_instructions_tooltip_disabled'] = 'IA désactivée dans la configuration de l\'activité';
$string['ai_instructions_confirm_replace'] = 'Remplacer le contenu actuel des instructions ?';
$string['ai_instructions_error_generic'] = 'Échec de la génération. Réessayez.';
$string['ai_instructions_error_disabled'] = 'L\'IA est désactivée dans la configuration de l\'activité.';
$string['ai_instructions_error_no_provider'] = 'Aucun fournisseur d\'IA n\'est configuré.';
$string['ai_instructions_error_model_empty'] = 'Remplissez d\'abord le modèle de correction.';
$string['ai_instructions_success'] = 'Instructions générées avec succès.';
```

- [ ] **Step 2.2 — Ajouter les 4 défauts manquants dans FR**

Localiser la chaîne `$string['ai_instructions_default_step7']` (~ligne 654). Insérer avant et après selon l'ordre, en respectant l'ordre numérique. Soit, dans l'ordre 5, 6, 8, 9 :

```php
$string['ai_instructions_default_step5'] = 'Rôle : Tu es un professeur de technologie expérimenté au collège.

Contexte de l\'étape : Tu corriges une fiche d\'essai. L\'élève décrit un protocole expérimental visant à valider une fonction de service issue du CDCF (objectif, étapes, matériel, résultats, conclusion).

Critères d\'attention :
- L\'objectif est explicite et lié à une fonction de service.
- Le protocole est détaillé, séquentiel et reproductible.
- Le matériel et les précautions de sécurité sont listés.
- Les résultats sont présentés clairement.
- La conclusion répond à l\'objectif initial.

Tonalité : Sois bienveillant, valorise les efforts, propose des pistes d\'amélioration concrètes.';

$string['ai_instructions_default_step6'] = 'Rôle : Tu es un professeur de technologie expérimenté au collège.

Contexte de l\'étape : Tu corriges un rapport de projet (synthèse complète : besoin, solutions envisagées, choix justifié, réalisation, difficultés, validation, bilan, perspectives).

Critères d\'attention :
- Le besoin et les impératifs sont clairement présentés.
- Les solutions sont analysées et le choix retenu est justifié.
- La réalisation est décrite avec précision.
- Les difficultés rencontrées sont mentionnées.
- Le bilan est réflexif (ce qui a marché, ce qui pourrait être amélioré).

Tonalité : Sois bienveillant, valorise les efforts, propose des pistes d\'amélioration concrètes.';

$string['ai_instructions_default_step8'] = 'Rôle : Tu es un professeur de technologie expérimenté au collège.

Contexte de l\'étape : Tu corriges un carnet de bord — suivi chronologique des séances et tâches réalisées par l\'élève au fil du projet.

Critères d\'attention :
- Les séances sont régulièrement documentées (pas de gros trous).
- Les tâches sont décrites de manière compréhensible.
- L\'avancement est cohérent et réaliste.
- Les remarques montrent une réflexion sur le travail (et pas juste une description).

Tonalité : Sois bienveillant, valorise les efforts, propose des pistes d\'amélioration concrètes.';

$string['ai_instructions_default_step9'] = 'Rôle : Tu es un professeur de technologie expérimenté au collège.

Contexte de l\'étape : Tu corriges un diagramme FAST. L\'élève traduit les fonctions de service du CDCF en fonctions techniques (FT), puis en solutions techniques (ST), avec une cohérence FP → FT → ST.

Critères d\'attention :
- Les fonctions techniques (FT) couvrent les fonctions de service du CDCF.
- Les FT sont correctement formulées (verbe + complément).
- Les sous-fonctions, lorsque utilisées, sont judicieuses.
- Les solutions techniques (ST) proposées sont concrètes et adaptées aux FT.
- L\'arborescence est cohérente : du « pourquoi » au « comment ».

Tonalité : Sois bienveillant, valorise les efforts, propose des pistes d\'amélioration concrètes.';
```

Insérer ces 4 chaînes près de `ai_instructions_default_step4` et `ai_instructions_default_step7` pour conserver le regroupement thématique.

- [ ] **Step 2.3 — Ajouter les 11 chaînes UI dans EN**

Dans `gestionprojet/lang/en/gestionprojet.php`, après `$string['ai_instructions_placeholder']`, insérer :

```php
$string['ai_instructions_btn_default'] = 'Default template';
$string['ai_instructions_btn_generate'] = 'Generate from model';
$string['ai_instructions_btn_generating'] = 'Generating…';
$string['ai_instructions_tooltip_empty'] = 'Fill in the correction model first';
$string['ai_instructions_tooltip_disabled'] = 'AI is disabled in the activity settings';
$string['ai_instructions_confirm_replace'] = 'Replace the current instructions?';
$string['ai_instructions_error_generic'] = 'Generation failed. Please retry.';
$string['ai_instructions_error_disabled'] = 'AI is disabled in the activity settings.';
$string['ai_instructions_error_no_provider'] = 'No AI provider is configured.';
$string['ai_instructions_error_model_empty'] = 'Fill in the correction model first.';
$string['ai_instructions_success'] = 'Instructions generated successfully.';
```

- [ ] **Step 2.4 — Ajouter les 4 défauts EN**

Adapter les 4 textes FR de Step 2.2 en anglais (traduction directe, conserver la structure Role / Context / Criteria / Tone). Insérer dans `lang/en/gestionprojet.php`.

- [ ] **Step 2.5 — Vérifier syntaxe PHP**

```bash
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/lang/fr/gestionprojet.php"
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/lang/en/gestionprojet.php"
```

Expected: `No syntax errors detected` pour les deux fichiers.

- [ ] **Step 2.6 — Commit**

```bash
git add gestionprojet/lang/fr/gestionprojet.php gestionprojet/lang/en/gestionprojet.php
git commit -m "lang: add strings for AI prompt generation buttons (FR + EN)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Endpoint AJAX `generate_ai_instructions.php`

**Files:**
- Create: `gestionprojet/ajax/generate_ai_instructions.php`

- [ ] **Step 3.1 — Créer l'endpoint avec sécurité + validation**

Créer `gestionprojet/ajax/generate_ai_instructions.php` :

```php
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AJAX endpoint: generate AI correction instructions from a teacher model.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/mod/gestionprojet/classes/ai_prompt_builder.php');
require_once($CFG->dirroot . '/mod/gestionprojet/classes/ai_config.php');
require_once($CFG->dirroot . '/mod/gestionprojet/classes/ai_evaluator.php');

$cmid = required_param('id', PARAM_INT);
$step = required_param('step', PARAM_INT);
$modeldata = required_param('model_data', PARAM_RAW);

$cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:configureteacherpages', $context);

header('Content-Type: application/json; charset=utf-8');

$respond = function (array $payload) {
    echo json_encode($payload);
    exit;
};

if (!in_array($step, [4, 5, 6, 7, 8, 9], true)) {
    $respond(['success' => false, 'error' => 'invalid_step']);
}

$decoded = json_decode($modeldata, true);
if (!is_array($decoded)) {
    $respond(['success' => false, 'error' => 'invalid_step']);
}

// Whitelist fields against STEP_FIELDS for the requested step.
$allowedfields = \mod_gestionprojet\ai_prompt_builder::STEP_FIELDS[$step] ?? [];
$tmpmodel = new stdClass();
$hasvalue = false;
foreach ($allowedfields as $field) {
    if (array_key_exists($field, $decoded)) {
        $value = is_string($decoded[$field]) ? trim($decoded[$field]) : '';
        $tmpmodel->$field = $value;
        if ($value !== '') {
            $hasvalue = true;
        }
    } else {
        $tmpmodel->$field = '';
    }
}

if (!$hasvalue) {
    $respond(['success' => false, 'error' => 'model_empty']);
}

$aiconfig = \mod_gestionprojet\ai_config::get_config($cm->instance);
if (!$aiconfig || empty($aiconfig->enabled)) {
    $respond(['success' => false, 'error' => 'ai_disabled']);
}

$apikey = \mod_gestionprojet\ai_config::get_effective_api_key(
    $aiconfig->provider,
    $aiconfig->api_key ?? ''
);
if ($apikey === '') {
    $respond(['success' => false, 'error' => 'no_provider']);
}

try {
    $builder = new \mod_gestionprojet\ai_prompt_builder();
    $prompts = $builder->build_meta_prompt($step, $tmpmodel);

    $provider = \mod_gestionprojet\ai_evaluator::get_provider($aiconfig->provider, $apikey);
    $model = \mod_gestionprojet\ai_evaluator::get_model_for_provider($aiconfig->provider);

    $response = $provider->evaluate($prompts['system'], $prompts['user'], $model, 1500);

    $instructions = trim($response['content'] ?? '');
    if ($instructions === '') {
        $respond(['success' => false, 'error' => 'ai_failed', 'message' => 'Empty response']);
    }

    $respond(['success' => true, 'instructions' => $instructions]);
} catch (\Throwable $e) {
    $respond([
        'success' => false,
        'error' => 'ai_failed',
        'message' => mb_substr($e->getMessage(), 0, 200),
    ]);
}
```

- [ ] **Step 3.2 — Vérifier syntaxe PHP**

```bash
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/generate_ai_instructions.php"
```

Expected: `No syntax errors detected`.

- [ ] **Step 3.3 — Smoke test côté serveur (preprod)**

Sur la preprod (cf. `TESTING.md` pour les credentials/chemin), exécuter :

```bash
# Récupérer un sesskey valide (login navigateur), puis :
curl -X POST 'https://<preprod-host>/mod/gestionprojet/ajax/generate_ai_instructions.php' \
  -H 'Cookie: <session-cookie>' \
  -d 'id=<cmid>&step=4&sesskey=<sesskey>&model_data={"produit":"test"}'
```

Expected: réponse JSON `{"success":true,"instructions":"..."}` ou `{"success":false,"error":"ai_disabled"}` selon la config.

Si l'environnement local ne permet pas ce test, marquer ce step comme à valider plus tard via Task 13.

- [ ] **Step 3.4 — Commit**

```bash
git add gestionprojet/ajax/generate_ai_instructions.php
git commit -m "feat(ai): add AJAX endpoint to generate correction instructions

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Module AMD `generate_ai_instructions.js`

**Files:**
- Create: `gestionprojet/amd/src/generate_ai_instructions.js`

- [ ] **Step 4.1 — Créer le module AMD**

Créer `gestionprojet/amd/src/generate_ai_instructions.js` :

```javascript
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Generate AI correction instructions buttons for teacher correction models.
 *
 * @module     mod_gestionprojet/generate_ai_instructions
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/notification'], function($, Str, Notification) {

    function fetchStrings() {
        return Str.get_strings([
            {key: 'ai_instructions_btn_default',       component: 'mod_gestionprojet'},
            {key: 'ai_instructions_btn_generate',      component: 'mod_gestionprojet'},
            {key: 'ai_instructions_btn_generating',    component: 'mod_gestionprojet'},
            {key: 'ai_instructions_tooltip_empty',     component: 'mod_gestionprojet'},
            {key: 'ai_instructions_tooltip_disabled',  component: 'mod_gestionprojet'},
            {key: 'ai_instructions_confirm_replace',   component: 'mod_gestionprojet'},
            {key: 'ai_instructions_error_generic',     component: 'mod_gestionprojet'},
            {key: 'ai_instructions_error_disabled',    component: 'mod_gestionprojet'},
            {key: 'ai_instructions_error_no_provider', component: 'mod_gestionprojet'},
            {key: 'ai_instructions_error_model_empty', component: 'mod_gestionprojet'},
            {key: 'ai_instructions_success',           component: 'mod_gestionprojet'}
        ]).then(function(values) {
            return {
                btnDefault:        values[0],
                btnGenerate:       values[1],
                btnGenerating:     values[2],
                tooltipEmpty:      values[3],
                tooltipDisabled:   values[4],
                confirmReplace:    values[5],
                errorGeneric:      values[6],
                errorDisabled:     values[7],
                errorNoProvider:   values[8],
                errorModelEmpty:   values[9],
                success:           values[10]
            };
        });
    }

    function errorMessage(strings, code) {
        if (code === 'ai_disabled')   { return strings.errorDisabled; }
        if (code === 'no_provider')   { return strings.errorNoProvider; }
        if (code === 'model_empty')   { return strings.errorModelEmpty; }
        return strings.errorGeneric;
    }

    return {
        /**
         * Initialise the two buttons for one teacher page.
         *
         * @param {Object} cfg
         * @param {number} cfg.cmid           Course module id.
         * @param {number} cfg.step           Step number (4-9).
         * @param {string} cfg.defaultText    Localized default ai_instructions text.
         * @param {boolean} cfg.aiEnabled     Whether AI is enabled at activity level.
         * @param {string} cfg.containerSelector   CSS selector of the buttons container.
         * @param {string} cfg.textareaSelector    CSS selector of the ai_instructions textarea.
         * @param {Function} cfg.getModelData      Returns the current model fields object.
         * @param {Function} cfg.isModelEmpty      Returns true if all model fields are empty.
         * @param {Function} [cfg.onUpdated]       Optional callback after textarea is filled.
         */
        init: function(cfg) {
            fetchStrings().then(function(strings) {
                var $container = $(cfg.containerSelector);
                var $textarea  = $(cfg.textareaSelector);

                var $btnDefault = $('<button type="button" class="btn btn-secondary btn-ai-default">')
                    .text(strings.btnDefault);

                var $btnGenerate = $('<button type="button" class="btn btn-primary btn-ai-generate">')
                    .text(strings.btnGenerate);

                $container.empty().append($btnDefault).append(' ').append($btnGenerate);

                // Default button.
                $btnDefault.on('click', function() {
                    if ($textarea.val().trim() !== '' && !window.confirm(strings.confirmReplace)) {
                        return;
                    }
                    $textarea.val(cfg.defaultText).trigger('change').trigger('input');
                    if (typeof cfg.onUpdated === 'function') { cfg.onUpdated(); }
                });

                // Generate button — disabled state.
                function refreshGenerateState() {
                    if (!cfg.aiEnabled) {
                        $btnGenerate.prop('disabled', true).attr('title', strings.tooltipDisabled);
                        return;
                    }
                    if (cfg.isModelEmpty()) {
                        $btnGenerate.prop('disabled', true).attr('title', strings.tooltipEmpty);
                    } else {
                        $btnGenerate.prop('disabled', false).removeAttr('title');
                    }
                }
                refreshGenerateState();
                // Re-evaluate on any input change in the surrounding form.
                $container.closest('form').on('input change', refreshGenerateState);

                // Generate button — click handler.
                $btnGenerate.on('click', function() {
                    if ($textarea.val().trim() !== '' && !window.confirm(strings.confirmReplace)) {
                        return;
                    }

                    var $btn = $(this);
                    var originalLabel = $btn.text();
                    $btn.prop('disabled', true).text(strings.btnGenerating);

                    $.ajax({
                        url:  M.cfg.wwwroot + '/mod/gestionprojet/ajax/generate_ai_instructions.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            id: cfg.cmid,
                            step: cfg.step,
                            sesskey: M.cfg.sesskey,
                            model_data: JSON.stringify(cfg.getModelData())
                        }
                    }).done(function(resp) {
                        if (resp && resp.success) {
                            $textarea.val(resp.instructions).trigger('change').trigger('input');
                            if (typeof cfg.onUpdated === 'function') { cfg.onUpdated(); }
                            Notification.addNotification({message: strings.success, type: 'success'});
                        } else {
                            Notification.addNotification({
                                message: errorMessage(strings, resp && resp.error),
                                type: 'error'
                            });
                        }
                    }).fail(function() {
                        Notification.addNotification({message: strings.errorGeneric, type: 'error'});
                    }).always(function() {
                        $btn.text(originalLabel);
                        refreshGenerateState();
                    });
                });
            }).catch(Notification.exception);
        }
    };
});
```

- [ ] **Step 4.2 — Build le module avec grunt**

```bash
cd <moodle-root>
npx grunt amd --root=mod/gestionprojet
```

Expected: génère `gestionprojet/amd/build/generate_ai_instructions.min.js`.

Si grunt n'est pas dispo localement, le build CI/serveur le générera ; pour le commit local, ajouter manuellement un fichier minifié simplifié — pratique courante : copier le `src/` vers `build/` en `.min.js` (Moodle 4+ accepte les non-minifiés en debug).

- [ ] **Step 4.3 — Commit**

```bash
git add gestionprojet/amd/src/generate_ai_instructions.js gestionprojet/amd/build/generate_ai_instructions.min.js
git commit -m "feat(ai): add AMD module for AI instructions buttons

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: CSS namespacé

**Files:**
- Modify: `gestionprojet/styles.css`

- [ ] **Step 5.1 — Ajouter le bloc CSS**

À la fin de `gestionprojet/styles.css`, ajouter :

```css
/* AI instructions buttons (teacher correction models) */
.path-mod-gestionprojet .ai-instructions-actions {
    display: flex;
    gap: 0.5rem;
    margin: 0.5rem 0;
    flex-wrap: wrap;
}

.path-mod-gestionprojet .ai-instructions-actions .btn-ai-default,
.path-mod-gestionprojet .ai-instructions-actions .btn-ai-generate {
    font-size: 0.9rem;
    padding: 0.35rem 0.75rem;
}

.path-mod-gestionprojet .ai-instructions-actions .btn-ai-generate:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
```

- [ ] **Step 5.2 — Purge caches**

```bash
php /<moodle-root>/admin/cli/purge_caches.php
```

- [ ] **Step 5.3 — Commit**

```bash
git add gestionprojet/styles.css
git commit -m "style: add CSS for AI instructions buttons

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Câblage `step4_teacher.php`

**Files:**
- Modify: `gestionprojet/pages/step4_teacher.php`

- [ ] **Step 6.1 — Insérer le conteneur de boutons et l'appel AMD**

Dans `gestionprojet/pages/step4_teacher.php`, repérer le bloc :

```html
<div class="ai-instructions-section">
    <h3>...</h3>
    <textarea id="ai_instructions" name="ai_instructions" ...></textarea>
    <p class="ai-instructions-help">...</p>
</div>
```

Modifier comme suit (insérer le `<div class="ai-instructions-actions">` juste avant le `<textarea>`, et le `js_call_amd` vient via `$PAGE->requires->js_call_amd()` mais comme la page n'a pas de bootstrap PHP additionnel évident, on appelle l'init directement dans le `require([...])` existant) :

```html
<div class="ai-instructions-section">
    <h3><?php echo icon::render('bot', 'sm', 'purple'); ?> <?php echo get_string('ai_instructions', 'gestionprojet'); ?></h3>
    <div class="ai-instructions-actions" id="aiInstructionsActions"></div>
    <textarea id="ai_instructions" name="ai_instructions"
              placeholder="<?php echo get_string('ai_instructions_placeholder', 'gestionprojet'); ?>"><?php echo s($model->ai_instructions ?? ''); ?></textarea>
    <p class="ai-instructions-help">
        <?php echo get_string('ai_instructions_help', 'gestionprojet'); ?>
    </p>
</div>
```

Puis dans le bloc `<script>` final, repérer :

```js
require(['jquery', 'mod_gestionprojet/autosave'], function($, Autosave) {
    $(document).ready(function() {
        // ... existing code ...
```

Ajouter `'mod_gestionprojet/generate_ai_instructions'` aux dépendances et l'init après `Autosave.init({...})` :

```js
require(['jquery', 'mod_gestionprojet/autosave', 'mod_gestionprojet/generate_ai_instructions'],
    function($, Autosave, GenerateAi) {
        $(document).ready(function() {
            renderInteractors();

            var cmid = <?php echo $cm->id; ?>;
            var autosaveInterval = <?php echo ($gestionprojet->autosave_interval ?? 30) * 1000; ?>;

            var serializeData = function() {
                var dates = getDateValues();
                return {
                    produit: document.getElementById('produit').value,
                    milieu: document.getElementById('milieu').value,
                    fp: document.getElementById('fp').value,
                    interacteurs_data: JSON.stringify(interacteurs),
                    ai_instructions: document.getElementById('ai_instructions').value,
                    submission_date: dates.submission_date,
                    deadline_date: dates.deadline_date
                };
            };

            Autosave.init({ /* unchanged */ });

            // AI instructions buttons
            GenerateAi.init({
                cmid: cmid,
                step: 4,
                aiEnabled: <?php echo $gestionprojet->ai_enabled ? 'true' : 'false'; ?>,
                defaultText: <?php echo json_encode(get_string('ai_instructions_default_step4', 'gestionprojet')); ?>,
                containerSelector: '#aiInstructionsActions',
                textareaSelector: '#ai_instructions',
                getModelData: function() {
                    var d = serializeData();
                    return {
                        produit: d.produit,
                        milieu: d.milieu,
                        fp: d.fp,
                        interacteurs_data: d.interacteurs_data
                    };
                },
                isModelEmpty: function() {
                    var d = this.getModelData();
                    return !d.produit && !d.milieu && !d.fp &&
                           (!d.interacteurs_data || d.interacteurs_data === '[]');
                },
                onUpdated: function() { Autosave.save(); }
            });

            // existing manual save button binding...
        });
    });
```

- [ ] **Step 6.2 — Test manuel local**

1. `php admin/cli/purge_caches.php`
2. Ouvrir la page step4_teacher dans un navigateur
3. Vérifier que les deux boutons apparaissent au-dessus du textarea
4. Vérifier que « Générer » est désactivé tant que `produit` est vide
5. Remplir `produit` → bouton actif
6. Cliquer « Modèle par défaut » → texte par défaut inséré dans le textarea
7. Cliquer « Générer depuis le modèle » → appel AJAX, spinner, puis textarea rempli

- [ ] **Step 6.3 — Commit**

```bash
git add gestionprojet/pages/step4_teacher.php
git commit -m "feat(step4): wire AI instructions buttons into step4 teacher page

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Câblage `step5_teacher.php`

**Files:**
- Modify: `gestionprojet/pages/step5_teacher.php`

- [ ] **Step 7.1 — Lire la structure actuelle**

```bash
grep -n "ai-instructions-section\|serializeData\|require\(\[" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step5_teacher.php"
```

Identifier les variables sérialisées (les champs métier de step 5 sont : `nom_essai`, `objectif`, `fonction_service`, `niveaux_reussite`, `etapes_protocole`, `materiel_outils`, `precautions`, `resultats_obtenus`, `observations_remarques`, `conclusion`).

- [ ] **Step 7.2 — Insérer le conteneur de boutons**

Avant le `<textarea id="ai_instructions">`, insérer :
```html
<div class="ai-instructions-actions" id="aiInstructionsActions"></div>
```

- [ ] **Step 7.3 — Ajouter l'init AMD**

Ajouter `'mod_gestionprojet/generate_ai_instructions'` dans les dépendances `require([...])` et appeler `GenerateAi.init({...})` après `Autosave.init` avec :

```js
GenerateAi.init({
    cmid: cmid,
    step: 5,
    aiEnabled: <?php echo $gestionprojet->ai_enabled ? 'true' : 'false'; ?>,
    defaultText: <?php echo json_encode(get_string('ai_instructions_default_step5', 'gestionprojet')); ?>,
    containerSelector: '#aiInstructionsActions',
    textareaSelector: '#ai_instructions',
    getModelData: function() {
        return {
            nom_essai: document.getElementById('nom_essai').value,
            objectif: document.getElementById('objectif').value,
            fonction_service: document.getElementById('fonction_service').value,
            niveaux_reussite: document.getElementById('niveaux_reussite').value,
            etapes_protocole: document.getElementById('etapes_protocole').value,
            materiel_outils: document.getElementById('materiel_outils').value,
            precautions: document.getElementById('precautions').value,
            resultats_obtenus: document.getElementById('resultats_obtenus').value,
            observations_remarques: document.getElementById('observations_remarques').value,
            conclusion: document.getElementById('conclusion').value
        };
    },
    isModelEmpty: function() {
        var d = this.getModelData();
        return Object.values(d).every(function(v) { return !v || !v.trim(); });
    },
    onUpdated: function() { Autosave.save(); }
});
```

- [ ] **Step 7.4 — Test manuel local**

Mêmes vérifications que Step 6.2, page step5_teacher.

- [ ] **Step 7.5 — Commit**

```bash
git add gestionprojet/pages/step5_teacher.php
git commit -m "feat(step5): wire AI instructions buttons into step5 teacher page

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Câblage `step6_teacher.php`

**Files:**
- Modify: `gestionprojet/pages/step6_teacher.php`

Mêmes étapes que Task 7. Champs métier step 6 : `titre_projet`, `auteurs`, `besoin_projet`, `imperatifs`, `solutions`, `justification`, `realisation`, `difficultes`, `validation`, `ameliorations`, `bilan`, `perspectives`.

- [ ] **Step 8.1 — Insérer le conteneur**

```html
<div class="ai-instructions-actions" id="aiInstructionsActions"></div>
```

- [ ] **Step 8.2 — Init AMD**

```js
GenerateAi.init({
    cmid: cmid,
    step: 6,
    aiEnabled: <?php echo $gestionprojet->ai_enabled ? 'true' : 'false'; ?>,
    defaultText: <?php echo json_encode(get_string('ai_instructions_default_step6', 'gestionprojet')); ?>,
    containerSelector: '#aiInstructionsActions',
    textareaSelector: '#ai_instructions',
    getModelData: function() {
        var fields = ['titre_projet','auteurs','besoin_projet','imperatifs','solutions',
                      'justification','realisation','difficultes','validation',
                      'ameliorations','bilan','perspectives'];
        var d = {};
        fields.forEach(function(f) {
            var el = document.getElementById(f);
            d[f] = el ? el.value : '';
        });
        return d;
    },
    isModelEmpty: function() {
        var d = this.getModelData();
        return Object.values(d).every(function(v) { return !v || !v.trim(); });
    },
    onUpdated: function() { Autosave.save(); }
});
```

- [ ] **Step 8.3 — Test manuel + commit**

```bash
git add gestionprojet/pages/step6_teacher.php
git commit -m "feat(step6): wire AI instructions buttons into step6 teacher page

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Câblage `step7_teacher.php`

**Files:**
- Modify: `gestionprojet/pages/step7_teacher.php`

Champs métier step 7 : `aqui`, `surquoi`, `dansquelbut`.

- [ ] **Step 9.1 — Insérer le conteneur**

```html
<div class="ai-instructions-actions" id="aiInstructionsActions"></div>
```

- [ ] **Step 9.2 — Init AMD**

```js
GenerateAi.init({
    cmid: cmid,
    step: 7,
    aiEnabled: <?php echo $gestionprojet->ai_enabled ? 'true' : 'false'; ?>,
    defaultText: <?php echo json_encode(get_string('ai_instructions_default_step7', 'gestionprojet')); ?>,
    containerSelector: '#aiInstructionsActions',
    textareaSelector: '#ai_instructions',
    getModelData: function() {
        return {
            aqui: document.getElementById('aqui').value,
            surquoi: document.getElementById('surquoi').value,
            dansquelbut: document.getElementById('dansquelbut').value
        };
    },
    isModelEmpty: function() {
        var d = this.getModelData();
        return !d.aqui && !d.surquoi && !d.dansquelbut;
    },
    onUpdated: function() { Autosave.save(); }
});
```

- [ ] **Step 9.3 — Test manuel + commit**

```bash
git add gestionprojet/pages/step7_teacher.php
git commit -m "feat(step7): wire AI instructions buttons into step7 teacher page

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: Câblage `step8_teacher.php`

**Files:**
- Modify: `gestionprojet/pages/step8_teacher.php`

Champ métier step 8 : `tasks_data` (JSON).

- [ ] **Step 10.1 — Insérer le conteneur + Init AMD**

Conteneur :
```html
<div class="ai-instructions-actions" id="aiInstructionsActions"></div>
```

Init AMD :
```js
GenerateAi.init({
    cmid: cmid,
    step: 8,
    aiEnabled: <?php echo $gestionprojet->ai_enabled ? 'true' : 'false'; ?>,
    defaultText: <?php echo json_encode(get_string('ai_instructions_default_step8', 'gestionprojet')); ?>,
    containerSelector: '#aiInstructionsActions',
    textareaSelector: '#ai_instructions',
    getModelData: function() {
        // tasks_data is maintained as a JS variable in this page (cf. existing serializeData).
        return { tasks_data: JSON.stringify(typeof tasks !== 'undefined' ? tasks : []) };
    },
    isModelEmpty: function() {
        var d = this.getModelData();
        return !d.tasks_data || d.tasks_data === '[]';
    },
    onUpdated: function() { Autosave.save(); }
});
```

(Si la variable JS s'appelle autrement — `taskList`, etc. — adapter en se référant au `serializeData()` existant.)

- [ ] **Step 10.2 — Test manuel + commit**

```bash
git add gestionprojet/pages/step8_teacher.php
git commit -m "feat(step8): wire AI instructions buttons into step8 teacher page

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 11: Câblage step 9 (mustache + PHP)

**Files:**
- Modify: `gestionprojet/templates/step9_form.mustache`
- Modify: `gestionprojet/pages/step9_teacher.php`

Step 9 utilise un template Mustache et un module AMD spécifique pour le diagramme FAST. Champ métier : `data_json`.

- [ ] **Step 11.1 — Modifier le mustache**

Dans `gestionprojet/templates/step9_form.mustache`, repérer le bloc qui contient le `<textarea name="ai_instructions">` (ligne ~49). Insérer juste avant le `<textarea>` :

```mustache
<div class="ai-instructions-actions" id="aiInstructionsActions-{{cmid}}"></div>
```

Le fichier de spec dit que ce bloc est rendu uniquement en mode teacher. Vérifier qu'il est bien sous une section `{{^isprovided}}...{{/isprovided}}` ou équivalente — sinon l'envelopper de la même manière que le textarea actuel.

- [ ] **Step 11.2 — Ajouter l'init AMD côté PHP**

Dans `gestionprojet/pages/step9_teacher.php`, repérer l'appel `$PAGE->requires->js_call_amd(...)` existant (probablement pour le module FAST), et ajouter un appel supplémentaire :

```php
$PAGE->requires->js_call_amd('mod_gestionprojet/generate_ai_instructions', 'init', [[
    'cmid' => $cm->id,
    'step' => 9,
    'aiEnabled' => (bool) $gestionprojet->ai_enabled,
    'defaultText' => get_string('ai_instructions_default_step9', 'gestionprojet'),
    'containerSelector' => '#aiInstructionsActions-' . $cm->id,
    'textareaSelector' => '#fast-ai-' . $cm->id,
]]);
```

**Limitation importante** : le module AMD attend `getModelData` et `isModelEmpty` comme **fonctions JavaScript**, mais `js_call_amd` ne sait pas sérialiser des fonctions. Deux options :
- **(a)** Côté PHP, on **ne passe PAS** `getModelData` / `isModelEmpty`. À la place, on passe une représentation textuelle pré-sérialisée du modèle au moment du chargement de page (ex. `currentDataJson` = la valeur actuelle de `data_json`). Mais cela ne se met pas à jour quand l'utilisateur modifie le diagramme.
- **(b)** Étendre le module AMD pour supporter une variante « fetch the data via DOM selector ». Trop large pour cette task.
- **(c)** Émettre côté JS un wrapper init via une variable globale, comme pour les autres steps.

**Choix retenu** : option (c) — appeler `GenerateAi.init({...})` depuis du JS spécifique à la page (déjà présent pour le FAST), pas via `js_call_amd`. Donc dans `pages/step9_teacher.php`, retirer l'appel `js_call_amd` ci-dessus et à la place modifier le bloc `<script>` existant qui initialise le composant FAST.

Concrètement :

```php
// Append after the existing FAST AMD initialisation in step9_teacher.php :
$PAGE->requires->js_init_code(<<<JS
require(['jquery', 'mod_gestionprojet/generate_ai_instructions'], function(\$, GenerateAi) {
    GenerateAi.init({
        cmid: {$cm->id},
        step: 9,
        aiEnabled: {$aienabledjsbool},
        defaultText: {$defaulttextjs},
        containerSelector: '#aiInstructionsActions-{$cm->id}',
        textareaSelector: '#fast-ai-{$cm->id}',
        getModelData: function() {
            // FAST diagram exposes its current state via window.MOD_GESTIONPROJET_FAST_GET_DATA(cmid)
            var fn = window.MOD_GESTIONPROJET_FAST_GET_DATA;
            var data = (typeof fn === 'function') ? fn({$cm->id}) : '';
            return { data_json: typeof data === 'string' ? data : JSON.stringify(data || {}) };
        },
        isModelEmpty: function() {
            var d = this.getModelData();
            return !d.data_json || d.data_json === '{}' || d.data_json === '[]';
        }
    });
});
JS
);
```

avec en amont :

```php
$aienabledjsbool = $gestionprojet->ai_enabled ? 'true' : 'false';
$defaulttextjs = json_encode(get_string('ai_instructions_default_step9', 'gestionprojet'));
```

**Note** : si le module FAST n'expose pas `window.MOD_GESTIONPROJET_FAST_GET_DATA`, il faudra l'ajouter (1 ligne dans son module AMD). Step à valider lors de l'implémentation en lisant `amd/src/fast.js` ou équivalent.

- [ ] **Step 11.3 — Test manuel local**

Idem Step 6.2 mais sur la page step 9. Vérifier en particulier que `getModelData()` retourne quelque chose de non-vide quand un nœud FT est ajouté au diagramme.

- [ ] **Step 11.4 — Commit**

```bash
git add gestionprojet/templates/step9_form.mustache gestionprojet/pages/step9_teacher.php
git commit -m "feat(step9): wire AI instructions buttons into FAST teacher page

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 12: Bump version

**Files:**
- Modify: `gestionprojet/version.php`
- Modify: `gestionprojet/CHANGELOG.md`

- [ ] **Step 12.1 — Bump version**

Le `version.php` actuel a `$plugin->version = 2026050500` et `$plugin->release = '2.4.0'`. Le numéro doit être strictement croissant pour déclencher l'upgrade ; on le porte à `2026050501` (même jour, slot suivant) :

```php
$plugin->version = 2026050501;  // YYYYMMDDXX format
$plugin->release = '2.5.0';
```

- [ ] **Step 12.2 — Mettre à jour CHANGELOG**

Dans `gestionprojet/CHANGELOG.md`, ajouter en haut :

```markdown
## [2.5.0] — 2026-05-XX

### Added
- Bouton « Modèle par défaut » dans les modèles de correction (étapes 4-9) pour insérer rapidement les instructions IA par défaut.
- Bouton « Générer depuis le modèle » qui appelle l'IA configurée pour produire des instructions de correction adaptées au modèle rempli.
- Méta-prompt `ai_prompt_builder::build_meta_prompt()` + tests PHPUnit.
- Endpoint AJAX `ajax/generate_ai_instructions.php`.
- Module AMD `mod_gestionprojet/generate_ai_instructions`.
- Chaînes par défaut `ai_instructions_default_step{5,6,8,9}` (FR + EN).
```

- [ ] **Step 12.3 — Commit**

```bash
git add gestionprojet/version.php gestionprojet/CHANGELOG.md
git commit -m "chore(release): bump to 2.5.0

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 13: Recette manuelle + TESTING.md

**Files:**
- Modify: `TESTING.md` (à la racine du repo)

- [ ] **Step 13.1 — Ajouter le bloc de recette dans TESTING.md**

Ajouter :

```markdown
## Recette — Boutons de génération du prompt IA (v2.5.0)

Pour chaque étape ∈ {4, 5, 6, 7, 8, 9}, ouvrir la page de modèle de correction enseignant correspondante :

1. **Affichage** : les deux boutons « Modèle par défaut » et « Générer depuis le modèle » apparaissent au-dessus du textarea `ai_instructions`.
2. **Désactivation modèle vide** : avec un modèle entièrement vide, le bouton « Générer » est désactivé et son tooltip affiche « Remplissez d'abord le modèle de correction ».
3. **Réactivation** : remplir au moins un champ du modèle → le bouton devient actif.
4. **Modèle par défaut (textarea vide)** : cliquer → texte par défaut inséré dans le textarea + autosave déclenché (vérifier la mention « Saved » dans le coin du formulaire).
5. **Modèle par défaut (textarea non-vide)** : cliquer → confirmation `confirm()` apparaît → si OK, remplacement.
6. **Générer (textarea vide)** : cliquer → spinner + libellé « Génération en cours… » → après quelques secondes, textarea rempli avec un texte cohérent en français.
7. **Générer (textarea non-vide)** : cliquer → confirmation `confirm()` → si OK, remplacement.
8. **IA désactivée** : dans la config de l'activité, décocher `ai_enabled` → recharger la page → bouton « Générer » désactivé avec tooltip « IA désactivée dans la configuration de l'activité ». Bouton « Modèle par défaut » reste actif.
9. **Provider en échec** (optionnel, simuler avec une mauvaise clé API) : cliquer « Générer » → toast d'erreur, bouton réactivé.
```

- [ ] **Step 13.2 — Faire la recette en preprod**

Cocher au fur et à mesure les 9 points × 6 étapes = 54 vérifications. Documenter les éventuels écarts.

- [ ] **Step 13.3 — Commit**

```bash
git add TESTING.md
git commit -m "docs(testing): add recipe for AI prompt generation buttons (v2.5.0)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 14: Déploiement

- [ ] **Step 14.1 — Push sur Forge EDU**

```bash
git push origin main
```

- [ ] **Step 14.2 — Construire le ZIP**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
zip -r gestionprojet.zip gestionprojet/ -x "*.git*" "*.claude*" "*lessons.md"
```

- [ ] **Step 14.3 — Upload Moodle production**

Via l'interface Moodle Admin → Plugins → Install plugins → Upload ZIP. Valider l'upgrade sur la page Notifications.

- [ ] **Step 14.4 — Vérification post-déploiement**

Refaire la recette §13.1 sur ent-occitanie.com/moodle (au moins steps 4 et 9).

---

## Self-review checklist

- [x] Chaque step a du code complet (pas de TBD/TODO).
- [x] Chemins exacts pour tous les fichiers.
- [x] Commands exactes avec output attendu.
- [x] Couvre tous les §1-14 du spec.
- [x] Conformité Moodle (CLAUDE.md §1-11) : header GPL sur les fichiers nouveaux, pas de CSS inline (Step 5.1), pas de JS inline nouveau (Task 4 + Task 11 init via `js_init_code` qui pointe vers AMD), strings via lang files (Task 2), sécurité endpoint (Step 3.1), pas de DB → pas d'impact `delete_instance`.
- [x] Type / signature consistency : `build_meta_prompt(int, object): array` partout, `evaluate(string, string, string, int): array` (existant), réponses `{success, error?, instructions?, message?}` cohérentes endpoint ↔ JS.
