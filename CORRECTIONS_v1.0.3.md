# Corrections v1.0.3 - Plugin Moodle Gestion de Projet

**Date**: 19 janvier 2026
**Version**: 1.0.3 (2026011903)

---

## 📋 Résumé des corrections

Cette version corrige des problèmes critiques de navigation et d'enregistrement sur la page "Expression du besoin" (Step 2), et améliore le diagramme bête à corne pour le rendre plus fidèle au modèle pédagogique standard.

---

## 🐛 Corrections de bugs

### 1. Erreur d'installation - Fonction upgrade manquante

**Problème**: L'installation/mise à jour du plugin échouait avec l'erreur "Call to undefined function xmldb_gestionprojet_upgrade()".

**Fichier modifié**: `db/upgrade.php` (ligne 33)

**Solution**:
- Correction du nom de fonction: `xmldb_gestionprojet_upgrade()` au lieu de `xmldb_mod_gestionprojet_upgrade()`
- Moodle attend le format `xmldb_{pluginname}_upgrade()` sans le préfixe `mod_`

**Impact**:
- ✅ Installation du plugin fonctionnelle
- ✅ Mise à jour depuis versions antérieures possible

---

### 2. Navigation défectueuse sur Step 2

**Problème**: Les boutons "Précédent" et "Suivant" généraient des URLs incorrectes de type `https://preprod.ent-occitanie.com/mod/view.php?id=3` au lieu de `/mod/gestionprojet/view.php?id=3&step=X`.

**Fichier modifié**: `pages/step2.php` (lignes 49-69)

**Solution**:
- Ajout d'une vérification `MOODLE_INTERNAL` pour détecter le mode d'inclusion
- En mode "inclus depuis view.php": utilisation de `moodle_url` avec paramètres corrects
- En mode "accès direct": utilisation de chemins relatifs avec `&step=`

**URLs générées maintenant**:
```
← Précédent: /mod/gestionprojet/view.php?id=3&step=1
Suivant →: /mod/gestionprojet/view.php?id=3&step=3
```

---

### 3. Enregistrement des données non fonctionnel

**Problème**: Les données saisies dans les champs "À qui", "Sur quoi" et "Dans quel but" ne s'enregistraient pas. Le système utilisait `property_exists()` qui ne fonctionnait pas avec les objets récupérés de la base de données Moodle.

**Fichier modifié**: `ajax/autosave.php` (lignes 98-120)

**Solution**:
- Remplacement de `property_exists($record, $key)` par une liste explicite de champs valides
- Ajout de `$validfields = ['aqui', 'surquoi', 'dansquelbut', 'locked']`
- Utilisation de `in_array($key, $validfields)` pour la validation

**Impact**:
- ✅ Autosave fonctionnel sur tous les champs du formulaire
- ✅ Verrouillage de page correctement enregistré
- ✅ Historique des modifications (change log) fonctionnel

---

## 🎨 Améliorations visuelles

### 4. Amélioration du diagramme bête à corne

**Fichier modifié**: `pages/step2.php` (lignes 505-559)

**Améliorations apportées**:

#### Courbe supérieure ("corne")
- Points de départ/arrivée repositionnés aux bords intérieurs des ellipses
- Courbe passe au-dessus de la boîte produit centrale
- Cercles de connexion aux extrémités

#### Courbe inférieure
- Nouvelle courbe bézier cubique reliant le produit à la boîte "Dans quel but"
- Forme caractéristique de la "bête à corne" plus fidèle au modèle pédagogique
- Flèche directionnelle vers le but
- Cercle de départ sous la boîte produit

**Résultat**: Le diagramme correspond désormais visuellement au modèle de référence de la page https://technologie.forge.apps.education.fr/gestion-de-projet/expression-besoin.html

---

## 📊 Diagramme dynamique

Le diagramme SVG se met à jour en temps réel lors de la saisie dans les champs :

| Champ | Position | Description |
|-------|----------|-------------|
| **À qui** | Ellipse gauche | Utilisateur/bénéficiaire du produit |
| **Sur quoi** | Ellipse droite | Matière d'œuvre sur laquelle agit le produit |
| **Dans quel but** | Rectangle bas | Fonction d'usage ou besoin satisfait |
| **Produit** | Centre (violet) | Objet technique étudié |

---

## 🔧 Détails techniques

### Fichiers modifiés
1. `mod_gestionprojet/pages/step2.php`
   - Navigation (lignes 49-69)
   - Diagramme SVG (lignes 505-559)

2. `mod_gestionprojet/ajax/autosave.php`
   - Logique d'enregistrement Step 2 (lignes 86-121)

3. `mod_gestionprojet/db/upgrade.php`
   - Correction du nom de fonction: `xmldb_gestionprojet_upgrade()` au lieu de `xmldb_mod_gestionprojet_upgrade()` (ligne 33)

4. `mod_gestionprojet/version.php`
   - Version: `2026011903`
   - Release: `1.0.3`

### Base de données
Aucune modification de schéma requise. La table `gestionprojet_besoin` reste inchangée avec les champs :
- `aqui` (TEXT)
- `surquoi` (TEXT)
- `dansquelbut` (TEXT)
- `locked` (INT)

---

## 🚀 Installation / Mise à jour

### Depuis Moodle
1. Télécharger `mod_gestionprojet_v1.0.3.zip`
2. Administration du site → Plugins → Installer des plugins
3. Sélectionner le fichier ZIP
4. Cliquer sur "Mettre à jour la base de données"

### Depuis le serveur
```bash
cd /path/to/moodle/mod/
rm -rf gestionprojet
unzip mod_gestionprojet_v1.0.3.zip
chown -R www-data:www-data gestionprojet
php admin/cli/upgrade.php --non-interactive
```

---

## ✅ Tests effectués

- [x] Navigation entre Step 1 ↔ Step 2 ↔ Step 3
- [x] Enregistrement automatique des champs texte
- [x] Mise à jour dynamique du diagramme
- [x] Verrouillage de page fonctionnel
- [x] Affichage correct du diagramme bête à corne
- [x] URLs générées correctement depuis view.php

---

## 📝 Notes de migration depuis v1.0.2

Cette mise à jour est **rétrocompatible**. Aucune action spéciale n'est requise :
- Les données existantes sont préservées
- Pas de modification de schéma de base de données
- Les paramètres de configuration restent identiques

---

## 🔜 Prochaines améliorations prévues

- Export SVG du diagramme bête à corne
- Mode impression optimisé
- Validation des champs obligatoires
- Templates de réponses prédéfinis

---

**Développeur**: Emmanuel REMY
**License**: GNU GPL v3 or later
**Compatibilité**: Moodle 5.0+
