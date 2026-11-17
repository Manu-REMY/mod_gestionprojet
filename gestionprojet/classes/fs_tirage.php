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
 * Service functions drawn at random per copy at step 4 (request 42, D-088, v2.16.0).
 *
 * Each copy completes only the FS it drew. The other FS reach it complete: every field "to fill in" of a non-drawn
 * FS takes the correction model text and becomes provided, so the lock machinery (seed, with_flags, enforce, follow)
 * and the AI prompt (provided fields are not graded) work unchanged on the consigne of the copy.
 *
 * All arrays are CDCF structures normalized by cdcf_helper::normalize(). No Moodle call: pure checks load this class.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Service functions drawn at random per copy at step 4.
 */
class fs_tirage {

    /**
     * Ids of the FS that have at least one field to complete (to fill in or pre-filled), in consigne order.
     *
     * @param array $consigne Consigne.
     * @return int[]
     */
    public static function eligible(array $consigne): array {
        $ids = [];
        foreach ($consigne['fonctionsService'] ?? [] as $fs) {
            $open = !empty($fs['gap']) || !empty($fs['prefill']);
            foreach ($fs['criteres'] ?? [] as $c) {
                $open = $open || !empty($c['gap']) || !empty($c['prefill']) || !empty($c['niveauGap'])
                    || !empty($c['niveauPrefill']);
            }
            if ($open) {
                $ids[] = (int)$fs['id'];
            }
        }
        return $ids;
    }

    /**
     * Choose the FS of a new copy: those drawn the fewest times in its class, then in the whole activity.
     *
     * @param int[] $eligible Ids that can be drawn.
     * @param int $count Number of FS per copy.
     * @param array $classcounts FS id => times drawn by the other copies of the class.
     * @param array $totalcounts FS id => times drawn by the other copies of the activity.
     * @param callable $pick Picks one id among the tied ones (random in production).
     * @return int[] Sorted ids.
     */
    public static function choose(array $eligible, int $count, array $classcounts, array $totalcounts,
            callable $pick): array {
        $left = array_values(array_unique(array_map('intval', $eligible)));
        $chosen = [];
        while (count($chosen) < $count && $left) {
            $best = null;
            $tied = [];
            foreach ($left as $id) {
                $key = [(int)($classcounts[$id] ?? 0), (int)($totalcounts[$id] ?? 0)];
                if ($best === null || $key < $best) {
                    $best = $key;
                    $tied = [$id];
                } else if ($key === $best) {
                    $tied[] = $id;
                }
            }
            $id = (int)$pick($tied);
            $chosen[] = $id;
            $left = array_values(array_diff($left, [$id]));
        }
        sort($chosen);
        return $chosen;
    }

    /**
     * The consigne a copy sees: its drawn FS as they are (flag tiree), the other FS complete (flag donnee).
     *
     * In a non-drawn FS every field to complete takes the text of the correction model (FS and criterion matched by
     * id) and becomes provided. A field the model leaves empty stays to complete (listed by missing() for the teacher).
     *
     * @param array $consigne Consigne.
     * @param array $model Correction model.
     * @param int[] $drawn Drawn FS ids; empty: no draw, the consigne is returned unchanged.
     * @return array
     */
    public static function consigne(array $consigne, array $model, array $drawn): array {
        if (!$drawn) {
            return $consigne;
        }
        $drawn = array_map('intval', $drawn);
        $models = self::by_id($model['fonctionsService'] ?? []);
        foreach ($consigne['fonctionsService'] as &$fs) {
            if (in_array((int)$fs['id'], $drawn, true)) {
                $fs['tiree'] = true;
                continue;
            }
            $fs['donnee'] = true;
            $source = $models[$fs['id']] ?? null;
            if ($source === null) {
                continue;
            }
            if (self::open($fs, 'gap', 'prefill') && trim($source['description']) !== '') {
                $fs = self::provide($fs, ['description' => $source['description']], 'locked', ['gap', 'prefill']);
            }
            $criteria = self::by_id($source['criteres']);
            foreach ($fs['criteres'] as &$c) {
                $sc = $criteria[$c['id']] ?? null;
                if ($sc === null) {
                    continue;
                }
                if (self::open($c, 'gap', 'prefill') && trim($sc['description']) !== '') {
                    $c = self::provide($c, ['description' => $sc['description']], 'locked', ['gap', 'prefill']);
                }
                if (self::open($c, 'niveauGap', 'niveauPrefill') && trim($sc['niveau']) !== '') {
                    $c = self::provide($c, ['niveau' => $sc['niveau'], 'flexibilite' => $sc['flexibilite']],
                        'niveauLocked', ['niveauGap', 'niveauPrefill']);
                }
            }
            unset($c);
        }
        unset($fs);
        return $consigne;
    }

