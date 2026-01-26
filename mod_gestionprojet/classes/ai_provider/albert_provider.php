<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Albert Provider for Gestion de Projet.
 *
 * Implements the AI provider interface for Albert (Etalab) API.
 * Albert is the French government's AI service with an OpenAI-compatible API.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\ai_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Albert (Etalab) AI provider implementation.
 *
 * Uses the OpenAI-compatible API provided by the French government.
 * API endpoint: https://albert.api.etalab.gouv.fr/v1/chat/completions
 */
class albert_provider extends base_provider {

    /** @var string API endpoint */
    const ENDPOINT = 'https://albert.api.etalab.gouv.fr/v1/chat/completions';

    /** @var array Available models */
    const MODELS = [
        'albert-large',
        'albert-base',
        'guillaumetell-7b',
    ];

    /** @var string Default model for evaluation */
    const DEFAULT_MODEL = 'albert-large';

    /** @var string Model used for testing */
    const TEST_MODEL = 'albert-base';

    /**
     * Get provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'albert';
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
     * Albert uses Bearer token authentication like OpenAI.
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
     * Albert uses OpenAI-compatible format.
     *
     * @param string $systemprompt System prompt
     * @param string $userprompt User prompt
     * @param string $model Model to use
     * @param int $maxtokens Maximum tokens
     * @return array
     */
    protected function build_body(string $systemprompt, string $userprompt, string $model, int $maxtokens): array {
        return [
            'model' => $model,
            'max_tokens' => $maxtokens,
            'temperature' => 0.3, // Lower temperature for consistent grading.
            'messages' => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $userprompt],
            ],
        ];
    }

    /**
     * Parse API response.
     *
     * Albert uses OpenAI-compatible response format.
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
