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
 * Step 2: Needs expression (Horn Diagram) - Teacher configuration page
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
$besoin = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);

// Display grade and feedback if available (for teachers viewing their own work)
$showGrade = false;

echo $OUTPUT->header();

// Navigation buttons
// Navigation buttons
echo '<div class="navigation-container-flex">';
    echo '<div class="nav-group">';
        echo '<a href="' . new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]) . '" class="nav-button nav-button-prev"><span>🏠</span><span>' . get_string('home', 'gestionprojet') . '</span></a>';
        if ($nav_links['prev']) {
            echo '<a href="' . $nav_links['prev'] . '" class="nav-button nav-button-prev"><span>←</span><span>' . get_string('previous', 'gestionprojet') . '</span></a>';
        }
    echo '</div>';

    echo '<div>';
        // Step 2 is the last teacher step - link back to home instead of next student step.
        echo '<a href="' . new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]) . '" class="nav-button"><span>' . get_string('home', 'gestionprojet') . '</span><span>🏠</span></a>';
    echo '</div>';
echo '</div>';

echo '<h2>🦏 ' . get_string('step2', 'gestionprojet') . '</h2>';

// Description
echo '<div class="alert alert-info">';
echo '<h4>' . get_string('bete_a_corne_title', 'gestionprojet') . '</h4>';
echo '<p>' . get_string('bete_a_corne_description', 'gestionprojet') . '</p>';
echo '<ul>';
echo '<li><strong>' . get_string('aqui', 'gestionprojet') . '</strong> - ' . get_string('aqui_help', 'gestionprojet') . '</li>';
echo '<li><strong>' . get_string('surquoi', 'gestionprojet') . '</strong> - ' . get_string('surquoi_help', 'gestionprojet') . '</li>';
echo '<li><strong>' . get_string('dansquelbut', 'gestionprojet') . '</strong> - ' . get_string('dansquelbut_help', 'gestionprojet') . '</li>';
echo '</ul>';
echo '</div>';

if ($showGrade && isset($besoin->grade)): ?>
    <div class="alert alert-success">
        <h4><?php echo get_string('grade'); ?>: <?php echo number_format($besoin->grade, 2); ?>/20</h4>
        <?php if (!empty($besoin->feedback)): ?>
            <p><strong><?php echo get_string('feedback'); ?>:</strong><br><?php echo format_text($besoin->feedback, FORMAT_HTML); ?></p>
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
            <h4><?php echo get_string('bete_a_corne_diagram', 'gestionprojet'); ?></h4>
            <!-- Lock toggle removed -->

        </div>

        <div id="diagramContainer" class="diagram-box">
            <svg id="beteACorneCanvas" class="diagram-svg"></svg>
        </div>

        <form id="besoinForm">
            <div class="form-group mb-3">
                <label for="aqui" class="label-highlight"><?php echo get_string('aqui', 'gestionprojet'); ?></label>
                <small class="form-text text-muted"><?php echo get_string('aqui_help', 'gestionprojet'); ?></small>
                <textarea class="form-control" id="aqui" name="aqui" rows="3"
                    <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>><?php echo $besoin ? s($besoin->aqui) : ''; ?></textarea>
            </div>

            <div class="form-group mb-3">
                <label for="surquoi" class="label-highlight"><?php echo get_string('surquoi', 'gestionprojet'); ?></label>
                <small class="form-text text-muted"><?php echo get_string('surquoi_help', 'gestionprojet'); ?></small>
                <textarea class="form-control" id="surquoi" name="surquoi" rows="3"
                    <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>><?php echo $besoin ? s($besoin->surquoi) : ''; ?></textarea>
            </div>

            <div class="form-group mb-3">
                <label for="dansquelbut" class="label-highlight"><?php echo get_string('dansquelbut', 'gestionprojet'); ?></label>
                <small class="form-text text-muted"><?php echo get_string('dansquelbut_help', 'gestionprojet'); ?></small>
                <textarea class="form-control" id="dansquelbut" name="dansquelbut" rows="3"
                    <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>><?php echo $besoin ? s($besoin->dansquelbut) : ''; ?></textarea>
            </div>
        </form>

        <div id="autosaveIndicator" class="text-muted small" style="margin-top: 10px;"></div>
    </div>
</div>

<?php
// Load the AMD module for step 2.
$PAGE->requires->js_call_amd('mod_gestionprojet/step2', 'init', [[
    'cmid' => $cm->id,
    'step' => 2,
    'autosaveInterval' => $gestionprojet->autosave_interval * 1000,
    'readonly' => $readonly,
]]);

echo $OUTPUT->footer();
