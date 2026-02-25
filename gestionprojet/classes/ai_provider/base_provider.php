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
 * Base AI Provider class for Gestion de Projet.
 *
 * Provides common functionality for all AI providers.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\ai_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base class for AI providers.
 */
abstract class base_provider implements provider_interface {

    /** @var string API key for authentication */
    protected string $apikey;

    /** @var int Request timeout in seconds */
    protected int $timeout = 60;

    /** @var int Connection timeout in seconds */
    protected int $connecttimeout = 15;

    /** @var int Maximum retries on failure */
    protected int $maxretries = 3;

    /** @var array Retry delays in seconds */
    protected array $retrydelays = [5, 30, 120];

    /**
     * Constructor.
     *
     * @param string $apikey The API key for authentication
     */
    public function __construct(string $apikey) {
        $this->apikey = $apikey;
    }

    /**
     * Get the API endpoint URL.
     *
     * @return string The API endpoint
     */
    abstract protected function get_endpoint(): string;

    /**
     * Build HTTP headers for the request.
     *
     * @return array Headers array
     */
    abstract protected function build_headers(): array;

    /**
     * Build the request body.
     *
     * @param string $systemprompt System prompt
     * @param string $userprompt User prompt
     * @param string $model Model to use
     * @param int $maxtokens Maximum tokens
     * @return array Request body
     */
    abstract protected function build_body(string $systemprompt, string $userprompt, string $model, int $maxtokens): array;

    /**
     * Parse the response from the API.
     *
     * @param array $response Raw response
     * @return array Normalized response with content, prompt_tokens, completion_tokens
     */
    abstract protected function parse_response(array $response): array;

    /**
     * Estimate token count for text.
     * Uses a simple approximation: ~4 characters per token.
     *
     * @param string $text Text to estimate
     * @return int Estimated tokens
     */
    public function estimate_tokens(string $text): int {
        // Rough estimate: 1 token ≈ 4 characters for most languages.
        // Use mb_strlen if available, fall back to strlen otherwise.
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        return (int) ceil($length / 4);
    }

    /**
     * Send evaluation request to the AI provider.
     *
     * @param string $systemprompt System prompt
     * @param string $userprompt User prompt
     * @param string $model Model to use
     * @param int $maxtokens Maximum response tokens
     * @return array Response array
     * @throws \Exception On API error
     */
    public function evaluate(string $systemprompt, string $userprompt, string $model, int $maxtokens = 2000): array {
        return $this->call_with_retry(function() use ($systemprompt, $userprompt, $model, $maxtokens) {
            return $this->make_request($systemprompt, $userprompt, $model, $maxtokens);
        });
    }

    /**
     * Test the API connection with a simple request.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function test_connection(): array {
        try {
            $response = $this->make_request(
                'You are a helpful assistant.',
                'Hello',
                $this->get_test_model(),
                10
            );

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
     * Get the model used for testing connections.
     *
     * @return string Test model name
     */
    protected function get_test_model(): string {
        $models = $this->get_models();
        return $models[0] ?? $this->get_default_model();
    }

    /**
     * Make an API request.
     *
     * @param string $systemprompt System prompt
     * @param string $userprompt User prompt
     * @param string $model Model to use
     * @param int $maxtokens Maximum tokens
     * @return array Parsed response
     * @throws \Exception On error
     */
    protected function make_request(string $systemprompt, string $userprompt, string $model, int $maxtokens): array {
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => $this->connecttimeout,
        ]);

        $headers = $this->build_headers();
        $body = $this->build_body($systemprompt, $userprompt, $model, $maxtokens);

        $response = $curl->post($this->get_endpoint(), json_encode($body), [
            'CURLOPT_HTTPHEADER' => $headers,
        ]);

        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if ($httpcode === 0) {
            throw new \Exception(get_string('ai_connection_error', 'gestionprojet'));
        }

        $decoded = json_decode($response, true);

        if ($httpcode >= 400) {
            $errormsg = $this->extract_error_message($decoded, $httpcode);
            throw new \Exception($errormsg);
        }

        if ($decoded === null) {
            throw new \Exception(get_string('ai_invalid_response', 'gestionprojet'));
        }

        return $this->parse_response($decoded);
    }

    /**
     * Extract error message from API response.
     *
     * @param array|null $response Response data
     * @param int $httpcode HTTP status code
     * @return string Error message
     */
    protected function extract_error_message(?array $response, int $httpcode): string {
        if (isset($response['error']['message'])) {
            return $response['error']['message'];
        }
        if (isset($response['message'])) {
            return $response['message'];
        }
        return "HTTP Error: $httpcode";
    }

    /**
     * Call a function with retry logic.
     *
     * @param callable $callback Function to call
     * @return mixed Function result
     * @throws \Exception If all retries fail
     */
    protected function call_with_retry(callable $callback) {
        $lastexception = null;

        for ($attempt = 0; $attempt < $this->maxretries; $attempt++) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastexception = $e;

                // Don't retry on authentication errors.
                if ($this->is_auth_error($e)) {
                    throw $e;
                }

                // Don't retry on the last attempt.
                if ($attempt < $this->maxretries - 1) {
                    sleep($this->retrydelays[$attempt] ?? 5);
                }
            }
        }

        throw $lastexception;
    }

    /**
     * Check if an exception is an authentication error.
     *
     * @param \Exception $e The exception
     * @return bool True if auth error
     */
    protected function is_auth_error(\Exception $e): bool {
        $message = strtolower($e->getMessage());
        return strpos($message, 'unauthorized') !== false
            || strpos($message, 'invalid api key') !== false
            || strpos($message, 'authentication') !== false
            || strpos($message, '401') !== false
            || strpos($message, '403') !== false;
    }
}
