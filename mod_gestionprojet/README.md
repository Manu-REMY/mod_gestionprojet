# Plugin Moodle - Gestion de Projet 📋

Plugin Moodle pour la gestion collaborative de projets éducatifs en 6 étapes structurées.

## 🎯 Vue d'ensemble

Ce plugin Moodle transforme l'application web "Carnet de Bord - Outils de Gestion de Projet" en une activité de cours Moodle complète avec :

- **3 étapes configurées par l'enseignant** (lecture seule pour les élèves)
- **3 étapes complétées par les élèves** (en groupe)
- **Sauvegarde automatique en temps réel** (toutes les 30 secondes)
- **Interface de correction par étape** avec navigation entre groupes
- **Système de notation intégré** au carnet de notes Moodle
- **Historique complet** des modifications

## 📊 Architecture

```
┌─────────────────────────────────────────┐
│         ENSEIGNANT (Configuration)       │
├─────────────────────────────────────────┤
│ 1. Fiche Descriptive          [verrouillable] │
│ 2. Expression du Besoin       [verrouillable] │
│ 3. Planification              [verrouillable] │
└─────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────┐
│      GROUPES D'ÉLÈVES (Collaboration)   │
├─────────────────────────────────────────┤
│ 4. Cahier des Charges Fonctionnel       │
│ 5. Fiche Essai - Validation             │
│ 6. Rapport de Projet                    │
└─────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────┐
│    ENSEIGNANT (Correction par étape)    │
├─────────────────────────────────────────┤
│ • Navigation : Groupe 1 → 2 → 3...      │
│ • Conservation du contexte (étape fixe) │
│ • Notation et commentaires              │
└─────────────────────────────────────────┘
```

## 🚀 Fonctionnalités principales

### Pour les enseignants

#### Configuration du projet (Pages 1-3)
- **Fiche Descriptive** : Intitulé, niveau, compétences, modalités d'évaluation
- **Expression du Besoin** : Diagramme Bête à Corne interactif
- **Planification** : Timeline avec intégration des vacances scolaires (zones A/B/C)
- **Verrouillage** : Empêche les modifications une fois la configuration validée

#### Interface de correction innovante
- **Navigation par étape** : Rester sur l'étape 4 tout en passant de groupe en groupe
- **Contexte conservé** : Pas besoin de changer d'étape entre chaque groupe
- **Notation unifiée** : Note moyenne des 3 étapes élèves → carnet de notes
- **Commentaires par étape** : Feedback ciblé pour chaque livrable

### Pour les élèves

#### Travail collaboratif en groupe
- **Cahier des Charges Fonctionnel** : Diagramme des interacteurs, fonctions principales/contraintes
- **Fiche Essai** : Protocole expérimental, résultats, validation
- **Rapport de Projet** : Synthèse complète du projet

#### Sauvegarde automatique
- **Temps réel** : Sauvegarde toutes les 30 secondes
- **Indicateur visuel** : Statut affiché en haut à droite
- **Historique** : Toutes les modifications sont enregistrées
- **Sécurité** : Aucune perte de données

## 📁 Structure du projet

```
mod_gestionprojet/
├── version.php                 # Métadonnées du plugin
├── lib.php                     # Fonctions principales Moodle
├── mod_form.php               # Formulaire de configuration
├── view.php                   # Page principale
├── grading.php                # Interface de correction
│
├── db/
│   ├── install.xml            # Schéma de base de données
│   └── access.php             # Définition des capacités
│
├── lang/fr/
│   └── gestionprojet.php      # Traductions françaises
│
├── pages/
│   ├── home.php               # Page d'accueil avec navigation
│   ├── step1.php              # Fiche Descriptive
│   ├── step2.php              # Expression du Besoin
│   ├── step3.php              # Planification
│   ├── step4.php              # Cahier des Charges (élèves)
│   ├── step5.php              # Fiche Essai (élèves)
│   └── step6.php              # Rapport (élèves)
│
├── ajax/
│   └── autosave.php           # Endpoint de sauvegarde automatique
│
├── amd/src/
│   └── autosave.js            # Module JavaScript AMD
│
├── classes/
│   ├── output/                # Renderers
│   ├── privacy/               # Conformité RGPD
│   └── event/                 # Événements Moodle
│
└── backup/                    # Sauvegarde/restauration Moodle
```

## 🛢️ Schéma de base de données

### Tables principales

1. **gestionprojet** : Instances du module
2. **gestionprojet_description** : Fiches descriptives (enseignant)
3. **gestionprojet_besoin** : Expressions du besoin (enseignant)
4. **gestionprojet_planning** : Planifications (enseignant)
5. **gestionprojet_cdcf** : Cahiers des charges (groupes)
6. **gestionprojet_essai** : Fiches d'essai (groupes)
7. **gestionprojet_rapport** : Rapports finaux (groupes)
8. **gestionprojet_history** : Historique des modifications

### Champs clés
- `groupid` : Identifiant du groupe Moodle
- `locked` : Verrouillage des pages enseignant
- `grade` : Note sur 20
- `feedback` : Commentaires de l'enseignant
- `timemodified` : Timestamp de dernière modification

