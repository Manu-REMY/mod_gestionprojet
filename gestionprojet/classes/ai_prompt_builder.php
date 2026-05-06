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
 * AI Prompt Builder for Gestion de Projet.
 *
 * Constructs evaluation prompts for each step type.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gestionprojet/lib.php');

/**
 * Builds prompts for AI evaluation of student submissions.
 */
class ai_prompt_builder {

    /** @var array Step-specific field mappings */
    const STEP_FIELDS = [
        4 => ['interacteurs_data'],
        5 => ['nom_essai', 'objectif', 'fonction_service', 'niveaux_reussite', 'etapes_protocole',
              'materiel_outils', 'precautions', 'resultats_obtenus', 'observations_remarques', 'conclusion'],
        6 => ['titre_projet', 'auteurs', 'besoin_projet', 'besoins', 'imperatifs', 'solutions',
              'justification', 'realisation', 'difficultes', 'validation', 'ameliorations', 'bilan', 'perspectives'],
        7 => ['aqui', 'surquoi', 'dansquelbut'],
        8 => ['tasks_data'],
        9 => ['data_json'],
    ];

    /** @var array Step descriptions in French */
    const STEP_CONTEXT = [
        4 => 'Cahier des Charges Fonctionnel - Analyse fonctionnelle conforme NF EN 16271 (interacteurs, fonctions de service avec critères et flexibilité, contraintes)',
        5 => 'Fiche d\'Essai - Protocole expérimental avec objectifs, étapes, matériel et résultats',
        6 => 'Rapport de Projet - Synthèse complète du projet avec besoin, solutions, réalisation et bilan',
        7 => 'Expression du Besoin - Diagramme Bête à Cornes (À qui? Sur quoi? Dans quel but?)',
        8 => 'Carnet de Bord - Suivi chronologique des séances et tâches réalisées',
        9 => 'Diagramme FAST - Analyse fonctionnelle traduisant les fonctions de service en solutions techniques',
    ];

    /** @var array Step-specific evaluation criteria */
    const STEP_CRITERIA = [
        4 => [
            ['name' => 'Interacteurs', 'weight' => 4, 'description' => 'Les interacteurs sont pertinents et complets'],
            ['name' => 'Fonctions de service', 'weight' => 6, 'description' => 'Chaque FS exprime un service rendu, est rattachée à 1 ou 2 interacteurs et utilise un verbe d\'action à l\'infinitif'],
            ['name' => 'Critères', 'weight' => 4, 'description' => 'Chaque critère a un niveau quantifié et une flexibilité (F0–F3) cohérente'],
            ['name' => 'Contraintes', 'weight' => 3, 'description' => 'Les contraintes sont identifiées avec justification'],
            ['name' => 'Cohérence globale', 'weight' => 3, 'description' => 'L\'ensemble est cohérent avec la norme NF EN 16271'],
        ],
        5 => [
            ['name' => 'Objectif de l\'essai', 'weight' => 3, 'description' => 'L\'objectif est clairement défini'],
            ['name' => 'Protocole', 'weight' => 4, 'description' => 'Les étapes sont détaillées et reproductibles'],
            ['name' => 'Matériel', 'weight' => 2, 'description' => 'Le matériel nécessaire est listé'],
            ['name' => 'Sécurité', 'weight' => 3, 'description' => 'Les précautions de sécurité sont mentionnées'],
            ['name' => 'Résultats', 'weight' => 4, 'description' => 'Les résultats sont présentés clairement'],
            ['name' => 'Conclusion', 'weight' => 4, 'description' => 'La conclusion répond à l\'objectif initial'],
        ],
        6 => [
            ['name' => 'Présentation du besoin', 'weight' => 3, 'description' => 'Le besoin est clairement exprimé'],
            ['name' => 'Analyse des solutions', 'weight' => 3, 'description' => 'Les solutions sont analysées et justifiées'],
            ['name' => 'Description de la réalisation', 'weight' => 4, 'description' => 'La réalisation est bien décrite'],
            ['name' => 'Gestion des difficultés', 'weight' => 3, 'description' => 'Les difficultés et solutions sont mentionnées'],
            ['name' => 'Validation', 'weight' => 3, 'description' => 'La validation du projet est présentée'],
            ['name' => 'Bilan et perspectives', 'weight' => 4, 'description' => 'Le bilan est réflexif avec perspectives'],
        ],
        7 => [
            ['name' => 'À qui?', 'weight' => 7, 'description' => 'Le bénéficiaire est correctement identifié'],
            ['name' => 'Sur quoi?', 'weight' => 7, 'description' => 'L\'objet de l\'action est bien défini'],
            ['name' => 'Dans quel but?', 'weight' => 6, 'description' => 'La finalité est clairement exprimée'],
        ],
        8 => [
            ['name' => 'Régularité du suivi', 'weight' => 4, 'description' => 'Les séances sont régulièrement documentées'],
            ['name' => 'Description des tâches', 'weight' => 5, 'description' => 'Les tâches sont clairement décrites'],
            ['name' => 'Suivi de l\'avancement', 'weight' => 5, 'description' => 'L\'avancement est cohérent et réaliste'],
            ['name' => 'Réflexivité', 'weight' => 6, 'description' => 'Les remarques montrent une réflexion sur le travail'],
        ],
        9 => [
            ['name' => 'Décomposition fonctionnelle', 'weight' => 4, 'description' => 'Les fonctions techniques (FT) couvrent les fonctions de service du CDCF'],
            ['name' => 'Pertinence des FT', 'weight' => 4, 'description' => 'Les FT sont correctement formulées et clairement identifiées'],
            ['name' => 'Sous-fonctions', 'weight' => 3, 'description' => 'La scission en sous-fonctions, lorsque utilisée, est judicieuse'],
            ['name' => 'Solutions techniques', 'weight' => 5, 'description' => 'Les solutions techniques (ST) proposées sont concrètes et adaptées aux FT/sous-FT'],
            ['name' => 'Cohérence FP → FT → ST', 'weight' => 4, 'description' => 'L\'arborescence est cohérente : du « pourquoi » au « comment »'],
        ],
    ];

