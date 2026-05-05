# Bouton "Soumettre" élève + déclenchement IA — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implémenter un bouton « Soumettre mon travail » côté élève qui verrouille définitivement la production et déclenche automatiquement l'évaluation IA en arrière-plan, avec retour visuel et notifications prof.

**Architecture:** Approche inline minimaliste — `queue_evaluation()` appelé directement après l'update_record dans `submit_step.php`, modale Bootstrap Moodle (`core/modal_factory`) côté front, poll AJAX 5s sur l'endpoint `get_evaluation_status.php` étendu, notification prof via `message_send()` dans le catch de `process_evaluation()`. Aucun changement de schéma DB.

**Tech Stack:** Moodle 5.0+ / PHP 8.1+, AMD modules (RequireJS + jQuery), Mustache templates, Bootstrap 5, `core/modal_factory`, Moodle messaging API.

**Spec :** `docs/superpowers/specs/2026-05-05-student-submit-ai-trigger-design.md`

---

## File Structure

| Fichier | Action | Rôle |
|---|---|---|
| `gestionprojet/ajax/submit_step.php` | Modify | Déclenchement IA après status=1 + step 9 dans `$steptables` |
| `gestionprojet/classes/ai_evaluator.php` | Modify | Notif prof dans le catch de `process_evaluation()` |
| `gestionprojet/ajax/get_evaluation_status.php` | Modify | Autoriser élève sur sa propre éval (payload sanitizé) |
| `gestionprojet/db/messages.php` | Create | Déclaration provider Moodle `ai_evaluation_failed` |
| `gestionprojet/lang/en/gestionprojet.php` | Modify | Strings EN |
| `gestionprojet/lang/fr/gestionprojet.php` | Modify | Strings FR |
| `gestionprojet/templates/submit_modal.mustache` | Create | Corps de la modale Bootstrap |
| `gestionprojet/amd/src/submission.js` | Modify | Refonte avec modale Moodle + checkbox |
| `gestionprojet/amd/src/student_ai_progress.js` | Create | Poll 5s côté élève |
| `gestionprojet/pages/student_submission_section.php` | Modify | Bandeau IA + js_call_amd étendu |
| `gestionprojet/styles.css` | Modify | Styles bandeau IA |
| `gestionprojet/version.php` | Modify | Bump 2.6.3 / 2026050505 |

---

## Task 1: Ajouter step 9 dans `submit_step.php`

**Files:**
- Modify: `gestionprojet/ajax/submit_step.php:47-53`

- [ ] **Step 1: Ouvrir le fichier et localiser `$steptables`**

Lire `gestionprojet/ajax/submit_step.php` lignes 47-53.

- [ ] **Step 2: Ajouter step 9**

Remplacer le tableau actuel par :

```php
// Map step to table name.
$steptables = [
    4 => 'gestionprojet_cdcf',
    5 => 'gestionprojet_essai',
    6 => 'gestionprojet_rapport',
    7 => 'gestionprojet_besoin_eleve',
    8 => 'gestionprojet_carnet',
    9 => 'gestionprojet_fast',
];
```

- [ ] **Step 3: Vérifier que `gestionprojet_get_or_create_submission` accepte 'fast' comme steptype**

Run: `grep -n "fast\|steptype" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/lib.php" | head -20`

Si la fonction ne supporte pas 'fast', noter le résultat — il faudra peut-être la mettre à jour. Ne pas faire de modification ici, juste documenter le résultat.

- [ ] **Step 4: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/ajax/submit_step.php
git commit -m "feat(submit): add step 9 (FAST) to submission tables map"
```

---

## Task 2: Déclencher l'éval IA dans `submit_step.php`

**Files:**
- Modify: `gestionprojet/ajax/submit_step.php:86-106` (bloc `if ($action === 'submit')`)
- Modify: `gestionprojet/ajax/submit_step.php:169-173` (réponse JSON)

- [ ] **Step 1: Localiser le bloc submit**

Relire `gestionprojet/ajax/submit_step.php` lignes 72-106 pour bien situer le code juste après `$DB->update_record($tablename, $record);` et `gestionprojet_log_change(...)`.

- [ ] **Step 2: Ajouter le déclenchement IA**

Remplacer :

```php
        $DB->update_record($tablename, $record);

        // Log the submission.
        gestionprojet_log_change(
            $gestionprojet->id,
            $steptype,
            $record->id,
            'status',
            0,
            1,
            $USER->id,
            $groupid
        );

        $success = true;
        $message = get_string('submissionsuccess', 'gestionprojet');
