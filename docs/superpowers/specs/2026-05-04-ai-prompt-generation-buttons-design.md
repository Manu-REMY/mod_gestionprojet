# Spec — Boutons de génération du prompt de correction IA

**Date** : 2026-05-04
**Auteur** : Emmanuel REMY (via brainstorming)
**Cible** : `mod_gestionprojet` v2.5.0
**Pages concernées** : modèles de correction enseignant, étapes 4 à 9

---

## 1. Contexte et problème

Chaque page de modèle de correction enseignant (`pages/step{4..9}_teacher.php`) contient un textarea `ai_instructions` où l'enseignant rédige des instructions spécifiques destinées à l'IA correctrice. Ces instructions sont injectées dans le prompt système assemblé par `classes/ai_prompt_builder.php` lors de l'évaluation d'une production élève.

Aujourd'hui :

- Le textarea est livré vide pour les nouvelles activités, sauf pour les étapes 4 et 7 qui disposent d'un texte par défaut (`ai_instructions_default_step4`, `ai_instructions_default_step7`).
- L'enseignant doit rédiger ses instructions à la main, sans aide.

L'enseignant souhaite deux raccourcis :

1. **Modèle par défaut (option C)** — insérer un texte d'instructions par défaut adapté à l'étape, en un clic.
2. **Génération assistée par IA (option A)** — demander à l'IA configurée dans l'activité de produire des instructions de correction adaptées au modèle de correction *actuellement* rempli (produit, milieu, FP, interacteurs… selon l'étape).

## 2. Objectifs et non-objectifs

**Objectifs**

- Ajouter deux boutons au-dessus du textarea `ai_instructions` sur les 6 pages de modèle de correction (étapes 4-9).
- Permettre la génération en un clic d'un texte d'instructions personnalisé via le provider IA déjà configuré dans l'activité.
- Compléter les chaînes de langue manquantes (`ai_instructions_default_step{5,6,8,9}`) pour uniformiser le comportement du bouton C.
- Respecter intégralement la checklist de contribution Moodle (cf. `CLAUDE.md` §1-11).

**Non-objectifs**

- Pas de modification du flux d'évaluation des productions élèves.
- Pas de nouvelle table DB.
- Pas de nouveau provider IA. Réutiliser exclusivement les classes `ai_provider/*` existantes.
- Pas de stockage du prompt généré ailleurs que dans le textarea (l'autosave habituel persiste la valeur).

## 3. Périmètre fonctionnel

### 3.1 Bouton « Modèle par défaut » (option C)

- 100 % JavaScript, aucun appel réseau.
- Insère dans le textarea la chaîne `ai_instructions_default_step{N}` (déjà rendue côté PHP comme variable JS au chargement).
- Si le textarea est non-vide, demande confirmation `confirm()` avant remplacement (cas **2a**).
- Déclenche un autosave après remplissage.

### 3.2 Bouton « Générer depuis le modèle » (option A)

- Disponible uniquement si `gestionprojet.ai_enabled = 1` ET provider configuré.
- Désactivé tant que le modèle de correction est vide (cas **1a**) — détection JS sur les champs métier de l'étape (cf. `STEP_FIELDS` dans `ai_prompt_builder`). Tooltip : « Remplissez d'abord le modèle de correction ».
- Au clic :
  1. Si textarea non-vide → `confirm()` (cas **2a**).
  2. Désactiver le bouton et afficher l'état « Génération en cours… » avec spinner inline.
  3. POST AJAX vers `ajax/generate_ai_instructions.php`.
  4. À la réponse : injecter le texte dans le textarea, déclencher autosave, toast de succès.
  5. Sur erreur : toast d'erreur, réactiver le bouton.

### 3.3 Cas de bord retenus

| Cas | Comportement |
|---|---|
| Modèle de correction vide | Bouton « Générer » désactivé, tooltip explicatif |
| Textarea `ai_instructions` non vide | Confirmation `confirm()` avant remplacement (vaut pour C et A) |
| `ai_enabled = 0` | Bouton « Générer » désactivé, tooltip « IA désactivée dans la configuration de l'activité ». Bouton C reste actif. |
| Provider mal configuré (clé API manquante côté config) | Erreur `no_provider` renvoyée par l'endpoint, toast côté client |
| Provider IA en échec | Erreur `ai_failed` avec message remonté (tronqué à 200 caractères côté toast) |

## 4. Architecture

```
[Page step{N}_teacher.php] (N ∈ {4,5,6,7,8,9})
   │
   │  Boutons "Modèle par défaut" + "Générer depuis le modèle"
   │   au-dessus du textarea #ai_instructions
   │
   ├──► (C) JS pur : remplace textarea par lang-string ai_instructions_default_step{N}
   │
   └──► (A) AMD module mod_gestionprojet/generate_ai_instructions
           │
           ├─ confirm() si textarea non-vide
           ├─ spinner + état désactivé
           └─ POST /mod/gestionprojet/ajax/generate_ai_instructions.php
                  │
                  ▼
           ┌──────────────────────────────────────────────────┐
           │ ajax/generate_ai_instructions.php                 │
           │  - require_login + require_sesskey                │
           │  - require_capability(:configureteacherpages)     │
           │  - vérifie ai_enabled + provider configuré        │
           │  - vérifie modèle non-vide                        │
           │  - $builder->build_meta_prompt(step, $tmpmodel)   │
           │  - $provider->generate($system, $user)            │
           │  - retourne JSON {success, instructions}          │
           └──────────────────────────────────────────────────┘
```

### 4.1 Composants nouveaux

| Fichier | Rôle |
|---|---|
| `ajax/generate_ai_instructions.php` | Endpoint AJAX |
| `amd/src/generate_ai_instructions.js` | Module AMD client |
| `tests/ai_meta_prompt_test.php` | Tests PHPUnit du méta-prompt |

### 4.2 Composants modifiés

| Fichier | Modification |
|---|---|
| `classes/ai_prompt_builder.php` | Ajout méthode `build_meta_prompt()` |
| `pages/step4_teacher.php` … `step8_teacher.php` | Insertion du bloc boutons + script init |
| `templates/step9_form.mustache` | Insertion du bloc boutons (variante mustache) |
| `pages/step9_teacher.php` | Sérialisation du modèle pour le JS + appel `js_call_amd()` |
| `lang/fr/gestionprojet.php` + `lang/en/gestionprojet.php` | 15 nouvelles clés × 2 langues |
| `styles.css` | Bloc `.path-mod-gestionprojet .ai-instructions-actions` |
| `version.php` | Bump 2.5.0 |

## 5. UI

### 5.1 Maquette

```
┌─────────────────────────────────────────────────────────────────┐
│ 🤖 Instructions pour l'IA correctrice                           │
│                                                                 │
│   [↻ Modèle par défaut]  [✨ Générer depuis le modèle]   ⓘ     │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ <textarea ai_instructions>                               │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  Aide : Fournissez des instructions spécifiques pour guider…   │
└─────────────────────────────────────────────────────────────────┘
```

### 5.2 États du bouton « Générer »

- **Actif** : `ai_enabled` ET au moins un champ métier non-vide.
- **Désactivé : IA off** : tooltip `ai_instructions_tooltip_disabled`.
- **Désactivé : modèle vide** : tooltip `ai_instructions_tooltip_empty`.
- **En cours** : disabled, spinner, libellé `ai_instructions_btn_generating`.

### 5.3 CSS

Bloc namespacé `.path-mod-gestionprojet .ai-instructions-actions { display: flex; gap: .5rem; margin-bottom: .5rem; }` — boutons cohérents visuellement avec les `.btn-save` / `.btn-add` existants. Pas de couleur exotique.

## 6. Backend — endpoint AJAX

**Fichier** : `ajax/generate_ai_instructions.php`

### 6.1 Sécurité

```php
require_login($course, false, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:configureteacherpages', $context);
```

### 6.2 Inputs

| Paramètre | Type Moodle | Méthode |
|---|---|---|
| `id` | `PARAM_INT` (cmid) | POST |
| `step` | `PARAM_INT` (4..9) | POST |
| `model_data` | `PARAM_RAW` (JSON sérialisé du modèle, re-validé champ par champ) | POST |

### 6.3 Réponse JSON

```json
{ "success": true,  "instructions": "..." }
{ "success": false, "error": "ai_disabled" }
{ "success": false, "error": "no_provider" }
{ "success": false, "error": "model_empty" }
{ "success": false, "error": "invalid_step" }
{ "success": false, "error": "ai_failed", "message": "..." }
```

### 6.4 Logique

1. Décoder `model_data`. Whitelist sur `ai_prompt_builder::STEP_FIELDS[$step]` — toute clé inconnue est ignorée.
2. Vérifier qu'au moins un champ métier whitelisé est non-vide → sinon `model_empty`.
3. Construire un objet `stdClass` temporaire `$tmpmodel` peuplé avec les champs reçus (non persisté).
4. Récupérer la config IA via `ai_config::get_config($cm->instance)`. Si null ou `enabled = 0` → `ai_disabled`.
5. Récupérer la clé effective : `ai_config::get_effective_api_key($aiconfig->provider, $aiconfig->api_key)`. Si vide → `no_provider`.
6. Instancier le provider : `ai_evaluator::get_provider($aiconfig->provider, $apikey)` (méthode statique existante).
7. Appeler `$builder->build_meta_prompt($step, $tmpmodel)` → `['system', 'user']`.
8. Appeler `$provider->evaluate($system, $user, ai_evaluator::get_model_for_provider($aiconfig->provider), $maxtokens=1500)`. Capture exception → `ai_failed`.
9. Le provider retourne `['content' => 'texte brut', 'prompt_tokens' => N, 'completion_tokens' => M]`. Lire `$response['content']` directement.
10. Retourner ce texte comme `instructions`.

**Note : pas de nouvelle méthode `generate_text` nécessaire.** Le `parse_response()` de chaque provider concret retourne déjà le contenu textuel brut sous la clé `content`. Le parsing JSON spécifique à l'évaluation élève (note, critères, feedback) se fait *après*, dans `ai_response_parser::parse()`, appelé uniquement par `ai_evaluator`. Notre endpoint court-circuite ce parsing JSON et utilise directement le `content`.

## 7. Méta-prompt — `ai_prompt_builder::build_meta_prompt()`

### 7.1 Signature

```php
public function build_meta_prompt(int $step, object $teachermodel): array
// returns ['system' => string, 'user' => string]
```

### 7.2 System prompt (générique)

```
Tu es un expert pédagogique en technologie. Ta mission est de rédiger
des instructions de correction destinées à un autre IA correcteur, qui
évaluera des productions d'élèves de collège/lycée.

Les instructions que tu produis doivent :
- Être en français, à la 2e personne du singulier ("tu")
- Préciser les points d'attention spécifiques au modèle fourni
- Indiquer les éléments obligatoires, les bonus, les pénalités éventuelles
- Rester concises (8-15 lignes max)
- Être réutilisables tel quel par l'IA correctrice

Réponds UNIQUEMENT avec le texte des instructions, sans préambule ni
balisage Markdown.
```

### 7.3 User prompt (par étape)

```
Voici le modèle de correction rempli par l'enseignant pour l'étape
{STEP_CONTEXT[$step]}.

Critères officiels d'évaluation :
{liste depuis STEP_CRITERIA[$step], format "- name (poids X/20) : description"}

Modèle rempli :
{format_teacher_model($step, $teachermodel)}

Rédige maintenant les instructions de correction adaptées à ce modèle
précis.
```

### 7.4 Réutilisation

Les constantes `STEP_CONTEXT`, `STEP_CRITERIA`, `FIELD_LABELS` et la méthode `format_teacher_model()` sont réutilisées telles quelles. La nouvelle méthode n'introduit aucune duplication.

## 8. Module AMD côté client

**Fichier** : `amd/src/generate_ai_instructions.js`

```js
define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {
    return {
        init: function(config) {
            // config = {
            //   cmid, step, defaultText, aiEnabled,
            //   getModelData: () => ({...}),     // injecté par chaque page
            //   isModelEmpty: (data) => bool,
            //   onGenerated: (text) => void      // remplit textarea + autosave
            // }
        }
    };
});
```

Responsabilités :

- Câbler le bouton « Modèle par défaut » → confirm si textarea non-vide → injecter `defaultText` → `onGenerated()`.
- Câbler le bouton « Générer depuis le modèle » → check `aiEnabled` → check `isModelEmpty` → confirm si textarea non-vide → POST AJAX → `onGenerated()` ou toast d'erreur.
- Gérer les états visuels (disabled, spinner, libellé dynamique).
- Récupérer les libellés via `core/str` pour rester compatible avec la langue active.

Chaque page `step{N}_teacher.php` appelle :

```php
$PAGE->requires->js_call_amd('mod_gestionprojet/generate_ai_instructions', 'init', [[
    'cmid' => $cm->id,
    'step' => $step,
    'defaultText' => get_string('ai_instructions_default_step' . $step, 'gestionprojet'),
    'aiEnabled' => (bool) $gestionprojet->ai_enabled,
]]);
```

`getModelData` / `isModelEmpty` / `onGenerated` sont fournis par le code JS spécifique à chaque page, qui connaît la sérialisation du formulaire (cf. `serializeData` dans `step4_teacher.php`).

**Note sur la conformité CLAUDE.md §3** : les pages `step{4..8}_teacher.php` actuelles contiennent déjà du JS inline (pattern hérité). Cette spec n'introduit **pas de nouveau JS inline** — toute la logique des deux boutons vit dans le module AMD ; seuls quelques **paramètres** (cmid, step, defaultText, aiEnabled) sont passés via `js_call_amd()`. La fonction `serializeData` existe déjà inline ; on l'expose comme callback au module AMD via une variable globale `window.gestionprojetTeacherSerialize_step{N}` (ou équivalent), sans étendre la dette inline.

## 9. Chaînes de langue (15 nouvelles × 2 langues = 30 entrées)

### 9.1 Libellés UI

| Clé | FR | EN |
|---|---|---|
| `ai_instructions_btn_default` | `Modèle par défaut` | `Default template` |
| `ai_instructions_btn_generate` | `Générer depuis le modèle` | `Generate from model` |
| `ai_instructions_btn_generating` | `Génération en cours…` | `Generating…` |
| `ai_instructions_tooltip_empty` | `Remplissez d'abord le modèle de correction` | `Fill in the correction model first` |
| `ai_instructions_tooltip_disabled` | `IA désactivée dans la configuration de l'activité` | `AI is disabled in the activity settings` |
| `ai_instructions_confirm_replace` | `Remplacer le contenu actuel des instructions ?` | `Replace the current instructions?` |
| `ai_instructions_error_generic` | `Échec de la génération. Réessayez.` | `Generation failed. Please retry.` |
| `ai_instructions_error_disabled` | `L'IA est désactivée dans la configuration de l'activité.` | `AI is disabled in the activity settings.` |
| `ai_instructions_error_no_provider` | `Aucun fournisseur d'IA n'est configuré.` | `No AI provider is configured.` |
| `ai_instructions_error_model_empty` | `Remplissez d'abord le modèle de correction.` | `Fill in the correction model first.` |
| `ai_instructions_success` | `Instructions générées avec succès.` | `Instructions generated successfully.` |

### 9.2 Textes par défaut

`ai_instructions_default_step5`, `ai_instructions_default_step6`, `ai_instructions_default_step8`, `ai_instructions_default_step9`. Rédigés en phase d'implémentation, calqués sur la structure des défauts existants (step4, step7) :

- **Rôle** : « Tu es un professeur de technologie expérimenté au collège/lycée. »
- **Contexte de l'étape** : 2-3 phrases reprenant `STEP_CONTEXT[$step]` du `ai_prompt_builder`.
- **Critères d'attention** : liste des points à valoriser (dérivée de `STEP_CRITERIA[$step]`).
- **Tonalité** : « Sois bienveillant, valorise les efforts, propose des pistes d'amélioration concrètes. »

## 10. Tests

### 10.1 PHPUnit (`tests/ai_meta_prompt_test.php`)

- `test_build_meta_prompt_step4()` : vérifie que `system` contient « expert pédagogique » et `user` contient les valeurs des champs `produit`/`milieu`/`fp`.
- `test_build_meta_prompt_each_step()` : data provider sur `[4, 5, 6, 7, 8, 9]`, vérifie que `STEP_CONTEXT[$step]` est dans `user`.
- `test_build_meta_prompt_empty_model()` : modèle vide → `user` contient le marqueur « (Modèle de correction non renseigné… ».

Pas de test sur l'endpoint AJAX (peu de logique propre, dépendant de Moodle bootstrap) — vérification manuelle.

### 10.2 Vérification manuelle (à inscrire dans `TESTING.md`)

1. Page `step4_teacher.php`, modèle vide → bouton « Générer » désactivé + tooltip.
2. Remplir `produit` → bouton « Générer » devient actif.
3. Cliquer « Modèle par défaut » avec textarea vide → contenu inséré + autosave.
4. Cliquer « Modèle par défaut » avec textarea non-vide → confirmation.
5. Cliquer « Générer » → spinner, puis textarea rempli avec un texte cohérent.
6. Désactiver `ai_enabled` dans config activité → bouton « Générer » désactivé + tooltip.
7. Idem pour les étapes 5, 6, 7, 8, 9.

## 11. Conformité checklist Moodle (CLAUDE.md §1-11)

| § | Contrainte | Application |
|---|---|---|
| 1 | GPL header complet | Endpoint + module AMD + test : header complet |
| 2 | Pas de CSS inline | Tout dans `styles.css` namespacé |
| 3 | Pas de JS inline | Logique dans `amd/src/generate_ai_instructions.js`, appelé via `$PAGE->requires->js_call_amd()` |
| 4 | Commentaires en anglais | PHPDoc + commentaires JS en anglais |
| 5 | Pas de debug en prod | Aucun `var_dump` / `error_log` / `console.log` |
| 6 | Pas de superglobals | `required_param()` partout |
| 7 | Sécurité sur entry point | `require_login` + `require_sesskey` + `require_capability` |
| 8 | `delete_instance()` | N/A (aucune nouvelle table) |
| 9 | Strings via lang files | 30 nouvelles entrées FR + EN |
| 10 | Bump version | `2.5.0` (`2026050400`) |
| 11 | Nouvelle table | N/A |

## 12. Plan de déploiement

1. Branche `feat/ai-prompt-generation-buttons` depuis `main`.
2. Implémenter dans l'ordre du §13 ci-dessous.
3. `grunt amd` après chaque modif JS.
4. `php admin/cli/purge_caches.php` après chaque ajout de chaîne de langue ou modification de template.
5. Recette manuelle locale + preprod (cf. `TESTING.md`).
6. Bump `version.php` → 2.5.0 / `2026050400`.
7. Push `main` sur Forge EDU + ZIP via `zip -r gestionprojet.zip gestionprojet/ -x "*.git*" "*.claude*" "*lessons.md"`.
8. Upload sur `ent-occitanie.com/moodle`.

## 13. Ordre d'implémentation

1. `ai_prompt_builder::build_meta_prompt()` + tests PHPUnit.
2. Endpoint `ajax/generate_ai_instructions.php`.
3. Module AMD `amd/src/generate_ai_instructions.js`, build via `grunt amd`.
4. Chaînes de langue FR + EN (libellés UI + 4 défauts manquants).
5. Modification des 5 pages PHP `step{4,5,6,7,8}_teacher.php`.
6. Modification du template `templates/step9_form.mustache` + page `step9_teacher.php`.
7. CSS namespacé dans `styles.css`.
8. Bump version + entrée `CHANGELOG.md`.
9. Recette manuelle (TESTING.md).

## 14. Risques et mitigations

| Risque | Mitigation |
|---|---|
| Le provider IA renvoie du Markdown / du préambule malgré l'instruction | Le system prompt insiste explicitement « UNIQUEMENT le texte ». L'enseignant peut éditer manuellement. |
| Coût de tokens si l'enseignant clique en boucle | Pas de garde explicite — la limitation vient du provider lui-même. À surveiller via `gestionprojet_ai_summaries` si pertinent. |
| Step 9 a un pattern différent (mustache + champ data_json complexe) | Adaptation séparée du template + serialization JS spécifique. Tests manuels dédiés step 9. |
| `ai_enabled` peut être activé sans clé API valide (sauf Albert) | L'erreur `ai_failed` remonte le message du provider, l'enseignant comprend et reconfigure. |
