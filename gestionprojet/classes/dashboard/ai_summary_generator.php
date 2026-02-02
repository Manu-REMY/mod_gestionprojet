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
 * AI Summary Generator for teacher dashboard.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\dashboard;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../ai_config.php');

/**
 * Class for generating AI summaries of student work.
 */
class ai_summary_generator {

    /** @var int Minimum evaluations required to generate summary */
    const MIN_EVALUATIONS = 1;

    /** @var int Cache duration in seconds (1 hour) */
    const CACHE_DURATION = 3600;

    /**
     * Get existing summary for a step.
     *
     * @param int $gestionprojetid Instance ID
     * @param int $step Step number (4-8)
     * @return object Summary object
     */
    public static function get_summary($gestionprojetid, $step) {
        global $DB;

        $summary = $DB->get_record('gestionprojet_ai_summaries', [
            'gestionprojetid' => $gestionprojetid,
            'step' => $step,
        ]);

        if (!$summary) {
            return self::no_summary_response($gestionprojetid, $step);
        }

        return (object)[
            'has_summary' => true,
            'difficulties' => json_decode($summary->difficulties ?? '[]', true) ?: [],
            'strengths' => json_decode($summary->strengths ?? '[]', true) ?: [],
            'recommendations' => json_decode($summary->recommendations ?? '[]', true) ?: [],
            'general_observation' => $summary->general_observation ?? '',
            'submissions_analyzed' => intval($summary->submissions_analyzed),
            'provider' => $summary->provider,
            'model' => $summary->model,
            'prompt_tokens' => intval($summary->prompt_tokens ?? 0),
            'completion_tokens' => intval($summary->completion_tokens ?? 0),
            'generated_date' => userdate($summary->timemodified, get_string('strftimedatetime', 'langconfig')),
            'is_stale' => (time() - $summary->timemodified) > self::CACHE_DURATION,
            'timemodified' => intval($summary->timemodified),
        ];
    }

