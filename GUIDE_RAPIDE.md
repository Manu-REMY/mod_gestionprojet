# Guide Rapide - Tester l'autosave v1.0.4

## Étape 1 : Trouver votre CMID

### Méthode 1 : Utiliser le script `trouver_cmid.php`

1. Placez le fichier `trouver_cmid.php` à la racine de votre installation Moodle
2. Ouvrez dans votre navigateur : `http://votre-moodle/trouver_cmid.php`
3. Vous verrez la liste de toutes vos activités Gestion de Projet avec leur CMID

### Méthode 2 : Via l'URL de l'activité

1. Allez sur votre cours Moodle
2. Cliquez sur votre activité "Gestion de Projet"
3. Regardez l'URL dans la barre d'adresse : `mod/gestionprojet/view.php?id=XX`
4. Le nombre après `id=` est votre CMID

**Exemple :** Si l'URL est `http://moodle.local/mod/gestionprojet/view.php?id=5`, votre CMID est **5**

### Méthode 3 : Via phpMyAdmin

```sql
SELECT cm.id as cmid, g.name as activity_name, c.fullname as course_name
FROM mdl_course_modules cm
JOIN mdl_modules m ON cm.module = m.id
JOIN mdl_gestionprojet g ON cm.instance = g.id
JOIN mdl_course c ON cm.course = c.id
WHERE m.name = 'gestionprojet';
```

## Étape 2 : Tester la sauvegarde

### Test simple (recommandé)

1. Ouvrez `http://votre-moodle/trouver_cmid.php`
2. Cliquez sur le bouton **"Tester"** à côté de votre activité
3. Cliquez sur **"Test INSERT"**
4. Vérifiez que les données sont insérées

### Test complet

1. Allez sur votre activité : `http://votre-moodle/mod/gestionprojet/view.php?id=VOTRE_CMID`
2. Cliquez sur **"Expression du besoin"** (Step 2)
3. Saisissez du texte dans les 3 champs
4. Attendez 30 secondes (l'intervalle d'autosave par défaut)
5. Vérifiez dans la BDD

## Étape 3 : Vérifier dans la base de données

### Via phpMyAdmin

```sql
-- Remplacez 5 par votre CMID
SELECT b.*
FROM mdl_gestionprojet_besoin b
JOIN mdl_gestionprojet g ON b.gestionprojetid = g.id
JOIN mdl_course_modules cm ON cm.instance = g.id
WHERE cm.id = 5;
```

### Via le script `trouver_cmid.php`

Le script affiche automatiquement l'état de la BDD pour chaque activité.

## Étape 4 : Consulter les logs de debug

Si les données ne s'enregistrent pas, consultez le fichier de log :

```bash
# Emplacement
moodledata/temp/autosave_debug.log

# Voir le contenu
cat moodledata/temp/autosave_debug.log

# Suivre en temps réel
tail -f moodledata/temp/autosave_debug.log
```

## Résolution des problèmes courants

### Problème 1 : "Je ne trouve pas mon activité"

**Solution :** Créez d'abord une activité Gestion de Projet dans un cours :
1. Allez dans votre cours
2. Activez le mode édition
3. Ajoutez une activité → Gestion de Projet
4. Donnez-lui un nom et sauvegardez

### Problème 2 : "L'autosave ne se déclenche pas"

**Solutions :**
- Attendez bien 30 secondes après avoir saisi du texte
- Ouvrez la console JavaScript (F12) et vérifiez qu'il n'y a pas d'erreurs
- Essayez de taper manuellement dans la console : `autosave()`
- Purgez le cache Moodle : Administration > Purger tous les caches

### Problème 3 : "Les champs restent NULL dans la BDD"

**Solutions :**
- Vérifiez que vous avez bien la version 1.0.4 : `SELECT value FROM mdl_config_plugins WHERE plugin = 'mod_gestionprojet' AND name = 'version';` (doit être 2026011904)
- Vérifiez le fichier de log `autosave_debug.log`
- Essayez le test direct avec `test_autosave.php?cmid=XX&test=insert`

### Problème 4 : "Permission denied"

**Solutions :**
- Vérifiez que vous êtes connecté en tant qu'enseignant ou administrateur
- Vérifiez les permissions de votre rôle dans le cours

## Commandes utiles

### Purger le cache Moodle
```bash
php admin/cli/purge_caches.php
```

### Vérifier la version du plugin
```sql
SELECT * FROM mdl_config_plugins WHERE plugin = 'mod_gestionprojet';
```

### Supprimer les données de test
Utilisez `test_autosave.php?cmid=XX&test=cleanup`

## Fichiers importants

- **trouver_cmid.php** : Trouve tous vos CMID
- **test_autosave.php** : Test direct de la BDD
- **test_ajax_autosave.html** : Test de l'endpoint AJAX
- **autosave_debug.log** : Logs de debug (dans moodledata/temp/)
- **GUIDE_DEPANNAGE_AUTOSAVE.md** : Guide complet de dépannage

## Support

Si vous avez encore des problèmes après avoir suivi ce guide :

1. Consultez le fichier `autosave_debug.log`
2. Vérifiez la console JavaScript (F12 dans le navigateur)
3. Utilisez le guide complet : `GUIDE_DEPANNAGE_AUTOSAVE.md`
4. Assurez-vous d'avoir la version 1.0.4 du plugin
