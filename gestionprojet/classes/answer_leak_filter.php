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
 * Keeps the answer key out of the feedback shown to students.
 *
 * Adapted from mod_redaction (notes_sujets::sans_mots_du_corrige).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Pure filter, without Moodle calls so that it can be checked alone.
 */
class answer_leak_filter {

    /**
     * @var array Roots that never give an answer away: common French words of a feedback
     * and the vocabulary of the project steps (CDCF, test, report...).
     */
    const COMMON_ROOTS = [
        // Common words.
        'alors', 'atten', 'aussi', 'autre', 'avec', 'avoir', 'bien', 'bonne', 'cela', 'cette', 'chaqu', 'comme', 'dans',
        'doit', 'doive', 'ensem', 'entre', 'encor', 'etre', 'faire', 'fait', 'faut', 'leurs', 'mais', 'meme', 'notre',
        'peut', 'plus', 'pour', 'quell', 'quels', 'sans', 'sont', 'tout', 'toute', 'tres', 'votre',
        // Words of a feedback.
        'ajout', 'ameli', 'clair', 'compl', 'corre', 'effor', 'eleme', 'exact', 'ident', 'juste', 'manqu', 'oubli',
        'organ', 'parti', 'pense', 'point', 'preci', 'propo', 'reali', 'repon', 'reuss', 'serie', 'trava',
        // Vocabulary of the steps.
        'besoi', 'cahie', 'charg', 'contr', 'crite', 'descr', 'diagr', 'envir', 'essai', 'exige', 'flexi', 'fonct',
        'inter', 'justi', 'lien', 'milie', 'nivea', 'perme', 'pieuv', 'produ', 'proje', 'proto', 'rappo', 'relie',
        'resul', 'servi', 'syste', 'table', 'unite', 'usage', 'utili', 'valeu',
    ];

    /**
     * Plain text of stored data: the string values of arrays, objects and JSON strings, not their keys.
     *
     * @param mixed $value
     * @return string
     */
    public static function text_of($value): string {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return self::text_of($decoded);
            }
            return strip_tags($value);
        }
        if (is_array($value) || is_object($value)) {
            $parts = [];
            foreach ((array) $value as $item) {
                $parts[] = self::text_of($item);
            }
            return implode(' ', array_filter($parts, 'strlen'));
        }
        return '';
    }

    /**
     * Content of a record (submission, correction model, provided instructions): its content columns only.
     * Ids and dates would add stray numbers. The stored feedback and grade of a submission come from the AI
     * or the teacher: counting them as student text would permit every leaked word.
     *
     * @param object|array $submission Record.
     * @return string
     */
    public static function content_text($submission): string {
        $fields = (array) $submission;
        foreach (array_keys($fields) as $name) {
            if (in_array($name, ['id', 'gestionprojetid', 'groupid', 'userid', 'status', 'locked'], true)
                    || preg_match('/feedback|grade|^time|_date$|_by$|_at$/', $name)) {
                unset($fields[$name]);
            }
        }
        return self::text_of($fields);
    }

    /**
     * Roots of the answer key that the student did not write and that are not common words.
     *
     * @param string $answerkey Text (or JSON) of the correction model.
     * @param string $permitted Text (or JSON) the student may see quoted back: their work, the instructions.
     * @return string[]
     */
    public static function forbidden_roots(string $answerkey, string $permitted): array {
        return array_values(array_diff(
            self::roots(self::text_of($answerkey)),
            self::roots(self::text_of($permitted)),
            self::COMMON_ROOTS
        ));
    }

    /**
     * Removes each sentence holding a forbidden root, whatever its wording.
     *
     * A harmless sentence may go too: a sentence giving the answer is what must not stay.
     *
     * @param string $comment
     * @param string[] $forbidden From forbidden_roots().
     * @return string
     */
    public static function filter(string $comment, array $forbidden): string {
        if (!$forbidden) {
            return trim($comment);
        }
        $kept = [];
        foreach (preg_split('/(?<=[.!?…])\s+/u', trim($comment)) as $sentence) {
            if (!array_intersect(self::roots($sentence), $forbidden)) {
                $kept[] = $sentence;
            }
        }
        return trim(implode(' ', $kept));
    }

    /**
     * Parts of an AI evaluation as a student may see them.
     *
     * The stored feedback is filtered too when the teacher edited it: a small edit must not let a leak through.
     *
     * @param string $feedback Feedback stored on the submission.
     * @param array $criteria Criteria (name, score, max, comment).
     * @param array $suggestions Suggestions (strings).
     * @param string[] $forbidden From forbidden_roots().
     * @return array ['feedback' => string, 'criteria' => array, 'suggestions' => string[]]
     */
    public static function student_view(string $feedback, array $criteria, array $suggestions, array $forbidden): array {
        $feedback = self::filter($feedback, $forbidden);
        foreach ($criteria as $i => $criterion) {
            if (is_array($criterion) && isset($criterion['comment'])) {
                $criteria[$i]['comment'] = self::filter((string) $criterion['comment'], $forbidden);
            }
        }
        $kept = [];
        foreach ($suggestions as $suggestion) {
            $clean = self::filter((string) $suggestion, $forbidden);
            if ($clean !== '') {
                $kept[] = $clean;
            }
        }
        return ['feedback' => $feedback, 'criteria' => $criteria, 'suggestions' => $kept];
    }

    /**
     * Roots of a text: the first five letters of each word of at least four letters, without case or accent
     * ("Rayons" and "rayon" are the same word), and each standalone number ("50 cm" and "50cm" give "50", "1 000" gives "1000"; "FS2" gives nothing).
     *
     * @param string $text
     * @return string[]
     */
    public static function roots(string $text): array {
        $simple = mb_strtolower($text);
        if (class_exists('Normalizer')) {
            $simple = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($simple, \Normalizer::FORM_D));
        }
        $simple = strtr($simple, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e',
            'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'œ' => 'oe']);
        preg_match_all('/[a-z]{4,}/', $simple, $words);
        // Same number whatever its writing: "1 000" (spaces, non-breaking spaces) is "1000", "50cm" holds "50".
        $simple = preg_replace('/(?<=\d)[\s\x{00A0}\x{202F}](?=\d{3}(?!\d))/u', '', $simple);
        preg_match_all('/(?<![a-z0-9])\d+(?:[.,]\d+)?/', $simple, $numbers);
        $roots = array_map(fn($w) => substr($w, 0, 5), $words[0]);
        $nums = array_map(fn($n) => str_replace(',', '.', $n), $numbers[0]);
        return array_values(array_unique(array_merge($roots, $nums)));
    }
}
