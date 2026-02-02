# Plan de Développement - Tableau de Bord Enseignant

## Objectif

Créer un tableau de bord pour chaque type de travail (étapes 4-8) accessible depuis les pages de modèle de correction. Ce tableau de bord fournira à l'enseignant :

1. **État des soumissions** - Graphique de progression
2. **Moyenne des évaluations** - Notes moyennes avec distribution
3. **Récapitulatif IA** - Synthèse des difficultés et points positifs
4. **Bilan des tokens** - Consommation pour les feedbacks

---

## Phase 1 : Infrastructure de Base

### 1.1 Création de la table pour les récapitulatifs IA

**Fichier:** `db/install.xml`

Nouvelle table `gestionprojet_ai_summaries` :

```xml
<TABLE NAME="gestionprojet_ai_summaries">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="gestionprojetid" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="step" TYPE="int" LENGTH="2" NOTNULL="true"/>
    <FIELD NAME="summary_type" TYPE="char" LENGTH="20" NOTNULL="true" COMMENT="difficulties, strengths, general"/>
    <FIELD NAME="content" TYPE="text" NOTNULL="false"/>
    <FIELD NAME="submissions_analyzed" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="provider" TYPE="char" LENGTH="20"/>
    <FIELD NAME="model" TYPE="char" LENGTH="50"/>
    <FIELD NAME="prompt_tokens" TYPE="int" LENGTH="10" DEFAULT="0"/>
    <FIELD NAME="completion_tokens" TYPE="int" LENGTH="10" DEFAULT="0"/>
    <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
    <KEY NAME="gestionprojetid" TYPE="foreign" FIELDS="gestionprojetid" REFTABLE="gestionprojet" REFFIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="step_type" UNIQUE="false" FIELDS="gestionprojetid, step, summary_type"/>
  </INDEXES>
</TABLE>
```

### 1.2 Script de migration

**Fichier:** `db/upgrade.php`

```php
if ($oldversion < 2026013000) {
    // Create ai_summaries table
    $table = new xmldb_table('gestionprojet_ai_summaries');
    // ... définition des champs
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }
    upgrade_mod_savepoint(true, 2026013000, 'gestionprojet');
}
```

### 1.3 Mise à jour de version

**Fichier:** `version.php`

```php
$plugin->version = 2026013000;
```

---

## Phase 2 : Classes PHP pour le Dashboard

### 2.1 Classe de statistiques des soumissions

**Fichier:** `classes/dashboard/submission_stats.php`

```php
namespace mod_gestionprojet\dashboard;

class submission_stats {

    /**
     * Récupère les statistiques de soumission pour une étape
     * @return object {total, submitted, draft, graded, avg_grade, grade_distribution}
     */
    public static function get_step_stats($gestionprojetid, $step) {
        global $DB;

        $table = gestionprojet_get_step_table($step);

        // Comptages
        $all = $DB->get_records($table, ['gestionprojetid' => $gestionprojetid]);
        $total = count($all);
        $submitted = count(array_filter($all, fn($s) => $s->status == 1));
        $draft = $total - $submitted;
        $graded = array_filter($all, fn($s) => $s->status == 1 && !empty($s->grade));

        // Moyenne
        $grades = array_column($graded, 'grade');
        $avg = empty($grades) ? null : round(array_sum($grades) / count($grades), 2);

        // Distribution (tranches de 4 points : 0-4, 4-8, 8-12, 12-16, 16-20)
        $distribution = [0, 0, 0, 0, 0];
        foreach ($grades as $g) {
            $index = min(4, floor($g / 4));
            $distribution[$index]++;
        }

        return (object)[
            'total' => $total,
            'submitted' => $submitted,
            'draft' => $draft,
            'graded_count' => count($graded),
            'avg_grade' => $avg,
            'grade_distribution' => $distribution,
            'completion_percent' => $total > 0 ? round($submitted / $total * 100) : 0
        ];
    }
}
```

### 2.2 Classe de statistiques des tokens

**Fichier:** `classes/dashboard/token_stats.php`

