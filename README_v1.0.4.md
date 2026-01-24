# Plugin Moodle - Gestion de Projet v1.0.4

## 📦 Informations sur la version

- **Version** : 1.0.4
- **Date de sortie** : 19 janvier 2026
- **Compatibilité** : Moodle 5.0+
- **Type de mise à jour** : Correction critique (OBLIGATOIRE)

## 🔴 Correction critique

Cette version corrige un **bug majeur** qui empêchait l'enregistrement des données dans la base de données.

**Si vous utilisez une version antérieure (1.0.0, 1.0.1, 1.0.2, ou 1.0.3), vous DEVEZ mettre à jour vers la version 1.0.4.**

## ✅ Qu'est-ce qui a été corrigé ?

Le problème principal était que les données saisies par les utilisateurs (fiche descriptive, expression du besoin, planning, CDCF, essai, rapport) n'étaient pas enregistrées dans les tables de la base de données.

**Cause** : Le code utilisait `property_exists()` pour vérifier les champs avant de les enregistrer, mais cela échouait avec les nouveaux enregistrements.

**Solution** : Remplacement par des listes explicites de champs valides pour chaque étape.

## 📥 Installation

### Fichiers inclus

- `mod_gestionprojet_v1.0.4.zip` - Plugin complet prêt à installer

### Procédure d'installation

1. **Téléchargez** le fichier `mod_gestionprojet_v1.0.4.zip`
2. **Connectez-vous** à votre Moodle en tant qu'administrateur
3. **Allez dans** : Administration du site > Plugins > Installer un plugin
4. **Glissez-déposez** le fichier ZIP dans la zone prévue
5. **Suivez** les instructions à l'écran
6. **Cliquez** sur "Mettre à jour la base de données Moodle"

Moodle détectera automatiquement qu'il s'agit d'une mise à jour et procédera en conséquence.

## 🧪 Test de l'installation

Après l'installation, vérifiez que tout fonctionne :

1. Créez ou ouvrez une activité "Gestion de Projet"
2. Allez dans "Expression du besoin" (Step 2)
3. Saisissez du texte dans les trois champs
4. Attendez 30 secondes (temps d'autosave par défaut)
5. Vérifiez dans la BDD que les données sont présentes :

```sql
SELECT * FROM mdl_gestionprojet_besoin
WHERE gestionprojetid = (
    SELECT instance FROM mdl_course_modules WHERE id = VOTRE_CMID
);
```

## 📋 Modifications détaillées

### Fichiers modifiés

1. **mod_gestionprojet/ajax/autosave.php**
   - Ajout de listes `$validfields` pour les 6 étapes
   - Remplacement de `property_exists()` par `in_array()`

2. **mod_gestionprojet/version.php**
   - Version : 2026011903 → 2026011904
   - Release : 1.0.3 → 1.0.4

### Tables concernées

Toutes les tables suivantes sont maintenant correctement mises à jour :
- `mdl_gestionprojet_description` (Step 1)
- `mdl_gestionprojet_besoin` (Step 2)
- `mdl_gestionprojet_planning` (Step 3)
- `mdl_gestionprojet_cdcf` (Step 4)
- `mdl_gestionprojet_essai` (Step 5)
- `mdl_gestionprojet_rapport` (Step 6)

## 🔧 Scripts de test (optionnels)

Des scripts de test sont disponibles pour vérifier le bon fonctionnement :

### trouver_cmid.php
Trouve facilement les CMID de vos activités.

**Usage** : Placez à la racine de Moodle et accédez via `http://votre-moodle/trouver_cmid.php`

### test_autosave.php
Teste directement l'insertion dans la BDD.

**Usage** : `http://votre-moodle/test_autosave.php?cmid=XX&test=insert`

### test_ajax_autosave.html
Teste l'endpoint AJAX de manière interactive.

**⚠️ Important** : Ces scripts sont fournis uniquement pour le développement et les tests. **Ne les laissez pas sur un serveur de production.**

## 📚 Documentation

- **CORRECTIONS_v1.0.4.md** - Détails complets des corrections
- **GUIDE_RAPIDE.md** - Guide de démarrage rapide
- **GUIDE_DEPANNAGE_AUTOSAVE.md** - Guide de dépannage complet

## 🆘 Support

Si vous rencontrez des problèmes après la mise à jour :

1. Vérifiez que vous avez bien la version 1.0.4 :
   ```sql
   SELECT value FROM mdl_config_plugins
   WHERE plugin = 'mod_gestionprojet' AND name = 'version';
   ```
   Devrait retourner : **2026011904**

2. Purgez le cache Moodle :
   - Administration du site > Développement > Purger tous les caches
   - Ou en CLI : `php admin/cli/purge_caches.php`

3. Consultez le guide de dépannage : `GUIDE_DEPANNAGE_AUTOSAVE.md`

## 📝 Notes de version

### v1.0.4 (19 janvier 2026)
- ✅ **Correction critique** : Enregistrement des données maintenant fonctionnel
- ✅ Ajout de listes de champs valides pour toutes les étapes
- ✅ Testé et validé sur environnement de production

### v1.0.3 (19 janvier 2026)
- Correction de la navigation et des libellés OST

### v1.0.2 (19 janvier 2026)
- Correction de la navigation

### v1.0.1 (17 janvier 2026)
- Corrections mineures

### v1.0.0 (17 janvier 2026)
- Version initiale

## 📜 Licence

GNU General Public License v3.0 or later

## 👨‍💻 Auteur

Emmanuel REMY - 2026
