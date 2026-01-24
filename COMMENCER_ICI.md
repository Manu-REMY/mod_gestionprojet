# 🚀 Commencer ici - Plugin Moodle Gestion de Projet v1.0.4

## ✅ Vous êtes au bon endroit !

Ce fichier vous guide pour installer et utiliser le plugin **Gestion de Projet v1.0.4**.

---

## 📦 1. Fichiers importants

### Pour l'installation
- **mod_gestionprojet_v1.0.4.zip** ← Le plugin à installer

### Documentation
- **README_v1.0.4.md** ← Informations sur la version
- **LIVRAISON_v1.0.4.md** ← Guide de livraison complet
- **GUIDE_RAPIDE.md** ← Guide rapide
- **CORRECTIONS_v1.0.4.md** ← Détails techniques

### Scripts de test (optionnels)
- **trouver_cmid.php** ← Trouve vos CMID facilement
- **test_autosave.php** ← Teste l'enregistrement
- **test_ajax_autosave.html** ← Teste l'AJAX

---

## 🎯 2. Installation en 5 étapes

### Étape 1 : Télécharger
Récupérez le fichier **mod_gestionprojet_v1.0.4.zip**

### Étape 2 : Installer dans Moodle
1. Connectez-vous en tant qu'**administrateur**
2. Allez dans : **Administration > Plugins > Installer un plugin**
3. **Glissez-déposez** le fichier ZIP
4. Cliquez sur **"Mettre à jour la base de données"**
5. ✅ Terminé !

### Étape 3 : Vérifier la version
Dans phpMyAdmin ou via SQL :
```sql
SELECT value FROM mdl_config_plugins
WHERE plugin = 'mod_gestionprojet' AND name = 'version';
```
**Résultat attendu** : `2026011904`

### Étape 4 : Purger le cache
- **Via l'interface** : Administration > Développement > Purger tous les caches
- **Via CLI** : `php admin/cli/purge_caches.php`

### Étape 5 : Tester
1. Créez une activité "Gestion de Projet" dans un cours
2. Ouvrez-la
3. Allez sur "Expression du besoin" (Step 2)
4. Saisissez du texte
5. Attendez 30 secondes
6. Vérifiez dans la BDD que les données sont là

---

## 🧪 3. Comment tester rapidement

### Option A : Test via l'interface (recommandé)
1. Créez une activité Gestion de Projet
2. Remplissez les formulaires
3. Vérifiez dans phpMyAdmin que les données sont enregistrées

### Option B : Test via script
1. Copiez `trouver_cmid.php` à la racine de Moodle
2. Ouvrez `http://votre-moodle/trouver_cmid.php`
3. Notez le CMID de votre activité
4. Cliquez sur "Tester" pour vérifier l'enregistrement

---

## 🔍 4. Vérifier dans la base de données

### Trouver votre CMID
```sql
SELECT cm.id as cmid, g.name as activity_name, c.fullname as course_name
FROM mdl_course_modules cm
JOIN mdl_modules m ON cm.module = m.id
JOIN mdl_gestionprojet g ON cm.instance = g.id
JOIN mdl_course c ON cm.course = c.id
WHERE m.name = 'gestionprojet';
```

### Vérifier les données enregistrées
```sql
-- Remplacez 3 par votre gestionprojetid (instance)
SELECT * FROM mdl_gestionprojet_besoin WHERE gestionprojetid = 3;
SELECT * FROM mdl_gestionprojet_description WHERE gestionprojetid = 3;
SELECT * FROM mdl_gestionprojet_planning WHERE gestionprojetid = 3;
```

---

## ❓ 5. Problèmes fréquents

### "Les données ne s'enregistrent pas"
**Solution** :
1. Vérifiez que vous avez la version 1.0.4
2. Purgez le cache Moodle
3. Attendez bien 30 secondes après la saisie
4. Utilisez le script `test_autosave.php` pour diagnostiquer

### "Je ne trouve pas mon CMID"
**Solution** :
- Utilisez le script `trouver_cmid.php`
- Ou regardez l'URL quand vous êtes sur l'activité : `view.php?id=XX`

### "Erreur lors de l'installation"
**Solution** :
1. Vérifiez les permissions de fichiers
2. Consultez les logs Moodle
3. Essayez de désinstaller puis réinstaller

---

## 📚 6. Documentation complète

Pour aller plus loin :

| Document | Contenu |
|----------|---------|
| **README_v1.0.4.md** | Informations sur la version |
| **LIVRAISON_v1.0.4.md** | Guide de livraison détaillé |
| **GUIDE_RAPIDE.md** | Guide de démarrage rapide |
| **GUIDE_DEPANNAGE_AUTOSAVE.md** | Dépannage approfondi |
| **CORRECTIONS_v1.0.4.md** | Détails techniques |

---

## 🎓 7. Utilisation du plugin

### Pour les enseignants

1. **Créer une activité**
   - Dans votre cours, activez le mode édition
   - Ajoutez une activité → Gestion de Projet

2. **Configurer le projet** (Steps 1-3)
   - Step 1 : Fiche descriptive
   - Step 2 : Expression du besoin (Bête à Corne)
   - Step 3 : Planning

3. **Les élèves travaillent** (Steps 4-6)
   - Step 4 : Cahier des charges fonctionnel
   - Step 5 : Fiche d'essai
   - Step 6 : Rapport de projet

4. **Noter les travaux**
   - Cliquez sur "Noter les travaux"
   - Attribuez une note et un feedback

### Pour les élèves

1. Ouvrez l'activité Gestion de Projet
2. Suivez les étapes 4, 5 et 6
3. Remplissez les formulaires
4. Les données sont sauvegardées automatiquement toutes les 30 secondes

---

## ⚡ 8. Démarrage rapide (2 minutes)

```bash
# 1. Télécharger le ZIP
# (Récupérez mod_gestionprojet_v1.0.4.zip)

# 2. Installer via Moodle
# Administration > Plugins > Installer un plugin
# Glissez-déposez le ZIP

# 3. Purger le cache
php admin/cli/purge_caches.php

# 4. Tester
# Créez une activité et testez la saisie
```

---

## 🎯 9. Checklist de déploiement

- [ ] Plugin téléchargé
- [ ] Plugin installé dans Moodle
- [ ] Version 2026011904 confirmée
- [ ] Cache purgé
- [ ] Activité de test créée
- [ ] Données enregistrées correctement
- [ ] Formation des enseignants planifiée
- [ ] Documentation distribuée

---

## 📞 10. Besoin d'aide ?

### Étape par étape
1. Lisez le **GUIDE_RAPIDE.md**
2. Si problème, consultez **GUIDE_DEPANNAGE_AUTOSAVE.md**
3. Utilisez les scripts de test
4. Vérifiez la version installée

### Ressources
- Documentation complète dans les fichiers `.md`
- Scripts de test disponibles
- Requêtes SQL fournies

---

## ✨ C'est parti !

Vous êtes maintenant prêt à installer et utiliser le plugin **Gestion de Projet v1.0.4**.

**Temps estimé** : 10 minutes pour l'installation et les premiers tests.

**Prochaine étape** : Installez le plugin en suivant la section 2 ci-dessus ! 🚀

---

**Version** : 1.0.4
**Date** : 19 janvier 2026
**Statut** : ✅ Production Ready
