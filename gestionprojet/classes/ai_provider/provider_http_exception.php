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
 * Error answered by an AI provider, carrying its HTTP status (0 when the provider could not be reached).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\ai_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Provider HTTP error.
 */
class provider_http_exception extends \Exception {

    /** @var bool Whether the call ran out of time (curl error 28) rather than finding no server */
    private bool $timedout;

    /**
     * Constructor.
     *
     * @param string $message
     * @param int $code HTTP status, 0 when there was no answer.
     * @param bool $timedout Whether the call ran out of time.
     */
    public function __construct(string $message, int $code = 0, bool $timedout = false) {
        parent::__construct($message, $code);
        $this->timedout = $timedout;
    }

    /**
     * Whether trying again later may succeed: no answer, rate limit or provider error.
     *
     * A call that ran out of time is not retried (v2.13.6): the model is slow, not unreachable, and each attempt
     * would hold a background task runner of the whole site for the full time limit.
     *
     * @return bool
     */
    public function is_transient(): bool {
        if ($this->timedout) {
            return false;
        }
        $status = $this->getCode();
        return $status === 0 || $status === 408 || $status === 429 || $status >= 500;
    }
}
