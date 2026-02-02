<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Shared styles for teacher correction model pages.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
?>

<style>
    /* Dashboard and form container alignment */
    .gestionprojet-dashboard {
        max-width: 1200px;
        margin: 0 auto 20px auto;
    }
    .teacher-model-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    .teacher-model-header {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
        padding: 20px 30px;
        border-radius: 12px;
        margin-bottom: 25px;
    }
    .teacher-model-header h2 {
        margin: 0 0 10px;
        font-size: 24px;
    }
    .teacher-model-header p {
        margin: 0;
        opacity: 0.9;
    }
    .back-nav {
        margin-bottom: 20px;
    }
    .back-nav a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #17a2b8;
        text-decoration: none;
        font-weight: 500;
    }
    .back-nav a:hover {
        text-decoration: underline;
    }
    .model-form-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .model-form-section h3 {
        color: #17a2b8;
        margin: 0 0 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
    }
    .form-group input[type="text"],
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
        box-sizing: border-box;
    }
    .form-group input[type="text"]:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #17a2b8;
    }
    .form-group textarea {
        min-height: 100px;
        resize: vertical;
    }
    .ai-instructions-section {
        background: linear-gradient(135deg, #e7f3ff 0%, #f0f7ff 100%);
        border: 2px solid #17a2b8;
        border-radius: 12px;
        padding: 25px;
        margin-top: 30px;
    }
    .ai-instructions-section h3 {
        color: #0056b3;
        margin: 0 0 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .ai-instructions-section textarea {
        width: 100%;
        min-height: 150px;
        padding: 15px;
        border: 2px solid #17a2b8;
        border-radius: 8px;
        font-size: 14px;
        background: white;
        box-sizing: border-box;
    }
    .ai-instructions-help {
        font-size: 13px;
        color: #666;
        margin-top: 10px;
        line-height: 1.5;
    }
    .save-section {
        margin-top: 30px;
        text-align: center;
    }
    .btn-save {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
        border: none;
        padding: 15px 40px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: transform 0.2s;
    }
    .btn-save:hover {
        transform: scale(1.02);
    }
    .save-status {
        margin-top: 15px;
        padding: 10px;
        border-radius: 6px;
        display: none;
    }
    .save-status.success {
        background: #d4edda;
        color: #155724;
        display: block;
    }
    .save-status.error {
        background: #f8d7da;
        color: #721c24;
        display: block;
    }
    /* Step Navigation */
    .step-navigation {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #e9ecef;
        gap: 15px;
    }
    .btn-nav {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
    }
    .btn-prev, .btn-next {
        background: #f8f9fa;
        color: #17a2b8;
        border: 2px solid #17a2b8;
    }
    .btn-prev:hover, .btn-next:hover {
        background: #17a2b8;
        color: white;
        text-decoration: none;
    }
    .btn-hub {
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        color: white;
        border: none;
    }
    .btn-hub:hover {
        transform: scale(1.02);
        color: white;
        text-decoration: none;
    }
    .nav-spacer {
        flex: 1;
    }
    .step-navigation-top {
        margin-top: 0;
        margin-bottom: 20px;
        padding-top: 0;
        border-top: none;
    }
</style>
