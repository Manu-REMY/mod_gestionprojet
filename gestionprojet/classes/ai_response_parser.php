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
 * AI Response Parser for Project Management.
 *
 * Parses and validates AI evaluation responses.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Parses AI responses and extracts evaluation data.
 */
class ai_response_parser {

    /** @var float Minimum valid grade */
    const MIN_GRADE = 0.0;

    /** @var float Maximum valid grade */
    const MAX_GRADE = 20.0;

    /**
     * Parse AI response content.
     *
     * @param string $content Raw response content
     * @return object Parsed evaluation data
     * @throws \Exception If parsing fails
     */
    public function parse(string $content): object {
        // Try to extract JSON from the response.
        $json = $this->extract_json($content);

        if ($json === null) {
            throw new \Exception(get_string('ai_parse_error', 'gestionprojet'));
        }

        // Validate and normalize the parsed data.
        return $this->normalize($json);
    }

    /**
     * Extract JSON from response content.
     *
     * The AI might include extra text before/after the JSON.
     *
     * @param string $content Raw content
     * @return array|null Parsed JSON or null
     */
    private function extract_json(string $content): ?array {
        $content = trim($content);

        // First, try direct JSON parsing.
        $data = json_decode($content, true);
        if ($data !== null && is_array($data)) {
            return $data;
        }

        // Try to find JSON object in the content.
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $data = json_decode($matches[0], true);
            if ($data !== null && is_array($data)) {
                return $data;
            }
        }

