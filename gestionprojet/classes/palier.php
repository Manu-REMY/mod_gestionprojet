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
 * Rules of the step 4 level validation F / S / TS (v2.11.2).
 *
 * A group asks for one level at a time; the AI grades the parts of that level on a frozen copy, the server decides
 * the verdict from the points, and the zones of a validated level are frozen as a whole. No Moodle call: pure checks
 * load this class.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Rules of the level validation.
 */
class palier {

    /** @var string[] Levels from the lowest to the highest (same codes as plan-classe). */
    const ORDER = ['F', 'S', 'TS'];

    /**
     * Levels present among the graded parts, from the lowest.
     *
     * @param array $parts Graded parts (grille::effective_parts).
     * @return string[]
     */
    public static function paliers_in_grid(array $parts): array {
        $present = array_column($parts, 'palier');
        return array_values(array_filter(self::ORDER, function (string $p) use ($present): bool {
            return in_array($p, $present, true);
        }));
    }

    /**
     * Graded parts of one level.
     *
     * @param array $parts Graded parts.
     * @param string $palier
     * @return array
     */
    public static function parts_for(array $parts, string $palier): array {
        if (!in_array($palier, self::ORDER, true)) {
            return [];
        }
        return array_values(array_filter($parts, function (array $part) use ($palier): bool {
            return ($part['palier'] ?? '') === $palier;
        }));
    }

    /**
     * Zones of the copy frozen when the level is validated: zones of its parts, without "ensemble".
     *
     * @param array $parts Graded parts.
     * @param string $palier
     * @return string[] In the order of grille::ZONES.
     */
    public static function zones_for(array $parts, string $palier): array {
        $zones = [];
        foreach (self::parts_for($parts, $palier) as $part) {
            $zones = array_merge($zones, $part['zones']);
        }
        return array_values(array_filter(grille::ZONES, function (string $z) use ($zones): bool {
            return $z !== 'ensemble' && in_array($z, $zones, true);
        }));
    }

    /**
     * Level that can be asked for now: the lowest level of the grid not yet validated, if every lower one is.
     *
     * @param string[] $ingrid Levels of the grid.
     * @param string[] $validated Levels validated.
     * @return string[] Zero or one level.
     */
    public static function requestable(array $ingrid, array $validated): array {
        foreach (self::ORDER as $p) {
            if (!in_array($p, $ingrid, true) || in_array($p, $validated, true)) {
                continue;
            }
            return [$p];
        }
        return [];
    }

    /**
     * Verdict of an attempt, from the points of the criteria returned by the AI.
     *
     * Scores are bounded to [0, max]. No criterion, or no point to earn, never validates.
     *
     * @param array $criteria Criteria of the AI response (name, score, max).
     * @param int $seuil Threshold in % of the points of the level.
     * @return array ['valide' => bool, 'percent' => float]
     */
    public static function verdict(array $criteria, int $seuil): array {
        $score = 0.0;
        $max = 0.0;
        foreach ($criteria as $criterion) {
            $m = max(0.0, (float)($criterion['max'] ?? 0));
            $score += min($m, max(0.0, (float)($criterion['score'] ?? 0)));
            $max += $m;
        }
        if ($max <= 0) {
            return ['valide' => false, 'percent' => 0.0];
        }
        $percent = round(100 * $score / $max, 1);
        return ['valide' => $percent >= $seuil, 'percent' => $percent];
    }

    /**
     * Freeze zones of a copy as a whole: no change, no deletion, no addition.
     *
     * interacteurs and contraintes: the whole list; fonctions: the list of FS (ids, statements, interactors), their
     * criteria following the other zones; criteres: the criteria of each frozen FS (ids, descriptions); niveaux and
     * flexibilite: the values of the matching criteria.
     *
     * @param array $student Student copy (normalized).
     * @param array $frozen Reference content (normalized), the copy judged when the level was validated.
     * @param string[] $zones Zones to freeze.
     * @return array Copy without flags.
     */
    public static function freeze(array $student, array $frozen, array $zones): array {
        $student = consigne_lock::strip_flags($student);
        $frozen = consigne_lock::strip_flags($frozen);
        if (!$zones) {
            return $student;
        }
        if (in_array('interacteurs', $zones, true)) {
            $student['interactors'] = $frozen['interactors'];
        }
        if (in_array('contraintes', $zones, true)) {
            $student['contraintes'] = $frozen['contraintes'];
        }
        $studentfs = [];
        foreach ($student['fonctionsService'] as $fs) {
            $studentfs[$fs['id']] = $studentfs[$fs['id']] ?? $fs;
        }
        if (in_array('fonctions', $zones, true)) {
            $list = [];
            foreach ($frozen['fonctionsService'] as $ffs) {
                $fs = $studentfs[$ffs['id']] ?? $ffs;
                $fs['description'] = $ffs['description'];
                $fs['interactor1Id'] = $ffs['interactor1Id'];
                $fs['interactor2Id'] = $ffs['interactor2Id'];
                $list[] = $fs;
            }
            $student['fonctionsService'] = $list;
        }
        $frozenfs = [];
        foreach ($frozen['fonctionsService'] as $ffs) {
            $frozenfs[$ffs['id']] = $ffs;
        }
        foreach ($student['fonctionsService'] as &$fs) {
            if (!isset($frozenfs[$fs['id']])) {
                continue;
            }
            $fcriteria = $frozenfs[$fs['id']]['criteres'];
            if (in_array('criteres', $zones, true)) {
                $mine = [];
                foreach ($fs['criteres'] as $c) {
                    $mine[$c['id']] = $mine[$c['id']] ?? $c;
                }
                $list = [];
                foreach ($fcriteria as $fc) {
                    $c = $mine[$fc['id']] ?? $fc;
                    $c['description'] = $fc['description'];
                    $list[] = $c;
                }
                $fs['criteres'] = $list;
            }
            $byid = [];
            foreach ($fcriteria as $fc) {
                $byid[$fc['id']] = $fc;
            }
            foreach ($fs['criteres'] as &$c) {
                if (!isset($byid[$c['id']])) {
                    continue;
                }
                if (in_array('niveaux', $zones, true)) {
                    $c['niveau'] = $byid[$c['id']]['niveau'];
                }
                if (in_array('flexibilite', $zones, true)) {
                    $c['flexibilite'] = $byid[$c['id']]['flexibilite'];
                }
            }
            unset($c);
        }
        unset($fs);
        return $student;
    }

    /**
     * Levels removed when the teacher removes one: that level and every higher validated one.
     *
     * @param string $palier Level removed by the teacher.
     * @param string[] $validated Levels validated.
     * @return string[] In the order of ORDER.
     */
    public static function retire_cascade(string $palier, array $validated): array {
        $from = array_search($palier, self::ORDER, true);
        if ($from === false) {
            return [];
        }
        return array_values(array_filter(self::ORDER, function (string $p) use ($from, $validated): bool {
            return array_search($p, self::ORDER, true) >= $from && in_array($p, $validated, true);
        }));
    }
}
