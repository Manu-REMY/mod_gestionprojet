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
 * Spelling at step 4: what is judged, how the AI answer is checked, points, grade and level cap (request 32, v2.15.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Pure rules, without Moodle calls so that they can be checked alone.
 *
 * Decisions of Emmanuel (11/11/2026): spelling only at step 4; 0 mistake gives P points, 1 or 2 give P/2, 3 or more give 0;
 * "TS must be perfect": a level TS is capped at S as soon as one mistake is kept; the feedback gives the exact form (D-025).
 */
class orthographe {

    /**
     * Texts written by the student in a step 4 copy: provided fields and pre-filled fields left as is are not judged.
     *
     * @param array $flagged Copy, with the flags of consigne_lock::with_flags() when a consigne is shown.
     * @return string[] Non-empty texts.
     */
    public static function judged_texts(array $flagged): array {
        $out = [];
        $take = function (array $element, string $field, string $lock, string $prefill, string $hint) use (&$out): void {
            $text = trim((string)($element[$field] ?? ''));
            if ($text === '' || !empty($element[$lock])) {
                return;
            }
            if (!empty($element[$prefill]) && self::bare($text) === self::bare((string)($element[$hint] ?? ''))) {
                return;
            }
            $out[] = $text;
        };
        foreach ($flagged['interactors'] ?? [] as $i) {
            $take((array)$i, 'name', 'locked', 'prefill', 'hint');
        }
        foreach ($flagged['fonctionsService'] ?? [] as $fs) {
            $take((array)$fs, 'description', 'locked', 'prefill', 'hint');
            foreach ($fs['criteres'] ?? [] as $c) {
                $take((array)$c, 'description', 'locked', 'prefill', 'hint');
                $take((array)$c, 'niveau', 'niveauLocked', 'niveauPrefill', 'niveauHint');
                $take((array)$c, 'flexibilite', 'locked', 'none', 'none');
            }
        }
        foreach ($flagged['contraintes'] ?? [] as $c) {
            $take((array)$c, 'description', 'locked', 'prefill', 'hint');
            $take((array)$c, 'justification', 'locked', 'none', 'none');
        }
        return $out;
    }

    /**
     * Mistakes listed by the AI answer.
     *
     * @param string $raw Text of the answer (JSON, maybe with text around it).
     * @return array List of ['cite' => string, 'correction' => string, 'type' => string].
     * @throws \UnexpectedValueException When the answer holds no readable list "fautes" (cut, missing): never read halfway.
     */
    public static function parse(string $raw): array {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        $data = ($start !== false && $end !== false && $end > $start)
            ? json_decode(substr($raw, $start, $end - $start + 1), true) : null;
        if (!is_array($data) || !isset($data['fautes']) || !is_array($data['fautes'])) {
            throw new \UnexpectedValueException('Unreadable spelling answer');
        }
        $out = [];
        foreach ($data['fautes'] as $faute) {
            if (!is_array($faute)) {
                continue;
            }
            $out[] = ['cite' => trim((string)($faute['cite'] ?? '')), 'correction' => trim((string)($faute['correction'] ?? '')),
                'type' => trim((string)($faute['type'] ?? ''))];
        }
        return $out;
    }

    /**
     * Mistakes kept after the server checks: quoted from the copy, corrected into another spelling of the same word.
     *
     * @param array $fautes From parse().
     * @param string $text Text judged (judged_texts joined by line breaks).
     * @param string[] $accepted Words accepted by the teacher for the activity (normalized or not).
     * @return array ['fautes' => kept mistakes, 'ecartees' => number dismissed]
     */
    public static function verify(array $fautes, string $text, array $accepted): array {
        $haystack = ' ' . self::collapse(mb_strtolower($text)) . ' ';
        $accepted = array_map([self::class, 'normalize'], $accepted);
        $kept = [];
        $dismissed = 0;
        foreach ($fautes as $faute) {
            $cite = trim((string)($faute['cite'] ?? ''));
            $correction = trim((string)($faute['correction'] ?? ''));
            $needle = self::collapse(mb_strtolower($cite));
            $key = self::normalize($cite);
            $found = $needle !== '' && (preg_match('/\s/u', $needle)
                ? mb_strpos($haystack, $needle) !== false
                : preg_match('/(?<![\p{L}\p{N}])' . preg_quote($needle, '/') . '(?![\p{L}\p{N}])/u', $haystack) === 1);
            $close = $correction !== '' && mb_strtolower($correction) !== mb_strtolower($cite)
                && self::close($cite, $correction);
            if (!$found || !$close || in_array($key, $accepted, true)) {
                $dismissed++;
                continue;
            }
            if (isset($kept[$key])) {
                continue;
            }
            $kept[$key] = ['cite' => $cite, 'correction' => $correction, 'type' => (string)($faute['type'] ?? '')];
        }
        return ['fautes' => array_values($kept), 'ecartees' => $dismissed];
    }

    /**
     * Spelling points: P without mistake, P/2 for 1 or 2 mistakes, 0 for 3 or more.
     *
     * @param int $nbfautes Mistakes kept.
     * @param float $p Points of the language (langue_points).
     * @return float
     */
    public static function points(int $nbfautes, float $p): float {
        if ($nbfautes <= 0) {
            return $p;
        }
        return $nbfautes <= 2 ? $p / 2 : 0.0;
    }

    /**
     * Grade of the copy: content brought to (20 - P), plus the spelling points, rounded to 0.01.
     *
     * @param float $content Grade of the content on 20.
     * @param float $p Points of the language.
     * @param float $points Spelling points earned.
     * @return float
     */
    public static function total(float $content, float $p, float $points): float {
        return round($content * (20 - $p) / 20 + $points, 2);
    }

    /**
     * Level once spelling is counted: "TS must be perfect", any mistake caps TS at S; lower levels stay.
     *
     * @param string $reached Level reached by the content ('' when none).
     * @param int $nbfautes Mistakes kept.
     * @return string
     */
    public static function cap_level(string $reached, int $nbfautes): string {
        return ($reached === 'TS' && $nbfautes > 0) ? 'S' : $reached;
    }

    /**
     * Form used to compare words: lower case, no accents, single spaces.
     *
     * @param string $text
     * @return string
     */
    public static function normalize(string $text): string {
        $text = mb_strtolower($text);
        if (class_exists('Normalizer')) {
            $text = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($text, \Normalizer::FORM_D)) ?? $text;
        }
        return self::collapse($text);
    }

    /**
     * Whether a correction is another spelling of the quoted word (Levenshtein on normalized forms).
     *
     * @param string $cite
     * @param string $correction
     * @return bool
     */
    private static function close(string $cite, string $correction): bool {
        $a = self::normalize($cite);
        $b = self::normalize($correction);
        $limit = max(2, (int)floor(0.4 * mb_strlen($a)));
        return levenshtein($a, $b) <= $limit;
    }

    /**
     * Single spaces, trimmed.
     *
     * @param string $text
     * @return string
     */
    private static function collapse(string $text): string {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Letters and digits only, lower case: a field where only punctuation, case or "…" changed is left as is (v2.13.3).
     *
     * @param string $text
     * @return string
     */
    private static function bare(string $text): string {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text)) ?? '';
    }
}
