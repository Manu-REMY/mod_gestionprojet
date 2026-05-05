# Uniformisation CDCF avec la norme NF EN 16271 — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aligner la step 4 (CDCF) du plugin sur le modèle de référence du projet `sequence-manager` (norme NF EN 16271) — afficher d'abord les interacteurs puis les fonctions de service indépendantes (chacune liée à 1 ou 2 interacteurs et porteuse de critères avec niveau et flexibilité), ajouter une section contraintes, adopter le diagramme pieuvre du modèle, et migrer les données existantes.

**Architecture:** Refonte structurelle du JSON `interacteurs_data` (3 tables : `gestionprojet_cdcf`, `gestionprojet_cdcf_teacher`, `gestionprojet_cdcf_provided`). Migration en place dans `db/upgrade.php` qui (1) convertit la FP texte libre en première FS, (2) convertit chaque FC imbriquée d'un interacteur en FS indépendante référençant cet interacteur, (3) concatène l'ancienne `unite` à la fin du `niveau`, (4) ajoute la flexibilité (vide par défaut), (5) initialise un tableau `contraintes` vide, puis drop les colonnes `produit`, `milieu`, `fp`. Frontend : nouveau module AMD `cdcf` qui rend interacteurs → diagramme pieuvre (courbes colorées entre paires) → FS avec critères → contraintes. Vue lecture pour `cdcf_provided`. Le nom de l'activité Moodle remplace l'ancien champ `produit` au centre du diagramme.

**Tech Stack:** Moodle 5.0+ / PHP 8.1+, XMLDB, DML, AMD (RequireJS + jQuery), SVG natif, Bootstrap 5, Mustache (si besoin).

**Modèle de référence consulté:** `/Volumes/DONNEES/Claude code/Séquence/sequence-manager/src/types/projet.ts` et `src/components/projets/tools/CahierChargesTool.tsx`.

---

## Nouveau schéma JSON (champ `interacteurs_data`)

```json
{
  "interactors": [
    { "id": 1, "name": "Utilisateur" },
    { "id": 2, "name": "Environnement" }
  ],
  "fonctionsService": [
    {
      "id": 1,
      "description": "Permettre à l'utilisateur de…",
      "interactor1Id": 1,
      "interactor2Id": 0,
      "criteres": [
        { "id": 1, "description": "Précision", "niveau": "10 mm", "flexibilite": "F1" }
      ]
    }
  ],
  "contraintes": [
    { "id": 1, "description": "Conforme RoHS", "justification": "Réglementation UE", "linkedFsId": 0 }
  ]
}
```

Valeurs `flexibilite` autorisées : `""`, `"F0"`, `"F1"`, `"F2"`, `"F3"` (libellés affichés via lang).

---

## File Structure

