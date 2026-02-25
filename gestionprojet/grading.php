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
 * Grading interface with step-by-step navigation.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_gestionprojet\output\icon;

$id = required_param('id', PARAM_INT); // Course module ID
$step = required_param('step', PARAM_INT); // Step number (4, 5, or 6)
$groupid = optional_param('groupid', 0, PARAM_INT); // Current group being graded

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);



require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:grade', $context);

// Check if we are in individual submission mode
$isGroupSubmission = $gestionprojet->group_submission;

// Handling navigation items (Groups or Users)
$navItems = [];
$navId = 0; // Current item ID (groupid or userid)

if ($isGroupSubmission) {
    // Get all groups
    $groups = groups_get_all_groups($course->id);
    if (empty($groups)) {
        // If no groups, create a virtual group for "All participants"
        $groups = [0 => (object) ['id' => 0, 'name' => get_string('allparticipants')]];
    }

    // Convert to simplified array for navigation
    foreach ($groups as $g) {
        $navItems[$g->id] = (object) ['id' => $g->id, 'name' => $g->name];
    }

    // Determine current ID
    $groupid = optional_param('groupid', 0, PARAM_INT);
    if (!$groupid && !empty($navItems)) {
        $groupid = array_key_first($navItems);
    }
    $navId = $groupid;
    $userid = 0; // Not relevant for group submission

} else {
    // Get all enrolled users who can submit
    // Equivalent to get_enrolled_users with capability check
    $context = context_module::instance($cm->id);
    $users = get_enrolled_users($context, 'mod/gestionprojet:submit');

    foreach ($users as $u) {
        $navItems[$u->id] = (object) ['id' => $u->id, 'name' => fullname($u)];
    }

    if (empty($navItems)) {
        $navItems[0] = (object) ['id' => 0, 'name' => get_string('nousers', 'gestionprojet')];
    }

    // Determine current ID
    $userid = optional_param('userid', 0, PARAM_INT);
    if (!$userid && !empty($navItems)) {
        $userid = array_key_first($navItems);
    }
    $navId = $userid;
    $groupid = 0; // Not used as primary key, but might be useful for context

    // Try to find group for this user if needed
    if ($userid) {
        $groupid = gestionprojet_get_user_group($cm, $userid);
    }
}

// Navigation logic
$allIds = array_keys($navItems);
$currentindex = array_search($navId, $allIds);

// Get previous and next IDs
$prevId = ($currentindex > 0) ? $allIds[$currentindex - 1] : null;
$nextId = ($currentindex < count($allIds) - 1) ? $allIds[$currentindex + 1] : null;

