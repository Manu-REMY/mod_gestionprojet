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
 * Lock and gap flags of the step 4 consigne (v2.11.1).
 *
 * The teacher marks each element of the consigne "provided" (locked: the student can neither change nor delete it)
 * or "to fill in" (gap: it reaches the student empty). The flags live in the consigne only: the student copy is
 * stored without flags, and the locks are always read again from the consigne, so the server stays the authority.
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
 * Lock and gap flags of the step 4 consigne.
 */
class consigne_lock {

    /** @var string[] Keys that only describe the consigne, never stored in a student copy. */
    const FLAG_KEYS = ['locked', 'gap', 'niveauLocked', 'niveauGap', 'hint', 'niveauHint'];

    /**
     * Remove every flag key.
     *
     * @param array $data CDCF structure.
     * @return array
     */
    public static function strip_flags(array $data): array {
        $strip = function (array $element): array {
            return array_diff_key($element, array_flip(self::FLAG_KEYS));
        };
        $data['interactors'] = array_map($strip, $data['interactors'] ?? []);
        $data['fonctionsService'] = array_map(function (array $fs) use ($strip): array {
            $fs = $strip($fs);
            $fs['criteres'] = array_map($strip, $fs['criteres'] ?? []);
            return $fs;
        }, $data['fonctionsService'] ?? []);
        $data['contraintes'] = array_map($strip, $data['contraintes'] ?? []);
        return $data;
    }

