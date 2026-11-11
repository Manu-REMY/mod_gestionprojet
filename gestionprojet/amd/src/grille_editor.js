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
 * Editor of the step 4 grading grid on the correction model page.
 *
 * Writes the grid as JSON into a hidden field and fires an input event so that the autosave picks it up.
 * The server normalizes what is saved (\mod_gestionprojet\grille::normalize).
 *
 * @module     mod_gestionprojet/grille_editor
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Create an element; text is always set as text, never parsed as HTML.
     *
     * @param {string} tag
     * @param {Object} props className, text, type, value, title, ariaLabel, onchange, onclick, checked, min
     * @returns {HTMLElement}
     */
    function el(tag, props) {
        var node = document.createElement(tag);
        props = props || {};
        if (props.className) { node.className = props.className; }
        if (props.text !== undefined) { node.textContent = props.text; }
        if (props.type) { node.type = props.type; }
        if (props.value !== undefined) { node.value = props.value; }
        if (props.title) { node.title = props.title; }
        if (props.ariaLabel) { node.setAttribute('aria-label', props.ariaLabel); }
        if (props.min !== undefined) { node.min = props.min; }
        if (props.checked) { node.checked = true; }
        if (props.onchange) { node.addEventListener('change', props.onchange); }
        if (props.oninput) { node.addEventListener('input', props.oninput); }
        if (props.onclick) { node.addEventListener('click', props.onclick); }
        return node;
    }

    /**
     * Start the editor.
     *
     * @param {Object} cfg fieldId, rootId, initial (grid), defaults (default grid), zones, lockedZones, lang
     */
    function init(cfg) {
        var field = document.getElementById(cfg.fieldId);
        var root = document.getElementById(cfg.rootId);
        if (!field || !root) {
            return;
        }
        var lang = cfg.lang;
        var parts = JSON.parse(JSON.stringify(cfg.initial || []));

        function nextId() {
            return parts.reduce(function(max, p) { return Math.max(max, p.id || 0); }, 0) + 1;
        }

        function emit() {
            field.value = JSON.stringify(parts);
            field.dispatchEvent(new Event('input', {bubbles: true}));
            renderTotal();
        }

        var lockedZones = cfg.lockedZones || [];
        var absentZones = cfg.absentZones || [];
        // Level mode (v2.11.2): only the parts with a level are graded, level by level.
        var paliersMode = !!cfg.paliersMode;

        function isGraded(part) {
            if (part.evaluee !== 'auto') {
                return part.evaluee === 'oui';
            }
            // "auto": excluded when every zone of the part is entirely provided by the consigne,
            // or absent from the correction model and the consigne.
            return part.zones.some(function(z) {
                return lockedZones.indexOf(z) === -1 && absentZones.indexOf(z) === -1;
            });
        }

        function statusText(part) {
            if (isGraded(part)) {
                return '✓ ' + lang.statusGraded;
            }
            if (part.evaluee !== 'auto') {
                return '⊘ ' + lang.statusExcluded;
            }
            var absent = part.zones.every(function(z) { return absentZones.indexOf(z) !== -1; });
            return '⊘ ' + (absent ? lang.statusAbsent : lang.statusProvided);
        }

        var total = el('p', {className: 'gp-grille-total'});

        function renderTotal() {
            var sum = parts.filter(isGraded).reduce(function(s, p) { return s + (parseInt(p.poids, 10) || 0); }, 0);
            total.textContent = lang.total.replace('{$a}', String(sum));
        }

        function select(options, current, onchange, label) {
            var s = el('select', {onchange: onchange, ariaLabel: label});
            options.forEach(function(o) {
                var opt = el('option', {value: o[0], text: o[1]});
                if (o[0] === current) { opt.selected = true; }
                s.appendChild(opt);
            });
            return s;
        }

        function renderPart(part, index) {
            var row = el('div', {className: 'gp-grille-part' + (isGraded(part) ? '' : ' gp-grille-excluded')});

            var head = el('div', {className: 'gp-grille-head'});
            head.appendChild(el('input', {type: 'text', value: part.nom, ariaLabel: lang.nom, className: 'gp-grille-nom',
                onchange: function(e) { part.nom = e.target.value; emit(); }}));
            head.appendChild(el('input', {type: 'number', value: part.poids, min: 0, ariaLabel: lang.poids,
                className: 'gp-grille-poids', title: lang.poids,
                onchange: function(e) { part.poids = Math.max(0, parseInt(e.target.value, 10) || 0); emit(); }}));
            head.appendChild(select([['auto', lang.evalueeAuto], ['oui', lang.evalueeOui], ['non', lang.evalueeNon]],
                part.evaluee, function(e) { part.evaluee = e.target.value; emit(); render(); }, lang.evaluee));
            // Graded or excluded: a word and a symbol, never the colour alone.
            head.appendChild(el('span', {className: 'gp-grille-status',
                text: statusText(part)}));

            var tools = el('span', {className: 'gp-grille-tools'});
            tools.appendChild(el('button', {type: 'button', text: '↑', ariaLabel: lang.up, title: lang.up,
                onclick: function() { move(index, -1); }}));
            tools.appendChild(el('button', {type: 'button', text: '↓', ariaLabel: lang.down, title: lang.down,
                onclick: function() { move(index, 1); }}));
            tools.appendChild(el('button', {type: 'button', text: '✕', ariaLabel: lang.remove, title: lang.remove,
                onclick: function() { parts.splice(index, 1); emit(); render(); }}));
            head.appendChild(tools);
            row.appendChild(head);

            row.appendChild(el('textarea', {value: part.description, ariaLabel: lang.description,
                className: 'gp-grille-description', title: lang.description,
                onchange: function(e) { part.description = e.target.value; emit(); }}));

            var zones = el('fieldset', {className: 'gp-grille-zones'});
            zones.appendChild(el('legend', {text: lang.zones}));
            cfg.zones.forEach(function(z) {
                var label = el('label', {});
                label.appendChild(el('input', {type: 'checkbox', checked: part.zones.indexOf(z[0]) !== -1,
                    onchange: function(e) {
                        part.zones = keepHiddenZones(part.zones, z[0], e.target.checked);
                        emit();
                        // The status may change: a part whose zones are all provided is excluded.
                        render();
                    }}));
                label.appendChild(document.createTextNode(' ' + z[1]));
                zones.appendChild(label);
            });
            row.appendChild(zones);

            var palier = el('label', {className: 'gp-grille-palier', text: lang.palier + ' '});
            palier.appendChild(select([['', lang.palierNone], ['F', lang.palierF], ['S', lang.palierS], ['TS', lang.palierTs]], part.palier,
                function(e) { part.palier = e.target.value; emit(); }, lang.palier));
            palier.appendChild(el('small', {text: ' ' + lang.palierHelp}));
            if (paliersMode && !part.palier) {
                palier.appendChild(el('strong', {className: 'gp-grille-nolevel', text: ' ⊘ ' + lang.palierNoneMode}));
            }
            row.appendChild(palier);
            return row;
        }

        /**
         * Zones of a part after ticking or unticking one: zones the editor does not show (flexibility when
         * the instance does not ask for it) are kept, so that enabling the option again finds them.
         *
         * @param {string[]} zones Current zones of the part.
         * @param {string} code Zone ticked or unticked.
         * @param {boolean} checked
         * @returns {string[]}
         */
        function keepHiddenZones(zones, code, checked) {
            var shown = cfg.zones.map(function(x) { return x[0]; });
            var kept = zones.filter(function(c) { return shown.indexOf(c) === -1; });
            var ticked = shown.filter(function(c) { return c === code ? checked : zones.indexOf(c) !== -1; });
            return ticked.concat(kept);
        }

        function move(index, delta) {
            var target = index + delta;
            if (target < 0 || target >= parts.length) {
                return;
            }
            var tmp = parts[index];
            parts[index] = parts[target];
            parts[target] = tmp;
            emit();
            render();
        }

        function render() {
            root.textContent = '';
            parts.forEach(function(part, i) { root.appendChild(renderPart(part, i)); });
            var actions = el('div', {className: 'gp-grille-actions'});
            actions.appendChild(el('button', {type: 'button', className: 'btn btn-secondary', text: lang.add,
                onclick: function() {
                    parts.push({id: nextId(), nom: lang.newPart, description: '', poids: 1, zones: ['ensemble'],
                        palier: '', evaluee: 'auto'});
                    emit();
                    render();
                }}));
            actions.appendChild(el('button', {type: 'button', className: 'btn btn-link', text: lang.reset,
                onclick: function() {
                    parts = JSON.parse(JSON.stringify(cfg.defaults));
                    emit();
                    render();
                }}));
            root.appendChild(actions);
            root.appendChild(total);
            renderTotal();
        }

        render();
    }

    return {init: init};
});
