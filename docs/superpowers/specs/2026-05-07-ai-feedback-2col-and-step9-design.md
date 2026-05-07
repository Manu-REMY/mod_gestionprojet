# Refonte 2 colonnes du retour IA élève + extension à step 9

**Date** : 2026-05-07
**Cible version** : 2.10.1
**Statut** : design validé, en attente de plan d'implémentation

## Contexte

Le composant `pages/student_ai_feedback_display.php` affiche aujourd'hui le retour de l'évaluation IA aux élèves sur les steps 4, 5, 6, 7 et 8. Le moteur IA (`classes/ai_prompt_builder.php` — `STEP_CRITERIA`) produit déjà des critères pour les six steps 4-9, et l'évaluation est déclenchée automatiquement à la soumission via `classes/external/submit_step.php`. Deux problèmes côté élève :

1. **Présentation** : le rendu actuel est trop volumineux (gros bloc note 3rem, sections empilées en pleine largeur), peu lisible sur écran large.
2. **Couverture** : step 9 (FAST) ne charge pas le composant — l'élève ne voit aucun retour IA, alors que l'évaluation a bien été produite et appliquée par l'enseignant.

## Objectif

- Refondre la mise en page du composant en grille deux colonnes (1/3 + 2/3).
- Étendre l'affichage du retour IA à step 9.
- Harmoniser la position du bloc d'évaluation : juste sous le bandeau « Vous travaillez en groupe » sur tous les steps qui le possèdent, et en haut de la zone de contenu sur ceux qui ne l'ont pas.
- Ajouter le bandeau `group-info` (conditionnel à `group_submission`) sur les steps 7 et 9 qui ne l'ont pas.

## Hors-scope

- Pas de modification du moteur IA (prompts, parser, evaluator).
- Pas de modification du schéma DB.
- Pas de nouvelles chaînes de langue (toutes celles utilisées existent déjà).
- Pas de changement de l'affichage côté enseignant (`grading.php`).

## Architecture & périmètre

### Fichiers modifiés

| Fichier | Nature de la modif |
|---------|---------------------|
| `pages/student_ai_feedback_display.php` | Refonte du markup à partir de la ligne 141 (HTML), logique PHP de récupération inchangée |
| `styles.css` | Ajout des styles `.feedback-body`, `.feedback-col-left`, `.feedback-col-right`, `.feedback-keywords-row`, `.feedback-grade-card` + media query mobile |
| `pages/step4.php` | Déplacement de l'include `student_ai_feedback_display.php` juste après `group-info` (avant la description) |
| `pages/step6.php` | Déplacement de l'include juste après `group-info` (avant la `info-box`) |
| `pages/step7.php` | Ajout du bandeau `group-info` conditionnel + déplacement de l'include juste après le bandeau |
| `pages/step9.php` | Ajout du bandeau `group-info` conditionnel + ajout de l'include, avant le wrapper `gp-student` qui contient le canvas FAST |
| `version.php` | Bump 2.10.0 → 2.10.1 (pas de DB upgrade) |

### Fichiers non touchés

`classes/ai_*` (prompts, parser, evaluator), `db/install.xml`, `db/upgrade.php`, `lang/{en,fr}/gestionprojet.php`.

## Structure HTML cible

```html
<div class="student-feedback">
    <div class="feedback-header">
        <div class="feedback-title"><span class="icon">📊</span><span>Évaluation</span></div>
        <div class="grade-badge {gradeColorClass}"><span>⭐</span><span>9.0 / 20</span></div>
    </div>

    <div class="feedback-body">
        <div class="feedback-col-left">
            <div class="feedback-grade-card">
                <div class="grade-value">9.0/20</div>
                <div class="grade-label">Note suggérée par l'IA</div>
            </div>

            <!-- Si show_feedback && feedback non vide -->
            <div class="feedback-section">
                <div class="section-title"><span class="section-icon">💬</span><span>Commentaires de l'IA</span></div>
                <div class="feedback-text">{feedback}</div>
            </div>

            <!-- Si show_suggestions && suggestions non vides -->
            <div class="feedback-section">
                <div class="section-title"><span class="section-icon">💡</span><span>Suggestions</span></div>
                <div class="suggestions-list"><ul><li>...</li></ul></div>
            </div>
        </div>

        <div class="feedback-col-right">
            <!-- Si show_criteria && criteria non vides -->
            <div class="feedback-section">
                <div class="section-title"><span class="section-icon">📋</span><span>Critères d'évaluation</span></div>
                <div class="criteria-grid">
                    <div class="criteria-item">
                        <div class="criteria-info">
                            <div class="criteria-name">{name}</div>
                            <div class="criteria-comment">{comment}</div>
                        </div>
                        <div class="criteria-score {score-high|medium|low}">{score} / {max}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Si keywords_found ou keywords_missing -->
    <div class="feedback-keywords-row">
        <div class="keywords-container">
            <div class="keywords-box found">...</div>
            <div class="keywords-box missing">...</div>
        </div>
    </div>
</div>
```

**Conservation** : `feedback-header`, `grade-badge`, `feedback-section`, `section-title`, `feedback-text`, `criteria-grid`, `criteria-item`, `criteria-info`, `criteria-name`, `criteria-comment`, `criteria-score` (et variantes), `keywords-container`, `keywords-box`, `keyword-tag`, `suggestions-list` — toutes les classes existantes sont réutilisées sans modification.

**Nouveaux wrappers** : `.feedback-body`, `.feedback-col-left`, `.feedback-col-right`, `.feedback-keywords-row`, `.feedback-grade-card` (+ enfants `.grade-value`, `.grade-label`).

