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
 * OpenAI Provider for Gestion de Projet.
 *
 * Implements the AI provider interface for OpenAI's API.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\ai_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * OpenAI provider implementation.
 */
class openai_provider extends base_provider {

    /** @var string API endpoint */
    const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /** @var array Available models */
    const MODELS = [
        'gpt-4o-mini',
        'gpt-4o',
        'gpt-4-turbo',
        'gpt-3.5-turbo',
    ];

    /** @var string Default model for evaluation */
    const DEFAULT_MODEL = 'gpt-4o-mini';

    /** @var string Model used for testing */
    const TEST_MODEL = 'gpt-3.5-turbo';

    /**
     * Get provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'openai';
    }

    /**
     * Get available models.
     *
     * @return array
     */
    public function get_models(): array {
        return self::MODELS;
    }

    /**
     * Get default model.
     *
     * @return string
     */
    public function get_default_model(): string {
        return self::DEFAULT_MODEL;
    }

    /**
     * Get test model.
     *
     * @return string
     */
    protected function get_test_model(): string {
        return self::TEST_MODEL;
    }

    /**
     * Get API endpoint.
     *
     * @return string
     */
    protected function get_endpoint(): string {
        return self::ENDPOINT;
    }

    /**
     * Build HTTP headers.
     *
     * @return array
     */
    protected function build_headers(): array {
        return [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apikey,
        ];
    }

    /**
     * Build request body.
     *
     * @param string $systemprompt System prompt
     * @param string $userprompt User prompt
     * @param string $model Model to use
     * @param int $maxtokens Maximum tokens
     * @return array
     */
    protected function build_body(string $systemprompt, string $userprompt, string $model, int $maxtokens): array {
        $body = [
            'model' => $model,
            'max_tokens' => $maxtokens,
            'temperature' => 0.3, // Lower temperature for more consistent grading.
            'messages' => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $userprompt],
            ],
        ];

        // Request JSON format for structured output.
        if (strpos($model, 'gpt-4') !== false || strpos($model, 'gpt-3.5-turbo') !== false) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        return $body;
    }

    /**
     * Parse API response.
     *
     * @param array $response Raw response
     * @return array Normalized response
     */
    protected function parse_response(array $response): array {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $usage = $response['usage'] ?? [];

        return [
            'content' => $content,
            'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
        ];
    }
}
