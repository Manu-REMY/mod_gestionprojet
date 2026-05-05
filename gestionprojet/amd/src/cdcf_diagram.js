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
 * Pieuvre diagram renderer for the CDCF (NF EN 16271).
 *
 * @module     mod_gestionprojet/cdcf_diagram
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var COLORS = ['#d97706', '#dc2626', '#7c3aed', '#0891b2', '#059669', '#d946ef'];
    var WIDTH = 800;
    var HEIGHT = 460;
    var CX = WIDTH / 2;
    var CY = HEIGHT / 2;
    var SVG_NS = 'http://www.w3.org/2000/svg';

    function svg(name, attrs) {
        var el = document.createElementNS(SVG_NS, name);
        Object.keys(attrs || {}).forEach(function(k) { el.setAttribute(k, attrs[k]); });
        return el;
    }

    function svgText(attrs, text) {
        var t = svg('text', attrs);
        t.textContent = text;
        return t;
    }

    function computePositions(n) {
        var rx = n <= 4 ? 285 : 330;
        var ry = n <= 4 ? 165 : 203;
        var positions = [];
        for (var i = 0; i < n; i++) {
            var angle = -Math.PI / 2 + (2 * Math.PI * i) / Math.max(n, 1);
            positions.push({
                x: CX + rx * Math.cos(angle),
                y: CY + ry * Math.sin(angle),
            });
        }
        return positions;
    }

    /**
     * Render the pieuvre diagram into target.
     *
     * @param {HTMLElement} target Container element. Will be cleared.
     * @param {string} projetNom Center label.
     * @param {Array<{id:number,name:string}>} interactors
     * @param {Array<{id:number,interactor1Id:number,interactor2Id:number}>} fonctionsService
     */
    function render(target, projetNom, interactors, fonctionsService) {
        target.replaceChildren();
        var root = svg('svg', {
            viewBox: '0 0 ' + WIDTH + ' ' + HEIGHT,
            width: '100%',
            height: 'auto',
        });

        var positions = computePositions(interactors.length);

        fonctionsService.forEach(function(fs, idx) {
            var color = COLORS[idx % COLORS.length];
            var label = 'FS' + (idx + 1);
            var i1 = interactors.findIndex(function(it) { return it.id === fs.interactor1Id; });
            if (i1 < 0) { return; }
            if (fs.interactor2Id > 0) {
                var i2 = interactors.findIndex(function(it) { return it.id === fs.interactor2Id; });
                if (i2 < 0) { return; }
                var p1 = positions[i1];
                var p2 = positions[i2];
                var mx = (p1.x + p2.x) / 2;
                var my = (p1.y + p2.y) / 2;
                var d = 'M ' + p1.x + ' ' + p1.y + ' Q ' + CX + ' ' + CY + ' ' + p2.x + ' ' + p2.y;
                root.appendChild(svg('path', {
                    d: d, stroke: color, 'stroke-width': '2', fill: 'none',
                }));
                root.appendChild(svgText({
                    x: mx, y: my, fill: color,
                    'font-size': '12', 'font-weight': 'bold', 'text-anchor': 'middle',
                }, label));
            } else {
                var p = positions[i1];
                var midx = (p.x + CX) / 2;
                var midy = (p.y + CY) / 2;
                root.appendChild(svg('line', {
                    x1: p.x, y1: p.y, x2: CX, y2: CY,
                    stroke: color, 'stroke-width': '2',
                }));
                root.appendChild(svgText({
                    x: midx, y: midy - 6, fill: color,
                    'font-size': '12', 'font-weight': 'bold', 'text-anchor': 'middle',
                }, label));
            }
        });

        // Center ellipse (product) drawn after curves so it covers the lines visually.
        root.appendChild(svg('ellipse', {
            cx: CX, cy: CY, rx: '90', ry: '55',
            fill: '#667eea', stroke: '#764ba2', 'stroke-width': '3',
        }));
        root.appendChild(svgText({
            x: CX, y: CY,
            'text-anchor': 'middle', 'dominant-baseline': 'middle',
            'font-size': '16', 'font-weight': 'bold', fill: 'white',
        }, projetNom || ''));

        interactors.forEach(function(inter, i) {
            var pos = positions[i];
            root.appendChild(svg('circle', {
                cx: pos.x, cy: pos.y, r: '38',
                fill: '#f0f3ff', stroke: '#667eea', 'stroke-width': '2',
            }));
            root.appendChild(svgText({
                x: pos.x, y: pos.y,
                'text-anchor': 'middle', 'dominant-baseline': 'middle',
                'font-size': '12', fill: '#333',
            }, inter.name || ('Interacteur ' + inter.id)));
        });

        target.appendChild(root);
    }

    return { render: render };
});
