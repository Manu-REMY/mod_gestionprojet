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
define(['mod_gestionprojet/cdcf_diagram'], function(Diagram) {
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
        var onChange = opts.onChange || function() {};

        function emit() { onChange(data); render(); }

        function fsReferencingInteractor(id) {
            return data.fonctionsService.filter(function(fs) {
                return fs.interactor1Id === id || fs.interactor2Id === id;
            });
        }

        function render() {
            container.replaceChildren(
                renderInteractors(),
                renderDiagram(),
                renderFs(),
                renderContraintes()
            );
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
                var del = el('button', {
                    type: 'button', className: 'gp-cdcf-btn-remove', text: '🗑',
                    onclick: function() {
                        data.interactors = data.interactors.filter(function(x) { return x.id !== inter.id; });
                        emit();
                    },
                });
                if (locked || !canRemove) { del.disabled = true; }
                section.appendChild(el('div', { className: 'gp-cdcf-row' },
                    [el('span', { className: 'gp-cdcf-badge gp-cdcf-badge-i', text: 'I' + inter.id }), input, del]));
            });
            if (!locked) {
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
            if (!locked) {
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
            if (locked || idx === 0) { up.disabled = true; }
            if (locked || idx === data.fonctionsService.length - 1) { down.disabled = true; }

            var badge = el('span', { className: 'gp-cdcf-badge gp-cdcf-badge-fs', text: 'FS' + (idx + 1) });

            var desc = el('textarea', {
                className: 'gp-cdcf-fs-desc', rows: '2', placeholder: lang.fsDescPlaceholder,
                onchange: function(e) { fs.description = e.target.value; emit(); },
            });
            desc.value = fs.description;
            if (locked) { desc.disabled = true; }

            var sel1 = renderInteractorSelect(fs.interactor1Id, false, function(v) { fs.interactor1Id = v; emit(); });
            var sel2 = renderInteractorSelect(fs.interactor2Id, true, function(v) { fs.interactor2Id = v; emit(); });
            var del = el('button', {
                type: 'button', className: 'gp-cdcf-btn-remove', text: '🗑',
                onclick: function() {
                    data.fonctionsService = data.fonctionsService.filter(function(x) { return x.id !== fs.id; });
                    emit();
                },
            });
            if (locked || data.fonctionsService.length <= 1) { del.disabled = true; }

            head.appendChild(el('div', { className: 'gp-cdcf-fs-arrows' }, [up, down]));
            head.appendChild(badge);
            head.appendChild(el('div', { className: 'gp-cdcf-fs-desc-wrap' }, [
                el('label', null, [lang.fsDescLabel]), desc,
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
            if (!locked) {
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
            var f = el('select', { onchange: function(e) { crit.flexibilite = e.target.value; emit(); } });
            [['', lang.flexNone], ['F0', lang.flexF0], ['F1', lang.flexF1], ['F2', lang.flexF2], ['F3', lang.flexF3]]
                .forEach(function(p) {
                    var o = el('option', { value: p[0], text: p[1] });
                    if (crit.flexibilite === p[0]) { o.selected = true; }
                    f.appendChild(o);
                });
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
            if (locked) { d.disabled = n.disabled = f.disabled = del.disabled = true; }
            row.appendChild(d);
            row.appendChild(n);
            row.appendChild(f);
            row.appendChild(del);
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
                section.appendChild(el('div', { className: 'gp-cdcf-row' }, [
                    el('span', { className: 'gp-cdcf-badge gp-cdcf-badge-c', text: 'C' + (idx + 1) }),
                    d, j, sel, del,
                ]));
            });
            if (!locked) {
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
