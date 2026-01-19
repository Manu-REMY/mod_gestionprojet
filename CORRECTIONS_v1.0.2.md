# Corrections v1.0.2 - Plugin mod_gestionprojet

## 📋 Résumé

Cette version corrige les problèmes identifiés lors des premiers tests fonctionnels du plugin :

1. ❌ **Erreur "paramètre cmid manquant"** lors de la navigation entre les pages
2. ❌ **Libellés OST incorrects** (OST1 et OST2 ne correspondaient pas au référentiel)

## 🔧 Corrections Appliquées

### 1. Navigation entre les pages - Erreur "paramètre cmid manquant"

**Problème :**
```
Un paramètre requis (cmid) est manquant
```

**Cause :**
Les pages step2.php, step3.php, step4.php, step5.php et step6.php étaient conçues uniquement comme pages standalone (avec `require_once config.php`), mais elles sont incluses par `view.php`. Cela créait un conflit lors de l'inclusion.

**Solution :**
Modification de toutes les pages step pour fonctionner dans deux modes :
- **Mode inclusion** : Quand appelées via `view.php?id=X&step=Y` (variables déjà définies)
- **Mode standalone** : Quand appelées directement (initialisation complète)

**Fichiers modifiés :**
- `pages/step2.php` - Détection du mode via `!defined('MOODLE_INTERNAL')`
- `pages/step3.php` - Détection du mode via `!defined('MOODLE_INTERNAL')`
- `pages/step4.php` - Détection du mode via `!defined('MOODLE_INTERNAL')`
- `pages/step5.php` - Détection du mode via `!defined('MOODLE_INTERNAL')`
- `pages/step6.php` - Détection du mode via `!defined('MOODLE_INTERNAL')`

**Impact :**
✅ La navigation entre les étapes fonctionne correctement
✅ Les pages peuvent être incluses ou accédées directement
✅ Plus d'erreur "paramètre manquant"

---

### 2. Correction des libellés des compétences OST

**Problème :**
Les libellés OST1 et OST2 ne correspondaient pas au référentiel officiel de la technologie au collège.

**Avant (incorrect) :**
- OST1: "Pratiquer des démarches scientifiques et technologiques"
- OST2: "Concevoir, créer, réaliser"
- OST3: "Caractériser et choisir un objet ou un système technique selon différents critères" ✓

**Après (conforme au référentiel) :**
- OST1: "Décrire les liens entre usages et évolutions technologiques des objets et des systèmes techniques"
- OST2: "Décrire les interactions entre un objet ou un système technique, son environnement et les utilisateurs"
- OST3: "Caractériser et choisir un objet ou un système technique selon différents critères" ✓

**Référence :**
https://technologie.forge.apps.education.fr/gestion-de-projet/description.html

**Fichiers modifiés :**
- `pages/step1.php` (lignes 481 et 490)

**Impact :**
✅ Les compétences affichées correspondent au référentiel officiel
✅ Cohérence avec les documents pédagogiques existants

---

## 📦 Fichiers Modifiés

```
mod_gestionprojet/
├── pages/
│   ├── step1.php     [MODIFIÉ] - Libellés OST corrigés
│   ├── step2.php     [MODIFIÉ] - Support mode inclusion
│   ├── step3.php     [MODIFIÉ] - Support mode inclusion
│   ├── step4.php     [MODIFIÉ] - Support mode inclusion
│   ├── step5.php     [MODIFIÉ] - Support mode inclusion
│   └── step6.php     [MODIFIÉ] - Support mode inclusion
```

**6 fichiers modifiés**

## 🚀 Installation de la Mise à Jour

### Via SSH (Recommandé)

```bash
# 1. Sauvegarde
mv /path/to/moodle/mod/gestionprojet \
   /path/to/moodle/mod/gestionprojet.backup_v101

# 2. Extraction du nouveau package
cd /path/to/moodle/mod/
unzip /chemin/vers/mod_gestionprojet_v1.0.2.zip

# 3. Permissions
chmod -R 755 gestionprojet
chown -R www-data:www-data gestionprojet

# 4. Purge des caches (dans l'interface Moodle)
# Administration → Développement → Purger tous les caches
```

### Via FTP/SFTP

1. Télécharger `mod_gestionprojet_v1.0.2.zip`
2. Décompresser localement
3. Sauvegarder le dossier actuel `mod/gestionprojet`
4. Remplacer par le nouveau dossier via FTP/SFTP
5. Purger les caches dans Moodle

