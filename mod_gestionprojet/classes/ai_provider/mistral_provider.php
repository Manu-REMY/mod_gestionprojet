<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Mistral Provider for Gestion de Projet.
 *
 * Implements the AI provider interface for Mistral AI's API.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\ai_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Mistral AI provider implementation.
 */
class mistral_provider extends base_provider {

    /** @var string API endpoint */
    const ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';

    /** @var array Available models */
    const MODELS = [
        'mistral-medium-latest',
        'mistral-small-latest',
        'mistral-large-latest',
        'open-mistral-7b',
        'open-mixtral-8x7b',
    ];

    /** @var string Default model for evaluation */
    const DEFAULT_MODEL = 'mistral-medium-latest';

    /** @var string Model used for testing */
    const TEST_MODEL = 'mistral-small-latest';

    /**
     * Get provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'mistral';
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
            'temperature' => 0.3, // Lower temperature for consistent grading.
            'messages' => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $userprompt],
            ],
        ];

        // Mistral supports JSON mode for some models.
        if (strpos($model, 'mistral') !== false) {
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
