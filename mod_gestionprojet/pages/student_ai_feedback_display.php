<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

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

<style>
    .student-feedback {
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border-radius: 12px;
        padding: 24px;
        margin: 20px 0;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e9ecef;
    }

    .student-feedback .feedback-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e9ecef;
    }

    .student-feedback .feedback-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        font-weight: 600;
        color: #333;
    }

    .student-feedback .feedback-title .icon {
        font-size: 24px;
    }

    .student-feedback .grade-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 25px;
        font-size: 20px;
        font-weight: 700;
    }

    .student-feedback .grade-badge.grade-excellent {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }

    .student-feedback .grade-badge.grade-good {
        background: linear-gradient(135deg, #cce5ff 0%, #b8daff 100%);
        color: #004085;
    }

    .student-feedback .grade-badge.grade-average {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
        color: #856404;
    }

    .student-feedback .grade-badge.grade-medium {
        background: linear-gradient(135deg, #ffeeba 0%, #ffe69c 100%);
        color: #856404;
    }

    .student-feedback .grade-badge.grade-low {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
    }

    .student-feedback .feedback-section {
        margin-bottom: 20px;
    }

    .student-feedback .feedback-section:last-child {
        margin-bottom: 0;
    }

    .student-feedback .section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
        font-weight: 600;
        color: #495057;
        margin-bottom: 12px;
    }

    .student-feedback .section-title .section-icon {
        font-size: 18px;
    }

    .student-feedback .feedback-text {
        background: #fff;
        padding: 16px;
        border-radius: 8px;
        border-left: 4px solid #667eea;
        font-size: 14px;
        line-height: 1.6;
        color: #333;
    }

    /* Criteria grid */
    .student-feedback .criteria-grid {
        display: grid;
        gap: 12px;
    }

    .student-feedback .criteria-item {
        background: #fff;
        padding: 14px 16px;
        border-radius: 8px;
        border: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
    }

    .student-feedback .criteria-info {
        flex: 1;
    }

    .student-feedback .criteria-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 4px;
    }

    .student-feedback .criteria-comment {
        font-size: 13px;
        color: #666;
        line-height: 1.4;
    }

    .student-feedback .criteria-score {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 14px;
        white-space: nowrap;
    }

    .student-feedback .criteria-score.score-high {
        background: #d4edda;
        color: #155724;
    }

    .student-feedback .criteria-score.score-medium {
        background: #fff3cd;
        color: #856404;
    }

    .student-feedback .criteria-score.score-low {
        background: #f8d7da;
        color: #721c24;
    }

    /* Keywords lists */
    .student-feedback .keywords-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    @media (max-width: 768px) {
        .student-feedback .keywords-container {
            grid-template-columns: 1fr;
        }
    }

    .student-feedback .keywords-box {
        background: #fff;
        padding: 16px;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    .student-feedback .keywords-box.found {
        border-left: 4px solid #28a745;
    }

    .student-feedback .keywords-box.missing {
        border-left: 4px solid #ffc107;
    }

    .student-feedback .keywords-box-title {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 10px;
    }

    .student-feedback .keywords-box.found .keywords-box-title {
        color: #155724;
    }

    .student-feedback .keywords-box.missing .keywords-box-title {
        color: #856404;
    }

    .student-feedback .keywords-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .student-feedback .keyword-tag {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 13px;
    }

    .student-feedback .keyword-tag.found {
        background: #d4edda;
        color: #155724;
    }

    .student-feedback .keyword-tag.missing {
        background: #fff3cd;
        color: #856404;
    }

    /* Suggestions */
    .student-feedback .suggestions-list {
        background: #fff;
        padding: 16px;
        border-radius: 8px;
        border-left: 4px solid #17a2b8;
    }

    .student-feedback .suggestions-list ul {
        margin: 0;
        padding-left: 20px;
    }

    .student-feedback .suggestions-list li {
        margin-bottom: 8px;
        font-size: 14px;
        color: #333;
        line-height: 1.5;
    }

    .student-feedback .suggestions-list li:last-child {
        margin-bottom: 0;
    }
</style>

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