```php
namespace mod_gestionprojet\dashboard;

class token_stats {

    /**
     * Récupère les statistiques de tokens pour une étape
     */
    public static function get_step_token_stats($gestionprojetid, $step) {
        global $DB;

        $sql = "SELECT
                    COUNT(*) as total_evaluations,
                    SUM(prompt_tokens) as total_prompt,
                    SUM(completion_tokens) as total_completion,
                    AVG(prompt_tokens + completion_tokens) as avg_per_eval,
                    MIN(timecreated) as first_eval,
                    MAX(timecreated) as last_eval
                FROM {gestionprojet_ai_evaluations}
                WHERE gestionprojetid = ? AND step = ?
                AND status IN ('completed', 'applied')";

        $stats = $DB->get_record_sql($sql, [$gestionprojetid, $step]);

        // Statistiques par provider
        $byProvider = $DB->get_records_sql(
            "SELECT provider,
                    COUNT(*) as count,
                    SUM(prompt_tokens + completion_tokens) as tokens
             FROM {gestionprojet_ai_evaluations}
             WHERE gestionprojetid = ? AND step = ? AND status IN ('completed', 'applied')
             GROUP BY provider",
            [$gestionprojetid, $step]
        );

        return (object)[
            'total_evaluations' => $stats->total_evaluations ?? 0,
            'total_tokens' => ($stats->total_prompt ?? 0) + ($stats->total_completion ?? 0),
            'prompt_tokens' => $stats->total_prompt ?? 0,
            'completion_tokens' => $stats->total_completion ?? 0,
            'avg_per_evaluation' => round($stats->avg_per_eval ?? 0),
            'first_evaluation' => $stats->first_eval,
            'last_evaluation' => $stats->last_eval,
            'by_provider' => $byProvider
        ];
    }
}
```

### 2.3 Classe de génération du récapitulatif IA

**Fichier:** `classes/dashboard/ai_summary_generator.php`

```php
namespace mod_gestionprojet\dashboard;

class ai_summary_generator {

    /**
     * Génère ou met à jour le récapitulatif IA pour une étape
     */
    public static function generate_summary($gestionprojet, $step, $force = false) {
        global $DB;

        // Vérifier si une synthèse récente existe (< 1h)
        if (!$force) {
            $existing = $DB->get_record('gestionprojet_ai_summaries', [
                'gestionprojetid' => $gestionprojet->id,
                'step' => $step,
                'summary_type' => 'general'
            ]);
            if ($existing && (time() - $existing->timemodified) < 3600) {
                return self::get_summary($gestionprojet->id, $step);
            }
        }

        // Collecter tous les feedbacks IA de l'étape
        $evaluations = $DB->get_records('gestionprojet_ai_evaluations', [
            'gestionprojetid' => $gestionprojet->id,
            'step' => $step,
            'status' => 'applied'  // Seulement les évaluations appliquées
        ], '', 'id, parsed_feedback, criteria_json, keywords_found, keywords_missing, suggestions');

        if (count($evaluations) < 3) {
            return (object)[
                'has_summary' => false,
                'message' => get_string('dashboard:notenoughevaluations', 'gestionprojet'),
                'min_required' => 3,
                'current_count' => count($evaluations)
            ];
        }

        // Construire le prompt de synthèse
        $prompt = self::build_synthesis_prompt($evaluations, $step);

        // Appeler l'API IA
        $config = new \mod_gestionprojet\ai_config($gestionprojet);
        $response = $config->call_api($prompt);

        // Parser et sauvegarder
        $summary = self::parse_and_save($gestionprojet->id, $step, $response, count($evaluations));

        return $summary;
    }

    private static function build_synthesis_prompt($evaluations, $step) {
        $stepName = get_string('step' . $step . '_title', 'gestionprojet');

        $feedbacks = [];
        foreach ($evaluations as $eval) {
            $feedbacks[] = [
                'feedback' => $eval->parsed_feedback,
                'keywords_found' => $eval->keywords_found,
                'keywords_missing' => $eval->keywords_missing,
                'suggestions' => $eval->suggestions
            ];
        }

        return [
            'role' => 'system',
            'content' => "Tu es un assistant pédagogique expert. Analyse les feedbacks IA suivants pour l'étape '{$stepName}' et génère une synthèse structurée.

Format de réponse JSON attendu :
{
    \"difficulties\": [\"difficulté 1\", \"difficulté 2\", ...],
    \"strengths\": [\"point fort 1\", \"point fort 2\", ...],
    \"recommendations\": [\"recommandation 1\", \"recommandation 2\", ...],
    \"general_observation\": \"Observation générale en 2-3 phrases\"
}

Feedbacks à analyser :
" . json_encode($feedbacks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ];
    }
}
```

