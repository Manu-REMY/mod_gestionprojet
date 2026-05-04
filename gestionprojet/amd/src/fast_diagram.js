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
 * FAST diagram SVG renderer.
 * Layout: FP -> FT -> (sub-FT) -> ST, left-to-right tree.
 *
 * @module     mod_gestionprojet/fast_diagram
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var XHTML_NS = 'http://www.w3.org/1999/xhtml';

    var FP_WIDTH = 160, FP_HEIGHT = 38;
    var FT_WIDTH = 200, FT_HEIGHT = 30;
    var SOUS_FT_WIDTH = 180, SOUS_FT_HEIGHT = 28;
    var ST_WIDTH = 180, ST_HEIGHT = 28;
    var H_GAP = 60, V_GAP = 5;
    var LINE_COLOR = '#94a3b8';

    var COLORS = {
        fpFill: '#dbeafe', fpStroke: '#3b82f6',
        ftFill: '#dcfce7', ftStroke: '#22c55e',
        sfFill: '#f0fdf4', sfStroke: '#86efac',
        stFill: '#ffedd5', stStroke: '#f97316'
    };

    function computeLayout(data) {
        var nodes = [], lines = [];
        var fonctions = (data && data.fonctions) || [];
        var fps = (data && data.fonctionsPrincipales) || [];
        var fpLabel = fps.map(function(fp) { return fp.description; }).filter(Boolean).join(' / ') || 'FP';

        if (fonctions.length === 0) {
            return { nodes: [], lines: [], width: 400, height: 200 };
        }

        var hasSousFonctions = fonctions.some(function(ft) { return ft.sousFonctions && ft.sousFonctions.length > 0; });

        var fpX = 30;
        var ftX = fpX + FP_WIDTH + H_GAP;
        var sfX = ftX + FT_WIDTH + H_GAP;
        var stX = hasSousFonctions ? sfX + SOUS_FT_WIDTH + H_GAP : ftX + FT_WIDTH + H_GAP;

        var totalRows = 0;
        var ftRowStarts = [];
        fonctions.forEach(function(ft) {
            ftRowStarts.push(totalRows);
            totalRows += (ft.sousFonctions && ft.sousFonctions.length > 0) ? ft.sousFonctions.length : 1;
        });

        var rowHeight = Math.max(FT_HEIGHT, SOUS_FT_HEIGHT, ST_HEIGHT) + V_GAP;
        var topPadding = 20;
        var fpY = topPadding;

        nodes.push({
            x: fpX, y: fpY, w: FP_WIDTH, h: FP_HEIGHT,
            label: fpLabel, fill: COLORS.fpFill, stroke: COLORS.fpStroke
        });

        var fpCenterY = fpY + FP_HEIGHT / 2;
        var fpRight = fpX + FP_WIDTH;

        fonctions.forEach(function(ft, ftIdx) {
            var rowStart = ftRowStarts[ftIdx];
            var subCount = (ft.sousFonctions && ft.sousFonctions.length) || 0;
            var rowCount = subCount > 0 ? subCount : 1;
            var groupTopY = topPadding + rowStart * rowHeight;
            var groupCenterY = groupTopY + (rowCount * rowHeight) / 2 - FT_HEIGHT / 2;
            var ftY = groupCenterY;
            var ftCenterY = ftY + FT_HEIGHT / 2;

            nodes.push({
                x: ftX, y: ftY, w: FT_WIDTH, h: FT_HEIGHT,
                label: ft.description || ('FT' + (ftIdx + 1)),
                fill: COLORS.ftFill, stroke: COLORS.ftStroke
            });

            var midX1 = fpRight + H_GAP / 2;
            lines.push({ x1: fpRight, y1: fpCenterY, x2: midX1, y2: fpCenterY });
            lines.push({ x1: midX1, y1: fpCenterY, x2: midX1, y2: ftCenterY });
            lines.push({ x1: midX1, y1: ftCenterY, x2: ftX, y2: ftCenterY });

            var ftRight = ftX + FT_WIDTH;

            if (subCount > 0) {
                ft.sousFonctions.forEach(function(sf, sfIdx) {
                    var sfRowY = topPadding + (rowStart + sfIdx) * rowHeight;
                    var sfY = sfRowY + (rowHeight - SOUS_FT_HEIGHT) / 2;
                    var sfCenterY = sfY + SOUS_FT_HEIGHT / 2;

                    nodes.push({
                        x: sfX, y: sfY, w: SOUS_FT_WIDTH, h: SOUS_FT_HEIGHT,
                        label: sf.description || ('FT' + (ftIdx + 1) + '.' + (sfIdx + 1)),
                        fill: COLORS.sfFill, stroke: COLORS.sfStroke
                    });

                    var midX2 = ftRight + H_GAP / 2;
                    lines.push({ x1: ftRight, y1: ftCenterY, x2: midX2, y2: ftCenterY });
                    lines.push({ x1: midX2, y1: ftCenterY, x2: midX2, y2: sfCenterY });
                    lines.push({ x1: midX2, y1: sfCenterY, x2: sfX, y2: sfCenterY });

                    if (sf.solution) {
                        var sfRight = sfX + SOUS_FT_WIDTH;
                        var stY = sfY + (SOUS_FT_HEIGHT - ST_HEIGHT) / 2;
                        var stCenterY = stY + ST_HEIGHT / 2;
                        nodes.push({
                            x: stX, y: stY, w: ST_WIDTH, h: ST_HEIGHT,
                            label: sf.solution,
                            fill: COLORS.stFill, stroke: COLORS.stStroke
                        });
                        lines.push({ x1: sfRight, y1: sfCenterY, x2: stX, y2: stCenterY });
                    }
                });
            } else if (ft.solution) {
                var stY2 = ftY + (FT_HEIGHT - ST_HEIGHT) / 2;
                var stCenterY2 = stY2 + ST_HEIGHT / 2;
                nodes.push({
                    x: stX, y: stY2, w: ST_WIDTH, h: ST_HEIGHT,
                    label: ft.solution,
                    fill: COLORS.stFill, stroke: COLORS.stStroke
                });
                lines.push({ x1: ftRight, y1: ftCenterY, x2: stX, y2: stCenterY2 });
            }
        });

        var maxX = 0, maxY = 0;
        nodes.forEach(function(n) {
            if (n.x + n.w > maxX) { maxX = n.x + n.w; }
            if (n.y + n.h > maxY) { maxY = n.y + n.h; }
        });

        return { nodes: nodes, lines: lines, width: maxX + 40, height: maxY + 40 };
    }

    function buildSvg(layout) {
        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + layout.width + ' ' + layout.height);
        svg.setAttribute('class', 'fast-svg');
        svg.setAttribute('preserveAspectRatio', 'xMinYMin meet');
        svg.style.minHeight = Math.min(layout.height, 600) + 'px';

        layout.lines.forEach(function(l) {
            var line = document.createElementNS(SVG_NS, 'line');
            line.setAttribute('x1', l.x1);
            line.setAttribute('y1', l.y1);
            line.setAttribute('x2', l.x2);
            line.setAttribute('y2', l.y2);
            line.setAttribute('stroke', LINE_COLOR);
            line.setAttribute('stroke-width', '1.5');
            svg.appendChild(line);
        });

        layout.nodes.forEach(function(n) {
            var group = document.createElementNS(SVG_NS, 'g');

            var rect = document.createElementNS(SVG_NS, 'rect');
            rect.setAttribute('x', n.x);
            rect.setAttribute('y', n.y);
            rect.setAttribute('width', n.w);
            rect.setAttribute('height', n.h);
            rect.setAttribute('rx', '6');
            rect.setAttribute('fill', n.fill);
            rect.setAttribute('stroke', n.stroke);
            rect.setAttribute('stroke-width', '1.5');
            group.appendChild(rect);

            var fo = document.createElementNS(SVG_NS, 'foreignObject');
            fo.setAttribute('x', n.x + 6);
            fo.setAttribute('y', n.y + 4);
            fo.setAttribute('width', n.w - 12);
            fo.setAttribute('height', n.h - 8);

            var div = document.createElementNS(XHTML_NS, 'div');
            div.setAttribute('class', 'fast-node-label');
            div.textContent = n.label;
            fo.appendChild(div);

            group.appendChild(fo);
            svg.appendChild(group);
        });

        return svg;
    }

    function buildEmpty(text) {
        var div = document.createElement('div');
        div.setAttribute('class', 'fast-diagram-empty');
        div.textContent = text || 'FAST';
        return div;
    }

    function render(containerId, data) {
        var container = document.getElementById(containerId);
        if (!container) { return; }

        var fonctions = (data && data.fonctions) || [];
        if (fonctions.length === 0) {
            container.replaceChildren(buildEmpty('FAST'));
            return;
        }

        var layout = computeLayout(data);
        container.replaceChildren(buildSvg(layout));
    }

    return {
        render: render,
        computeLayout: computeLayout
    };
});
