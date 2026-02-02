# Documentation Technique - Plugin Gestion de Projet (v1.0)

Ce document décrit l'architecture technique et le fonctionnement interne du plugin `mod_gestionprojet`. Il est destiné aux développeurs souhaitant maintenir ou faire évoluer le plugin.

## 🏗 Architecture Globale

Le plugin est un module d'activité Moodle standard (`mod`). Il suit l'architecture MVC typique de Moodle.

### Structure des fichiers clés

- **`lib.php`** : Contient les fonctions d'API standard de Moodle (`_add_instance`, `_update_instance`, `_delete_instance`, `_supports`, etc.). C'est le point d'entrée pour les opérations système.
- **`view.php`** : Point d'entrée principal pour l'affichage de l'activité. Il redirige vers `home.php` ou gère l'initialisation du contexte.
- **`grading.php`** : Interface dédiée à la correction pour les enseignants. Elle gère la navigation inter-groupes/élèves tout en conservant le contexte de l'étape.
- **`pages/`** : Contient la logique spécifique à chaque page de l'application.
  - `home.php` : Tableau de bord principal.
  - `step1.php` à `step3.php` : Pages de configuration (Enseignant).
  - `step4.php` à `step6.php` : Pages de soumission (Élèves).
- **`amd/src/autosave.js`** : Module JavaScript gérant la sauvegarde automatique via AJAX.
- **`ajax/autosave.php`** : Script serveur recevant les requêtes AJAX de sauvegarde.

## 🛢 Base de Données

Le plugin utilise plusieurs tables pour stocker les données.

### Tables de configuration (Enseignant)
Ces tables stockent les consignes et paramètres définis par l'enseignant.
- `mdl_gestionprojet_description` : Étape 1 (Intitulé, niveau, etc.).
- `mdl_gestionprojet_besoin` : Étape 2 (Bête à corne).
- `mdl_gestionprojet_planning` : Étape 3 (Dates, zones).

### Tables de soumission (Élèves)
Ces tables stockent le travail des élèves.
- `mdl_gestionprojet_cdcf` : Étape 4 (Cahier des charges, Anaylse fonctionnelle).
- `mdl_gestionprojet_essai` : Étape 5 (Protocoles de test).
- `mdl_gestionprojet_rapport` : Étape 6 (Rapport final).
- **Clés importantes** :
  - `gestionprojetid` : Lien vers l'instance de l'activité.
  - `groupid` : Lien vers le groupe (ou 0 si individuel).
  - `userid` : Lien vers l'utilisateur (si soumission individuelle).

## 🔐 Logique d'Accès et Permissions

L'accès est géré via l'API de capacités Moodle (`Access API`).

### Capacités principales (`db/access.php`)
- `mod/gestionprojet:addinstance` : Créer l'activité.
- `mod/gestionprojet:view` : Voir l'activité.
- `mod/gestionprojet:submit` : Soumettre un travail (Élèves).
- `mod/gestionprojet:grade` : Corriger les travaux (Enseignants).

### Gestion des groupes vs Individuel
Le plugin supporte les deux modes de fonctionnement, configurés dans les paramètres de l'activité.
- **Mode Groupe** : Les soumissions sont liées à `groupid`. Tous les membres du groupe voient et modifient la même entrée.
- **Mode Individuel** : Les soumissions sont liées à `userid`.
- **Logique dans le code** :
  - `lib.php` contient des helpers comme `gestionprojet_get_user_group($cm, $userid)` pour résoudre le groupe d'un utilisateur.
  - `grading.php` utilise une logique adaptative pour lister soit les groupes, soit les utilisateurs individuels dans la barre de navigation.

## 🔄 Flux de Sauvegarde (Autosave)

1. **Client (JS)** : `amd/src/autosave.js` détecte les changements dans les formulaires.
2. **Timer** : Un timer (par défaut 30s) envoie les données périodiquement.
3. **AJAX** : Requête POST vers `ajax/autosave.php` avec les données sérialisées.
4. **Serveur (PHP)** : 
   - Vérification de la session et des capacités (`sesskey`, `require_capability`).
   - Mise à jour ou insertion dans la table correspondante via `$DB->update_record` ou `$DB->insert_record`.
   - Retourne un statut JSON au client.

## 📝 Notation (Grading)

La notation se fait via `grading.php`.
- L'enseignant note chaque étape (4, 5, 6) individuellement.
- La note est stockée dans la table de soumission correspondante (`mdl_gestionprojet_cdcf`, etc.).
- Lors de la sauvegarde d'une note, la fonction `gestionprojet_update_grades()` dans `lib.php` est appelée.
- Cette fonction calcule la moyenne (ou autre logique définie) et met à jour le carnet de notes Moodle via `grade_update()`.

## 🚀 Pistes pour le développement futur (v1.1+)

- **Refactoring des formulaires** : Migrer les formulaires HTML actuels vers l'API Form API de Moodle (`moodleform`) pour une meilleure validation et sécurité standardisée.
- **Export PDF** : Ajouter une fonctionnalité pour exporter le projet complet en PDF (via TCPDF inclus dans Moodle).
- **Classes persistantes** : Utiliser les classes persistantes Moodle pour l'accès aux données au lieu de requêtes `$DB` directes répétitives.
