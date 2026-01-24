# Guide de Dépannage - Autosave v1.0.4

## Problème : Les données ne s'enregistrent pas

Si vous constatez que les données saisies dans les formulaires ne sont pas enregistrées dans la base de données, suivez ce guide étape par étape.

## Étape 1 : Vérifier que les fichiers sont à jour

Assurez-vous que vous avez bien la version 1.0.4 du plugin :

```sql
SELECT * FROM mdl_config_plugins WHERE plugin = 'mod_gestionprojet' AND name = 'version';
```

La version doit être **2026011904**.

## Étape 2 : Tester l'insertion manuelle dans la BDD

Utilisez le script `test_autosave.php` :

1. Placez `test_autosave.php` à la racine de votre installation Moodle
2. Accédez à : `http://votre-moodle/test_autosave.php?cmid=XX` (remplacez XX par votre cmid)
3. Cliquez sur "Test INSERT"
4. Vérifiez que les données sont insérées

Si l'insertion manuelle fonctionne mais pas l'autosave, le problème vient du JavaScript ou de l'endpoint AJAX.

## Étape 3 : Activer les logs de debug

Les logs de debug sont maintenant activés dans `autosave.php`. Les logs sont écrits dans :

```
moodledata/temp/autosave_debug.log
```

### Vérifier le fichier de log

```bash
tail -f moodledata/temp/autosave_debug.log
```

Ensuite, allez sur une page du plugin (par exemple Step 2), saisissez du texte dans un champ, et attendez que l'autosave se déclenche (par défaut 30 secondes).

### Ce que vous devriez voir dans le log :

1. **Les paramètres reçus** :
```
timestamp => 2026-01-19 14:30:45
REQUEST => Array
(
    [cmid] => 5
    [step] => 2
    [data] => {"aqui":"Test","surquoi":"Test","dansquelbut":"Test","locked":0}
)
```

2. **Les données décodées** :
```
Decoded formdata:
Array
(
    [aqui] => Test
    [surquoi] => Test
    [dansquelbut] => Test
    [locked] => 0
)
```

3. **Le résultat de la sauvegarde** :
```
STEP 2: Updated record ID 1
stdClass Object
(
    [id] => 1
    [gestionprojetid] => 3
    [aqui] => Test
    [surquoi] => Test
    [dansquelbut] => Test
    [locked] => 0
    [timecreated] => 1768807758
    [timemodified] => 1768807820
)
```

## Étape 4 : Vérifier la console JavaScript

Ouvrez la console du navigateur (F12) et vérifiez :

1. **Aucune erreur JavaScript** : Il ne doit pas y avoir d'erreur rouge
2. **Les requêtes AJAX** : Vous devriez voir des requêtes vers `ajax/autosave.php` toutes les 30 secondes (ou selon votre configuration)
3. **La réponse du serveur** : La réponse doit être `{"success":true,"message":"...","timestamp":...}`

### Commandes de debug dans la console :

```javascript
// Voir les données collectées
console.log(collectFormData());

// Déclencher manuellement l'autosave
autosave();
```

## Étape 5 : Tester l'endpoint AJAX directement

Utilisez le fichier `test_ajax_autosave.html` :

1. Ouvrez `http://votre-moodle/test_ajax_autosave.html` dans votre navigateur
2. Connectez-vous d'abord à Moodle dans un autre onglet
3. Entrez votre Course Module ID (cmid)
4. Cliquez sur "Envoyer Test Autosave"
5. Vérifiez le résultat

## Étape 6 : Problèmes courants et solutions

### Problème 1 : "Invalid data" ou "Failed to decode JSON"

**Cause** : Les données envoyées ne sont pas au format JSON valide

**Solution** :
- Vérifiez que le JavaScript collecte correctement les données
- Vérifiez que `JSON.stringify()` fonctionne
- Vérifiez le log pour voir exactement ce qui est reçu

### Problème 2 : Les champs restent NULL dans la BDD

**Cause** : Les noms de champs ne correspondent pas ou ne sont pas dans `$validfields`

**Solution** :
- Vérifiez que les champs HTML ont les bons attributs `name` :
  ```html
  <textarea name="aqui" ...>
  <textarea name="surquoi" ...>
  <textarea name="dansquelbut" ...>
  ```
- Vérifiez que ces noms sont dans `$validfields` dans `autosave.php`

### Problème 3 : L'autosave ne se déclenche jamais

**Cause** : Le JavaScript ne détecte pas les changements dans les champs

**Solution** :
- Vérifiez que jQuery est chargé
- Vérifiez que le sélecteur `$('#besoinForm textarea')` trouve les bons éléments
- Vérifiez que l'événement `on('input', ...)` est bien attaché
- Essayez de déclencher manuellement : `autosave()` dans la console

### Problème 4 : "nopermission" error

**Cause** : L'utilisateur n'a pas les permissions nécessaires

**Solution** :
- Vérifiez que vous êtes bien enseignant ou administrateur
- Pour les étapes 1-3, il faut la capacité `mod/gestionprojet:configureteacherpages`
- Pour les étapes 4-6, il faut la capacité `mod/gestionprojet:submit`

## Étape 7 : Vérifier directement dans la BDD

```sql
-- Vérifier les enregistrements de besoin
SELECT b.*, g.name as activity_name
FROM mdl_gestionprojet_besoin b
JOIN mdl_gestionprojet g ON b.gestionprojetid = g.id
WHERE b.timemodified > UNIX_TIMESTAMP(NOW() - INTERVAL 1 HOUR);

-- Vérifier tous les enregistrements
SELECT 'description' as type, COUNT(*) as count FROM mdl_gestionprojet_description
UNION ALL
SELECT 'besoin', COUNT(*) FROM mdl_gestionprojet_besoin
UNION ALL
SELECT 'planning', COUNT(*) FROM mdl_gestionprojet_planning
UNION ALL
SELECT 'cdcf', COUNT(*) FROM mdl_gestionprojet_cdcf
UNION ALL
SELECT 'essai', COUNT(*) FROM mdl_gestionprojet_essai
UNION ALL
SELECT 'rapport', COUNT(*) FROM mdl_gestionprojet_rapport;
```

## Étape 8 : Nettoyer le cache Moodle

Parfois, le JavaScript peut être en cache :

1. Administration du site > Développement > Purger tous les caches
2. Ou via CLI : `php admin/cli/purge_caches.php`
3. Rechargez la page avec Ctrl+Shift+R (hard refresh)

## Étape 9 : Vérifier les permissions de fichiers

Le fichier de log doit être accessible en écriture :

```bash
# Vérifier
ls -la moodledata/temp/

# Corriger si nécessaire
chmod 777 moodledata/temp/
```

## Support supplémentaire

Si le problème persiste après avoir suivi toutes ces étapes :

1. Rassemblez les informations suivantes :
   - Version de Moodle
   - Version du plugin (doit être 1.0.4)
   - Contenu du fichier `autosave_debug.log`
   - Erreurs de la console JavaScript
   - Résultat de la requête SQL pour vérifier la BDD

2. Vérifiez les fichiers modifiés dans la version 1.0.4 :
   - `mod_gestionprojet/ajax/autosave.php` : doit avoir les listes `$validfields`
   - `mod_gestionprojet/version.php` : doit être à 2026011904

3. En dernier recours, réinstallez le plugin :
   - Sauvegardez vos données
   - Désinstallez le plugin
   - Réinstallez la version 1.0.4