        // Try to find JSON after markdown code block.
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $content, $matches)) {
            $data = json_decode($matches[1], true);
            if ($data !== null && is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Normalize and validate parsed data.
     *
     * @param array $data Raw parsed data
     * @return object Normalized evaluation object
     */
    private function normalize(array $data): object {
        $result = new \stdClass();

        // Grade (required, validated).
        $result->grade = $this->validate_grade($data['grade'] ?? null);

        // Max grade.
        $result->max_grade = (float) ($data['max_grade'] ?? self::MAX_GRADE);

        // Feedback (required).
        $result->feedback = $this->sanitize_text($data['feedback'] ?? '');
        if (empty($result->feedback)) {
            $result->feedback = get_string('ai_no_feedback', 'gestionprojet');
        }

        // Criteria (array of scoring details).
        $result->criteria = $this->normalize_criteria($data['criteria'] ?? []);

        // Keywords found/missing.
        $result->keywords_found = $this->normalize_string_array($data['keywords_found'] ?? []);
        $result->keywords_missing = $this->normalize_string_array($data['keywords_missing'] ?? []);

        // Suggestions.
        $result->suggestions = $this->normalize_string_array($data['suggestions'] ?? []);

        // Confidence score.
        $result->confidence = $this->validate_confidence($data['confidence'] ?? 0.5);

        return $result;
    }

    /**
     * Validate and clamp grade value.
     *
     * @param mixed $grade Raw grade value
     * @return float Valid grade
     */
    private function validate_grade($grade): float {
        if ($grade === null || $grade === '') {
            return 10.0; // Default to middle value if missing.
        }

        $grade = (float) $grade;

        // Clamp to valid range.
        return max(self::MIN_GRADE, min(self::MAX_GRADE, $grade));
    }

    /**
     * Validate confidence score.
     *
     * @param mixed $confidence Raw confidence
     * @return float Valid confidence (0-1)
     */
    private function validate_confidence($confidence): float {
        $confidence = (float) $confidence;
        return max(0.0, min(1.0, $confidence));
    }

    /**
     * Normalize criteria array.
     *
     * @param mixed $criteria Raw criteria
     * @return array Normalized criteria
     */
    private function normalize_criteria($criteria): array {
        if (!is_array($criteria)) {
            return [];
        }

        $normalized = [];
        foreach ($criteria as $criterion) {
            if (!is_array($criterion)) {
                continue;
            }

            $normalized[] = [
                'name' => $this->sanitize_text($criterion['name'] ?? 'Critère'),
                'score' => (float) ($criterion['score'] ?? 0),
                'max' => (float) ($criterion['max'] ?? 5),
                'comment' => $this->sanitize_text($criterion['comment'] ?? ''),
            ];
        }

        return $normalized;
    }

    /**
     * Normalize an array of strings.
     *
     * @param mixed $array Raw array
     * @return array Normalized string array
     */
    private function normalize_string_array($array): array {
        if (!is_array($array)) {
            return [];
        }

        $normalized = [];
        foreach ($array as $item) {
            $text = $this->sanitize_text((string) $item);
            if (!empty($text)) {
                $normalized[] = $text;
            }
        }

        return $normalized;
    }

    /**
     * Sanitize text for storage.
     *
     * @param string $text Raw text
     * @return string Sanitized text
     */
    private function sanitize_text(string $text): string {
        // Remove potentially dangerous content but keep formatting.
        $text = strip_tags($text, '<br><p><ul><ol><li><strong><em><b><i>');

        // Normalize whitespace.
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Calculate grade from criteria scores.
     *
     * Alternative method if AI doesn't provide a direct grade.
     *
     * @param array $criteria Criteria array
     * @return float Calculated grade
     */
    public function calculate_grade_from_criteria(array $criteria): float {
        if (empty($criteria)) {
            return 0.0;
        }

        $totalscore = 0;
        $totalmax = 0;

        foreach ($criteria as $criterion) {
            $score = $criterion['score'] ?? 0;
            $max = $criterion['max'] ?? 5;

            $totalscore += $score;
            $totalmax += $max;
        }

        if ($totalmax === 0) {
            return 0.0;
        }

        // Scale to 20.
        return round(($totalscore / $totalmax) * self::MAX_GRADE, 2);
    }

    /**
     * Format parsed result for display.
     *
     * @param object $result Parsed result
     * @return string HTML formatted output
     */
    public function format_for_display(object $result): string {
        $html = '<div class="ai-evaluation-result">';

        // Grade.
        $gradeclass = $result->grade >= 10 ? 'text-success' : 'text-danger';
        $html .= '<div class="ai-grade mb-3">';
        $html .= '<strong>' . get_string('ai_grade', 'gestionprojet') . ':</strong> ';
        $html .= '<span class="' . $gradeclass . ' h4">' . number_format($result->grade, 1) . '/' . $result->max_grade . '</span>';
        $html .= '</div>';

        // Feedback.
        $html .= '<div class="ai-feedback mb-3">';
        $html .= '<strong>' . get_string('ai_feedback', 'gestionprojet') . ':</strong>';
        $html .= '<p class="mb-0">' . nl2br(s($result->feedback)) . '</p>';
        $html .= '</div>';

        // Criteria.
        if (!empty($result->criteria)) {
            $html .= '<div class="ai-criteria mb-3">';
            $html .= '<strong>' . get_string('ai_criteria', 'gestionprojet') . ':</strong>';
            $html .= '<ul class="list-unstyled">';
            foreach ($result->criteria as $criterion) {
                $scoreclass = $criterion['score'] >= ($criterion['max'] / 2) ? 'text-success' : 'text-warning';
                $html .= '<li>';
                $html .= '<span class="' . $scoreclass . '">' . s($criterion['name']) . ': ';
                $html .= $criterion['score'] . '/' . $criterion['max'] . '</span>';
                if (!empty($criterion['comment'])) {
                    $html .= ' - <small class="text-muted">' . s($criterion['comment']) . '</small>';
                }
                $html .= '</li>';
            }
            $html .= '</ul></div>';
        }

        // Keywords found.
        if (!empty($result->keywords_found)) {
            $html .= '<div class="ai-keywords-found mb-2">';
            $html .= '<strong class="text-success">' . get_string('ai_keywords_found', 'gestionprojet') . ':</strong> ';
            $html .= implode(', ', array_map('s', $result->keywords_found));
            $html .= '</div>';
        }

        // Keywords missing.
        if (!empty($result->keywords_missing)) {
            $html .= '<div class="ai-keywords-missing mb-2">';
            $html .= '<strong class="text-warning">' . get_string('ai_keywords_missing', 'gestionprojet') . ':</strong> ';
            $html .= implode(', ', array_map('s', $result->keywords_missing));
            $html .= '</div>';
        }

        // Suggestions.
        if (!empty($result->suggestions)) {
            $html .= '<div class="ai-suggestions mb-2">';
            $html .= '<strong>' . get_string('ai_suggestions', 'gestionprojet') . ':</strong>';
            $html .= '<ul>';
            foreach ($result->suggestions as $suggestion) {
                $html .= '<li>' . s($suggestion) . '</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '</div>';

        return $html;
    }
}
