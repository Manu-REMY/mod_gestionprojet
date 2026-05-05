# Bouton "Soumettre mon travail" : verrouillage + déclenchement IA automatique

**Date :** 2026-05-05
**Version cible :** 2.6.3 (2026050505)
**Auteur :** Emmanuel REMY (collab. Claude)

---

## 1. Contexte et objectif

Les élèves doivent disposer d'un bouton **« Soumettre mon travail »** qui :

1. **Verrouille définitivement** la production de l'élève (plus aucune modification possible côté élève).
2. **Déclenche immédiatement la correction IA automatique** en arrière-plan.
3. **Affiche un retour visuel** à l'élève pendant et après l'évaluation IA.
4. **Notifie le prof** en cas d'échec de l'évaluation IA.

L'objectif pédagogique : forcer une étape consciente de finalisation et obtenir un feedback IA rapide sans intervention prof.

## 2. État actuel (avant changement)

L'infrastructure de soumission et d'IA est **déjà en place** :

| Brique | État |
|---|---|
| Bouton "Submit step" sur pages élèves (4-8) | ✅ Existe (`student_submission_section.php`) |
| Endpoint `ajax/submit_step.php` (passe `status=1`) | ✅ Existe |
| Verrouillage backend (autosave refuse `status=1`) | ✅ Existe (`autosave.php`) |
| Verrouillage UI (`disabled readonly` sur inputs) | ✅ Existe (chaque `pages/stepX.php`) |
| Mécanisme d'éval IA (`ai_evaluator::queue_evaluation()`) | ✅ Existe |
| Adhoc task `evaluate_submission` | ✅ Existe |
| `ai_auto_apply` (auto-application des notes) | ✅ Existe |
| Affichage feedback IA côté élève | ✅ Existe (`student_ai_feedback_display.php`) |
| Endpoint `evaluate.php` (déclenchement IA) | ✅ Existe — **mais réservé aux profs** |
| Step 9 (FAST) dans `submit_step.php` `$steptables` | ❌ Manque |

**Ce qui manque** : déclencher automatiquement `queue_evaluation()` après une soumission élève, avec retour visuel poll AJAX + notification prof en cas d'échec + modale de confirmation explicite + ajout step 9.

## 3. Décisions de design