## 📦 Installation

### Prérequis
- Moodle 4.0+ (testé sur 4.1, 4.2, 4.3)
- PHP 7.4+
- MySQL 5.7+ ou PostgreSQL 10+

### Étapes d'installation

1. **Télécharger le plugin**
   ```bash
   cd /path/to/moodle/mod/
   git clone [url-du-depot] gestionprojet
   ```

2. **Connexion en tant qu'administrateur Moodle**
   - Aller sur votre site Moodle
   - Se connecter comme administrateur

3. **Installation automatique**
   - Moodle détecte automatiquement le nouveau plugin
   - Suivre l'assistant d'installation
   - Valider la création des tables

4. **Vérification**
   - Aller dans : Administration → Plugins → Activités → Gestion de Projet
   - Vérifier que la version s'affiche correctement

### Installation manuelle (alternative)

1. **Télécharger le ZIP**
2. **Décompresser dans `/path/to/moodle/mod/gestionprojet`**
3. **Définir les permissions**
   ```bash
   chmod -R 755 gestionprojet
   chown -R www-data:www-data gestionprojet
   ```
4. **Visiter** : `https://votre-moodle/admin/index.php`

## 🎓 Utilisation

### Configuration d'une activité

1. **Créer l'activité**
   - Activer le mode édition dans un cours
   - Ajouter une activité → Gestion de Projet
   - Nommer l'activité (ex: "Projet Robot Suiveur de Ligne")

2. **Configurer les paramètres**
   - Intervalle de sauvegarde : 30 secondes (recommandé)
   - Mode de groupe : Groupes séparés (recommandé)
   - Note maximale : 20

3. **Créer les groupes**
   - Aller dans Participants → Groupes
   - Créer les groupes d'élèves
   - Assigner les élèves à leurs groupes

### Workflow enseignant

#### Phase 1 : Configuration (pages 1-3)

1. **Fiche Descriptive**
   - Remplir tous les champs obligatoires
   - Sélectionner les compétences travaillées
   - Optionnel : Ajouter une image
   - Verrouiller la page (🔒)

2. **Expression du Besoin**
   - Répondre aux 3 questions de la Bête à Corne
   - Visualiser le diagramme généré
   - Verrouiller la page

3. **Planification**
   - Définir les dates de début/fin
   - Sélectionner la zone de vacances scolaires
   - Ajuster les durées des 5 phases
   - Visualiser la timeline
   - Verrouiller la page

#### Phase 2 : Correction (étapes 4-6)

1. **Sélectionner une étape à corriger**
   - Cliquer sur "Corriger" sous l'étape 4, 5 ou 6

2. **Navigation intelligente**
   - L'étape reste fixe
   - Utiliser ← Groupe précédent / Groupe suivant →
   - Voir le compteur : Groupe 2/5

3. **Évaluation**
   - Lire le travail du groupe
   - Attribuer une note /20
   - Rédiger des commentaires
   - Sauvegarder → passe automatiquement au groupe suivant

4. **Résultat**
   - Note moyenne des 3 étapes → carnet de notes
   - Tous les membres du groupe reçoivent la même note

### Workflow élève

1. **Accéder à l'activité**
   - Cliquer sur l'activité dans le cours
   - Voir les 3 étapes enseignant (lecture seule)
   - Voir le nom de son groupe

2. **Compléter les étapes**
   - **Étape 4 (CDCF)** :
     - Définir le produit et le milieu
     - Ajouter les interacteurs
     - Remplir les fonctions principales et contraintes
     - Générer le diagramme

   - **Étape 5 (Essai)** :
     - Décrire l'objectif
     - Détailler le protocole
     - Consigner les résultats
     - Rédiger la conclusion

   - **Étape 6 (Rapport)** :
     - Synthétiser besoins et solutions
     - Décrire la réalisation
     - Présenter la validation

3. **Sauvegarde automatique**
   - Indicateur visuel en haut à droite
   - Sauvegarde toutes les 30 secondes
   - Possibilité de fermer et reprendre plus tard

4. **Consultation des notes**
   - Voir les notes et commentaires dans chaque étape
   - Consulter la note finale dans le carnet de notes

## ⚙️ Configuration avancée

### Personnalisation de l'intervalle de sauvegarde

Dans les paramètres de l'activité :
- 10 secondes : Pour connexions très stables
- 30 secondes : **Recommandé** (bon compromis)
- 60 secondes : Pour réduire la charge serveur
- 120 secondes : Pour connexions lentes

### Mode de groupe

**Recommandation** : Groupes séparés
- Chaque groupe ne voit que son travail
- Isolation complète entre groupes
- Évite la copie

**Alternative** : Groupes visibles
- Les élèves voient le travail des autres groupes
- Utile pour favoriser l'émulation

### Permissions personnalisées

Modifier dans Administration → Utilisateurs → Permissions → Définir les rôles :

```php
// Autoriser les étudiants à voir les autres soumissions
mod/gestionprojet:viewallsubmissions

// Créer un rôle "Assistant enseignant"
mod/gestionprojet:grade (sans configureteacherpages)
```

