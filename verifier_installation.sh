#!/bin/bash

# Script de vérification de l'installation de mod_gestionprojet v1.0.1
# Usage: ./verifier_installation.sh /chemin/vers/moodle

if [ -z "$1" ]; then
    echo "Usage: $0 /chemin/vers/moodle"
    echo "Exemple: $0 /var/www/html/moodle"
    exit 1
fi

MOODLE_PATH="$1"
PLUGIN_PATH="$MOODLE_PATH/mod/gestionprojet"

echo "================================================"
echo "  Vérification Plugin mod_gestionprojet v1.0.1"
echo "================================================"
echo ""

ERRORS=0
WARNINGS=0

# Vérification 1: Dossier du plugin
echo "1. Vérification du dossier du plugin..."
if [ -d "$PLUGIN_PATH" ]; then
    echo "   ✓ Dossier trouvé: $PLUGIN_PATH"
else
    echo "   ✗ Dossier NON trouvé: $PLUGIN_PATH"
    echo "   Le plugin n'est pas installé à cet emplacement."
    exit 1
fi

# Vérification 2: Fichiers critiques
echo ""
echo "2. Vérification des fichiers critiques..."
FILES=(
    "version.php"
    "lib.php"
    "view.php"
    "db/install.xml"
    "db/upgrade.php"
    "db/access.php"
    "lang/en/gestionprojet.php"
    "lang/fr/gestionprojet.php"
)

for file in "${FILES[@]}"; do
    if [ -f "$PLUGIN_PATH/$file" ]; then
        echo "   ✓ $file"
    else
        echo "   ✗ $file MANQUANT"
        ((ERRORS++))
    fi
done

# Vérification 3: Nouveaux fichiers v1.0.1
echo ""
echo "3. Vérification des nouveaux fichiers v1.0.1..."

if [ -f "$PLUGIN_PATH/classes/event/course_module_viewed.php" ]; then
    echo "   ✓ classes/event/course_module_viewed.php"
else
    echo "   ✗ classes/event/course_module_viewed.php MANQUANT"
    echo "     → Cette classe est NÉCESSAIRE pour éviter l'erreur PHP"
    ((ERRORS++))
fi

if [ -f "$PLUGIN_PATH/pix/icon.svg" ]; then
    echo "   ✓ pix/icon.svg"
else
    echo "   ✗ pix/icon.svg MANQUANT"
    echo "     → L'icône du plugin sera invisible"
    ((WARNINGS++))
fi

if [ -f "$PLUGIN_PATH/pix/monologo.svg" ]; then
    echo "   ✓ pix/monologo.svg"
else
    echo "   ⚠ pix/monologo.svg MANQUANT"
    echo "     → Version monochrome de l'icône manquante (non critique)"
    ((WARNINGS++))
fi

# Vérification 4: Permissions
echo ""
echo "4. Vérification des permissions..."

# Vérifier que le serveur web peut lire les fichiers
WEB_USER=$(ps aux | grep -E 'apache|httpd|nginx' | grep -v root | head -1 | awk '{print $1}')

if [ -z "$WEB_USER" ]; then
    echo "   ⚠ Impossible de détecter l'utilisateur du serveur web"
    echo "     Vérifiez manuellement les permissions"
    ((WARNINGS++))
else
    echo "   ℹ Utilisateur du serveur web détecté: $WEB_USER"

    # Vérifier la propriété
    OWNER=$(stat -c '%U' "$PLUGIN_PATH" 2>/dev/null || stat -f '%Su' "$PLUGIN_PATH" 2>/dev/null)
    if [ "$OWNER" = "$WEB_USER" ] || [ "$OWNER" = "root" ]; then
        echo "   ✓ Propriétaire: $OWNER (OK)"
    else
        echo "   ⚠ Propriétaire: $OWNER (devrait être $WEB_USER ou root)"
        ((WARNINGS++))
    fi
fi

# Vérification 5: Version PHP
echo ""
echo "5. Vérification de la syntaxe PHP..."

PHP_BIN=$(which php 2>/dev/null)
if [ -z "$PHP_BIN" ]; then
    echo "   ⚠ PHP CLI non trouvé, impossible de vérifier la syntaxe"
    ((WARNINGS++))
else
    PHP_VERSION=$($PHP_BIN -v | head -1)
    echo "   ℹ $PHP_VERSION"

    # Vérifier la syntaxe des nouveaux fichiers
    if [ -f "$PLUGIN_PATH/classes/event/course_module_viewed.php" ]; then
        OUTPUT=$($PHP_BIN -l "$PLUGIN_PATH/classes/event/course_module_viewed.php" 2>&1)
        if echo "$OUTPUT" | grep -q "No syntax errors"; then
            echo "   ✓ Syntaxe course_module_viewed.php correcte"
        else
            echo "   ✗ Erreur de syntaxe dans course_module_viewed.php"
            echo "     $OUTPUT"
            ((ERRORS++))
        fi
    fi
fi

# Vérification 6: Base de données (si accessible)
echo ""
echo "6. Vérification de la base de données..."
echo "   ℹ Connectez-vous à Moodle pour vérifier:"
echo "     Administration → Plugins → Modules d'activité"
echo "     → 'Gestion de Projet' doit être listé"

# Résumé
echo ""
echo "================================================"
echo "  RÉSUMÉ"
echo "================================================"

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo "✓ PARFAIT: Plugin correctement installé"
    echo "  0 erreur, 0 avertissement"
    echo ""
    echo "Prochaines étapes:"
    echo "1. Connectez-vous à Moodle en tant qu'admin"
    echo "2. Allez dans: Administration → Notifications"
    echo "3. Purgez les caches:"
    echo "   Administration → Développement → Purger tous les caches"
    echo "4. Testez l'accès à une activité 'Gestion de Projet'"
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo "⚠ ATTENTION: $WARNINGS avertissement(s)"
    echo "  Le plugin devrait fonctionner mais avec des problèmes mineurs"
    echo ""
    echo "Vérifiez les avertissements ci-dessus"
    exit 0
else
    echo "✗ ERREURS DÉTECTÉES: $ERRORS erreur(s), $WARNINGS avertissement(s)"
    echo ""
    echo "Veuillez corriger les erreurs avant de continuer"
    echo "Consultez: CORRECTIONS_v1.0.1.md pour plus d'informations"
    exit 1
fi
