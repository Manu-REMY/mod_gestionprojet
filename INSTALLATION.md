# 📦 Guide d'Installation - Plugin Moodle Gestion de Projet

## Prérequis

- Moodle 4.0 ou supérieur
- PHP 7.4 ou supérieur
- MySQL 5.7+ ou PostgreSQL 10+
- Accès administrateur Moodle

## Installation

### Étape 1 : Copier les fichiers

```bash
cd /path/to/your/moodle/mod/
cp -r /path/to/mod_gestionprojet ./gestionprojet
```

### Étape 2 : Définir les permissions

```bash
chmod -R 755 gestionprojet
chown -R www-data:www-data gestionprojet  # Adapter selon votre serveur
```

### Étape 3 : Installation via Moodle

1. Connectez-vous à Moodle en tant qu'administrateur
2. Moodle détecte automatiquement le nouveau plugin
3. Suivez l'assistant d'installation
4. Les 8 tables seront créées automatiquement

### Étape 4 : Vérification

Administration du site → Plugins → Modules d'activité → Gestion de Projet

Vous devriez voir :
- Version : 1.0.0
- Statut : Installé

## Configuration d'un cours

### 1. Créer les groupes

1. Aller dans le cours
2. Participants → Groupes
3. Créer autant de groupes que nécessaire
4. Assigner les élèves à leurs groupes

### 2. Ajouter l'activité

1. Activer le mode édition
2. Ajouter une activité → Gestion de Projet
3. Configurer :
   - Nom : ex "Projet Robot Suiveur"
   - Intervalle de sauvegarde : 30 secondes
   - Mode de groupe : Groupes séparés
   - Note maximale : 20
4. Enregistrer

### 3. Configurer les pages enseignant

1. Cliquer sur l'activité
2. Remplir la Fiche Descriptive
3. Verrouiller (🔒)
4. Remplir l'Expression du Besoin
5. Verrouiller (🔒)
6. Remplir la Planification
7. Verrouiller (🔒)

**Les élèves peuvent maintenant commencer !**

## Désinstallation

1. Supprimer toutes les instances du plugin dans les cours
2. Administration → Plugins → Modules d'activité → Gestion de Projet
3. Cliquer sur "Désinstaller"
4. Confirmer
5. Les 8 tables seront supprimées automatiquement

## Support

En cas de problème :
1. Consulter le README.md
2. Vérifier les logs Moodle
3. Ouvrir une issue sur GitHub