// Handle form submission
if (optional_param('savegrading', false, PARAM_BOOL) && confirm_sesskey()) {
    $grade = optional_param('grade', null, PARAM_FLOAT);
    $feedback = optional_param('feedback', '', PARAM_RAW);

    $table = '';
    switch ($step) {
        case 4:
            $table = 'gestionprojet_cdcf';
            break;
        case 5:
            $table = 'gestionprojet_essai';
            break;
        case 6:
            $table = 'gestionprojet_rapport';
            break;
        case 7:
            $table = 'gestionprojet_besoin_eleve';
            break;
        case 8:
            $table = 'gestionprojet_carnet';
            break;
    }

    if ($table) {
        // Construct search params based on submission mode
        $params = ['gestionprojetid' => $gestionprojet->id];
        if ($isGroupSubmission) {
            $params['groupid'] = $groupid;
            // Ensure unique constraint logic matches lib.php
        } else {
            $params['userid'] = $userid;
        }

        $record = $DB->get_record($table, $params);

        if ($record) {
            $record->grade = $grade;
            $record->feedback = $feedback;
            $record->timemodified = time();
            $DB->update_record($table, $record);

            // Update gradebook.
            // In per_step mode, pass the step number to update only that grade item.
            // In combined mode (legacy), step is null and all grades are recalculated.
            $grademode = isset($gestionprojet->grade_mode) ? $gestionprojet->grade_mode : 0;
            if ($grademode == 1) {
                // Per-step mode: update only this step's grade item.
                gestionprojet_update_grades($gestionprojet, ($isGroupSubmission ? 0 : $userid), true, $step);
            } else {
                // Combined mode: recalculate the combined average.
                gestionprojet_update_grades($gestionprojet, ($isGroupSubmission ? 0 : $userid));
            }

            // Redirect parameters
            $redirectParams = ['id' => $id, 'step' => $step];
            if ($isGroupSubmission) {
                $redirectParams['groupid'] = $nextId ?: $groupid;
            } else {
                $redirectParams['userid'] = $nextId ?: $userid;
            }

            redirect(new moodle_url('/mod/gestionprojet/grading.php', $redirectParams), get_string('grading_save', 'gestionprojet'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

// Page setup
$PAGE->set_url('/mod/gestionprojet/grading.php', ['id' => $cm->id, 'step' => $step, 'groupid' => $groupid]);
$PAGE->set_title(get_string('grading_navigation', 'gestionprojet'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Initialize AMD modules for AI progress, notifications, and grading interactions.
$PAGE->requires->js_call_amd('mod_gestionprojet/notifications', 'init', []);
$PAGE->requires->js_call_amd('mod_gestionprojet/ai_progress', 'init', [[
    'cmid' => $cm->id,
    'step' => $step,
    'containerSelector' => 'body'
]]);
$PAGE->requires->js_call_amd('mod_gestionprojet/grading', 'init', [[
    'cmid' => $cm->id,
    'step' => $step,
    'submissionid' => isset($submission) && $submission ? ($submission->id ?? 0) : 0,
    'strings' => [
        'confirm_unlock' => get_string('confirm_unlock_submission', 'gestionprojet'),
        'confirm_bulk' => get_string('confirm_bulk_reevaluate', 'gestionprojet'),
        'error' => get_string('error_invaliddata', 'gestionprojet'),
        'network_error' => get_string('toast_network_error', 'gestionprojet'),
        'bulk_processing' => get_string('bulk_reevaluate_processing', 'gestionprojet'),
    ],
]]);

echo $OUTPUT->header();

// Step definitions and filtering.
$steps = [
    7 => ['icon' => icon::render_step(7, 'sm', 'purple'), 'name' => get_string('step7', 'gestionprojet')],
    4 => ['icon' => icon::render_step(4, 'sm', 'purple'), 'name' => get_string('step4', 'gestionprojet')],
    5 => ['icon' => icon::render_step(5, 'sm', 'purple'), 'name' => get_string('step5', 'gestionprojet')],
    8 => ['icon' => icon::render_step(8, 'sm', 'purple'), 'name' => get_string('step8', 'gestionprojet')],
    6 => ['icon' => icon::render_step(6, 'sm', 'purple'), 'name' => get_string('step6', 'gestionprojet')],
];

// Filter enabled steps.
foreach ($steps as $k => $stepinfo) {
    $field = 'enable_step' . $k;
    if (isset($gestionprojet->$field) && !$gestionprojet->$field) {
        unset($steps[$k]);
    }
}

// Ensure current step is valid/enabled, otherwise redirect to first available.
if (!array_key_exists($step, $steps) && !empty($steps)) {
    $firststep = array_key_first($steps);
    redirect(new moodle_url('/mod/gestionprojet/grading.php', [
        'id' => $id,
        'step' => $firststep,
        'groupid' => $groupid
    ]));
}

// Get submission based on step.
$submission = null;
$tablename = '';
$params = ['gestionprojetid' => $gestionprojet->id];
if ($isGroupSubmission) {
    $params['groupid'] = $groupid;
} else {
    $params['userid'] = $userid;
}

switch ($step) {
    case 1:
        $tablename = 'gestionprojet_description';
        $submission = $DB->get_record($tablename, ['gestionprojetid' => $gestionprojet->id]);
        break;
    case 2:
        $tablename = 'gestionprojet_besoin';
        $submission = $DB->get_record($tablename, ['gestionprojetid' => $gestionprojet->id]);
        break;
    case 3:
        $tablename = 'gestionprojet_planning';
        $submission = $DB->get_record($tablename, ['gestionprojetid' => $gestionprojet->id]);
        break;
    case 4:
        $tablename = 'gestionprojet_cdcf';
        $submission = $DB->get_record($tablename, $params);
        break;
    case 5:
        $tablename = 'gestionprojet_essai';
        $submission = $DB->get_record($tablename, $params);
        break;
    case 6:
        $tablename = 'gestionprojet_rapport';
        $submission = $DB->get_record($tablename, $params);
        break;
    case 7:
        $tablename = 'gestionprojet_besoin_eleve';
        $submission = $DB->get_record($tablename, $params);
        break;
    case 8:
        $tablename = 'gestionprojet_carnet';
        $submission = $DB->get_record($tablename, $params);
        break;
}

// Get AI evaluation if available.
$aievaluation = null;
if ($submission && in_array($step, [4, 5, 6, 7, 8]) && !empty($gestionprojet->ai_enabled)) {
    require_once(__DIR__ . '/classes/ai_evaluator.php');
    require_once(__DIR__ . '/classes/ai_response_parser.php');
    $aievaluation = \mod_gestionprojet\ai_evaluator::get_evaluation(
        $gestionprojet->id,
        $step,
        $submission->id
    );
}

$isSubmitted = $submission && !empty($submission->status) && $submission->status == 1;
$paramName = $isGroupSubmission ? 'groupid' : 'userid';
?>

<div class="grading-container">
    <?php
    // Build grading navigation template context.
    $steptabs = [];
    foreach ($steps as $stepnum => $stepinfo) {
        $steptabs[] = [
            'stepnum' => $stepnum,
            'icon' => $stepinfo['icon'],
            'name' => $stepinfo['name'],
            'isactive' => ($stepnum == $step),
            'url' => (new moodle_url('/mod/gestionprojet/grading.php', [
                'id' => $id,
                'step' => $stepnum,
                $paramName => $navId,
            ]))->out(false),
        ];
    }

    $navitemsdata = [];
    foreach ($navItems as $item) {
        $navitemsdata[] = [
            'id' => $item->id,
            'name' => s($item->name),
            'isselected' => ($item->id == $navId),
            'url' => (new moodle_url('/mod/gestionprojet/grading.php', [
                'id' => $id,
                'step' => $step,
                $paramName => $item->id,
            ]))->out(false),
        ];
    }

    $navcontext = [
        'cmid' => $cm->id,
        'currentstep' => $step,
        'homeurl' => (new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]))->out(false),
        'aienabled' => !empty($gestionprojet->ai_enabled),
        'steptabs' => $steptabs,
        'navid' => $navId,
        'paramname' => $paramName,
        'currentindex' => ($currentindex !== false ? $currentindex + 1 : 0),
        'totalitems' => count($navItems),
        'hasprev' => ($prevId !== null),
        'prevurl' => ($prevId !== null) ? (new moodle_url('/mod/gestionprojet/grading.php', [
            'id' => $id, 'step' => $step, $paramName => $prevId,
        ]))->out(false) : '',
        'hasnext' => ($nextId !== null),
        'nexturl' => ($nextId !== null) ? (new moodle_url('/mod/gestionprojet/grading.php', [
            'id' => $id, 'step' => $step, $paramName => $nextId,
        ]))->out(false) : '',
        'navitems' => $navitemsdata,
        'currentstepicon' => $steps[$step]['icon'] ?? '',
        'currentstepname' => $steps[$step]['name'] ?? '',
        'icon_back' => icon::render('chevron-left', 'sm'),
        'icon_prev' => icon::render('chevron-left', 'sm'),
        'icon_next' => icon::render('chevron-right', 'sm'),
        'icon_refresh' => icon::render('refresh-cw', 'sm'),
        'icon_home' => icon::render('home', 'sm'),
    ];

    $gradingrenderer = $PAGE->get_renderer('mod_gestionprojet');
    echo $gradingrenderer->render_grading_navigation($navcontext);
    ?>

    <?php if (empty($navItems)): ?>
        <div class="alert alert-warning">
            <?php echo get_string('no_submission', 'gestionprojet'); ?>
        </div>
    <?php else: ?>
        <!-- Two-panel layout -->
        <div class="submission-panel">
            <!-- Left: Submission Content -->
            <div class="submission-content">
                <h2><?php echo $steps[$step]['icon'] . ' ' . $steps[$step]['name']; ?></h2>

                <?php if (!$submission): ?>
                    <div class="status-bar no-submission">
                        <?php echo icon::render('x-circle', 'sm', 'red'); ?> <?php echo get_string('no_submission', 'gestionprojet'); ?>
                    </div>

                <?php else: ?>
                    <?php // Status bar for student steps.
                    if (in_array($step, [4, 5, 6, 7, 8])): ?>
                        <?php if ($isSubmitted): ?>
                            <div class="status-bar submitted">
                                <?php echo icon::render('check-circle', 'sm', 'green'); ?> <?php echo get_string('submission_status_submitted', 'gestionprojet'); ?>
                                <?php if (!empty($submission->timesubmitted)): ?>
                                    <span class="grading-timestamp">
                                        <?php echo get_string('submitted_at', 'gestionprojet'); ?>: <?php echo userdate($submission->timesubmitted, get_string('strftimedatetime')); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (has_capability('mod/gestionprojet:lock', $context)): ?>
                                    <button type="button" class="unlock-btn" id="btn-unlock-submission"
                                        data-cmid="<?php echo $cm->id; ?>"
                                        data-step="<?php echo $step; ?>"
                                        data-groupid="<?php echo $groupid; ?>"
                                        data-userid="<?php echo $userid ?? 0; ?>">
                                        <?php echo icon::render('lock-open', 'sm'); ?> <?php echo get_string('unlock_submission', 'gestionprojet'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="status-bar draft">
                                <?php echo icon::render('file-text', 'sm'); ?> <?php echo get_string('submission_status_draft', 'gestionprojet'); ?>
                                <span class="grading-timestamp">
                                    <?php echo get_string('submission_status_draft_desc', 'gestionprojet'); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php
                    // Display submission content based on step.
                    if ($step == 1): // Description ?>
                        <div class="field-display">
                            <h4><?php echo get_string('intitule', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->intitule ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('niveau', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->niveau ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('support', 'gestionprojet'); ?></h4>
                            <p><?php echo format_text($submission->support ?? '', FORMAT_HTML); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('duree', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->duree ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('besoin', 'gestionprojet'); ?></h4>
                            <p><?php echo format_text($submission->besoin ?? '', FORMAT_HTML); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('production', 'gestionprojet'); ?></h4>
                            <p><?php echo format_text($submission->production ?? '', FORMAT_HTML); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('outils', 'gestionprojet'); ?></h4>
                            <p><?php echo format_text($submission->outils ?? '', FORMAT_HTML); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('evaluation', 'gestionprojet'); ?></h4>
                            <p><?php echo format_text($submission->evaluation ?? '', FORMAT_HTML); ?></p>
                        </div>

                    <?php elseif ($step == 2): // Needs Expression ?>
                        <div class="field-display">
                            <h4><?php echo get_string('aqui', 'gestionprojet'); ?></h4>
                            <p><?php echo format_text($submission->aqui ?? '', FORMAT_PLAIN); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('surquoi', 'gestionprojet'); ?></h4>
                            <p><?php echo format_text($submission->surquoi ?? '', FORMAT_PLAIN); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('dansquelbut', 'gestionprojet'); ?></h4>
                            <p><?php echo format_text($submission->dansquelbut ?? '', FORMAT_PLAIN); ?></p>
                        </div>

                    <?php elseif ($step == 3): // Planning ?>
                        <div class="field-display">
                            <h4><?php echo get_string('projectname', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->projectname ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('startdate', 'gestionprojet'); ?> - <?php echo get_string('enddate', 'gestionprojet'); ?></h4>
                            <p>
                                <?php if ($submission->startdate) echo userdate($submission->startdate, get_string('strftimedaydate')); ?>
                                -
                                <?php if ($submission->enddate) echo userdate($submission->enddate, get_string('strftimedaydate')); ?>
                            </p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('vacationzone', 'gestionprojet'); ?></h4>
                            <p><?php echo $submission->vacationzone ? get_string('vacationzone_' . strtolower($submission->vacationzone), 'gestionprojet') : get_string('vacationzone_none', 'gestionprojet'); ?></p>
                        </div>

                    <?php elseif ($step == 4): // CDCF
                        $interacteursData = [];
                        if ($submission->interacteurs_data) {
                            $interacteursData = json_decode($submission->interacteurs_data, true) ?? [];
                        }
                    ?>
                        <div class="field-display">
                            <h4><?php echo get_string('produit', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->produit ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('milieu', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->milieu ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('fonction_principale', 'gestionprojet'); ?> (FP)</h4>
                            <p><?php echo s($submission->fp ?? ''); ?></p>
                        </div>
                        <?php if (!empty($interacteursData)): ?>
                            <div class="field-display">
                                <h4><?php echo get_string('interacteurs', 'gestionprojet'); ?></h4>
                                <?php foreach ($interacteursData as $idx => $interacteur): ?>
                                    <div class="interacteur-card">
                                        <strong><?php echo s($interacteur['name'] ?? 'Interacteur ' . ($idx + 1)); ?></strong>
                                        <?php if (!empty($interacteur['fcs'])): ?>
                                            <ul>
                                                <?php foreach ($interacteur['fcs'] as $fcIdx => $fc): ?>
                                                    <?php if (!empty($fc['value'])): ?>
                                                        <li><strong>FC<?php echo ($fcIdx + 1); ?>:</strong> <?php echo s($fc['value']); ?></li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($step == 5): // Test Sheet ?>
                        <div class="field-display">
                            <h4><?php echo get_string('nom_essai', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->nom_essai ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('date', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->date_essai ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('fonction_service', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->fonction_service ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('niveaux_reussite', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->niveaux_reussite ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('etapes_protocole', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->etapes_protocole ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('materiel_outils', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->materiel_outils ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('resultats_obtenus', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->resultats_obtenus ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('conclusion', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->conclusion ?? ''); ?></p>
                        </div>

                    <?php elseif ($step == 6): // Report
                        $auteurs = [];
                        if ($submission->auteurs) {
                            $auteurs = json_decode($submission->auteurs, true) ?? [];
                        }
                    ?>
                        <div class="field-display">
                            <h4><?php echo get_string('titre_projet', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->titre_projet ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('auteurs', 'gestionprojet'); ?></h4>
                            <p><?php echo !empty($auteurs) ? s(implode(', ', $auteurs)) : get_string('no_submission', 'gestionprojet'); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('besoin_projet', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->besoin_projet ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('imperatifs', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->imperatifs ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('solutions', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->solutions ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('justification', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->justification ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('realisation', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->realisation ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('difficultes', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->difficultes ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('validation', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->validation ?? ''); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('bilan', 'gestionprojet'); ?></h4>
                            <p><?php echo s($submission->bilan ?? ''); ?></p>
                        </div>

                    <?php elseif ($step == 7): // Student Needs Expression ?>
                        <div class="field-display">
                            <h4><?php echo get_string('aqui', 'gestionprojet'); ?></h4>
                            <p><?php echo format_text($submission->aqui ?? '', FORMAT_PLAIN); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('surquoi', 'gestionprojet'); ?></h4>
                            <p><?php echo format_text($submission->surquoi ?? '', FORMAT_PLAIN); ?></p>
                        </div>
                        <div class="field-display">
                            <h4><?php echo get_string('dansquelbut', 'gestionprojet'); ?></h4>
                            <p><?php echo format_text($submission->dansquelbut ?? '', FORMAT_PLAIN); ?></p>
                        </div>

                    <?php elseif ($step == 8): // Logbook
                        $tasks_data = [];
                        if ($submission->tasks_data) {
                            $tasks_data = json_decode($submission->tasks_data, true) ?? [];
                        }
                    ?>
                        <div class="field-display">
                            <h4><?php echo get_string('step8', 'gestionprojet'); ?></h4>
                            <?php if (empty($tasks_data)): ?>
                                <p><em><?php echo get_string('no_submission', 'gestionprojet'); ?></em></p>
                            <?php else: ?>
                                <table class="grading-logbook-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo get_string('logbook_date', 'gestionprojet'); ?></th>
                                            <th><?php echo get_string('logbook_tasks_today', 'gestionprojet'); ?></th>
                                            <th><?php echo get_string('logbook_tasks_future', 'gestionprojet'); ?></th>
                                            <th><?php echo get_string('logbook_status_header', 'gestionprojet'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tasks_data as $task):
                                            $statusClass = '';
                                            $statusLabel = '';
                                            if (($task['status'] ?? '') === 'ahead') {
                                                $statusClass = 'ahead';
                                                $statusLabel = get_string('logbook_status_ahead', 'gestionprojet');
                                            } elseif (($task['status'] ?? '') === 'ontime') {
                                                $statusClass = 'ontime';
                                                $statusLabel = get_string('logbook_status_ontime', 'gestionprojet');
                                            } elseif (($task['status'] ?? '') === 'late') {
                                                $statusClass = 'late';
                                                $statusLabel = get_string('logbook_status_late', 'gestionprojet');
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo s($task['date'] ?? ''); ?></td>
                                                <td><?php echo s($task['tasks_today'] ?? ''); ?></td>
                                                <td><?php echo s($task['tasks_future'] ?? ''); ?></td>
                                                <td><span class="logbook-status <?php echo $statusClass; ?>"><?php echo s($statusLabel); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Right: Grading Sidebar -->
            <div class="grading-sidebar">
                <?php
                // ============================================
                // AI Evaluation Section (sidebar)
                // ============================================
                if ($submission && in_array($step, [4, 5, 6, 7, 8]) && !empty($gestionprojet->ai_enabled)):
                ?>
                <div class="ai-evaluation-container">
                    <h4><?php echo icon::render('bot', 'sm', 'purple'); ?> <?php echo get_string('ai_evaluation_section', 'gestionprojet'); ?></h4>

                    <?php if (!$aievaluation): ?>
                        <p class="grading-draft-hint">
                            <?php echo get_string('no_ai_evaluation', 'gestionprojet'); ?>
                        </p>
                        <?php if ($isSubmitted): ?>
                            <button type="button" class="btn-ai btn-ai-trigger" id="btn-trigger-ai-eval"
                                data-cmid="<?php echo $cm->id; ?>"
                                data-step="<?php echo $step; ?>"
                                data-submissionid="<?php echo $submission->id; ?>">
                                <?php echo icon::render('zap', 'sm'); ?> <?php echo get_string('trigger_ai_evaluation', 'gestionprojet'); ?>
                            </button>
                        <?php else: ?>
                            <p class="grading-draft-hint">
                                <?php echo get_string('submission_status_draft_desc', 'gestionprojet'); ?>
                            </p>
                        <?php endif; ?>

                    <?php elseif ($aievaluation->status === 'pending' || $aievaluation->status === 'processing'): ?>
                        <div class="ai-pending" data-auto-reload="10000">
                            <div class="spinner"></div>
                            <span><?php echo get_string('ai_evaluating', 'gestionprojet'); ?></span>
                        </div>

                    <?php elseif ($aievaluation->status === 'failed'): ?>
                        <div class="ai-error-box">
                            <strong class="ai-error-title"><?php echo icon::render('alert-triangle', 'sm', 'red'); ?> <?php echo get_string('ai_evaluation_failed', 'gestionprojet'); ?></strong>
                            <?php if ($aievaluation->error_message): ?>
                                <p class="ai-error-detail">
                                    <?php echo s($aievaluation->error_message); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn-ai btn-ai-trigger" id="btn-retry-ai-eval"
                            data-cmid="<?php echo $cm->id; ?>"
                            data-evaluationid="<?php echo $aievaluation->id; ?>">
                            <?php echo icon::render('refresh-cw', 'sm'); ?> <?php echo get_string('retry_evaluation', 'gestionprojet'); ?>
                        </button>

                    <?php elseif ($aievaluation->status === 'completed' || $aievaluation->status === 'applied'):
                        $result = new \stdClass();
                        $result->criteria = json_decode($aievaluation->criteria_json, true) ?? [];
                        $result->keywords_found = json_decode($aievaluation->keywords_found, true) ?? [];
                        $result->keywords_missing = json_decode($aievaluation->keywords_missing, true) ?? [];
                        $result->suggestions = json_decode($aievaluation->suggestions, true) ?? [];
                    ?>

                        <?php if ($aievaluation->status === 'applied'): ?>
                            <div class="ai-applied-badge">
                                <?php echo icon::render('check-circle', 'sm', 'green'); ?> <?php echo get_string('ai_evaluation_applied', 'gestionprojet'); ?>
                                <?php if ($aievaluation->applied_at): ?>
                                    (<?php echo userdate($aievaluation->applied_at, get_string('strftimedatetime')); ?>)
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- AI Grade Card -->
                        <div class="ai-grade-card">
                            <div class="ai-grade-value"><?php echo number_format($aievaluation->parsed_grade, 1); ?>/20</div>
                            <div class="ai-grade-label"><?php echo get_string('ai_grade', 'gestionprojet'); ?></div>
                        </div>

                        <!-- Criteria with progress bars (collapsible) -->
                        <?php if (!empty($result->criteria)): ?>
                            <div class="ai-criteria-section">
                                <div class="ai-section-toggle">
                                    <h5 class="ai-section-title"><?php echo icon::render('bar-chart-3', 'sm'); ?> <?php echo get_string('ai_criteria', 'gestionprojet'); ?></h5>
                                    <span class="toggle-icon"><?php echo icon::render('chevron-down', 'xs'); ?></span>
                                </div>
                                <div class="ai-section-content">
                                    <?php foreach ($result->criteria as $criterion):
                                        $score = isset($criterion['score']) ? (float)$criterion['score'] : 0;
                                        $max = isset($criterion['max']) ? (float)$criterion['max'] : 5;
                                        $percentage = $max > 0 ? ($score / $max) * 100 : 0;
                                        $scoreClass = $percentage >= 70 ? 'good' : ($percentage >= 50 ? 'medium' : 'low');
                                    ?>
                                        <div class="ai-criterion">
                                            <div class="ai-criterion-header">
                                                <span class="ai-criterion-name"><?php echo s($criterion['name'] ?? ''); ?></span>
                                                <span class="ai-criterion-score <?php echo $scoreClass; ?>">
                                                    <?php echo number_format($score, 1); ?>/<?php echo number_format($max, 0); ?>
                                                </span>
                                            </div>
                                            <div class="ai-criterion-progress">
                                                <div class="ai-criterion-progress-bar <?php echo $scoreClass; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <?php if (!empty($criterion['comment'])): ?>
                                                <div class="ai-criterion-comment"><?php echo nl2br(s($criterion['comment'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Feedback (collapsible) -->
                        <?php if (!empty($aievaluation->parsed_feedback)): ?>
                            <div class="ai-criteria-section">
                                <div class="ai-section-toggle">
                                    <h5 class="ai-section-title"><?php echo icon::render('message-circle', 'sm'); ?> <?php echo get_string('ai_feedback', 'gestionprojet'); ?></h5>
                                    <span class="toggle-icon"><?php echo icon::render('chevron-down', 'xs'); ?></span>
                                </div>
                                <div class="ai-section-content">
                                    <div class="ai-feedback ai-feedback-inline">
                                        <?php echo nl2br(s($aievaluation->parsed_feedback)); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Keywords found/missing -->
                        <?php if (!empty($result->keywords_found) || !empty($result->keywords_missing)): ?>
                            <div class="ai-keywords-section">
                                <?php if (!empty($result->keywords_found)): ?>
                                    <div class="ai-keywords-card found">
                                        <h6><?php echo get_string('ai_keywords_found', 'gestionprojet'); ?></h6>
                                        <?php foreach ($result->keywords_found as $keyword): ?>
                                            <span class="ai-keyword-tag found"><?php echo icon::render('check-circle', 'xs', 'green'); ?> <?php echo s($keyword); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($result->keywords_missing)): ?>
                                    <div class="ai-keywords-card missing">
                                        <h6><?php echo get_string('ai_keywords_missing', 'gestionprojet'); ?></h6>
                                        <?php foreach ($result->keywords_missing as $keyword): ?>
                                            <span class="ai-keyword-tag missing"><?php echo icon::render('x-circle', 'xs', 'red'); ?> <?php echo s($keyword); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Suggestions -->
                        <?php if (!empty($result->suggestions)): ?>
                            <div class="ai-suggestions">
                                <h5><?php echo get_string('ai_suggestions', 'gestionprojet'); ?></h5>
                                <ul>
                                    <?php foreach ($result->suggestions as $suggestion): ?>
                                        <li><?php echo s($suggestion); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Token usage -->
                        <?php
                        $tokensUsed = ($aievaluation->prompt_tokens ?? 0) + ($aievaluation->completion_tokens ?? 0);
                        if ($tokensUsed > 0):
                        ?>
                            <div class="ai-token-info">
                                <?php echo get_string('ai_tokens_used', 'gestionprojet'); ?>: <?php echo number_format($tokensUsed); ?>
                                (<?php echo s($aievaluation->provider); ?>/<?php echo s($aievaluation->model); ?>)
                            </div>
                        <?php endif; ?>

                        <!-- Visibility options -->
                        <?php if ($aievaluation->status !== 'applied'): ?>
                            <div class="visibility-options">
                                <h5><?php echo icon::render('eye', 'sm'); ?> <?php echo get_string('visibility_options', 'gestionprojet'); ?></h5>
                                <label><input type="checkbox" id="show_feedback" name="show_feedback" value="1" checked> <?php echo get_string('show_feedback_to_student', 'gestionprojet'); ?></label>
                                <label><input type="checkbox" id="show_criteria" name="show_criteria" value="1" checked> <?php echo get_string('show_criteria_to_student', 'gestionprojet'); ?></label>
                                <label><input type="checkbox" id="show_keywords_found" name="show_keywords_found" value="1" checked> <?php echo get_string('show_keywords_found_to_student', 'gestionprojet'); ?></label>
                                <label><input type="checkbox" id="show_keywords_missing" name="show_keywords_missing" value="1" checked> <?php echo get_string('show_keywords_missing_to_student', 'gestionprojet'); ?></label>
                                <label><input type="checkbox" id="show_suggestions" name="show_suggestions" value="1" checked> <?php echo get_string('show_suggestions_to_student', 'gestionprojet'); ?></label>
                            </div>
                        <?php endif; ?>

                        <!-- AI Action buttons -->
                        <div class="ai-actions">
                            <?php if ($aievaluation->status !== 'applied'): ?>
                                <button type="button" class="btn-ai btn-ai-apply" id="btn-apply-ai-grade"
                                    data-cmid="<?php echo $cm->id; ?>"
                                    data-evaluationid="<?php echo $aievaluation->id; ?>"
                                    data-grade="<?php echo $aievaluation->parsed_grade; ?>"
                                    data-feedback="<?php echo htmlspecialchars($aievaluation->parsed_feedback ?? '', ENT_QUOTES); ?>">
                                    <?php echo icon::render('check-circle', 'sm', 'green'); ?> <?php echo get_string('apply_ai_grade', 'gestionprojet'); ?>
                                </button>
                                <button type="button" class="btn-ai btn-ai-modify"
                                    data-grade="<?php echo $aievaluation->parsed_grade; ?>"
                                    data-feedback="<?php echo htmlspecialchars($aievaluation->parsed_feedback ?? '', ENT_QUOTES); ?>">
                                    <?php echo icon::render('pencil', 'sm'); ?> <?php echo get_string('apply_with_modifications', 'gestionprojet'); ?>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn-ai btn-ai-trigger" id="btn-retry-ai-eval"
                                data-cmid="<?php echo $cm->id; ?>"
                                data-evaluationid="<?php echo $aievaluation->id; ?>">
                                <?php echo icon::render('refresh-cw', 'sm'); ?> <?php echo get_string('retry_evaluation', 'gestionprojet'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- AI Progress Container -->
                <div id="ai-progress-container"></div>
                <?php endif; ?>

                <!-- Manual Grading Form -->
                <?php if ($submission): ?>
                <div class="grading-form-container">
                    <h4><?php echo icon::render('pencil', 'sm'); ?> <?php echo get_string('grading_grade', 'gestionprojet'); ?></h4>

                    <form method="post" action="">
                        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                        <input type="hidden" name="savegrading" value="1">

                        <div class="form-group">
                            <label for="grade"><?php echo get_string('grading_grade', 'gestionprojet'); ?> /20</label>
                            <input type="number" name="grade" id="grade" class="form-control grade-input"
                                min="0" max="20" step="0.5"
                                value="<?php echo $submission->grade ?? ''; ?>" placeholder="0 - 20">
                        </div>

                        <div class="form-group">
                            <label for="feedback"><?php echo get_string('grading_feedback', 'gestionprojet'); ?></label>
                            <textarea name="feedback" id="feedback" class="form-control" rows="6"
                                placeholder="<?php echo get_string('grading_feedback', 'gestionprojet'); ?>..."><?php echo s($submission->feedback ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn-save-grade">
                            <?php echo icon::render('save', 'sm'); ?> <?php echo get_string('grading_save', 'gestionprojet'); ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();