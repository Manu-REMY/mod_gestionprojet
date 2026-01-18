# 📋 Plan d'Action - Plugin Moodle Gestion de Projet

## ✅ Phase 1 : Fondations (TERMINÉE)

### Architecture et Configuration
- [x] Créer la structure du plugin `mod_gestionprojet`
- [x] Définir les fichiers de base (version.php, lib.php, mod_form.php)
- [x] Configurer les métadonnées du plugin

### Base de données
- [x] Schéma XML complet avec 8 tables
- [x] Support des groupes Moodle
- [x] Système d'historique des modifications
- [x] Gestion des notes par étape

### Permissions et Sécurité
- [x] 9 capacités définies (view, submit, grade, lock, etc.)
- [x] Rôles : enseignant, élève, guest
- [x] Vérifications de sécurité CSRF/XSS

## ✅ Phase 2 : Interface Utilisateur (TERMINÉE)

### Page d'accueil
- [x] Navigation différenciée enseignant/élève
- [x] Cartes pour les 6 étapes
- [x] Indicateurs de progression
- [x] Affichage du groupe de l'élève
- [x] Section correction pour enseignants

### Système de sauvegarde automatique
- [x] Endpoint AJAX (`ajax/autosave.php`)
- [x] Module JavaScript AMD (`amd/src/autosave.js`)
- [x] Indicateur visuel de statut
- [x] Intervalle configurable (10-120s)
- [x] Sauvegarde différenciée par étape
- [x] Historique d'audit complet

### Interface de correction
- [x] Navigation par étape avec contexte conservé
- [x] Boutons Précédent/Suivant entre groupes
- [x] Sélecteur d'étape (4, 5, 6)
- [x] Formulaire de notation /20
- [x] Zone de commentaires
- [x] Affichage du contenu de la soumission

## 🔄 Phase 3 : Migration des Outils (EN COURS)

### Étapes enseignant (à migrer depuis le projet original)

#### ⏳ Étape 1 : Fiche Descriptive
```
Fichier à créer : pages/step1.php
Source : description.html du projet original

Tâches :
- [ ] Convertir le formulaire HTML en formulaire Moodle
- [ ] Intégrer l'upload d'image avec l'API file de Moodle
- [ ] Gérer la sélection des compétences
- [ ] Implémenter le verrouillage
- [ ] Ajouter l'export PDF (TCPDF)
- [ ] Connecter à l'autosave JavaScript
```

#### ⏳ Étape 2 : Expression du Besoin
```
Fichier à créer : pages/step2.php
Source : expression-besoin.html du projet original

Tâches :
- [ ] Migrer le formulaire Bête à Corne
- [ ] Conserver le canvas HTML5 pour le diagramme
- [ ] Adapter le code JavaScript pour Moodle
- [ ] Génération PDF côté serveur avec TCPDF
- [ ] Système de verrouillage
- [ ] Intégration autosave
```

#### ⏳ Étape 3 : Planification
```
Fichier à créer : pages/step3.php
Source : gestion-projet.html du projet original

Tâches :
- [ ] Migrer le formulaire de planification
- [ ] Intégrer l'API vacances scolaires (data.education.gouv.fr)
- [ ] Adapter la timeline interactive
- [ ] Calculs des semaines avec exclusion des vacances
- [ ] Génération du carnet de bord PDF
- [ ] Verrouillage et autosave
```

### Étapes élèves (à migrer)

#### ⏳ Étape 4 : Cahier des Charges Fonctionnel
```
Fichier à créer : pages/step4.php
Source : cahier-charges.html du projet original

Tâches :
- [ ] Migrer le formulaire CDCF
- [ ] Adapter le canvas pour le diagramme des interacteurs
- [ ] Algorithme de courbes de Bézier (10 règles)
- [ ] Gestion dynamique des interacteurs
- [ ] Tableau des fonctions (FP, FC, FS)
- [ ] Export PDF avec diagramme
- [ ] Autosave avec groupid
```

#### ⏳ Étape 5 : Fiche Essai
```
Fichier à créer : pages/step5.php
Source : fiche-essai.html du projet original

Tâches :
- [ ] Formulaire d'essai expérimental
- [ ] Éditeur de texte enrichi (Moodle editor)
- [ ] Sections : objectif, protocole, précautions, résultats, conclusion
- [ ] Export PDF (2 pages)
- [ ] Autosave collaboratif
```

#### ⏳ Étape 6 : Rapport de Projet
```
Fichier à créer : pages/step6.php
Source : conclusion.html du projet original

Tâches :
- [ ] Formulaire multi-sections
- [ ] Champs : auteurs, besoins, solutions, réalisation, validation
- [ ] Export PDF rapport complet
- [ ] Autosave final
```

## 🎨 Phase 4 : Polissage et Optimisation

### Design et UX
- [ ] Responsive design mobile/tablette
- [ ] Thème Bootstrap 4 Moodle
- [ ] Animations CSS
- [ ] Accessibilité WCAG 2.1
- [ ] Mode sombre (optionnel)

### Performance
- [ ] Optimisation des requêtes SQL
- [ ] Cache Moodle pour les vacances scolaires
- [ ] Minification JavaScript
- [ ] Lazy loading des images

### Export et Partage
- [ ] Export ZIP de tous les PDFs d'un groupe
- [ ] Export global pour l'enseignant (tous les groupes)
- [ ] Partage de projet entre classes
- [ ] Import/Export format JSON

## 🧪 Phase 5 : Tests et Validation

