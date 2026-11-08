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
 * Pure check: only transient provider errors are retried (a 404 on an unknown model was retried 3 times).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/classes/ai_provider/provider_interface.php');
require(GP_ROOT . '/classes/ai_provider/base_provider.php');
require(GP_ROOT . '/classes/ai_provider/provider_http_exception.php');

use mod_gestionprojet\ai_provider\provider_http_exception;

/**
 * Number of calls made when every call fails with the given HTTP status.
 *
 * @param int $status 0 for a connection failure.
 * @return int
 */
function attempts_for(int $status): int {
    $p = new class('k') extends \mod_gestionprojet\ai_provider\base_provider {
        protected array $retrydelays = [0, 0, 0];
        public function get_name(): string { return 'fake'; }
        protected function get_endpoint(): string { return ''; }
        protected function build_headers(): array { return []; }
        protected function build_body(string $s, string $u, string $m, int $t): array { return []; }
        protected function parse_response(array $r): array { return []; }
        public function run(callable $c) { return $this->call_with_retry($c); }
    };
    $n = 0;
    try {
        $p->run(function () use (&$n, $status) {
            $n++;
            throw new provider_http_exception('HTTP ' . $status, $status);
        });
    } catch (\Exception $e) {
        // Expected.
    }
    return $n;
}

test('404 (unknown model) is not retried', fn() => attempts_for(404) === 1);
test('400 (bad request) is not retried', fn() => attempts_for(400) === 1);
test('401 (bad key) is not retried', fn() => attempts_for(401) === 1);
test('429 (rate limit) is retried', fn() => attempts_for(429) === 3);
test('503 (provider down) is retried', fn() => attempts_for(503) === 3);
test('a connection failure is retried', fn() => attempts_for(0) === 3);
