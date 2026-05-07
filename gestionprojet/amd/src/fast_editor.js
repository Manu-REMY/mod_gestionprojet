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
 * FAST editor — form CRUD, autosave bridge, diagram event emitter.
 *
 * @module     mod_gestionprojet/fast_editor
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/notification', 'core/str',
        'mod_gestionprojet/fast_diagram', 'mod_gestionprojet/toast'],
function($, Notification, Str, FastDiagram, Toast) {

    var STEP = 9;
    var AUTOSAVE_DELAY_MS = 10000;

    function emptyData() {
        return {
            fonctionsPrincipales: [],
            fonctions: [],
            populatedFromCdcf: false
        };
    }

    function nextId(items) {
        var max = 0;
        items.forEach(function(it) { if (it.id > max) { max = it.id; } });
        return max + 1;
    }

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function(k) {
                node.setAttribute(k, attrs[k]);
            });
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function input(value, dataField, placeholder) {
        var i = el('input', {
            type: 'text',
            'class': 'form-control',
            'data-field': dataField,
            placeholder: placeholder || ''
        });
        i.value = value || '';
        return i;
    }

    function btn(action, label, classes) {
        return el('button', {
            type: 'button',
            'class': 'btn btn-sm ' + (classes || ''),
            'data-action': action
        }, label);
    }

    function badge(text, classes) {
        return el('span', { 'class': 'badge ' + (classes || '') }, text);
    }

    function buildFt(ft, idx, strings) {
        var ftWrap = el('div', { 'class': 'fast-ft', 'data-ft-id': ft.id });
        var row = el('div', { 'class': 'd-flex align-items-start gap-2' });

        row.appendChild(badge('FT' + (idx + 1), 'badge-success'));

        var middle = el('div', { 'class': 'flex-grow-1' });
        var descInput = input(ft.description, 'ft-description', strings.ftPlaceholder);
        descInput.classList.add('mb-2');
        middle.appendChild(descInput);

        var hasSubs = ft.sousFonctions && ft.sousFonctions.length > 0;
        if (!hasSubs) {
            middle.appendChild(input(ft.solution, 'ft-solution', strings.solPlaceholder));
        }
        row.appendChild(middle);

        if (!hasSubs) {
            row.appendChild(btn('split', strings.split, 'btn-outline-info'));
        }
        row.appendChild(btn('remove-ft', '×', 'btn-outline-danger'));

        ftWrap.appendChild(row);

        if (hasSubs) {
            var sfList = el('div', { 'class': 'fast-sf-list ml-4 mt-2' });
            ft.sousFonctions.forEach(function(sf, sfIdx) {
                var sfWrap = el('div', { 'class': 'fast-sf p-2 mb-2 bg-light rounded', 'data-sf-id': sf.id });
                var sfRow = el('div', { 'class': 'd-flex align-items-start gap-2' });

                sfRow.appendChild(badge('FT' + (idx + 1) + '.' + (sfIdx + 1), 'badge-light'));

                var sfMid = el('div', { 'class': 'flex-grow-1' });
                var sfDesc = input(sf.description, 'sf-description', strings.sfPlaceholder);
                sfDesc.classList.add('mb-1');
                sfMid.appendChild(sfDesc);
                sfMid.appendChild(input(sf.solution, 'sf-solution', strings.solPlaceholder));
                sfRow.appendChild(sfMid);

                sfRow.appendChild(btn('remove-sf', '×', 'btn-outline-danger'));
                sfWrap.appendChild(sfRow);
                sfList.appendChild(sfWrap);
            });
            sfList.appendChild(btn('add-sf', '+ ' + strings.addSub, 'btn-outline-success'));
            ftWrap.appendChild(sfList);
        }

        return ftWrap;
    }

    function buildForm(data, strings) {
        var frag = document.createDocumentFragment();

        // Fonction Principale (read-only, sourced from CDCF).
        var fps = (data && data.fonctionsPrincipales) || [];
        var nonEmptyFps = fps.filter(function(fp) {
            return fp && fp.description && String(fp.description).trim() !== '';
        });
        if (nonEmptyFps.length > 0) {
            var fpSection = el('div', { 'class': 'fast-fp-section mb-3' });
            fpSection.appendChild(el('label', { 'class': 'font-weight-bold mb-1' }, strings.fpLabel));
            nonEmptyFps.forEach(function(fp, idx) {
                var fpItem = el('div', { 'class': 'fast-fp-item d-flex align-items-center gap-2 mb-1' });
                fpItem.appendChild(el('span', { 'class': 'badge badge-primary' }, 'FP' + (idx + 1)));
                fpItem.appendChild(el('span', { 'class': 'fast-fp-text' }, fp.description));
                fpSection.appendChild(fpItem);
            });
            fpSection.appendChild(el('small', { 'class': 'form-text text-muted' }, strings.fpHelp));
            frag.appendChild(fpSection);
        }

        var list = el('div', { 'class': 'fast-fonctions' });

        if (!data.fonctions || data.fonctions.length === 0) {
            list.appendChild(el('p', { 'class': 'text-muted text-center py-3' }, strings.placeholder));
        } else {
            data.fonctions.forEach(function(ft, idx) {
                list.appendChild(buildFt(ft, idx, strings));
            });
        }
        frag.appendChild(list);

        var addBtn = btn('add-ft', '+ ' + strings.addFn, 'btn-outline-success mt-2');
        frag.appendChild(addBtn);
        return frag;
    }

    function init(opts) {
        var cmid = opts.cmid;
        var mode = opts.mode;
        var sesskey = opts.sesskey;
        var groupid = opts.groupid || 0;

        var formContainer = document.getElementById('fast-form-' + cmid);
        var dataInput = document.getElementById('fast-data-' + cmid);
        var diagramId = 'fast-diagram-' + cmid;

        if (!formContainer || !dataInput) { return; }

        var stringRequests = [
            { key: 'fast:placeholder', component: 'mod_gestionprojet' },
            { key: 'fast:ft_description_placeholder', component: 'mod_gestionprojet' },
            { key: 'fast:sf_description_placeholder', component: 'mod_gestionprojet' },
            { key: 'fast:solution_placeholder', component: 'mod_gestionprojet' },
            { key: 'fast:add_function', component: 'mod_gestionprojet' },
            { key: 'fast:add_subfunction', component: 'mod_gestionprojet' },
            { key: 'fast:split', component: 'mod_gestionprojet' },
            { key: 'fast:fp_label', component: 'mod_gestionprojet' },
            { key: 'fast:fp_help', component: 'mod_gestionprojet' },
            { key: 'autosave_status_saving', component: 'mod_gestionprojet' },
            { key: 'autosave_status_saved', component: 'mod_gestionprojet' },
            { key: 'autosave_error', component: 'mod_gestionprojet' }
        ];

        Str.get_strings(stringRequests).then(function(loaded) {
            var strings = {
                placeholder: loaded[0],
                ftPlaceholder: loaded[1],
                sfPlaceholder: loaded[2],
                solPlaceholder: loaded[3],
                addFn: loaded[4],
                addSub: loaded[5],
                split: loaded[6],
                fpLabel: loaded[7],
                fpHelp: loaded[8],
                savingLabel: loaded[9],
                savedLabel: loaded[10],
                errorLabel: loaded[11]
            };
            startEditor(strings);
            return null;
        }).catch(Notification.exception);

        function startEditor(strings) {
            var data;
            try {
                data = JSON.parse(dataInput.value || '{}');
                if (!data.fonctions) { data = emptyData(); }
            } catch (e) {
                data = emptyData();
            }

            function rerender() {
                formContainer.replaceChildren(buildForm(data, strings));
                FastDiagram.render(diagramId, data);
                dataInput.value = JSON.stringify(data);
            }

            var isDirty = false;
            var saveInFlight = false;

            function autosave(opts) {
                opts = opts || {};
                var payload = { data_json: JSON.stringify(data) };
                if (mode === 'teacher') {
                    var aiInput = document.getElementById('fast-ai-' + cmid);
                    if (aiInput) {
                        payload.ai_instructions = aiInput.value;
                    }
                }
                if (opts.showSaving) {
                    Toast.info(strings.savingLabel || 'Enregistrement...', 1500);
                }
                saveInFlight = true;
                return $.post(M.cfg.wwwroot + '/mod/gestionprojet/ajax/autosave.php', {
                    cmid: cmid,
                    step: STEP,
                    mode: (mode === 'teacher' || mode === 'provided') ? mode : '',
                    groupid: groupid,
                    sesskey: sesskey,
                    data: JSON.stringify(payload)
                }).done(function(response) {
                    if (response && response.success) {
                        isDirty = false;
                        if (opts.showSaved !== false) {
                            Toast.success(strings.savedLabel || 'Enregistré', 1800);
                        }
                    } else {
                        Toast.error((response && response.message) || strings.errorLabel || 'Erreur de sauvegarde', 4000);
                    }
                }).fail(function(xhr) {
                    Toast.error(strings.errorLabel || 'Erreur de sauvegarde', 4000);
                }).always(function() {
                    saveInFlight = false;
                });
            }

            var saveTimer = null;
            function scheduleSave() {
                isDirty = true;
                if (saveTimer) { clearTimeout(saveTimer); }
                saveTimer = setTimeout(function() {
                    autosave({ showSaving: false, showSaved: true });
                }, AUTOSAVE_DELAY_MS);
            }

            function saveNow() {
                if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
                autosave({ showSaving: true, showSaved: true });
            }

            // Manual "Save" button (rendered in templates/step9_form.mustache).
            $(document).on('click', '#fast-save-' + cmid, function(e) {
                e.preventDefault();
                saveNow();
            });

            // Try to flush pending changes when the user navigates away.
            $(window).on('beforeunload', function() {
                if (isDirty && !saveInFlight) {
                    autosave({ showSaving: false, showSaved: false });
                }
            });

            $(formContainer).on('input', 'input', function(e) {
                var $input = $(e.target);
                var field = $input.data('field');
                var $ft = $input.closest('.fast-ft');
                var $sf = $input.closest('.fast-sf');
                var ftId = parseInt($ft.attr('data-ft-id'), 10);
                var ft = data.fonctions.find(function(f) { return f.id === ftId; });
                if (!ft) { return; }
                if (field === 'ft-description') { ft.description = $input.val(); }
                else if (field === 'ft-solution') { ft.solution = $input.val(); }
                else if ($sf.length) {
                    var sfId = parseInt($sf.attr('data-sf-id'), 10);
                    var sf = (ft.sousFonctions || []).find(function(s) { return s.id === sfId; });
                    if (!sf) { return; }
                    if (field === 'sf-description') { sf.description = $input.val(); }
                    else if (field === 'sf-solution') { sf.solution = $input.val(); }
                }
                FastDiagram.render(diagramId, data);
                dataInput.value = JSON.stringify(data);
                scheduleSave();
            });

            // Schedule autosave when the AI instructions textarea changes (teacher only).
            if (mode === 'teacher') {
                var aiTextarea = document.getElementById('fast-ai-' + cmid);
                if (aiTextarea) {
                    $(aiTextarea).on('input change', scheduleSave);
                }
            }

            $(formContainer).on('click', '[data-action]', function(e) {
                var action = $(e.currentTarget).data('action');
                var $ft = $(e.currentTarget).closest('.fast-ft');
                var ftId = parseInt($ft.attr('data-ft-id'), 10);
                var ft = data.fonctions.find(function(f) { return f.id === ftId; });

                if (action === 'add-ft') {
                    data.fonctions.push({
                        id: nextId(data.fonctions), description: '', solution: '', sousFonctions: []
                    });
                } else if (action === 'remove-ft') {
                    data.fonctions = data.fonctions.filter(function(f) { return f.id !== ftId; });
                } else if (action === 'split' && ft) {
                    ft.solution = '';
                    ft.sousFonctions = [
                        { id: 1, description: '', solution: '' },
                        { id: 2, description: '', solution: '' }
                    ];
                } else if (action === 'add-sf' && ft) {
                    ft.sousFonctions.push({
                        id: nextId(ft.sousFonctions), description: '', solution: ''
                    });
                } else if (action === 'remove-sf' && ft) {
                    var $sf = $(e.currentTarget).closest('.fast-sf');
                    var sfId = parseInt($sf.attr('data-sf-id'), 10);
                    ft.sousFonctions = ft.sousFonctions.filter(function(s) { return s.id !== sfId; });
                    if (ft.sousFonctions.length === 0) { ft.solution = ''; }
                }
                rerender();
                scheduleSave();
            });

            $('[data-action="populate-cdcf"]').on('click', function() {
                $.getJSON(M.cfg.wwwroot + '/mod/gestionprojet/ajax/fast_populate_cdcf.php', {
                    cmid: cmid, sesskey: sesskey
                }, function(resp) {
                    if (!resp || !resp.success) { return; }
                    var fps = resp.fonctionsPrincipales || [];
                    var fts = resp.fonctionsService || [];
                    data.fonctionsPrincipales = fps;
                    data.fonctions = fts.map(function(fs, idx) {
                        return {
                            id: idx + 1,
                            description: fs.description,
                            originCdcf: 'FS',
                            originIndex: fs.id,
                            solution: '',
                            sousFonctions: []
                        };
                    });
                    data.populatedFromCdcf = true;
                    rerender();
                    autosave();
                });
            });

            rerender();
        }
    }

    return { init: init };
});
