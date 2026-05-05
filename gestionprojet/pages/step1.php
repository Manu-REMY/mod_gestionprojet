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
 * Step 1: Project Description Sheet
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


// Check capability
$context = context_module::instance($cm->id);
$isteacher = has_capability('mod/gestionprojet:configureteacherpages', $context);

if (!$isteacher) {
    // Students can view but not edit
    require_capability('mod/gestionprojet:view', $context);
} else {
    // Teachers need full access
    require_capability('mod/gestionprojet:configureteacherpages', $context);
}

// Force locked state for students
$readonly = !$isteacher;


// Get description record
$description = $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]);
if (!$description) {
    // Create if doesn't exist
    $description = new stdClass();
    $description->gestionprojetid = $gestionprojet->id;
    $description->locked = 0;
    $description->timecreated = time();
    $description->timemodified = time();
    $description->id = $DB->insert_record('gestionprojet_description', $description);
}

// Decode competences JSON
$competences = [];
if (!empty($description->competences)) {
    $competences = json_decode($description->competences, true) ?? [];
}

// Handle lock/unlock
// Lock toggle removed


// Autosave handled inline at bottom of file
// $PAGE->requires->js_call_amd('mod_gestionprojet/autosave', 'init', [
//     'cmid' => $cm->id,
//     'step' => 1,
//     'interval' => $gestionprojet->autosave_interval * 1000,
//     'formSelector' => '#descriptionForm'
// ]);

echo $OUTPUT->header();

// Render direct step navigation tabs.
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 1, 'consignes')
);
?>