| # | Décision |
|---|---|
| Steps concernés | Tous les steps soumissibles : 4 (CDCF), 5 (essai), 6 (rapport), 7 (besoin élève), 8 (carnet), 9 (FAST) |
| Si IA non disponible | Soumission acceptée, message neutre côté élève |
| Visibilité éval IA élève | Poll AJAX 5s, affichage dès dispo (sous réserve des règles de visibilité existantes du modèle prof) |
| Confirmation | Modale Bootstrap Moodle (`core/modal_factory`) avec checkbox de relecture obligatoire |
| Erreur IA | Élève : message neutre. Prof : notif Moodle (popup + email selon ses prefs) |
| Mode groupe | N'importe quel membre peut soumettre. Toute le groupe est verrouillé. Avertissement dans la modale en mode groupe. |
| Re-soumission après unlock prof | Nouvelle éval créée, ancienne conservée dans l'historique |
| Architecture | Approche **inline minimaliste** (pas d'event-driven) — cohérent avec les patterns existants |

## 4. Architecture et flux

### 4.1 Flux de soumission élève

```
[Page step] Élève clique "Soumettre mon travail"
     ↓
[JS submission.js] Modale Bootstrap (titre + texte + checkbox "J'ai relu")
     ↓
[JS] Élève coche + clique "Soumettre définitivement"
     ↓
[AJAX submit_step.php] action=submit
     ├─ require_login + sesskey + capability submit
     ├─ status = 1, timesubmitted = time()
     ├─ gestionprojet_log_change(...)
     ├─ TRY: ai_evaluator::queue_evaluation()
     │    ├─ OK → evaluationid retourné
     │    └─ Exception → log + on continue (soumission OK quand même)
     └─ JSON {success, evaluationid|null, ai_available: bool}
     ↓
[JS] Recharge la page
     ↓
[Page step rechargée]
     ├─ $isLocked=true → tous inputs disabled readonly (existant)
     └─ $pendingEval présent → bandeau "Éval IA en cours…" + poll
     ↓
[JS student_ai_progress.js] Poll 5s sur get_evaluation_status.php
     ├─ status=processing → spin
     ├─ status=completed/applied → reload pour afficher feedback
     └─ status=failed → bandeau neutre "IA indispo, prof corrigera"
     ↓
[Cron Moodle] adhoc task `evaluate_submission` (asynchrone)
     ├─ Succès → status=completed (+ apply si ai_auto_apply=1)
     └─ Exception → status=failed
                  + message_send() à tous les profs du cours (popup + email)
```

### 4.2 Acteurs (fichiers)

**Modifications :**
- `ajax/submit_step.php` — ajout queue IA + step 9 dans `$steptables`
- `classes/ai_evaluator.php` — ajout `notify_teachers_of_failure()` dans le catch de `process_evaluation()`
- `ajax/get_evaluation_status.php` — extension capability pour autoriser élève sur sa propre éval
- `pages/student_submission_section.php` — bandeau de progression IA
- `amd/src/submission.js` — refonte modale Bootstrap
- `lang/en/gestionprojet.php` + `lang/fr/gestionprojet.php` — nouvelles strings
- `styles.css` — styles bandeau IA
- `version.php` — bump 2.6.3 / 2026050505

**Nouveaux fichiers :**
- `db/messages.php` — déclaration du message provider `ai_evaluation_failed`
- `templates/submit_modal.mustache` — corps de la modale
- `amd/src/student_ai_progress.js` — poll AJAX côté élève

**Pas de changement :**
- Pas de schéma DB modifié (réutilisation de `gestionprojet_ai_evaluations`)
- Pas de modification du flux unlock prof (existe déjà)
- Pas de modification du verrouillage UI (déjà géré par `$isLocked` dans chaque step)
- Pas de modification de l'autosave (refuse déjà `status=1`)

## 5. Détail des modifications

### 5.1 `ajax/submit_step.php`

**Avant** (extrait, ligne 89-103) : update_record + log_change.
**Après** :

```php
// Update status to submitted.
$record->status = 1;
$record->timesubmitted = time();
$record->timemodified = time();
$DB->update_record($tablename, $record);

// Log the submission.
gestionprojet_log_change(...);

// Try to queue AI evaluation. Failure is non-fatal.
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

Ajout step 9 dans `$steptables` :
```php
$steptables = [
    4 => 'gestionprojet_cdcf',
    5 => 'gestionprojet_essai',
    6 => 'gestionprojet_rapport',
    7 => 'gestionprojet_besoin_eleve',
    8 => 'gestionprojet_carnet',
    9 => 'gestionprojet_fast',
];
```

Réponse JSON enrichie :
```php
echo json_encode([
    'success' => $success,
    'message' => $message,
    'evaluationid' => $evaluationid,
    'ai_available' => $aiAvailable,
    'timestamp' => time(),
]);
```

### 5.2 `classes/ai_evaluator.php`

Ajouter dans `process_evaluation()`, dans le bloc `catch (\Exception $e)`, après le `update_record` qui flag `status=failed` :

```php
self::notify_teachers_of_failure($evaluation, $e->getMessage());
```

Nouvelle méthode privée :

```php
private static function notify_teachers_of_failure(object $evaluation, string $errorMessage): void {
    $cm = get_coursemodule_from_instance('gestionprojet', $evaluation->gestionprojetid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        return;
    }
    $context = \context_module::instance($cm->id);

    $teachers = get_users_by_capability(
        $context,
        'mod/gestionprojet:grade',
        'u.id, u.firstname, u.lastname, u.email, u.lang'
    );
    if (empty($teachers)) {
        return;
    }

    $url = (new \moodle_url('/mod/gestionprojet/grading.php', ['id' => $cm->id]))->out(false);
    $activityname = format_string($cm->name);

    foreach ($teachers as $teacher) {
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
    }
}
```

### 5.3 `db/messages.php` (NOUVEAU)

```php
<?php
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

### 5.4 `ajax/get_evaluation_status.php`

Étendre la capability pour autoriser un élève à interroger **uniquement** sa propre éval (ou celle de son groupe) :

```php
if (has_capability('mod/gestionprojet:grade', $context)) {
    // Teacher: full payload (existing behaviour).
    $payload = [...];
} else {
    require_capability('mod/gestionprojet:submit', $context);
    $evaluation = $DB->get_record('gestionprojet_ai_evaluations', ['id' => $evaluationid], '*', MUST_EXIST);
    $isOwner = ($evaluation->userid && $evaluation->userid == $USER->id);
    $isGroupMember = ($evaluation->groupid && groups_is_member($evaluation->groupid, $USER->id));
    if (!$isOwner && !$isGroupMember) {
        throw new moodle_exception('nopermission');
    }
    // Sanitized payload — student only sees status/timestamps, no prompts/raw_response.
    $payload = [
        'success' => true,
        'evaluationid' => (int)$evaluation->id,
        'status' => $evaluation->status,
        'timemodified' => (int)$evaluation->timemodified,
    ];
}
echo json_encode($payload);
```

### 5.5 `pages/student_submission_section.php`

Avant le rendu, charger l'éval IA en cours :

```php
$pendingEval = null;
if ($isSubmitted && $submission && !empty($gestionprojet->ai_enabled) && in_array($step, [4,5,6,7,8,9])) {
    $pendingEval = \mod_gestionprojet\ai_evaluator::get_evaluation(
        $gestionprojet->id, $step, $submission->id
    );
}
```

Rendu du bandeau (après la div `submission-section`) :

```php
<?php if ($isSubmitted && $pendingEval && in_array($pendingEval->status, ['pending', 'processing', 'failed'])): ?>
<div id="ai-progress-banner" class="ai-progress-banner status-<?php echo s($pendingEval->status); ?>" data-status="<?php echo s($pendingEval->status); ?>">
    <span class="ai-progress-icon"><?php echo icon::render('zap', 'sm', 'inherit'); ?></span>
    <span class="ai-progress-label">
        <?php echo get_string('ai_progress_' . ($pendingEval->status === 'failed' ? 'failed_student' : $pendingEval->status), 'gestionprojet'); ?>
    </span>
</div>
<?php
if (in_array($pendingEval->status, ['pending', 'processing'])) {
    $PAGE->requires->js_call_amd('mod_gestionprojet/student_ai_progress', 'init', [[
        'evaluationid' => (int)$pendingEval->id,
        'statusUrl' => (new moodle_url('/mod/gestionprojet/ajax/get_evaluation_status.php'))->out(false),
        'strings' => [
            'pending' => get_string('ai_progress_pending', 'gestionprojet'),
            'processing' => get_string('ai_progress_processing', 'gestionprojet'),
            'failed' => get_string('ai_progress_failed_student', 'gestionprojet'),
        ],
    ]]);
}
endif; ?>
```

Aussi, modifier le `js_call_amd` existant (ligne 62 actuelle) pour transmettre `isGroup` et `aiEnabled` :

```php
// Derive isGroup locally (variable not present in this shared file).
$isGroupSubmission = !empty($gestionprojet->group_submission) && !empty($groupid);

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

### 5.6 `amd/src/submission.js` (refonte)

Voir Section 3.1 du design (modale Moodle + checkbox + AJAX).

### 5.7 `templates/submit_modal.mustache` (NOUVEAU)

Voir Section 3.2 du design.

### 5.8 `amd/src/student_ai_progress.js` (NOUVEAU)

Voir Section 3.3 du design (poll 5s).

### 5.9 `styles.css`

Ajouter les styles `.ai-progress-banner` (Section 3.5).

### 5.10 Strings (en + fr)

Voir Section 4.1 du design.

### 5.11 `version.php`

```php
$plugin->version = 2026050505;
$plugin->release = '2.6.3';
```

## 6. Cas d'usage et règles métier

### 6.1 Mode individuel

- Élève clique → modale → confirme → `submit_step.php` → status=1 + queue IA → reload.
- Bandeau "éval en cours" → poll 5s → reload quand completed/applied.

### 6.2 Mode groupe

- Élève A (membre groupe G) clique → modale (avec avertissement groupe) → confirme.
- Submission portée par `groupid=G, userid=0` (logique existante).
- Élève B (autre membre du même groupe) qui ouvre la page voit `isLocked=true` (lecture seule) + bandeau IA.
- Une seule éval IA pour le groupe.

### 6.3 IA non configurée

- `ai_enabled=0` ou pas d'API key ou pas de modèle prof : `queue_evaluation()` lève une exception, on l'attrape, on log via `debugging()`, on retourne `success=true, ai_available=false`.
- Soumission OK, pas de bandeau IA.

### 6.4 Échec d'évaluation IA

- Adhoc task lève une exception → `process_evaluation()` flag `status=failed`.
- `notify_teachers_of_failure()` envoie un message Moodle à **tous** les utilisateurs ayant la capability `mod/gestionprojet:grade` dans le contexte du module.
- Élève : bandeau "IA indisponible, ton prof corrigera".
- Prof : popup Moodle (cloche) + email selon ses prefs `message_send`.

### 6.5 Re-soumission après unlock prof

- Prof clique unlock → `submit_step.php action=unlock` → status=0.
- Élève peut modifier (autosave réactivée).
- Élève resoumet → nouveau `queue_evaluation()` → nouveau record dans `gestionprojet_ai_evaluations`.
- L'ancienne éval reste dans la table (historique). `get_evaluation()` retourne la plus récente (ORDER BY timecreated DESC).

## 7. Sécurité

- `submit_step.php` : `require_login`, `require_sesskey`, `require_capability('mod/gestionprojet:submit')` — déjà en place.
- `get_evaluation_status.php` : ajout vérification d'appartenance pour les élèves (sinon un élève pourrait lire l'éval d'un autre).
- Payload élève sanitizé — pas de `prompt_*`, pas de `raw_response`, pas de `criteria_json` (qui peuvent contenir des éléments du modèle prof).
- Modale Mustache : escape automatique via `{{#str}}` Moodle.
- Pas de `<script>` inline ni `<style>` inline (respect contribution checklist).

## 8. Tests manuels (TESTING.md à compléter)

| # | Scénario | Attendu |
|---|---|---|
| 1 | Soumission élève — IA OK | Modale → reload → bandeau "en cours" → reload auto avec note |
| 2 | Soumission élève — IA désactivée | Modale sans mention IA, soumission OK, pas de bandeau |
| 3 | Soumission élève — IA activée mais clé manquante | Soumission OK, pas de bandeau (l'éval n'a pas été créée) |
| 4 | Mode groupe — A soumet | B voit travail verrouillé + bandeau IA |
| 5 | Erreur IA simulée | Élève bandeau neutre, prof reçoit notif Moodle + email |
| 6 | Re-soumission après unlock | Nouvelle éval, ancienne dans l'historique |
| 7 | Steps 4 à 9 | Flux fonctionne sur chaque step |
| 8 | Verrouillage robuste | Modification DOM côté élève → autosave refuse (`submissionlocked`) |
| 9 | Checkbox de relecture | Bouton "Soumettre définitivement" reste désactivé tant que la case n'est pas cochée |
| 10 | Annulation modale | Cliquer Annuler → modale ferme, rien ne change |

## 9. Risques et mitigations

| Risque | Mitigation |
|---|---|
| Race condition (2 membres groupe en simultané) | 2e requête voit status=1 → `alreadysubmitted` (logique existante) |
| Spam d'emails si IA tombe en boucle | Un seul message par éval failed (notification au passage à `failed`, pas en boucle) |
| Élève ferme la page pendant le poll | Adhoc task continue côté serveur ; au prochain affichage, page rechargée détecte le statut |
| Cron Moodle non actif | Bandeau reste sur "en attente". Problème admin Moodle, pas du plugin. |
| Charge IA si tout le monde soumet en même temps | Adhoc tasks gérées par Moodle, traitées séquentiellement. Pas de mitigation côté plugin. |

## 10. Déploiement

1. SCP des fichiers modifiés vers preprod.
2. Sur preprod : `php admin/cli/upgrade.php`, `grunt amd`, `php admin/cli/purge_caches.php`.
3. Tester les 10 scénarios.
4. Commit + push sur `main` Forge EDU.
5. Build ZIP : `zip -r gestionprojet.zip gestionprojet/ -x "*.git*" "*.claude*" "*lessons.md"`.
6. Upload via admin Moodle pour prod.

## 11. Hors-scope (NOT in this spec)

- Statistiques d'utilisation des soumissions / IA (dashboard).
- Notification push browser à l'élève quand l'éval est terminée (le poll suffit).
- Possibilité pour l'élève de demander une re-éval IA (réservé prof aujourd'hui).
- Modification du système d'unlock prof.
- Refonte de la visibilité des critères IA pour l'élève (gérée par le modèle prof — inchangé).