    /**
     * Whether the consigne locks at least one element.
     *
     * @param array $provided Consigne.
     * @return bool
     */
    public static function has_locks(array $provided): bool {
        foreach (array_merge($provided['interactors'] ?? [], $provided['fonctionsService'] ?? [],
                $provided['contraintes'] ?? []) as $element) {
            if (!empty($element['locked'])) {
                return true;
            }
        }
        foreach ($provided['fonctionsService'] ?? [] as $fs) {
            foreach ($fs['criteres'] ?? [] as $c) {
                if (!empty($c['locked']) || !empty($c['niveauLocked'])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Starting copy of a student: the consigne without flags, fields "to fill in" emptied.
     *
     * @param array $provided Consigne.
     * @return array
     */
    public static function seed(array $provided): array {
        foreach ($provided['interactors'] as &$i) {
            if (!empty($i['gap'])) {
                $i['name'] = '';
            }
        }
        unset($i);
        foreach ($provided['fonctionsService'] as &$fs) {
            $fs = self::seed_fs($fs);
        }
        unset($fs);
        foreach ($provided['contraintes'] as &$c) {
            if (!empty($c['gap'])) {
                $c['description'] = '';
                $c['justification'] = '';
            }
        }
        unset($c);
        return self::strip_flags($provided);
    }

    /**
     * The student copy with the flags of the matching consigne elements, for display and for the AI prompt.
     *
     * A field "to fill in" gets the consigne text as a hint (hint, niveauHint) when there is one.
     * Criteria are matched by FS id and criterion id (criterion ids are only unique within an FS).
     *
     * @param array $student Student copy.
     * @param array $provided Consigne.
     * @return array
     */
    public static function with_flags(array $student, array $provided): array {
        $student = self::strip_flags($student);
        $byid = function (array $list): array {
            $out = [];
            foreach ($list as $element) {
                $out[$element['id']] = $out[$element['id']] ?? $element;
            }
            return $out;
        };
        $pinteractors = $byid($provided['interactors']);
        $pfs = $byid($provided['fonctionsService']);
        $pcontraintes = $byid($provided['contraintes']);

        foreach ($student['interactors'] as &$i) {
            if (isset($pinteractors[$i['id']])) {
                $i = self::copy_flags($i, $pinteractors[$i['id']], 'name');
            }
        }
        unset($i);
        foreach ($student['fonctionsService'] as &$fs) {
            if (!isset($pfs[$fs['id']])) {
                continue;
            }
            $source = $pfs[$fs['id']];
            $fs = self::copy_flags($fs, $source, 'description');
            $pcrit = $byid($source['criteres']);
            foreach ($fs['criteres'] as &$c) {
                if (isset($pcrit[$c['id']])) {
                    $c = self::copy_flags($c, $pcrit[$c['id']], 'description');
                }
            }
            unset($c);
        }
        unset($fs);
        foreach ($student['contraintes'] as &$c) {
            if (isset($pcontraintes[$c['id']])) {
                $c = self::copy_flags($c, $pcontraintes[$c['id']], 'description');
            }
        }
        unset($c);
        return $student;
    }

    /**
     * Restore what the consigne locks in a student copy (the server is the authority).
     *
     * Without any lock in the consigne, the copy is returned unchanged (flags removed). Otherwise: duplicate ids are
     * renumbered (the first occurrence keeps its id), locked fields are taken from the consigne, and missing locked
     * elements are put back at their rank in the consigne, together with what they need: the FS of a locked
     * criterion and the interactors of a locked FS.
     *
     * Student text is never lost: when the element sitting on a locked id has a non-empty locked field that differs
     * from the consigne (an element the student wrote, or edited before the teacher locked it), it keeps its text
     * under a new id and the consigne element is inserted next to it.
     *
     * @param array $student Student copy (as sent by the browser, normalized).
     * @param array $provided Consigne.
     * @return array Student copy to store, without flags.
     */
    public static function enforce(array $student, array $provided): array {
        $student = self::strip_flags($student);
        if (!self::has_locks($provided)) {
            return $student;
        }

        // Interactors: locked ones, and those used by a locked FS.
        $needed = [];
        foreach ($provided['fonctionsService'] as $fs) {
            if (!empty($fs['locked'])) {
                $needed[$fs['interactor1Id']] = true;
                $needed[$fs['interactor2Id']] = true;
            }
        }
        $student['interactors'] = self::unique_ids($student['interactors'], $provided['interactors']);
        $max = self::max_id($student['interactors'], $provided['interactors']);
        $remap = [];
        foreach ($provided['interactors'] as $rank => $pi) {
            $index = self::index_of($student['interactors'], $pi['id']);
            if ($index !== null && !empty($pi['locked'])
                    && self::differs($student['interactors'][$index], $pi, ['name'])) {
                // The student's interactor keeps its name under a new id; their FS follow it.
                $remap[$pi['id']] = ++$max;
                $student['interactors'][$index]['id'] = $max;
                $index = null;
            }
            if ($index === null) {
                if (!empty($pi['locked']) || isset($needed[$pi['id']])) {
                    $copy = self::seed(['interactors' => [$pi], 'fonctionsService' => [], 'contraintes' => []]);
                    array_splice($student['interactors'], min($rank, count($student['interactors'])), 0,
                        [$copy['interactors'][0]]);
                }
            } else if (!empty($pi['locked'])) {
                $student['interactors'][$index]['name'] = $pi['name'];
            }
        }
        foreach ($student['fonctionsService'] as &$sfs) {
            foreach (['interactor1Id', 'interactor2Id'] as $key) {
                if (isset($remap[$sfs[$key]])) {
                    $sfs[$key] = $remap[$sfs[$key]];
                }
            }
        }
        unset($sfs);

        // Service functions and their criteria.
        $student['fonctionsService'] = self::unique_ids($student['fonctionsService'], $provided['fonctionsService']);
        $max = self::max_id($student['fonctionsService'], $provided['fonctionsService']);
        foreach ($provided['fonctionsService'] as $rank => $pfs) {
            $lockedcriteria = array_filter($pfs['criteres'], function (array $c): bool {
                return !empty($c['locked']) || !empty($c['niveauLocked']);
            });
            $index = self::index_of($student['fonctionsService'], $pfs['id']);
            if ($index !== null && !empty($pfs['locked'])
                    && self::differs($student['fonctionsService'][$index], $pfs, ['description'])) {
                // The student's FS (with its criteria) keeps its text under a new id.
                $student['fonctionsService'][$index]['id'] = ++$max;
                $index = null;
            }
            if ($index === null) {
                if (!empty($pfs['locked']) || $lockedcriteria) {
                    array_splice($student['fonctionsService'], min($rank, count($student['fonctionsService'])), 0,
                        [self::strip_flags(['fonctionsService' => [self::seed_fs($pfs)]])['fonctionsService'][0]]);
                }
                continue;
            }
            $fs = &$student['fonctionsService'][$index];
            if (!empty($pfs['locked'])) {
                $fs['description'] = $pfs['description'];
                $fs['interactor1Id'] = $pfs['interactor1Id'];
                $fs['interactor2Id'] = $pfs['interactor2Id'];
            }
            $fs['criteres'] = self::unique_ids($fs['criteres'], $pfs['criteres']);
            $cmax = self::max_id($fs['criteres'], $pfs['criteres']);
            foreach ($pfs['criteres'] as $crank => $pc) {
                if (empty($pc['locked']) && empty($pc['niveauLocked'])) {
                    continue;
                }
                $cindex = self::index_of($fs['criteres'], $pc['id']);
                $lockedfields = array_merge(!empty($pc['locked']) ? ['description'] : [],
                    !empty($pc['niveauLocked']) ? ['niveau'] : []);
                if ($cindex !== null && self::differs($fs['criteres'][$cindex], $pc, $lockedfields)) {
                    $fs['criteres'][$cindex]['id'] = ++$cmax;
                    $cindex = null;
                }
                if ($cindex === null) {
                    $seeded = self::strip_flags(['fonctionsService' => [self::seed_fs(['criteres' => [$pc]])]]);
                    array_splice($fs['criteres'], min($crank, count($fs['criteres'])), 0,
                        [$seeded['fonctionsService'][0]['criteres'][0]]);
                    continue;
                }
                if (!empty($pc['locked'])) {
                    $fs['criteres'][$cindex]['description'] = $pc['description'];
                }
                if (!empty($pc['niveauLocked'])) {
                    $fs['criteres'][$cindex]['niveau'] = $pc['niveau'];
                    $fs['criteres'][$cindex]['flexibilite'] = $pc['flexibilite'];
                }
            }
            unset($fs);
        }

        // Constraints.
        $student['contraintes'] = self::unique_ids($student['contraintes'], $provided['contraintes']);
        $max = self::max_id($student['contraintes'], $provided['contraintes']);
        foreach ($provided['contraintes'] as $rank => $pc) {
            if (empty($pc['locked'])) {
                continue;
            }
            $index = self::index_of($student['contraintes'], $pc['id']);
            if ($index !== null && self::differs($student['contraintes'][$index], $pc, ['description', 'justification'])) {
                $student['contraintes'][$index]['id'] = ++$max;
                $index = null;
            }
            $locked = self::strip_flags(['contraintes' => [$pc]])['contraintes'][0];
            if ($index === null) {
                array_splice($student['contraintes'], min($rank, count($student['contraintes'])), 0, [$locked]);
            } else {
                $student['contraintes'][$index] = $locked;
            }
        }
        return $student;
    }

    /**
     * Student JSON without the consigne flags: only the server decides what is locked (v2.11.1).
     *
     * JSON without any flag is returned byte for byte, so activities without locks store exactly what they did.
     *
     * @param string $json CDCF JSON sent by the browser.
     * @return string
     */
    public static function sanitize_student_json(string $json): string {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $json;
        }
        $found = false;
        array_walk_recursive($decoded, function ($value, $key) use (&$found) {
            if (in_array($key, self::FLAG_KEYS, true)) {
                $found = true;
            }
        });
        if (!$found) {
            return $json;
        }
        return json_encode(self::strip_flags(cdcf_helper::normalize($decoded)), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Zones of the grading grid entirely provided by the consigne (every element locked, zone not empty).
     *
     * Order of grille::ZONES; "ensemble" is never locked; flexibility follows the levels.
     *
     * @param array $provided Consigne.
     * @return string[]
     */
    public static function locked_zones(array $provided): array {
        $all = function (array $list, string $key): bool {
            if (!$list) {
                return false;
            }
            foreach ($list as $element) {
                if (empty($element[$key])) {
                    return false;
                }
            }
            return true;
        };
        $criteria = [];
        foreach ($provided['fonctionsService'] ?? [] as $fs) {
            $criteria = array_merge($criteria, $fs['criteres'] ?? []);
        }
        $zones = [];
        if ($all($provided['interactors'] ?? [], 'locked')) {
            $zones[] = 'interacteurs';
        }
        if ($all($provided['fonctionsService'] ?? [], 'locked')) {
            $zones[] = 'fonctions';
        }
        if ($all($criteria, 'locked')) {
            $zones[] = 'criteres';
        }
        if ($all($criteria, 'niveauLocked')) {
            $zones[] = 'niveaux';
            $zones[] = 'flexibilite';
        }
        if ($all($provided['contraintes'] ?? [], 'locked')) {
            $zones[] = 'contraintes';
        }
        return $zones;
    }

    /**
     * An FS of the consigne with its fields "to fill in" emptied (flags kept).
     *
     * @param array $fs
     * @return array
     */
    private static function seed_fs(array $fs): array {
        if (!empty($fs['gap'])) {
            $fs['description'] = '';
        }
        foreach ($fs['criteres'] as &$c) {
            if (!empty($c['gap'])) {
                $c['description'] = '';
            }
            if (!empty($c['niveauGap'])) {
                $c['niveau'] = '';
                $c['flexibilite'] = '';
            }
        }
        unset($c);
        return $fs;
    }

    /**
     * Copy the flags of a consigne element, with the hints of its fields "to fill in".
     *
     * @param array $element Student element.
     * @param array $source Consigne element.
     * @param string $field Main text field of the element.
     * @return array
     */
    private static function copy_flags(array $element, array $source, string $field): array {
        foreach (['locked', 'gap', 'niveauLocked', 'niveauGap'] as $flag) {
            if (!empty($source[$flag])) {
                $element[$flag] = true;
            }
        }
        if (!empty($source['gap']) && ($source[$field] ?? '') !== '') {
            $element['hint'] = $source[$field];
        }
        if (!empty($source['niveauGap']) && ($source['niveau'] ?? '') !== '') {
            $element['niveauHint'] = $source['niveau'];
        }
        return $element;
    }

    /**
     * Renumber duplicate ids: the first occurrence keeps its id, the others get ids used nowhere.
     *
     * @param array $list Student elements.
     * @param array $reserved Consigne elements (their ids are not given to duplicates).
     * @return array
     */
    private static function unique_ids(array $list, array $reserved): array {
        $max = 0;
        foreach (array_merge($list, $reserved) as $element) {
            $max = max($max, (int)$element['id']);
        }
        $seen = [];
        foreach ($list as &$element) {
            if (isset($seen[$element['id']])) {
                $element['id'] = ++$max;
            }
            $seen[$element['id']] = true;
        }
        unset($element);
        return array_values($list);
    }

    /**
     * Whether a student element carries its own text in a locked field (non-empty and different from the consigne).
     *
     * @param array $element Student element.
     * @param array $source Consigne element.
     * @param string[] $fields Locked text fields.
     * @return bool
     */
    private static function differs(array $element, array $source, array $fields): bool {
        foreach ($fields as $field) {
            $value = (string)($element[$field] ?? '');
            if ($value !== '' && $value !== (string)($source[$field] ?? '')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Highest id of two lists.
     *
     * @param array $list
     * @param array $other
     * @return int
     */
    private static function max_id(array $list, array $other): int {
        $max = 0;
        foreach (array_merge($list, $other) as $element) {
            $max = max($max, (int)$element['id']);
        }
        return $max;
    }

    /**
     * Position of the element with this id, or null.
     *
     * @param array $list
     * @param int $id
     * @return int|null
     */
    private static function index_of(array $list, int $id): ?int {
        foreach ($list as $index => $element) {
            if ($element['id'] === $id) {
                return $index;
            }
        }
        return null;
    }
}
