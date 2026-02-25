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
 * Step 3: Planning - Teacher configuration page
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Check if this file is included by view.php or accessed directly
if (!defined('MOODLE_INTERNAL')) {
    // Standalone mode - requires config
    require_once(__DIR__ . '/../../../config.php');
    require_once(__DIR__ . '/../lib.php');

    $cmid = required_param('cmid', PARAM_INT);
    $cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

    require_login($course, false, $cm);
    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);

    // Check capability for standalone
    $isteacher = has_capability('mod/gestionprojet:configureteacherpages', $context);
    if (!$isteacher) {
        require_capability('mod/gestionprojet:view', $context);
    } else {
        require_capability('mod/gestionprojet:configureteacherpages', $context);
    }
} else {
    // Included mode - variables already set by view.php
    // Check capability
    $isteacher = has_capability('mod/gestionprojet:configureteacherpages', $context);
    if (!$isteacher) {
        require_capability('mod/gestionprojet:view', $context);
    } else {
        require_capability('mod/gestionprojet:configureteacherpages', $context);
    }
}

$readonly = !$isteacher;

// Get existing data
$planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);

// Display grade and feedback if available (for teachers viewing their own work)
$showGrade = false;

echo $OUTPUT->header();

// Navigation buttons
$nav_links = gestionprojet_get_navigation_links($gestionprojet, $cm->id, 'step3');

echo '<div class="navigation-container-flex">';
echo '<div class="nav-group">';
echo '<a href="' . new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]) . '" class="nav-button nav-button-prev"><span>🏠</span><span>' . get_string('home', 'gestionprojet') . '</span></a>';
if ($nav_links['prev']) {
    echo '<a href="' . $nav_links['prev'] . '" class="nav-button nav-button-prev"><span>←</span><span>' . get_string('previous', 'gestionprojet') . '</span></a>';
}
echo '</div>';

echo '<div>';
if ($nav_links['next']) {
    echo '<a href="' . $nav_links['next'] . '" class="nav-button"><span>' . get_string('next', 'gestionprojet') . '</span><span>→</span></a>';
}
echo '</div>';
echo '</div>';
echo '<h2>📋 ' . get_string('step3', 'gestionprojet') . '</h2>';

// Description
echo '<div class="alert alert-info">';
echo '<p>' . get_string('planning_description', 'gestionprojet') . '</p>';
echo '</div>';

if ($showGrade && isset($planning->grade)): ?>
    <div class="alert alert-success">
        <h4>
            <?php echo get_string('grade'); ?>:
            <?php echo number_format($planning->grade, 2); ?>/20
        </h4>
        <?php if (!empty($planning->feedback)): ?>
            <p><strong>
                    <?php echo get_string('feedback'); ?>:
                </strong><br>
                <?php echo format_text($planning->feedback, FORMAT_HTML); ?>
            </p>
        <?php endif; ?>
    </div>
<?php endif;

// Lock status (teacher can lock their own configuration)
// Lock status removed (always unlocked)
$locked = 0;
?>