## 🔐 Sécurité et confidentialité

### Conformité RGPD

Le plugin est conforme au RGPD :
- Déclaration des données personnelles dans `classes/privacy/`
- Export des données utilisateur
- Suppression des données à la demande
- Historique d'audit transparent

### Données stockées

**Données personnelles** :
- Identifiant utilisateur (userid)
- Groupe d'appartenance (groupid)
- Notes et commentaires
- Historique des modifications

**Données de projet** :
- Contenu des 6 étapes
- Fichiers uploadés (images)
- Dates de modification

### Sécurité

- **Authentification** : Intégration Moodle native
- **Autorisation** : Vérification des capacités à chaque action
- **Injection SQL** : Protection via API Moodle ($DB)
- **XSS** : Échappement automatique des sorties
- **CSRF** : Tokens sesskey sur tous les formulaires

## 🧪 Tests

### Tests manuels

1. **En tant qu'enseignant** :
   - Créer une activité
   - Remplir les 3 pages enseignant
   - Verrouiller les pages
   - Corriger les soumissions de plusieurs groupes

2. **En tant qu'élève** :
   - Rejoindre un groupe
   - Compléter les 3 étapes élèves
   - Vérifier la sauvegarde automatique
   - Consulter les notes reçues

3. **Tests de sauvegarde** :
   - Modifier un champ et attendre 30 secondes
   - Rafraîchir la page
   - Vérifier que les données sont conservées

### Tests automatisés (à venir)

```bash
# PHPUnit
php admin/tool/phpunit/cli/init.php
php vendor/bin/phpunit mod/gestionprojet/tests/

# Behat
php admin/tool/behat/cli/init.php
php vendor/bin/behat --tags=@mod_gestionprojet
```

## 🐛 Dépannage

### La sauvegarde automatique ne fonctionne pas

**Causes possibles** :
1. JavaScript désactivé
2. AJAX bloqué par le pare-feu
3. Session expirée

**Solution** :
- Vérifier la console JavaScript (F12)
- Vérifier les logs Moodle
- Augmenter la durée de session PHP

### Les groupes ne s'affichent pas

**Causes** :
1. Pas de groupes créés dans le cours
2. Mode de groupe non configuré
3. Élève non assigné à un groupe

**Solution** :
- Aller dans Participants → Groupes
- Créer les groupes
- Assigner les élèves
- Vérifier le mode de groupe dans les paramètres

### Erreur "nopermission"

**Causes** :
- Rôle insuffisant
- Capacités manquantes

**Solution** :
- Vérifier le rôle de l'utilisateur
- Vérifier les capacités dans Administration → Permissions

## 📝 TODO / Feuille de route

### Version 1.1 (Court terme)
- [ ] Migration complète des 6 pages HTML
- [ ] Génération PDF côté serveur (TCPDF)
- [ ] Export global multi-PDF
- [ ] Intégration API vacances scolaires
- [ ] Canvas HTML5 pour les diagrammes

### Version 1.2 (Moyen terme)
- [ ] Mode hors ligne (PWA)
- [ ] Collaboration temps réel (WebSocket)
- [ ] Templates de projets prédéfinis
- [ ] Notifications push
- [ ] Application mobile (Moodle App)

### Version 2.0 (Long terme)
- [ ] Intelligence artificielle (suggestions)
- [ ] Analyse de données (analytics)
- [ ] Intégration avec d'autres LMS
- [ ] API REST publique
- [ ] Marketplace de templates

## 🤝 Contribution

Les contributions sont les bienvenues !

### Comment contribuer

1. Fork le projet
2. Créer une branche (`git checkout -b feature/AmazingFeature`)
3. Commit les changements (`git commit -m 'Add AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

### Standards de code

- **PHP** : PSR-12
- **JavaScript** : ESLint (Airbnb)
- **CSS** : BEM methodology
- **Documentation** : PHPDoc et JSDoc

## 📜 Licence

Ce projet est sous licence **MIT**.

```
MIT License

Copyright (c) 2026 Emmanuel REMY

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

## 👨‍💻 Auteur

**Emmanuel REMY**
- Email : contact@technologie.forge.apps.education.fr
- Site : https://technologie.forge.apps.education.fr/

## 🙏 Remerciements

- L'équipe Moodle pour leur excellent framework
- La communauté française Moodle
- Claude Code pour l'assistance au développement

## 📞 Support

- **Issues** : https://github.com/[votre-repo]/issues
- **Forum Moodle** : https://moodle.org/mod/forum/
- **Documentation** : Ce README.md

## 📅 Historique des versions

### Version 1.1.0 (2026-01-21)
- **Nouveauté** : Accès en lecture seule pour les élèves aux étapes 1, 2 et 3 (Fiche Descriptive, Expression du Besoin, Planification)
- **Amélioration** : Interface de consultation adaptée pour les élèves
- **Correctif** : Désactivation de la sauvegarde automatique en mode lecture seule

### Version 1.0.5 (2026-01-20)
- **Correctif** : Problèmes de chargement RequireJS et Autosave

---

Développé avec ❤️ pour la communauté éducative française
