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

## État Actuel (v1.2.0)

### Fonctionnel
- [x] 8 phases implémentées avec UI complète
- [x] Configuration enseignant (Steps 1-3)
- [x] Soumissions élèves (Steps 4-8)
- [x] Système d'autosave AJAX
- [x] Mode groupe/individuel
- [x] Interface de notation manuelle (0-20)
- [x] Intégration carnet de notes Moodle (moyenne des phases)
- [x] Historique des modifications
- [x] Support français/anglais
- [x] Modèles de correction enseignant pour Steps 4-8
- [x] **Configuration flexible pilotage/élève par phase (Phase 1 ✓)**
- [x] **Stockage sécurisé clé API par activité (Phase 2 ✓)**

### Manquant
- [ ] Système d'évaluation IA automatique
- [ ] Évaluation par compétences
- [ ] Notes individuelles par phase dans Moodle
- [ ] Export PDF complet

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

### PHASE 3 : Modèles de Correction Enseignant
**Objectif** : Interface pour que l'enseignant définisse les corrections attendues

**Note :** Les tables `_teacher` existent déjà pour Steps 4-8. Il faut :

**Tâches :**
1. **Compléter les interfaces enseignant**
   - Réutiliser les formulaires élèves en mode "modèle"
   - Ajouter champs spécifiques : points clés, critères d'évaluation, pondération
   - Permettre plusieurs réponses acceptables

2. **Structure des modèles**
   ```
   Pour chaque champ du formulaire élève :
   ├─ Réponse modèle (texte attendu)
   ├─ Mots-clés obligatoires (JSON array)
   ├─ Mots-clés bonus (JSON array)
   ├─ Pondération (pourcentage du total)
   └─ Instructions IA (guide pour l'évaluation)
   ```

3. **Interface de saisie**
   - Édition inline ou formulaire dédié
   - Prévisualisation du modèle
   - Indication de complétion

4. **Validation**
   - Vérifier que le modèle est complet avant activation élève
   - Alertes si modèle incomplet

**Fichiers impactés :** `pages/step*_teacher.php` (modification), `classes/teacher_model.php` (nouveau), tables `gestionprojet_*_teacher`

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

### PHASE 6 : Raffinements et Qualité
**Objectif** : Robustesse, UX, documentation

**Tâches :**
1. **Tests et validation**
   - Tests unitaires (PHPUnit)
   - Tests d'intégration
   - Tests de charge API

2. **Interface utilisateur**
   - Indicateurs de progression IA
   - Notifications temps réel (WebSocket ou polling)
   - Responsive design

3. **Documentation**
   - Guide enseignant
   - Guide élève
   - Documentation technique

4. **Export PDF**
   - Export du projet complet
   - Export des évaluations
   - Certificat de complétion

5. **Optimisations**
   - Cache des évaluations
   - Batch processing pour les soumissions multiples
   - Monitoring des coûts API

**Fichiers impactés :** `tests/`, `docs/`, `export_pdf.php`, `classes/cache.php`

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
| Phase 3 | Moyenne | Modèles de correction complets | ⏳ À venir |
| Phase 4 | Élevée | Première évaluation IA fonctionnelle | ⏳ À venir |
| Phase 5 | Moyenne | Notes par phase dans carnet Moodle | ⏳ À venir |
| Phase 6 | Variable | Version production | ⏳ À venir |

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

*Document créé le 24/01/2026 - À maintenir à jour au fil du développement*