### Important
⚠️ **Aucune mise à jour de base de données n'est nécessaire** - Il s'agit uniquement de corrections de code PHP.

## ✅ Vérification Post-Installation

### 1. Test de navigation

- [ ] Accéder à une activité "Gestion de Projet"
- [ ] Cliquer sur "Configurer" pour l'étape 1 (Fiche Descriptive)
- [ ] Vérifier que la page s'affiche correctement
- [ ] Cliquer sur le bouton "Suivant →" pour aller à l'étape 2
- [ ] Vérifier que la navigation fonctionne sans erreur
- [ ] Tester la navigation vers l'étape 3

### 2. Vérification des compétences OST

- [ ] Accéder à l'étape 1 (Fiche Descriptive)
- [ ] Descendre à la section "Compétences travaillées"
- [ ] Vérifier les libellés OST1 et OST2 :
  - OST1 doit contenir "usages et évolutions technologiques"
  - OST2 doit contenir "interactions entre un objet"

### 3. Test complet du workflow

- [ ] Remplir la fiche descriptive (Étape 1)
- [ ] Naviguer vers l'étape 2 (Expression du Besoin)
- [ ] Remplir les 3 champs de la Bête à Corne
- [ ] Naviguer vers l'étape 3 (Planification)
- [ ] Aucune erreur ne doit apparaître

## 🐛 Dépannage

### L'erreur "cmid manquant" persiste

1. **Purger TOUS les caches**
   ```bash
   # Dans Moodle
   Administration → Développement → Purger tous les caches

   # Cache PHP (si opcache est activé)
   sudo service php-fpm restart
   # ou
   sudo systemctl restart php7.4-fpm
   ```

2. **Vérifier que les fichiers sont bien mis à jour**
   ```bash
   # Sur le serveur
   grep -n "defined('MOODLE_INTERNAL')" /path/to/moodle/mod/gestionprojet/pages/step2.php
   # Doit afficher une ligne avec !defined('MOODLE_INTERNAL')
   ```

3. **Vérifier les permissions**
   ```bash
   ls -la /path/to/moodle/mod/gestionprojet/pages/
   # Tous les fichiers doivent être lisibles (permissions 644 ou 755)
   ```

### Les libellés OST ne sont pas mis à jour

1. Purger le cache des chaînes de langue :
   ```
   Administration → Langue → Caches de langue → Purger
   ```

2. Vider le cache du navigateur (Ctrl+Shift+R)

3. Vérifier le fichier directement :
   ```bash
   grep "OST1" /path/to/moodle/mod/gestionprojet/pages/step1.php
   # Doit afficher "usages et évolutions technologiques"
   ```

## 📝 Changelog

### [1.0.2] - 2026-01-19

#### Corrigé
- Erreur "paramètre cmid manquant" lors de la navigation entre les pages enseignant
- Pages step2-6 adaptées pour fonctionner en mode inclusion et standalone
- Libellé OST1 : "Décrire les liens entre usages et évolutions technologiques..." (conforme au référentiel)
- Libellé OST2 : "Décrire les interactions entre un objet ou un système technique..." (conforme au référentiel)

#### Technique
- Détection automatique du mode d'exécution (inclusion vs standalone) pour les pages step
- Correction de l'appel à `gestionprojet_get_or_create_submission()` (paramètres simplifiés)

### [1.0.1] - 2026-01-19

#### Ajouté
- Classe d'événement `course_module_viewed`
- Icônes SVG (icon.svg, monologo.svg)

#### Corrigé
- Erreur "Class not found" lors de l'accès à l'activité
- Icône manquante dans l'interface

### [1.0.0] - 2026-01-17

#### Initial
- Version initiale du plugin
- 6 étapes de gestion de projet

## 🔗 Références

- [Site de référence - Gestion de Projet](https://technologie.forge.apps.education.fr/gestion-de-projet/)
- [Référentiel OST](https://technologie.forge.apps.education.fr/gestion-de-projet/description.html)

## 📞 Support

En cas de problème persistant :

1. Vérifier la compatibilité : Moodle 5.0+ requis
2. Consulter les logs d'erreur PHP : `tail -f /var/log/apache2/error.log`
3. Vérifier les permissions des fichiers
4. Purger TOUS les caches (Moodle + PHP + Navigateur)