    /**
     * Generate or regenerate AI summary for a step.
     *
     * @param object $gestionprojet Instance record
     * @param int $step Step number (4-8)
     * @param bool $force Force regeneration even if cache is fresh
     * @return object Summary result
     */
    public static function generate_summary($gestionprojet, $step, $force = false) {
        global $DB;

        // Check if AI is enabled.
        if (empty($gestionprojet->ai_enabled)) {
            return (object)[
                'success' => false,
                'message' => get_string('dashboard:ai_disabled', 'gestionprojet'),
            ];
        }

        // Check if a fresh summary exists.
        if (!$force) {
            $existing = $DB->get_record('gestionprojet_ai_summaries', [
                'gestionprojetid' => $gestionprojet->id,
                'step' => $step,
            ]);

            if ($existing && (time() - $existing->timemodified) < self::CACHE_DURATION) {
                return (object)[
                    'success' => true,
                    'message' => get_string('dashboard:summary_cached', 'gestionprojet'),
                    'summary' => self::get_summary($gestionprojet->id, $step),
                ];
            }
        }

        // Collect all completed or applied AI evaluations for this step.
        $evaluations = $DB->get_records_sql(
            'SELECT id, parsed_feedback, criteria_json, keywords_found, keywords_missing, suggestions
             FROM {gestionprojet_ai_evaluations}
             WHERE gestionprojetid = ? AND step = ? AND status IN (?, ?)
             AND parsed_feedback IS NOT NULL AND parsed_feedback != ?',
            [$gestionprojet->id, $step, 'applied', 'completed', '']
        );

        if (count($evaluations) < self::MIN_EVALUATIONS) {
            return self::no_summary_response($gestionprojet->id, $step, count($evaluations));
        }

        // Build and send the synthesis prompt.
        try {
            $result = self::call_ai_for_summary($gestionprojet, $step, $evaluations);

            if (!$result['success']) {
                return (object)[
                    'success' => false,
                    'message' => $result['error'] ?? get_string('dashboard:ai_error', 'gestionprojet'),
                ];
            }

            // Parse and save the summary.
            $parsed = self::parse_ai_response($result['response']);
            self::save_summary($gestionprojet->id, $step, $parsed, count($evaluations), $result);

            return (object)[
                'success' => true,
                'message' => get_string('dashboard:summary_generated', 'gestionprojet'),
                'summary' => self::get_summary($gestionprojet->id, $step),
            ];

        } catch (\Exception $e) {
            return (object)[
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Call AI API to generate summary.
     *
     * @param object $gestionprojet Instance record
     * @param int $step Step number
     * @param array $evaluations Array of evaluation records
     * @return array Result with success status and response
     */
    private static function call_ai_for_summary($gestionprojet, $step, $evaluations) {
        $provider = $gestionprojet->ai_provider;

        // Get API key.
        $apikey = \mod_gestionprojet\ai_config::get_effective_api_key(
            $provider,
            \mod_gestionprojet\ai_config::decrypt_api_key($gestionprojet->ai_api_key ?? '')
        );

        if (empty($apikey)) {
            return ['success' => false, 'error' => get_string('ai_api_key_required', 'gestionprojet')];
        }

        // Build the prompt.
        $stepname = get_string('step' . $step, 'gestionprojet');
        $prompt = self::build_synthesis_prompt($evaluations, $stepname);

        // Make API request.
        $endpoint = \mod_gestionprojet\ai_config::ENDPOINTS[$provider];
        $model = self::get_model_for_provider($provider);

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 120,
            'CURLOPT_CONNECTTIMEOUT' => 30,
        ]);

        $headers = self::build_headers($provider, $apikey);
        $body = self::build_request_body($provider, $model, $prompt);

        $response = $curl->post($endpoint, json_encode($body), [
            'CURLOPT_HTTPHEADER' => $headers,
        ]);

        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if ($httpcode === 0) {
            return ['success' => false, 'error' => get_string('ai_connection_error', 'gestionprojet')];
        }

        $decoded = json_decode($response, true);

        if ($httpcode >= 400) {
            $errormsg = $decoded['error']['message'] ?? "HTTP Error: $httpcode";
            return ['success' => false, 'error' => $errormsg];
        }

        // Extract content and token usage.
        $content = self::extract_content($decoded, $provider);
        $promptTokens = self::extract_prompt_tokens($decoded, $provider);
        $completionTokens = self::extract_completion_tokens($decoded, $provider);

        return [
            'success' => true,
            'response' => $content,
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
        ];
    }

    /**
     * Build the synthesis prompt.
     *
     * @param array $evaluations Array of evaluation records
     * @param string $stepname Step display name
     * @return string The prompt text
     */
    private static function build_synthesis_prompt($evaluations, $stepname) {
        $feedbacks = [];
        foreach ($evaluations as $eval) {
            $feedbacks[] = [
                'feedback' => $eval->parsed_feedback ?? '',
                'keywords_found' => $eval->keywords_found ?? '',
                'keywords_missing' => $eval->keywords_missing ?? '',
                'suggestions' => $eval->suggestions ?? '',
            ];
        }

        $feedbacksJson = json_encode($feedbacks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return "Tu es un assistant pedagogique expert. Analyse les " . count($evaluations) . " feedbacks IA suivants pour l'etape '{$stepname}' et genere une synthese structuree.

IMPORTANT: Reponds UNIQUEMENT avec un objet JSON valide, sans texte avant ou apres. Le JSON doit suivre exactement ce format:

{
    \"difficulties\": [\"difficulte 1\", \"difficulte 2\", \"difficulte 3\"],
    \"strengths\": [\"point fort 1\", \"point fort 2\", \"point fort 3\"],
    \"recommendations\": [\"recommandation 1\", \"recommandation 2\"],
    \"general_observation\": \"Observation generale en 2-3 phrases\"
}

Regles:
- Identifie les 3-5 difficultes les plus frequentes
- Identifie les 3-5 points forts les plus frequents
- Propose 2-4 recommandations pedagogiques concretes
- L'observation generale doit etre concise et actionnable

Feedbacks a analyser:
$feedbacksJson";
    }

    /**
     * Parse AI response into structured data.
     *
     * @param string $response AI response content
     * @return object Parsed summary data
     */
    private static function parse_ai_response($response) {
        // Try to extract JSON from response.
        $json = $response;

        // Handle markdown code blocks.
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
            $json = trim($matches[1]);
        }

        // Try to parse JSON.
        $parsed = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback: try to find JSON object in response.
            if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
                $parsed = json_decode($matches[0], true);
            }
        }

