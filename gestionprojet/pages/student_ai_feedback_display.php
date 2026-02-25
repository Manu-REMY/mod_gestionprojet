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
 * Shared evaluation feedback display for student pages.
 * This shows evaluation results to students including:
 * - Grade
 * - Feedback
 * - Evaluation criteria with scores (if enabled by teacher)
 * - Elements found (if enabled by teacher)
 * - Missing elements (if enabled by teacher)
 * - Suggestions for improvement (if enabled by teacher)
 *
 * Required variables before including this file:
 * - $submission: The student submission record (with grade and feedback)
 * - $gestionprojet: The activity instance
 * - $step: The step number (4-8)
 * - $groupid: The group ID
 * - $USER: Global user object
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Only display if a grade has been applied.
if (!isset($submission->grade) || $submission->grade === null) {
    return;
}

// Get the AI evaluation record for this submission to check visibility settings.
$aiEvaluation = null;
if ($gestionprojet->group_submission && $groupid != 0) {
    $aiEvaluation = $DB->get_record('gestionprojet_ai_evaluations', [
        'gestionprojetid' => $gestionprojet->id,
        'step' => $step,
        'groupid' => $groupid,
        'userid' => 0,
        'status' => 'applied'
    ]);
} else {
    $aiEvaluation = $DB->get_record('gestionprojet_ai_evaluations', [
        'gestionprojetid' => $gestionprojet->id,
        'step' => $step,
        'userid' => $USER->id,
        'status' => 'applied'
    ]);
}

// If no applied AI evaluation, try to get any completed one.
if (!$aiEvaluation) {
    if ($gestionprojet->group_submission && $groupid != 0) {
        $aiEvaluation = $DB->get_record('gestionprojet_ai_evaluations', [
            'gestionprojetid' => $gestionprojet->id,
            'step' => $step,
            'groupid' => $groupid,
            'userid' => 0,
            'status' => 'completed'
        ]);
    } else {
        $aiEvaluation = $DB->get_record('gestionprojet_ai_evaluations', [
            'gestionprojetid' => $gestionprojet->id,
            'step' => $step,
            'userid' => $USER->id,
            'status' => 'completed'
        ]);
    }
}

// Default visibility settings (all visible if no AI evaluation record found).
$showFeedback = true;
$showCriteria = true;
$showKeywordsFound = true;
$showKeywordsMissing = true;
$showSuggestions = true;

// Parse AI evaluation data and visibility settings.
$criteria = [];
$keywordsFound = [];
$keywordsMissing = [];
$suggestions = [];

if ($aiEvaluation) {
    // Get visibility settings from the evaluation record.
    $showFeedback = !isset($aiEvaluation->show_feedback) || $aiEvaluation->show_feedback;
    $showCriteria = !isset($aiEvaluation->show_criteria) || $aiEvaluation->show_criteria;
    $showKeywordsFound = !isset($aiEvaluation->show_keywords_found) || $aiEvaluation->show_keywords_found;
    $showKeywordsMissing = !isset($aiEvaluation->show_keywords_missing) || $aiEvaluation->show_keywords_missing;
    $showSuggestions = !isset($aiEvaluation->show_suggestions) || $aiEvaluation->show_suggestions;

    // Parse data only if visible.
    if ($showCriteria && !empty($aiEvaluation->criteria_json)) {
        $criteria = json_decode($aiEvaluation->criteria_json, true) ?? [];
    }
    if ($showKeywordsFound && !empty($aiEvaluation->keywords_found)) {
        $keywordsFound = json_decode($aiEvaluation->keywords_found, true) ?? [];
    }
    if ($showKeywordsMissing && !empty($aiEvaluation->keywords_missing)) {
        $keywordsMissing = json_decode($aiEvaluation->keywords_missing, true) ?? [];
    }
    if ($showSuggestions && !empty($aiEvaluation->suggestions)) {
        $suggestions = json_decode($aiEvaluation->suggestions, true) ?? [];
    }
}

