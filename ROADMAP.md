# Plan d'Action Global - Plugin Gestion de Projet Moodle

## Vision du Projet

L'enseignant configure le cadre pédagogique et fournit des modèles de correction. Les élèves complètent les phases activées. Un système IA évalue automatiquement les productions élèves en les comparant aux modèles enseignant. Les résultats s'intègrent au carnet de notes Moodle.

---

## Architecture Cible

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        ENSEIGNANT                                        │
├─────────────────────────────────────────────────────────────────────────┤
│  1. Configuration de l'activité                                          │
│     ├─ Choix des phases pilotées (enseignant remplit)                   │
│     ├─ Choix des phases élèves (élèves remplissent)                     │
│     ├─ Mode groupe/individuel                                            │
│     └─ Clé API pour l'évaluation IA                                     │
│                                                                          │
│  2. Remplissage du cadre projet (phases pilotées)                       │
│     ├─ Step 1: Fiche Descriptive                                        │
│     ├─ Step 2: Expression du Besoin                                     │
│     └─ Step 3: Planification                                            │
│                                                                          │
│  3. Modèles de correction (pour phases élèves activées)                 │
│     ├─ Step 2: Expression du Besoin (si élève)                          │
│     ├─ Step 4: Cahier des Charges Fonctionnel                           │
│     ├─ Step 5: Fiche Essai                                              │
│     ├─ Step 6: Rapport de Projet                                        │
│     └─ Step 8: Carnet de Bord                                           │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          ÉLÈVES                                          │
├─────────────────────────────────────────────────────────────────────────┤
│  1. Consultation des phases pilotées (lecture seule)                    │
│                                                                          │
│  2. Complétion des phases activées                                      │
│     ├─ Travail individuel ou en groupe                                  │
│     ├─ Sauvegarde automatique                                           │
│     └─ Soumission de chaque phase                                       │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    SYSTÈME D'ÉVALUATION IA                              │
├─────────────────────────────────────────────────────────────────────────┤
│  1. Réception de la soumission élève                                    │
│  2. Récupération du modèle de correction enseignant                     │
│  3. Appel API IA (clé fournie par l'enseignant)                        │
│  4. Comparaison production élève vs modèle                              │
│  5. Génération de l'évaluation (note + feedback)                        │
│  6. Enregistrement dans le carnet de notes Moodle                       │
│     ├─ Mode par compétences (si configuré)                              │
│     └─ Mode par notes (si configuré)                                    │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## État Actuel (v1.7.0)

### Fonctionnel
- [x] 8 phases implémentées avec UI complète
- [x] Configuration enseignant (Steps 1-3)
- [x] Soumissions élèves (Steps 4-8)
- [x] Système d'autosave AJAX (student + teacher pages)
- [x] Mode groupe/individuel
- [x] Interface de notation manuelle (0-20)
- [x] Intégration carnet de notes Moodle (moyenne des phases)
- [x] Historique des modifications
- [x] Support français/anglais
- [x] **Configuration flexible pilotage/élève par phase (Phase 1 ✓)**
- [x] **Stockage sécurisé clé API par activité (Phase 2 ✓)**
- [x] **Modèles de correction enseignant Steps 4-8 avec autosave (Phase 3 ✓)**
- [x] **Hub des modèles de correction**
- [x] **Instructions IA par step**
- [x] **Timeline Step 3 avec vacances scolaires API (Phase 3.5 ✓)**
- [x] **Dates de soumission configurables par step (Phase 3.5 ✓)**
- [x] **Affichage des dates aux élèves (Phase 3.5 ✓)**
- [x] **Moteur d'évaluation IA (Phase 4 ✓)**
- [x] **Intégration gradebook per-step (Phase 5 ✓)**
- [x] **Provider Albert (Etalab) avec clé API intégrée**

### Manquant (Phase 6)
- [ ] Indicateurs de progression IA
- [ ] Notifications temps réel
- [ ] Responsive design amélioré
- [ ] Documentation utilisateur
- [ ] Export PDF complet
- [ ] Optimisations (cache, batch processing)

---

## Plan d'Action en Phases

### PHASE 1 : Flexibilité de Configuration des Phases ✅ TERMINÉE
**Objectif** : Permettre à l'enseignant de définir qui remplit chaque phase

**Statut** : Implémentée et opérationnelle (v1.1.3)

---

### PHASE 2 : Configuration et Stockage de la Clé API ✅ TERMINÉE
**Objectif** : Permettre la configuration sécurisée de l'API IA

**Statut** : Implémentée (v1.2.0)

**Réalisations :**
1. **Champs base de données**
   - [x] `ai_api_key` (TEXT, chiffré via Moodle encryption API)
   - [x] `ai_provider` (CHAR: openai, anthropic, mistral)
   - [x] `ai_enabled` (INT: 0/1)

2. **Interface de configuration**
   - [x] Section "Évaluation par IA" dans `mod_form.php`
   - [x] Champ password avec toggle visibilité
   - [x] Sélecteur de fournisseur IA
   - [x] Bouton de test de connexion

3. **Sécurisation**
   - [x] Classe `ai_config.php` avec chiffrement/déchiffrement
   - [x] Logs d'accès à la clé API (audit trail)
   - [x] Vérification des permissions (configureteacherpages)

4. **Validation**
   - [x] Endpoint `ajax/test_api.php`
   - [x] Test de connexion aux 3 providers (OpenAI, Anthropic, Mistral)

**Fichiers créés/modifiés :** `db/install.xml`, `db/upgrade.php`, `mod_form.php`, `lib.php`, `classes/ai_config.php`, `ajax/test_api.php`, `lang/*.php`

---

### PHASE 3 : Modèles de Correction Enseignant ✅ TERMINÉE
**Objectif** : Interface pour que l'enseignant définisse les corrections attendues

**Statut** : Implémentée (v1.3.1)

**Réalisations :**

1. **Navigation**
   - [x] Bouton "Modèles de correction" sur la page d'accueil enseignant
   - [x] Hub listant les modèles pour Steps 4, 5, 6, 7, 8
   - [x] Indicateurs de complétion par step

2. **Pages modèles de correction (Steps 4-8)**
   - [x] 5 pages teacher (step4_teacher.php à step8_teacher.php)
   - [x] Formulaires identiques aux versions élèves
   - [x] Champ "Instructions de correction IA" en bas de chaque page
   - [x] Autosave activé sur toutes les pages teacher (mode='teacher')

3. **Structure des données**
   - [x] Tables `gestionprojet_*_teacher` créées (5 tables)
   - [x] Champ `ai_instructions` (TEXT) dans chaque table
   - [x] Champs identiques aux tables élèves

4. **Améliorations supplémentaires**
   - [x] Navigation step2 corrigée (retour accueil au lieu de step élève)
   - [x] Steps 7 et 8 activés par défaut
   - [x] Styles partagés (teacher_model_styles.php)

**Fichiers créés/modifiés :**
- `pages/correction_models.php` (hub)
- `pages/step4_teacher.php` à `pages/step8_teacher.php`
- `pages/teacher_model_styles.php`
- `amd/src/autosave.js` (support mode teacher)
- `db/install.xml`, `db/upgrade.php`
- `lang/*.php`

---

### PHASE 3.5 : Améliorations Planification & Système de Soumission ✅ TERMINÉE
**Objectif** : Enrichir le Step 3 (planification) et ajouter un système de soumission par step

**Statut** : Implémentée (v1.4.0)

**Réalisations :**

1. **Amélioration du Step 3 (Planification)**
   - [x] Mise à jour automatique des durées des étapes du projet
   - [x] Prise en compte des vacances scolaires via API gouvernementale (zones A/B/C)
   - [x] Synchronisation avec les fonctionnalités de `gestion-projet.html`
   - [x] Timeline interactive avec jalons et marqueurs de vacances
   - [x] Calcul des semaines travaillées hors vacances
   - [x] Auto-répartition des heures selon durée projet

2. **Dates de soumission dans les steps élèves**
   - [x] Affichage de la date de soumission normale (provenant des jalons du Step 3)
   - [x] Indicateur visuel de la deadline pour chaque step
   - [x] Alerte si proche de la date limite (badge "Bientôt dû")
   - [x] Alerte si en retard (badge "En retard")

3. **Configuration des dates dans les modèles de correction**
   - [x] Champ date "soumission normale" (auto-rempli depuis Step 3)
   - [x] Champ date "soumission limite" (configurable par l'enseignant)
   - [x] Positionnement : section dédiée au-dessus du champ "Instructions IA"

4. **Système de soumission existant amélioré**
   - [x] Champ `enable_submission` ajouté à la table principale
   - [x] Champs `status` et `timesubmitted` ajoutés aux tables élèves manquantes
   - [x] Verrouillage de l'édition après soumission
   - [x] Possibilité de déverrouiller (enseignant uniquement)

**Tables modifiées :**
- `gestionprojet` : Ajout `enable_submission`
- `gestionprojet_*_teacher` : Ajout `submission_date`, `deadline_date`
- `gestionprojet_essai`, `gestionprojet_rapport` : Ajout `status`, `timesubmitted`

**Fichiers créés/modifiés :**
- `pages/step3.php` (timeline améliorée avec API vacances)
- `pages/student_dates_display.php` (nouveau - composant dates élèves)
- `pages/teacher_dates_section.php` (nouveau - composant dates enseignant)
- `pages/step4.php` à `pages/step8.php` (affichage dates)
- `pages/step*_teacher.php` (champs dates)
- `ajax/submit_step.php` (nouveau - endpoint soumission)
- `ajax/autosave.php` (support champs dates)
- `db/install.xml`, `db/upgrade.php`
- `lang/fr/*.php`, `lang/en/*.php`

---

### PHASE 4 : Moteur d'Évaluation IA
**Objectif** : Évaluation automatique des productions élèves

**Tâches :**
1. **Créer la classe d'évaluation IA**
   ```php
   classes/ai_evaluator.php
   ├─ evaluate_submission($step, $student_data, $teacher_model)
   ├─ build_prompt($step, $student_data, $teacher_model)
   ├─ parse_response($ai_response)
   └─ calculate_grade($evaluation_result)
   ```

2. **Prompts d'évaluation par phase**
   - Template de prompt par type de phase
   - Instructions d'évaluation standardisées
   - Format de réponse structuré (JSON)

3. **Intégration au workflow de soumission**
   - Hook après `ajax/submit.php`
   - Évaluation asynchrone (tâche planifiée) ou synchrone
   - Gestion des erreurs API (retry, fallback)

4. **Stockage des résultats**
   - Table `gestionprojet_ai_evaluations`
   - Champs : submission_id, step, raw_response, parsed_grade, parsed_feedback, tokens_used, timestamp

5. **Interface de révision**
   - L'enseignant peut voir/modifier l'évaluation IA
   - Historique des évaluations

**Fichiers impactés :** `classes/ai_evaluator.php` (nouveau), `classes/ai_providers/*.php` (nouveau), `ajax/submit.php`, `db/install.xml`

---

### PHASE 5 : Intégration Carnet de Notes Moodle
**Objectif** : Notes individuelles par phase + évaluation par compétences

**Tâches :**
1. **Notes par phase dans Moodle**
   - Créer un grade item par phase activée
   - Nomenclature : "[Activité] - Phase X : [Nom]"
   - Mise à jour automatique après évaluation IA

2. **Support des compétences Moodle**
   - Lier les phases aux compétences du référentiel
   - Configuration dans `mod_form.php` (mapping phase → compétences)
   - Mise à jour des compétences via API Moodle

3. **Calcul de la note finale**
   - Pondération configurable par phase
   - Formule personnalisable
   - Note finale = somme pondérée des phases

4. **Rétroaction**
   - Feedback IA visible dans le carnet de notes
   - Export du feedback détaillé

**Fichiers impactés :** `lib.php` (fonctions grade_*), `mod_form.php`, `classes/grading.php` (nouveau), intégration API compétences Moodle

---

### PHASE 6 : Raffinements et Qualité ⏳ EN COURS
**Objectif** : Robustesse, UX, documentation

**Statut** : En cours (v1.7.0 → v1.8.0)

#### Étape 6.1 : Tests et validation ✅ TERMINÉE
- [x] Tests unitaires (PHPUnit)
- [x] Tests d'intégration
- [x] Tests de charge API

#### Étape 6.2 : Interface utilisateur ⏳ EN COURS
**Objectif** : Améliorer l'expérience utilisateur avec des indicateurs visuels

**Tâches :**
1. **Indicateurs de progression IA**
   - [ ] Spinner/loader pendant l'évaluation IA
   - [ ] Barre de progression pour les évaluations longues
   - [ ] État visuel (en attente, en cours, terminé, erreur)
   - [ ] Affichage du résultat IA inline

2. **Notifications temps réel**
   - [ ] Système de notifications toast (succès, erreur, info)
   - [ ] Polling pour mise à jour du statut d'évaluation
   - [ ] Notification quand l'évaluation IA est terminée
   - [ ] Badge de notification sur les steps évalués

3. **Responsive design**
   - [ ] Adaptation mobile des formulaires steps
   - [ ] Menu navigation responsive
   - [ ] Timeline step3 responsive
   - [ ] Tableaux de grading responsive

**Fichiers impactés :**
- `amd/src/ai_progress.js` (nouveau)
- `amd/src/notifications.js` (nouveau)
- `templates/ai_progress.mustache` (nouveau)
- `styles.css` ou SCSS
- `ajax/check_evaluation_status.php` (nouveau)

#### Étape 6.3 : Documentation
- [ ] Guide enseignant (PDF/Markdown)
- [ ] Guide élève (PDF/Markdown)
- [ ] Documentation technique (README amélioré)

#### Étape 6.4 : Export PDF
- [ ] Export du projet complet
- [ ] Export des évaluations
- [ ] Certificat de complétion

#### Étape 6.5 : Optimisations
- [ ] Cache des évaluations
- [ ] Batch processing pour les soumissions multiples
- [ ] Monitoring des coûts API

---

## Priorités et Dépendances

```
PHASE 1 ──────────────────┐
(Config flexible)         │
                          ▼
PHASE 2 ──────────────────┼──── PHASE 3
(Clé API)                 │     (Modèles correction)
                          │            │
                          ▼            ▼
                    PHASE 4 ◄──────────┘
                    (Moteur IA)
                          │
                          ▼
                    PHASE 5
                    (Notes Moodle)
                          │
                          ▼
                    PHASE 6
                    (Qualité)
```

---

## Estimation et Jalons

| Phase | Complexité | Jalon | Statut |
|-------|------------|-------|--------|
| Phase 1 | Moyenne | Configuration flexible opérationnelle | ✅ Terminée |
| Phase 2 | Faible | Clé API stockée et testable | ✅ Terminée |
| Phase 3 | Moyenne | Modèles de correction complets | ✅ Terminée |
| Phase 3.5 | Moyenne | Améliorations planification & soumissions | ✅ Terminée |
| Phase 4 | Élevée | Première évaluation IA fonctionnelle | ✅ Terminée |
| Phase 5 | Moyenne | Notes par phase dans carnet Moodle | ✅ Terminée |
| Phase 6.1 | Moyenne | Tests unitaires et intégration | ✅ Terminée |
| Phase 6.2 | Moyenne | Interface utilisateur améliorée | ⏳ En cours |
| Phase 6.3 | Faible | Documentation utilisateur | ⏳ À venir |
| Phase 6.4 | Moyenne | Export PDF | ⏳ À venir |
| Phase 6.5 | Moyenne | Optimisations | ⏳ À venir |

---

## Risques et Mitigations

| Risque | Impact | Mitigation |
|--------|--------|------------|
| Coût API élevé | Moyen | Limites d'usage, cache, modèles économiques |
| Qualité évaluation IA | Élevé | Validation enseignant obligatoire, prompts affinés |
| Latence API | Moyen | Évaluation asynchrone, file d'attente |
| Sécurité clé API | Élevé | Chiffrement, audit, rotation |
| Compatibilité Moodle | Moyen | Tests sur versions multiples |

---

## Notes Techniques

### Fournisseurs IA Envisagés
- **OpenAI** (GPT-4) : Référence qualité, coût moyen
- **Anthropic** (Claude) : Bonne compréhension, sécurité
- **Mistral** : Option européenne, RGPD friendly

### Format de Réponse IA Standardisé
```json
{
  "grade": 15,
  "max_grade": 20,
  "feedback": "Texte détaillé...",
  "criteria": [
    {"name": "Pertinence", "score": 4, "max": 5, "comment": "..."},
    {"name": "Complétude", "score": 3, "max": 5, "comment": "..."}
  ],
  "keywords_found": ["mot1", "mot2"],
  "keywords_missing": ["mot3"],
  "suggestions": ["Suggestion 1", "Suggestion 2"]
}
```

---

*Document créé le 24/01/2026 - Dernière mise à jour 27/01/2026 (Phase 6.2 en cours)*
