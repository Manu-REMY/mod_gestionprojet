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
 * Bootstrap glue between step4*.php pages and the cdcf editor.
 *
 * Supports both the student page (submit/revert wiring) and the teacher
 * correction model page (manual save button + AI instructions textarea +
 * submission/deadline date inputs included in the autosave payload).
 *
 * The submit button is intentionally NOT bound here on the student page:
 * it is owned by mod_gestionprojet/submission, loaded via
 * student_submit_helper.php, which opens a Bootstrap modal and triggers
 * the AI evaluation server-side.
 *
 * @module     mod_gestionprojet/cdcf_bootstrap
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'mod_gestionprojet/cdcf', 'mod_gestionprojet/autosave'],
function($, Ajax, Cdcf, Autosave) {
    'use strict';

    function init(cfg) {
        var dataField = document.getElementById('cdcfDataField');
        var root = document.getElementById('cdcfRoot');
        if (!root || !dataField) {
            return;
        }

        Cdcf.init({
            container: root,
            initialData: cfg.initial,
            lang: cfg.lang,
            projetNom: cfg.projetNom,
            isLocked: cfg.isLocked,
            onChange: function(data) {
                dataField.value = JSON.stringify(data);
            },
        });

        $('#revertButton').on('click', function() {
            if (!window.confirm(cfg.confirmRevert)) {
                return;
            }
            Ajax.call([{
                methodname: 'mod_gestionprojet_submit_step',
                args: { cmid: cfg.cmid, step: cfg.step, action: 'revert' },
            }])[0].done(function(d) {
                if (d.success) {
                    window.location.reload();
                }
            });
        });

        if (!cfg.isLocked) {
            Autosave.init({
                cmid: cfg.cmid,
                step: cfg.step,
                groupid: cfg.groupid,
                mode: cfg.mode || undefined,
                interval: cfg.autosaveMs,
                formSelector: '#cdcfForm',
                serialize: function() {
                    var payload = { interacteurs_data: dataField.value };
                    var aiTextarea = document.getElementById('ai_instructions');
                    if (aiTextarea) { payload.ai_instructions = aiTextarea.value; }
                    var subDate = document.getElementById('submission_date');
                    if (subDate) { payload.submission_date = subDate.value; }
                    var deadDate = document.getElementById('deadline_date');
                    if (deadDate) { payload.deadline_date = deadDate.value; }
                    if (cfg.introTextSelector) {
                        var introEl = document.querySelector(cfg.introTextSelector);
                        if (introEl) { payload.intro_text = introEl.value; }
                    }
                    return payload;
                },
            });

            // Optional manual save button (teacher correction model flow):
            // saves immediately, then redirects back to the hub once the
            // request resolves so the teacher sees their model is persisted.
            var $saveBtn = $('#saveButton');
            if ($saveBtn.length && cfg.redirectAfterSave) {
                $saveBtn.on('click', function() {
                    var prev = Autosave.onSave;
                    Autosave.onSave = function(response) {
                        if (prev) { prev(response); }
                        window.setTimeout(function() {
                            window.location.href = cfg.redirectAfterSave;
                        }, 800);
                    };
                    Autosave.save();
                });
            }
        }
    }

    return { init: init };
});