---

## Phase 3 : Template Mustache du Dashboard

### 3.1 Template principal

**Fichier:** `templates/dashboard_teacher.mustache`

```mustache
{{!
    Teacher Dashboard for a specific step

    Context variables:
    * step - Step number (4-8)
    * stepname - Localized step name
    * submissionstats - Submission statistics object
    * tokenstats - Token usage statistics
    * aisummary - AI-generated summary
    * canedit - Can generate new summary
}}

<div class="gestionprojet-dashboard card mb-4" data-step="{{step}}">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">
            <i class="fa fa-chart-bar mr-2"></i>
            {{#str}}dashboard:title, gestionprojet{{/str}} - {{stepname}}
        </h4>
    </div>

    <div class="card-body">
        {{! Section 1: Progress Bar }}
        <div class="dashboard-section mb-4">
            <h5><i class="fa fa-tasks mr-2"></i>{{#str}}dashboard:submissionprogress, gestionprojet{{/str}}</h5>

            <div class="progress-container">
                <div class="d-flex justify-content-between mb-2">
                    <span>{{#str}}dashboard:submitted, gestionprojet{{/str}}: {{submissionstats.submitted}} / {{submissionstats.total}}</span>
                    <span class="badge badge-{{#submissionstats.completion_percent}}{{#gte100}}success{{/gte100}}{{#gte50}}warning{{/gte50}}{{^gte50}}danger{{/gte50}}{{/submissionstats.completion_percent}}">
                        {{submissionstats.completion_percent}}%
                    </span>
                </div>

                <div class="progress" style="height: 25px;">
                    <div class="progress-bar bg-success" style="width: {{submissionstats.graded_percent}}%"
                         title="{{#str}}dashboard:graded, gestionprojet{{/str}}: {{submissionstats.graded_count}}">
                        {{submissionstats.graded_count}} {{#str}}dashboard:graded, gestionprojet{{/str}}
                    </div>
                    <div class="progress-bar bg-warning" style="width: {{submissionstats.pending_percent}}%"
                         title="{{#str}}dashboard:pendinggrade, gestionprojet{{/str}}">
                        {{submissionstats.pending_grade}} {{#str}}dashboard:pending, gestionprojet{{/str}}
                    </div>
                    <div class="progress-bar bg-secondary" style="width: {{submissionstats.draft_percent}}%"
                         title="{{#str}}dashboard:draft, gestionprojet{{/str}}">
                        {{submissionstats.draft}} {{#str}}dashboard:draft, gestionprojet{{/str}}
                    </div>
                </div>

                <div class="legend mt-2 small text-muted">
                    <span class="mr-3"><span class="badge badge-success">&nbsp;</span> {{#str}}dashboard:graded, gestionprojet{{/str}}</span>
                    <span class="mr-3"><span class="badge badge-warning">&nbsp;</span> {{#str}}dashboard:pendinggrade, gestionprojet{{/str}}</span>
                    <span><span class="badge badge-secondary">&nbsp;</span> {{#str}}dashboard:draft, gestionprojet{{/str}}</span>
                </div>
            </div>
        </div>

        {{! Section 2: Grade Average }}
        <div class="dashboard-section mb-4">
            <h5><i class="fa fa-graduation-cap mr-2"></i>{{#str}}dashboard:gradeaverage, gestionprojet{{/str}}</h5>

            <div class="row">
                <div class="col-md-4">
                    <div class="stat-card text-center p-3 border rounded">
                        <div class="stat-value display-4 {{#submissionstats.avg_grade}}{{#gte12}}text-success{{/gte12}}{{#gte8}}text-warning{{/gte8}}{{^gte8}}text-danger{{/gte8}}{{/submissionstats.avg_grade}}">
                            {{#submissionstats.avg_grade}}{{submissionstats.avg_grade}}{{/submissionstats.avg_grade}}
                            {{^submissionstats.avg_grade}}-{{/submissionstats.avg_grade}}
                        </div>
                        <div class="stat-label text-muted">/ 20</div>
                    </div>
                </div>

                <div class="col-md-8">
                    <canvas id="grade-distribution-{{step}}" height="150"></canvas>
                </div>
            </div>
        </div>

        {{! Section 3: AI Summary }}
        {{#aienabled}}
        <div class="dashboard-section mb-4">
            <h5>
                <i class="fa fa-robot mr-2"></i>{{#str}}dashboard:aisummary, gestionprojet{{/str}}
                {{#canedit}}
                <button class="btn btn-sm btn-outline-primary ml-2 refresh-summary" data-step="{{step}}">
                    <i class="fa fa-sync"></i> {{#str}}dashboard:refreshsummary, gestionprojet{{/str}}
                </button>
                {{/canedit}}
            </h5>

            {{#aisummary.has_summary}}
            <div class="row">
                <div class="col-md-6">
                    <div class="summary-card difficulties border-left border-danger pl-3 mb-3">
                        <h6 class="text-danger"><i class="fa fa-exclamation-triangle mr-1"></i>{{#str}}dashboard:difficulties, gestionprojet{{/str}}</h6>
                        <ul class="mb-0">
                            {{#aisummary.difficulties}}
                            <li>{{.}}</li>
                            {{/aisummary.difficulties}}
                        </ul>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="summary-card strengths border-left border-success pl-3 mb-3">
                        <h6 class="text-success"><i class="fa fa-check-circle mr-1"></i>{{#str}}dashboard:strengths, gestionprojet{{/str}}</h6>
                        <ul class="mb-0">
                            {{#aisummary.strengths}}
                            <li>{{.}}</li>
                            {{/aisummary.strengths}}
                        </ul>
                    </div>
                </div>
            </div>

            {{#aisummary.recommendations}}
            <div class="recommendations alert alert-info">
                <h6><i class="fa fa-lightbulb mr-1"></i>{{#str}}dashboard:recommendations, gestionprojet{{/str}}</h6>
                <ul class="mb-0">
                    {{#aisummary.recommendations}}
                    <li>{{.}}</li>
                    {{/aisummary.recommendations}}
                </ul>
            </div>
            {{/aisummary.recommendations}}

            <p class="text-muted small">
                <i class="fa fa-info-circle mr-1"></i>
                {{#str}}dashboard:analyzedfrom, gestionprojet, {{aisummary.submissions_analyzed}}{{/str}}
                - {{#str}}dashboard:generatedat, gestionprojet{{/str}}: {{aisummary.generated_date}}
            </p>
            {{/aisummary.has_summary}}

            {{^aisummary.has_summary}}
            <div class="alert alert-secondary">
                <i class="fa fa-info-circle mr-2"></i>
                {{aisummary.message}}
                {{#aisummary.min_required}}
                ({{aisummary.current_count}}/{{aisummary.min_required}} {{#str}}dashboard:evaluationsrequired, gestionprojet{{/str}})
                {{/aisummary.min_required}}
            </div>
            {{/aisummary.has_summary}}
        </div>
        {{/aienabled}}

        {{! Section 4: Token Usage }}
        {{#aienabled}}
        <div class="dashboard-section">
            <h5><i class="fa fa-coins mr-2"></i>{{#str}}dashboard:tokenusage, gestionprojet{{/str}}</h5>

            <div class="row">
                <div class="col-md-3">
                    <div class="token-stat text-center p-2 border rounded">
                        <div class="h4 mb-0">{{tokenstats.total_evaluations}}</div>
                        <small class="text-muted">{{#str}}dashboard:evaluations, gestionprojet{{/str}}</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="token-stat text-center p-2 border rounded">
                        <div class="h4 mb-0">{{tokenstats.total_tokens}}</div>
                        <small class="text-muted">{{#str}}dashboard:totaltokens, gestionprojet{{/str}}</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="token-stat text-center p-2 border rounded">
                        <div class="h4 mb-0">{{tokenstats.prompt_tokens}}</div>
                        <small class="text-muted">{{#str}}dashboard:prompttokens, gestionprojet{{/str}}</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="token-stat text-center p-2 border rounded">
                        <div class="h4 mb-0">{{tokenstats.completion_tokens}}</div>
                        <small class="text-muted">{{#str}}dashboard:completiontokens, gestionprojet{{/str}}</small>
                    </div>
                </div>
            </div>

            {{#tokenstats.avg_per_evaluation}}
            <p class="mt-2 text-muted small">
                <i class="fa fa-calculator mr-1"></i>
                {{#str}}dashboard:avgpereval, gestionprojet, {{tokenstats.avg_per_evaluation}}{{/str}}
            </p>
            {{/tokenstats.avg_per_evaluation}}

            {{#tokenstats.by_provider}}
            <div class="provider-breakdown mt-2 small">
                {{#.}}
                <span class="badge badge-light mr-2">{{provider}}: {{tokens}} tokens ({{count}} éval.)</span>
                {{/.}}
            </div>
            {{/tokenstats.by_provider}}
        </div>
        {{/aienabled}}
    </div>
</div>

{{#js}}
require(['mod_gestionprojet/dashboard'], function(Dashboard) {
    Dashboard.initGradeChart({{step}}, {{{gradeDistributionJson}}});
});
{{/js}}
```

