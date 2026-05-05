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
 * The submit button is intentionally NOT bound here: it is owned by
 * mod_gestionprojet/submission, loaded via student_submit_helper.php,
 * which opens a Bootstrap modal and triggers the AI evaluation server-side.
 *
 * @module     mod_gestionprojet/cdcf_bootstrap
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'mod_gestionprojet/cdcf', 'mod_gestionprojet/autosave'],
function($, Ajax, Cdcf, Autosave) {
    'use strict';

    function bootProvidedReadOnly() {
        var providedRoot = document.getElementById('cdcfProvidedRoot');
        if (!providedRoot) { return; }
        var raw = providedRoot.getAttribute('data-cdcf');
        if (!raw) { return; }
        var data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            return;
        }
        Cdcf.init({
            container: providedRoot,
            initialData: data,
            lang: window._gpCdcfLang || {},
            projetNom: providedRoot.getAttribute('data-projet') || '',
            isLocked: true,
            onChange: function() {},
        });
    }

    function init(cfg) {
        // Cache lang strings on window so the read-only mount (which has no cfg
        // of its own) can reuse them.
        window._gpCdcfLang = cfg.lang;

        bootProvidedReadOnly();

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
                interval: cfg.autosaveMs,
                formSelector: '#cdcfForm',
                serialize: function() {
                    return { interacteurs_data: dataField.value };
                },
            });
        }
    }

    return { init: init };
});
