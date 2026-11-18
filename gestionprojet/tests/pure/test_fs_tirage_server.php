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
 * Pure checks of the wiring of the FS drawn per copy (request 42, v2.16.0): every reader of the step 4 consigne
 * uses the consigne of the copy.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$read = fn(string $path) => (string)file_get_contents(GP_ROOT . '/' . $path);
$lib = $read('lib.php');
$body = function (string $function) use ($lib): string {
    $start = strpos($lib, 'function ' . $function . '(');
    $end = strpos($lib, "\nfunction ", $start + 1);
    return $start === false ? '' : substr($lib, $start, $end === false ? null : $end - $start);
};

test('schema: instance fields and the draw of a copy', function () use ($read) {
    $xml = $read('db/install.xml');
    return strpos($xml, 'NAME="step4_fs_mode"') && strpos($xml, 'NAME="step4_fs_tirage"')
        && strpos($xml, 'NAME="step4_fs_sujets"') && strpos($xml, 'NAME="fs_tirees"');
});
test('upgrade: 2026111700 adds the four fields', function () use ($read) {
    $up = $read('db/upgrade.php');
    return strpos($up, '2026111700') && strpos($up, "'step4_fs_mode'") && strpos($up, "'step4_fs_sujets'")
        && strpos($up, "'fs_tirees'");
});
test('version 2.16.0 or later', fn() => preg_match('/plugin->version = (\d+);/', $read('version.php'), $m) && (int)$m[1] >= 2026111700);
test('backup: settings and the draw of each copy', function () use ($read) {
    $b = $read('backup/moodle2/backup_gestionprojet_stepslib.php');
    return strpos($b, "'step4_fs_mode', 'step4_fs_tirage', 'step4_fs_sujets'") && strpos($b, "'zones_validees', 'fs_tirees'");
});
test('enforce uses the consigne of the copy and never drops student text', fn() =>
    strpos($body('gestionprojet_cdcf_enforce_consigne'), 'gestionprojet_cdcf_consigne($gestionprojet, $submission)')
    && strpos($lib, 'given_shape') === false);
test('review 1: the draw runs under a lock of the copy and re-reads it', function () use ($body) {
    $draw = $body('gestionprojet_cdcf_draw');
    $locked = $body('gestionprojet_cdcf_draw_locked');
    return strpos($draw, "get_lock_factory('mod_gestionprojet_tirage')") && strpos($draw, '$lock->release()')
        && strpos($locked, 'get_record(\'gestionprojet_cdcf\', [\'id\' => $copy->id]') && strpos($locked, 'if ($current && !$replace)');
});
test('review 1: a save from a page showing another draw is refused (both endpoints)', function () use ($read, $body) {
    $check = 'gestionprojet_cdcf_check_draw($gestionprojet, (string)$value, $record);';
    return strpos($read('ajax/autosave.php'), $check) && strpos($read('classes/external/autosave.php'), $check)
        && strpos($body('gestionprojet_cdcf_check_draw'), "'fs_tirage_changed'")
        && strpos($read('pages/step4.php'), "\$cdcfdata['fsTirees'] = \$drawnids;");
});
test('review 3: back to "every FS" releases the draws of the draft copies', fn() =>
    strpos($body('gestionprojet_update_instance'), 'gestionprojet_cdcf_release_draws($beforedraw, $gestionprojet)')
    && strpos($body('gestionprojet_cdcf_release_draws'), 'fs_tirage::release(')
    && strpos($body('gestionprojet_cdcf_release_draws'), "'fs_tirees' => null"));
test('review 4: only drawn FS still in the consigne count', fn() =>
    strpos($body('gestionprojet_cdcf_drawn'), 'fs_tirage::valid(') !== false);
test('review 5: no draw on a copy with validated zones', fn() =>
    strpos($body('gestionprojet_cdcf_draw_locked'), "'fs_tirage_frozen'") !== false);
