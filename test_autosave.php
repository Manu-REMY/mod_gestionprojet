<?php
/**
 * Test script for autosave functionality
 *
 * Usage: Place this file in the Moodle root directory and access it via browser:
 * http://your-moodle-site/test_autosave.php?cmid=XX
 *
 * Replace XX with your actual course module ID
 */

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/mod/gestionprojet/lib.php');

$cmid = required_param('cmid', PARAM_INT);
$test = optional_param('test', 'info', PARAM_ALPHA);

$cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);

$PAGE->set_url(new moodle_url('/test_autosave.php', ['cmid' => $cmid]));
$PAGE->set_title('Test Autosave');
$PAGE->set_heading('Test Autosave Functionality');
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);

echo $OUTPUT->header();
echo '<h2>Test de la fonctionnalité d\'autosave</h2>';
echo '<p>Course Module ID: ' . $cmid . '</p>';
echo '<p>Gestion Projet ID: ' . $gestionprojet->id . '</p>';

echo '<div style="margin: 20px 0;">';
echo '<a href="?cmid=' . $cmid . '&test=info" class="btn btn-primary">Voir les infos</a> ';
echo '<a href="?cmid=' . $cmid . '&test=insert" class="btn btn-success">Test INSERT</a> ';
echo '<a href="?cmid=' . $cmid . '&test=update" class="btn btn-warning">Test UPDATE</a> ';
echo '<a href="?cmid=' . $cmid . '&test=cleanup" class="btn btn-danger">Nettoyer</a>';
echo '</div>';

if ($test === 'info') {
    echo '<h3>État actuel de la base de données</h3>';

    // Check description table
    $description = $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]);
    echo '<h4>Table gestionprojet_description:</h4>';
    if ($description) {
        echo '<pre>' . print_r($description, true) . '</pre>';
    } else {
        echo '<p>Aucun enregistrement trouvé.</p>';
    }

    // Check besoin table
    $besoin = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);
    echo '<h4>Table gestionprojet_besoin:</h4>';
    if ($besoin) {
        echo '<pre>' . print_r($besoin, true) . '</pre>';
    } else {
        echo '<p>Aucun enregistrement trouvé.</p>';
    }

    // Check planning table
    $planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);
    echo '<h4>Table gestionprojet_planning:</h4>';
    if ($planning) {
        echo '<pre>' . print_r($planning, true) . '</pre>';
    } else {
        echo '<p>Aucun enregistrement trouvé.</p>';
    }
}

if ($test === 'insert') {
    echo '<h3>Test d\'insertion de données</h3>';

    try {
        $time = time();

        // Test Step 2 (Besoin)
        $record = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);
        if (!$record) {
            $record = new stdClass();
            $record->gestionprojetid = $gestionprojet->id;
            $record->timecreated = $time;
        }

        // Simulate form data
        $formdata = [
            'aqui' => 'Test utilisateur ' . date('H:i:s'),
            'surquoi' => 'Test matière ' . date('H:i:s'),
            'dansquelbut' => 'Test but ' . date('H:i:s'),
            'locked' => 0
        ];

        $validfields = ['aqui', 'surquoi', 'dansquelbut', 'locked'];

        foreach ($formdata as $key => $value) {
            if ($key !== 'id' && in_array($key, $validfields)) {
                echo '<p>Ajout du champ: ' . $key . ' = ' . $value . '</p>';
                $record->$key = $value;
            }
        }

        $record->timemodified = $time;

        if (isset($record->id)) {
            echo '<p>Mise à jour de l\'enregistrement ID: ' . $record->id . '</p>';
            $DB->update_record('gestionprojet_besoin', $record);
            echo '<p class="alert alert-success">✓ Mise à jour réussie!</p>';
        } else {
            echo '<p>Insertion d\'un nouvel enregistrement</p>';
            $record->id = $DB->insert_record('gestionprojet_besoin', $record);
            echo '<p class="alert alert-success">✓ Insertion réussie! ID: ' . $record->id . '</p>';
        }

        // Vérifier l'insertion
        $check = $DB->get_record('gestionprojet_besoin', ['id' => $record->id]);
        echo '<h4>Vérification dans la BDD:</h4>';
        echo '<pre>' . print_r($check, true) . '</pre>';

    } catch (Exception $e) {
        echo '<p class="alert alert-danger">Erreur: ' . $e->getMessage() . '</p>';
    }
}

if ($test === 'update') {
    echo '<h3>Test de mise à jour de données</h3>';

    try {
        $record = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);

        if (!$record) {
            echo '<p class="alert alert-warning">Aucun enregistrement à mettre à jour. Faites d\'abord un INSERT.</p>';
        } else {
            $record->aqui = 'MAJ: ' . date('H:i:s');
            $record->surquoi = 'MAJ: ' . date('H:i:s');
            $record->dansquelbut = 'MAJ: ' . date('H:i:s');
            $record->timemodified = time();

            $DB->update_record('gestionprojet_besoin', $record);
            echo '<p class="alert alert-success">✓ Mise à jour réussie!</p>';

            // Vérifier la mise à jour
            $check = $DB->get_record('gestionprojet_besoin', ['id' => $record->id]);
            echo '<h4>Vérification dans la BDD:</h4>';
            echo '<pre>' . print_r($check, true) . '</pre>';
        }

    } catch (Exception $e) {
        echo '<p class="alert alert-danger">Erreur: ' . $e->getMessage() . '</p>';
    }
}

if ($test === 'cleanup') {
    echo '<h3>Nettoyage des données de test</h3>';

    try {
        // Clean description
        $DB->delete_records('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]);
        echo '<p>✓ Table gestionprojet_description nettoyée</p>';

        // Clean besoin
        $DB->delete_records('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);
        echo '<p>✓ Table gestionprojet_besoin nettoyée</p>';

        // Clean planning
        $DB->delete_records('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);
        echo '<p>✓ Table gestionprojet_planning nettoyée</p>';

        echo '<p class="alert alert-success">✓ Nettoyage terminé!</p>';

    } catch (Exception $e) {
        echo '<p class="alert alert-danger">Erreur: ' . $e->getMessage() . '</p>';
    }
}

echo $OUTPUT->footer();
