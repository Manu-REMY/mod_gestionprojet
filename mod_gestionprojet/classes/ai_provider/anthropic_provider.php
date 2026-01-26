<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Anthropic Provider for Gestion de Projet.
 *
 * Implements the AI provider interface for Anthropic's Claude API.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\ai_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Anthropic Claude provider implementation.
 */
class anthropic_provider extends base_provider {

    /** @var string API endpoint */
    const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /** @var string API version */
    const API_VERSION = '2023-06-01';

    /** @var array Available models */
    const MODELS = [
        'claude-3-5-sonnet-20241022',
        'claude-3-5-haiku-20241022',
        'claude-3-opus-20240229',
        'claude-3-sonnet-20240229',
        'claude-3-haiku-20240307',
    ];

    /** @var string Default model for evaluation */
    const DEFAULT_MODEL = 'claude-3-5-sonnet-20241022';

    /** @var string Model used for testing */
    const TEST_MODEL = 'claude-3-haiku-20240307';

    /**
     * Get provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'anthropic';
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
            'x-api-key: ' . $this->apikey,
            'anthropic-version: ' . self::API_VERSION,
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
        return [
            'model' => $model,
            'max_tokens' => $maxtokens,
            'temperature' => 0.3, // Lower temperature for consistent grading.
            'system' => $systemprompt,
            'messages' => [
                ['role' => 'user', 'content' => $userprompt],
            ],
        ];
    }

    /**
     * Parse API response.
     *
     * @param array $response Raw response
     * @return array Normalized response
     */
    protected function parse_response(array $response): array {
        // Anthropic returns content as an array of blocks.
        $content = '';
        if (isset($response['content']) && is_array($response['content'])) {
            foreach ($response['content'] as $block) {
                if (isset($block['type']) && $block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }
        }

        $usage = $response['usage'] ?? [];

        return [
            'content' => $content,
            'prompt_tokens' => $usage['input_tokens'] ?? 0,
            'completion_tokens' => $usage['output_tokens'] ?? 0,
        ];
    }

    /**
     * Extract error message from API response.
     *
     * @param array|null $response Response data
     * @param int $httpcode HTTP status code
     * @return string Error message
     */
    protected function extract_error_message(?array $response, int $httpcode): string {
        // Anthropic uses 'error.message' structure.
        if (isset($response['error']['message'])) {
            return $response['error']['message'];
        }
        if (isset($response['type']) && $response['type'] === 'error') {
            return $response['message'] ?? "HTTP Error: $httpcode";
        }
        return parent::extract_error_message($response, $httpcode);
    }
}