---

## Phase 4 : Module JavaScript AMD

### 4.1 Module Dashboard

**Fichier:** `amd/src/dashboard.js`

```javascript
define(['jquery', 'core/ajax', 'core/notification', 'core/chartjs'],
function($, Ajax, Notification, Chart) {

    return {
        /**
         * Initialize grade distribution chart
         */
        initGradeChart: function(step, distribution) {
            var ctx = document.getElementById('grade-distribution-' + step);
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['0-4', '5-8', '9-12', '13-16', '17-20'],
                    datasets: [{
                        label: M.util.get_string('dashboard:students', 'gestionprojet'),
                        data: distribution,
                        backgroundColor: [
                            'rgba(220, 53, 69, 0.7)',   // danger
                            'rgba(255, 193, 7, 0.7)',  // warning
                            'rgba(23, 162, 184, 0.7)', // info
                            'rgba(40, 167, 69, 0.7)',  // success
                            'rgba(0, 123, 255, 0.7)'   // primary
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        },

        /**
         * Refresh AI summary
         */
        refreshSummary: function(cmid, step) {
            $('.refresh-summary[data-step="' + step + '"]')
                .prop('disabled', true)
                .find('i').addClass('fa-spin');

            Ajax.call([{
                methodname: 'mod_gestionprojet_generate_ai_summary',
                args: { cmid: cmid, step: step, force: true }
            }])[0].done(function(response) {
                if (response.success) {
                    // Reload dashboard section
                    location.reload();
                } else {
                    Notification.alert(
                        M.util.get_string('error', 'core'),
                        response.message
                    );
                }
            }).fail(Notification.exception).always(function() {
                $('.refresh-summary[data-step="' + step + '"]')
                    .prop('disabled', false)
                    .find('i').removeClass('fa-spin');
            });
        },

        /**
         * Initialize dashboard
         */
        init: function(cmid) {
            var self = this;

            // Bind refresh buttons
            $(document).on('click', '.refresh-summary', function() {
                var step = $(this).data('step');
                self.refreshSummary(cmid, step);
            });
        }
    };
});
```

