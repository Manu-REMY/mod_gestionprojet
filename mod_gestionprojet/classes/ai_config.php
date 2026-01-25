<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AI Configuration class for Gestion de Projet.
 *
 * Handles secure storage and retrieval of AI API keys and settings.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for managing AI configuration and API key security.
 */
class ai_config {

    /** @var array Supported AI providers */
    const PROVIDERS = ['openai', 'anthropic', 'mistral'];

    /** @var array Provider API endpoints */
    const ENDPOINTS = [
        'openai' => 'https://api.openai.com/v1/chat/completions',
        'anthropic' => 'https://api.anthropic.com/v1/messages',
        'mistral' => 'https://api.mistral.ai/v1/chat/completions',
    ];

    /** @var array Provider test models (lightweight for testing) */
    const TEST_MODELS = [
        'openai' => 'gpt-3.5-turbo',
        'anthropic' => 'claude-3-haiku-20240307',
        'mistral' => 'mistral-small-latest',
    ];

    /**
     * Encrypt an API key for storage.
     *
     * @param string $apikey The plain API key
     * @return string The encrypted API key
     */
    public static function encrypt_api_key(string $apikey): string {
        if (empty($apikey)) {
            return '';
        }

        // Use Moodle's encryption if available (Moodle 4.0+).
        if (class_exists('\core\encryption')) {
            return \core\encryption::encrypt($apikey);
        }

        // Fallback: base64 encoding (not secure, but better than plain text).
        return base64_encode($apikey);
    }

    /**
     * Decrypt an API key from storage.
     *
     * @param string $encrypted The encrypted API key
     * @return string The decrypted API key
     */
    public static function decrypt_api_key(string $encrypted): string {
        if (empty($encrypted)) {
            return '';
        }

        // Use Moodle's encryption if available (Moodle 4.0+).
        if (class_exists('\core\encryption')) {
            try {
                return \core\encryption::decrypt($encrypted);
            } catch (\Exception $e) {
                // If decryption fails, try base64 (migration case).
                $decoded = base64_decode($encrypted, true);
                if ($decoded !== false) {
                    return $decoded;
                }
                return '';
            }
        }

        // Fallback: base64 decoding.
        $decoded = base64_decode($encrypted, true);
        return $decoded !== false ? $decoded : '';
    }

    /**
     * Get AI configuration for a gestionprojet instance.
     *
     * @param int $gestionprojetid The instance ID
     * @return object|null The configuration object or null
     */
    public static function get_config(int $gestionprojetid): ?object {
        global $DB;

        $record = $DB->get_record('gestionprojet', ['id' => $gestionprojetid], 'id, ai_enabled, ai_provider, ai_api_key');

        if (!$record || empty($record->ai_enabled)) {
            return null;
        }

        return (object) [
            'enabled' => (bool) $record->ai_enabled,
            'provider' => $record->ai_provider,
            'api_key' => self::decrypt_api_key($record->ai_api_key ?? ''),
        ];
    }

    /**
     * Save AI configuration for a gestionprojet instance.
     *
     * @param int $gestionprojetid The instance ID
     * @param bool $enabled Whether AI is enabled
     * @param string $provider The AI provider
     * @param string $apikey The API key (plain text, will be encrypted)
     * @return bool Success status
     */
    public static function save_config(int $gestionprojetid, bool $enabled, string $provider, string $apikey): bool {
        global $DB;

        $data = new \stdClass();
        $data->id = $gestionprojetid;
        $data->ai_enabled = $enabled ? 1 : 0;
        $data->ai_provider = $provider;
        $data->ai_api_key = self::encrypt_api_key($apikey);
        $data->timemodified = time();

        return $DB->update_record('gestionprojet', $data);
    }

    /**
     * Test an API connection.
     *
     * @param string $provider The AI provider
     * @param string $apikey The API key (plain text)
     * @return array ['success' => bool, 'message' => string]
     */
    public static function test_connection(string $provider, string $apikey): array {
        if (!in_array($provider, self::PROVIDERS)) {
            return [
                'success' => false,
                'message' => get_string('ai_provider_invalid', 'gestionprojet'),
            ];
        }

        if (empty($apikey)) {
            return [
                'success' => false,
                'message' => get_string('ai_api_key_required', 'gestionprojet'),
            ];
        }

        $endpoint = self::ENDPOINTS[$provider];
        $model = self::TEST_MODELS[$provider];

        try {
            $response = self::make_api_request($provider, $apikey, $endpoint, $model, 'Hello');

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'message' => $response['error']['message'] ?? get_string('ai_test_failed', 'gestionprojet'),
                ];
            }

            return [
                'success' => true,
                'message' => get_string('ai_test_success', 'gestionprojet'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Make an API request to the AI provider.
     *
     * @param string $provider The AI provider
     * @param string $apikey The API key
     * @param string $endpoint The API endpoint
     * @param string $model The model to use
     * @param string $message The message to send
     * @return array The parsed response
     */
    private static function make_api_request(
        string $provider,
        string $apikey,
        string $endpoint,
        string $model,
        string $message
    ): array {
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);

        $headers = self::build_headers($provider, $apikey);
        $body = self::build_request_body($provider, $model, $message);

        $response = $curl->post($endpoint, json_encode($body), [
            'CURLOPT_HTTPHEADER' => $headers,
        ]);

        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if ($httpcode === 0) {
            throw new \Exception(get_string('ai_connection_error', 'gestionprojet'));
        }

        $decoded = json_decode($response, true);

        if ($httpcode >= 400) {
            return [
                'error' => [
                    'message' => $decoded['error']['message'] ?? "HTTP Error: $httpcode",
                ],
            ];
        }

        return $decoded;
    }

    /**
     * Build HTTP headers for the API request.
     *
     * @param string $provider The AI provider
     * @param string $apikey The API key
     * @return array The headers
     */
    private static function build_headers(string $provider, string $apikey): array {
        $headers = [
            'Content-Type: application/json',
        ];

        switch ($provider) {
            case 'openai':
            case 'mistral':
                $headers[] = "Authorization: Bearer $apikey";
                break;
            case 'anthropic':
                $headers[] = "x-api-key: $apikey";
                $headers[] = 'anthropic-version: 2023-06-01';
                break;
        }

        return $headers;
    }

    /**
     * Build the request body for the API request.
     *
     * @param string $provider The AI provider
     * @param string $model The model to use
     * @param string $message The message to send
     * @return array The request body
     */
    private static function build_request_body(string $provider, string $model, string $message): array {
        switch ($provider) {
            case 'anthropic':
                return [
                    'model' => $model,
                    'max_tokens' => 10,
                    'messages' => [
                        ['role' => 'user', 'content' => $message],
                    ],
                ];
            default: // OpenAI and Mistral use similar format.
                return [
                    'model' => $model,
                    'max_tokens' => 10,
                    'messages' => [
                        ['role' => 'user', 'content' => $message],
                    ],
                ];
        }
    }

    /**
     * Log API key access for audit purposes.
     *
     * @param int $gestionprojetid The instance ID
     * @param int $userid The user accessing the key
     * @param string $action The action performed
     */
    public static function log_access(int $gestionprojetid, int $userid, string $action): void {
        global $DB;

        $log = new \stdClass();
        $log->gestionprojetid = $gestionprojetid;
        $log->tablename = 'gestionprojet';
        $log->recordid = $gestionprojetid;
        $log->fieldname = 'ai_api_key_access';
        $log->oldvalue = '';
        $log->newvalue = $action;
        $log->userid = $userid;
        $log->timecreated = time();

        $DB->insert_record('gestionprojet_history', $log);
    }
}