    /** @var array Field labels in French */
    const FIELD_LABELS = [
        'interacteurs_data' => 'Cahier des Charges Fonctionnel (interacteurs, fonctions de service, contraintes)',
        'nom_essai' => 'Nom de l\'essai',
        'objectif' => 'Objectif',
        'fonction_service' => 'Fonction de service testée',
        'niveaux_reussite' => 'Niveaux de réussite',
        'etapes_protocole' => 'Étapes du protocole',
        'materiel_outils' => 'Matériel et outils',
        'precautions' => 'Précautions de sécurité',
        'resultats_obtenus' => 'Résultats obtenus',
        'observations_remarques' => 'Observations et remarques',
        'conclusion' => 'Conclusion',
        'titre_projet' => 'Titre du projet',
        'auteurs' => 'Auteurs',
        'besoin_projet' => 'Besoin du projet',
        'besoins' => 'Besoins (modèle enseignant)',
        'imperatifs' => 'Impératifs',
        'solutions' => 'Solutions envisagées',
        'justification' => 'Justification du choix',
        'realisation' => 'Réalisation',
        'difficultes' => 'Difficultés rencontrées',
        'validation' => 'Validation',
        'ameliorations' => 'Améliorations possibles',
        'bilan' => 'Bilan',
        'perspectives' => 'Perspectives',
        'aqui' => 'À qui rend-il service?',
        'surquoi' => 'Sur quoi agit-il?',
        'dansquelbut' => 'Dans quel but?',
        'tasks_data' => 'Entrées du carnet de bord',
        'data_json' => 'Diagramme FAST',
    ];

    /**
     * Build the complete evaluation prompt.
     *
     * @param int $step Step number (4-9)
     * @param object $studentdata Student submission data
     * @param object $teachermodel Teacher correction model
     * @param string|null $teacherintro Optional teacher pedagogical intro text (HTML allowed, will be stripped)
     * @return array ['system' => string, 'user' => string]
     */
    public function build_prompt(int $step, object $studentdata, object $teachermodel, ?string $teacherintro = null): array {
        $systemprompt = $this->build_system_prompt($step, $teachermodel, $teacherintro);
        $userprompt = $this->build_user_prompt($step, $studentdata, $teachermodel);

        return [
            'system' => $systemprompt,
            'user' => $userprompt,
        ];
    }