---

## Phase 5 : Intégration dans les Pages Teacher

### 5.1 Modification des pages stepX_teacher.php

**Fichier:** `pages/step4_teacher.php` (et similaire pour 5-8)

Ajouter après l'en-tête et avant le formulaire :

```php
// === DASHBOARD SECTION ===
if (has_capability('mod/gestionprojet:grade', $context)) {
    // Get statistics
    $submissionStats = \mod_gestionprojet\dashboard\submission_stats::get_step_stats($gestionprojet->id, 4);
    $tokenStats = \mod_gestionprojet\dashboard\token_stats::get_step_token_stats($gestionprojet->id, 4);
    $aiSummary = \mod_gestionprojet\dashboard\ai_summary_generator::get_summary($gestionprojet->id, 4);

    // Prepare template context
    $dashboardContext = [
        'step' => 4,
        'stepname' => get_string('step4_title', 'gestionprojet'),
        'submissionstats' => $submissionStats,
        'tokenstats' => $tokenStats,
        'aisummary' => $aiSummary,
        'aienabled' => !empty($gestionprojet->ai_enabled),
        'canedit' => has_capability('mod/gestionprojet:configureteacherpages', $context),
        'gradeDistributionJson' => json_encode($submissionStats->grade_distribution)
    ];

    // Render dashboard
    echo $OUTPUT->render_from_template('mod_gestionprojet/dashboard_teacher', $dashboardContext);
}
// === END DASHBOARD SECTION ===
```

