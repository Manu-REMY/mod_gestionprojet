#!/bin/bash

echo "================================================="
echo "Test d'Installation - Plugin mod_gestionprojet"
echo "================================================="
echo ""

PLUGIN_DIR="/Users/remyemmanuel/Documents/Claude code/Plugin Moodle/mod_gestionprojet"
ERRORS=0

echo "✓ Test 1: Fichiers obligatoires"
echo "--------------------------------"

declare -A FILES=(
    ["$PLUGIN_DIR/version.php"]="version.php"
    ["$PLUGIN_DIR/lib.php"]="lib.php"
    ["$PLUGIN_DIR/db/upgrade.php"]="db/upgrade.php"
    ["$PLUGIN_DIR/db/install.xml"]="db/install.xml"
    ["$PLUGIN_DIR/db/access.php"]="db/access.php"
    ["$PLUGIN_DIR/lang/en/mod_gestionprojet.php"]="lang/en/mod_gestionprojet.php"
)

for file in "${!FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✅ ${FILES[$file]}"
    else
        echo "  ❌ ${FILES[$file]} MANQUANT"
        ((ERRORS++))
    fi
done

echo ""
echo "✓ Test 2: Syntaxe PHP"
echo "---------------------"

php -l "$PLUGIN_DIR/version.php" > /dev/null 2>&1 && echo "  ✅ version.php" || { echo "  ❌ version.php"; ((ERRORS++)); }
php -l "$PLUGIN_DIR/lib.php" > /dev/null 2>&1 && echo "  ✅ lib.php" || { echo "  ❌ lib.php"; ((ERRORS++)); }
php -l "$PLUGIN_DIR/db/upgrade.php" > /dev/null 2>&1 && echo "  ✅ db/upgrade.php" || { echo "  ❌ db/upgrade.php"; ((ERRORS++)); }
php -l "$PLUGIN_DIR/lang/en/mod_gestionprojet.php" > /dev/null 2>&1 && echo "  ✅ lang/en/mod_gestionprojet.php" || { echo "  ❌ lang/en/mod_gestionprojet.php"; ((ERRORS++)); }

echo ""
echo "✓ Test 3: Validation XML"
echo "------------------------"

xmllint --noout "$PLUGIN_DIR/db/install.xml" > /dev/null 2>&1 && echo "  ✅ install.xml valide" || { echo "  ❌ install.xml invalide"; ((ERRORS++)); }

echo ""
echo "✓ Test 4: Configuration version.php"
echo "------------------------------------"

if grep -q '\$plugin->component.*=.*mod_gestionprojet' "$PLUGIN_DIR/version.php"; then
    echo "  ✅ Component: mod_gestionprojet"
else
    echo "  ❌ Component incorrect"
    ((ERRORS++))
fi

if grep -q '\$plugin->requires.*=.*2024100700' "$PLUGIN_DIR/version.php"; then
    echo "  ✅ Requires: Moodle 5.0+"
else
    echo "  ⚠️  Version Moodle < 5.0"
fi

echo ""
echo "================================================="
echo "RÉSUMÉ"
echo "================================================="

if [ $ERRORS -eq 0 ]; then
    echo "✅ SUCCÈS: Plugin prêt pour l'installation!"
    echo "   0 erreur détectée"
    exit 0
else
    echo "❌ ÉCHEC: $ERRORS erreur(s) détectée(s)"
    exit 1
fi