<div class="step1-container">
    <div class="title-container">
        <h2>📋 <?php echo get_string('step1', 'gestionprojet'); ?></h2>

        <!-- Lock toggle removed -->

    </div>

    <!-- Locked alert removed -->


    <div class="description-info">
        <h3><?php echo get_string('step1_objective_title', 'gestionprojet'); ?></h3>
        <p><?php echo get_string('step1_objective_desc', 'gestionprojet'); ?></p>
    </div>

    <form id="descriptionForm" class="locked-overlay">

        <div class="form-layout">
            <div class="form-main">
                <div class="form-row">
                    <div class="form-group">
                        <label for="intitule"><?php echo get_string('intitule', 'gestionprojet'); ?> *</label>
                        <input type="text" id="intitule" name="intitule"
                               value="<?php echo s($description->intitule ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="niveau"><?php echo get_string('niveau', 'gestionprojet'); ?> *</label>
                        <select id="niveau" name="niveau" required>
                            <option value=""><?php echo get_string('step1_select_default', 'gestionprojet'); ?></option>
                            <option value="6ème" <?php echo ($description->niveau ?? '') == '6ème' ? 'selected' : ''; ?>><?php echo get_string('step1_grade_6', 'gestionprojet'); ?></option>
                            <option value="5ème" <?php echo ($description->niveau ?? '') == '5ème' ? 'selected' : ''; ?>><?php echo get_string('step1_grade_5', 'gestionprojet'); ?></option>
                            <option value="4ème" <?php echo ($description->niveau ?? '') == '4ème' ? 'selected' : ''; ?>><?php echo get_string('step1_grade_4', 'gestionprojet'); ?></option>
                            <option value="3ème" <?php echo ($description->niveau ?? '') == '3ème' ? 'selected' : ''; ?>><?php echo get_string('step1_grade_3', 'gestionprojet'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="duree"><?php echo get_string('duree', 'gestionprojet'); ?> *</label>
                        <input type="text" id="duree" name="duree"
                               value="<?php echo s($description->duree ?? ''); ?>"
                               placeholder="<?php echo get_string('step1_duree_placeholder', 'gestionprojet'); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="support"><?php echo get_string('support', 'gestionprojet'); ?></label>
                    <textarea id="support" name="support" rows="3"><?php echo s($description->support ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="besoin"><?php echo get_string('besoin', 'gestionprojet'); ?> *</label>
                    <textarea id="besoin" name="besoin" rows="4" required><?php echo s($description->besoin ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="production"><?php echo get_string('production', 'gestionprojet'); ?> *</label>
                    <textarea id="production" name="production" rows="4" required><?php echo s($description->production ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="outils"><?php echo get_string('outils', 'gestionprojet'); ?></label>
                    <textarea id="outils" name="outils" rows="4"><?php echo s($description->outils ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="evaluation"><?php echo get_string('evaluation', 'gestionprojet'); ?></label>
                    <textarea id="evaluation" name="evaluation" rows="4"><?php echo s($description->evaluation ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-sidebar">
                <h4><?php echo get_string('image', 'gestionprojet'); ?></h4>
                <p style="font-size: 14px; color: #666; margin: 10px 0;"><?php echo get_string('step1_image_desc', 'gestionprojet'); ?></p>

                <?php if ($description->imageid): ?>
                    <div id="imagePreview">
                        <?php
                        $fs = get_file_storage();
                        $files = $fs->get_area_files($context->id, 'mod_gestionprojet', 'description_image', $description->id, 'timemodified', false);
                        if ($file = reset($files)) {
                            $url = moodle_url::make_pluginfile_url(
                                $file->get_contextid(),
                                $file->get_component(),
                                $file->get_filearea(),
                                $file->get_itemid(),
                                $file->get_filepath(),
                                $file->get_filename()
                            );
                            echo html_writer::img($url, 'Project image', ['class' => 'image-preview']);
                        }
                        ?>
                    </div>
                <?php else: ?>
                    <div style="padding: 40px; color: #999; font-style: italic;">
                        <?php echo get_string('step1_no_image', 'gestionprojet'); ?>
                    </div>
                <?php endif; ?>

                <!-- TODO: Implement file upload with Moodle file API -->
                <p style="font-size: 12px; color: #999; margin-top: 10px;">
                    <?php echo get_string('step1_image_upload_todo', 'gestionprojet'); ?>
                </p>
            </div>
        </div>

        <!-- Competencies -->
        <div class="competences-section">
            <h3><?php echo get_string('competences', 'gestionprojet'); ?></h3>

            <div class="competences-list">
                <!-- OST Column -->
                <div class="competences-column">
                    <h4>OST</h4>
                    <div class="competence-item">
                        <input type="checkbox" id="comp_ost1" name="competences[]" value="OST1"
                               <?php echo in_array('OST1', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">OST1</div>
                            <div class="competence-text"><?php echo get_string('comp_ost1_desc', 'gestionprojet'); ?></div>
                        </div>
                    </div>

                    <div class="competence-item">
                        <input type="checkbox" id="comp_ost2" name="competences[]" value="OST2"
                               <?php echo in_array('OST2', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">OST2</div>
                            <div class="competence-text"><?php echo get_string('comp_ost2_desc', 'gestionprojet'); ?></div>
                        </div>
                    </div>

                    <div class="competence-item">
                        <input type="checkbox" id="comp_ost3" name="competences[]" value="OST3"
                               <?php echo in_array('OST3', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">OST3</div>
                            <div class="competence-text"><?php echo get_string('comp_ost3_desc', 'gestionprojet'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- SFC Column -->
                <div class="competences-column">
                    <h4>SFC</h4>
                    <div class="competence-item">
                        <input type="checkbox" id="comp_sfc1" name="competences[]" value="SFC1"
                               <?php echo in_array('SFC1', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">SFC1</div>
                            <div class="competence-text"><?php echo get_string('comp_sfc1_desc', 'gestionprojet'); ?></div>
                        </div>
                    </div>

                    <div class="competence-item">
                        <input type="checkbox" id="comp_sfc2" name="competences[]" value="SFC2"
                               <?php echo in_array('SFC2', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">SFC2</div>
                            <div class="competence-text"><?php echo get_string('comp_sfc2_desc', 'gestionprojet'); ?></div>
                        </div>
                    </div>

                    <div class="competence-item">
                        <input type="checkbox" id="comp_sfc3" name="competences[]" value="SFC3"
                               <?php echo in_array('SFC3', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">SFC3</div>
                            <div class="competence-text"><?php echo get_string('comp_sfc3_desc', 'gestionprojet'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- CCRI Column -->
                <div class="competences-column">
                    <h4>CCRI</h4>
                    <div class="competence-item">
                        <input type="checkbox" id="comp_ccri1" name="competences[]" value="CCRI1"
                               <?php echo in_array('CCRI1', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">CCRI1</div>
                            <div class="competence-text"><?php echo get_string('comp_ccri1_desc', 'gestionprojet'); ?></div>
                        </div>
                    </div>

                    <div class="competence-item">
                        <input type="checkbox" id="comp_ccri2" name="competences[]" value="CCRI2"
                               <?php echo in_array('CCRI2', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">CCRI2</div>
                            <div class="competence-text"><?php echo get_string('comp_ccri2_desc', 'gestionprojet'); ?></div>
                        </div>
                    </div>

                    <div class="competence-item">
                        <input type="checkbox" id="comp_ccri3" name="competences[]" value="CCRI3"
                               <?php echo in_array('CCRI3', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">CCRI3</div>
                            <div class="competence-text"><?php echo get_string('comp_ccri3_desc', 'gestionprojet'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="button-container">
        <button type="button" id="exportPdfBtn" class="btn-export btn-export-margin">
            📄 <?php echo get_string('export_pdf', 'gestionprojet'); ?>
        </button>
    </div>
</div>

<?php
$PAGE->requires->js_call_amd('mod_gestionprojet/step1', 'init', [[
    'cmid' => (int)$cm->id,
    'step' => 1,
    'groupid' => 0,
    'autosaveInterval' => (int)$gestionprojet->autosave_interval * 1000,
    'isReadonly' => (bool)$readonly,
    'strings' => [
        'export_pdf_coming_soon' => get_string('step1_export_pdf_todo', 'gestionprojet'),
    ],
]]);

echo $OUTPUT->footer();
?>