### 5.2 Fonction helper pour inclure le dashboard

**Fichier:** `lib.php`

```php
/**
 * Renders the teacher dashboard for a specific step
 *
 * @param object $gestionprojet Instance record
 * @param int $step Step number (4-8)
 * @param context_module $context Module context
 * @return string Rendered HTML
 */
function gestionprojet_render_step_dashboard($gestionprojet, $step, $context) {
    global $OUTPUT, $PAGE;

    if (!has_capability('mod/gestionprojet:grade', $context)) {
        return '';
    }

    // Load dashboard classes
    require_once(__DIR__ . '/classes/dashboard/submission_stats.php');
    require_once(__DIR__ . '/classes/dashboard/token_stats.php');
    require_once(__DIR__ . '/classes/dashboard/ai_summary_generator.php');

    // Get statistics
    $submissionStats = \mod_gestionprojet\dashboard\submission_stats::get_step_stats($gestionprojet->id, $step);
    $tokenStats = \mod_gestionprojet\dashboard\token_stats::get_step_token_stats($gestionprojet->id, $step);
    $aiSummary = \mod_gestionprojet\dashboard\ai_summary_generator::get_summary($gestionprojet->id, $step);

    // Calculate percentages for progress bar
    if ($submissionStats->total > 0) {
        $submissionStats->graded_percent = round($submissionStats->graded_count / $submissionStats->total * 100);
        $submissionStats->pending_grade = $submissionStats->submitted - $submissionStats->graded_count;
        $submissionStats->pending_percent = round($submissionStats->pending_grade / $submissionStats->total * 100);
        $submissionStats->draft_percent = round($submissionStats->draft / $submissionStats->total * 100);
    } else {
        $submissionStats->graded_percent = 0;
        $submissionStats->pending_grade = 0;
        $submissionStats->pending_percent = 0;
        $submissionStats->draft_percent = 0;
    }

    // Step name
    $stepname = get_string('step' . $step . '_title', 'gestionprojet');

    // Prepare context
    $dashboardContext = [
        'step' => $step,
        'stepname' => $stepname,
        'submissionstats' => $submissionStats,
        'tokenstats' => $tokenStats,
        'aisummary' => $aiSummary,
        'aienabled' => !empty($gestionprojet->ai_enabled),
        'canedit' => has_capability('mod/gestionprojet:configureteacherpages', $context),
        'gradeDistributionJson' => json_encode($submissionStats->grade_distribution)
    ];

    // Add JS
    $PAGE->requires->js_call_amd('mod_gestionprojet/dashboard', 'init', [$PAGE->cm->id]);

    return $OUTPUT->render_from_template('mod_gestionprojet/dashboard_teacher', $dashboardContext);
}
```

---

## Phase 6 : Web Services et AJAX

### 6.1 Déclaration du service

**Fichier:** `db/services.php`

```php
$functions = [
    'mod_gestionprojet_generate_ai_summary' => [
        'classname'   => 'mod_gestionprojet\external\generate_ai_summary',
        'methodname'  => 'execute',
        'description' => 'Generate AI summary for a step',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/gestionprojet:grade'
    ],
];
```

### 6.2 Classe External

**Fichier:** `classes/external/generate_ai_summary.php`

