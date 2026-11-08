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
     * Verdict of an attempt, computed by the server from the points of the level's parts.
     *
     * The points of each part are its weight on the server grid (grille::scaled_weights of the level's parts);
     * the AI's own "max" is never trusted. AI criteria are matched to the parts by name (case and spaces ignored),
     * then by a name starting with the part name followed by a non-letter. Unknown or duplicate criteria are ignored
     * (an unknown name never earns points), a missing part scores 0, scores
     * are bounded to [0, weight]. The threshold is compared with the unrounded ratio; no point earned never validates.
     *
     * @param array $criteria Criteria of the AI response (name, score).
     * @param array $parts Graded parts of the level (palier::parts_for).
     * @param int $seuil Threshold in % of the points of the level.
     * @return array ['valide' => bool, 'percent' => float rounded to 0.1]
     */
    public static function verdict(array $criteria, array $parts, int $seuil): array {
        if (!$parts || !$criteria) {
            return ['valide' => false, 'percent' => 0.0];
        }
        $weights = grille::scaled_weights($parts);
        $norm = function ($name): string {
            return trim(preg_replace('/\s+/u', ' ', mb_strtolower((string)$name)));
        };
        $partbyname = [];
        foreach ($parts as $index => $part) {
            $partbyname[$norm($part['nom'])] = $partbyname[$norm($part['nom'])] ?? $index;
        }
        $scores = [];
        $used = [];
        foreach (array_values($criteria) as $rank => $criterion) {
            $key = $norm($criterion['name'] ?? '');
            if (isset($partbyname[$key]) && !isset($scores[$partbyname[$key]])) {
                $scores[$partbyname[$key]] = $criterion['score'] ?? 0;
                $used[$rank] = true;
            }
        }
        // A name starting with the part name, then a non-letter ("Fonctions de service (verbes)"), matches too.
        foreach (array_values($criteria) as $rank => $criterion) {
            if (!empty($used[$rank])) {
                continue;
            }
            $key = $norm($criterion['name'] ?? '');
            foreach ($parts as $index => $part) {
                $partname = $norm($part['nom']);
                if (!isset($scores[$index]) && $partname !== ''
                        && preg_match('/^' . preg_quote($partname, '/') . '(?![\p{L}\p{N}])/u', $key)) {
                    $scores[$index] = $criterion['score'] ?? 0;
                    $used[$rank] = true;
                    break;
                }
            }
        }
        $earned = 0.0;
        $total = 0.0;
        foreach ($parts as $index => $part) {
            $w = (float)($weights[$part['id']] ?? 0);
            $total += $w;
            $earned += min($w, max(0.0, (float)($scores[$index] ?? 0)));
        }
        if ($total <= 0) {
            return ['valide' => false, 'percent' => 0.0];
        }
        $ratio = 100 * $earned / $total;
        // No point earned never validates, even with a 0 % threshold (an empty or broken AI answer).
        return ['valide' => $earned > 0 && $ratio >= $seuil, 'percent' => round($ratio, 1)];
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
     * Whether a user is listed in the members of a level (JSON [[userid, username], ...]).
     *
     * @param string|null $membres
     * @param int $userid
     * @return bool
     */
    public static function has_member(?string $membres, int $userid): bool {
        foreach ((array)json_decode((string)$membres, true) as $member) {
            if (is_array($member) && (int)($member[0] ?? 0) === $userid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Members of a level without this user (privacy deletion).
     *
     * @param string|null $membres
     * @param int $userid
     * @return string JSON [[userid, username], ...]
     */
    public static function remove_member(?string $membres, int $userid): string {
        $kept = [];
        foreach ((array)json_decode((string)$membres, true) as $member) {
            if (is_array($member) && (int)($member[0] ?? 0) !== $userid) {
                $kept[] = $member;
            }
        }
        return json_encode($kept);
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