    /**
     * Fields to complete that the correction model leaves empty: a non-drawn FS could not be given complete.
     *
     * @param array $consigne Consigne.
     * @param array $model Correction model.
     * @return array List of ['fs' => FS id, 'champ' => 'fs' | 'critere' | 'niveau'], in consigne order.
     */
    public static function missing(array $consigne, array $model): array {
        $models = self::by_id($model['fonctionsService'] ?? []);
        $out = [];
        foreach ($consigne['fonctionsService'] ?? [] as $fs) {
            $source = $models[$fs['id']] ?? null;
            $fields = [];
            if (self::open($fs, 'gap', 'prefill') && ($source === null || trim($source['description']) === '')) {
                $fields['fs'] = true;
            }
            $criteria = $source === null ? [] : self::by_id($source['criteres']);
            foreach ($fs['criteres'] ?? [] as $c) {
                $sc = $criteria[$c['id']] ?? null;
                if (self::open($c, 'gap', 'prefill') && ($sc === null || trim($sc['description']) === '')) {
                    $fields['critere'] = true;
                }
                if (self::open($c, 'niveauGap', 'niveauPrefill') && ($sc === null || trim($sc['niveau']) === '')) {
                    $fields['niveau'] = true;
                }
            }
            foreach (array_keys($fields) as $field) {
                $out[] = ['fs' => (int)$fs['id'], 'champ' => $field];
            }
        }
        return $out;
    }

    /**
     * Criteria of a given FS are those of the consigne of the copy: a criterion added there is dropped.
     *
     * @param array $copy Student copy (normalized, without flags).
     * @param array $consigne Consigne of the copy (result of consigne()).
     * @return array
     */
    public static function given_shape(array $copy, array $consigne): array {
        $given = [];
        foreach ($consigne['fonctionsService'] ?? [] as $fs) {
            if (!empty($fs['donnee'])) {
                $given[$fs['id']] = array_map(function (array $c): int {
                    return (int)$c['id'];
                }, $fs['criteres']);
            }
        }
        if (!$given) {
            return $copy;
        }
        foreach ($copy['fonctionsService'] as &$fs) {
            if (!isset($given[$fs['id']])) {
                continue;
            }
            $ids = $given[$fs['id']];
            $fs['criteres'] = array_values(array_filter($fs['criteres'], function (array $c) use ($ids): bool {
                return in_array((int)$c['id'], $ids, true);
            }));
        }
        unset($fs);
        return $copy;
    }

