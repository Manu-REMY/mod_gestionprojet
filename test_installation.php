#!/usr/bin/env php
<?php
/**
 * Script de test d'installation pour mod_gestionprojet
 *
 * Ce script vérifie que tous les fichiers requis sont présents
 * et que la structure du plugin est correcte.
 */

define('CLI_SCRIPT', true);

$plugin_path = __DIR__ . '/mod_gestionprojet';

echo "=================================================\n";
echo "Test d'Installation - Plugin mod_gestionprojet\n";
echo "=================================================\n\n";

$errors = 0;
$warnings = 0;

// Vérification des fichiers obligatoires
echo "1. Vérification des fichiers obligatoires...\n";
$required_files = [
    'version.php' => 'Fichier de version du plugin',
    'lib.php' => 'Bibliothèque de fonctions',
    'mod_form.php' => 'Formulaire de configuration',
    'view.php' => 'Page de vue principale',
    'db/access.php' => 'Définition des permissions',
    'db/install.xml' => 'Schéma de base de données',
    'db/upgrade.php' => 'Script de mise à jour',
    'lang/en/mod_gestionprojet.php' => 'Fichier de langue anglaise (OBLIGATOIRE)',
    'lang/fr/mod_gestionprojet.php' => 'Fichier de langue française',
];

foreach ($required_files as $file => $description) {
    $filepath = $plugin_path . '/' . $file;
    if (file_exists($filepath)) {
        echo "   ✅ $description : $file\n";
    } else {
        echo "   ❌ MANQUANT: $description : $file\n";
        $errors++;
    }
}

// Vérification de la syntaxe PHP
echo "\n2. Vérification de la syntaxe PHP...\n";
$php_files = [
    'version.php',
    'lib.php',
    'mod_form.php',
    'view.php',
    'db/access.php',
    'db/upgrade.php',
    'lang/en/mod_gestionprojet.php',
];

foreach ($php_files as $file) {
    $filepath = $plugin_path . '/' . $file;
    if (file_exists($filepath)) {
        $output = [];
        $return_var = 0;
        exec("php -l " . escapeshellarg($filepath) . " 2>&1", $output, $return_var);
        if ($return_var === 0) {
            echo "   ✅ $file\n";
        } else {
            echo "   ❌ ERREUR DE SYNTAXE: $file\n";
            echo "      " . implode("\n      ", $output) . "\n";
            $errors++;
        }
    }
}

// Vérification du fichier version.php
echo "\n3. Vérification du fichier version.php...\n";
$version_file = $plugin_path . '/version.php';
$version_content = file_get_contents($version_file);

// Vérifier la présence des variables obligatoires
$required_vars = [
    'component' => '\$plugin->component',
    'version' => '\$plugin->version',
    'requires' => '\$plugin->requires',
    'maturity' => '\$plugin->maturity',
    'release' => '\$plugin->release',
];

foreach ($required_vars as $var => $pattern) {
    if (strpos($version_content, $pattern) !== false) {
        echo "   ✅ Variable $pattern présente\n";
    } else {
        echo "   ❌ Variable $pattern manquante\n";
        $errors++;
    }
}

// Vérifier la version Moodle requise
if (preg_match('/\$plugin->requires\s*=\s*(\d+)/', $version_content, $matches)) {
    $requires = $matches[1];
    echo "   ℹ️  Version Moodle requise: $requires\n";

    // 2024100700 = Moodle 5.0
    if ($requires >= 2024100700) {
        echo "   ✅ Compatible avec Moodle 5.0+\n";
    } else {
        echo "   ⚠️  Version Moodle < 5.0 ($requires)\n";
        $warnings++;
    }
}

// Vérification du composant
if (preg_match('/\$plugin->component\s*=\s*[\'"]([^\'"]+)[\'"]/', $version_content, $matches)) {
    $component = $matches[1];
    if ($component === 'mod_gestionprojet') {
        echo "   ✅ Composant correct: $component\n";
    } else {
        echo "   ❌ Composant incorrect: $component (attendu: mod_gestionprojet)\n";
        $errors++;
    }
}

