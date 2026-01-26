<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AI Provider Interface for Gestion de Projet.
 *
 * Defines the contract that all AI providers must implement.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\ai_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface for AI providers (OpenAI, Anthropic, Mistral).
 */
interface provider_interface {

    /**
     * Get the provider name.
     *
     * @return string Provider identifier (e.g., 'openai', 'anthropic', 'mistral')
     */
    public function get_name(): string;

    /**
     * Get available models for this provider.
     *
     * @return array Array of model names
     */
    public function get_models(): array;

    /**
     * Get the default model for evaluation tasks.
     *
     * @return string Model name
     */
    public function get_default_model(): string;

    /**
     * Send an evaluation request to the AI provider.
     *
     * @param string $systemprompt The system prompt setting context
     * @param string $userprompt The user prompt with evaluation data
     * @param string $model The model to use
     * @param int $maxtokens Maximum response tokens
     * @return array Response array with keys: content, prompt_tokens, completion_tokens
     * @throws \Exception On API error
     */
    public function evaluate(string $systemprompt, string $userprompt, string $model, int $maxtokens = 2000): array;

    /**
     * Estimate token count for a given text.
     *
     * @param string $text Text to estimate
     * @return int Estimated token count
     */
    public function estimate_tokens(string $text): int;

    /**
     * Test the API connection.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function test_connection(): array;
}
