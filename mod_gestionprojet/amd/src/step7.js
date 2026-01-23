/*
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'mod_gestionprojet/autosave', 'core/str', 'core/notification'], function ($, Autosave, Str, Notification) {
    return {
        init: function (config) {
            var cmid = config.cmid;
            var step = 7;
            var autosaveInterval = config.autosaveInterval;
            var groupid = config.groupid;
            var isLocked = config.isLocked;
            var STRINGS = config.strings || {};

            // Update diagram when text changes
            $('#besoinEleveForm textarea').on('input', function () {
                updateDiagram();
            });

            // Custom serialization
            var serializeData = function () {
                var formData = {};
                $('#besoinEleveForm textarea').each(function () {
                    if (this.name) {
                        formData[this.name] = this.value;
                    }
                });
                return formData;
            };

            // Handle Submission
            $('#submitButton').on('click', function () {
                if (confirm(STRINGS.confirm_submission)) {
                    $.ajax({
                        url: M.cfg.wwwroot + '/mod/gestionprojet/ajax/submit.php',
                        method: 'POST',
                        data: {
                            id: cmid,
                            step: step,
                            action: 'submit',
                            sesskey: M.cfg.sesskey
                        },
                        success: function (response) {
                            var res = JSON.parse(response);
                            if (res.success) {
                                window.location.reload();
                            } else {
                                alert('Error submitting');
                            }
                        }
                    });
                }
            });

            // Handle Revert
            $('#revertButton').on('click', function () {
                if (confirm(STRINGS.confirm_revert)) {
                    $.ajax({
                        url: M.cfg.wwwroot + '/mod/gestionprojet/ajax/submit.php',
                        method: 'POST',
                        data: {
                            id: cmid,
                            step: step,
                            action: 'revert',
                            sesskey: M.cfg.sesskey
                        },
                        success: function (response) {
                            var res = JSON.parse(response);
                            if (res.success) {
                                window.location.reload();
                            } else {
                                alert('Error reverting');
                            }
                        }
                    });
                }
            });

            // Initialize Autosave if not readonly
            if (!isLocked) {
                Autosave.init({
                    cmid: cmid,
                    step: step,
                    groupid: groupid,
                    interval: autosaveInterval,
                    formSelector: '#besoinEleveForm',
                    serialize: serializeData
                });
            }

            // Simplified Bête à Corne SVG diagram
            function wrapText(text, maxLength) {
                if (!text) return [''];
                const words = text.split(' ');
                const lines = [];
                let currentLine = '';

                words.forEach(word => {
                    if ((currentLine + word).length <= maxLength) {
                        currentLine += (currentLine ? ' ' : '') + word;
                    } else {
                        if (currentLine) lines.push(currentLine);
                        currentLine = word;
                    }
                });
                if (currentLine) lines.push(currentLine);
                return lines;
            }

            function updateDiagram() {
                const svg = document.getElementById('beteACorneCanvas');
                if (!svg) return;

                const width = svg.clientWidth || 800; // Fallback width
                const height = 500;

                // Clear SVG
                svg.innerHTML = '';
                svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);

                // Define arrow marker
                const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
                const marker = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
                marker.setAttribute('id', 'arrowhead');
                marker.setAttribute('markerWidth', '10');
                marker.setAttribute('markerHeight', '10');
                marker.setAttribute('refX', '9');
                marker.setAttribute('refY', '3');
                marker.setAttribute('orient', 'auto');

                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', 'M0,0 L0,6 L9,3 z');
                path.setAttribute('fill', '#e91e63');
                marker.appendChild(path);
                defs.appendChild(marker);
                svg.appendChild(defs);

                const centerX = width / 2;
                const topY = 70;
                const centerY = 240;
                const bottomY = 360;
                const boxWidth = 250;
                const boxHeight = 90;
                const productWidth = 220;
                const productHeight = 110;
                const spacing = 160;

                // Get values
                const aquiText = $('#aqui').val() || '';
                const surquoiText = $('#surquoi').val() || '';
                const dansquelbutText = $('#dansquelbut').val() || '';

                // Left ellipse - À qui rend-il service ?
                const leftBoxX = centerX - spacing - boxWidth / 2;
                const leftBoxY = topY;
                const leftCenterX = leftBoxX + boxWidth / 2;
                const leftCenterY = leftBoxY + boxHeight / 2;

                const leftBox = document.createElementNS('http://www.w3.org/2000/svg', 'ellipse');
                leftBox.setAttribute('cx', leftCenterX);
                leftBox.setAttribute('cy', leftCenterY);
                leftBox.setAttribute('rx', boxWidth / 2);
                leftBox.setAttribute('ry', boxHeight / 2);
                leftBox.setAttribute('fill', 'white');
                leftBox.setAttribute('stroke', '#4fc3f7');
                leftBox.setAttribute('stroke-width', '2.5');
                svg.appendChild(leftBox);

                // Left content
                const leftLines = wrapText(aquiText, 30);
                const leftTextStartY = leftBoxY + boxHeight / 2 - (leftLines.length * 8);
                leftLines.forEach((line, i) => {
                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.setAttribute('x', leftCenterX);
                    text.setAttribute('y', leftTextStartY + i * 18);
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('fill', '#555');
                    text.setAttribute('font-size', '13');
                    text.textContent = line;
                    svg.appendChild(text);
                });

                // Left title
                const leftTitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                leftTitle.setAttribute('x', leftCenterX);
                leftTitle.setAttribute('y', leftBoxY - 25);
                leftTitle.setAttribute('text-anchor', 'middle');
                leftTitle.setAttribute('fill', '#333');
                leftTitle.setAttribute('font-size', '15');
                leftTitle.setAttribute('font-weight', '700');
                leftTitle.textContent = 'À qui le produit rend-il service ?';
                svg.appendChild(leftTitle);

                const leftSubtitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                leftSubtitle.setAttribute('x', leftCenterX);
                leftSubtitle.setAttribute('y', leftBoxY - 8);
                leftSubtitle.setAttribute('text-anchor', 'middle');
                leftSubtitle.setAttribute('fill', '#666');
                leftSubtitle.setAttribute('font-size', '12');
                leftSubtitle.textContent = '(utilisateur)';
                svg.appendChild(leftSubtitle);

                // Right ellipse - Sur quoi agit-il ?
                const rightBoxX = centerX + spacing - boxWidth / 2;
                const rightBoxY = topY;
                const rightCenterX = rightBoxX + boxWidth / 2;
                const rightCenterY = rightBoxY + boxHeight / 2;

                const rightBox = document.createElementNS('http://www.w3.org/2000/svg', 'ellipse');
                rightBox.setAttribute('cx', rightCenterX);
                rightBox.setAttribute('cy', rightCenterY);
                rightBox.setAttribute('rx', boxWidth / 2);
                rightBox.setAttribute('ry', boxHeight / 2);
                rightBox.setAttribute('fill', 'white');
                rightBox.setAttribute('stroke', '#4fc3f7');
                rightBox.setAttribute('stroke-width', '2.5');
                svg.appendChild(rightBox);

                // Right content
                const rightLines = wrapText(surquoiText, 30);
                const rightTextStartY = rightBoxY + boxHeight / 2 - (rightLines.length * 8);
                rightLines.forEach((line, i) => {
                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.setAttribute('x', rightCenterX);
                    text.setAttribute('y', rightTextStartY + i * 18);
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('fill', '#555');
                    text.setAttribute('font-size', '13');
                    text.textContent = line;
                    svg.appendChild(text);
                });

                // Right title
                const rightTitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                rightTitle.setAttribute('x', rightCenterX);
                rightTitle.setAttribute('y', rightBoxY - 25);
                rightTitle.setAttribute('text-anchor', 'middle');
                rightTitle.setAttribute('fill', '#333');
                rightTitle.setAttribute('font-size', '15');
                rightTitle.setAttribute('font-weight', '700');
                rightTitle.textContent = 'Sur quoi agit-il ?';
                svg.appendChild(rightTitle);

                const rightSubtitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                rightSubtitle.setAttribute('x', rightCenterX);
                rightSubtitle.setAttribute('y', rightBoxY - 8);
                rightSubtitle.setAttribute('text-anchor', 'middle');
                rightSubtitle.setAttribute('fill', '#666');
                rightSubtitle.setAttribute('font-size', '12');
                rightSubtitle.textContent = '(matière d\'œuvre)';
                svg.appendChild(rightSubtitle);

                // Product box in center
                const productX = centerX - productWidth / 2;
                const productY = centerY - productHeight / 2;

                const productBox = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                productBox.setAttribute('x', productX);
                productBox.setAttribute('y', productY);
                productBox.setAttribute('width', productWidth);
                productBox.setAttribute('height', productHeight);
                productBox.setAttribute('rx', '25');
                productBox.setAttribute('fill', '#667eea');
                productBox.setAttribute('stroke', '#764ba2');
                productBox.setAttribute('stroke-width', '2.5');
                svg.appendChild(productBox);

                const productLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                productLabel.setAttribute('x', centerX);
                productLabel.setAttribute('y', centerY - 8);
                productLabel.setAttribute('text-anchor', 'middle');
                productLabel.setAttribute('fill', 'white');
                productLabel.setAttribute('font-size', '20');
                productLabel.setAttribute('font-weight', 'bold');
                productLabel.textContent = 'Produit';
                svg.appendChild(productLabel);

                const productSubLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                productSubLabel.setAttribute('x', centerX);
                productSubLabel.setAttribute('y', centerY + 12);
                productSubLabel.setAttribute('text-anchor', 'middle');
                productSubLabel.setAttribute('fill', 'white');
                productSubLabel.setAttribute('font-size', '14');
                productSubLabel.textContent = '(objet technique)';
                svg.appendChild(productSubLabel);

                // Bottom rectangle - Dans quel but ?
                const bottomBoxX = centerX - boxWidth / 2;
                const bottomBoxY = bottomY;
                const bottomCenterX = bottomBoxX + boxWidth / 2;
                const bottomCenterY = bottomBoxY + boxHeight / 2;

                const bottomBox = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                bottomBox.setAttribute('x', bottomBoxX);
                bottomBox.setAttribute('y', bottomBoxY);
                bottomBox.setAttribute('width', boxWidth);
                bottomBox.setAttribute('height', boxHeight);
                bottomBox.setAttribute('rx', '10');
                bottomBox.setAttribute('fill', 'white');
                bottomBox.setAttribute('stroke', '#4fc3f7');
                bottomBox.setAttribute('stroke-width', '2.5');
                svg.appendChild(bottomBox);

                // Bottom content
                const bottomLines = wrapText(dansquelbutText, 30);
                const bottomTextStartY = bottomBoxY + boxHeight / 2 - (bottomLines.length * 8);
                bottomLines.forEach((line, i) => {
                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.setAttribute('x', bottomCenterX);
                    text.setAttribute('y', bottomTextStartY + i * 18);
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('fill', '#555');
                    text.setAttribute('font-size', '13');
                    text.textContent = line;
                    svg.appendChild(text);
                });

                // Bottom title
                const bottomTitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                bottomTitle.setAttribute('x', bottomCenterX);
                bottomTitle.setAttribute('y', bottomBoxY + boxHeight + 20);
                bottomTitle.setAttribute('text-anchor', 'middle');
                bottomTitle.setAttribute('fill', '#333');
                bottomTitle.setAttribute('font-size', '15');
                bottomTitle.setAttribute('font-weight', '700');
                bottomTitle.textContent = 'Dans quel but ?';
                svg.appendChild(bottomTitle);

                const bottomSubtitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                bottomSubtitle.setAttribute('x', bottomCenterX);
                bottomSubtitle.setAttribute('y', bottomBoxY + boxHeight + 37);
                bottomSubtitle.setAttribute('text-anchor', 'middle');
                bottomSubtitle.setAttribute('fill', '#666');
                bottomSubtitle.setAttribute('font-size', '12');
                bottomSubtitle.textContent = '(fonction d\'usage ou besoin)';
                svg.appendChild(bottomSubtitle);

                // "Horn" curve - top arc connecting left and right ellipses
                const topCurveStartX = leftCenterX + boxWidth / 2 - 10;
                const topCurveStartY = leftCenterY;
                const topCurveEndX = rightCenterX - boxWidth / 2 + 10;
                const topCurveEndY = rightCenterY;
                const topCurveControlX = centerX;
                const topCurveControlY = centerY - 50;

                const topCurve = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                topCurve.setAttribute('d', 'M ' + topCurveStartX + ' ' + topCurveStartY + ' Q ' + topCurveControlX + ' ' + topCurveControlY + ' ' + topCurveEndX + ' ' + topCurveEndY);
                topCurve.setAttribute('stroke', '#e91e63');
                topCurve.setAttribute('stroke-width', '3');
                topCurve.setAttribute('fill', 'none');
                svg.appendChild(topCurve);

                // Circles at top curve ends
                const leftCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                leftCircle.setAttribute('cx', topCurveStartX);
                leftCircle.setAttribute('cy', topCurveStartY);
                leftCircle.setAttribute('r', '5');
                leftCircle.setAttribute('fill', '#e91e63');
                svg.appendChild(leftCircle);

                const rightCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                rightCircle.setAttribute('cx', topCurveEndX);
                rightCircle.setAttribute('cy', topCurveEndY);
                rightCircle.setAttribute('r', '5');
                rightCircle.setAttribute('fill', '#e91e63');
                svg.appendChild(rightCircle);

                // Bottom "horn" curve - connecting top arc to bottom box
                const bottomCurveStartX = centerX;
                const bottomCurveStartY = centerY + productHeight / 2 + 10;
                const bottomCurveEndX = bottomCenterX;
                const bottomCurveEndY = bottomBoxY - 5;
                const bottomCurveControl1X = centerX + 80;
                const bottomCurveControl1Y = centerY + 70;
                const bottomCurveControl2X = bottomCenterX + 60;
                const bottomCurveControl2Y = bottomBoxY - 40;

                const bottomCurve = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                bottomCurve.setAttribute('d', 'M ' + bottomCurveStartX + ' ' + bottomCurveStartY + ' C ' + bottomCurveControl1X + ' ' + bottomCurveControl1Y + ', ' + bottomCurveControl2X + ' ' + bottomCurveControl2Y + ', ' + bottomCurveEndX + ' ' + bottomCurveEndY);
                bottomCurve.setAttribute('stroke', '#e91e63');
                bottomCurve.setAttribute('stroke-width', '3');
                bottomCurve.setAttribute('fill', 'none');
                bottomCurve.setAttribute('marker-end', 'url(#arrowhead)');
                svg.appendChild(bottomCurve);

                // Circle at bottom curve start
                const bottomStartCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                bottomStartCircle.setAttribute('cx', bottomCurveStartX);
                bottomStartCircle.setAttribute('cy', bottomCurveStartY);
                bottomStartCircle.setAttribute('r', '5');
                bottomStartCircle.setAttribute('fill', '#e91e63');
                svg.appendChild(bottomStartCircle);
            }

            // Initial diagram render
            updateDiagram();
        }
    };
});
