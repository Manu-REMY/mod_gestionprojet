<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 1: Fiche Descriptive du Projet
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
?>



<div class="step1-container">
    <div class="navigation-buttons">
        <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]); ?>" class="nav-btn nav-btn-back">
            ← <?php echo get_string('home', 'gestionprojet'); ?>
        </a>
        <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 2]); ?>" class="nav-btn">
            <?php echo get_string('step2', 'gestionprojet'); ?> →
        </a>
    </div>

    <div class="title-container">
        <h2>📋 <?php echo get_string('step1', 'gestionprojet'); ?></h2>

        <!-- Lock toggle removed -->

    </div>

    <!-- Locked alert removed -->


    <div class="description-info">
        <h3>Objectif</h3>
        <p>La fiche descriptive permet de cadrer le projet en définissant son intitulé, son niveau, les compétences travaillées et les modalités d'évaluation.</p>
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
                            <option value="">-- Choisir --</option>
                            <option value="6ème" <?php echo ($description->niveau ?? '') == '6ème' ? 'selected' : ''; ?>>6ème</option>
                            <option value="5ème" <?php echo ($description->niveau ?? '') == '5ème' ? 'selected' : ''; ?>>5ème</option>
                            <option value="4ème" <?php echo ($description->niveau ?? '') == '4ème' ? 'selected' : ''; ?>>4ème</option>
                            <option value="3ème" <?php echo ($description->niveau ?? '') == '3ème' ? 'selected' : ''; ?>>3ème</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="duree"><?php echo get_string('duree', 'gestionprojet'); ?> *</label>
                        <input type="text" id="duree" name="duree"
                               value="<?php echo s($description->duree ?? ''); ?>"
                               placeholder="ex: 12 semaines" required>
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
                <p style="font-size: 14px; color: #666; margin: 10px 0;"><?php echo get_string('image_optional', 'gestionprojet'); ?></p>

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
                            echo html_writer::img($url, get_string('image', 'gestionprojet'), ['class' => 'image-preview']);
                        }
                        ?>
                    </div>
                <?php else: ?>
                    <div style="padding: 40px; color: #999; font-style: italic;">
                        <?php echo get_string('no_image_available', 'gestionprojet'); ?>
                    </div>
                <?php endif; ?>

                <!-- TODO: Implement file upload with Moodle file API -->
                <p style="font-size: 12px; color: #999; margin-top: 10px;">
                    <?php echo get_string('upload_feature_pending', 'gestionprojet'); ?>
                </p>
            </div>
        </div>

        <!-- Compétences -->
        <div class="competences-section">
            <h3><?php echo get_string('competences', 'gestionprojet'); ?></h3>

            <div class="competences-list">
                <!-- Colonne OST -->
                <div class="competences-column">
                    <h4>OST</h4>
                    <div class="competence-item">
                        <input type="checkbox" id="comp_ost1" name="competences[]" value="OST1"
                               <?php echo in_array('OST1', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">OST1</div>
                            <div class="competence-text">Décrire les liens entre usages et évolutions technologiques des objets et des systèmes techniques</div>
                        </div>
                    </div>

                    <div class="competence-item">
                        <input type="checkbox" id="comp_ost2" name="competences[]" value="OST2"
                               <?php echo in_array('OST2', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">OST2</div>
                            <div class="competence-text">Décrire les interactions entre un objet ou un système technique, son environnement et les utilisateurs</div>
                        </div>
                    </div>

                    <div class="competence-item">
                        <input type="checkbox" id="comp_ost3" name="competences[]" value="OST3"
                               <?php echo in_array('OST3', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">OST3</div>
                            <div class="competence-text">Caractériser et choisir un objet ou un système technique selon différents critères</div>
                        </div>
                    </div>
                </div>

                <!-- Colonne SFC -->
                <div class="competences-column">
                    <h4>SFC</h4>
                    <div class="competence-item">
                        <input type="checkbox" id="comp_sfc1" name="competences[]" value="SFC1"
                               <?php echo in_array('SFC1', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">SFC1</div>
                            <div class="competence-text">Décrire et caractériser l'organisation interne d'un objet ou d'un système technique et ses échanges avec son environnement (énergies, données)</div>
                        </div>
                    </div>

                    <div class="competence-item">
                        <input type="checkbox" id="comp_sfc2" name="competences[]" value="SFC2"
                               <?php echo in_array('SFC2', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">SFC2</div>
                            <div class="competence-text">Identifier un dysfonctionnement d'un objet technique et y remédier</div>
                        </div>
                    </div>

                    <div class="competence-item">
                        <input type="checkbox" id="comp_sfc3" name="competences[]" value="SFC3"
                               <?php echo in_array('SFC3', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">SFC3</div>
                            <div class="competence-text">Comprendre et modifier un programme associé à une fonctionnalité d'un objet ou d'un système technique</div>
                        </div>
                    </div>
                </div>

                <!-- Colonne CCRI -->
                <div class="competences-column">
                    <h4>CCRI</h4>
                    <div class="competence-item">
                        <input type="checkbox" id="comp_ccri1" name="competences[]" value="CCRI1"
                               <?php echo in_array('CCRI1', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">CCRI1</div>
                            <div class="competence-text">Imaginer, concevoir et réaliser une ou des solutions en réponse à un besoin, à des exigences (de développement durable, par exemple) ou à la nécessité d'améliorations dans une démarche de créativité</div>
                        </div>
                    </div>

                    <div class="competence-item">
                        <input type="checkbox" id="comp_ccri2" name="competences[]" value="CCRI2"
                               <?php echo in_array('CCRI2', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">CCRI2</div>
                            <div class="competence-text">Valider les solutions techniques par des simulations ou par des protocoles de tests</div>
                        </div>
                    </div>

                    <div class="competence-item">
                        <input type="checkbox" id="comp_ccri3" name="competences[]" value="CCRI3"
                               <?php echo in_array('CCRI3', $competences) ? 'checked' : ''; ?>>
                        <div class="competence-content">
                            <div class="competence-code">CCRI3</div>
                            <div class="competence-text">Concevoir, écrire, tester et mettre au point un programme</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="button-container">
        <button type="button" class="btn-export" onclick="exportToPDF()">
            📄 <?php echo get_string('export_pdf', 'gestionprojet'); ?>
        </button>
    </div>
</div>

<?php
// Ensure jQuery is loaded
$PAGE->requires->jquery();
?>


<script>
// Wait for jQuery to be loaded
// Wait for RequireJS to be loaded
(function waitRequire() {
    if (typeof require === 'undefined') {
        setTimeout(waitRequire, 50);
        return;
    }
    
    require(['jquery', 'mod_gestionprojet/autosave'], function($, Autosave) {
        <?php if (!$readonly): ?>
        var cmid = <?php echo $cm->id; ?>;
        var step = 1;
        var autosaveInterval = <?php echo $gestionprojet->autosave_interval * 1000; ?>;

        // Custom serialization for step 1 (handling competences array)
        var serializeData = function() {
            var formData = {};
            var form = document.getElementById('descriptionForm');

            // Collect regular fields
            form.querySelectorAll('input[type="text"], select, textarea').forEach(function(field) {
                if (field.name && !field.name.includes('[]')) {
                    formData[field.name] = field.value;
                }
            });

            // Collect competences as array
            var competences = [];
            form.querySelectorAll('input[name="competences[]"]:checked').forEach(function(cb) {
                competences.push(cb.value);
            });
            formData['competences'] = JSON.stringify(competences);

            // Include lock state if present
            // Lock state removed

            
            return formData;
        };

        Autosave.init({
            cmid: cmid,
            step: step,
            groupid: 0,
            interval: autosaveInterval,
            formSelector: '#descriptionForm',
            serialize: serializeData
        });
        <?php endif; ?>

        // Lock form elements if readonly
        <?php if ($readonly): ?>
        $('#descriptionForm input, #descriptionForm select, #descriptionForm textarea').prop('disabled', true);
        <?php endif; ?>
    });
})();


// Export PDF functionality (to be implemented with TCPDF)
function exportToPDF() {
    alert('<?php echo get_string('pdf_export_pending', 'gestionprojet'); ?>');
    // TODO: Implement server-side PDF generation
    window.location.href = M.cfg.wwwroot + '/mod/gestionprojet/export_pdf.php?id=<?php echo $cm->id; ?>&step=1';
}

// Custom handling for checkbox arrays
</script>

<?php
echo $OUTPUT->footer();
?>
