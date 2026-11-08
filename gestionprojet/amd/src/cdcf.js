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
 * CDCF editor (norm NF EN 16271) — interactors / FS / criteres / contraintes.
 *
 * @module     mod_gestionprojet/cdcf
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['mod_gestionprojet/cdcf_diagram', 'core/notification'], function(Diagram, Notification) {
    'use strict';

    function nextId(arr) {
        var m = 0;
        arr.forEach(function(x) { if (x.id > m) { m = x.id; } });
        return m + 1;
    }

    function el(tag, attrs, children) {
        var n = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function(k) {
                if (k === 'className') { n.className = attrs[k]; }
                else if (k === 'value') { n.value = attrs[k]; }
                else if (k === 'text') { n.textContent = attrs[k]; }
                else if (k.indexOf('on') === 0 && typeof attrs[k] === 'function') { n[k] = attrs[k]; }
                else { n.setAttribute(k, attrs[k]); }
            });
        }
        (children || []).forEach(function(c) {
            if (c == null) { return; }
            if (typeof c === 'string') { n.appendChild(document.createTextNode(c)); }
            else { n.appendChild(c); }
        });
        return n;
    }

    function init(opts) {
        var container = opts.container;
        var data = opts.initialData;
        var lang = opts.lang;
        var locked = !!opts.isLocked;
        // Instance option step4_flexibility: when off, criteria have no F0-F3 select.
        var hideFlexibility = opts.showFlexibility === false;
        // Consigne page (v2.11.1): the teacher marks each field "provided" (locked) or "to fill in" (gap).
        // Elsewhere the flags come from the consigne (consigne_lock::with_flags) and are applied to the student.
        var editFlags = !!opts.editFlags;
        // Level mode (v2.11.2): zones of a validated level, or of the level being judged, are frozen as a whole
        // (no change, no deletion, no addition). The server restores them anyway (palier::freeze).
        var frozenZones = opts.frozenZones || [];
        function frozen(zone) { return !editFlags && frozenZones.indexOf(zone) !== -1; }
        function freezeField(node) {
            node.disabled = true;
            node.classList.add('gp-cdcf-frozen');
            node.title = lang.frozenHelp || '';
        }
        var onChange = opts.onChange || function() {};

        function emit() { onChange(data); render(); }

        function fsReferencingInteractor(id) {
            return data.fonctionsService.filter(function(fs) {
                return fs.interactor1Id === id || fs.interactor2Id === id;
            });
        }

        /**
         * Teacher toggles of one field: "provided" and "to fill in", mutually exclusive.
         *
         * @param {Object} obj Element carrying the flags.
         * @param {string} lockKey Flag key of the lock (locked or niveauLocked).
         * @param {string} gapKey Flag key of the gap (gap or niveauGap).
         * @returns {HTMLElement|null}
         */
        function flagToggles(obj, lockKey, gapKey) {
            if (!editFlags) { return null; }
            // Three exclusive states (v2.11.3): provided, pre-filled (text written, editable), to fill in.
            var prefillKey = lockKey === 'locked' ? 'prefill' : 'niveauPrefill';
            var keys = [lockKey, prefillKey, gapKey];
            var wrap = el('span', { className: 'gp-cdcf-flags' });
            [[lockKey, '🔒 ' + lang.flagLocked, lang.flagLockedHelp],
             [prefillKey, '✎ ' + lang.flagPrefill, lang.flagPrefillHelp],
             [gapKey, '▢ ' + lang.flagGap, lang.flagGapHelp]].forEach(function(t) {
                var on = !!obj[t[0]];
                var b = el('button', {
                    type: 'button', className: 'gp-cdcf-flag' + (on ? ' gp-cdcf-flag-on' : ''),
                    text: t[1], title: t[2], 'aria-pressed': on ? 'true' : 'false',
                    onclick: function() {
                        var wason = !!obj[t[0]];
                        keys.forEach(function(k) { delete obj[k]; });
                        if (!wason) { obj[t[0]] = true; }
                        emit();
                    },
                });
                if (locked) { b.disabled = true; }
                wrap.appendChild(b);
            });
            return wrap;
        }

        /**
         * Teacher toggle of the links of an FS (its interactors), locked on their own (v2.11.3).
         *
         * @param {Object} fs
         * @returns {HTMLElement|null}
         */
        function linksToggle(fs) {
            if (!editFlags) { return null; }
            var on = !!fs.linksLocked;
            var b = el('button', {
                type: 'button', className: 'gp-cdcf-flag' + (on ? ' gp-cdcf-flag-on' : ''),
                text: '🔗 ' + lang.flagLinks, title: lang.flagLinksHelp, 'aria-pressed': on ? 'true' : 'false',
                onclick: function() {
                    if (fs.linksLocked) { delete fs.linksLocked; } else { fs.linksLocked = true; }
                    emit();
                },
            });
            if (locked) { b.disabled = true; }
            return b;
        }

        /**
         * State of a consigne field for the auto-mark: empty → to fill in, "…" or "A compléter" → pre-filled,
         * otherwise provided.
         *
         * @param {string} text
         * @returns {string} 'gap', 'prefill' or 'locked'
         */
        function autoState(text) {
            var t = (text || '').trim();
            if (t === '') { return 'gap'; }
            if (/…|\.\.\.|[aà] compl[eé]ter/i.test(t)) { return 'prefill'; }
            return 'locked';
        }

        /** Mark every field of the consigne automatically (the teacher adjusts afterwards). */
        function automark() {
            var set = function(obj, text, keys) {
                keys.forEach(function(k) { delete obj[k]; });
                var state = autoState(text);
                obj[keys[['locked', 'prefill', 'gap'].indexOf(state)]] = true;
            };
            var element = ['locked', 'prefill', 'gap'];
            var level = ['niveauLocked', 'niveauPrefill', 'niveauGap'];
            data.interactors.forEach(function(i) { set(i, i.name, element); });
            data.fonctionsService.forEach(function(fs) {
                set(fs, fs.description, element);
                if (fs.interactor1Id) { fs.linksLocked = true; }
                fs.criteres.forEach(function(c) {
                    set(c, c.description, element);
                    set(c, c.niveau, level);
                });
            });
            data.contraintes.forEach(function(c) { set(c, c.description, element); });
            emit();
        }

        /** True when the consigne locks something (a field, a level or links). */
        function hasLock() {
            var any = function(list, keys) {
                return list.some(function(o) { return keys.some(function(k) { return !!o[k]; }); });
            };
            var criteria = [];
            data.fonctionsService.forEach(function(fs) { criteria = criteria.concat(fs.criteres); });
            return any(data.interactors, ['locked']) || any(data.fonctionsService, ['locked', 'linksLocked'])
                || any(criteria, ['locked', 'niveauLocked']) || any(data.contraintes, ['locked']);
        }

        /** Number of pre-filled fields still as the consigne gave them, or still holding "…". */
        function prefillLeft() {
            var count = 0;
            var check = function(value, flag, hint) {
                if (!flag) { return; }
                var v = (value || '').trim();
                if (v === (hint || '').trim() || /…|\.\.\.|[aà] compl[eé]ter/i.test(v)) { count++; }
            };
            data.interactors.forEach(function(i) { check(i.name, i.prefill, i.hint); });
            data.fonctionsService.forEach(function(fs) {
                check(fs.description, fs.prefill, fs.hint);
                fs.criteres.forEach(function(c) {
                    check(c.description, c.prefill, c.hint);
                    check(c.niveau, c.niveauPrefill, c.niveauHint);
                });
            });
            data.contraintes.forEach(function(c) { check(c.description, c.prefill, c.hint); });
            return count;
        }

        /**
         * Notices above the editor: on the consigne page, the "nothing locked" warning and the auto-mark button;
         * for the student, the pre-filled fields still to rework.
         *
         * @returns {HTMLElement[]}
         */
        function notices() {
            var out = [];
            if (editFlags && !locked) {
                if (!hasLock()) {
                    out.push(el('div', { className: 'alert alert-warning gp-cdcf-nolock', text: '⚠ ' + lang.noLockWarning }));
                }
                out.push(el('button', { type: 'button', className: 'btn btn-secondary gp-cdcf-automark',
                    text: '⚙ ' + lang.automark, title: lang.automarkHelp,
                    onclick: function() {
                        // It replaces every state set by hand: confirm first.
                        Notification.saveCancelPromise(lang.automark, lang.automarkConfirm, lang.automark)
                            .then(automark)
                            .catch(function() { return null; });
                    } }));
            } else if (!editFlags && !locked) {
                var left = prefillLeft();
                if (left > 0) {
                    out.push(el('div', { className: 'alert alert-info gp-cdcf-prefill-notice',
                        text: '✎ ' + lang.prefillNotice.replace('{$a}', String(left)) }));
                }
            }
            return out;
        }

        /**
         * Show the flags of a field: style in both modes; for the student, a locked field is read-only
         * and a field to fill in shows the teacher's hint.
         *
         * @param {HTMLElement} input Field.
         * @param {Object} obj Element carrying the flags.
         * @param {string} lockKey
         * @param {string} gapKey
         * @param {string|null} hintKey Key of the hint (hint or niveauHint), null for no hint.
         * @returns {HTMLElement|null} Badge "provided" to show next to a locked field (student only).
         */
        function applyFlags(input, obj, lockKey, gapKey, hintKey) {
            if (obj[lockKey]) {
                input.classList.add('gp-cdcf-locked');
                if (!editFlags) {
                    input.disabled = true;
                    input.title = lang.flagLockedHelp;
                    return el('span', { className: 'gp-cdcf-lock-badge', text: '🔒 ' + lang.flagLocked,
                        title: lang.flagLockedHelp });
                }
            } else if (obj[gapKey]) {
                input.classList.add('gp-cdcf-gap');
                if (!editFlags) {
                    var hint = hintKey && obj[hintKey] ? obj[hintKey] : '';
                    input.placeholder = hint ? lang.gapPlaceholder + ' — ' + hint : lang.gapPlaceholder;
                }
            } else if (obj[lockKey === 'locked' ? 'prefill' : 'niveauPrefill']) {
                // Pre-filled: the consigne text is in the field and stays editable (no lock).
                input.classList.add('gp-cdcf-prefill');
                if (!editFlags) {
                    input.title = lang.flagPrefillHelp;
                    return el('span', { className: 'gp-cdcf-prefill-badge', text: '✎ ' + lang.prefillBadge,
                        title: lang.flagPrefillHelp });
                }
            }
            return null;
        }

        /** True when the student may not delete this element (it or one of its criteria is provided). */
        function isProtected(obj) {
            if (editFlags) { return false; }
            if (obj.locked || obj.niveauLocked || obj.linksLocked) { return true; }
            return (obj.criteres || []).some(function(c) { return c.locked || c.niveauLocked; });
        }

        function render() {
            container.replaceChildren.apply(container, notices().concat([
                renderDiagram(),
                renderInteractors(),
                renderFs(),
                renderContraintes()
            ]));
        }

        function renderInteractors() {
            var section = el('section', { className: 'gp-cdcf-section gp-cdcf-interactors' });
            section.appendChild(el('h3', null, [lang.interactorsTitle]));
            section.appendChild(el('p', { className: 'gp-cdcf-norm' }, [lang.interactorsNorm]));
            data.interactors.forEach(function(inter) {
                var canRemove = data.interactors.length > 2 && fsReferencingInteractor(inter.id).length === 0;
                var input = el('input', {
                    type: 'text', value: inter.name, placeholder: lang.interactorPlaceholder,
                    onchange: function(e) { inter.name = e.target.value; emit(); },
                });
                if (locked) { input.disabled = true; }
                if (frozen('interacteurs')) { freezeField(input); }
                var badge = applyFlags(input, inter, 'locked', 'gap', 'hint');
                var del = el('button', {
                    type: 'button', className: 'gp-cdcf-btn-remove', text: '🗑',
                    onclick: function() {
                        data.interactors = data.interactors.filter(function(x) { return x.id !== inter.id; });
                        emit();
                    },
                });
                if (locked || !canRemove || isProtected(inter) || frozen('interacteurs')) { del.disabled = true; }
                section.appendChild(el('div', { className: 'gp-cdcf-row' },
                    [el('span', { className: 'gp-cdcf-badge gp-cdcf-badge-i', text: 'I' + inter.id }), input, badge,
                        flagToggles(inter, 'locked', 'gap'), del]));
            });
            if (!locked && !frozen('interacteurs')) {
                section.appendChild(el('button', {
                    type: 'button', className: 'gp-cdcf-btn-add', text: '+ ' + lang.addInteractor,
                    onclick: function() {
                        data.interactors.push({ id: nextId(data.interactors), name: '' });
                        emit();
                    },
                }));
            }
            return section;
        }

        function renderDiagram() {
            var section = el('section', { className: 'gp-cdcf-section gp-cdcf-diagram-wrap' });
            section.appendChild(el('h3', null, [lang.diagramTitle]));
            var holder = el('div', { className: 'gp-cdcf-diagram' });
            section.appendChild(holder);
            Diagram.render(holder, opts.projetNom, data.interactors, data.fonctionsService);
            return section;
        }

        function renderFs() {
            var section = el('section', { className: 'gp-cdcf-section gp-cdcf-fs' });
            section.appendChild(el('h3', null, [lang.fsTitle]));
            section.appendChild(el('p', { className: 'gp-cdcf-norm' }, [lang.fsNorm]));
            data.fonctionsService.forEach(function(fs, idx) {
                section.appendChild(renderOneFs(fs, idx));
            });
            if (!locked && !frozen('fonctions')) {
                section.appendChild(el('button', {
                    type: 'button', className: 'gp-cdcf-btn-add', text: '+ ' + lang.addFs,
                    onclick: function() {
                        data.fonctionsService.push({
                            id: nextId(data.fonctionsService),
                            description: '',
                            interactor1Id: data.interactors[0] ? data.interactors[0].id : 0,
                            interactor2Id: 0,
                            criteres: [{ id: 1, description: '', niveau: '', flexibilite: '' }],
                        });
                        emit();
                    },
                }));
            }
            return section;
        }

        function moveFs(idx, dir) {
            var t = idx + dir;
            if (t < 0 || t >= data.fonctionsService.length) { return; }
            var arr = data.fonctionsService;
            var tmp = arr[idx]; arr[idx] = arr[t]; arr[t] = tmp;
            emit();
        }

        function renderOneFs(fs, idx) {
            var card = el('div', { className: 'gp-cdcf-fs-card' });
            var head = el('div', { className: 'gp-cdcf-fs-head' });

            var up = el('button', {
                type: 'button', className: 'gp-cdcf-icon-btn', text: '▲',
                onclick: function() { moveFs(idx, -1); },
            });
            var down = el('button', {
                type: 'button', className: 'gp-cdcf-icon-btn', text: '▼',
                onclick: function() { moveFs(idx, 1); },
            });
            if (locked || idx === 0 || frozen('fonctions')) { up.disabled = true; }
            if (locked || idx === data.fonctionsService.length - 1 || frozen('fonctions')) { down.disabled = true; }

            var badge = el('span', { className: 'gp-cdcf-badge gp-cdcf-badge-fs', text: 'FS' + (idx + 1) });

            var desc = el('textarea', {
                className: 'gp-cdcf-fs-desc', rows: '2', placeholder: lang.fsDescPlaceholder,
                onchange: function(e) { fs.description = e.target.value; emit(); },
            });
            desc.value = fs.description;
            if (locked) { desc.disabled = true; }
            var fsBadge = applyFlags(desc, fs, 'locked', 'gap', 'hint');

            var sel1 = renderInteractorSelect(fs.interactor1Id, false, function(v) { fs.interactor1Id = v; emit(); });
            var sel2 = renderInteractorSelect(fs.interactor2Id, true, function(v) { fs.interactor2Id = v; emit(); });
            if (!editFlags && (fs.locked || fs.linksLocked)) { sel1.disabled = sel2.disabled = true; }
            if (frozen('fonctions')) { freezeField(desc); freezeField(sel1); freezeField(sel2); }
            var del = el('button', {
                type: 'button', className: 'gp-cdcf-btn-remove', text: '🗑',
                onclick: function() {
                    data.fonctionsService = data.fonctionsService.filter(function(x) { return x.id !== fs.id; });
                    emit();
                },
            });
            if (locked || data.fonctionsService.length <= 1 || isProtected(fs) || frozen('fonctions')) { del.disabled = true; }

            head.appendChild(el('div', { className: 'gp-cdcf-fs-arrows' }, [up, down]));
            head.appendChild(badge);
            head.appendChild(el('div', { className: 'gp-cdcf-fs-desc-wrap' }, [
                el('label', null, [lang.fsDescLabel]), desc, fsBadge, flagToggles(fs, 'locked', 'gap'), linksToggle(fs),
            ]));
            head.appendChild(el('div', { className: 'gp-cdcf-fs-selects' }, [
                el('label', null, [lang.fsInteractorsLabel]), sel1, sel2,
            ]));
            head.appendChild(del);
            card.appendChild(head);

            var critList = el('div', { className: 'gp-cdcf-criteres' });
            fs.criteres.forEach(function(crit) {
                critList.appendChild(renderCritere(fs, crit));
            });
            if (!locked && !frozen('criteres')) {
                critList.appendChild(el('button', {
                    type: 'button', className: 'gp-cdcf-btn-add-sm', text: '+ ' + lang.addCritere,
                    onclick: function() {
                        fs.criteres.push({ id: nextId(fs.criteres), description: '', niveau: '', flexibilite: '' });
                        emit();
                    },
                }));
            }
            card.appendChild(critList);
            return card;
        }

        function renderCritere(fs, crit) {
            var row = el('div', { className: 'gp-cdcf-critere gp-cdcf-flex-' + (crit.flexibilite || 'none') });
            var d = el('input', {
                type: 'text', value: crit.description, placeholder: lang.criterePlaceholder,
                onchange: function(e) { crit.description = e.target.value; emit(); },
            });
            var n = el('input', {
                type: 'text', value: crit.niveau, placeholder: lang.niveauPlaceholder,
                onchange: function(e) { crit.niveau = e.target.value; emit(); },
            });
            var f = null;
            if (!hideFlexibility) {
                f = el('select', { onchange: function(e) { crit.flexibilite = e.target.value; emit(); } });
                [['', lang.flexNone], ['F0', lang.flexF0], ['F1', lang.flexF1], ['F2', lang.flexF2], ['F3', lang.flexF3]]
                    .forEach(function(p) {
                        var o = el('option', { value: p[0], text: p[1] });
                        if (crit.flexibilite === p[0]) { o.selected = true; }
                        f.appendChild(o);
                    });
            }
            var del = el('button', {
                type: 'button', className: 'gp-cdcf-btn-remove', text: '✕',
                onclick: function() {
                    fs.criteres = fs.criteres.filter(function(x) { return x.id !== crit.id; });
                    if (fs.criteres.length === 0) {
                        fs.criteres.push({ id: 1, description: '', niveau: '', flexibilite: '' });
                    }
                    emit();
                },
            });
            if (locked) {
                d.disabled = n.disabled = del.disabled = true;
                if (f) { f.disabled = true; }
            }
            var dBadge = applyFlags(d, crit, 'locked', 'gap', 'hint');
            var nBadge = applyFlags(n, crit, 'niveauLocked', 'niveauGap', 'niveauHint');
            if (f && !editFlags && crit.niveauLocked) { f.disabled = true; }
            if (isProtected(crit) || frozen('criteres')) { del.disabled = true; }
            if (frozen('criteres')) { freezeField(d); }
            if (frozen('niveaux')) { freezeField(n); }
            if (f && frozen('flexibilite')) { freezeField(f); }
            [d, dBadge, flagToggles(crit, 'locked', 'gap'), n, f, nBadge, flagToggles(crit, 'niveauLocked', 'niveauGap'), del]
                .forEach(function(node) { if (node) { row.appendChild(node); } });
            return row;
        }

        function renderInteractorSelect(currentId, allowNone, cb) {
            var s = el('select', { onchange: function(e) { cb(parseInt(e.target.value, 10)); } });
            if (allowNone) {
                var none = el('option', { value: '0', text: lang.noneOption });
                if (currentId === 0) { none.selected = true; }
                s.appendChild(none);
            }
            data.interactors.forEach(function(inter) {
                var label = inter.name || ('Interacteur ' + inter.id);
                var o = el('option', { value: String(inter.id), text: label });
                if (inter.id === currentId) { o.selected = true; }
                s.appendChild(o);
            });
            if (locked) { s.disabled = true; }
            return s;
        }

        function renderContraintes() {
            var section = el('section', { className: 'gp-cdcf-section gp-cdcf-contraintes' });
            section.appendChild(el('h3', null, [lang.contraintesTitle]));
            section.appendChild(el('p', { className: 'gp-cdcf-norm' }, [lang.contraintesNorm]));
            data.contraintes.forEach(function(c, idx) {
                var d = el('input', {
                    type: 'text', value: c.description, placeholder: lang.contraintePlaceholder,
                    onchange: function(e) { c.description = e.target.value; emit(); },
                });
                var j = el('input', {
                    type: 'text', value: c.justification, placeholder: lang.justificationPlaceholder,
                    onchange: function(e) { c.justification = e.target.value; emit(); },
                });
                var sel = el('select', {
                    onchange: function(e) { c.linkedFsId = parseInt(e.target.value, 10); emit(); },
                });
                var none = el('option', { value: '0', text: lang.noFsLink });
                if (c.linkedFsId === 0) { none.selected = true; }
                sel.appendChild(none);
                data.fonctionsService.forEach(function(fs, fsidx) {
                    var label = 'FS' + (fsidx + 1) + (fs.description ? (' — ' + fs.description.substring(0, 30)) : '');
                    var o = el('option', { value: String(fs.id), text: label });
                    if (fs.id === c.linkedFsId) { o.selected = true; }
                    sel.appendChild(o);
                });
                var del = el('button', {
                    type: 'button', className: 'gp-cdcf-btn-remove', text: '🗑',
                    onclick: function() {
                        data.contraintes = data.contraintes.filter(function(x) { return x.id !== c.id; });
                        emit();
                    },
                });
                if (locked) { d.disabled = j.disabled = sel.disabled = del.disabled = true; }
                var cBadge = applyFlags(d, c, 'locked', 'gap', 'hint');
                applyFlags(j, c, 'locked', 'gap', null);
                if (!editFlags && c.locked) { sel.disabled = true; }
                if (isProtected(c)) { del.disabled = true; }
                if (frozen('contraintes')) { freezeField(d); freezeField(j); freezeField(sel); del.disabled = true; }
                section.appendChild(el('div', { className: 'gp-cdcf-row' }, [
                    el('span', { className: 'gp-cdcf-badge gp-cdcf-badge-c', text: 'C' + (idx + 1) }),
                    d, j, sel, cBadge, flagToggles(c, 'locked', 'gap'), del,
                ]));
            });
            if (!locked && !frozen('contraintes')) {
                section.appendChild(el('button', {
                    type: 'button', className: 'gp-cdcf-btn-add', text: '+ ' + lang.addContrainte,
                    onclick: function() {
                        data.contraintes.push({
                            id: nextId(data.contraintes),
                            description: '',
                            justification: '',
                            linkedFsId: 0,
                        });
                        emit();
                    },
                }));
            }
            return section;
        }

        render();
        onChange(data);
    }

    return { init: init };
});
