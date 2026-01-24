# Corrections v1.0.1 - Plugin mod_gestionprojet

## 📋 Résumé

Cette version corrige deux problèmes identifiés lors de la première installation du plugin sur Moodle 5.0.1 :

1. ❌ **Erreur PHP** : Classe d'événement manquante
2. ❌ **Icône manquante** : Carré vide dans l'interface

## 🔧 Corrections Appliquées

### 1. Classe d'événement `course_module_viewed`

**Problème :**
```
Exception : Class "mod_gestionprojet\event\course_module_viewed" not found
```

**Solution :**
- Création du fichier `classes/event/course_module_viewed.php`
- Implémentation de la classe héritant de `\core\event\course_module_viewed`
- Méthodes requises : `init()`, `get_url()`, `validate_data()`, `get_objectid_mapping()`

**Impact :**
- ✅ La page de l'activité s'affiche correctement
- ✅ Les événements de consultation sont enregistrés dans les logs Moodle
- ✅ Le suivi des activités fonctionne

---

### 2. Icônes du plugin

**Problème :**
- Pas d'icône visible dans la liste des activités
- Carré vide au lieu de l'icône

**Solution :**
- Création de `pix/icon.svg` (icône principale colorée)
- Création de `pix/monologo.svg` (version monochrome pour les menus)

**Design de l'icône :**
- Document de projet avec coin plié (couleur : #4A90E2)
- Lignes horizontales représentant un diagramme de Gantt
- Badge de validation vert avec checkmark

**Impact :**
- ✅ Icône visible dans la liste des activités du cours
- ✅ Icône visible dans le menu "Ajouter une activité"
- ✅ Meilleure identification visuelle du plugin

---

## 📦 Fichiers Ajoutés

```
mod_gestionprojet/
├── classes/
│   └── event/
│       └── course_module_viewed.php    [NOUVEAU]
└── pix/
    ├── icon.svg                        [NOUVEAU]
    └── monologo.svg                    [NOUVEAU]
```

## 🚀 Installation de la Mise à Jour

### Option 1 : Remplacement via SSH (Recommandé)

```bash
# 1. Sauvegarde de l'ancien plugin
mv /path/to/moodle/mod/gestionprojet /path/to/moodle/mod/gestionprojet.backup

# 2. Extraction du nouveau package
unzip mod_gestionprojet_v1.0.1.zip -d /path/to/moodle/mod/

# 3. Permissions
chmod -R 755 /path/to/moodle/mod/gestionprojet
chown -R www-data:www-data /path/to/moodle/mod/gestionprojet

# 4. Purge du cache via l'interface Moodle
# Administration → Développement → Purger tous les caches
```

### Option 2 : Via FTP/SFTP

1. Télécharger `mod_gestionprojet_v1.0.1.zip`
2. Décompresser localement
3. Sauvegarder le dossier actuel `mod/gestionprojet`
4. Remplacer par le nouveau dossier via FTP/SFTP
5. Purger les caches dans Moodle

### Option 3 : Désinstallation/Réinstallation

⚠️ **ATTENTION** : Cette méthode supprime toutes les données existantes (activités, soumissions, etc.)

À utiliser uniquement en dernier recours si les options 1 et 2 échouent.

## ✅ Vérification Post-Installation

### 1. Vérifier les fichiers

```bash
ls -la /path/to/moodle/mod/gestionprojet/classes/event/
# Doit afficher : course_module_viewed.php

ls -la /path/to/moodle/mod/gestionprojet/pix/
# Doit afficher : icon.svg, monologo.svg
```

### 2. Vérifier l'interface Moodle

- [ ] L'icône est visible dans la liste des activités
- [ ] Aucune erreur en accédant à l'activité "Gestion de Projet"
- [ ] La page d'accueil du plugin s'affiche correctement

### 3. Vérifier les logs (optionnel)

Administration → Rapports → Journaux
- Rechercher "Module viewed" pour "Gestion de Projet"
- L'événement doit être enregistré sans erreur

## 🐛 Dépannage

### L'erreur persiste après la mise à jour

1. **Purger tous les caches**
   - Cache Moodle : Administration → Développement → Purger tous les caches
   - Cache PHP opcache (si activé) : redémarrer PHP-FPM
   - Cache navigateur : Ctrl+Shift+R

2. **Vérifier les permissions**
   ```bash
   find /path/to/moodle/mod/gestionprojet -type d -exec chmod 755 {} \;
   find /path/to/moodle/mod/gestionprojet -type f -exec chmod 644 {} \;
   ```

3. **Consulter les logs PHP**
   ```bash
   tail -f /var/log/apache2/error.log
   # ou
   tail -f /var/log/nginx/error.log
   ```

### L'icône ne s'affiche toujours pas

1. Vérifier que les fichiers SVG sont présents dans `pix/`
2. Purger le cache des thèmes : Administration → Apparence → Thèmes → Paramètres du thème → Purger tous les caches
3. Vider le cache du navigateur (Ctrl+Shift+Suppr)

## 📝 Changelog

### [1.0.1] - 2026-01-19

#### Ajouté
- Classe d'événement `course_module_viewed` pour le logging des activités
- Icône SVG colorée (`icon.svg`)
- Icône SVG monochrome (`monologo.svg`)

#### Corrigé
- Erreur "Class not found" lors de l'accès à l'activité
- Icône manquante dans l'interface

### [1.0.0] - 2026-01-17

#### Ajouté
- Version initiale du plugin
- 6 étapes de gestion de projet (3 enseignant + 3 élèves)
- Système de groupes
- Sauvegarde automatique
- Historique des modifications
- Export PDF

## 📞 Support

En cas de problème persistant :

1. Vérifier la compatibilité : Moodle 5.0+ requis
2. Consulter les logs d'erreur PHP
3. Vérifier la documentation Moodle sur les événements et les icônes

## 🔗 Références

- [Moodle Plugin Development - Events](https://docs.moodle.org/dev/Events_API)
- [Moodle Plugin Development - Icons](https://docs.moodle.org/dev/Moodle_icons)
- [Moodle Plugin Structure](https://docs.moodle.org/dev/Plugin_files)