    /**
     * Decode the drawn ids stored in a copy.
     *
     * @param string|null $json Value of gestionprojet_cdcf.fs_tirees.
     * @return int[] Empty when the copy has no draw.
     */
    public static function decode(?string $json): array {
        $ids = json_decode((string)$json, true);
        if (!is_array($ids)) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));
        sort($ids);
        return $ids;
    }

    /**
     * Read the predefined subjects the teacher wrote: one subject a line, FS numbers (rank in the consigne).
     *
     * @param string $text E.g. "FS1, FS4, FS6" on each line.
     * @param array $consigne Consigne.
     * @return array ['sujets' => list of sorted FS id lists, 'erreurs' => list of ['ligne' => n, 'fs' => number]]:
     *     a number beyond the consigne or an FS with nothing to complete.
     */
    public static function parse_sujets(string $text, array $consigne): array {
        $ids = array_map(function (array $fs): int {
            return (int)$fs['id'];
        }, $consigne['fonctionsService'] ?? []);
        $eligible = self::eligible($consigne);
        $sujets = [];
        $erreurs = [];
        foreach (preg_split('/\R/', $text) as $index => $line) {
            preg_match_all('/\d+/', $line, $matches);
            if (!$matches[0]) {
                continue;
            }
            $sujet = [];
            foreach ($matches[0] as $number) {
                $rank = (int)$number;
                $id = $ids[$rank - 1] ?? null;
                if ($id === null || !in_array($id, $eligible, true)) {
                    $erreurs[] = ['ligne' => $index + 1, 'fs' => $rank];
                    continue;
                }
                $sujet[] = $id;
            }
            $sujet = array_values(array_unique($sujet));
            sort($sujet);
            if ($sujet) {
                $sujets[] = $sujet;
            }
        }
        return ['sujets' => $sujets, 'erreurs' => $erreurs];
    }

    /**
     * Subjects as the teacher writes them: "FS1, FS4, FS6", one a line.
     *
     * @param array $sujets List of FS id lists.
     * @param array $consigne Consigne.
     * @return string
     */
    public static function format_sujets(array $sujets, array $consigne): string {
        return implode("\n", array_map(function (array $sujet) use ($consigne): string {
            return implode(', ', array_map(function (int $rank): string {
                return 'FS' . $rank;
            }, self::rangs($consigne, $sujet)));
        }, $sujets));
    }

    /**
     * FS numbers (rank in the consigne) of FS ids, sorted; unknown ids are left out.
     *
     * @param array $consigne Consigne.
     * @param int[] $ids
     * @return int[]
     */
    public static function rangs(array $consigne, array $ids): array {
        $ranks = [];
        foreach ($consigne['fonctionsService'] ?? [] as $index => $fs) {
            if (in_array((int)$fs['id'], array_map('intval', $ids), true)) {
                $ranks[] = $index + 1;
            }
        }
        return $ranks;
    }

    /**
     * Index of the subject a draw comes from.
     *
     * @param array $sujets List of FS id lists.
     * @param int[] $drawn Drawn FS ids.
     * @return int|null
     */
    public static function sujet_of(array $sujets, array $drawn): ?int {
        $drawn = array_map('intval', $drawn);
        sort($drawn);
        foreach ($sujets as $index => $sujet) {
            if ($sujet === $drawn) {
                return (int)$index;
            }
        }
        return null;
    }

    /**
     * Choose the subject of a new copy: the one drawn the fewest times in its class, then in the activity.
     *
     * @param array $sujets List of FS id lists.
     * @param array $classcounts Subject index => times drawn by the other copies of the class.
     * @param array $totalcounts Subject index => times drawn by the other copies of the activity.
     * @param callable $pick Picks one index among the tied ones.
     * @return int[] FS ids of the subject; empty without subject.
     */
    public static function choose_sujet(array $sujets, array $classcounts, array $totalcounts, callable $pick): array {
        $chosen = self::choose(array_keys($sujets), 1, $classcounts, $totalcounts, $pick);
        return $chosen ? $sujets[$chosen[0]] : [];
    }

    /**
     * Decode the stored subjects (gestionprojet.step4_fs_sujets).
     *
     * @param string|null $json
     * @return array List of sorted FS id lists, empty ones dropped.
     */
    public static function decode_sujets(?string $json): array {
        $raw = json_decode((string)$json, true);
        $sujets = [];
        foreach (is_array($raw) ? $raw : [] as $sujet) {
            if (!is_array($sujet)) {
                continue;
            }
            $ids = self::decode(json_encode(array_values($sujet)));
            if ($ids) {
                $sujets[] = $ids;
            }
        }
        return $sujets;
    }

    /**
     * Whether a field is to complete (to fill in or pre-filled).
     *
     * @param array $element
     * @param string $gap
     * @param string $prefill
     * @return bool
     */
    private static function open(array $element, string $gap, string $prefill): bool {
        return !empty($element[$gap]) || !empty($element[$prefill]);
    }

    /**
     * Give the fields their model text and mark them provided.
     *
     * @param array $element
     * @param array $values Field => model text.
     * @param string $lock Lock flag to set.
     * @param string[] $remove Flags to remove.
     * @return array
     */
    private static function provide(array $element, array $values, string $lock, array $remove): array {
        foreach ($remove as $flag) {
            unset($element[$flag]);
        }
        foreach ($values as $field => $value) {
            $element[$field] = $value;
        }
        $element[$lock] = true;
        return $element;
    }

    /**
     * Index a list of elements by id (first occurrence wins).
     *
     * @param array $list
     * @return array
     */
    private static function by_id(array $list): array {
        $out = [];
        foreach ($list as $element) {
            $out[$element['id']] = $out[$element['id']] ?? $element;
        }
        return $out;
    }
}
