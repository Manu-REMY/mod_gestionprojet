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
 * Live list of what is left to complete, in the progress panel of the student pages (v2.12.0).
 *
 * Step 4: computed from the CDCF hidden field (#cdcfDataField, with the consigne flags) at every change; a field
 * to fill in still empty, or a pre-filled one still as given or holding "…", is left; without consigne flags,
 * the empty statements and criteria. Steps 5 to 7: the named text fields of the form, by their label. A click on
 * an item scrolls to the field and puts the cursor in it.
 *
 * @module     mod_gestionprojet/progress_panel
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var PLACEHOLDER = /…|\.\.\.|[aà] compl[eé]ter/i;

    /**
     * Whether a field still needs the student.
     *
     * @param {string} value
     * @param {boolean} prefill Pre-filled field (compared with its consigne text).
     * @param {string} hint Consigne text of a pre-filled field.
     * @returns {boolean}
     */
    function isLeft(value, prefill, hint) {
        var v = (value || '').trim();
        if (v === '') {
            return true;
        }
        return prefill && (v === (hint || '').trim() || PLACEHOLDER.test(v));
    }

    /**
     * Fields of the CDCF (step 4).
     *
     * @param {Object} lang Labels.
     * @returns {Object} {total, left: [{label, key}]}
     */
    function cdcfFields(lang) {
        var field = document.getElementById('cdcfDataField');
        var data;
        try {
            data = JSON.parse(field ? field.value : '{}');
        } catch (e) {
            data = {};
        }
        var interactors = data.interactors || [];
        var functions = data.fonctionsService || [];
        var constraints = data.contraintes || [];
        var flagged = JSON.stringify(data).search(/"(gap|prefill|niveauGap|niveauPrefill)":true/) !== -1;
        var out = {total: 0, left: []};
        var consider = function(value, gap, prefill, hint, label, key) {
            if (flagged && !gap && !prefill) {
                return;
            }
            out.total++;
            if (isLeft(value, prefill, hint)) {
                out.left.push({label: label, key: key});
            }
        };
        if (flagged) {
            interactors.forEach(function(i) {
                consider(i.name, i.gap, i.prefill, i.hint, lang.interactor + ' I' + i.id, 'i-' + i.id);
            });
        }
        functions.forEach(function(fs, idx) {
            var name = 'FS' + (idx + 1);
            consider(fs.description, fs.gap, fs.prefill, fs.hint, name + ' · ' + lang.statement, 'fs-' + fs.id);
            (fs.criteres || []).forEach(function(c, cidx) {
                consider(c.description, c.gap, c.prefill, c.hint, name + ' · ' + lang.criterion + ' ' + (cidx + 1),
                    'c-' + fs.id + '-' + c.id);
                if (flagged) {
                    consider(c.niveau, c.niveauGap, c.niveauPrefill, c.niveauHint,
                        name + ' · ' + lang.level + ' ' + (cidx + 1), 'n-' + fs.id + '-' + c.id);
                }
            });
        });
        if (flagged) {
            constraints.forEach(function(c, idx) {
                consider(c.description, c.gap, c.prefill, c.hint, lang.constraint + ' C' + (idx + 1), 'k-' + c.id);
            });
        }
        return out;
    }

    /**
     * Named text fields of a form (steps 5 to 7).
     *
     * @param {string} selector Form selector.
     * @returns {Object} {total, left: [{label, element}]}
     */
    function formFields(selector) {
        var form = document.querySelector(selector);
        var out = {total: 0, left: []};
        if (!form) {
            return out;
        }
        Array.prototype.slice.call(form.querySelectorAll('textarea[name], input[name][type="text"], input[name][type="date"]'))
            .forEach(function(input) {
                var label = input.id ? form.querySelector('label[for="' + input.id + '"]') : null;
                if (!label || input.disabled || input.readOnly) {
                    return;
                }
                out.total++;
                if ((input.value || '').trim() === '') {
                    out.left.push({label: label.textContent.trim().replace(/\s*:\s*$/, ''), element: input});
                }
            });
        return out;
    }

    /**
     * Scroll to a field, put the cursor in it and show it for 2 s.
     *
     * @param {HTMLElement|null} target
     */
    function goTo(target) {
        if (!target) {
            return;
        }
        target.scrollIntoView({block: 'center', behavior: 'smooth'});
        target.focus();
        target.classList.add('gp-progress-target');
        setTimeout(function() { target.classList.remove('gp-progress-target'); }, 2000);
    }

    /**
     * Start the live list.
     *
     * @param {Object} cfg step, formSelector (steps 5 to 7), strings {count, done, statement, criterion, level,
     *     interactor, constraint}
     */
    function init(cfg) {
        var list = document.querySelector('[data-gp-progress-list]');
        var count = document.querySelector('[data-gp-progress-count]');
        var bar = document.querySelector('.gp-progress-bar');
        var fill = document.querySelector('.gp-progress-bar-fill');
        if (!list) {
            return;
        }
        var update = function() {
            var result = cfg.step === 4 ? cdcfFields(cfg.strings) : formFields(cfg.formSelector);
            var done = result.total - result.left.length;
            var percent = result.total ? Math.round(100 * done / result.total) : 100;
            if (count) {
                count.textContent = cfg.strings.count.replace('{left}', String(result.left.length))
                    .replace('{total}', String(result.total));
            }
            if (fill) {
                fill.style.width = percent + '%';
            }
            if (bar) {
                bar.setAttribute('aria-valuenow', String(percent));
            }
            var items = result.left.map(function(item) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.href = '#';
                a.textContent = '▢ ' + item.label;
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    goTo(item.element || document.querySelector('[data-gp-field="' + item.key + '"]'));
                });
                li.appendChild(a);
                return li;
            });
            if (!items.length) {
                var li = document.createElement('li');
                li.textContent = cfg.strings.done;
                items.push(li);
            }
            list.replaceChildren.apply(list, items);
        };
        // The CDCF editor fires "input" on its hidden field at every change; the forms fire it while typing.
        document.addEventListener('input', function(e) {
            if (cfg.step === 4 ? e.target.id === 'cdcfDataField' : !!e.target.closest(cfg.formSelector)) {
                update();
            }
        });
        setTimeout(update, 0);
    }

    return {init: init};
});
