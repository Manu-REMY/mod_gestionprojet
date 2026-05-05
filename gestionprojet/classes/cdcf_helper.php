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
 * Helpers for the CDCF (Cahier des Charges Fonctionnel) data structure (NF EN 16271).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Helpers for the CDCF data structure.
 */
class cdcf_helper {

    /** @var string[] Allowed flexibilite codes. */
    const FLEXIBILITE_CODES = ['', 'F0', 'F1', 'F2', 'F3'];

    /**
     * Default empty CDCF data structure.
     *
     * @return array
     */
    public static function default_data(): array {
        return [
            'interactors' => [
                ['id' => 1, 'name' => ''],
                ['id' => 2, 'name' => ''],
            ],
            'fonctionsService' => [],
            'contraintes' => [],
        ];
    }

    /**
     * Decode a JSON string into a normalized CDCF array.
     *
     * @param string|null $json
     * @return array
     */
    public static function decode(?string $json): array {
        if (empty($json)) {
            return self::default_data();
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return self::default_data();
        }
        return self::normalize($data);
    }

    /**
     * Ensure a decoded array has all expected keys with correct shape.
     *
     * @param array $data
     * @return array
     */
    public static function normalize(array $data): array {
        $out = self::default_data();
        if (!empty($data['interactors']) && is_array($data['interactors'])) {
            $out['interactors'] = array_values(array_map(function ($i) {
                return [
                    'id' => (int)($i['id'] ?? 0),
                    'name' => (string)($i['name'] ?? ''),
                ];
            }, $data['interactors']));
        }
        if (!empty($data['fonctionsService']) && is_array($data['fonctionsService'])) {
            $out['fonctionsService'] = array_values(array_map(function ($fs) {
                $criteres = [];
                if (!empty($fs['criteres']) && is_array($fs['criteres'])) {
                    foreach ($fs['criteres'] as $c) {
                        $criteres[] = [
                            'id' => (int)($c['id'] ?? 0),
                            'description' => (string)($c['description'] ?? ''),
                            'niveau' => (string)($c['niveau'] ?? ''),
                            'flexibilite' => in_array((string)($c['flexibilite'] ?? ''), self::FLEXIBILITE_CODES, true)
                                ? (string)$c['flexibilite'] : '',
                        ];
                    }
                }
                return [
                    'id' => (int)($fs['id'] ?? 0),
                    'description' => (string)($fs['description'] ?? ''),
                    'interactor1Id' => (int)($fs['interactor1Id'] ?? 0),
                    'interactor2Id' => (int)($fs['interactor2Id'] ?? 0),
                    'criteres' => $criteres,
                ];
            }, $data['fonctionsService']));
        }
        if (!empty($data['contraintes']) && is_array($data['contraintes'])) {
            $out['contraintes'] = array_values(array_map(function ($c) {
                return [
                    'id' => (int)($c['id'] ?? 0),
                    'description' => (string)($c['description'] ?? ''),
                    'justification' => (string)($c['justification'] ?? ''),
                    'linkedFsId' => (int)($c['linkedFsId'] ?? 0),
                ];
            }, $data['contraintes']));
        }
        return $out;
    }

    /**
     * Migrate an old-format CDCF record (with $fp text + interacteurs imbriquant des FCs)
     * into the new structure. Idempotent : if data already looks like the new schema,
     * returns it normalized.
     *
     * @param string|null $oldjson Old interacteurs_data JSON (FCs nested in interactors).
     * @param string|null $oldfp Old fp text field.
     * @return array New normalized structure.
     */
    public static function migrate_legacy(?string $oldjson, ?string $oldfp): array {
        $decoded = json_decode((string)$oldjson, true);

        // Already new schema : flat interactors (no `fcs` key) AND has fonctionsService key.
        if (is_array($decoded) && (isset($decoded['fonctionsService']) || isset($decoded['interactors']))) {
            $isnew = true;
            if (!empty($decoded['interactors']) && is_array($decoded['interactors'])) {
                foreach ($decoded['interactors'] as $i) {
                    if (isset($i['fcs'])) { $isnew = false; break; }
                }
            }
            if ($isnew) {
                return self::normalize($decoded);
            }
        }

        $out = [
            'interactors' => [],
            'fonctionsService' => [],
            'contraintes' => [],
        ];
        $nextfsid = 1;

        if (is_array($decoded)) {
            foreach ($decoded as $idx => $i) {
                $out['interactors'][] = [
                    'id' => $idx + 1,
                    'name' => (string)($i['name'] ?? ''),
                ];
            }
        }
        while (count($out['interactors']) < 2) {
            $out['interactors'][] = ['id' => count($out['interactors']) + 1, 'name' => ''];
        }

        $fptrim = trim((string)$oldfp);
        if ($fptrim !== '') {
            $out['fonctionsService'][] = [
                'id' => $nextfsid++,
                'description' => $fptrim,
                'interactor1Id' => $out['interactors'][0]['id'],
                'interactor2Id' => 0,
                'criteres' => [],
            ];
        }

        if (is_array($decoded)) {
            foreach ($decoded as $idx => $i) {
                if (empty($i['fcs']) || !is_array($i['fcs'])) {
                    continue;
                }
                $interactorid = $idx + 1;
                foreach ($i['fcs'] as $fc) {
                    $criteres = [];
                    $cidx = 1;
                    if (!empty($fc['criteres']) && is_array($fc['criteres'])) {
                        foreach ($fc['criteres'] as $c) {
                            $oldniveau = trim((string)($c['niveau'] ?? ''));
                            $oldunite = trim((string)($c['unite'] ?? ''));
                            $mergedniveau = $oldniveau;
                            if ($oldunite !== '') {
                                $mergedniveau = $oldniveau === '' ? $oldunite : ($oldniveau . ' ' . $oldunite);
                            }
                            $criteres[] = [
                                'id' => $cidx++,
                                'description' => (string)($c['critere'] ?? $c['name'] ?? ''),
                                'niveau' => $mergedniveau,
                                'flexibilite' => '',
                            ];
                        }
                    }
                    $out['fonctionsService'][] = [
                        'id' => $nextfsid++,
                        'description' => (string)($fc['value'] ?? $fc['name'] ?? ''),
                        'interactor1Id' => $interactorid,
                        'interactor2Id' => 0,
                        'criteres' => $criteres,
                    ];
                }
            }
        }

        return self::normalize($out);
    }
}
