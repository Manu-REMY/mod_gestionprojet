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
 * Rules of the feedback window of the student step pages (v2.17.0, D-086 of mod_redaction adopted here).
 *
 * No Moodle call: pure checks load this class.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Rules of the feedback window.
 */
class retour_view {

    /** @var string[] Levels from which the window offers the final submission (Emmanuel, 18/11/2026). */
    const SUBMIT_FROM = ['S', 'TS'];

    /**
     * Split the criteria of a feedback into what works (full score) and what is missing.
     *
     * A criterion without a numeric max goes to "what is missing" when it has a comment, otherwise it is left out:
     * nothing is said that was not judged.
     *
     * @param array $criteria Criteria of the AI response (name, score, max, comment), already filtered for the student.
     * @return array ['va' => list of ['name', 'comment', 'score', 'max' (floats, null when not scored)], 'manque' => same]
     */
    public static function split_criteria(array $criteria): array {
        $out = ['va' => [], 'manque' => []];
        foreach ($criteria as $criterion) {
            if (!is_array($criterion)) {
                continue;
            }
            $item = [
                'name' => is_scalar($criterion['name'] ?? null) ? (string)$criterion['name'] : '',
                'comment' => is_scalar($criterion['comment'] ?? null) ? (string)$criterion['comment'] : '',
            ];
            $score = $criterion['score'] ?? null;
            $max = $criterion['max'] ?? null;
            $scored = is_numeric($score) && is_numeric($max) && (float)$max > 0;
            $item['score'] = $scored ? (float)$score : null;
            $item['max'] = $scored ? (float)$max : null;
            if ($scored) {
                $out[(float)$score >= (float)$max - 1e-9 ? 'va' : 'manque'][] = $item;
            } else if ($item['comment'] !== '') {
                $out['manque'][] = $item;
            }
        }
        return $out;
    }

    /**
     * Whether the window puts the final submission forward: SAT or TS reached, and the copy can be submitted.
     *
     * @param array|null $level See palier::level_reached(); a level recorded at submission carries 'validated'.
     * @param bool $cansubmit The student can submit this copy now.
     * @return bool
     */
    public static function propose_submit(?array $level, bool $cansubmit): bool {
        return $cansubmit && $level !== null && empty($level['validated'])
            && in_array($level['reached'] ?? '', self::SUBMIT_FROM, true);
    }
}