| Fichier | Action | Rôle |
|---|---|---|
| `gestionprojet/db/install.xml` | Modify | Drop `produit`, `milieu`, `fp` des 3 tables CDCF |
| `gestionprojet/db/upgrade.php` | Modify | Step de migration JSON + drop colonnes |
| `gestionprojet/version.php` | Modify | Bump 2.7.0 / 2026050601 |
| `gestionprojet/classes/cdcf_helper.php` | Create | Helpers PHP pour normalisation JSON, migration in-memory, défauts |
| `gestionprojet/pages/step4.php` | Rewrite | Vue élève : interacteurs → pieuvre → FS → contraintes |
| `gestionprojet/pages/step4_teacher.php` | Rewrite | Vue modèle correction (mêmes blocs + zone IA) |
| `gestionprojet/pages/step4_provided.php` | Rewrite | Vue lecture seule du CDCF consigne |
| `gestionprojet/amd/src/cdcf.js` | Create | Rendu interacteurs / FS / critères / contraintes |
| `gestionprojet/amd/src/cdcf_diagram.js` | Create | SVG pieuvre (courbes colorées entre paires d'interacteurs) |
| `gestionprojet/amd/src/cdcf_bootstrap.js` | Create | Glue entre PHP, autosave, submit, et l'éditeur AMD |
| `gestionprojet/ajax/autosave.php` | Modify | Whitelist : retirer `produit`/`milieu`/`fp`, garder `interacteurs_data` |
| `gestionprojet/ajax/submit_step.php` | Verify only | Aucun champ obsolète à nettoyer (à confirmer en Task 13) |
| `gestionprojet/classes/ai_prompt_builder.php` | Modify | `STEP_FIELDS[4]`, `STEP_CRITERIA[4]`, `format_interacteurs` → format FS+contraintes |
| `gestionprojet/classes/ai_response_parser.php` | Verify only | Vérifier qu'aucun parsing nommé `produit/milieu/fp` |
| `gestionprojet/classes/ai_evaluator.php` | Verify only | Vérifier qu'aucun usage direct de `produit`/`milieu`/`fp` |
| `gestionprojet/lang/en/gestionprojet.php` | Modify | Nouveaux strings (FS, flexibilité, contraintes) + retraits |
| `gestionprojet/lang/fr/gestionprojet.php` | Modify | Idem FR |
| `gestionprojet/styles.css` | Modify | Styles `.path-mod-gestionprojet .gp-cdcf-*` (FS, critères, flexibilité, contraintes) |
| `gestionprojet/lib.php` | Verify only | `gestionprojet_delete_instance` — pas de nouvelle table donc rien à ajouter |
| `gestionprojet/classes/privacy/provider.php` | Modify if needed | Retirer `produit/milieu/fp` des metadata si présents |

PDF : aucun export PDF dédié à step 4 trouvé dans le code (l'`exportPDF()` de `step4.php` est un `alert('coming soon')`). Aucune action.

---

## Task 1 : Helper PHP pour normaliser le nouveau schéma JSON

**Files:**
- Create: `gestionprojet/classes/cdcf_helper.php`

- [ ] **Step 1 : Créer la classe `cdcf_helper`**

```php
<?php
// GPL header complet (cf CLAUDE.md §1).

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Helpers for the CDCF (Cahier des Charges Fonctionnel) data structure.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

        // Already new schema : flat interactors (no `fcs` key).
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
```

- [ ] **Step 2 : Lint syntaxe**

Run :
```bash
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/classes/cdcf_helper.php"
```
Expected : `No syntax errors detected`.

- [ ] **Step 3 : Commit**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet"
git add gestionprojet/classes/cdcf_helper.php
git commit -m "feat(cdcf): add cdcf_helper with normalize/decode/migrate_legacy"
```

---

## Task 2 : Test unitaire du helper de migration

**Files:**
- Create: `gestionprojet/tests/cdcf_helper_test.php`

- [ ] **Step 1 : Écrire le test PHPUnit**

S'aligner sur le boilerplate de `gestionprojet/tests/fast_helpers_test.php`.

```php
<?php
// GPL header complet.

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for cdcf_helper::migrate_legacy and normalize.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_gestionprojet\cdcf_helper
 */
class cdcf_helper_test extends \basic_testcase {

    public function test_default_data_has_expected_shape(): void {
        $d = cdcf_helper::default_data();
        $this->assertArrayHasKey('interactors', $d);
        $this->assertArrayHasKey('fonctionsService', $d);
        $this->assertArrayHasKey('contraintes', $d);
        $this->assertCount(2, $d['interactors']);
    }

    public function test_migrate_legacy_converts_fp_to_first_fs(): void {
        $oldjson = json_encode([
            ['name' => 'Utilisateur', 'fcs' => []],
            ['name' => 'Environnement', 'fcs' => []],
        ]);
        $result = cdcf_helper::migrate_legacy($oldjson, 'Permettre à l\'utilisateur de mesurer la température');
        $this->assertCount(1, $result['fonctionsService']);
        $this->assertSame('Permettre à l\'utilisateur de mesurer la température',
            $result['fonctionsService'][0]['description']);
        $this->assertSame(1, $result['fonctionsService'][0]['interactor1Id']);
        $this->assertSame(0, $result['fonctionsService'][0]['interactor2Id']);
    }

    public function test_migrate_legacy_converts_fcs_to_fs_per_interactor(): void {
        $oldjson = json_encode([
            ['name' => 'A', 'fcs' => [
                ['value' => 'Mesurer X', 'criteres' => [
                    ['critere' => 'Précision', 'niveau' => '10', 'unite' => 'mm'],
                ]],
            ]],
            ['name' => 'B', 'fcs' => [
                ['value' => 'Résister à Y', 'criteres' => []],
            ]],
        ]);
        $result = cdcf_helper::migrate_legacy($oldjson, '');
        $this->assertCount(2, $result['fonctionsService']);
        $this->assertSame('Mesurer X', $result['fonctionsService'][0]['description']);
        $this->assertSame(1, $result['fonctionsService'][0]['interactor1Id']);
        $this->assertSame('10 mm', $result['fonctionsService'][0]['criteres'][0]['niveau']);
        $this->assertSame('Précision', $result['fonctionsService'][0]['criteres'][0]['description']);
        $this->assertSame('', $result['fonctionsService'][0]['criteres'][0]['flexibilite']);
        $this->assertSame('Résister à Y', $result['fonctionsService'][1]['description']);
        $this->assertSame(2, $result['fonctionsService'][1]['interactor1Id']);
    }

    public function test_migrate_legacy_is_idempotent_on_new_schema(): void {
        $newjson = json_encode([
            'interactors' => [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']],
            'fonctionsService' => [
                ['id' => 1, 'description' => 'X', 'interactor1Id' => 1, 'interactor2Id' => 0, 'criteres' => []],
            ],
            'contraintes' => [],
        ]);
        $result = cdcf_helper::migrate_legacy($newjson, '');
        $this->assertCount(1, $result['fonctionsService']);
        $this->assertSame('X', $result['fonctionsService'][0]['description']);
    }

    public function test_migrate_legacy_handles_empty_input(): void {
        $result = cdcf_helper::migrate_legacy('', '');
        $this->assertSame([], $result['fonctionsService']);
        $this->assertSame([], $result['contraintes']);
        $this->assertCount(2, $result['interactors']);
    }

    public function test_normalize_filters_invalid_flexibilite(): void {
        $data = [
            'interactors' => [['id' => 1, 'name' => 'A']],
            'fonctionsService' => [[
                'id' => 1, 'description' => 'X', 'interactor1Id' => 1, 'interactor2Id' => 0,
                'criteres' => [['id' => 1, 'description' => 'C', 'niveau' => 'N', 'flexibilite' => 'BOGUS']],
            ]],
        ];
        $result = cdcf_helper::normalize($data);
        $this->assertSame('', $result['fonctionsService'][0]['criteres'][0]['flexibilite']);
    }
}
```

- [ ] **Step 2 : Lancer le test**

Run (depuis racine Moodle, en preprod ou en local) :
```bash
vendor/bin/phpunit --no-coverage --filter cdcf_helper_test
```
Expected : 6 tests OK.

Si pas d'environnement Moodle PHPUnit local : noter `# skip — to run on preprod` et passer.

- [ ] **Step 3 : Commit**

```bash
git add gestionprojet/tests/cdcf_helper_test.php
git commit -m "test(cdcf): cover migrate_legacy and normalize"
```

---

## Task 3 : Migration DB (upgrade.php) + bump version

**Files:**
- Modify: `gestionprojet/db/upgrade.php` (ajouter un nouveau bloc à la fin, juste avant `return true;`)
- Modify: `gestionprojet/db/install.xml`
- Modify: `gestionprojet/version.php`

- [ ] **Step 1 : Lire le bas de `upgrade.php`**

Run :
```bash
tail -50 "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/db/upgrade.php"
```
Vérifier la dernière `$oldversion <` utilisée.

- [ ] **Step 2 : Bumper `version.php`**

Remplacer dans `gestionprojet/version.php` :
```php
$plugin->version = 2026050505;
$plugin->release = '2.6.3';
```
par :
```php
$plugin->version = 2026050601;
$plugin->release = '2.7.0';
```

- [ ] **Step 3 : Ajouter la step de migration dans `upgrade.php`**

Insérer (avant `return true;`) :

```php
    if ($oldversion < 2026050601) {

        require_once($CFG->dirroot . '/mod/gestionprojet/classes/cdcf_helper.php');

        $cdcftables = ['gestionprojet_cdcf', 'gestionprojet_cdcf_teacher', 'gestionprojet_cdcf_provided'];
        foreach ($cdcftables as $tname) {
            $rs = $DB->get_recordset($tname);
            foreach ($rs as $rec) {
                $newdata = \mod_gestionprojet\cdcf_helper::migrate_legacy(
                    $rec->interacteurs_data ?? null,
                    $rec->fp ?? null
                );
                $rec->interacteurs_data = json_encode($newdata, JSON_UNESCAPED_UNICODE);
                $DB->update_record($tname, $rec);
            }
            $rs->close();
        }

        $deprecated = ['produit', 'milieu', 'fp'];
        foreach ($cdcftables as $tname) {
            $table = new xmldb_table($tname);
            foreach ($deprecated as $fname) {
                $field = new xmldb_field($fname);
                if ($dbman->field_exists($table, $field)) {
                    $dbman->drop_field($table, $field);
                }
            }
        }

        upgrade_mod_savepoint(true, 2026050601, 'gestionprojet');
    }
```

- [ ] **Step 4 : Mettre à jour `db/install.xml`**

Pour les 3 tables `gestionprojet_cdcf`, `gestionprojet_cdcf_teacher`, `gestionprojet_cdcf_provided` : supprimer les `<FIELD>` `produit`, `milieu`, `fp`. Conserver `interacteurs_data`. Vérifier qu'aucun `<KEY>` ou `<INDEX>` ne référence ces champs :

```bash
grep -n "produit\|milieu\|FIELD NAME=\"fp\"" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/db/install.xml"
```

- [ ] **Step 5 : Lint**

```bash
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/db/upgrade.php"
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/version.php"
```

- [ ] **Step 6 : Commit**

```bash
git add gestionprojet/db/upgrade.php gestionprojet/db/install.xml gestionprojet/version.php
git commit -m "feat(cdcf): db migration to norm-aligned schema (drop produit/milieu/fp), bump 2.7.0"
```

---

## Task 4 : AI prompt builder — adapter au nouveau schéma

**Files:**
- Modify: `gestionprojet/classes/ai_prompt_builder.php`

- [ ] **Step 1 : Adapter `STEP_FIELDS[4]`**

Localiser autour de la ligne 41 et remplacer :
```php
4 => ['produit', 'milieu', 'fp', 'interacteurs_data'],
```
par :
```php
4 => ['interacteurs_data'],
```

- [ ] **Step 2 : Adapter `STEP_CRITERIA[4]`**

Remplacer le bloc 4 (lignes ~63-70) par :
```php
        4 => [
            ['name' => 'Interacteurs', 'weight' => 4, 'description' => 'Les interacteurs sont pertinents et complets'],
            ['name' => 'Fonctions de service', 'weight' => 6, 'description' => 'Chaque FS exprime un service rendu, est rattachée à 1 ou 2 interacteurs et utilise un verbe d\'action à l\'infinitif'],
            ['name' => 'Critères', 'weight' => 4, 'description' => 'Chaque critère a un niveau quantifié et une flexibilité (F0–F3) cohérente'],
            ['name' => 'Contraintes', 'weight' => 3, 'description' => 'Les contraintes sont identifiées avec justification'],
            ['name' => 'Cohérence globale', 'weight' => 3, 'description' => 'L\'ensemble est cohérent avec la norme NF EN 16271'],
        ],
```

- [ ] **Step 3 : Réécrire `format_interacteurs`**

Remplacer la méthode (lignes ~360-403) par :
```php
    /**
     * Format CDCF data for AI prompt display.
     *
     * @param string|null $json
     * @return string
     */
    private function format_interacteurs(?string $json): string {
        if (empty($json)) {
            return '';
        }

        require_once(__DIR__ . '/cdcf_helper.php');
        $data = \mod_gestionprojet\cdcf_helper::decode($json);

        $out = [];

        $out[] = 'INTERACTEURS :';
        foreach ($data['interactors'] as $i) {
            $out[] = '  • [I' . $i['id'] . '] ' . ($i['name'] !== '' ? $i['name'] : '(sans nom)');
        }

        $byid = [];
        foreach ($data['interactors'] as $i) {
            $byid[$i['id']] = $i['name'] !== '' ? $i['name'] : ('Interacteur ' . $i['id']);
        }

        $out[] = '';
        $out[] = 'FONCTIONS DE SERVICE :';
        foreach ($data['fonctionsService'] as $idx => $fs) {
            $i1 = $byid[$fs['interactor1Id']] ?? '?';
            $tail = $fs['interactor2Id'] > 0 ? (' ↔ ' . ($byid[$fs['interactor2Id']] ?? '?')) : '';
            $out[] = sprintf('  • FS%d : %s [%s%s]',
                $idx + 1,
                $fs['description'] !== '' ? $fs['description'] : '(énoncé manquant)',
                $i1, $tail);
            foreach ($fs['criteres'] as $cidx => $c) {
                $out[] = sprintf('      - C%d.%d : %s | niveau : %s | flexibilité : %s',
                    $idx + 1, $cidx + 1,
                    $c['description'] !== '' ? $c['description'] : '(critère vide)',
                    $c['niveau'] !== '' ? $c['niveau'] : '(non précisé)',
                    $c['flexibilite'] !== '' ? $c['flexibilite'] : '(non précisée)');
            }
        }

        if (!empty($data['contraintes'])) {
            $out[] = '';
            $out[] = 'CONTRAINTES :';
            foreach ($data['contraintes'] as $cidx => $c) {
                $linked = $c['linkedFsId'] > 0 ? (' (liée à FS' . $c['linkedFsId'] . ')') : '';
                $out[] = sprintf('  • C%d : %s%s — Justification : %s',
                    $cidx + 1,
                    $c['description'] !== '' ? $c['description'] : '(énoncé manquant)',
                    $linked,
                    $c['justification'] !== '' ? $c['justification'] : '(non précisée)');
            }
        }

        return implode("\n", $out);
    }
```

- [ ] **Step 4 : Vérifier les autres références à `produit`/`milieu`/`fp`**

Run :
```bash
grep -n "produit\|milieu\|'fp'" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/classes/ai_prompt_builder.php"
```
Si des occurrences subsistent (ex. dans `STEP_DESCRIPTIONS` ou un `field_label_map`), les retirer/adapter.

- [ ] **Step 5 : Lint**

```bash
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/classes/ai_prompt_builder.php"
```

- [ ] **Step 6 : Commit**

```bash
git add gestionprojet/classes/ai_prompt_builder.php
git commit -m "feat(ai): rebuild step 4 prompt for new CDCF schema (FS + flexibilité + contraintes)"
```

---

## Task 5 : AJAX autosave — whitelist du nouveau schéma

**Files:**
- Modify: `gestionprojet/ajax/autosave.php`

- [ ] **Step 1 : Mettre à jour les 3 whitelists step 4**

Remplacer ligne ~66 :
```php
4 => ['table' => 'gestionprojet_cdcf_provided', 'fields' => ['produit', 'milieu', 'fp', 'interacteurs_data']],
```
par :
```php
4 => ['table' => 'gestionprojet_cdcf_provided', 'fields' => ['interacteurs_data']],
```

Remplacer ligne ~115 :
```php
4 => ['table' => 'gestionprojet_cdcf_teacher', 'fields' => ['produit', 'milieu', 'fp', 'interacteurs_data', 'ai_instructions', 'submission_date', 'deadline_date']],
```
par :
```php
4 => ['table' => 'gestionprojet_cdcf_teacher', 'fields' => ['interacteurs_data', 'ai_instructions', 'submission_date', 'deadline_date']],
```

Remplacer ligne ~291 :
```php
$validfields = ['produit', 'milieu', 'fp', 'interacteurs_data'];
```
par :
```php
$validfields = ['interacteurs_data'];
```

- [ ] **Step 2 : Lint**

```bash
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/ajax/autosave.php"
```

- [ ] **Step 3 : Commit**

```bash
git add gestionprojet/ajax/autosave.php
git commit -m "feat(autosave): step 4 whitelist down to interacteurs_data only"
```

---

## Task 6 : Module AMD du diagramme pieuvre

**Files:**
- Create: `gestionprojet/amd/src/cdcf_diagram.js`

- [ ] **Step 1 : Implémenter le rendu SVG**

Le module construit un SVG pieuvre : interacteurs disposés sur une ellipse, ellipse centrale avec le nom du projet, et une courbe colorée par FS reliant ses 1 ou 2 interacteurs (label `FS{n}` au milieu de la courbe). Aucune utilisation de `innerHTML` — `replaceChildren()` pour vider, `appendChild` pour le reste, `textContent` pour le texte des `<text>`.

```javascript
/**
 * Pieuvre diagram renderer for the CDCF (NF EN 16271).
 *
 * @module     mod_gestionprojet/cdcf_diagram
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var COLORS = ['#d97706', '#dc2626', '#7c3aed', '#0891b2', '#059669', '#d946ef'];
    var WIDTH = 800;
    var HEIGHT = 460;
    var CX = WIDTH / 2;
    var CY = HEIGHT / 2;

    function svg(name, attrs) {
        var el = document.createElementNS('http://www.w3.org/2000/svg', name);
        Object.keys(attrs || {}).forEach(function(k) { el.setAttribute(k, attrs[k]); });
        return el;
    }

    function svgText(attrs, text) {
        var t = svg('text', attrs);
        t.textContent = text;
        return t;
    }

    function computePositions(n) {
        var rx = n <= 4 ? 285 : 330;
        var ry = n <= 4 ? 165 : 203;
        var positions = [];
        for (var i = 0; i < n; i++) {
            var angle = -Math.PI / 2 + (2 * Math.PI * i) / Math.max(n, 1);
            positions.push({
                x: CX + rx * Math.cos(angle),
                y: CY + ry * Math.sin(angle),
            });
        }
        return positions;
    }

    function render(target, projetNom, interactors, fonctionsService) {
        target.replaceChildren();
        var root = svg('svg', { viewBox: '0 0 ' + WIDTH + ' ' + HEIGHT, width: '100%', height: 'auto' });

        var positions = computePositions(interactors.length);

        fonctionsService.forEach(function(fs, idx) {
            var color = COLORS[idx % COLORS.length];
            var label = 'FS' + (idx + 1);
            var i1 = interactors.findIndex(function(it) { return it.id === fs.interactor1Id; });
            if (i1 < 0) { return; }
            if (fs.interactor2Id > 0) {
                var i2 = interactors.findIndex(function(it) { return it.id === fs.interactor2Id; });
                if (i2 < 0) { return; }
                var p1 = positions[i1], p2 = positions[i2];
                var mx = (p1.x + p2.x) / 2, my = (p1.y + p2.y) / 2;
                var d = 'M ' + p1.x + ' ' + p1.y + ' Q ' + CX + ' ' + CY + ' ' + p2.x + ' ' + p2.y;
                root.appendChild(svg('path', { d: d, stroke: color, 'stroke-width': '2', fill: 'none' }));
                root.appendChild(svgText({
                    x: mx, y: my, fill: color, 'font-size': '12', 'font-weight': 'bold', 'text-anchor': 'middle',
                }, label));
            } else {
                var p = positions[i1];
                var midx = (p.x + CX) / 2, midy = (p.y + CY) / 2;
                root.appendChild(svg('line', {
                    x1: p.x, y1: p.y, x2: CX, y2: CY, stroke: color, 'stroke-width': '2',
                }));
                root.appendChild(svgText({
                    x: midx, y: midy - 6, fill: color, 'font-size': '12', 'font-weight': 'bold', 'text-anchor': 'middle',
                }, label));
            }
        });

        root.appendChild(svg('ellipse', {
            cx: CX, cy: CY, rx: '90', ry: '55', fill: '#667eea', stroke: '#764ba2', 'stroke-width': '3',
        }));
        root.appendChild(svgText({
            x: CX, y: CY, 'text-anchor': 'middle', 'dominant-baseline': 'middle',
            'font-size': '16', 'font-weight': 'bold', fill: 'white',
        }, projetNom || ''));

        interactors.forEach(function(inter, i) {
            var pos = positions[i];
            root.appendChild(svg('circle', {
                cx: pos.x, cy: pos.y, r: '38', fill: '#f0f3ff', stroke: '#667eea', 'stroke-width': '2',
            }));
            root.appendChild(svgText({
                x: pos.x, y: pos.y, 'text-anchor': 'middle', 'dominant-baseline': 'middle',
                'font-size': '12', fill: '#333',
            }, inter.name || ('Interacteur ' + inter.id)));
        });

        target.appendChild(root);
    }

    return { render: render };
});
```

- [ ] **Step 2 : Build AMD**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet" && grunt amd 2>&1 | tail -10
```
Si grunt indisponible : créer manuellement `amd/build/cdcf_diagram.min.js` (copie ou minify simple via `npx terser`).

- [ ] **Step 3 : Commit**

```bash
git add gestionprojet/amd/src/cdcf_diagram.js gestionprojet/amd/build/cdcf_diagram.min.js
git commit -m "feat(cdcf): add cdcf_diagram AMD module (pieuvre with FS curves)"
```

---

## Task 7 : Module AMD principal CDCF (rendu interacteurs / FS / critères / contraintes)

**Files:**
- Create: `gestionprojet/amd/src/cdcf.js`

- [ ] **Step 1 : Implémenter le module**

Le module exporte `init({ container, initialData, lang, projetNom, isLocked, onChange })` et :
- rend les sections dans l'ordre : Interacteurs → Diagramme → Fonctions de service → Contraintes
- réordonne les FS via flèches haut/bas
- ajoute/supprime interacteur (bloque la suppression si une FS référence l'interacteur, ou si on a moins de 3 interacteurs)
- ajoute/supprime FS (avec selects interacteur1/2)
- ajoute/supprime critères dans une FS
- gère la flexibilité (select F0–F3) avec classe CSS reflétant la couleur
- ajoute/supprime contraintes (avec select FS liée optionnel)
- chaque mutation appelle `onChange(currentData)` qui met à jour un input caché `interacteurs_data`

Construction DOM via `document.createElement` et `textContent` uniquement — pas d'`innerHTML`. Vidage via `replaceChildren()`.

```javascript
/**
 * CDCF editor (norm NF EN 16271).
 *
 * @module     mod_gestionprojet/cdcf
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['mod_gestionprojet/cdcf_diagram'], function(Diagram) {
    'use strict';

    function nextId(arr) {
        var m = 0;
        arr.forEach(function(x) { if (x.id > m) { m = x.id; } });
        return m + 1;
    }

    function el(tag, attrs, children) {
        var n = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function(k) {
                if (k === 'className') { n.className = attrs[k]; }
                else if (k === 'value') { n.value = attrs[k]; }
                else if (k === 'text') { n.textContent = attrs[k]; }
                else if (k.indexOf('on') === 0 && typeof attrs[k] === 'function') { n[k] = attrs[k]; }
                else { n.setAttribute(k, attrs[k]); }
            });
        }
        (children || []).forEach(function(c) {
            if (c == null) { return; }
            if (typeof c === 'string') { n.appendChild(document.createTextNode(c)); }
            else { n.appendChild(c); }
        });
        return n;
    }

    function init(opts) {
        var container = opts.container;
        var data = opts.initialData;
        var lang = opts.lang;
        var locked = !!opts.isLocked;
        var onChange = opts.onChange || function() {};

        function emit() { onChange(data); render(); }

        function fsReferencingInteractor(id) {
            return data.fonctionsService.filter(function(fs) {
                return fs.interactor1Id === id || fs.interactor2Id === id;
            });
        }

        function render() {
            container.replaceChildren(
                renderInteractors(),
                renderDiagram(),
                renderFs(),
                renderContraintes()
            );
        }

        function renderInteractors() {
            var section = el('section', { className: 'gp-cdcf-section gp-cdcf-interactors' });
            section.appendChild(el('h3', null, [lang.interactorsTitle]));
            section.appendChild(el('p', { className: 'gp-cdcf-norm' }, [lang.interactorsNorm]));
            data.interactors.forEach(function(inter) {
                var canRemove = data.interactors.length > 2 && fsReferencingInteractor(inter.id).length === 0;
                var input = el('input', {
                    type: 'text', value: inter.name, placeholder: lang.interactorPlaceholder,
                    onchange: function(e) { inter.name = e.target.value; emit(); },
                });
                if (locked) { input.disabled = true; }
                var del = el('button', {
                    type: 'button', className: 'gp-cdcf-btn-remove', text: '🗑',
                    onclick: function() {
                        data.interactors = data.interactors.filter(function(x) { return x.id !== inter.id; });
                        emit();
                    },
                });
                if (locked || !canRemove) { del.disabled = true; }
                section.appendChild(el('div', { className: 'gp-cdcf-row' },
                    [el('span', { className: 'gp-cdcf-badge gp-cdcf-badge-i', text: 'I' + inter.id }), input, del]));
            });
            if (!locked) {
                section.appendChild(el('button', {
                    type: 'button', className: 'gp-cdcf-btn-add', text: '+ ' + lang.addInteractor,
                    onclick: function() {
                        data.interactors.push({ id: nextId(data.interactors), name: '' });
                        emit();
                    },
                }));
            }
            return section;
        }

        function renderDiagram() {
            var section = el('section', { className: 'gp-cdcf-section gp-cdcf-diagram-wrap' });
            section.appendChild(el('h3', null, [lang.diagramTitle]));
            var holder = el('div', { className: 'gp-cdcf-diagram' });
            section.appendChild(holder);
            Diagram.render(holder, opts.projetNom, data.interactors, data.fonctionsService);
            return section;
        }

        function renderFs() {
            var section = el('section', { className: 'gp-cdcf-section gp-cdcf-fs' });
            section.appendChild(el('h3', null, [lang.fsTitle]));
            section.appendChild(el('p', { className: 'gp-cdcf-norm' }, [lang.fsNorm]));
            data.fonctionsService.forEach(function(fs, idx) {
                section.appendChild(renderOneFs(fs, idx));
            });
            if (!locked) {
                section.appendChild(el('button', {
                    type: 'button', className: 'gp-cdcf-btn-add', text: '+ ' + lang.addFs,
                    onclick: function() {
                        data.fonctionsService.push({
                            id: nextId(data.fonctionsService),
                            description: '',
                            interactor1Id: data.interactors[0] ? data.interactors[0].id : 0,
                            interactor2Id: 0,
                            criteres: [{ id: 1, description: '', niveau: '', flexibilite: '' }],
                        });
                        emit();
                    },
                }));
            }
            return section;
        }

        function moveFs(idx, dir) {
            var t = idx + dir;
            if (t < 0 || t >= data.fonctionsService.length) { return; }
            var arr = data.fonctionsService;
            var tmp = arr[idx]; arr[idx] = arr[t]; arr[t] = tmp;
            emit();
        }

        function renderOneFs(fs, idx) {
            var card = el('div', { className: 'gp-cdcf-fs-card' });
            var head = el('div', { className: 'gp-cdcf-fs-head' });

            var up = el('button', { type: 'button', className: 'gp-cdcf-icon-btn', text: '▲',
                onclick: function() { moveFs(idx, -1); } });
            var down = el('button', { type: 'button', className: 'gp-cdcf-icon-btn', text: '▼',
                onclick: function() { moveFs(idx, 1); } });
            if (locked || idx === 0) { up.disabled = true; }
            if (locked || idx === data.fonctionsService.length - 1) { down.disabled = true; }

            var badge = el('span', { className: 'gp-cdcf-badge gp-cdcf-badge-fs', text: 'FS' + (idx + 1) });

            var desc = el('textarea', {
                className: 'gp-cdcf-fs-desc', rows: '2', placeholder: lang.fsDescPlaceholder,
                onchange: function(e) { fs.description = e.target.value; emit(); },
            });
            desc.value = fs.description;
            if (locked) { desc.disabled = true; }

            var sel1 = renderInteractorSelect(fs.interactor1Id, false, function(v) { fs.interactor1Id = v; emit(); });
            var sel2 = renderInteractorSelect(fs.interactor2Id, true, function(v) { fs.interactor2Id = v; emit(); });
            var del = el('button', {
                type: 'button', className: 'gp-cdcf-btn-remove', text: '🗑',
                onclick: function() {
                    data.fonctionsService = data.fonctionsService.filter(function(x) { return x.id !== fs.id; });
                    emit();
                },
            });
            if (locked || data.fonctionsService.length <= 1) { del.disabled = true; }

            head.appendChild(el('div', { className: 'gp-cdcf-fs-arrows' }, [up, down]));
            head.appendChild(badge);
            head.appendChild(el('div', { className: 'gp-cdcf-fs-desc-wrap' }, [
                el('label', null, [lang.fsDescLabel]), desc,
            ]));
            head.appendChild(el('div', { className: 'gp-cdcf-fs-selects' }, [
                el('label', null, [lang.fsInteractorsLabel]), sel1, sel2,
            ]));
            head.appendChild(del);
            card.appendChild(head);

            var critList = el('div', { className: 'gp-cdcf-criteres' });
            fs.criteres.forEach(function(crit) {
                critList.appendChild(renderCritere(fs, crit));
            });
            if (!locked) {
                critList.appendChild(el('button', {
                    type: 'button', className: 'gp-cdcf-btn-add-sm', text: '+ ' + lang.addCritere,
                    onclick: function() {
                        fs.criteres.push({ id: nextId(fs.criteres), description: '', niveau: '', flexibilite: '' });
                        emit();
                    },
                }));
            }
            card.appendChild(critList);
            return card;
        }

        function renderCritere(fs, crit) {
            var row = el('div', { className: 'gp-cdcf-critere gp-cdcf-flex-' + (crit.flexibilite || 'none') });
            var d = el('input', {
                type: 'text', value: crit.description, placeholder: lang.criterePlaceholder,
                onchange: function(e) { crit.description = e.target.value; emit(); },
            });
            var n = el('input', {
                type: 'text', value: crit.niveau, placeholder: lang.niveauPlaceholder,
                onchange: function(e) { crit.niveau = e.target.value; emit(); },
            });
            var f = el('select', { onchange: function(e) { crit.flexibilite = e.target.value; emit(); } });
            [['', lang.flexNone], ['F0', lang.flexF0], ['F1', lang.flexF1], ['F2', lang.flexF2], ['F3', lang.flexF3]]
                .forEach(function(p) {
                    var o = el('option', { value: p[0], text: p[1] });
                    if (crit.flexibilite === p[0]) { o.selected = true; }
                    f.appendChild(o);
                });
            var del = el('button', {
                type: 'button', className: 'gp-cdcf-btn-remove', text: '✕',
                onclick: function() {
                    fs.criteres = fs.criteres.filter(function(x) { return x.id !== crit.id; });
                    if (fs.criteres.length === 0) {
                        fs.criteres.push({ id: 1, description: '', niveau: '', flexibilite: '' });
                    }
                    emit();
                },
            });
            if (locked) { d.disabled = n.disabled = f.disabled = del.disabled = true; }
            row.appendChild(d); row.appendChild(n); row.appendChild(f); row.appendChild(del);
            return row;
        }

        function renderInteractorSelect(currentId, allowNone, cb) {
            var s = el('select', { onchange: function(e) { cb(parseInt(e.target.value, 10)); } });
            if (allowNone) {
                var none = el('option', { value: '0', text: lang.noneOption });
                if (currentId === 0) { none.selected = true; }
                s.appendChild(none);
            }
            data.interactors.forEach(function(inter) {
                var o = el('option', {
                    value: String(inter.id),
                    text: inter.name || ('Interacteur ' + inter.id),
                });
                if (inter.id === currentId) { o.selected = true; }
                s.appendChild(o);
            });
            if (locked) { s.disabled = true; }
            return s;
        }

        function renderContraintes() {
            var section = el('section', { className: 'gp-cdcf-section gp-cdcf-contraintes' });
            section.appendChild(el('h3', null, [lang.contraintesTitle]));
            section.appendChild(el('p', { className: 'gp-cdcf-norm' }, [lang.contraintesNorm]));
            data.contraintes.forEach(function(c, idx) {
                var d = el('input', {
                    type: 'text', value: c.description, placeholder: lang.contraintePlaceholder,
                    onchange: function(e) { c.description = e.target.value; emit(); },
                });
                var j = el('input', {
                    type: 'text', value: c.justification, placeholder: lang.justificationPlaceholder,
                    onchange: function(e) { c.justification = e.target.value; emit(); },
                });
                var sel = el('select', {
                    onchange: function(e) { c.linkedFsId = parseInt(e.target.value, 10); emit(); },
                });
                var none = el('option', { value: '0', text: lang.noFsLink });
                if (c.linkedFsId === 0) { none.selected = true; }
                sel.appendChild(none);
                data.fonctionsService.forEach(function(fs, fsidx) {
                    var label = 'FS' + (fsidx + 1) + (fs.description ? (' — ' + fs.description.substring(0, 30)) : '');
                    var o = el('option', { value: String(fs.id), text: label });
                    if (fs.id === c.linkedFsId) { o.selected = true; }
                    sel.appendChild(o);
                });
                var del = el('button', {
                    type: 'button', className: 'gp-cdcf-btn-remove', text: '🗑',
                    onclick: function() {
                        data.contraintes = data.contraintes.filter(function(x) { return x.id !== c.id; });
                        emit();
                    },
                });
                if (locked) { d.disabled = j.disabled = sel.disabled = del.disabled = true; }
                section.appendChild(el('div', { className: 'gp-cdcf-row' }, [
                    el('span', { className: 'gp-cdcf-badge gp-cdcf-badge-c', text: 'C' + (idx + 1) }),
                    d, j, sel, del,
                ]));
            });
            if (!locked) {
                section.appendChild(el('button', {
                    type: 'button', className: 'gp-cdcf-btn-add', text: '+ ' + lang.addContrainte,
                    onclick: function() {
                        data.contraintes.push({
                            id: nextId(data.contraintes), description: '', justification: '', linkedFsId: 0,
                        });
                        emit();
                    },
                }));
            }
            return section;
        }

        render();
        onChange(data);
    }

    return { init: init };
});
```

- [ ] **Step 2 : Build AMD**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet" && grunt amd 2>&1 | tail -10
```

- [ ] **Step 3 : Commit**

```bash
git add gestionprojet/amd/src/cdcf.js gestionprojet/amd/build/cdcf.min.js
git commit -m "feat(cdcf): AMD module rendering interactors/FS/criteres/contraintes"
```

---

## Task 8 : Réécrire `pages/step4.php` (élève)

**Files:**
- Rewrite: `gestionprojet/pages/step4.php`
- Create: `gestionprojet/amd/src/cdcf_bootstrap.js`

- [ ] **Step 1 : Garder la première moitié inchangée**

Conserver lignes 1-150 (auth, capabilities, $submission, $isLocked, $canSubmit, $canRevert, header, status, dates, bloc `step4_provided`).

- [ ] **Step 2 : Remplacer le form (lignes 244-309) et tout le `<script>` inline (lignes 316-710) par :**

```php
<?php
require_once($CFG->dirroot . '/mod/gestionprojet/classes/cdcf_helper.php');
$cdcfdata = \mod_gestionprojet\cdcf_helper::decode($submission->interacteurs_data ?? null);
$projetnom = format_string($gestionprojet->name);
?>
<form id="cdcfForm" method="post" action="" class="gp-cdcf-form">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    <input type="hidden" name="interacteurs_data" id="cdcfDataField"
        value="<?php echo s(json_encode($cdcfdata, JSON_UNESCAPED_UNICODE)); ?>">

    <div class="gp-cdcf-norm-block">
        <strong>NF EN 16271 :</strong>
        <?php echo get_string('step4_norm_intro', 'gestionprojet'); ?>
    </div>

    <div id="cdcfRoot" class="gp-cdcf-root"></div>

    <?php require_once(__DIR__ . '/student_ai_feedback_display.php'); ?>

    <div class="export-section">
        <?php if ($canSubmit): ?>
            <button type="button" class="btn btn-primary btn-lg btn-submit-large" id="submitButton">
                📤 <?php echo get_string('submit', 'gestionprojet'); ?>
            </button>
        <?php endif; ?>
        <?php if ($canRevert): ?>
            <button type="button" class="btn btn-warning" id="revertButton">
                ↩️ <?php echo get_string('revert_to_draft', 'gestionprojet'); ?>
            </button>
        <?php endif; ?>
    </div>
</form>

<?php
$langstrings = [
    'interactorsTitle'         => get_string('step4_interactors_title', 'gestionprojet'),
    'interactorsNorm'          => get_string('step4_interactors_norm', 'gestionprojet'),
    'interactorPlaceholder'    => get_string('step4_interactor_placeholder', 'gestionprojet'),
    'addInteractor'            => get_string('step4_add_interactor', 'gestionprojet'),
    'diagramTitle'             => get_string('step4_diagram_title', 'gestionprojet'),
    'fsTitle'                  => get_string('step4_fs_title', 'gestionprojet'),
    'fsNorm'                   => get_string('step4_fs_norm', 'gestionprojet'),
    'fsDescPlaceholder'        => get_string('step4_fs_desc_placeholder', 'gestionprojet'),
    'fsDescLabel'              => get_string('step4_fs_desc_label', 'gestionprojet'),
    'fsInteractorsLabel'       => get_string('step4_fs_interactors_label', 'gestionprojet'),
    'addFs'                    => get_string('step4_add_fs', 'gestionprojet'),
    'criterePlaceholder'       => get_string('step4_critere_placeholder', 'gestionprojet'),
    'niveauPlaceholder'        => get_string('step4_niveau_placeholder', 'gestionprojet'),
    'flexNone'                 => get_string('step4_flex_none', 'gestionprojet'),
    'flexF0'                   => get_string('step4_flex_f0', 'gestionprojet'),
    'flexF1'                   => get_string('step4_flex_f1', 'gestionprojet'),
    'flexF2'                   => get_string('step4_flex_f2', 'gestionprojet'),
    'flexF3'                   => get_string('step4_flex_f3', 'gestionprojet'),
    'addCritere'               => get_string('step4_add_critere', 'gestionprojet'),
    'noneOption'               => get_string('step4_none_option', 'gestionprojet'),
    'contraintesTitle'         => get_string('step4_contraintes_title', 'gestionprojet'),
    'contraintesNorm'          => get_string('step4_contraintes_norm', 'gestionprojet'),
    'contraintePlaceholder'    => get_string('step4_contrainte_placeholder', 'gestionprojet'),
    'justificationPlaceholder' => get_string('step4_justification_placeholder', 'gestionprojet'),
    'noFsLink'                 => get_string('step4_no_fs_link', 'gestionprojet'),
    'addContrainte'            => get_string('step4_add_contrainte', 'gestionprojet'),
];

$PAGE->requires->js_call_amd('mod_gestionprojet/cdcf_bootstrap', 'init', [[
    'cmid'          => $cm->id,
    'step'          => 4,
    'groupid'       => $groupid,
    'autosaveMs'    => (int)$gestionprojet->autosave_interval * 1000,
    'isLocked'      => $isLocked,
    'canSubmit'     => $canSubmit,
    'canRevert'     => $canRevert,
    'projetNom'     => $projetnom,
    'initial'       => $cdcfdata,
    'lang'          => $langstrings,
    'confirmSubmit' => get_string('confirm_submission', 'gestionprojet'),
    'confirmRevert' => get_string('confirm_revert', 'gestionprojet'),
]]);
```

- [ ] **Step 3 : Créer `cdcf_bootstrap.js`**

Glue minimaliste entre la page PHP et l'éditeur. Aucune utilisation d'`innerHTML`.

```javascript
/**
 * Bootstrap glue between step4.php and the cdcf editor.
 *
 * @module     mod_gestionprojet/cdcf_bootstrap
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'mod_gestionprojet/cdcf', 'mod_gestionprojet/autosave'],
function($, Ajax, Cdcf, Autosave) {
    'use strict';

    function init(cfg) {
        var dataField = document.getElementById('cdcfDataField');
        var root = document.getElementById('cdcfRoot');
        if (!root || !dataField) { return; }

        Cdcf.init({
            container: root,
            initialData: cfg.initial,
            lang: cfg.lang,
            projetNom: cfg.projetNom,
            isLocked: cfg.isLocked,
            onChange: function(data) {
                dataField.value = JSON.stringify(data);
            },
        });

        $('#submitButton').on('click', function() {
            if (!confirm(cfg.confirmSubmit)) { return; }
            Ajax.call([{ methodname: 'mod_gestionprojet_submit_step',
                args: { cmid: cfg.cmid, step: cfg.step, action: 'submit' } }])[0]
                .done(function(d) { if (d.success) { window.location.reload(); } });
        });

        $('#revertButton').on('click', function() {
            if (!confirm(cfg.confirmRevert)) { return; }
            Ajax.call([{ methodname: 'mod_gestionprojet_submit_step',
                args: { cmid: cfg.cmid, step: cfg.step, action: 'revert' } }])[0]
                .done(function(d) { if (d.success) { window.location.reload(); } });
        });

        if (!cfg.isLocked) {
            Autosave.init({
                cmid: cfg.cmid,
                step: cfg.step,
                groupid: cfg.groupid,
                interval: cfg.autosaveMs,
                formSelector: '#cdcfForm',
                serialize: function() {
                    return { interacteurs_data: dataField.value };
                },
            });
        }
    }

    return { init: init };
});
```

- [ ] **Step 4 : Build AMD + lint PHP**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet" && grunt amd 2>&1 | tail -10
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step4.php"
```

- [ ] **Step 5 : Commit**

```bash
git add gestionprojet/pages/step4.php gestionprojet/amd/src/cdcf_bootstrap.js gestionprojet/amd/build/cdcf_bootstrap.min.js
git commit -m "feat(step4): rewrite student CDCF view with norm-aligned editor"
```

---

## Task 9 : Réécrire `pages/step4_teacher.php` (modèle correction)

**Files:**
- Rewrite: `gestionprojet/pages/step4_teacher.php`

- [ ] **Step 1 : Lire l'existant**

```bash
sed -n '1,80p' "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step4_teacher.php"
```

- [ ] **Step 2 : Adapter la vue**

Garder l'auth, charger `$teachercdcf = $DB->get_record('gestionprojet_cdcf_teacher', ...)`, décoder via `cdcf_helper::decode($teachercdcf->interacteurs_data)`, instancier le même éditeur AMD via `cdcf_bootstrap`. Conserver le textarea `ai_instructions` et les champs `submission_date`/`deadline_date`.

Pour distinguer la cible d'autosave : la whitelist `gestionprojet_cdcf_teacher` est déjà sélectionnée par `ajax/autosave.php` selon le contexte appelant (cf. logique existante). Vérifier en lisant `autosave.php` quel champ identifie le contexte (probablement le path de la page ou un paramètre `mode`/`role`). Réutiliser le même mécanisme.

- [ ] **Step 3 : Lint + commit**

```bash
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step4_teacher.php"
git add gestionprojet/pages/step4_teacher.php
git commit -m "feat(step4_teacher): rewrite correction model view with norm-aligned editor"
```

---

## Task 10 : Réécrire `pages/step4_provided.php` (CDCF consigne)

**Files:**
- Rewrite: `gestionprojet/pages/step4_provided.php`

- [ ] **Step 1 : Identifier le mode (édition prof / lecture élève)**

Lire l'en-tête actuel pour comprendre le contrôle d'accès :
```bash
sed -n '1,80p' "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step4_provided.php"
```

- [ ] **Step 2 : Vue duale**

Si `has_capability('mod/gestionprojet:configureteacherpages', $context)` → mode édition (mêmes contrôles que step4_teacher mais sans `ai_instructions`). Sinon → `isLocked: true` (lecture seule pour les élèves quand `step4_provided` flag actif). Tout en utilisant le même éditeur AMD.

- [ ] **Step 3 : Lint + commit**

```bash
php -l "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step4_provided.php"
git add gestionprojet/pages/step4_provided.php
git commit -m "feat(step4_provided): rewrite consigne view with norm-aligned editor (read-only for students)"
```

---

## Task 11 : Strings de langue (en + fr)

**Files:**
- Modify: `gestionprojet/lang/en/gestionprojet.php`
- Modify: `gestionprojet/lang/fr/gestionprojet.php`

- [ ] **Step 1 : Ajouter les nouvelles strings (FR ci-dessous, traduire pour EN)**

```php
$string['step4_norm_intro'] = 'Document par lequel le demandeur exprime ses besoins en termes de fonctions de service et de contraintes. Pour chacune sont définis des critères d\'appréciation, leurs niveaux de performance et un degré de flexibilité.';
$string['step4_interactors_title'] = 'Interacteurs';
$string['step4_interactors_norm'] = 'Élément de l\'environnement du produit en interaction avec lui au cours de son cycle de vie.';
$string['step4_interactor_placeholder'] = 'Nom de l\'interacteur';
$string['step4_add_interactor'] = 'Ajouter un interacteur';
$string['step4_diagram_title'] = 'Diagramme des interacteurs';
$string['step4_fs_title'] = 'Fonctions de service (FS)';
$string['step4_fs_norm'] = 'Action demandée à un produit ou réalisée par lui afin de satisfaire une partie du besoin d\'un utilisateur donné.';
$string['step4_fs_desc_label'] = 'Énoncé';
$string['step4_fs_desc_placeholder'] = 'Commencez par un verbe d\'action à l\'infinitif';
$string['step4_fs_interactors_label'] = 'Interacteur(s)';
$string['step4_add_fs'] = 'Ajouter une FS';
$string['step4_critere_placeholder'] = 'Critère d\'appréciation';
$string['step4_niveau_placeholder'] = 'Niveau (avec unité)';
$string['step4_flex_none'] = 'Choisir…';
$string['step4_flex_f0'] = 'F0 Impératif';
$string['step4_flex_f1'] = 'F1 Peu négociable';
$string['step4_flex_f2'] = 'F2 Négociable';
$string['step4_flex_f3'] = 'F3 Très négociable';
$string['step4_add_critere'] = 'Ajouter un critère';
$string['step4_none_option'] = '— Aucun —';
$string['step4_contraintes_title'] = 'Contraintes';
$string['step4_contraintes_norm'] = 'Caractéristique, effet ou disposition de conception rendu obligatoire ou interdit pour une raison quelconque. Aucune autre possibilité n\'est laissée.';
$string['step4_contrainte_placeholder'] = 'Énoncé de la contrainte';
$string['step4_justification_placeholder'] = 'Justification';
$string['step4_no_fs_link'] = '— Aucune FS —';
$string['step4_add_contrainte'] = 'Ajouter une contrainte';
```

EN — équivalents :
```php
$string['step4_norm_intro'] = 'Document by which the requester expresses their needs in terms of service functions and constraints. For each, criteria of appreciation, performance levels and a degree of flexibility are defined.';
$string['step4_interactors_title'] = 'Interactors';
$string['step4_interactors_norm'] = 'Element of the product environment interacting with it throughout its lifecycle.';
$string['step4_interactor_placeholder'] = 'Interactor name';
$string['step4_add_interactor'] = 'Add an interactor';
$string['step4_diagram_title'] = 'Interactors diagram';
$string['step4_fs_title'] = 'Service Functions (SF)';
$string['step4_fs_norm'] = 'Action required from or performed by a product to satisfy part of a given user need.';
$string['step4_fs_desc_label'] = 'Statement';
$string['step4_fs_desc_placeholder'] = 'Start with an action verb in the infinitive';
$string['step4_fs_interactors_label'] = 'Interactor(s)';
$string['step4_add_fs'] = 'Add an SF';
$string['step4_critere_placeholder'] = 'Appreciation criterion';
$string['step4_niveau_placeholder'] = 'Level (with unit)';
$string['step4_flex_none'] = 'Choose…';
$string['step4_flex_f0'] = 'F0 Imperative';
$string['step4_flex_f1'] = 'F1 Slightly negotiable';
$string['step4_flex_f2'] = 'F2 Negotiable';
$string['step4_flex_f3'] = 'F3 Very negotiable';
$string['step4_add_critere'] = 'Add a criterion';
$string['step4_none_option'] = '— None —';
$string['step4_contraintes_title'] = 'Constraints';
$string['step4_contraintes_norm'] = 'Characteristic, effect or design disposition that is made mandatory or forbidden for any reason. No other possibility is left.';
$string['step4_contrainte_placeholder'] = 'Constraint statement';
$string['step4_justification_placeholder'] = 'Justification';
$string['step4_no_fs_link'] = '— No SF —';
$string['step4_add_contrainte'] = 'Add a constraint';
```

- [ ] **Step 2 : Retirer les strings devenues obsolètes**

Avant chaque suppression, vérifier l'absence d'usage :
```bash
grep -rn "'step4_fp_label'\|'step4_fc_label'\|'step4_fc_desc'\|'step4_fc_value_placeholder'\|'step4_unite_placeholder'\|'step4_add_fc'\|'step4_interactor_default'\|'step4_interactor_name_placeholder'\|'step4_interactors_section'\|'step4_product_fallback'\|'step4_produit_label'\|'step4_milieu_label'\|'step4_produit_placeholder'\|'step4_milieu_placeholder'\|'step4_fp_placeholder'\|'step4_fp_desc'\|'step4_desc_title'\|'step4_desc_text'" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/" --include="*.php" --include="*.mustache" --include="*.js"
```

Conserver `step4_provided_block_title` et `step4_provided_notice_student` si encore utilisés dans le bloc `step4_provided` de `step4.php`.

Pour chaque clé sans usage : supprimer dans EN et FR.

- [ ] **Step 3 : Commit**

```bash
git add gestionprojet/lang/en/gestionprojet.php gestionprojet/lang/fr/gestionprojet.php
git commit -m "feat(lang): strings for norm-aligned CDCF (FS, flexibilité, contraintes)"
```

---

## Task 12 : Styles CSS

**Files:**
- Modify: `gestionprojet/styles.css`

- [ ] **Step 1 : Ajouter le bloc CSS namespacé (à la fin du fichier)**

```css
/* === CDCF (NF EN 16271) — step 4 === */
.path-mod-gestionprojet .gp-cdcf-norm-block {
    background: #fef3c7; border: 1px solid #fde68a; border-radius: 6px;
    padding: 10px 14px; margin-bottom: 18px; font-size: 0.9em;
}
.path-mod-gestionprojet .gp-cdcf-section { margin-bottom: 24px; }
.path-mod-gestionprojet .gp-cdcf-section h3 { margin: 0 0 6px 0; }
.path-mod-gestionprojet .gp-cdcf-norm { font-size: 0.82em; color: #6b5e34;
    background: #fef9c3; padding: 8px 10px; border-radius: 4px; margin-bottom: 12px; }
.path-mod-gestionprojet .gp-cdcf-row { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
.path-mod-gestionprojet .gp-cdcf-row > input { flex: 1; }
.path-mod-gestionprojet .gp-cdcf-badge { display: inline-flex; align-items: center; justify-content: center;
    height: 28px; min-width: 36px; padding: 0 8px; border-radius: 14px;
    font-size: 0.85em; font-weight: 600; }
.path-mod-gestionprojet .gp-cdcf-badge-i { background: #dbeafe; color: #1e40af; }
.path-mod-gestionprojet .gp-cdcf-badge-fs { background: #fef3c7; color: #92400e; height: 32px; min-width: 44px; }
.path-mod-gestionprojet .gp-cdcf-badge-c { background: #fee2e2; color: #991b1b; }
.path-mod-gestionprojet .gp-cdcf-fs-card { border: 1px solid #e5e7eb; border-radius: 8px;
    padding: 12px; margin-bottom: 10px; }
.path-mod-gestionprojet .gp-cdcf-fs-head { display: flex; gap: 8px; align-items: flex-start; }
.path-mod-gestionprojet .gp-cdcf-fs-arrows { display: flex; flex-direction: column; gap: 2px; padding-top: 4px; }
.path-mod-gestionprojet .gp-cdcf-fs-desc-wrap { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.path-mod-gestionprojet .gp-cdcf-fs-desc { width: 100%; resize: vertical; }
.path-mod-gestionprojet .gp-cdcf-fs-selects { width: 220px; display: flex; flex-direction: column; gap: 4px; }
.path-mod-gestionprojet .gp-cdcf-criteres { margin-left: 50px; display: flex; flex-direction: column; gap: 4px; margin-top: 8px; }
.path-mod-gestionprojet .gp-cdcf-critere { display: flex; gap: 6px; align-items: center;
    padding: 6px 10px; border-radius: 6px; border: 1px solid #e5e7eb; background: #f9fafb; }
.path-mod-gestionprojet .gp-cdcf-critere > input:first-child { flex: 1; }
.path-mod-gestionprojet .gp-cdcf-critere > input:nth-child(2) { width: 160px; }
.path-mod-gestionprojet .gp-cdcf-critere > select { width: 140px; }
.path-mod-gestionprojet .gp-cdcf-flex-F0 { background: #fee2e2; border-color: #fca5a5; }
.path-mod-gestionprojet .gp-cdcf-flex-F1 { background: #fed7aa; border-color: #fdba74; }
.path-mod-gestionprojet .gp-cdcf-flex-F2 { background: #fef3c7; border-color: #fde68a; }
.path-mod-gestionprojet .gp-cdcf-flex-F3 { background: #d1fae5; border-color: #6ee7b7; }
.path-mod-gestionprojet .gp-cdcf-btn-add, .path-mod-gestionprojet .gp-cdcf-btn-add-sm {
    background: #2563eb; color: #fff; border: none; border-radius: 6px;
    padding: 6px 12px; cursor: pointer; font-size: 0.85em;
}
.path-mod-gestionprojet .gp-cdcf-btn-add-sm { padding: 4px 8px; font-size: 0.78em; background: #6b7280; }
.path-mod-gestionprojet .gp-cdcf-btn-remove { background: transparent; border: none;
    color: #ef4444; cursor: pointer; font-size: 1em; padding: 4px 8px; }
.path-mod-gestionprojet .gp-cdcf-btn-remove:disabled { color: #d1d5db; cursor: not-allowed; }
.path-mod-gestionprojet .gp-cdcf-icon-btn { background: transparent; border: none;
    color: #9ca3af; cursor: pointer; padding: 1px 4px; line-height: 1; }
.path-mod-gestionprojet .gp-cdcf-icon-btn:disabled { color: #e5e7eb; cursor: not-allowed; }
.path-mod-gestionprojet .gp-cdcf-diagram { display: flex; justify-content: center;
    background: #f9fafb; border-radius: 8px; padding: 12px; }
```

- [ ] **Step 2 : Vérifier l'absence de `<style>` inline dans les fichiers PHP touchés**

```bash
grep -l "<style" "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/pages/step4"*".php" 2>/dev/null
```
S'il y a des occurrences résiduelles, retirer (interdit par CLAUDE.md §2).

- [ ] **Step 3 : Commit**

```bash
git add gestionprojet/styles.css
git commit -m "feat(css): styles for CDCF norm-aligned editor (FS/critères/flexibilité)"
```

---

## Task 13 : Vérifier `submit_step.php`, `lib.php`, `privacy/provider.php`, `ai_response_parser.php`, `ai_evaluator.php`

**Files:**
- Verify: `gestionprojet/ajax/submit_step.php`
- Verify: `gestionprojet/lib.php`
- Modify if needed: `gestionprojet/classes/privacy/provider.php`
- Verify: `gestionprojet/classes/ai_response_parser.php`
- Verify: `gestionprojet/classes/ai_evaluator.php`

- [ ] **Step 1 : Recherche d'occurrences obsolètes**

```bash
grep -rn "'produit'\|'milieu'\|'fp'\b" \
    "/Volumes/DONNEES/Claude code/mod_gestionprojet/gestionprojet/" --include="*.php"
```

- [ ] **Step 2 : Pour chaque occurrence**

- Si dans `gestionprojet_delete_instance` (lib.php) : `delete_records` sur tables, pas sur colonnes → ne pas toucher.
- Si dans `provider.php` : retirer les colonnes des metadata `add_database_table`. Conserver `interacteurs_data`.
- Si dans `ai_response_parser.php` ou `ai_evaluator.php` : adapter ou supprimer (le parser ne devrait pas dépendre des noms de champs supprimés ; il interprète probablement la sortie JSON de l'IA).
- Si dans `submit_step.php` : adapter la validation step 4 pour vérifier la présence de `interacteurs_data` JSON valide.

- [ ] **Step 3 : Lint des fichiers modifiés et commit**

```bash
git add -p
git commit -m "chore(cdcf): cleanup deprecated produit/milieu/fp references"
```

---

## Task 14 : Test manuel en preprod (SCP + upgrade)

**Files:** déploiement uniquement (pas de modification de code).

- [ ] **Step 1 : Lire `TESTING.md` pour les credentials et chemins preprod**

```bash
sed -n '1,40p' "/Volumes/DONNEES/Claude code/mod_gestionprojet/TESTING.md"
```

- [ ] **Step 2 : SCP du plugin sur la preprod**

(Reprendre la commande exacte depuis `TESTING.md`.)

- [ ] **Step 3 : Lancer l'upgrade**

```bash
ssh <preprod> "php /chemin/moodle/admin/cli/upgrade.php --non-interactive"
ssh <preprod> "php /chemin/moodle/admin/cli/purge_caches.php"
```

- [ ] **Step 4 : Scénarios de test manuels**

Sur la preprod :
1. Ouvrir une activité existante avec un CDCF rempli en ancien format → vérifier que les FCs ont été migrées en FS et que `niveau` contient `niveau + unité` concaténés.
2. Créer un nouvel interacteur, une FS liée à 2 interacteurs, des critères avec différentes flexibilités → l'autosave persiste, le diagramme se met à jour.
3. Ajouter une contrainte liée à une FS, recharger la page → la liaison persiste.
4. Vérifier que la suppression d'un interacteur référencé par une FS est bloquée.
5. Côté enseignant : `step4_teacher.php` permet la même édition + `ai_instructions`.
6. Côté élève : `step4_provided.php` lecture seule.
7. Soumettre → IA déclenchée → vérifier que le prompt contient bien la nouvelle structure (interacteurs / FS / contraintes).

- [ ] **Step 5 : Mettre à jour `TESTING.md`**

Ajouter une section v2.7.0 avec les scénarios validés. Commit :

```bash
git add TESTING.md
git commit -m "docs(testing): manual scenarios for CDCF norm uniformization (v2.7.0)"
```

---

## Task 15 : Push de la branche

**Files:** git uniquement.

- [ ] **Step 1 : Vérifier que tout est commité**

```bash
cd "/Volumes/DONNEES/Claude code/mod_gestionprojet" && git status
```

- [ ] **Step 2 : Push de la branche**

```bash
git push -u origin feat/cdcf-norme-uniformisation
```

- [ ] **Step 3 : Demander à l'utilisateur s'il veut merger sur `main` puis pousser sur Forge EDU**

Ne pas merger sans confirmation explicite (cf. instruction globale CLAUDE.md sur les actions destructives / shared state).

---

## Self-Review

**Spec coverage :**
- 1) FP migrée vers une FS puis suppression des champs inutilisés → Tasks 1, 3 ✅
- 2) Conversion FC → FS → Task 1 (`migrate_legacy`), Task 3 (exécution upgrade) ✅
- 3) Disparition `unite`, fusion dans `niveau` + ajout `flexibilite` → Task 1 ✅
- 4) Ajout des contraintes → Tasks 1, 7, 11, 12 ✅
- 5) Tous les éléments liés au CDCF (élève, teacher, provided, IA, autosave, lang, css, privacy) → Tasks 4, 5, 8, 9, 10, 11, 12, 13 ✅
- 6) Adoption du diagramme du modèle → Task 6 ✅

**Placeholders :** aucun TODO résiduel dans les steps de code. Les vérifications « remplacer si présent » indiquent comment décider, pas un TBD.

**Type consistency :**
- JSON keys : `interactors`, `fonctionsService`, `contraintes`, `interactor1Id`, `interactor2Id`, `criteres`, `description`, `niveau`, `flexibilite`, `linkedFsId` → cohérents entre Tasks 1, 4, 6, 7, 8.
- `flexibilite` codes : `''`, `F0`, `F1`, `F2`, `F3` (constante `FLEXIBILITE_CODES`) cohérents avec `step4_flex_*` lang strings et classes CSS `gp-cdcf-flex-F0..F3`.
- AMD module names : `mod_gestionprojet/cdcf`, `mod_gestionprojet/cdcf_diagram`, `mod_gestionprojet/cdcf_bootstrap` cohérents entre Tasks 6, 7, 8.

---

## Execution

Plan complet et sauvegardé. Deux options d'exécution :

**1. Subagent-Driven (recommandé)** — un sous-agent frais par tâche avec review entre chaque tâche, itération rapide.

**2. Inline Execution** — exécuter les tâches dans la session courante avec checkpoints de review.

Confirme ton choix et je lance.