// Vérification du fichier install.xml
echo "\n4. Vérification du fichier install.xml...\n";
$install_xml = $plugin_path . '/db/install.xml';
if (file_exists($install_xml)) {
    $output = [];
    $return_var = 0;
    exec("xmllint --noout " . escapeshellarg($install_xml) . " 2>&1", $output, $return_var);
    if ($return_var === 0) {
        echo "   ✅ Fichier XML valide\n";

        // Vérifier les tables
        $xml_content = file_get_contents($install_xml);
        $expected_tables = [
            'gestionprojet',
            'gestionprojet_description',
            'gestionprojet_besoin',
            'gestionprojet_planning',
            'gestionprojet_cdcf',
            'gestionprojet_essai',
            'gestionprojet_rapport',
            'gestionprojet_history',
        ];

        foreach ($expected_tables as $table) {
            if (strpos($xml_content, 'NAME="' . $table . '"') !== false) {
                echo "   ✅ Table $table définie\n";
            } else {
                echo "   ⚠️  Table $table non trouvée\n";
                $warnings++;
            }
        }
    } else {
        echo "   ❌ Fichier XML invalide:\n";
        echo "      " . implode("\n      ", $output) . "\n";
        $errors++;
    }
}

// Vérification des chaînes de langue obligatoires
echo "\n5. Vérification des chaînes de langue anglaises...\n";
$lang_file = $plugin_path . '/lang/en/mod_gestionprojet.php';
if (file_exists($lang_file)) {
    include($lang_file);

    $required_strings = [
        'modulename',
        'modulenameplural',
        'pluginname',
        'modulename_help',
    ];

    foreach ($required_strings as $str) {
        if (isset($string[$str])) {
            echo "   ✅ Chaîne '$str' présente\n";
        } else {
            echo "   ❌ Chaîne '$str' manquante (OBLIGATOIRE)\n";
            $errors++;
        }
    }
}

// Vérification de la fonction upgrade
echo "\n6. Vérification de la fonction upgrade...\n";
$upgrade_file = $plugin_path . '/db/upgrade.php';
if (file_exists($upgrade_file)) {
    $upgrade_content = file_get_contents($upgrade_file);
    if (strpos($upgrade_content, 'function xmldb_mod_gestionprojet_upgrade') !== false) {
        echo "   ✅ Fonction xmldb_mod_gestionprojet_upgrade() présente\n";
    } else {
        echo "   ❌ Fonction xmldb_mod_gestionprojet_upgrade() manquante\n";
        $errors++;
    }
}

// Vérification des fonctions obligatoires dans lib.php
echo "\n7. Vérification des fonctions obligatoires dans lib.php...\n";
$lib_file = $plugin_path . '/lib.php';
if (file_exists($lib_file)) {
    $lib_content = file_get_contents($lib_file);

    $required_functions = [
        'gestionprojet_supports',
        'gestionprojet_add_instance',
        'gestionprojet_update_instance',
        'gestionprojet_delete_instance',
    ];

    foreach ($required_functions as $func) {
        if (strpos($lib_content, 'function ' . $func) !== false) {
            echo "   ✅ Fonction $func() présente\n";
        } else {
            echo "   ❌ Fonction $func() manquante (OBLIGATOIRE)\n";
            $errors++;
        }
    }
}

// Résumé
echo "\n=================================================\n";
echo "RÉSUMÉ DU TEST\n";
echo "=================================================\n";

if ($errors === 0 && $warnings === 0) {
    echo "✅ SUCCÈS: Le plugin est prêt pour l'installation !\n";
    echo "   Aucune erreur détectée.\n";
    exit(0);
} elseif ($errors === 0) {
    echo "⚠️  AVERTISSEMENTS: Le plugin peut être installé.\n";
    echo "   $warnings avertissement(s) détecté(s).\n";
    exit(0);
} else {
    echo "❌ ÉCHEC: Le plugin contient des erreurs.\n";
    echo "   $errors erreur(s) et $warnings avertissement(s) détecté(s).\n";
    echo "   Veuillez corriger les erreurs avant l'installation.\n";
    exit(1);
}