    /**
     * Build the system prompt establishing AI role and context.
     *
     * @param int $step Step number
     * @param object $teachermodel Teacher correction model
     * @param string|null $teacherintro Optional teacher pedagogical intro text (HTML allowed, will be stripped)
     * @return string System prompt
     */
    public function build_system_prompt(int $step, object $teachermodel, ?string $teacherintro = null): string {
        $stepcontext = self::STEP_CONTEXT[$step] ?? 'Évaluation de production élève';
        $aiinstructions = $teachermodel->ai_instructions ?? '';
        $criteriatext = $this->build_criteria_text($step);

        $prompt = <<<PROMPT
Tu es un assistant d'évaluation pédagogique expert en technologie pour le collège et le lycée.

CONTEXTE DE L'ÉVALUATION:
- Étape: $stepcontext
- Barème: Note sur 20 points
- Public: Élèves de collège/lycée

CRITÈRES D'ÉVALUATION:
$criteriatext

INSTRUCTIONS GÉNÉRALES:
1. Compare objectivement la production de l'élève avec le modèle de correction fourni
2. Évalue chaque critère de manière indépendante
3. Attribue une note sur 20 proportionnelle aux critères remplis
4. Fournis un feedback constructif, bienveillant et pédagogique
5. Identifie les points forts et les axes d'amélioration

PROMPT;

        // Add teacher's pedagogical intro (visible to students) as additional context for the AI.
        if ($teacherintro !== null) {
            $cleanintro = trim(html_entity_decode(strip_tags($teacherintro)));
            if ($cleanintro !== '') {
                $prompt .= <<<PROMPT

CONTEXTE FOURNI PAR L'ENSEIGNANT (présentation aux élèves):
$cleanintro

PROMPT;
            }
        }

        // Add teacher-specific instructions if provided.
        if (!empty($aiinstructions)) {
            $prompt .= <<<PROMPT

INSTRUCTIONS SPÉCIFIQUES DU PROFESSEUR:
$aiinstructions

PROMPT;
        }

        $prompt .= <<<PROMPT

FORMAT DE RÉPONSE OBLIGATOIRE:
Tu DOIS répondre UNIQUEMENT avec un objet JSON valide respectant exactement cette structure:
{
  "grade": <nombre entre 0 et 20>,
  "max_grade": 20,
  "feedback": "<texte de feedback détaillé et bienveillant>",
  "criteria": [
    {"name": "<nom du critère>", "score": <score>, "max": <max>, "comment": "<commentaire>"}
  ],
  "keywords_found": ["<mot-clé trouvé>"],
  "keywords_missing": ["<mot-clé attendu mais absent>"],
  "suggestions": ["<suggestion d'amélioration>"],
  "confidence": <nombre entre 0 et 1>
}

IMPORTANT:
- La réponse doit être UNIQUEMENT du JSON valide, sans texte avant ou après
- Sois juste mais bienveillant dans ton évaluation
- Les erreurs mineures ne doivent pas pénaliser lourdement
- Valorise les efforts et les bonnes idées de l'élève
PROMPT;

        return $prompt;
    }

    /**
     * Build the user prompt with student and teacher data.
     *
     * @param int $step Step number
     * @param object $studentdata Student submission
     * @param object $teachermodel Teacher correction model
     * @return string User prompt
     */
    public function build_user_prompt(int $step, object $studentdata, object $teachermodel): string {
        $studenttext = $this->format_student_data($step, $studentdata);
        $teachertext = $this->format_teacher_model($step, $teachermodel);

        return <<<PROMPT
## MODÈLE DE CORRECTION (Ce que l'enseignant attend):

$teachertext

---

## PRODUCTION DE L'ÉLÈVE (À évaluer):

$studenttext

---

Évalue cette production en comparant avec le modèle de correction. Réponds UNIQUEMENT avec le JSON demandé.
PROMPT;
    }

    /**
     * Format student data for prompt inclusion.
     *
     * @param int $step Step number
     * @param object $data Student submission data
     * @return string Formatted text
     */
    public function format_student_data(int $step, object $data): string {
        $fields = self::STEP_FIELDS[$step] ?? [];
        $output = [];

        foreach ($fields as $field) {
            $label = self::FIELD_LABELS[$field] ?? $field;
            $value = $this->get_field_value($data, $field);

            if (!empty($value)) {
                $output[] = "**$label:**\n$value";
            } else {
                $output[] = "**$label:**\n(Non renseigné)";
            }
        }

        return implode("\n\n", $output);
    }

    /**
     * Format teacher model for prompt inclusion.
     *
     * @param int $step Step number
     * @param object $model Teacher correction model
     * @return string Formatted text
     */
    public function format_teacher_model(int $step, object $model): string {
        $fields = self::STEP_FIELDS[$step] ?? [];
        $output = [];

        foreach ($fields as $field) {
            $label = self::FIELD_LABELS[$field] ?? $field;
            $value = $this->get_field_value($model, $field);

            if (!empty($value)) {
                $output[] = "**$label:**\n$value";
            }
        }

        if (empty($output)) {
            return "(Modèle de correction non renseigné - évaluer selon les critères généraux)";
        }

        return implode("\n\n", $output);
    }

    /**
     * Get field value, handling JSON fields specially.
     *
     * @param object $data Data object
     * @param string $field Field name
     * @return string Field value as text
     */
    private function get_field_value(object $data, string $field): string {
        if (!isset($data->$field)) {
            return '';
        }

        $value = $data->$field;

        // Handle JSON fields.
        if ($field === 'interacteurs_data') {
            return $this->format_interacteurs($value);
        }
        if ($field === 'tasks_data') {
            return $this->format_tasks($value);
        }
        if ($field === 'data_json') {
            return gestionprojet_fast_to_text($value);
        }
        if ($field === 'precautions') {
            return $this->format_json_array($value);
        }
        if ($field === 'auteurs') {
            return $this->format_json_array($value);
        }

        return trim((string) $value);
    }