```php
namespace mod_gestionprojet\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

class generate_ai_summary extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'step' => new external_value(PARAM_INT, 'Step number'),
            'force' => new external_value(PARAM_BOOL, 'Force regeneration', VALUE_DEFAULT, false)
        ]);
    }

    public static function execute($cmid, $step, $force) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'step' => $step,
            'force' => $force
        ]);

        // Get context and check capability
        $cm = get_coursemodule_from_id('gestionprojet', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/gestionprojet:grade', $context);

        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

        try {
            $summary = \mod_gestionprojet\dashboard\ai_summary_generator::generate_summary(
                $gestionprojet,
                $params['step'],
                $params['force']
            );

            return [
                'success' => true,
                'message' => '',
                'summary' => $summary
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'summary' => null
            ];
        }
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL),
            'message' => new external_value(PARAM_TEXT),
            'summary' => new external_value(PARAM_RAW, '', VALUE_OPTIONAL)
        ]);
    }
}
```

---

## Phase 7 : Chaînes de Langue

### 7.1 Français

**Fichier:** `lang/fr/gestionprojet.php`

```php
// Dashboard strings
$string['dashboard:title'] = 'Tableau de bord';
$string['dashboard:submissionprogress'] = 'État des soumissions';
$string['dashboard:submitted'] = 'Soumis';
$string['dashboard:draft'] = 'Brouillon';
$string['dashboard:graded'] = 'Noté';
$string['dashboard:pending'] = 'En attente';
$string['dashboard:pendinggrade'] = 'En attente de note';
$string['dashboard:gradeaverage'] = 'Moyenne des notes';
$string['dashboard:aisummary'] = 'Synthèse IA';
$string['dashboard:refreshsummary'] = 'Actualiser';
$string['dashboard:difficulties'] = 'Difficultés identifiées';
$string['dashboard:strengths'] = 'Points forts';
$string['dashboard:recommendations'] = 'Recommandations pédagogiques';
$string['dashboard:analyzedfrom'] = 'Basé sur {$a} évaluations';
$string['dashboard:generatedat'] = 'Généré le';
$string['dashboard:tokenusage'] = 'Consommation de tokens';
$string['dashboard:evaluations'] = 'Évaluations IA';
$string['dashboard:totaltokens'] = 'Tokens totaux';
$string['dashboard:prompttokens'] = 'Tokens (prompt)';
$string['dashboard:completiontokens'] = 'Tokens (réponse)';
$string['dashboard:avgpereval'] = 'Moyenne par évaluation : {$a} tokens';
$string['dashboard:notenoughevaluations'] = 'Pas assez d\'évaluations pour générer une synthèse';
$string['dashboard:evaluationsrequired'] = 'évaluations minimum requises';
$string['dashboard:students'] = 'Élèves';
```

### 7.2 Anglais

**Fichier:** `lang/en/gestionprojet.php`

```php
// Dashboard strings
$string['dashboard:title'] = 'Dashboard';
$string['dashboard:submissionprogress'] = 'Submission Progress';
$string['dashboard:submitted'] = 'Submitted';
$string['dashboard:draft'] = 'Draft';
$string['dashboard:graded'] = 'Graded';
$string['dashboard:pending'] = 'Pending';
$string['dashboard:pendinggrade'] = 'Pending grade';
$string['dashboard:gradeaverage'] = 'Grade Average';
$string['dashboard:aisummary'] = 'AI Summary';
$string['dashboard:refreshsummary'] = 'Refresh';
$string['dashboard:difficulties'] = 'Identified Difficulties';
$string['dashboard:strengths'] = 'Strengths';
$string['dashboard:recommendations'] = 'Teaching Recommendations';
$string['dashboard:analyzedfrom'] = 'Based on {$a} evaluations';
$string['dashboard:generatedat'] = 'Generated at';
$string['dashboard:tokenusage'] = 'Token Usage';
$string['dashboard:evaluations'] = 'AI Evaluations';
$string['dashboard:totaltokens'] = 'Total tokens';
$string['dashboard:prompttokens'] = 'Prompt tokens';
$string['dashboard:completiontokens'] = 'Completion tokens';
$string['dashboard:avgpereval'] = 'Average per evaluation: {$a} tokens';
$string['dashboard:notenoughevaluations'] = 'Not enough evaluations to generate summary';
$string['dashboard:evaluationsrequired'] = 'minimum evaluations required';
$string['dashboard:students'] = 'Students';
```