```

Par :

```php
        $DB->update_record($tablename, $record);

        // Log the submission.
        gestionprojet_log_change(
            $gestionprojet->id,
            $steptype,
            $record->id,
            'status',
            0,
            1,
            $USER->id,
            $groupid
        );

        // Try to queue AI evaluation. Failure is non-fatal — submission stays valid.
        $evaluationid = null;
        $aiAvailable = false;
        if (!empty($gestionprojet->ai_enabled)) {
            try {
                require_once(__DIR__ . '/../classes/ai_evaluator.php');
                $evaluationid = \mod_gestionprojet\ai_evaluator::queue_evaluation(
                    $gestionprojet->id,
                    $step,
                    $record->id,
                    $record->groupid ?? 0,
                    $record->userid ?? 0
                );
                $aiAvailable = true;
            } catch (\Exception $e) {
                debugging('AI evaluation skipped on submit: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $success = true;
        $message = get_string('submissionsuccess', 'gestionprojet');
```

- [ ] **Step 3: Étendre la réponse JSON**

Remplacer la fin du fichier (lignes 169-173) :

```php
echo json_encode([
    'success' => $success,
    'message' => $message,
    'timestamp' => time()
]);
```

Par :

```php
echo json_encode([
    'success' => $success,
    'message' => $message,
    'evaluationid' => $evaluationid ?? null,
    'ai_available' => $aiAvailable ?? false,
    'timestamp' => time(),
]);
```

- [ ] **Step 4: Vérifier la syntaxe PHP**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/submit_step.php"`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/ajax/submit_step.php
git commit -m "feat(submit): trigger AI evaluation on student submit"
```

---

## Task 3: Créer le message provider Moodle

**Files:**
- Create: `gestionprojet/db/messages.php`

- [ ] **Step 1: Créer le fichier**

Écrire le contenu suivant dans `gestionprojet/db/messages.php` :

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
 * Message providers for mod_gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'ai_evaluation_failed' => [
        'capability' => 'mod/gestionprojet:grade',
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
        ],
    ],
];
```

- [ ] **Step 2: Vérifier la syntaxe**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/db/messages.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/db/messages.php
git commit -m "feat(messages): add ai_evaluation_failed message provider"
```

---

## Task 4: Ajouter les strings i18n (en + fr)

**Files:**
- Modify: `gestionprojet/lang/en/gestionprojet.php` (append à la fin)
- Modify: `gestionprojet/lang/fr/gestionprojet.php` (append à la fin)

- [ ] **Step 1: Identifier le point d'insertion EN**

Lire la fin de `gestionprojet/lang/en/gestionprojet.php` pour trouver les dernières lignes (avant `?>` final s'il existe — sinon avant fin du fichier).

- [ ] **Step 2: Ajouter les strings EN**

Ajouter à la fin (juste avant `?>` final s'il existe) :

```php

// Submit modal + AI auto-trigger (v2.6.3).
$string['submit_modal_title'] = 'Submit your work';
$string['submit_modal_intro'] = 'Before submitting, please review the consequences:';
$string['submit_modal_lock'] = 'Your work will be locked — you won\'t be able to edit it.';
$string['submit_modal_ai'] = 'Automatic AI grading will start immediately.';
$string['submit_modal_irreversible'] = 'Only your teacher can unlock your submission.';
$string['submit_modal_group_warning'] = 'Warning: this action submits the work for the entire group — notify your teammates first.';
$string['submit_modal_checkbox'] = 'I have re-read my work and I want to submit it definitively.';
$string['confirm_submit_btn'] = 'Submit definitively';
$string['ai_progress_pending'] = 'AI evaluation queued…';
$string['ai_progress_processing'] = 'AI evaluation in progress…';
$string['ai_progress_failed_student'] = 'AI evaluation unavailable. Your teacher will grade your work.';
$string['messageprovider:ai_evaluation_failed'] = 'AI evaluation failure notifications';
$string['ai_failure_notif_subject'] = 'AI evaluation failed in {$a->activityname}';
$string['ai_failure_notif_body'] = 'An AI evaluation failed for step {$a->step}. Error: {$a->error}. Please review the submission at: {$a->url}';
$string['ai_failure_notif_small'] = 'AI evaluation failed';
```

- [ ] **Step 3: Ajouter les strings FR**

Ajouter à la fin de `gestionprojet/lang/fr/gestionprojet.php` (juste avant `?>` final s'il existe) :

```php

// Modale soumission + déclenchement auto IA (v2.6.3).
$string['submit_modal_title'] = 'Soumettre votre travail';
$string['submit_modal_intro'] = 'Avant de soumettre, prends connaissance de ces conséquences :';
$string['submit_modal_lock'] = 'Ton travail sera verrouillé — tu ne pourras plus le modifier.';
$string['submit_modal_ai'] = 'La correction IA automatique démarrera immédiatement.';
$string['submit_modal_irreversible'] = 'Seul ton professeur peut déverrouiller ta soumission.';
$string['submit_modal_group_warning'] = 'Attention : cette action soumet le travail pour tout le groupe — préviens d\'abord tes coéquipiers.';
$string['submit_modal_checkbox'] = 'J\'ai relu mon travail et je veux le soumettre définitivement.';
$string['confirm_submit_btn'] = 'Soumettre définitivement';
$string['ai_progress_pending'] = 'Évaluation IA en attente…';
$string['ai_progress_processing'] = 'Évaluation IA en cours…';
$string['ai_progress_failed_student'] = 'Évaluation IA indisponible. Ton professeur corrigera ton travail.';
$string['messageprovider:ai_evaluation_failed'] = 'Notifications d\'échec d\'évaluation IA';
$string['ai_failure_notif_subject'] = 'Échec de l\'évaluation IA dans {$a->activityname}';
$string['ai_failure_notif_body'] = 'Une évaluation IA a échoué pour l\'étape {$a->step}. Erreur : {$a->error}. Vérifie la soumission ici : {$a->url}';
$string['ai_failure_notif_small'] = 'Échec de l\'évaluation IA';
```

- [ ] **Step 4: Vérifier la syntaxe des deux fichiers**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/lang/en/gestionprojet.php" && php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/lang/fr/gestionprojet.php"`
Expected: `No syntax errors detected` × 2

- [ ] **Step 5: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/lang/en/gestionprojet.php gestionprojet/lang/fr/gestionprojet.php
git commit -m "feat(lang): add strings for submit modal + AI progress + failure notif"
```

---

## Task 5: Implémenter `notify_teachers_of_failure()` dans `ai_evaluator.php`

**Files:**
- Modify: `gestionprojet/classes/ai_evaluator.php` (ajout méthode privée à la fin de la classe)

- [ ] **Step 1: Localiser la fin de la classe**

Lire `gestionprojet/classes/ai_evaluator.php` lignes 620-630 pour trouver l'accolade fermante de la classe.

- [ ] **Step 2: Ajouter la méthode privée**

Juste avant l'accolade `}` finale de la classe `ai_evaluator`, ajouter :

```php

    /**
     * Notify all teachers of the course about a failed AI evaluation.
     *
     * Sends a Moodle message (popup + email per teacher prefs) to every user
     * holding the mod/gestionprojet:grade capability in this module's context.
     * Failure to send notifications is logged via debugging() and does not
     * propagate (this method is best-effort).
     *
     * @param object $evaluation Evaluation record (after being flagged 'failed')
     * @param string $errorMessage The exception message
     * @return void
     */
    private static function notify_teachers_of_failure(object $evaluation, string $errorMessage): void {
        $cm = get_coursemodule_from_instance('gestionprojet', $evaluation->gestionprojetid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $context = \context_module::instance($cm->id);

        $teachers = get_users_by_capability(
            $context,
            'mod/gestionprojet:grade',
            'u.id, u.firstname, u.lastname, u.email, u.lang, u.maildisplay, u.mailformat, u.deleted, u.suspended, u.confirmed, u.auth, u.username'
        );
        if (empty($teachers)) {
            return;
        }

        $url = (new \moodle_url('/mod/gestionprojet/grading.php', ['id' => $cm->id]))->out(false);
        $activityname = format_string($cm->name);

        foreach ($teachers as $teacher) {
            try {
                $message = new \core\message\message();
                $message->component = 'mod_gestionprojet';
                $message->name = 'ai_evaluation_failed';
                $message->userfrom = \core_user::get_noreply_user();
                $message->userto = $teacher;
                $message->subject = get_string(
                    'ai_failure_notif_subject',
                    'gestionprojet',
                    (object)['activityname' => $activityname]
                );
                $message->fullmessage = get_string(
                    'ai_failure_notif_body',
                    'gestionprojet',
                    (object)[
                        'step' => $evaluation->step,
                        'error' => $errorMessage,
                        'url' => $url,
                    ]
                );
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = '';
                $message->smallmessage = get_string('ai_failure_notif_small', 'gestionprojet');
                $message->notification = 1;
                $message->contexturl = $url;
                $message->contexturlname = get_string('grading', 'gestionprojet');

                \message_send($message);
            } catch (\Exception $e) {
                debugging('Failed to notify teacher of AI failure: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
```

- [ ] **Step 3: Vérifier la syntaxe**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/classes/ai_evaluator.php"`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/classes/ai_evaluator.php
git commit -m "feat(ai): add notify_teachers_of_failure helper for messaging"
```

---

## Task 6: Brancher la notif dans le catch de `process_evaluation()`

**Files:**
- Modify: `gestionprojet/classes/ai_evaluator.php:245-256` (bloc catch de `process_evaluation`)

- [ ] **Step 1: Localiser le catch**

Lire `gestionprojet/classes/ai_evaluator.php` lignes 245-256.

- [ ] **Step 2: Ajouter l'appel à la notif**

Remplacer :

```php
        } catch (\Exception $e) {
            // Mark as failed.
            $evaluation->status = 'failed';
            $evaluation->error_message = $e->getMessage();
            $evaluation->timemodified = time();
            $DB->update_record('gestionprojet_ai_evaluations', $evaluation);

            // Log the error.
            debugging('AI evaluation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);

            return false;
        }
```

Par :

```php
        } catch (\Exception $e) {
            // Mark as failed.
            $evaluation->status = 'failed';
            $evaluation->error_message = $e->getMessage();
            $evaluation->timemodified = time();
            $DB->update_record('gestionprojet_ai_evaluations', $evaluation);

            // Log the error.
            debugging('AI evaluation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);

            // Notify teachers (best-effort, errors swallowed inside).
            self::notify_teachers_of_failure($evaluation, $e->getMessage());

            return false;
        }
```

- [ ] **Step 3: Vérifier la syntaxe**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/classes/ai_evaluator.php"`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/classes/ai_evaluator.php
git commit -m "feat(ai): notify teachers when AI evaluation fails"
```

---

## Task 7: Étendre `get_evaluation_status.php` pour les élèves

**Files:**
- Modify: `gestionprojet/ajax/get_evaluation_status.php:46-127` (capabilities + payload)

- [ ] **Step 1: Lire l'état actuel du fichier**

Lire `gestionprojet/ajax/get_evaluation_status.php` lignes 46-135.

- [ ] **Step 2: Ajouter une vérification d'appartenance pour les non-profs**

Remplacer le bloc qui suit `$context = context_module::instance($cm->id);` (à partir de la ligne 46) :

```php
$context = context_module::instance($cm->id);

header('Content-Type: application/json');
```

Par :

```php
$context = context_module::instance($cm->id);

header('Content-Type: application/json');

// Check capability: teachers can see all, students only their own evaluation.
$isTeacher = has_capability('mod/gestionprojet:grade', $context);
if (!$isTeacher) {
    require_capability('mod/gestionprojet:submit', $context);
}
```

- [ ] **Step 3: Sanitiser le payload élève + filtrer par appartenance**

Localiser le bloc dans le `try` qui charge l'évaluation et construit `$response`. Après la récupération de l'évaluation et la vérification d'instance, AVANT la construction du `$response`, ajouter une vérification d'appartenance pour les élèves.

Remplacer :

```php
    // Verify it belongs to this instance.
    if ($evaluation->gestionprojetid != $gestionprojet->id) {
        throw new \Exception('Invalid evaluation');
    }

    // Get status display info.
    $statusinfo = \mod_gestionprojet\ai_evaluator::get_status_display($evaluation);

    $response = [
        'success' => true,
        'has_evaluation' => true,
        'evaluation_id' => $evaluation->id,
        'status' => $evaluation->status,
        'status_display' => $statusinfo,
        'timecreated' => $evaluation->timecreated,
        'timemodified' => $evaluation->timemodified,
    ];
```

Par :

```php
    // Verify it belongs to this instance.
    if ($evaluation->gestionprojetid != $gestionprojet->id) {
        throw new \Exception('Invalid evaluation');
    }

    // For students: ensure the evaluation belongs to them or their group.
    if (!$isTeacher) {
        $isOwner = ($evaluation->userid && $evaluation->userid == $USER->id);
        $isGroupMember = ($evaluation->groupid && groups_is_member($evaluation->groupid, $USER->id));
        if (!$isOwner && !$isGroupMember) {
            throw new \moodle_exception('nopermission');
        }
    }

    // Get status display info.
    $statusinfo = \mod_gestionprojet\ai_evaluator::get_status_display($evaluation);

    $response = [
        'success' => true,
        'has_evaluation' => true,
        'evaluation_id' => $evaluation->id,
        'status' => $evaluation->status,
        'status_display' => $statusinfo,
        'timecreated' => $evaluation->timecreated,
        'timemodified' => $evaluation->timemodified,
    ];
```

- [ ] **Step 4: Restreindre les champs détaillés aux profs**

Les champs `criteria`, `keywords_found`, `keywords_missing`, `suggestions`, `tokens_used` peuvent contenir des éléments du modèle prof — ne les exposer qu'aux profs.

Remplacer le bloc actuel :

```php
    // Include results if completed.
    if ($evaluation->status === 'completed' || $evaluation->status === 'applied') {
        $response['grade'] = $evaluation->parsed_grade;
        $response['feedback'] = $evaluation->parsed_feedback;
        $response['criteria'] = json_decode($evaluation->criteria_json, true) ?? [];
        $response['keywords_found'] = json_decode($evaluation->keywords_found, true) ?? [];
        $response['keywords_missing'] = json_decode($evaluation->keywords_missing, true) ?? [];
        $response['suggestions'] = json_decode($evaluation->suggestions, true) ?? [];
        $response['tokens_used'] = ($evaluation->prompt_tokens ?? 0) + ($evaluation->completion_tokens ?? 0);

        // Include formatted HTML for teacher view.
        if (has_capability('mod/gestionprojet:grade', $context)) {
            $parser = new \mod_gestionprojet\ai_response_parser();
            $result = new \stdClass();
            $result->grade = $evaluation->parsed_grade;
            $result->max_grade = 20;
            $result->feedback = $evaluation->parsed_feedback;
            $result->criteria = $response['criteria'];
            $result->keywords_found = $response['keywords_found'];
            $result->keywords_missing = $response['keywords_missing'];
            $result->suggestions = $response['suggestions'];
            $response['html'] = $parser->format_for_display($result);
        }
    }
```

Par :

```php
    // Include results if completed (teachers get full payload, students only get status — feedback rendering is handled server-side via student_ai_feedback_display.php on page reload).
    if ($evaluation->status === 'completed' || $evaluation->status === 'applied') {
        if ($isTeacher) {
            $response['grade'] = $evaluation->parsed_grade;
            $response['feedback'] = $evaluation->parsed_feedback;
            $response['criteria'] = json_decode($evaluation->criteria_json, true) ?? [];
            $response['keywords_found'] = json_decode($evaluation->keywords_found, true) ?? [];
            $response['keywords_missing'] = json_decode($evaluation->keywords_missing, true) ?? [];
            $response['suggestions'] = json_decode($evaluation->suggestions, true) ?? [];
            $response['tokens_used'] = ($evaluation->prompt_tokens ?? 0) + ($evaluation->completion_tokens ?? 0);

            $parser = new \mod_gestionprojet\ai_response_parser();
            $result = new \stdClass();
            $result->grade = $evaluation->parsed_grade;
            $result->max_grade = 20;
            $result->feedback = $evaluation->parsed_feedback;
            $result->criteria = $response['criteria'];
            $result->keywords_found = $response['keywords_found'];
            $result->keywords_missing = $response['keywords_missing'];
            $result->suggestions = $response['suggestions'];
            $response['html'] = $parser->format_for_display($result);
        }
        // Students get only status fields — they reload the page to see filtered feedback via student_ai_feedback_display.php.
    }

    // Include error message only for teachers (students get a generic banner).
    if ($evaluation->status === 'failed' && $isTeacher) {
        $response['error_message'] = $evaluation->error_message;
    }
```

Et supprimer le bloc dupliqué `if ($evaluation->status === 'failed')` plus bas dans le fichier (il devient redondant).

- [ ] **Step 5: Vérifier la syntaxe**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/get_evaluation_status.php"`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/ajax/get_evaluation_status.php
git commit -m "feat(ajax): allow students to poll status of their own evaluation"
```

---

## Task 8: Créer le template Mustache de la modale

**Files:**
- Create: `gestionprojet/templates/submit_modal.mustache`

- [ ] **Step 1: Créer le fichier**

Écrire dans `gestionprojet/templates/submit_modal.mustache` :

```mustache
{{!
    @template mod_gestionprojet/submit_modal

    Confirmation modal body for student work submission.

    Context variables:
        isgroup    - bool: true if in group submission mode
        aienabled  - bool: true if AI evaluation is enabled for this instance

    Example context (json):
    {
        "isgroup": false,
        "aienabled": true
    }
}}
<div class="submit-confirm-modal">
    <p>{{#str}}submit_modal_intro, mod_gestionprojet{{/str}}</p>
    <ul>
        <li>{{#str}}submit_modal_lock, mod_gestionprojet{{/str}}</li>
        {{#aienabled}}
        <li>{{#str}}submit_modal_ai, mod_gestionprojet{{/str}}</li>
        {{/aienabled}}
        <li>{{#str}}submit_modal_irreversible, mod_gestionprojet{{/str}}</li>
    </ul>
    {{#isgroup}}
    <div class="alert alert-warning" role="alert">
        {{#str}}submit_modal_group_warning, mod_gestionprojet{{/str}}
    </div>
    {{/isgroup}}
    <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" id="submit-confirm-checkbox">
        <label class="form-check-label" for="submit-confirm-checkbox">
            {{#str}}submit_modal_checkbox, mod_gestionprojet{{/str}}
        </label>
    </div>
</div>
```

- [ ] **Step 2: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/templates/submit_modal.mustache
git commit -m "feat(templates): add submit_modal mustache template"
```

---

## Task 9: Refondre `amd/src/submission.js` avec modale Moodle

**Files:**
- Modify: `gestionprojet/amd/src/submission.js` (réécriture complète)

- [ ] **Step 1: Réécrire entièrement le fichier**

Remplacer tout le contenu de `gestionprojet/amd/src/submission.js` par :

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
 * Submission handling for student step pages — modal confirmation + AJAX submit.
 *
 * @module     mod_gestionprojet/submission
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/modal_factory',
    'core/modal_events',
    'core/templates',
    'core/notification'
], function($, Ajax, ModalFactory, ModalEvents, Templates, Notification) {

    var config = {};

    /**
     * Initialize submission handling.
     *
     * @param {Object} cfg Configuration object from PHP.
     */
    function init(cfg) {
        config = cfg || {};
        var btn = document.getElementById('submitStepBtn');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', openModal);
    }

    /**
     * Open the confirmation modal.
     */
    function openModal() {
        Templates.render('mod_gestionprojet/submit_modal', {
            isgroup: !!config.isGroup,
            aienabled: !!config.aiEnabled
        }).then(function(html) {
            return ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: config.strings.modal_title,
                body: html,
                large: false
            });
        }).then(function(modal) {
            modal.setSaveButtonText(config.strings.confirm_submit_btn);

            // Disable save button until checkbox is checked.
            modal.getRoot().on('change', '#submit-confirm-checkbox', function() {
                modal.getRoot().find('[data-action="save"]').prop('disabled', !this.checked);
            });

            modal.getRoot().on(ModalEvents.shown, function() {
                modal.getRoot().find('[data-action="save"]').prop('disabled', true);
            });

            modal.getRoot().on(ModalEvents.save, function(e) {
                e.preventDefault();
                doSubmit(modal);
            });

            modal.show();
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Perform the AJAX submission.
     *
     * @param {Object} modal The modal instance.
     */
    function doSubmit(modal) {
        var saveBtn = modal.getRoot().find('[data-action="save"]');
        saveBtn.prop('disabled', true).text(config.strings.submitting);

        Ajax.call([{
            methodname: 'mod_gestionprojet_submit_step',
            args: {
                cmid: config.cmid,
                step: config.step,
                action: 'submit'
            }
        }])[0].done(function(data) {
            if (data.success) {
                window.location.reload();
            } else {
                modal.hide();
                window.alert(data.message || config.strings.submission_error);
            }
        }).fail(function() {
            modal.hide();
            window.alert(config.strings.submission_error);
        });
    }

    return {
        init: init
    };
});
```

- [ ] **Step 2: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/amd/src/submission.js
git commit -m "feat(js): rewrite submission.js with Moodle modal + checkbox gate"
```

---

## Task 10: Créer `amd/src/student_ai_progress.js`

**Files:**
- Create: `gestionprojet/amd/src/student_ai_progress.js`

- [ ] **Step 1: Créer le fichier**

Écrire dans `gestionprojet/amd/src/student_ai_progress.js` :

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
 * Student-side polling for AI evaluation progress.
 *
 * @module     mod_gestionprojet/student_ai_progress
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    var config = {};
    var pollTimer = null;
    var POLL_INTERVAL = 5000;

    /**
     * Initialize the progress poller.
     *
     * @param {Object} cfg {evaluationid, statusUrl, cmid, strings}
     */
    function init(cfg) {
        config = cfg || {};
        if (!config.evaluationid || !config.statusUrl) {
            return;
        }
        startPolling();
    }

    function startPolling() {
        // Immediate first check, then every POLL_INTERVAL.
        checkStatus();
        pollTimer = setInterval(checkStatus, POLL_INTERVAL);
    }

    function checkStatus() {
        var sesskeyEl = document.querySelector('input[name="sesskey"]');
        var sesskey = sesskeyEl ? sesskeyEl.value
            : (window.M && window.M.cfg && window.M.cfg.sesskey ? window.M.cfg.sesskey : '');

        var url = config.statusUrl
            + '?id=' + encodeURIComponent(config.cmid)
            + '&evaluationid=' + encodeURIComponent(config.evaluationid)
            + '&sesskey=' + encodeURIComponent(sesskey);

        fetch(url, {credentials: 'same-origin'})
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data || !data.success) {
                    return;
                }
                updateBanner(data.status);
                if (data.status === 'completed' || data.status === 'applied') {
                    clearInterval(pollTimer);
                    window.location.reload();
                } else if (data.status === 'failed') {
                    clearInterval(pollTimer);
                    // Banner already updated to "failed_student" message; no reload needed.
                }
            })
            .catch(function() {
                // Silent: retry on next tick.
            });
    }

    function updateBanner(status) {
        var banner = document.getElementById('ai-progress-banner');
        if (!banner) {
            return;
        }
        banner.dataset.status = status;
        // Toggle CSS classes for status.
        banner.className = banner.className.replace(/\bstatus-\S+/g, '');
        banner.classList.add('ai-progress-banner', 'status-' + status);

        var label = banner.querySelector('.ai-progress-label');
        if (label) {
            var key = (status === 'failed') ? 'failed_student' : status;
            if (config.strings && config.strings[key]) {
                label.textContent = config.strings[key];
            }
        }
    }

    return {
        init: init
    };
});
```

- [ ] **Step 2: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/amd/src/student_ai_progress.js
git commit -m "feat(js): add student_ai_progress polling module"
```

---

## Task 11: Modifier `pages/student_submission_section.php`

**Files:**
- Modify: `gestionprojet/pages/student_submission_section.php` (ajout chargement éval + bandeau + js_call_amd étendu)

- [ ] **Step 1: Lire le fichier complet pour bien situer les modifications**

Run: `cat -n "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/student_submission_section.php"`

- [ ] **Step 2: Ajouter le chargement de l'éval IA en cours**

Juste après les calculs de dates (`$isOverdue`, `$isDueSoon`) et **avant** la balise `?>` qui ferme le bloc PHP en haut du fichier (vers ligne 56-57), ajouter :

```php
// Load pending AI evaluation if AI is enabled and submission was made.
$pendingEval = null;
$isGroupSubmission = !empty($gestionprojet->group_submission) && !empty($groupid);
if ($isSubmitted && !empty($submission) && !empty($gestionprojet->ai_enabled)
        && in_array($step, [4, 5, 6, 7, 8, 9])) {
    require_once(__DIR__ . '/../classes/ai_evaluator.php');
    $pendingEval = \mod_gestionprojet\ai_evaluator::get_evaluation(
        $gestionprojet->id,
        $step,
        $submission->id
    );
}
```

- [ ] **Step 3: Étendre le `js_call_amd` du module submission**

Remplacer le bloc actuel :

```php
$PAGE->requires->js_call_amd('mod_gestionprojet/submission', 'init', [[
    'cmid' => $cm->id,
    'step' => $step,
    'strings' => [
        'confirm_submit' => get_string('confirm_submit', 'gestionprojet'),
        'submitting' => get_string('submitting', 'gestionprojet'),
        'submission_error' => get_string('submissionerror', 'gestionprojet'),
    ],
]]);
```

Par :

```php
$PAGE->requires->js_call_amd('mod_gestionprojet/submission', 'init', [[
    'cmid' => $cm->id,
    'step' => $step,
    'isGroup' => $isGroupSubmission,
    'aiEnabled' => !empty($gestionprojet->ai_enabled),
    'strings' => [
        'modal_title' => get_string('submit_modal_title', 'gestionprojet'),
        'confirm_submit_btn' => get_string('confirm_submit_btn', 'gestionprojet'),
        'submitting' => get_string('submitting', 'gestionprojet'),
        'submission_error' => get_string('submissionerror', 'gestionprojet'),
    ],
]]);
```

- [ ] **Step 4: Ajouter le bandeau de progression IA**

Juste **avant** le `<?php endif; ?>` final qui ferme le bloc `if ($submissionEnabled)`, ajouter :

```php

<?php if ($isSubmitted && $pendingEval && in_array($pendingEval->status, ['pending', 'processing', 'failed'])): ?>
<?php
$bannerStatusKey = ($pendingEval->status === 'failed')
    ? 'ai_progress_failed_student'
    : 'ai_progress_' . $pendingEval->status;
?>
<div id="ai-progress-banner" class="ai-progress-banner status-<?php echo s($pendingEval->status); ?>" data-status="<?php echo s($pendingEval->status); ?>">
    <span class="ai-progress-icon"><?php echo icon::render('zap', 'sm', 'inherit'); ?></span>
    <span class="ai-progress-label">
        <?php echo get_string($bannerStatusKey, 'gestionprojet'); ?>
    </span>
</div>
<?php
if (in_array($pendingEval->status, ['pending', 'processing'])) {
    $PAGE->requires->js_call_amd('mod_gestionprojet/student_ai_progress', 'init', [[
        'evaluationid' => (int)$pendingEval->id,
        'cmid' => (int)$cm->id,
        'statusUrl' => (new moodle_url('/mod/gestionprojet/ajax/get_evaluation_status.php'))->out(false),
        'strings' => [
            'pending' => get_string('ai_progress_pending', 'gestionprojet'),
            'processing' => get_string('ai_progress_processing', 'gestionprojet'),
            'failed_student' => get_string('ai_progress_failed_student', 'gestionprojet'),
        ],
    ]]);
}
?>
<?php endif; ?>
```

- [ ] **Step 5: Vérifier la syntaxe**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/student_submission_section.php"`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/pages/student_submission_section.php
git commit -m "feat(student): show AI progress banner + extend submission JS config"
```

---

## Task 12: Ajouter les styles CSS du bandeau IA

**Files:**
- Modify: `gestionprojet/styles.css` (append à la fin)

- [ ] **Step 1: Ajouter les styles**

Append à la fin de `gestionprojet/styles.css` :

```css

/* ===== AI progress banner (student side) ===== */
.path-mod-gestionprojet .ai-progress-banner {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    margin-top: 1rem;
    border-radius: 0.5rem;
    border: 1px solid transparent;
    font-size: 0.95rem;
}

.path-mod-gestionprojet .ai-progress-banner .ai-progress-icon {
    display: inline-flex;
    align-items: center;
}

.path-mod-gestionprojet .ai-progress-banner.status-pending,
.path-mod-gestionprojet .ai-progress-banner.status-processing {
    background-color: #e3f2fd;
    border-color: #90caf9;
    color: #0d47a1;
}

.path-mod-gestionprojet .ai-progress-banner.status-pending .ai-progress-icon,
.path-mod-gestionprojet .ai-progress-banner.status-processing .ai-progress-icon {
    animation: gestionprojet-spin 1.5s linear infinite;
}

.path-mod-gestionprojet .ai-progress-banner.status-failed {
    background-color: #fff3e0;
    border-color: #ffb74d;
    color: #e65100;
}

@keyframes gestionprojet-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
```

- [ ] **Step 2: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/styles.css
git commit -m "style(css): add AI progress banner styles"
```

---

## Task 13: Bump version + build AMD

**Files:**
- Modify: `gestionprojet/version.php`

- [ ] **Step 1: Mettre à jour la version**

Remplacer dans `gestionprojet/version.php` :

```php
$plugin->version = 2026050504;  // YYYYMMDDXX format
```
et
```php
$plugin->release = '2.6.2';
```

Par :

```php
$plugin->version = 2026050505;  // YYYYMMDDXX format
```
et
```php
$plugin->release = '2.6.3';
```

- [ ] **Step 2: Vérifier la syntaxe**

Run: `php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/version.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Build AMD modules**

Run: `cd "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet" && grunt amd 2>&1 | tail -20`
Expected: Une ligne "Done." sans erreur. Les fichiers `amd/build/submission.min.js` et `amd/build/student_ai_progress.min.js` doivent être créés/mis à jour.

Si grunt n'est pas dispo : noter que la build doit être faite côté serveur ou via un autre moyen avant déploiement.

- [ ] **Step 4: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/version.php gestionprojet/amd/build/
git commit -m "chore(version): bump to 2.6.3 (2026050505) for student submit + AI auto-trigger"
```

---

## Task 14: Vérifier la conformité contribution checklist Moodle

**Files:**
- Read-only verification across modified files.

- [ ] **Step 1: Scan inline `<style>` ou `<script>` dans les fichiers PHP modifiés**

Run: `grep -l '<style\|<script' "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/student_submission_section.php" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/submit_step.php" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/get_evaluation_status.php" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/classes/ai_evaluator.php" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/db/messages.php" 2>&1`
Expected: Aucun résultat.

- [ ] **Step 2: Scan superglobales PHP**

Run: `grep -n '\$_GET\|\$_POST\|\$_REQUEST\|\$_FILES' "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/student_submission_section.php" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/submit_step.php" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/get_evaluation_status.php" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/classes/ai_evaluator.php" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/db/messages.php" 2>&1`
Expected: Aucun résultat.

- [ ] **Step 3: Scan debug/print en prod**

Run: `grep -n 'var_dump\|print_r\|file_put_contents.*log\|error_log\|console.log' "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/submit_step.php" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/get_evaluation_status.php" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/classes/ai_evaluator.php" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/amd/src/submission.js" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/amd/src/student_ai_progress.js" 2>&1 | grep -v "^Binary" | grep -v "debugging("`
Expected: Aucun résultat (`debugging()` Moodle est OK et exclu du grep).

- [ ] **Step 4: Vérifier la présence du header GPL dans les nouveaux fichiers**

Run: `head -15 "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/db/messages.php" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/amd/src/student_ai_progress.js" | grep -c "distributed in the hope"`
Expected: `2`

Si le compte n'est pas 2, ajouter le header GPL complet (voir CLAUDE.md section 1) au(x) fichier(s) manquant(s) puis recommit.

- [ ] **Step 5: Si tout est OK, pas de commit (étape de vérification uniquement)**

Si des corrections ont été nécessaires :

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add -p gestionprojet/
git commit -m "chore(compliance): fix Moodle contribution checklist violations"
```

---

## Task 15: Déploiement preprod via SCP

**Files:** (lecture seule)
- Read: `TESTING.md` racine du repo pour récupérer les credentials preprod.

- [ ] **Step 1: Lire les credentials preprod**

Run: `cat "/Volumes/DONNEES/Claude code/mod_gestionprojet/TESTING.md"`

Identifier l'utilisateur SSH, l'hôte, le chemin distant du plugin, et la commande de purge cache.

- [ ] **Step 2: SCP des fichiers modifiés vers preprod**

Construire la commande SCP en se basant sur le format documenté dans `TESTING.md`. Liste des fichiers à transférer :

```
gestionprojet/ajax/submit_step.php
gestionprojet/ajax/get_evaluation_status.php
gestionprojet/classes/ai_evaluator.php
gestionprojet/db/messages.php
gestionprojet/lang/en/gestionprojet.php
gestionprojet/lang/fr/gestionprojet.php
gestionprojet/templates/submit_modal.mustache
gestionprojet/amd/src/submission.js
gestionprojet/amd/src/student_ai_progress.js
gestionprojet/amd/build/submission.min.js
gestionprojet/amd/build/student_ai_progress.min.js
gestionprojet/amd/build/submission.min.js.map
gestionprojet/amd/build/student_ai_progress.min.js.map
gestionprojet/pages/student_submission_section.php
gestionprojet/styles.css
gestionprojet/version.php
```

Note : si `TESTING.md` documente un script de déploiement automatisé, l'utiliser à la place du SCP manuel.

- [ ] **Step 3: Sur la preprod, lancer l'upgrade Moodle + purge caches**

Sur le serveur preprod :

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Ou via l'UI admin Moodle → Notifications.

- [ ] **Step 4: Vérifier que le plugin remonte la version 2.6.3**

Sur la preprod, dans Moodle : Administration du site → Plugins → Vue d'ensemble des plugins → chercher "Gestion de Projet" → vérifier que la version affichée est `2.6.3 (2026050505)`.

- [ ] **Step 5: Pas de commit pour cette tâche** (déploiement, pas de code)

---

## Task 16: Tests manuels en preprod

**Files:** (test only)

Suit les 10 scénarios listés dans la spec section 8.

- [ ] **Scénario 1: Soumission élève — IA OK + clé valide**

Connecté en élève sur la preprod, ouvrir une activité Gestion de Projet avec IA activée et clé valide :
1. Aller sur step 4 (CDCF), remplir quelques champs.
2. Cliquer "Soumettre mon travail".
3. **Attendu** : modale s'ouvre, bouton "Soumettre définitivement" désactivé.
4. Cocher la case → bouton activé.
5. Cliquer → page recharge → bandeau "Évaluation IA en cours…" + tous les inputs disabled.
6. Lancer le cron : `php admin/cli/cron.php` côté serveur preprod.
7. **Attendu** : page rechargée auto, note + feedback IA affichés via `student_ai_feedback_display.php`.

- [ ] **Scénario 2: Soumission élève — IA désactivée dans l'instance**

Désactiver `ai_enabled` dans la config de l'instance, soumettre un step :
- **Attendu** : modale sans la mention "IA automatique", soumission OK, pas de bandeau IA après reload, travail verrouillé.

- [ ] **Scénario 3: Soumission élève — IA activée mais clé manquante (ou modèle prof manquant)**

Activer `ai_enabled` mais retirer la clé API (ou supprimer le modèle prof du step) :
- **Attendu** : soumission OK, **pas** de bandeau IA après reload (l'éval n'a pas été créée — `queue_evaluation()` a levé une exception attrapée).

- [ ] **Scénario 4: Mode groupe — A soumet, B voit verrouillé**

Activer le mode groupe, élève A et B dans le même groupe :
1. A soumet step 4.
2. B ouvre la page step 4.
- **Attendu** : B voit le travail en lecture seule + bandeau IA en cours / résultat selon avancée.

- [ ] **Scénario 5: Erreur IA simulée → notif prof**

Pour simuler : configurer une clé API invalide, soumettre, lancer le cron.
- **Attendu** : éval flag `failed`. Côté élève : bandeau "IA indisponible". Côté prof : popup Moodle dans la cloche **et** email reçu (selon ses prefs).

- [ ] **Scénario 6: Re-soumission après unlock**

Prof unlock un travail soumis, élève modifie, resoumet.
- **Attendu** : nouvelle éval créée. Vérifier en DB : `SELECT * FROM mdl_gestionprojet_ai_evaluations WHERE submissionid = X ORDER BY timecreated DESC` → 2 records (ou plus).

- [ ] **Scénario 7: Steps 4 à 9 — chaque step soumissible**

Tester le flux complet sur step 4, 5, 6, 7, 8 puis 9 (FAST si activé).
- **Attendu** : modale + soumission + bandeau IA fonctionnent sur tous les steps.

- [ ] **Scénario 8: Verrouillage robuste — tentative de bypass**

Sur un travail soumis, ouvrir la console DevTools, modifier un `<input>` pour retirer `disabled readonly`, taper du texte, attendre l'autosave (10s).
- **Attendu** : autosave AJAX retourne erreur `submissionlocked`, le contenu en DB n'est pas modifié.

- [ ] **Scénario 9: Checkbox de relecture obligatoire**

Ouvrir la modale sans cocher la case → bouton "Soumettre définitivement" doit rester `disabled`.
- **Attendu** : impossible de soumettre sans cocher.

- [ ] **Scénario 10: Annulation modale**

Ouvrir la modale, cliquer "Annuler" (ou X).
- **Attendu** : modale ferme, aucune requête AJAX, statut inchangé.

---

## Task 17: Mettre à jour TESTING.md avec les scénarios

**Files:**
- Modify: `TESTING.md` (racine du repo) — ajouter une section v2.6.3.

- [ ] **Step 1: Lire TESTING.md actuel**

Run: `cat "/Volumes/DONNEES/Claude code/mod_gestionprojet/TESTING.md"`

- [ ] **Step 2: Ajouter une section v2.6.3**

Append à la fin du fichier (en respectant le style des sections existantes) :

```markdown

## v2.6.3 — Bouton Soumettre + déclenchement IA auto

10 scénarios à valider en preprod. Voir aussi `docs/superpowers/specs/2026-05-05-student-submit-ai-trigger-design.md` section 8.

1. Soumission IA OK → modale, bandeau, reload avec note
2. IA désactivée → modale sans mention IA, pas de bandeau
3. IA activée sans clé → soumission OK, pas de bandeau
4. Mode groupe — A soumet, B voit verrouillé
5. Erreur IA → bandeau neutre + notif prof Moodle/email
6. Re-soumission après unlock → nouvelle éval, ancienne en historique
7. Steps 4 à 9 — chaque step soumissible
8. Bypass tentative — autosave refuse
9. Checkbox de relecture obligatoire
10. Annulation modale
```

- [ ] **Step 3: Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add TESTING.md
git commit -m "docs(testing): add v2.6.3 manual test scenarios"
```

---

## Task 18: Push vers Forge EDU + build ZIP de release

**Files:** (release)

- [ ] **Step 1: Push sur la branche `main` de Forge EDU**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git push origin main
```

- [ ] **Step 2: Construire le ZIP de release**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
zip -r gestionprojet-v2.6.3.zip gestionprojet/ -x "*.git*" "*.claude*" "*lessons.md" "*node_modules*"
```

Vérifier que le ZIP fait une taille raisonnable (~1 MB attendu) :

Run: `ls -lh "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet-v2.6.3.zip"`

- [ ] **Step 3: Pas de commit pour cette tâche** — la release est l'artefact final.

Le ZIP peut maintenant être uploadé via l'admin Moodle de prod → Plugins → Install plugins → Upload ZIP.

---

## Self-Review Checklist (à exécuter avant de commencer)

Avant de démarrer la première tâche, l'agent exécutant doit :

1. ✅ **Spec coverage** : 11 sections de la spec couvertes par les tasks 1-18 (steps 4-9, modale, bandeau, poll, notif prof, etc.).
2. ✅ **Pas de placeholders** : tous les blocs de code sont complets, pas de "TBD".
3. ✅ **Cohérence types** : `notify_teachers_of_failure(object $evaluation, string $errorMessage)` cohérent entre Task 5 (définition) et Task 6 (appel). Variables `$pendingEval`, `$isGroupSubmission`, `$isSubmitted` cohérentes entre Task 11 et le PHP existant.
4. ✅ **Ordre des tâches** : DB/strings/messages d'abord (Tasks 3-4), backend ensuite (Tasks 1-2, 5-7), frontend (Tasks 8-12), version/déploiement à la fin (Tasks 13-18).
