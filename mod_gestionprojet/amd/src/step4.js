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
            var step = config.step;
            var groupid = config.groupid;
            var autosaveInterval = config.autosaveInterval;
            var isLocked = config.isLocked;
            var STRINGS = config.strings || {};

            // State
            var interacteurs = config.interacteurs || [];
            if (interacteurs.length === 0) {
                interacteurs = [
                    { name: 'Utilisateur', fcs: [] },
                    { name: 'Milieu extérieur', fcs: [] }
                ];
            }

            // Bind Events
            $('#addInteractorBtn').on('click', function () {
                addInteractor();
            });

            $('#exportPdfBtn').on('click', function () {
                alert(STRINGS.export_pdf_coming_soon || 'Export PDF coming soon');
            });

            $('#produit').on('change', function () {
                updateDiagram();
            });

            // Submission
            $('#submitButton').on('click', function () {
                if (confirm(STRINGS.confirm_submission)) {
                    submitAction('submit');
                }
            });

            $('#revertButton').on('click', function () {
                if (confirm(STRINGS.confirm_revert)) {
                    submitAction('revert');
                }
            });

            function submitAction(action) {
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/gestionprojet/ajax/submit.php',
                    method: 'POST',
                    data: {
                        id: cmid,
                        step: step,
                        action: action,
                        sesskey: M.cfg.sesskey
                    },
                    success: function (response) {
                        try {
                            var res = JSON.parse(response);
                            if (res.success) {
                                window.location.reload();
                            } else {
                                alert('Error: ' + (res.message || 'Unknown error'));
                            }
                        } catch (e) {
                            console.error('Submission error', e);
                        }
                    }
                });
            }

            // Functions
            function renderInteractors() {
                const container = document.getElementById('interactorsContainer');
                container.innerHTML = '';

                interacteurs.forEach((interactor, iIndex) => {
                    const item = document.createElement('div');
                    item.className = 'interactor-item';

                    // Header
                    const header = document.createElement('div');
                    header.className = 'interactor-header';

                    const nameInput = document.createElement('input');
                    nameInput.type = 'text';
                    nameInput.className = 'interactor-name-input';
                    nameInput.value = interactor.name;
                    nameInput.placeholder = 'Nom de l\'interacteur';
                    nameInput.readOnly = isLocked;
                    if (!isLocked) {
                        nameInput.onchange = () => {
                            interacteurs[iIndex].name = nameInput.value;
                            updateDiagram();
                        };
                    }
                    header.appendChild(nameInput);

                    if (!isLocked && iIndex >= 2) {
                        const deleteBtn = document.createElement('button');
                        deleteBtn.type = 'button';
                        deleteBtn.className = 'btn-delete-interactor';
                        deleteBtn.innerHTML = '🗑️ Supprimer';
                        deleteBtn.onclick = () => {
                            interacteurs.splice(iIndex, 1);
                            renderInteractors();
                            updateDiagram();
                        };
                        header.appendChild(deleteBtn);
                    }
                    item.appendChild(header);

                    // FC List
                    const fcList = document.createElement('div');
                    fcList.className = 'fc-list';

                    interactor.fcs.forEach((fc, fcIndex) => {
                        const fcItem = document.createElement('div');
                        fcItem.className = 'fc-item';

                        const fcHeader = document.createElement('div');
                        fcHeader.className = 'fc-header';
                        fcHeader.innerHTML = '<span class="fc-label">FC' + (fcIndex + 1) + '</span>';
                        fcItem.appendChild(fcHeader);

                        const fcValueInput = document.createElement('input');
                        fcValueInput.type = 'text';
                        fcValueInput.className = 'fc-value-input';
                        fcValueInput.value = fc.value;
                        fcValueInput.placeholder = 'Description de la fonction contrainte';
                        fcValueInput.readOnly = isLocked;
                        if (!isLocked) {
                            fcValueInput.onchange = () => {
                                interacteurs[iIndex].fcs[fcIndex].value = fcValueInput.value;
                                updateDiagram();
                            };
                        }
                        fcItem.appendChild(fcValueInput);

                        // Criteres
                        const criteresList = document.createElement('div');
                        criteresList.className = 'criteres-list';

                        if (fc.criteres) {
                            fc.criteres.forEach((critere, cIndex) => {
                                const critereItem = document.createElement('div');
                                critereItem.className = 'critere-item';

                                const critereInput = document.createElement('input');
                                critereInput.type = 'text';
                                critereInput.className = 'critere-input';
                                critereInput.value = critere.critere;
                                critereInput.placeholder = 'Critère d\'appréciation';
                                critereInput.readOnly = isLocked;
                                if (!isLocked) {
                                    critereInput.onchange = () => {
                                        interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].critere = critereInput.value;
                                    };
                                }

                                const niveauInput = document.createElement('input');
                                niveauInput.type = 'text';
                                niveauInput.className = 'critere-input';
                                niveauInput.value = critere.niveau;
                                niveauInput.placeholder = 'Niveau';
                                niveauInput.readOnly = isLocked;
                                if (!isLocked) {
                                    niveauInput.onchange = () => {
                                        interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].niveau = niveauInput.value;
                                    };
                                }

                                const uniteInput = document.createElement('input');
                                uniteInput.type = 'text';
                                uniteInput.className = 'critere-input';
                                uniteInput.value = critere.unite;
                                uniteInput.placeholder = 'Unité';
                                uniteInput.readOnly = isLocked;
                                if (!isLocked) {
                                    uniteInput.onchange = () => {
                                        interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].unite = uniteInput.value;
                                    };
                                }

                                critereItem.appendChild(critereInput);
                                critereItem.appendChild(niveauInput);
                                critereItem.appendChild(uniteInput);

                                if (!isLocked) {
                                    const removeBtn = document.createElement('button');
                                    removeBtn.type = 'button';
                                    removeBtn.className = 'btn-remove';
                                    removeBtn.innerHTML = '✕';
                                    removeBtn.onclick = () => {
                                        if (fc.criteres.length > 1) {
                                            interacteurs[iIndex].fcs[fcIndex].criteres.splice(cIndex, 1);
                                            renderInteractors();
                                        }
                                    };
                                    critereItem.appendChild(removeBtn);
                                }
                                criteresList.appendChild(critereItem);
                            });
                        }
                        fcItem.appendChild(criteresList);

                        if (!isLocked) {
                            const addCritereBtn = document.createElement('button');
                            addCritereBtn.type = 'button';
                            addCritereBtn.className = 'btn-add';
                            addCritereBtn.innerHTML = '+ Critère';
                            addCritereBtn.onclick = () => {
                                if (!interacteurs[iIndex].fcs[fcIndex].criteres) {
                                    interacteurs[iIndex].fcs[fcIndex].criteres = [];
                                }
                                interacteurs[iIndex].fcs[fcIndex].criteres.push({ critere: '', niveau: '', unite: '' });
                                renderInteractors();
                            };
                            fcItem.appendChild(addCritereBtn);
                        }

                        fcList.appendChild(fcItem);
                    });
                    item.appendChild(fcList);

                    if (!isLocked) {
                        const addFCBtn = document.createElement('button');
                        addFCBtn.type = 'button';
                        addFCBtn.className = 'btn-add';
                        addFCBtn.innerHTML = '+ Fonction Contrainte';
                        addFCBtn.onclick = () => {
                            interacteurs[iIndex].fcs.push({ value: '', criteres: [{ critere: '', niveau: '', unite: '' }] });
                            renderInteractors();
                            updateDiagram();
                        };
                        item.appendChild(addFCBtn);
                    }
                    container.appendChild(item);
                });
            }

            function addInteractor() {
                interacteurs.push({
                    name: 'Interacteur ' + (interacteurs.length + 1),
                    fcs: [{ value: '', criteres: [{ critere: '', niveau: '', unite: '' }] }]
                });
                renderInteractors();
                updateDiagram();
            }

            function updateDiagram() {
                const svg = document.getElementById('interactorDiagram');
                if (!svg) return;

                const width = 800;
                const height = 500;
                svg.innerHTML = '';
                svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);

                const centerX = width / 2;
                const centerY = height / 2;
                const productRadius = 60;

                // Valid interactors
                const validInteractors = interacteurs.filter(i => i.name && i.name.trim() !== '');
                const angleStep = (2 * Math.PI) / validInteractors.length;

                validInteractors.forEach((interactor, index) => {
                    const angle = (index * angleStep) - Math.PI / 2;
                    const distance = 200;
                    const x = centerX + distance * Math.cos(angle);
                    const y = centerY + distance * Math.sin(angle);

                    // Line to center
                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    line.setAttribute('x1', x);
                    line.setAttribute('y1', y);
                    line.setAttribute('x2', centerX);
                    line.setAttribute('y2', centerY);
                    line.setAttribute('stroke', index < 2 ? '#667eea' : '#ff6b6b');
                    line.setAttribute('stroke-width', index < 2 ? '3' : '2');
                    svg.appendChild(line);

                    // Circle
                    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    circle.setAttribute('cx', x);
                    circle.setAttribute('cy', y);
                    circle.setAttribute('r', '40');
                    circle.setAttribute('fill', '#f0f3ff');
                    circle.setAttribute('stroke', '#667eea');
                    circle.setAttribute('stroke-width', '2');
                    svg.appendChild(circle);

                    // Name
                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.setAttribute('x', x);
                    text.setAttribute('y', y);
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('dominant-baseline', 'middle');
                    text.setAttribute('font-size', '12');
                    text.setAttribute('fill', '#333');
                    text.textContent = interactor.name;
                    svg.appendChild(text);

                    // Label FC
                    if (index >= 2 && interactor.fcs && interactor.fcs[0] && interactor.fcs[0].value) {
                        const fcLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                        fcLabel.setAttribute('x', (x + centerX) / 2);
                        fcLabel.setAttribute('y', (y + centerY) / 2 - 10);
                        fcLabel.setAttribute('text-anchor', 'middle');
                        fcLabel.setAttribute('font-size', '11');
                        fcLabel.setAttribute('fill', '#ff6b6b');
                        fcLabel.setAttribute('font-weight', 'bold');
                        fcLabel.textContent = 'FC' + (index - 1);
                        svg.appendChild(fcLabel);
                    }
                });

                // Product Circle
                const productCircle = document.createElementNS('http://www.w3.org/2000/svg', 'ellipse');
                productCircle.setAttribute('cx', centerX);
                productCircle.setAttribute('cy', centerY);
                productCircle.setAttribute('rx', productRadius * 1.5);
                productCircle.setAttribute('ry', productRadius);
                productCircle.setAttribute('fill', '#667eea');
                productCircle.setAttribute('stroke', '#764ba2');
                productCircle.setAttribute('stroke-width', '3');
                svg.appendChild(productCircle);

                // Product name
                const productName = $('#produit').val() || 'Produit';
                const productText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                productText.setAttribute('x', centerX);
                productText.setAttribute('y', centerY);
                productText.setAttribute('text-anchor', 'middle');
                productText.setAttribute('dominant-baseline', 'middle');
                productText.setAttribute('font-size', '16');
                productText.setAttribute('font-weight', 'bold');
                productText.setAttribute('fill', 'white');
                productText.textContent = productName;
                svg.appendChild(productText);
            }

            // Custom serialization
            var serializeData = function () {
                var formData = {};

                // Regular fields
                $('#cdcfForm').find('input[type="text"], textarea').each(function () {
                    if (this.name) {
                        formData[this.name] = this.value;
                    }
                });

                // Interacteurs
                formData['interacteurs_data'] = JSON.stringify(interacteurs);
                return formData;
            }

            // Initial render
            renderInteractors();
            updateDiagram();

            // Autosave
            if (!isLocked) {
                Autosave.init({
                    cmid: cmid,
                    step: step,
                    groupid: groupid,
                    interval: autosaveInterval,
                    formSelector: '#cdcfForm',
                    serialize: serializeData
                });
            }
        }
    };
});
