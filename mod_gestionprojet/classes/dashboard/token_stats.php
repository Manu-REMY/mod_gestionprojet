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
 * Token usage statistics for teacher dashboard.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\dashboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for calculating AI token usage statistics.
 */
class token_stats {

    /**
     * Get token usage statistics for a specific step.
     *
     * @param int $gestionprojetid Instance ID
     * @param int $step Step number (4-8)
     * @return object Token statistics
     */
    public static function get_step_token_stats($gestionprojetid, $step) {
        global $DB;

        // Get aggregate statistics for completed/applied evaluations.
        $sql = "SELECT
                    COUNT(*) as total_evaluations,
                    COALESCE(SUM(prompt_tokens), 0) as total_prompt,
                    COALESCE(SUM(completion_tokens), 0) as total_completion,
                    MIN(timecreated) as first_eval,
                    MAX(timecreated) as last_eval
                FROM {gestionprojet_ai_evaluations}
                WHERE gestionprojetid = ? AND step = ?
                AND status IN ('completed', 'applied')";

        $stats = $DB->get_record_sql($sql, [$gestionprojetid, $step]);

        // Get statistics by provider.
        $bysql = "SELECT provider,
                         COUNT(*) as count,
                         COALESCE(SUM(prompt_tokens), 0) + COALESCE(SUM(completion_tokens), 0) as tokens
                  FROM {gestionprojet_ai_evaluations}
                  WHERE gestionprojetid = ? AND step = ? AND status IN ('completed', 'applied')
                  GROUP BY provider";

        $byProvider = $DB->get_records_sql($bysql, [$gestionprojetid, $step]);

        // Count by status.
        $pending = $DB->count_records('gestionprojet_ai_evaluations', [
            'gestionprojetid' => $gestionprojetid,
            'step' => $step,
            'status' => 'pending'
        ]);

        $processing = $DB->count_records('gestionprojet_ai_evaluations', [
            'gestionprojetid' => $gestionprojetid,
            'step' => $step,
            'status' => 'processing'
        ]);

        $failed = $DB->count_records('gestionprojet_ai_evaluations', [
            'gestionprojetid' => $gestionprojetid,
            'step' => $step,
            'status' => 'failed'
        ]);

        $applied = $DB->count_records('gestionprojet_ai_evaluations', [
            'gestionprojetid' => $gestionprojetid,
            'step' => $step,
            'status' => 'applied'
        ]);

        $totalEvaluations = intval($stats->total_evaluations ?? 0);
        $totalPrompt = intval($stats->total_prompt ?? 0);
        $totalCompletion = intval($stats->total_completion ?? 0);
        $totalTokens = $totalPrompt + $totalCompletion;

        // Calculate average per evaluation.
        $avgPerEval = $totalEvaluations > 0 ? round($totalTokens / $totalEvaluations) : 0;

        // Format provider breakdown for display.
        $providerList = [];
        foreach ($byProvider as $p) {
            $providerList[] = (object)[
                'provider' => ucfirst($p->provider),
                'count' => intval($p->count),
                'tokens' => intval($p->tokens),
            ];
        }

        return (object)[
            'total_evaluations' => $totalEvaluations,
            'total_tokens' => $totalTokens,
            'prompt_tokens' => $totalPrompt,
            'completion_tokens' => $totalCompletion,
            'avg_per_evaluation' => $avgPerEval,
            'first_evaluation' => $stats->first_eval ? intval($stats->first_eval) : null,
            'last_evaluation' => $stats->last_eval ? intval($stats->last_eval) : null,
            'pending_count' => $pending,
            'processing_count' => $processing,
            'failed_count' => $failed,
            'applied_count' => $applied,
            'by_provider' => $providerList,
            'has_data' => $totalEvaluations > 0,
        ];
    }

    /**
     * Get total token usage for all steps.
     *
     * @param int $gestionprojetid Instance ID
     * @return object Aggregate token statistics
     */
    public static function get_total_token_stats($gestionprojetid) {
        global $DB;

        $sql = "SELECT
                    COUNT(*) as total_evaluations,
                    COALESCE(SUM(prompt_tokens), 0) as total_prompt,
                    COALESCE(SUM(completion_tokens), 0) as total_completion
                FROM {gestionprojet_ai_evaluations}
                WHERE gestionprojetid = ?
                AND status IN ('completed', 'applied')";

        $stats = $DB->get_record_sql($sql, [$gestionprojetid]);

        return (object)[
            'total_evaluations' => intval($stats->total_evaluations ?? 0),
            'total_tokens' => intval($stats->total_prompt ?? 0) + intval($stats->total_completion ?? 0),
            'prompt_tokens' => intval($stats->total_prompt ?? 0),
            'completion_tokens' => intval($stats->total_completion ?? 0),
        ];
    }
}