<div class="card mb-3">
    <div class="card-body">
        <div class="card-header-flex">
            <h4>
                <?php echo get_string('project_planning', 'gestionprojet'); ?>
            </h4>
        </div>

        <form id="planningForm">
            <div class="form-group mb-3">
                <label for="projectname">
                    <?php echo get_string('projectname', 'gestionprojet'); ?>
                </label>
                <input type="text" class="form-control" id="projectname" name="projectname"
                    value="<?php echo $planning ? s($planning->projectname) : ''; ?>" <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="startdate">
                            <?php echo get_string('startdate', 'gestionprojet'); ?>
                        </label>
                        <input type="date" class="form-control" id="startdate" name="startdate"
                            value="<?php echo $planning && $planning->startdate ? date('Y-m-d', $planning->startdate) : ''; ?>"
                            <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="enddate">
                            <?php echo get_string('enddate', 'gestionprojet'); ?>
                        </label>
                        <input type="date" class="form-control" id="enddate" name="enddate"
                            value="<?php echo $planning && $planning->enddate ? date('Y-m-d', $planning->enddate) : ''; ?>"
                            <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="vacationzone">
                            <?php echo get_string('vacationzone', 'gestionprojet'); ?>
                        </label>
                        <select class="form-control" id="vacationzone" name="vacationzone" <?php echo ($locked || $readonly) ? 'disabled' : ''; ?>>
                            <option value="" <?php echo !$planning || !$planning->vacationzone ? 'selected' : ''; ?>>
                                <?php echo get_string('vacationzone_none', 'gestionprojet'); ?>
                            </option>
                            <option value="A" <?php echo $planning && $planning->vacationzone === 'A' ? 'selected' : ''; ?>>
                                <?php echo get_string('vacationzone_a', 'gestionprojet'); ?>
                            </option>
                            <option value="B" <?php echo $planning && $planning->vacationzone === 'B' ? 'selected' : ''; ?>>
                                <?php echo get_string('vacationzone_b', 'gestionprojet'); ?>
                            </option>
                            <option value="C" <?php echo $planning && $planning->vacationzone === 'C' ? 'selected' : ''; ?>>
                                <?php echo get_string('vacationzone_c', 'gestionprojet'); ?>
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="timeline-preview-section mt-4 mb-4">
                <h5 class="mb-3">
                    <?php echo get_string('timeline_preview', 'gestionprojet'); ?>
                </h5>
                <div id="timelineContainer" class="timeline-box">
                    <svg id="timelineSVG" class="timeline-svg"></svg>
                </div>
                <div id="milestonesContainer" class="milestones-container"></div>
                <div id="totalInfo" class="total-info-box"></div>
                <div id="vacationInfo" class="vacation-info"></div>
            </div>

            <h5 class="mt-4 mb-3">
                <?php echo get_string('task_durations', 'gestionprojet'); ?>
            </h5>
            <p class="text-muted small">
                <?php echo get_string('hours_per_week_info', 'gestionprojet'); ?>
            </p>

            <?php
            $taskColors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8'];
            for ($i = 1; $i <= 5; $i++):
                $fieldName = 'task' . $i . '_hours';
                $value = $planning ? $planning->$fieldName : 0;
                ?>
                <div class="task-input-item">
                    <div class="task-color-indicator"
                        style="background-color: <?php echo $taskColors[$i - 1]; ?>;">
                    </div>
                    <div class="task-label">
                        <?php echo get_string('task' . $i, 'gestionprojet'); ?>
                    </div>
                    <input type="number" class="form-control task-hours-input" id="task<?php echo $i; ?>_hours"
                        name="task<?php echo $i; ?>_hours" min="0" step="0.5" value="<?php echo $value; ?>"
                        <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>>
                    <span class="ml-2">
                        <?php echo get_string('hours', 'gestionprojet'); ?>
                    </span>
                </div>
            <?php endfor; ?>
        </form>

        <div id="autosaveIndicator" class="text-muted small" style="margin-top: 10px;"></div>
    </div>
</div>

<?php
// Load the AMD module with all required configuration.
$PAGE->requires->js_call_amd('mod_gestionprojet/step3', 'init', [[
    'cmid' => $cm->id,
    'autosaveInterval' => $gestionprojet->autosave_interval * 1000,
    'isLocked' => (bool) $locked,
    'readonly' => (bool) $readonly,
    'holidaysApiUrl' => 'https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records',
    'strings' => [
        'step3_select_dates' => get_string('step3_select_dates', 'gestionprojet'),
        'step3_end_after_start' => get_string('step3_end_after_start', 'gestionprojet'),
        'step3_define_durations' => get_string('step3_define_durations', 'gestionprojet'),
        // Pass placeholders through get_string so Moodle substitutes {$a->x} with {x}.
        'step3_total_hours' => get_string('step3_total_hours', 'gestionprojet',
            (object) ['hours' => '{hours}', 'weeks' => '{weeks}', 'hpw' => '{hpw}']),
        'step3_hours_available' => get_string('step3_hours_available', 'gestionprojet', '{a}'),
        'step3_hours_exceeded' => get_string('step3_hours_exceeded', 'gestionprojet', '{a}'),
        'step3_vacation_periods' => get_string('step3_vacation_periods', 'gestionprojet', '{a}'),
        'step3_vacation_prefix' => get_string('step3_vacation_prefix', 'gestionprojet'),
    ],
]]);

echo $OUTPUT->footer();
