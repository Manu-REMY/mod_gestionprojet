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
 * Editable grading grid of step 4 (functional specification).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Pure grid model, without Moodle calls so that it can be checked alone.
 *
 * A part: id, nom, description, poids (int >= 0), zones (subset of ZONES), palier (PALIERS), evaluee (EVALUEE).
 */
class grille {

    /** @var string[] What a part looks at in the student's CDCF, in display order. */
    const ZONES = ['interacteurs', 'fonctions', 'criteres', 'niveaux', 'flexibilite', 'contraintes', 'ensemble'];

    /** @var string[] Level a part belongs to in the levels mode ('' = none). */
    const PALIERS = ['', 'F', 'S', 'TS'];

    /** @var string[] auto: graded unless provided by the instructions; oui / non: forced by the teacher. */
    const EVALUEE = ['auto', 'oui', 'non'];

    /**
     * The grid used until the teacher saves one: the former fixed grid of step 4.
     *
     * @param bool $flexibility Whether the instance asks for flexibility (step4_flexibility): when it does not,
     *                          the grid neither mentions nor looks at it, so editing its wording cannot bring it back.
     * @return array
     */
    public static function default_grid(bool $flexibility = true): array {
        $parts = [
            ['Interacteurs', 4, 'Les interacteurs sont pertinents et complets', ['interacteurs']],
            ['Fonctions de service', 6, 'Chaque FS exprime un service rendu, est rattachée à 1 ou 2 interacteurs et utilise '
                . 'un verbe d\'action à l\'infinitif', ['fonctions']],
            $flexibility
                ? ['Critères', 4, 'Chaque critère a un niveau quantifié et une flexibilité (F0–F3) cohérente',
                    ['criteres', 'niveaux', 'flexibilite']]
                : ['Critères', 4, 'Chaque critère a un niveau quantifié', ['criteres', 'niveaux']],
            ['Contraintes', 3, 'Les contraintes sont identifiées avec justification', ['contraintes']],
            ['Cohérence globale', 3, 'L\'ensemble est cohérent avec la norme NF EN 16271', ['ensemble']],
        ];
        $grid = [];
        foreach ($parts as $i => [$nom, $poids, $description, $zones]) {
            $grid[] = ['id' => $i + 1, 'nom' => $nom, 'description' => $description, 'poids' => $poids,
                'zones' => $zones, 'palier' => '', 'evaluee' => 'auto'];
        }
        return $grid;
    }

    /**
     * Grid stored as JSON, or the default grid when there is none or it cannot be read.
     *
     * @param string|null $json
     * @param bool $flexibility See default_grid().
     * @return array
     */
    public static function decode(?string $json, bool $flexibility = true): array {
        $data = $json === null || trim($json) === '' ? null : json_decode($json, true);
        $grid = is_array($data) ? self::normalize($data) : [];
        return $grid ?: self::default_grid($flexibility);
    }

    /**
     * Cleans a grid sent by the browser or read from the database.
     *
     * @param array $parts
     * @return array
     */
    public static function normalize(array $parts): array {
        $out = [];
        $seen = [];
        foreach ($parts as $part) {
            if (!is_array($part) || trim((string) ($part['nom'] ?? '')) === '') {
                continue;
            }
            $zones = array_values(array_intersect(self::ZONES, (array) ($part['zones'] ?? [])));
            $palier = (string) ($part['palier'] ?? '');
            $evaluee = (string) ($part['evaluee'] ?? '');
            $out[] = [
                'id' => (int) ($part['id'] ?? 0),
                'nom' => trim((string) $part['nom']),
                'description' => trim((string) ($part['description'] ?? '')),
                'poids' => is_numeric($part['poids'] ?? null) ? max(0, (int) $part['poids']) : 0,
                'zones' => $zones ?: ['ensemble'],
                'palier' => in_array($palier, self::PALIERS, true) ? $palier : '',
                'evaluee' => in_array($evaluee, self::EVALUEE, true) ? $evaluee : 'auto',
            ];
        }
        // Ids identify a part across saves: they must be unique and not 0.
        $max = max(array_merge([0], array_column($out, 'id')));
        foreach ($out as $i => $part) {
            if ($part['id'] <= 0 || isset($seen[$part['id']])) {
                $out[$i]['id'] = ++$max;
            }
            $seen[$out[$i]['id']] = true;
        }
        return $out;
    }

    /**
     * Parts actually graded.
     *
     * @param array $grid Normalized grid.
     * @param string[] $lockedzones Zones whose elements are all provided and locked by the instructions.
     * @return array
     */
    public static function effective_parts(array $grid, array $lockedzones = []): array {
        return array_values(array_filter($grid, function (array $part) use ($lockedzones): bool {
            if ($part['evaluee'] !== 'auto') {
                return $part['evaluee'] === 'oui';
            }
            return (bool) array_diff($part['zones'], $lockedzones);
        }));
    }

    /**
     * Weight of each part on 20: rounded to the half point, the rounding gap goes to the heaviest part.
     *
     * @param array $parts Graded parts.
     * @return array [id => weight on 20]
     */
    public static function scaled_weights(array $parts): array {
        if (!$parts) {
            return [];
        }
        $total = array_sum(array_column($parts, 'poids'));
        $out = [];
        foreach ($parts as $part) {
            $share = $total > 0 ? $part['poids'] * 20 / $total : 20 / count($parts);
            $out[$part['id']] = round($share * 2) / 2;
        }
        $gap = 20 - array_sum($out);
        if ($gap != 0) {
            $heaviest = array_search(max($out), $out, true);
            $out[$heaviest] += $gap;
        }
        return $out;
    }
}
