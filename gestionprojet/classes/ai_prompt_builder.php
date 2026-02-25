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

/**
 * Builds prompts for AI evaluation of student submissions.
 */
class ai_prompt_builder {

    /** @var array Step-specific field mappings */
    const STEP_FIELDS = [
        4 => ['produit', 'milieu', 'fp', 'interacteurs_data'],
        5 => ['nom_essai', 'objectif', 'fonction_service', 'niveaux_reussite', 'etapes_protocole',
              'materiel_outils', 'precautions', 'resultats_obtenus', 'observations_remarques', 'conclusion'],
        6 => ['titre_projet', 'auteurs', 'besoin_projet', 'imperatifs', 'solutions',
              'justification', 'realisation', 'difficultes', 'validation', 'ameliorations', 'bilan', 'perspectives'],
        7 => ['aqui', 'surquoi', 'dansquelbut'],
        8 => ['tasks_data'],
    ];

    /** @var array Step descriptions in French */
    const STEP_CONTEXT = [
        4 => 'Cahier des Charges Fonctionnel - Analyse fonctionnelle avec diagramme des interacteurs et fonctions contraintes',
        5 => 'Fiche d\'Essai - Protocole expérimental avec objectifs, étapes, matériel et résultats',
        6 => 'Rapport de Projet - Synthèse complète du projet avec besoin, solutions, réalisation et bilan',
        7 => 'Expression du Besoin - Diagramme Bête à Cornes (À qui? Sur quoi? Dans quel but?)',
        8 => 'Carnet de Bord - Suivi chronologique des séances et tâches réalisées',
    ];

    /** @var array Step-specific evaluation criteria */
    const STEP_CRITERIA = [
        4 => [
            ['name' => 'Identification du produit', 'weight' => 2, 'description' => 'Le produit est clairement identifié et décrit'],
            ['name' => 'Identification du milieu', 'weight' => 2, 'description' => 'Le milieu environnant est bien défini'],
            ['name' => 'Fonction principale', 'weight' => 3, 'description' => 'La FP est correctement formulée (verbe infinitif + COD)'],
            ['name' => 'Interacteurs', 'weight' => 4, 'description' => 'Les interacteurs sont pertinents et complets'],
            ['name' => 'Fonctions contraintes', 'weight' => 5, 'description' => 'Les FC sont bien formulées avec critères et niveaux'],
            ['name' => 'Cohérence globale', 'weight' => 4, 'description' => 'L\'ensemble est cohérent et bien structuré'],
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
    ];

    /** @var array Field labels in French */
    const FIELD_LABELS = [
        'produit' => 'Produit',
        'milieu' => 'Milieu environnant',
        'fp' => 'Fonction Principale',
        'interacteurs_data' => 'Interacteurs et Fonctions Contraintes',
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
    ];

    /**
     * Build the complete evaluation prompt.
     *
     * @param int $step Step number (4-8)
     * @param object $studentdata Student submission data
     * @param object $teachermodel Teacher correction model
     * @return array ['system' => string, 'user' => string]
     */
    public function build_prompt(int $step, object $studentdata, object $teachermodel): array {
        $systemprompt = $this->build_system_prompt($step, $teachermodel);
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
     * @return string System prompt
     */
    public function build_system_prompt(int $step, object $teachermodel): string {
        $stepcontext = self::STEP_CONTEXT[$step] ?? 'Évaluation de production élève';
        $criteria = self::STEP_CRITERIA[$step] ?? [];
        $aiinstructions = $teachermodel->ai_instructions ?? '';

        $criteriatext = '';
        foreach ($criteria as $criterion) {
            $criteriatext .= "- {$criterion['name']} (poids: {$criterion['weight']}/20): {$criterion['description']}\n";
        }

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
        if ($field === 'precautions') {
            return $this->format_json_array($value);
        }
        if ($field === 'auteurs') {
            return $this->format_json_array($value);
        }

        return trim((string) $value);
    }

    /**
     * Format interacteurs data for display.
     *
     * @param string|null $json JSON string
     * @return string Formatted text
     */
    private function format_interacteurs(?string $json): string {
        if (empty($json)) {
            return '';
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return '';
        }

        $output = [];
        foreach ($data as $index => $interacteur) {
            $name = $interacteur['name'] ?? 'Interacteur ' . ($index + 1);
            $output[] = "• Interacteur: $name";

            if (!empty($interacteur['fcs']) && is_array($interacteur['fcs'])) {
                foreach ($interacteur['fcs'] as $fcindex => $fc) {
                    $fcname = $fc['name'] ?? 'FC' . ($fcindex + 1);
                    $output[] = "  - FC: $fcname";

                    if (!empty($fc['criteres']) && is_array($fc['criteres'])) {
                        foreach ($fc['criteres'] as $critere) {
                            $cname = $critere['name'] ?? '';
                            $niveau = $critere['niveau'] ?? '';
                            $flexibilite = $critere['flexibilite'] ?? '';
                            if ($cname) {
                                $output[] = "    * Critère: $cname | Niveau: $niveau | Flexibilité: $flexibilite";
                            }
                        }
                    }
                }
            }
        }

        return implode("\n", $output);
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
}