    /**
     * Build the formatted text of evaluation criteria for a given step.
     *
     * @param int $step Step number
     * @return string Bullet list of criteria lines, or empty string if none
     */
    private function build_criteria_text(int $step): string {
        $criteria = self::STEP_CRITERIA[$step] ?? [];
        $text = '';
        foreach ($criteria as $criterion) {
            $text .= "- {$criterion['name']} (poids: {$criterion['weight']}/20): {$criterion['description']}\n";
        }
        return $text;
    }

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

        // Map persisted FS id → human-visible "FS<n>" label so contraintes can reference
        // the same label the FS section prints, regardless of insertion order or deletes.
        $fslabelbyid = [];
        foreach ($data['fonctionsService'] as $idx => $fs) {
            $fslabelbyid[$fs['id']] = 'FS' . ($idx + 1);
        }

        if (!empty($data['fonctionsService'])) {
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
        }

        if (!empty($data['contraintes'])) {
            $out[] = '';
            $out[] = 'CONTRAINTES :';
            foreach ($data['contraintes'] as $cidx => $c) {
                $linked = '';
                if ($c['linkedFsId'] > 0 && isset($fslabelbyid[$c['linkedFsId']])) {
                    $linked = ' (liée à ' . $fslabelbyid[$c['linkedFsId']] . ')';
                }
                $out[] = sprintf('  • C%d : %s%s | Justification : %s',
                    $cidx + 1,
                    $c['description'] !== '' ? $c['description'] : '(énoncé manquant)',
                    $linked,
                    $c['justification'] !== '' ? $c['justification'] : '(non précisée)');
            }
        }

        return implode("\n", $out);
    }

    /**
     * Format tasks data for display.
     *
     * @param string|null $json JSON string
     * @return string Formatted text
     */
    private function format_tasks(?string $json): string {
        if (empty($json)) {
            return '';
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return '';
        }

        $output = [];
        foreach ($data as $index => $task) {
            $date = $task['date'] ?? '';
            $description = $task['description'] ?? $task['task'] ?? '';
            $status = $task['status'] ?? '';
            $remarks = $task['remarks'] ?? $task['remarques'] ?? '';

            $line = "• Séance " . ($index + 1);
            if ($date) {
                $line .= " ($date)";
            }
            $output[] = $line;

            if ($description) {
                $output[] = "  Tâche: $description";
            }
            if ($status) {
                $output[] = "  Statut: $status";
            }
            if ($remarks) {
                $output[] = "  Remarques: $remarks";
            }
        }

        return implode("\n", $output);
    }

    /**
     * Format a JSON array as a bullet list.
     *
     * @param string|null $json JSON string
     * @return string Formatted text
     */
    private function format_json_array(?string $json): string {
        if (empty($json)) {
            return '';
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $json; // Return as-is if not valid JSON.
        }

        $output = [];
        foreach ($data as $item) {
            if (is_string($item) && !empty(trim($item))) {
                $output[] = "• " . trim($item);
            }
        }

        return implode("\n", $output);
    }

    /**
     * Build a meta-prompt asking the AI to produce correction instructions
     * tailored to a teacher correction model.
     *
     * @param int $step Step number (4-9)
     * @param object $teachermodel Teacher correction model fields
     * @return array ['system' => string, 'user' => string]
     */
    public function build_meta_prompt(int $step, object $teachermodel): array {
        $stepcontext = self::STEP_CONTEXT[$step] ?? 'Évaluation de production élève';
        $criteriatext = $this->build_criteria_text($step);
        $modeltext = $this->format_teacher_model($step, $teachermodel);

        $system = <<<PROMPT
Tu es un expert pédagogique en technologie. Ta mission est de rédiger des instructions de correction destinées à un autre IA correcteur, qui évaluera des productions d'élèves de collège/lycée.

Les instructions que tu produis doivent :
- Être en français, à la 2e personne du singulier ("tu")
- Préciser les points d'attention spécifiques au modèle fourni
- Indiquer les éléments obligatoires, les bonus, les pénalités éventuelles
- Rester concises (8-15 lignes max)
- Être réutilisables tel quel par l'IA correctrice

Réponds UNIQUEMENT avec le texte des instructions, sans préambule ni balisage Markdown.
PROMPT;

        $user = <<<PROMPT
Voici le modèle de correction rempli par l'enseignant pour l'étape "{$stepcontext}".

Critères officiels d'évaluation :
{$criteriatext}
Modèle rempli :
{$modeltext}

Rédige maintenant les instructions de correction adaptées à ce modèle précis.
PROMPT;

        return ['system' => $system, 'user' => $user];
    }
}