### Tests unitaires (PHPUnit)
- [ ] Tests des fonctions lib.php
- [ ] Tests de la sauvegarde automatique
- [ ] Tests des permissions
- [ ] Tests du calcul de notes

### Tests d'intégration (Behat)
- [ ] Scénario complet enseignant
- [ ] Scénario complet élève
- [ ] Scénario de correction
- [ ] Scénario de collaboration groupe

### Tests manuels
- [ ] Test sur Moodle 4.0, 4.1, 4.2, 4.3
- [ ] Test avec différents navigateurs
- [ ] Test avec groupes séparés/visibles
- [ ] Test des exports PDF

## 📚 Phase 6 : Documentation

### Documentation utilisateur
- [x] README.md complet
- [ ] Guide enseignant (PDF)
- [ ] Guide élève (PDF)
- [ ] Vidéos de démonstration

### Documentation technique
- [ ] PHPDoc complète
- [ ] Diagrammes d'architecture
- [ ] Guide de contribution
- [ ] Changelog

## 🚀 Phase 7 : Déploiement

### Préparation
- [ ] Validation code Moodle (moodle-plugin-ci)
- [ ] Vérification sécurité
- [ ] Tests de montée en charge
- [ ] Traduction anglaise

### Publication
- [ ] Soumission au répertoire Moodle officiel
- [ ] Publication sur GitHub
- [ ] Page de présentation
- [ ] Annonce sur forum Moodle France

## 📊 État actuel

### Fichiers créés (11/30)
```
✅ version.php           - Métadonnées
✅ lib.php               - 400 lignes de fonctions
✅ mod_form.php          - Formulaire de config
✅ view.php              - Point d'entrée
✅ grading.php           - Interface de correction
✅ db/install.xml        - Schéma DB (8 tables)
✅ db/access.php         - 9 capacités
✅ lang/fr/gestionprojet.php - 100+ chaînes
✅ pages/home.php        - Page d'accueil
✅ ajax/autosave.php     - Endpoint AJAX
✅ amd/src/autosave.js   - Module JavaScript
```

### Fichiers à créer (19/30)
```
⏳ pages/step1.php       - Fiche Descriptive
⏳ pages/step2.php       - Expression du Besoin
⏳ pages/step3.php       - Planification
⏳ pages/step4.php       - CDCF
⏳ pages/step5.php       - Fiche Essai
⏳ pages/step6.php       - Rapport
⏳ classes/event/course_module_viewed.php
⏳ classes/privacy/provider.php
⏳ backup/moodle2/backup_gestionprojet_stepslib.php
⏳ backup/moodle2/restore_gestionprojet_stepslib.php
⏳ tests/lib_test.php
⏳ tests/behat/basic_functionality.feature
⏳ pix/icon.svg
⏳ pix/monologo.svg
⏳ CHANGELOG.md
⏳ CONTRIBUTING.md
⏳ LICENSE
⏳ .github/workflows/moodle-ci.yml
⏳ composer.json
```

## 🎯 Prochaines étapes recommandées

### Priorité 1 (Critique)
1. **Créer pages/step1.php** (Fiche Descriptive)
   - Migrer depuis description.html
   - Intégrer autosave
   - Tester le verrouillage

2. **Créer pages/step2.php** (Expression du Besoin)
   - Canvas Bête à Corne
   - Génération PDF

3. **Créer pages/step3.php** (Planification)
   - API vacances scolaires
   - Timeline interactive

### Priorité 2 (Important)
4. **Créer pages/step4-6.php** (Étapes élèves)
   - CDCF, Essai, Rapport
   - Collaboration groupe

5. **Événements Moodle**
   - course_module_viewed
   - submission_created
   - grading_updated

6. **Conformité RGPD**
   - classes/privacy/provider.php
   - Export/suppression données

### Priorité 3 (Souhaitable)
7. **Backup/Restore**
   - Sauvegarde activité
   - Restauration

8. **Tests automatisés**
   - PHPUnit
   - Behat

9. **Documentation**
   - Guides PDF
   - Vidéos

## 📈 Métriques de progression

- **Architecture** : 100% ✅
- **Base de données** : 100% ✅
- **Permissions** : 100% ✅
- **Interface de base** : 100% ✅
- **Sauvegarde auto** : 100% ✅
- **Correction** : 100% ✅
- **Migration outils** : 0% ⏳
- **Tests** : 0% ⏳
- **Documentation** : 50% 🔄

**Progression globale : 35%**

## 💡 Notes importantes

### Points forts du développement actuel
- ✅ Architecture solide et extensible
- ✅ Sauvegarde temps réel fonctionnelle
- ✅ Interface de correction innovante
- ✅ Historique complet des modifications
- ✅ Documentation exhaustive

### Défis à relever
- ⚠️ Migration des canvas HTML5 (diagrammes)
- ⚠️ Génération PDF côté serveur (TCPDF)
- ⚠️ Intégration API vacances scolaires
- ⚠️ Gestion des fichiers uploadés (images)
- ⚠️ Tests de charge avec nombreux groupes

### Choix techniques validés
- 🎯 Mode de groupe Moodle natif
- 🎯 AJAX avec sesskey pour sécurité
- 🎯 AMD pour JavaScript modulaire
- 🎯 Historique dans table dédiée
- 🎯 Note moyenne des 3 étapes élèves

### Prochaine session de développement
**Objectif** : Migrer la page 1 (Fiche Descriptive)
**Durée estimée** : 2-3 heures
**Livrable** : `pages/step1.php` fonctionnel avec autosave

---

*Document mis à jour le 17 janvier 2026*
*Plugin Moodle - Gestion de Projet v1.0.0-alpha*
