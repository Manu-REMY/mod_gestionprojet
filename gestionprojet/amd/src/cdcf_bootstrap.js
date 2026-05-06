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

        // Reset-to-provided button.
        var resetBtn = document.getElementById('resetButton');
        if (resetBtn && cfg.resetEnabled && cfg.resetUrl) {
            resetBtn.addEventListener('click', function() {
                var lang = cfg.resetLang || {};
                var modalHtml = '' +
                    '<div class="modal fade" id="gpResetModal" tabindex="-1" role="dialog">' +
                    '  <div class="modal-dialog" role="document">' +
                    '    <div class="modal-content">' +
                    '      <div class="modal-header">' +
                    '        <h5 class="modal-title">' + escapeHtml(lang.modalTitle || 'Reset?') + '</h5>' +
                    '        <button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                    '          <span aria-hidden="true">&times;</span>' +
                    '        </button>' +
                    '      </div>' +
                    '      <div class="modal-body"><p>' + escapeHtml(lang.modalBody || '') + '</p></div>' +
                    '      <div class="modal-footer">' +
                    '        <button type="button" class="btn btn-secondary" data-dismiss="modal">' +
                              escapeHtml(lang.modalCancel || 'Cancel') + '</button>' +
                    '        <button type="button" class="btn btn-warning" id="gpResetConfirm">' +
                              escapeHtml(lang.modalConfirm || 'Reset') + '</button>' +
                    '      </div>' +
                    '    </div>' +
                    '  </div>' +
                    '</div>';
                var existing = document.getElementById('gpResetModal');
                if (existing) { existing.parentNode.removeChild(existing); }
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                var modalEl = document.getElementById('gpResetModal');
                $(modalEl).modal('show');
                $('#gpResetConfirm').on('click', function() {
                    var fd = new FormData();
                    fd.append('id', cfg.cmid);
                    fd.append('step', cfg.step);
                    fd.append('groupid', cfg.groupid || 0);
                    fd.append('sesskey', cfg.sesskey);
                    $('#gpResetConfirm').prop('disabled', true);
                    fetch(cfg.resetUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd,
                    }).then(function(r) {
                        return r.json().then(function(j) { return { ok: r.ok, body: j }; });
                    }).then(function(res) {
                        $(modalEl).modal('hide');
                        if (res.ok && res.body.success) {
                            window.location.reload();
                        } else {
                            window.alert((res.body && res.body.message) || (lang.genericError || 'Error'));
                            $('#gpResetConfirm').prop('disabled', false);
                        }
                    }).catch(function() {
                        $(modalEl).modal('hide');
                        window.alert(lang.genericError || 'Error');
                    });
                });
            });
        }

        function escapeHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    }

    return { init: init };
});
