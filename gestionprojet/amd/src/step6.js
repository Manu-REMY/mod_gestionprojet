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

define(['jquery', 'mod_gestionprojet/autosave', 'core/str', 'core/ajax'], function ($, Autosave, Str, Ajax) {
    return {
        init: function (config) {
            var cmid = config.cmid;
            var step = config.step;
            var groupid = config.groupid;
            var autosaveInterval = config.autosaveInterval;
            var isLocked = config.isLocked;
            var members = config.auteurs || [''];
            var STRINGS = config.strings || {};

            if (members.length === 0) members = [''];

            // Submit / Revert
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

            $('#exportPdfBtn').on('click', function () {
                alert(STRINGS.export_pdf_coming_soon || 'Export PDF coming soon');
            });

            function submitAction(action) {
                Ajax.call([{
                    methodname: 'mod_gestionprojet_submit_step',
                    args: {
                        cmid: cmid,
                        step: step,
                        action: action
                    }
                }])[0].done(function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'Unknown error'));
                    }
                }).fail(function(ex) {
                    console.error('Submission error', ex);
                });
            }

            function renderMembers() {
                const container = document.getElementById('membersContainer');
                container.innerHTML = '';

                members.forEach((member, index) => {
                    const memberGroup = document.createElement('div');
                    memberGroup.className = 'member-group';

                    const input = document.createElement('input');
                    input.type = 'text';
                    input.placeholder = 'Nom et prénom';
                    input.value = member;
                    input.readOnly = isLocked;

                    if (!isLocked) {
                        input.onchange = (e) => {
                            members[index] = e.target.value;
                            // Trigger auto-save by firing change event on form if needed, or Autosave might catch it if we bound to form?
                            // Autosave binds to form inputs. 
                            // We should manually trigger change on form
                            $('#rapportForm').trigger('change');
                        };
                        input.oninput = (e) => {
                            members[index] = e.target.value;
                        }
                    }

                    memberGroup.appendChild(input);

                    if (!isLocked) {
                        if (index === members.length - 1) {
                            const addBtn = document.createElement('button');
                            addBtn.type = 'button';
                            addBtn.className = 'btn-add';
                            addBtn.innerHTML = '+';
                            addBtn.title = 'Ajouter un membre';
                            addBtn.onclick = () => {
                                members.push('');
                                renderMembers();
                            };
                            memberGroup.appendChild(addBtn);
                        } else {
                            const removeBtn = document.createElement('button');
                            removeBtn.type = 'button';
                            removeBtn.className = 'btn-remove';
                            removeBtn.innerHTML = '✕';
                            removeBtn.title = 'Retirer ce membre';
                            removeBtn.onclick = () => {
                                if (members.length > 1) {
                                    members.splice(index, 1);
                                    renderMembers();
                                    $('#rapportForm').trigger('change');
                                }
                            };
                            memberGroup.appendChild(removeBtn);
                        }
                    }
                    container.appendChild(memberGroup);
                });
            }

            // Custom serialization
            var serializeData = function () {
                var formData = {};
                $('#rapportForm').find('input[type="text"], textarea').each(function () {
                    if (this.name) {
                        formData[this.name] = this.value;
                    }
                });
                formData['auteurs'] = JSON.stringify(members.filter(m => m && m.trim() !== ''));
                return formData;
            };

            renderMembers();

            if (!isLocked) {
                Autosave.init({
                    cmid: cmid,
                    step: step,
                    groupid: groupid,
                    interval: autosaveInterval,
                    formSelector: '#rapportForm',
                    serialize: serializeData
                });
            }
        }
    };
});