        // Ensure we have valid arrays.
        return (object)[
            'difficulties' => is_array($parsed['difficulties'] ?? null) ? $parsed['difficulties'] : [],
            'strengths' => is_array($parsed['strengths'] ?? null) ? $parsed['strengths'] : [],
            'recommendations' => is_array($parsed['recommendations'] ?? null) ? $parsed['recommendations'] : [],
            'general_observation' => $parsed['general_observation'] ?? '',
        ];
    }

    /**
     * Save summary to database.
     *
     * @param int $gestionprojetid Instance ID
     * @param int $step Step number
     * @param object $parsed Parsed summary data
     * @param int $evaluationCount Number of evaluations analyzed
     * @param array $apiResult API call result with tokens
     */
    private static function save_summary($gestionprojetid, $step, $parsed, $evaluationCount, $apiResult) {
        global $DB;

        $existing = $DB->get_record('gestionprojet_ai_summaries', [
            'gestionprojetid' => $gestionprojetid,
            'step' => $step,
        ]);

        $record = new \stdClass();
        $record->gestionprojetid = $gestionprojetid;
        $record->step = $step;
        $record->difficulties = json_encode($parsed->difficulties, JSON_UNESCAPED_UNICODE);
        $record->strengths = json_encode($parsed->strengths, JSON_UNESCAPED_UNICODE);
        $record->recommendations = json_encode($parsed->recommendations, JSON_UNESCAPED_UNICODE);
        $record->general_observation = $parsed->general_observation;
        $record->submissions_analyzed = $evaluationCount;
        $record->provider = $apiResult['provider'] ?? '';
        $record->model = $apiResult['model'] ?? '';
        $record->prompt_tokens = $apiResult['prompt_tokens'] ?? 0;
        $record->completion_tokens = $apiResult['completion_tokens'] ?? 0;
        $record->timemodified = time();

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('gestionprojet_ai_summaries', $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record('gestionprojet_ai_summaries', $record);
        }
    }

    /**
     * Return response when no summary is available.
     *
     * @param int $gestionprojetid Instance ID
     * @param int $step Step number
     * @param int $currentCount Current evaluation count
     * @return object Response object
     */
    private static function no_summary_response($gestionprojetid, $step, $currentCount = null) {
        global $DB;

        if ($currentCount === null) {
            $currentCount = $DB->count_records_sql(
                'SELECT COUNT(*) FROM {gestionprojet_ai_evaluations}
                 WHERE gestionprojetid = ? AND step = ? AND status IN (?, ?)
                 AND parsed_feedback IS NOT NULL AND parsed_feedback != ?',
                [$gestionprojetid, $step, 'applied', 'completed', '']
            );
        }

        return (object)[
            'has_summary' => false,
            'message' => get_string('dashboard:notenoughevaluations', 'gestionprojet'),
            'min_required' => self::MIN_EVALUATIONS,
            'current_count' => $currentCount,
            'difficulties' => [],
            'strengths' => [],
            'recommendations' => [],
            'general_observation' => '',
        ];
    }

    /**
     * Get AI model for provider.
     *
     * @param string $provider Provider name
     * @return string Model name
     */
    private static function get_model_for_provider($provider) {
        $models = [
            'openai' => 'gpt-4o-mini',
            'anthropic' => 'claude-3-haiku-20240307',
            'mistral' => 'mistral-small-latest',
            'albert' => 'neuralbeagle',
        ];
        return $models[$provider] ?? 'gpt-3.5-turbo';
    }

    /**
     * Build HTTP headers for API request.
     *
     * @param string $provider Provider name
     * @param string $apikey API key
     * @return array Headers
     */
    private static function build_headers($provider, $apikey) {
        $headers = ['Content-Type: application/json'];

        switch ($provider) {
            case 'anthropic':
                $headers[] = "x-api-key: $apikey";
                $headers[] = 'anthropic-version: 2023-06-01';
                break;
            default:
                $headers[] = "Authorization: Bearer $apikey";
        }

        return $headers;
    }

    /**
     * Build request body for API.
     *
     * @param string $provider Provider name
     * @param string $model Model name
     * @param string $prompt Prompt text
     * @return array Request body
     */
    private static function build_request_body($provider, $model, $prompt) {
        $body = [
            'model' => $model,
            'max_tokens' => 2000,
            'temperature' => 0.3,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if ($provider === 'anthropic') {
            unset($body['temperature']);
        }

        return $body;
    }

    /**
     * Extract content from API response.
     *
     * @param array $response Decoded response
     * @param string $provider Provider name
     * @return string Content text
     */
    private static function extract_content($response, $provider) {
        if ($provider === 'anthropic') {
            return $response['content'][0]['text'] ?? '';
        }
        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Extract prompt tokens from response.
     *
     * @param array $response Decoded response
     * @param string $provider Provider name
     * @return int Token count
     */
    private static function extract_prompt_tokens($response, $provider) {
        return intval($response['usage']['prompt_tokens'] ?? $response['usage']['input_tokens'] ?? 0);
    }

    /**
     * Extract completion tokens from response.
     *
     * @param array $response Decoded response
     * @param string $provider Provider name
     * @return int Token count
     */
    private static function extract_completion_tokens($response, $provider) {
        return intval($response['usage']['completion_tokens'] ?? $response['usage']['output_tokens'] ?? 0);
    }
}
