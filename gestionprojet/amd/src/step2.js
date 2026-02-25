/*
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Step 2: Expression du Besoin (Bete a Corne) - SVG diagram and autosave.
 *
 * @module     mod_gestionprojet/step2
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'mod_gestionprojet/autosave'], function($, Autosave) {

    /**
     * Wrap text into lines that fit a given max character length.
     *
     * @param {string} text  The input text.
     * @param {number} maxLength  Maximum characters per line.
     * @return {string[]} Array of lines.
     */
    function wrapText(text, maxLength) {
        if (!text) {
            return [''];
        }
        var words = text.split(' ');
        var lines = [];
        var currentLine = '';

        for (var i = 0; i < words.length; i++) {
            var word = words[i];
            if ((currentLine + word).length <= maxLength) {
                currentLine += (currentLine ? ' ' : '') + word;
            } else {
                if (currentLine) {
                    lines.push(currentLine);
                }
                currentLine = word;
            }
        }
        if (currentLine) {
            lines.push(currentLine);
        }
        return lines;
    }

    /**
     * Create an SVG element in the SVG namespace.
     *
     * @param {string} tag  SVG element tag name.
     * @param {Object} attrs  Key/value attribute pairs.
     * @return {SVGElement}
     */
    function svgEl(tag, attrs) {
        var el = document.createElementNS('http://www.w3.org/2000/svg', tag);
        if (attrs) {
            for (var key in attrs) {
                if (attrs.hasOwnProperty(key)) {
                    el.setAttribute(key, attrs[key]);
                }
            }
        }
        return el;
    }

    /**
     * Append wrapped text lines as SVG text elements.
     *
     * @param {SVGElement} svg  Parent SVG container.
     * @param {string[]} lines  Array of text lines.
     * @param {number} cx  Center X coordinate.
     * @param {number} startY  Starting Y coordinate.
     * @param {string} fill  Text fill colour.
     * @param {string} fontSize  Font size value.
     */
    function appendTextLines(svg, lines, cx, startY, fill, fontSize) {
        for (var i = 0; i < lines.length; i++) {
            var text = svgEl('text', {
                'x': cx,
                'y': startY + i * 18,
                'text-anchor': 'middle',
                'fill': fill,
                'font-size': fontSize
            });
            text.textContent = lines[i];
            svg.appendChild(text);
        }
    }

    /**
     * Remove all child nodes from an element (safe alternative to innerHTML = '').
     *
     * @param {Element} el  The element to clear.
     */
    function clearElement(el) {
        while (el.firstChild) {
            el.removeChild(el.firstChild);
        }
    }

    /**
     * Render the Bete a Corne SVG diagram based on current textarea values.
     */
    function updateDiagram() {
        var svg = document.getElementById('beteACorneCanvas');
        if (!svg) {
            return;
        }
        var width = svg.clientWidth;
        var height = 500;

        // Clear SVG contents safely.
        clearElement(svg);
        svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);

        // Define arrow marker.
        var defs = svgEl('defs');
        var marker = svgEl('marker', {
            'id': 'arrowhead',
            'markerWidth': '10',
            'markerHeight': '10',
            'refX': '9',
            'refY': '3',
            'orient': 'auto'
        });
        var arrowPath = svgEl('path', {
            'd': 'M0,0 L0,6 L9,3 z',
            'fill': '#e91e63'
        });
        marker.appendChild(arrowPath);
        defs.appendChild(marker);
        svg.appendChild(defs);

        // Layout constants.
        var centerX = width / 2;
        var topY = 70;
        var centerY = 240;
        var bottomY = 360;
        var boxWidth = 250;
        var boxHeight = 90;
        var productWidth = 220;
        var productHeight = 110;
        var spacing = 160;

        // Read textarea values.
        var aquiText = document.getElementById('aqui').value || '';
        var surquoiText = document.getElementById('surquoi').value || '';
        var dansquelbutText = document.getElementById('dansquelbut').value || '';

        // ------- Left ellipse: A qui rend-il service ? -------
        var leftBoxX = centerX - spacing - boxWidth / 2;
        var leftBoxY = topY;
        var leftCenterX = leftBoxX + boxWidth / 2;
        var leftCenterY = leftBoxY + boxHeight / 2;

        svg.appendChild(svgEl('ellipse', {
            'cx': leftCenterX,
            'cy': leftCenterY,
            'rx': boxWidth / 2,
            'ry': boxHeight / 2,
            'fill': 'white',
            'stroke': '#4fc3f7',
            'stroke-width': '2.5'
        }));

        // Left content text.
        var leftLines = wrapText(aquiText, 30);
        var leftTextStartY = leftBoxY + boxHeight / 2 - (leftLines.length * 8);
        appendTextLines(svg, leftLines, leftCenterX, leftTextStartY, '#555', '13');

        // Left title.
        var leftTitle = svgEl('text', {
            'x': leftCenterX,
            'y': leftBoxY - 25,
            'text-anchor': 'middle',
            'fill': '#333',
            'font-size': '15',
            'font-weight': '700'
        });
        leftTitle.textContent = '\u00C0 qui le produit rend-il service ?';
        svg.appendChild(leftTitle);

        var leftSubtitle = svgEl('text', {
            'x': leftCenterX,
            'y': leftBoxY - 8,
            'text-anchor': 'middle',
            'fill': '#666',
            'font-size': '12'
        });
        leftSubtitle.textContent = '(utilisateur)';
        svg.appendChild(leftSubtitle);

        // ------- Right ellipse: Sur quoi agit-il ? -------
        var rightBoxX = centerX + spacing - boxWidth / 2;
        var rightBoxY = topY;
        var rightCenterX = rightBoxX + boxWidth / 2;
        var rightCenterY = rightBoxY + boxHeight / 2;

        svg.appendChild(svgEl('ellipse', {
            'cx': rightCenterX,
            'cy': rightCenterY,
            'rx': boxWidth / 2,
            'ry': boxHeight / 2,
            'fill': 'white',
            'stroke': '#4fc3f7',
            'stroke-width': '2.5'
        }));

        // Right content text.
        var rightLines = wrapText(surquoiText, 30);
        var rightTextStartY = rightBoxY + boxHeight / 2 - (rightLines.length * 8);
        appendTextLines(svg, rightLines, rightCenterX, rightTextStartY, '#555', '13');

        // Right title.
        var rightTitle = svgEl('text', {
            'x': rightCenterX,
            'y': rightBoxY - 25,
            'text-anchor': 'middle',
            'fill': '#333',
            'font-size': '15',
            'font-weight': '700'
        });
        rightTitle.textContent = 'Sur quoi agit-il ?';
        svg.appendChild(rightTitle);

        var rightSubtitle = svgEl('text', {
            'x': rightCenterX,
            'y': rightBoxY - 8,
            'text-anchor': 'middle',
            'fill': '#666',
            'font-size': '12'
        });
        rightSubtitle.textContent = '(mati\u00E8re d\'oeuvre)';
        svg.appendChild(rightSubtitle);

        // ------- Product box (centre) -------
        var productX = centerX - productWidth / 2;
        var productY = centerY - productHeight / 2;

        svg.appendChild(svgEl('rect', {
            'x': productX,
            'y': productY,
            'width': productWidth,
            'height': productHeight,
            'rx': '25',
            'fill': '#667eea',
            'stroke': '#764ba2',
            'stroke-width': '2.5'
        }));

        var productLabel = svgEl('text', {
            'x': centerX,
            'y': centerY - 8,
            'text-anchor': 'middle',
            'fill': 'white',
            'font-size': '20',
            'font-weight': 'bold'
        });
        productLabel.textContent = 'Produit';
        svg.appendChild(productLabel);

        var productSubLabel = svgEl('text', {
            'x': centerX,
            'y': centerY + 12,
            'text-anchor': 'middle',
            'fill': 'white',
            'font-size': '14'
        });
        productSubLabel.textContent = '(objet technique)';
        svg.appendChild(productSubLabel);

        // ------- Bottom rectangle: Dans quel but ? -------
        var bottomBoxX = centerX - boxWidth / 2;
        var bottomBoxY = bottomY;
        var bottomCenterX = bottomBoxX + boxWidth / 2;

        svg.appendChild(svgEl('rect', {
            'x': bottomBoxX,
            'y': bottomBoxY,
            'width': boxWidth,
            'height': boxHeight,
            'rx': '10',
            'fill': 'white',
            'stroke': '#4fc3f7',
            'stroke-width': '2.5'
        }));

        // Bottom content text.
        var bottomLines = wrapText(dansquelbutText, 30);
        var bottomTextStartY = bottomBoxY + boxHeight / 2 - (bottomLines.length * 8);
        appendTextLines(svg, bottomLines, bottomCenterX, bottomTextStartY, '#555', '13');

        // Bottom title.
        var bottomTitle = svgEl('text', {
            'x': bottomCenterX,
            'y': bottomBoxY + boxHeight + 20,
            'text-anchor': 'middle',
            'fill': '#333',
            'font-size': '15',
            'font-weight': '700'
        });
        bottomTitle.textContent = 'Dans quel but ?';
        svg.appendChild(bottomTitle);

        var bottomSubtitle = svgEl('text', {
            'x': bottomCenterX,
            'y': bottomBoxY + boxHeight + 37,
            'text-anchor': 'middle',
            'fill': '#666',
            'font-size': '12'
        });
        bottomSubtitle.textContent = '(fonction d\'usage ou besoin)';
        svg.appendChild(bottomSubtitle);

        // ------- Top "horn" curve connecting left and right ellipses -------
        var topCurveStartX = leftCenterX + boxWidth / 2 - 10;
        var topCurveStartY = leftCenterY;
        var topCurveEndX = rightCenterX - boxWidth / 2 + 10;
        var topCurveEndY = rightCenterY;
        var topCurveControlX = centerX;
        var topCurveControlY = centerY - 50;

        svg.appendChild(svgEl('path', {
            'd': 'M ' + topCurveStartX + ' ' + topCurveStartY +
                 ' Q ' + topCurveControlX + ' ' + topCurveControlY +
                 ' ' + topCurveEndX + ' ' + topCurveEndY,
            'stroke': '#e91e63',
            'stroke-width': '3',
            'fill': 'none'
        }));

        // Circles at top curve ends.
        svg.appendChild(svgEl('circle', {
            'cx': topCurveStartX,
            'cy': topCurveStartY,
            'r': '5',
            'fill': '#e91e63'
        }));
        svg.appendChild(svgEl('circle', {
            'cx': topCurveEndX,
            'cy': topCurveEndY,
            'r': '5',
            'fill': '#e91e63'
        }));

        // ------- Bottom "horn" curve connecting product to bottom box -------
        var bottomCurveStartX = centerX;
        var bottomCurveStartY = centerY + productHeight / 2 + 10;
        var bottomCurveEndX = bottomCenterX;
        var bottomCurveEndY = bottomBoxY - 5;
        var bottomCurveControl1X = centerX + 80;
        var bottomCurveControl1Y = centerY + 70;
        var bottomCurveControl2X = bottomCenterX + 60;
        var bottomCurveControl2Y = bottomBoxY - 40;

        svg.appendChild(svgEl('path', {
            'd': 'M ' + bottomCurveStartX + ' ' + bottomCurveStartY +
                 ' C ' + bottomCurveControl1X + ' ' + bottomCurveControl1Y +
                 ', ' + bottomCurveControl2X + ' ' + bottomCurveControl2Y +
                 ', ' + bottomCurveEndX + ' ' + bottomCurveEndY,
            'stroke': '#e91e63',
            'stroke-width': '3',
            'fill': 'none',
            'marker-end': 'url(#arrowhead)'
        }));

        // Circle at bottom curve start.
        svg.appendChild(svgEl('circle', {
            'cx': bottomCurveStartX,
            'cy': bottomCurveStartY,
            'r': '5',
            'fill': '#e91e63'
        }));
    }

    return {
        /**
         * Initialise the Step 2 page.
         *
         * @param {Object} config
         * @param {number} config.cmid             Course module ID.
         * @param {number} config.step             Step number (always 2).
         * @param {number} config.autosaveInterval Autosave interval in ms.
         * @param {boolean} config.readonly         Whether the page is readonly.
         */
        init: function(config) {
            var cmid = config.cmid;
            var step = config.step;
            var autosaveInterval = config.autosaveInterval;
            var readonly = config.readonly;

            // Update diagram when textarea values change.
            $('#besoinForm textarea').on('input', function() {
                updateDiagram();
            });

            // Custom serialization for step 2 form data.
            var serializeData = function() {
                var formData = {};
                $('#besoinForm textarea').each(function() {
                    if (this.name) {
                        formData[this.name] = this.value;
                    }
                });
                formData['locked'] = 0; // Always unlocked.
                return formData;
            };

            // Initialize autosave when not in readonly mode.
            if (!readonly) {
                Autosave.init({
                    cmid: cmid,
                    step: step,
                    groupid: 0,
                    interval: autosaveInterval,
                    formSelector: '#besoinForm',
                    serialize: serializeData
                });
            }

            // Initial diagram render.
            updateDiagram();
        }
    };
});