// Check if there's anything to display beyond just the grade.
$hasDetailedFeedback = ($showFeedback && !empty($submission->feedback))
    || ($showCriteria && !empty($criteria))
    || ($showKeywordsFound && !empty($keywordsFound))
    || ($showKeywordsMissing && !empty($keywordsMissing))
    || ($showSuggestions && !empty($suggestions));

// Determine grade color class.
$gradeColorClass = 'grade-medium';
if ($submission->grade >= 16) {
    $gradeColorClass = 'grade-excellent';
} elseif ($submission->grade >= 12) {
    $gradeColorClass = 'grade-good';
} elseif ($submission->grade >= 10) {
    $gradeColorClass = 'grade-average';
} else {
    $gradeColorClass = 'grade-low';
}
?>

<div class="student-feedback">
    <div class="feedback-header">
        <div class="feedback-title">
            <span class="icon">📊</span>
            <span><?php echo get_string('grade', 'gestionprojet'); ?></span>
        </div>
        <div class="grade-badge <?php echo $gradeColorClass; ?>">
            <span>⭐</span>
            <span><?php echo format_float($submission->grade, 1); ?> / 20</span>
        </div>
    </div>

    <?php if ($hasDetailedFeedback): ?>

    <!-- Feedback text -->
    <?php if ($showFeedback && !empty($submission->feedback)): ?>
    <div class="feedback-section">
        <div class="section-title">
            <span class="section-icon">💬</span>
            <span><?php echo get_string('feedback', 'gestionprojet'); ?></span>
        </div>
        <div class="feedback-text">
            <?php echo nl2br(s($submission->feedback)); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Evaluation criteria -->
    <?php if ($showCriteria && !empty($criteria)): ?>
    <div class="feedback-section">
        <div class="section-title">
            <span class="section-icon">📋</span>
            <span><?php echo get_string('ai_criteria', 'gestionprojet'); ?></span>
        </div>
        <div class="criteria-grid">
            <?php foreach ($criteria as $criterion):
                $score = $criterion['score'] ?? 0;
                $max = $criterion['max'] ?? 5;
                $percentage = ($max > 0) ? ($score / $max) * 100 : 0;
                $scoreClass = ($percentage >= 70) ? 'score-high' : (($percentage >= 50) ? 'score-medium' : 'score-low');
            ?>
            <div class="criteria-item">
                <div class="criteria-info">
                    <div class="criteria-name"><?php echo s($criterion['name'] ?? ''); ?></div>
                    <?php if (!empty($criterion['comment'])): ?>
                    <div class="criteria-comment"><?php echo s($criterion['comment']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="criteria-score <?php echo $scoreClass; ?>">
                    <?php echo format_float($score, 1); ?> / <?php echo format_float($max, 0); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Keywords found and missing -->
    <?php if (($showKeywordsFound && !empty($keywordsFound)) || ($showKeywordsMissing && !empty($keywordsMissing))): ?>
    <div class="feedback-section">
        <div class="keywords-container">
            <?php if ($showKeywordsFound && !empty($keywordsFound)): ?>
            <div class="keywords-box found">
                <div class="keywords-box-title">
                    <span>✓</span>
                    <span><?php echo get_string('ai_keywords_found', 'gestionprojet'); ?></span>
                </div>
                <div class="keywords-list">
                    <?php foreach ($keywordsFound as $keyword): ?>
                    <span class="keyword-tag found"><?php echo s($keyword); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($showKeywordsMissing && !empty($keywordsMissing)): ?>
            <div class="keywords-box missing">
                <div class="keywords-box-title">
                    <span>!</span>
                    <span><?php echo get_string('ai_keywords_missing', 'gestionprojet'); ?></span>
                </div>
                <div class="keywords-list">
                    <?php foreach ($keywordsMissing as $keyword): ?>
                    <span class="keyword-tag missing"><?php echo s($keyword); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Suggestions for improvement -->
    <?php if ($showSuggestions && !empty($suggestions)): ?>
    <div class="feedback-section">
        <div class="section-title">
            <span class="section-icon">💡</span>
            <span><?php echo get_string('ai_suggestions', 'gestionprojet'); ?></span>
        </div>
        <div class="suggestions-list">
            <ul>
                <?php foreach ($suggestions as $suggestion): ?>
                <li><?php echo s($suggestion); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