**Comportement grille rigide** : si une section est désactivée (`show_*=false`), la grille 1/3 + 2/3 reste en place ; la colonne concernée peut se vider partiellement (acceptable, validé par l'utilisateur).

## CSS

À ajouter dans `styles.css`, namespacé `.path-mod-gestionprojet`.

```css
.path-mod-gestionprojet .student-feedback .feedback-body {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 1.5rem;
    margin-top: 1rem;
}

@media (max-width: 768px) {
    .path-mod-gestionprojet .student-feedback .feedback-body {
        grid-template-columns: 1fr;
    }
}

.path-mod-gestionprojet .student-feedback .feedback-col-left,
.path-mod-gestionprojet .student-feedback .feedback-col-right {
    min-width: 0;
}

.path-mod-gestionprojet .student-feedback .feedback-grade-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    margin-bottom: 1rem;
}
.path-mod-gestionprojet .student-feedback .feedback-grade-card .grade-value {
    font-size: 2.25rem;
    font-weight: 700;
    color: #4f46e5;
    line-height: 1.1;
}
.path-mod-gestionprojet .student-feedback .feedback-grade-card .grade-label {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.path-mod-gestionprojet .student-feedback .feedback-keywords-row {
    margin-top: 1.5rem;
}

.path-mod-gestionprojet .student-feedback .feedback-col-left .feedback-section,
.path-mod-gestionprojet .student-feedback .feedback-col-right .feedback-section {
    margin-bottom: 1rem;
}

.path-mod-gestionprojet .student-feedback .feedback-col-right .criteria-grid {
    gap: 0.5rem;
}
```

## Repositionnement du bloc d'évaluation

| Step | Bandeau `group-info` actuel | Action sur le bandeau | Action sur l'include IA |
|------|------------------------------|------------------------|-------------------------|
| 4 (CDCF) | Présent (inconditionnel) | Pas touché | Déplacé : actuellement après la description (ligne 170), à placer juste après `group-info` (avant la description) |
| 5 (Essai) | Présent | Pas touché | Déjà juste après `group-info` (ligne 164) — pas de modification |
| 6 (Rapport) | Présent | Pas touché | Déplacé : actuellement après la `info-box` (ligne 151), à placer juste après `group-info` (avant la `info-box`) |
| 7 (Besoin) | Absent | Ajout conditionnel à `group_submission` | Ajout juste après le bandeau, avant la `description` (alert) |
| 8 (Carnet) | Présent | Pas touché | Déjà juste après `group-info` (ligne 143) — pas de modification |
| 9 (FAST) | Absent | Ajout conditionnel à `group_submission` | Ajout juste après le bandeau, avant le wrapper `gp-student` qui contient le canvas FAST |

### Patch identique pour steps 7 et 9

```php
<?php if ($gestionprojet->group_submission && !empty($group)): ?>
<div class="group-info">
    👥 <strong><?php echo get_string('your_group', 'gestionprojet'); ?>:</strong>
    <?php echo format_string($group->name); ?>
</div>
<?php endif; ?>

<!-- AI Evaluation Feedback Display -->
<?php require_once(__DIR__ . '/student_ai_feedback_display.php'); ?>
```

S'assurer que la variable `$group` est disponible avant l'insertion sur step 7 et step 9 (résolution via `groups_get_group($groupid)` si nécessaire — pattern utilisé dans step 4).

## Cas limites

| Cas | Comportement |
|-----|--------------|
| `$submission->grade === null` (pas encore noté) | Composant `return` immédiat, rien ne s'affiche |
| `show_feedback=false` | Section commentaires masquée dans la colonne gauche |
| `show_criteria=false` | Section critères masquée → colonne droite vide (grille rigide conservée) |
| `show_keywords_found=false` et `show_keywords_missing=false` | Ligne pleine largeur masquée |
| `show_suggestions=false` | Section suggestions masquée dans la colonne gauche |
| Note manuelle sans `gestionprojet_ai_evaluations` | En-tête + note + feedback texte ; pas de critères ni mots-clés ni suggestions |
| `group_submission=0` (mode individuel) | Sur 7 et 9 : pas de bandeau groupe, bloc IA tout en haut. Sur 4/5/6/8 : bandeau toujours affiché (comportement existant non touché) |
| Step 9 verrouillé après soumission | Bloc IA s'affiche normalement au-dessus du canvas FAST verrouillé |

## Tests manuels

Tests à exécuter sur preprod (`preprod.ent-occitanie.com/mod/gestionprojet`) :

1. Pour chaque step 4-9 : soumettre une production, attendre l'évaluation IA, l'appliquer côté enseignant, retourner côté élève.
2. Vérifier la grille 2 colonnes : carte note + commentaire + suggestions à gauche ; critères à droite ; mots-clés en pleine largeur en bas.
3. Réduire la fenêtre < 768px → vérifier l'empilage (gauche au-dessus, droite en-dessous, mots-clés tout en bas).
4. Vérifier le placement du bloc IA : juste sous le bandeau groupe sur 4/5/6/8, en haut de la zone de contenu sur 7/9 quand `group_submission=0`.
5. Tester chaque flag `show_*` désactivé : la grille reste rigide même quand une colonne se vide.
6. Step 9 : vérifier le bandeau `group-info` (apparaît si `group_submission=1`, masqué sinon).
7. Step 9 : vérifier que le canvas FAST s'affiche bien sous le bloc IA et reste verrouillé après soumission.
8. Vérifier l'absence de régression sur `grading.php` (pas touché).
9. Vérifier qu'aucun retour ne s'affiche tant que `$submission->grade === null`.

Pas de tests unitaires nouveaux (changement 100% présentation).

## Déploiement

1. Bump `version.php` (2.10.0 → 2.10.1, pas de bump `requires`, pas d'upgrade DB).
2. Purge caches Moodle après installation.
3. Déploiement preprod en SCP, puis prod après validation utilisateur.