test('follow: per copy, before and after, consigne and model', fn() =>
    strpos($body('gestionprojet_cdcf_follow_consigne'), 'gestionprojet_cdcf_consigne($gestionprojet, $copy, $oldjson, $oldmodeljson)')
    && strpos($body('gestionprojet_cdcf_follow_consigne'), 'gestionprojet_cdcf_consigne($gestionprojet, $copy, $newjson, $modeljson)'));
test('reseed: the starting copy of each copy', fn() =>
    strpos($body('gestionprojet_cdcf_reseed_untouched'), 'gestionprojet_cdcf_consigne($gestionprojet, $copy, $provided)'));
test('first visit: seed of the copy consigne, then the draw', function () use ($body) {
    $b = $body('gestionprojet_get_or_create_submission');
    return strpos($b, 'gestionprojet_cdcf_consigne($gestionprojet, $record, $provided->interacteurs_data)')
        && strpos($b, 'gestionprojet_cdcf_draw($gestionprojet, $record)');
});
test('reset: seed of the copy consigne', fn() =>
    strpos($read('classes/reset_helper.php'), 'gestionprojet_cdcf_consigne($gestionprojet, $record,') !== false);
test('AI: the consigne of the copy marks the non-drawn FS provided', fn() =>
    strpos($read('classes/ai_evaluator.php'), 'gestionprojet_cdcf_consigne($gestionprojet, $submission,') !== false);
test('student page: flags from the consigne of the copy, notice of the drawn FS', function () use ($read) {
    $p = $read('pages/step4.php');
    return strpos($p, 'with_flags($cdcfdata, $copyconsigne)') && strpos($p, "'fs_tirage_notice'")
        && strpos($p, "'fsTireeBand'") && strpos($p, "'fsDonneeBand'");
});
test('model save: the copies follow the correction model (both endpoints)', function () use ($read) {
    $needle = '), $oldmodel);';
    return strpos($read('ajax/autosave.php'), $needle) && strpos($read('classes/external/autosave.php'), $needle);
});
test('teacher endpoint: login, sesskey, capability', function () use ($read) {
    $e = $read('ajax/fs_tirage.php');
    return strpos($e, 'require_login(') && strpos($e, 'require_sesskey()')
        && strpos($e, "require_capability('mod/gestionprojet:configureteacherpages'");
});
test('consigne page: the draw table and its module', function () use ($read) {
    $p = $read('pages/step4_provided.php');
    return strpos($p, 'gestionprojet_fs_tirage_rows(') && strpos($p, "'mod_gestionprojet/fs_tirage_admin'");
});
test('JS build: bands of the drawn and given FS', fn() => strpos($read('amd/build/cdcf.min.js'), 'fsTireeBand') !== false
    && strpos($read('amd/build/cdcf.min.js'), 'gp-cdcf-fs-donnee') !== false && is_file(GP_ROOT . '/amd/build/fs_tirage_admin.min.js'));
test('CSS: thick frame of a drawn FS (D-089)', fn() => strpos($read('styles.css'), '.gp-cdcf-fs-card.gp-cdcf-fs-tiree') !== false);
test('consigne_lock: tiree and donnee are flags, never stored', fn() =>
    in_array('tiree', \mod_gestionprojet\consigne_lock::FLAG_KEYS, true)
    && in_array('donnee', \mod_gestionprojet\consigne_lock::FLAG_KEYS, true));
test('request 43: public state of a copy, read only', function () use ($body) {
    $b = $body('gestionprojet_step4_etat');
    return $b !== '' && strpos($b, 'insert_record') === false && strpos($b, 'update_record') === false
        && strpos($b, 'gestionprojet_cdcf_draw(') === false && strpos($b, "'niveau'") !== false;
});
test('review 3: hiding the consigne from the home page releases the draws too', fn() =>
    strpos($read('ajax/toggle_step.php'), 'gestionprojet_cdcf_release_draws($gestionprojet, $after);') !== false);
test('a refused save tells the student why', fn() => strpos($read('amd/src/autosave.js'), 'Toast.error(response.message') !== false
    && strpos($read('amd/build/autosave.min.js'), 'lastErrorShown') !== false);
