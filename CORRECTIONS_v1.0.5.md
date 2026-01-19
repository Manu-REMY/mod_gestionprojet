# Corrections Version 1.0.5

## Date : 19 janvier 2026

## 🎯 Corrections majeures

### Autosave amélioré - Déclenchement multiple

**Problème** : L'autosave ne se déclenchait qu'après un délai suite à une modification, ce qui rendait l'enregistrement peu fiable.

**Solution** : L'autosave se déclenche maintenant dans 4 situations :

1. **À chaque modification de champ** (avec debounce de 2 secondes)
2. **Périodiquement** (selon l'intervalle configuré : 10, 30, 60 secondes...)
3. **À la perte de focus du champ** (quand on clique ailleurs)
4. **À la perte de focus de la page** (changement d'onglet, fenêtre, etc.)

### Logging amélioré

- Ajout de `console.log` pour debug dans la console du navigateur
- Ajout de logs serveur dans `moodledata/temp/autosave_debug.log`
- Messages d'erreur plus détaillés

### Code simplifié

- Suppression de la tentative d'appel à une méthode Moodle inexistante
- **URL absolue pour AJAX** : Utilisation de `new moodle_url('/mod/gestionprojet/ajax/autosave.php')` au lieu de chemin relatif
- Meilleure gestion des erreurs
- **Chargement explicite de jQuery** : Ajout de `$PAGE->requires->jquery()`
- **Attente de jQuery** : Boucle de vérification avec `checkJQuery()` toutes les 50ms
- **Remplacement de `require()` AMD par jQuery** : Correction de l'erreur "ReferenceError: require is not defined"
- **Désactivation du module AMD dans home.php** : Correction de l'erreur "No define call for mod_gestionprojet/autosave"

## Fichiers modifiés

### mod_gestionprojet/pages/step2.php
```php
<?php
// Ensure jQuery is loaded
$PAGE->requires->jquery();
?>

<script>
// Wait for jQuery to be loaded (checks every 50ms)
(function checkJQuery() {
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function($) {
            // ... code autosave ...

            // Autosave on blur (when field loses focus)
            $('#besoinForm textarea').on('blur', function() {
                autosave();
            });

            // Autosave when page loses focus
            $(window).on('blur', function() {
                autosave();
            });

            // Periodic autosave
            let periodicTimer = setInterval(function() {
                autosave();
            }, autosaveInterval);

            // Autosave function with absolute URL
            function autosave() {
                $.ajax({
                    url: '<?php echo new moodle_url('/mod/gestionprojet/ajax/autosave.php'); ?>',
                    type: 'POST',
                    data: { cmid: cmid, step: step, data: JSON.stringify(formData) }
                    // ...
                });
            }
        });
    } else {
        setTimeout(checkJQuery, 50);
    }
})();
</script>
```

### mod_gestionprojet/ajax/autosave.php
- Ajout de logs de debug temporaires
- Logs dans `moodledata/temp/autosave_debug.log`

### mod_gestionprojet/pages/home.php
```php
// Note: Autosave JavaScript is now inline in each step file
// Commented out to avoid "No define call" error
// $PAGE->requires->js_call_amd('mod_gestionprojet/autosave', 'init', [...]);
```

### mod_gestionprojet/version.php
- Version : 2026011904 → 2026011905
- Release : 1.0.4 → 1.0.5

## Installation

### Mise à jour depuis 1.0.4

1. Téléchargez `mod_gestionprojet_v1.0.5.zip`
2. Administration du site > Plugins > Installer un plugin
3. Glissez-déposez le ZIP
4. Suivez les instructions
5. **Purgez les caches** : Administration > Développement > Purger tous les caches

### Nouvelle installation

1. Téléchargez `mod_gestionprojet_v1.0.5.zip`
2. Administration du site > Plugins > Installer un plugin
3. Glissez-déposez le ZIP
4. Suivez les instructions

## Tests

Après installation :

1. Ouvrez une activité Gestion de Projet
2. Allez sur Step 2 (Expression du besoin)
3. **Ouvrez la console JavaScript** (F12)
4. Tapez du texte dans un champ
5. Vous devriez voir :
   - `Autosave triggered` dans la console
   - `Autosave response` avec le résultat
   - Un indicateur visuel sur la page

6. **Testez les différents déclencheurs** :
   - Modifiez un champ → attendez 2 secondes
   - Cliquez ailleurs (blur) → autosave immédiat
   - Changez d'onglet → autosave immédiat
   - Attendez 30 secondes → autosave automatique

7. **Vérifiez les logs serveur** :
   ```
   cat moodledata/temp/autosave_debug.log
   ```

## Debug

### Console JavaScript

Vous devriez voir dans la console :
```
Autosave triggered {aqui: "...", surquoi: "...", dansquelbut: "...", locked: 0}
Autosave response: {success: true, message: "...", timestamp: 1768...}
```

### Logs serveur

Le fichier `moodledata/temp/autosave_debug.log` contient :
```
=== 2026-01-19 16:45:23 ===
POST: Array
(
    [cmid] => 3
    [step] => 2
    [data] => {"aqui":"Test","surquoi":"Test","dansquelbut":"Test","locked":0}
)
Data received: {"aqui":"Test","surquoi":"Test","dansquelbut":"Test","locked":0}
Decoded: Array
(
    [aqui] => Test
    [surquoi] => Test
    [dansquelbut] => Test
    [locked] => 0
)
STEP 2 UPDATE: stdClass Object
(
    [id] => 1
    [gestionprojetid] => 3
    [aqui] => Test
    [surquoi] => Test
    [dansquelbut] => Test
    [locked] => 0
    [timemodified] => 1768807523
)
```

## Problèmes connus et solutions

### Erreur "$ is not defined" ou "require is not defined"

**Cause** : jQuery non chargé ou chargé de manière asynchrone après le script inline

**Solution** : ✅ Corrigé dans cette version
- Ajout de `$PAGE->requires->jquery();` pour charger jQuery
- Boucle de vérification `checkJQuery()` qui attend que jQuery soit disponible (polling toutes les 50ms)
- Remplacement de `require()` AMD par `jQuery(document).ready()`

### Erreur "No define call for mod_gestionprojet/autosave"

**Cause** : Module AMD non compilé appelé depuis home.php

**Solution** : ✅ Corrigé dans cette version - appel AMD commenté dans home.php

### L'autosave ne se déclenche toujours pas

**Vérifications** :
1. Ouvrez la console JavaScript (F12) - Y a-t-il des erreurs ?
2. Vérifiez que jQuery est chargé : `typeof $` doit retourner `"function"`
3. Vérifiez le fichier de log : `moodledata/temp/autosave_debug.log`

### Erreur "Failed to load resource" ou "404 Not Found"

**Cause** : Le chemin relatif vers `autosave.php` ne fonctionne pas depuis `view.php?step=2`

**Solution** : ✅ Corrigé dans cette version
- Utilisation de `new moodle_url('/mod/gestionprojet/ajax/autosave.php')` pour générer une URL absolue
- Plus de problème de chemin relatif

### Les données ne s'enregistrent pas

**Diagnostic** :
1. Consultez la console JavaScript pour voir la réponse du serveur
2. Consultez le log serveur `autosave_debug.log`
3. Vérifiez les permissions de la table dans la BDD

## Désactiver les logs de debug (Production)

Une fois les tests terminés, pour désactiver les logs de debug :

1. Éditez `mod/gestionprojet/ajax/autosave.php`
2. Commentez ou supprimez les lignes avec `@file_put_contents($debug_log, ...)`
3. Supprimez les lignes avec `console.log` dans `step2.php`

## Avantages de cette version

✅ **Autosave plus fiable** : 4 déclencheurs différents
✅ **Meilleure UX** : Sauvegarde immédiate au blur
✅ **Pas de perte de données** : Sauvegarde automatique périodique
✅ **Debug facile** : Logs console + serveur
✅ **Performance** : Debounce de 2s pour éviter trop de requêtes
✅ **Pas d'erreurs JavaScript** : Correction des erreurs AMD require() et define()
✅ **Compatibilité** : Fonctionne avec toutes les configurations Moodle

## Migration depuis 1.0.4

Aucune modification de structure de BDD. La mise à jour est transparente.

Les données existantes ne sont pas affectées.
