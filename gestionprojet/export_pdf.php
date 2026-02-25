<?php
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
 * Export Logbook to PDF
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/pdflib.php');

$id = required_param('id', PARAM_INT); // CM ID
$groupid = optional_param('groupid', 0, PARAM_INT); // Group ID (optional)

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

require_capability('mod/gestionprojet:view', $context);

// Determine group
if (!$groupid) {
    $groupid = gestionprojet_get_user_group($cm, $USER->id);
}

// Check access to other groups
if ($groupid && $groupid != gestionprojet_get_user_group($cm, $USER->id)) {
    require_capability('mod/gestionprojet:grade', $context);
}

// Get submission
$submission = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $USER->id, 'carnet');
$tasks_data = [];
if ($submission->tasks_data) {
    $tasks_data = json_decode($submission->tasks_data, true) ?? [];
}

// Helper for group name
$groupname = get_string('defaultgroup', 'group');
if ($groupid) {
    if ($group = groups_get_group($groupid)) {
        $groupname = $group->name;
    }
} else {
    // Individual submission
    $groupname = fullname($USER);
}

// Create PDF
$pdf = new pdf();
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();

// Title
$pdf->SetFont('helvetica', 'B', 20);
$pdf->Cell(0, 10, get_string('step8', 'gestionprojet'), 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, format_string($course->fullname) . ' - ' . $groupname, 0, 1, 'C');
$pdf->Ln(10);

if (empty($tasks_data)) {
    $pdf->Cell(0, 10, get_string('no_submission', 'gestionprojet'), 0, 1, 'C');
} else {
    // Table Header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);

    // Widths
    $wDate = 25;
    $wStatus = 25;
    $wTask = 70; // Shared remaining width for two task columns
    // Total width is roughly 190mm (A4 margins)
    // 25 + 25 = 50. 190 - 50 = 140. 140 / 2 = 70.

    $pdf->Cell($wDate, 10, get_string('logbook_date', 'gestionprojet'), 1, 0, 'C', 1);
    $pdf->Cell($wTask, 10, get_string('logbook_tasks_today', 'gestionprojet'), 1, 0, 'C', 1);
    $pdf->Cell($wTask, 10, get_string('logbook_tasks_future', 'gestionprojet'), 1, 0, 'C', 1);
    $pdf->Cell($wStatus, 10, get_string('logbook_status', 'gestionprojet'), 1, 1, 'C', 1);

    $pdf->SetFont('helvetica', '', 10);

    foreach ($tasks_data as $task) {
        $date = $task['date'] ?? '';
        $today = $task['tasks_today'] ?? '';
        $future = $task['tasks_future'] ?? '';
        $status = $task['status'] ?? '';

        $statusLabel = '';
        if ($status === 'ahead')
            $statusLabel = get_string('logbook_status_ahead', 'gestionprojet');
        if ($status === 'ontime')
            $statusLabel = get_string('logbook_status_ontime', 'gestionprojet');
        if ($status === 'late')
            $statusLabel = get_string('logbook_status_late', 'gestionprojet');

        // Calculate height
        $nbLines = max(
            $pdf->getNumLines($today, $wTask),
            $pdf->getNumLines($future, $wTask)
        );
        $h = 6 * $nbLines + 4; // 6mm per line + padding

        // Check page break
        if ($pdf->GetY() + $h > $pdf->getPageHeight() - 20) {
            $pdf->AddPage();
            // Reprint Header
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell($wDate, 10, get_string('logbook_date', 'gestionprojet'), 1, 0, 'C', 1);
            $pdf->Cell($wTask, 10, get_string('logbook_tasks_today', 'gestionprojet'), 1, 0, 'C', 1);
            $pdf->Cell($wTask, 10, get_string('logbook_tasks_future', 'gestionprojet'), 1, 0, 'C', 1);
            $pdf->Cell($wStatus, 10, get_string('logbook_status', 'gestionprojet'), 1, 1, 'C', 1);
            $pdf->SetFont('helvetica', '', 10);
        }

        // Draw cells (MultiCell for text areas)
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->MultiCell($wDate, $h, userdate(strtotime($date), get_string('strftimedaydate')), 1, 'C', 0, 0);

        $pdf->SetXY($x + $wDate, $y);
        $pdf->MultiCell($wTask, $h, $today, 1, 'L', 0, 0);

        $pdf->SetXY($x + $wDate + $wTask, $y);
        $pdf->MultiCell($wTask, $h, $future, 1, 'L', 0, 0);

        $pdf->SetXY($x + $wDate + $wTask * 2, $y);
        // Status formatting if possible, simple text for now
        $pdf->MultiCell($wStatus, $h, $statusLabel, 1, 'C', 0, 0);

        $pdf->Ln();
    }
}

$pdf->Output('carnet_de_bord.pdf', 'D');
