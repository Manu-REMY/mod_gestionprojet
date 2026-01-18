<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 2: Expression du Besoin (Bête à Corne) - Teacher configuration page
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:configureteacherpages', $context);

$PAGE->set_url(new moodle_url('/mod/gestionprojet/pages/step2.php', ['cmid' => $cm->id]));
$PAGE->set_title(get_string('step2', 'gestionprojet'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

// Get existing data
$besoin = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);

// Display grade and feedback if available (for teachers viewing their own work)
$showGrade = false;

echo $OUTPUT->header();

// Navigation buttons
echo '<div class="navigation-container" style="display: flex; justify-content: space-between; margin-bottom: 20px; gap: 15px;">';
echo '<div>';
echo '<a href="../view.php?id=' . $cm->id . '" class="btn btn-secondary">← ' . get_string('back') . '</a>';
echo '</div>';
echo '<div>';
echo '<a href="step3.php?cmid=' . $cm->id . '" class="btn btn-primary">' . get_string('next') . ' →</a>';
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
$locked = $besoin ? $besoin->locked : 0;
?>

<div class="card mb-3">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="margin: 0;"><?php echo get_string('bete_a_corne_diagram', 'gestionprojet'); ?></h4>
            <div>
                <label class="switch" style="position: relative; display: inline-block; width: 60px; height: 34px;">
                    <input type="checkbox" id="lockToggle" <?php echo $locked ? 'checked' : ''; ?>>
                    <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: 0.4s; border-radius: 34px;"></span>
                </label>
                <span style="margin-left: 10px;"><?php echo get_string('lock_page', 'gestionprojet'); ?></span>
            </div>
        </div>

        <div id="diagramContainer" style="background: #fafbfc; padding: 20px; border-radius: 10px; border: 2px solid #e9ecef; margin-bottom: 30px;">
            <svg id="beteACorneCanvas" style="width: 100%; height: 500px;"></svg>
        </div>

        <form id="besoinForm">
            <div class="form-group mb-3">
                <label for="aqui" style="font-weight: 600; color: #667eea;"><?php echo get_string('aqui', 'gestionprojet'); ?></label>
                <small class="form-text text-muted"><?php echo get_string('aqui_help', 'gestionprojet'); ?></small>
                <textarea class="form-control" id="aqui" name="aqui" rows="3"
                    <?php echo $locked ? 'readonly' : ''; ?>><?php echo $besoin ? s($besoin->aqui) : ''; ?></textarea>
            </div>

            <div class="form-group mb-3">
                <label for="surquoi" style="font-weight: 600; color: #667eea;"><?php echo get_string('surquoi', 'gestionprojet'); ?></label>
                <small class="form-text text-muted"><?php echo get_string('surquoi_help', 'gestionprojet'); ?></small>
                <textarea class="form-control" id="surquoi" name="surquoi" rows="3"
                    <?php echo $locked ? 'readonly' : ''; ?>><?php echo $besoin ? s($besoin->surquoi) : ''; ?></textarea>
            </div>

            <div class="form-group mb-3">
                <label for="dansquelbut" style="font-weight: 600; color: #667eea;"><?php echo get_string('dansquelbut', 'gestionprojet'); ?></label>
                <small class="form-text text-muted"><?php echo get_string('dansquelbut_help', 'gestionprojet'); ?></small>
                <textarea class="form-control" id="dansquelbut" name="dansquelbut" rows="3"
                    <?php echo $locked ? 'readonly' : ''; ?>><?php echo $besoin ? s($besoin->dansquelbut) : ''; ?></textarea>
            </div>
        </form>

        <div id="autosaveIndicator" class="text-muted small" style="margin-top: 10px;"></div>
    </div>
</div>

<style>
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider:before {
    position: absolute;
    content: "🔓";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: 0.4s;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

input:checked + .slider {
    background-color: #667eea;
}

input:checked + .slider:before {
    transform: translateX(26px);
    content: "🔒";
}
</style>

<script>
require(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    const cmid = <?php echo $cm->id; ?>;
    const step = 2;
    const autosaveInterval = <?php echo $gestionprojet->autosave_interval * 1000; ?>;
    let autosaveTimer;
    let isLocked = <?php echo $locked ? 'true' : 'false'; ?>;

    // Lock toggle
    $('#lockToggle').on('change', function() {
        isLocked = this.checked;
        updateFormLockState();
        triggerAutosave();
    });

    function updateFormLockState() {
        if (isLocked) {
            $('#besoinForm textarea').attr('readonly', true);
        } else {
            $('#besoinForm textarea').attr('readonly', false);
        }
    }

    // Update diagram when text changes
    $('#besoinForm textarea').on('input', function() {
        updateDiagram();
        triggerAutosave();
    });

    function triggerAutosave() {
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(function() {
            autosave();
        }, autosaveInterval);
    }

    // Collect form data
    window.collectFormData = function() {
        const formData = {};
        $('#besoinForm textarea').each(function() {
            if (this.name) {
                formData[this.name] = this.value;
            }
        });
        formData['locked'] = isLocked ? 1 : 0;
        return formData;
    };

    function autosave() {
        const formData = collectFormData();

        $('#autosaveIndicator').html('<i class="fa fa-spinner fa-spin"></i> <?php echo get_string('autosaving', 'gestionprojet'); ?>');

        Ajax.call([{
            methodname: 'core_form_dynamic_form',
            args: {
                form: 'mod_gestionprojet\\form\\autosave_form',
                formdata: JSON.stringify({
                    cmid: cmid,
                    step: step,
                    data: JSON.stringify(formData)
                })
            },
            done: function(response) {
                $('#autosaveIndicator').html('<i class="fa fa-check text-success"></i> <?php echo get_string('autosaved', 'gestionprojet'); ?>');
                setTimeout(function() {
                    $('#autosaveIndicator').html('');
                }, 3000);
            },
            fail: function(error) {
                // Use AJAX endpoint directly
                $.ajax({
                    url: '../ajax/autosave.php',
                    type: 'POST',
                    data: {
                        cmid: cmid,
                        step: step,
                        data: JSON.stringify(formData)
                    },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            $('#autosaveIndicator').html('<i class="fa fa-check text-success"></i> <?php echo get_string('autosaved', 'gestionprojet'); ?>');
                        } else {
                            $('#autosaveIndicator').html('<i class="fa fa-exclamation-triangle text-warning"></i> ' + result.message);
                        }
                        setTimeout(function() {
                            $('#autosaveIndicator').html('');
                        }, 3000);
                    },
                    error: function() {
                        $('#autosaveIndicator').html('<i class="fa fa-exclamation-triangle text-danger"></i> <?php echo get_string('autosave_failed', 'gestionprojet'); ?>');
                    }
                });
            }
        }]);
    }

    // Simplified Bête à Corne SVG diagram
    function wrapText(text, maxLength) {
        if (!text) return [''];
        const words = text.split(' ');
        const lines = [];
        let currentLine = '';

        words.forEach(word => {
            if ((currentLine + word).length <= maxLength) {
                currentLine += (currentLine ? ' ' : '') + word;
            } else {
                if (currentLine) lines.push(currentLine);
                currentLine = word;
            }
        });
        if (currentLine) lines.push(currentLine);
        return lines;
    }

    function updateDiagram() {
        const svg = document.getElementById('beteACorneCanvas');
        const width = svg.clientWidth;
        const height = 500;

        // Clear SVG
        svg.innerHTML = '';
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);

        // Define arrow marker
        const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        const marker = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
        marker.setAttribute('id', 'arrowhead');
        marker.setAttribute('markerWidth', '10');
        marker.setAttribute('markerHeight', '10');
        marker.setAttribute('refX', '9');
        marker.setAttribute('refY', '3');
        marker.setAttribute('orient', 'auto');

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', 'M0,0 L0,6 L9,3 z');
        path.setAttribute('fill', '#e91e63');
        marker.appendChild(path);
        defs.appendChild(marker);
        svg.appendChild(defs);

        const centerX = width / 2;
        const topY = 70;
        const centerY = 240;
        const bottomY = 360;
        const boxWidth = 250;
        const boxHeight = 90;
        const productWidth = 220;
        const productHeight = 110;
        const spacing = 160;

        // Get values
        const aquiText = document.getElementById('aqui').value || '';
        const surquoiText = document.getElementById('surquoi').value || '';
        const dansquelbutText = document.getElementById('dansquelbut').value || '';

        // Left ellipse - À qui rend-il service ?
        const leftBoxX = centerX - spacing - boxWidth / 2;
        const leftBoxY = topY;
        const leftCenterX = leftBoxX + boxWidth / 2;
        const leftCenterY = leftBoxY + boxHeight / 2;

        const leftBox = document.createElementNS('http://www.w3.org/2000/svg', 'ellipse');
        leftBox.setAttribute('cx', leftCenterX);
        leftBox.setAttribute('cy', leftCenterY);
        leftBox.setAttribute('rx', boxWidth / 2);
        leftBox.setAttribute('ry', boxHeight / 2);
        leftBox.setAttribute('fill', 'white');
        leftBox.setAttribute('stroke', '#4fc3f7');
        leftBox.setAttribute('stroke-width', '2.5');
        svg.appendChild(leftBox);

        // Left content
        const leftLines = wrapText(aquiText, 30);
        const leftTextStartY = leftBoxY + boxHeight / 2 - (leftLines.length * 8);
        leftLines.forEach((line, i) => {
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', leftCenterX);
            text.setAttribute('y', leftTextStartY + i * 18);
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('fill', '#555');
            text.setAttribute('font-size', '13');
            text.textContent = line;
            svg.appendChild(text);
        });

        // Left title
        const leftTitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        leftTitle.setAttribute('x', leftCenterX);
        leftTitle.setAttribute('y', leftBoxY - 25);
        leftTitle.setAttribute('text-anchor', 'middle');
        leftTitle.setAttribute('fill', '#333');
        leftTitle.setAttribute('font-size', '15');
        leftTitle.setAttribute('font-weight', '700');
        leftTitle.textContent = 'À qui le produit rend-il service ?';
        svg.appendChild(leftTitle);

        const leftSubtitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        leftSubtitle.setAttribute('x', leftCenterX);
        leftSubtitle.setAttribute('y', leftBoxY - 8);
        leftSubtitle.setAttribute('text-anchor', 'middle');
        leftSubtitle.setAttribute('fill', '#666');
        leftSubtitle.setAttribute('font-size', '12');
        leftSubtitle.textContent = '(utilisateur)';
        svg.appendChild(leftSubtitle);

        // Right ellipse - Sur quoi agit-il ?
        const rightBoxX = centerX + spacing - boxWidth / 2;
        const rightBoxY = topY;
        const rightCenterX = rightBoxX + boxWidth / 2;
        const rightCenterY = rightBoxY + boxHeight / 2;

        const rightBox = document.createElementNS('http://www.w3.org/2000/svg', 'ellipse');
        rightBox.setAttribute('cx', rightCenterX);
        rightBox.setAttribute('cy', rightCenterY);
        rightBox.setAttribute('rx', boxWidth / 2);
        rightBox.setAttribute('ry', boxHeight / 2);
        rightBox.setAttribute('fill', 'white');
        rightBox.setAttribute('stroke', '#4fc3f7');
        rightBox.setAttribute('stroke-width', '2.5');
        svg.appendChild(rightBox);

        // Right content
        const rightLines = wrapText(surquoiText, 30);
        const rightTextStartY = rightBoxY + boxHeight / 2 - (rightLines.length * 8);
        rightLines.forEach((line, i) => {
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', rightCenterX);
            text.setAttribute('y', rightTextStartY + i * 18);
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('fill', '#555');
            text.setAttribute('font-size', '13');
            text.textContent = line;
            svg.appendChild(text);
        });

        // Right title
        const rightTitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        rightTitle.setAttribute('x', rightCenterX);
        rightTitle.setAttribute('y', rightBoxY - 25);
        rightTitle.setAttribute('text-anchor', 'middle');
        rightTitle.setAttribute('fill', '#333');
        rightTitle.setAttribute('font-size', '15');
        rightTitle.setAttribute('font-weight', '700');
        rightTitle.textContent = 'Sur quoi agit-il ?';
        svg.appendChild(rightTitle);

        const rightSubtitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        rightSubtitle.setAttribute('x', rightCenterX);
        rightSubtitle.setAttribute('y', rightBoxY - 8);
        rightSubtitle.setAttribute('text-anchor', 'middle');
        rightSubtitle.setAttribute('fill', '#666');
        rightSubtitle.setAttribute('font-size', '12');
        rightSubtitle.textContent = '(matière d\'œuvre)';
        svg.appendChild(rightSubtitle);

        // Product box in center
        const productX = centerX - productWidth / 2;
        const productY = centerY - productHeight / 2;

        const productBox = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        productBox.setAttribute('x', productX);
        productBox.setAttribute('y', productY);
        productBox.setAttribute('width', productWidth);
        productBox.setAttribute('height', productHeight);
        productBox.setAttribute('rx', '25');
        productBox.setAttribute('fill', '#667eea');
        productBox.setAttribute('stroke', '#764ba2');
        productBox.setAttribute('stroke-width', '2.5');
        svg.appendChild(productBox);

        const productLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        productLabel.setAttribute('x', centerX);
        productLabel.setAttribute('y', centerY - 8);
        productLabel.setAttribute('text-anchor', 'middle');
        productLabel.setAttribute('fill', 'white');
        productLabel.setAttribute('font-size', '20');
        productLabel.setAttribute('font-weight', 'bold');
        productLabel.textContent = 'Produit';
        svg.appendChild(productLabel);

        const productSubLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        productSubLabel.setAttribute('x', centerX);
        productSubLabel.setAttribute('y', centerY + 12);
        productSubLabel.setAttribute('text-anchor', 'middle');
        productSubLabel.setAttribute('fill', 'white');
        productSubLabel.setAttribute('font-size', '14');
        productSubLabel.textContent = '(objet technique)';
        svg.appendChild(productSubLabel);

        // Bottom rectangle - Dans quel but ?
        const bottomBoxX = centerX - boxWidth / 2;
        const bottomBoxY = bottomY;
        const bottomCenterX = bottomBoxX + boxWidth / 2;
        const bottomCenterY = bottomBoxY + boxHeight / 2;

        const bottomBox = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        bottomBox.setAttribute('x', bottomBoxX);
        bottomBox.setAttribute('y', bottomBoxY);
        bottomBox.setAttribute('width', boxWidth);
        bottomBox.setAttribute('height', boxHeight);
        bottomBox.setAttribute('rx', '10');
        bottomBox.setAttribute('fill', 'white');
        bottomBox.setAttribute('stroke', '#4fc3f7');
        bottomBox.setAttribute('stroke-width', '2.5');
        svg.appendChild(bottomBox);

        // Bottom content
        const bottomLines = wrapText(dansquelbutText, 30);
        const bottomTextStartY = bottomBoxY + boxHeight / 2 - (bottomLines.length * 8);
        bottomLines.forEach((line, i) => {
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', bottomCenterX);
            text.setAttribute('y', bottomTextStartY + i * 18);
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('fill', '#555');
            text.setAttribute('font-size', '13');
            text.textContent = line;
            svg.appendChild(text);
        });

        // Bottom title
        const bottomTitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        bottomTitle.setAttribute('x', bottomCenterX);
        bottomTitle.setAttribute('y', bottomBoxY + boxHeight + 20);
        bottomTitle.setAttribute('text-anchor', 'middle');
        bottomTitle.setAttribute('fill', '#333');
        bottomTitle.setAttribute('font-size', '15');
        bottomTitle.setAttribute('font-weight', '700');
        bottomTitle.textContent = 'Dans quel but ?';
        svg.appendChild(bottomTitle);

        const bottomSubtitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        bottomSubtitle.setAttribute('x', bottomCenterX);
        bottomSubtitle.setAttribute('y', bottomBoxY + boxHeight + 37);
        bottomSubtitle.setAttribute('text-anchor', 'middle');
        bottomSubtitle.setAttribute('fill', '#666');
        bottomSubtitle.setAttribute('font-size', '12');
        bottomSubtitle.textContent = '(fonction d\'usage ou besoin)';
        svg.appendChild(bottomSubtitle);

        // Simplified "horn" curve
        const topCurveStartX = leftCenterX;
        const topCurveStartY = leftCenterY + boxHeight / 2;
        const topCurveEndX = rightCenterX;
        const topCurveEndY = rightCenterY + boxHeight / 2;
        const topCurveControlX = centerX;
        const topCurveControlY = 235;

        const topCurve = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        topCurve.setAttribute('d', `M ${topCurveStartX} ${topCurveStartY} Q ${topCurveControlX} ${topCurveControlY} ${topCurveEndX} ${topCurveEndY}`);
        topCurve.setAttribute('stroke', '#e91e63');
        topCurve.setAttribute('stroke-width', '3');
        topCurve.setAttribute('fill', 'none');
        svg.appendChild(topCurve);

        // Circles at curve ends
        const leftCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        leftCircle.setAttribute('cx', topCurveStartX);
        leftCircle.setAttribute('cy', topCurveStartY);
        leftCircle.setAttribute('r', '5');
        leftCircle.setAttribute('fill', '#e91e63');
        svg.appendChild(leftCircle);

        const rightCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        rightCircle.setAttribute('cx', topCurveEndX);
        rightCircle.setAttribute('cy', topCurveEndY);
        rightCircle.setAttribute('r', '5');
        rightCircle.setAttribute('fill', '#e91e63');
        svg.appendChild(rightCircle);

        // Simplified arrow from right to bottom
        const arrowStartX = rightCenterX - 30;
        const arrowStartY = rightCenterY + 60;
        const arrowEndX = bottomCenterX;
        const arrowEndY = bottomCenterY - boxHeight / 2;

        const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        arrow.setAttribute('x1', arrowStartX);
        arrow.setAttribute('y1', arrowStartY);
        arrow.setAttribute('x2', arrowEndX);
        arrow.setAttribute('y2', arrowEndY);
        arrow.setAttribute('stroke', '#e91e63');
        arrow.setAttribute('stroke-width', '3');
        arrow.setAttribute('marker-end', 'url(#arrowhead)');
        svg.appendChild(arrow);
    }

    // Initial diagram render
    updateDiagram();
});
</script>

<?php
echo $OUTPUT->footer();
