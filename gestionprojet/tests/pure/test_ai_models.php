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
 * Pure check of the AI model choice: one default per provider, overridable by the admin.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/classes/ai_config.php');

use mod_gestionprojet\ai_config;

test('Albert uses deepseek-v4-flash by default (albert-large answers 404)',
    fn() => ai_config::resolve_model('albert', null) === 'deepseek-v4-flash');
test('an empty admin setting keeps the default',
    fn() => ai_config::resolve_model('albert', '   ') === 'deepseek-v4-flash');
test('the admin setting wins, trimmed',
    fn() => ai_config::resolve_model('albert', ' mistral-large ') === 'mistral-large');
test('every provider has a default model', function () {
    foreach (ai_config::PROVIDERS as $p) {
        if (ai_config::resolve_model($p, null) === '') {
            return false;
        }
    }
    return true;
});
test('no retired Anthropic 3.x model by default',
    fn() => strpos(ai_config::resolve_model('anthropic', null), 'claude-3') !== 0);
test('an unknown provider is refused',
    fn() => throws(fn() => ai_config::resolve_model('nope', null), \coding_exception::class)
        || throws(fn() => ai_config::resolve_model('nope', null), \InvalidArgumentException::class));
test('no separate test model table any more: the test uses the real model',
    fn() => !defined(ai_config::class . '::TEST_MODELS'));

test('no model name hard-coded outside ai_config (one place to change it)', function () {
    $offenders = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(GP_ROOT . '/classes'));
    foreach ($it as $file) {
        if (substr($file, -4) !== '.php' || basename($file) === 'ai_config.php') {
            continue;
        }
        // A prefix tested with strpos() detects a capability, it does not choose a model.
        if (preg_match('/(?<!strpos\(\$model, )[\'"](gpt-[0-9][^\'"]*|claude-[0-9a-z.-]+|albert-[a-z]+|neuralbeagle|mistral-[a-z]+-latest|deepseek[^\'"]*)[\'"]/',
                file_get_contents($file), $m)) {
            $offenders[] = basename($file) . ' (' . $m[1] . ')';
        }
    }
    if ($offenders) {
        echo '      ' . implode(', ', $offenders) . "\n";
    }
    return !$offenders;
});

test('each provider has a brand name for the settings page',
    fn() => array_keys(ai_config::PROVIDER_NAMES) == ai_config::PROVIDERS);