---

## Phase 8 : Tests et Validation

### 8.1 Checklist de test

- [ ] Vérifier l'affichage du dashboard sur chaque page step4-8_teacher.php
- [ ] Tester le graphique de progression avec différents états de soumission
- [ ] Vérifier le calcul de la moyenne des notes
- [ ] Tester la génération du récapitulatif IA (min 3 évaluations)
- [ ] Vérifier le bouton d'actualisation de la synthèse
- [ ] Tester l'affichage des statistiques de tokens
- [ ] Vérifier les permissions (seuls les enseignants voient le dashboard)
- [ ] Tester avec IA désactivée (sections AI masquées)
- [ ] Vérifier la responsivité sur mobile

### 8.2 Données de test

```sql
-- Créer des soumissions de test
INSERT INTO mdl_gestionprojet_cdcf (gestionprojetid, groupid, userid, status, grade) VALUES
(1, 1, 0, 1, 15.5),
(1, 2, 0, 1, 12.0),
(1, 3, 0, 1, 18.0),
(1, 4, 0, 0, NULL),
(1, 5, 0, 1, 14.5);

-- Créer des évaluations IA de test
INSERT INTO mdl_gestionprojet_ai_evaluations (gestionprojetid, step, status, prompt_tokens, completion_tokens) VALUES
(1, 4, 'applied', 850, 450),
(1, 4, 'applied', 920, 380),
(1, 4, 'applied', 780, 520);
```

---

## Résumé des Fichiers à Créer/Modifier

### Nouveaux fichiers

| Fichier | Description |
|---------|-------------|
| `classes/dashboard/submission_stats.php` | Statistiques des soumissions |
| `classes/dashboard/token_stats.php` | Statistiques des tokens |
| `classes/dashboard/ai_summary_generator.php` | Générateur de synthèse IA |
| `classes/external/generate_ai_summary.php` | Service web pour AJAX |
| `templates/dashboard_teacher.mustache` | Template du dashboard |
| `amd/src/dashboard.js` | Module JavaScript |

### Fichiers à modifier

| Fichier | Modifications |
|---------|---------------|
| `db/install.xml` | Ajouter table `gestionprojet_ai_summaries` |
| `db/upgrade.php` | Script de migration |
| `db/services.php` | Déclarer le service web |
| `version.php` | Incrémenter version |
| `lib.php` | Ajouter `gestionprojet_render_step_dashboard()` |
| `pages/step4_teacher.php` | Intégrer le dashboard |
| `pages/step5_teacher.php` | Intégrer le dashboard |
| `pages/step6_teacher.php` | Intégrer le dashboard |
| `pages/step7_teacher.php` | Intégrer le dashboard |
| `pages/step8_teacher.php` | Intégrer le dashboard |
| `lang/fr/gestionprojet.php` | Chaînes FR |
| `lang/en/gestionprojet.php` | Chaînes EN |

---

## Estimation des Phases

| Phase | Tâches |
|-------|--------|
| 1 | Infrastructure DB (table + migration) |
| 2 | Classes PHP (stats + token + AI summary) |
| 3 | Template Mustache |
| 4 | Module JavaScript AMD |
| 5 | Intégration pages teacher |
| 6 | Web services AJAX |
| 7 | Chaînes de langue |
| 8 | Tests et validation |

---

## Notes Techniques

1. **Cache du récapitulatif IA** : La synthèse est mise en cache pendant 1h pour éviter des appels API répétés. Le bouton "Actualiser" force la régénération.

2. **Seuil minimum** : 3 évaluations appliquées minimum pour générer une synthèse pertinente.

3. **Tokens de synthèse** : Les tokens utilisés pour générer la synthèse sont comptabilisés séparément dans la table `gestionprojet_ai_summaries`.

4. **Responsivité** : Le dashboard utilise le grid Bootstrap 5 pour s'adapter aux écrans mobiles.

5. **Performance** : Les requêtes SQL utilisent des index existants sur `gestionprojetid` et `step`.
