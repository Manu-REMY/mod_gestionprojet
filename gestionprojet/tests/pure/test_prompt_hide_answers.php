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
 * Pure check: with "do not reveal the correction model", the AI is told so (preco 3).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/classes/ai_prompt_builder.php');

$builder = new \mod_gestionprojet\ai_prompt_builder();
$model = (object) ['ai_instructions' => 'Ignorer la flexibilité.'];

test('hidden answers: the system prompt forbids quoting the correction model', function () use ($builder, $model) {
    $p = $builder->build_system_prompt(4, $model, null, true);
    return strpos($p, 'CONFIDENTIALITÉ DU CORRIGÉ') !== false && strpos($p, 'keywords_missing') !== false;
});
test('option off: no confidentiality section', fn() =>
    strpos($builder->build_system_prompt(4, $model, null, false), 'CONFIDENTIALITÉ DU CORRIGÉ') === false);
test('build_prompt passes the option through', fn() =>
    strpos($builder->build_prompt(4, (object) [], $model, null, null, false, true)['system'],
        'CONFIDENTIALITÉ DU CORRIGÉ') !== false);
test('the evaluator passes the instance option', fn() =>
    strpos(file_get_contents(GP_ROOT . '/classes/ai_evaluator.php'), "\$gestionprojet->ai_hide_answers") !== false);
