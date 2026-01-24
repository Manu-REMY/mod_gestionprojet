<?php
/**
 * Script pour trouver facilement le cmid de vos activités Gestion de Projet
 *
 * Usage: Placez ce fichier à la racine de Moodle et accédez-y via navigateur:
 * http://votre-moodle/trouver_cmid.php
 */

require_once(__DIR__ . '/config.php');

require_login();

$PAGE->set_url(new moodle_url('/trouver_cmid.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Trouver les CMID');
$PAGE->set_heading('Liste des activités Gestion de Projet');

echo $OUTPUT->header();
echo '<h2>🔍 Trouver les CMID de vos activités Gestion de Projet</h2>';

// Trouver toutes les instances de gestionprojet
$sql = "SELECT cm.id as cmid,
               cm.instance,
               g.name as activity_name,
               c.fullname as course_name,
               c.id as courseid,
               cm.visible
        FROM {course_modules} cm
        JOIN {modules} m ON cm.module = m.id
        JOIN {gestionprojet} g ON cm.instance = g.id
        JOIN {course} c ON cm.course = c.id
        WHERE m.name = 'gestionprojet'
        ORDER BY c.fullname, g.name";

$activities = $DB->get_records_sql($sql);

if (empty($activities)) {
    echo '<div class="alert alert-warning">';
    echo '<strong>Aucune activité Gestion de Projet trouvée !</strong><br>';
    echo 'Créez d\'abord une activité Gestion de Projet dans un cours.';
    echo '</div>';
} else {
    echo '<div class="alert alert-info">';
    echo 'Trouvé <strong>' . count($activities) . '</strong> activité(s) Gestion de Projet.';
    echo '</div>';

    echo '<table class="table table-striped table-bordered">';
    echo '<thead class="thead-dark">';
    echo '<tr>';
    echo '<th>CMID</th>';
    echo '<th>Cours</th>';
    echo '<th>Nom de l\'activité</th>';
    echo '<th>Visible</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($activities as $activity) {
        $visible = $activity->visible ? '✅ Oui' : '❌ Non';
        $url = new moodle_url('/mod/gestionprojet/view.php', ['id' => $activity->cmid]);

        echo '<tr>';
        echo '<td><strong style="color: #007bff; font-size: 18px;">' . $activity->cmid . '</strong></td>';
        echo '<td>' . htmlspecialchars($activity->course_name) . '</td>';
        echo '<td>' . htmlspecialchars($activity->activity_name) . '</td>';
        echo '<td>' . $visible . '</td>';
        echo '<td>';
        echo '<a href="' . $url . '" class="btn btn-sm btn-primary" target="_blank">Ouvrir</a> ';
        echo '<a href="test_autosave.php?cmid=' . $activity->cmid . '" class="btn btn-sm btn-success" target="_blank">Tester</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    echo '<hr>';
    echo '<h3>💡 Comment utiliser le CMID ?</h3>';
    echo '<ol>';
    echo '<li>Notez le <strong>CMID</strong> de l\'activité que vous voulez tester</li>';
    echo '<li>Cliquez sur <strong>"Tester"</strong> pour accéder directement aux tests</li>';
    echo '<li>Ou utilisez le CMID dans l\'URL : <code>view.php?id=<strong>CMID</strong></code></li>';
    echo '</ol>';

    echo '<hr>';
    echo '<h3>🔗 Liens rapides</h3>';
    echo '<div style="margin-bottom: 10px;">';
    foreach ($activities as $activity) {
        echo '<div class="card mb-2" style="padding: 15px;">';
        echo '<h5>' . htmlspecialchars($activity->activity_name) . ' (CMID: ' . $activity->cmid . ')</h5>';
        echo '<ul>';
        echo '<li><a href="mod/gestionprojet/view.php?id=' . $activity->cmid . '" target="_blank">Voir l\'activité</a></li>';
        echo '<li><a href="mod/gestionprojet/view.php?id=' . $activity->cmid . '&step=2" target="_blank">Step 2 - Expression du Besoin</a></li>';
        echo '<li><a href="test_autosave.php?cmid=' . $activity->cmid . '" target="_blank">Test BDD direct</a></li>';
        echo '<li><a href="test_ajax_autosave.html?cmid=' . $activity->cmid . '" target="_blank">Test AJAX</a></li>';
        echo '</ul>';
        echo '</div>';
    }
    echo '</div>';
}

// Vérifier l'état de la BDD pour chaque activité
if (!empty($activities)) {
    echo '<hr>';
    echo '<h3>📊 État de la base de données</h3>';

    foreach ($activities as $activity) {
        echo '<div class="card mb-3" style="padding: 15px;">';
        echo '<h5>' . htmlspecialchars($activity->activity_name) . ' (Instance ID: ' . $activity->instance . ')</h5>';

        // Check each table
        $tables = [
            'gestionprojet_description' => 'Fiche Descriptive',
            'gestionprojet_besoin' => 'Expression du Besoin',
            'gestionprojet_planning' => 'Planning'
        ];

        echo '<table class="table table-sm">';
        echo '<thead><tr><th>Table</th><th>Enregistrements</th><th>Dernière modification</th></tr></thead>';
        echo '<tbody>';

        foreach ($tables as $table => $label) {
            $record = $DB->get_record($table, ['gestionprojetid' => $activity->instance]);

            if ($record) {
                $lastmod = isset($record->timemodified) ? userdate($record->timemodified) : 'N/A';
                $status = '<span style="color: green;">✓ Existe (ID: ' . $record->id . ')</span>';
            } else {
                $lastmod = 'N/A';
                $status = '<span style="color: red;">✗ Aucun</span>';
            }

            echo '<tr>';
            echo '<td>' . $label . '</td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . $lastmod . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}

// Afficher la requête SQL pour référence
echo '<hr>';
echo '<h3>📝 Requête SQL pour phpMyAdmin</h3>';
echo '<p>Vous pouvez aussi utiliser cette requête directement dans phpMyAdmin :</p>';
echo '<pre style="background-color: #f5f5f5; padding: 15px; border-radius: 5px;">';
echo htmlspecialchars($sql);
echo '</pre>';

echo '<hr>';
echo '<h3>🆘 Besoin d\'aide ?</h3>';
echo '<p>Le <strong>CMID (Course Module ID)</strong> est l\'identifiant unique de votre activité dans Moodle.</p>';
echo '<p>Vous pouvez aussi le trouver dans l\'URL quand vous êtes sur l\'activité : <code>mod/gestionprojet/view.php?id=<strong>XX</strong></code></p>';

echo $OUTPUT->footer();
