# Refonte 2 colonnes du retour IA élève + extension step 9 — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refondre le rendu du retour IA côté élève en grille 2 colonnes (1/3 + 2/3), ajouter l'affichage sur step 9 (FAST), et harmoniser la position du bloc d'évaluation sur les steps 4, 6, 7, 9.

**Architecture:** Refonte purement présentationnelle. Le composant partagé `pages/student_ai_feedback_display.php` est réécrit en HTML pour adopter une grille CSS 2 colonnes ; les wrappers existants (`feedback-section`, `criteria-grid`, `keywords-container`, etc.) sont réutilisés tels quels. Steps 5 et 8 sont déjà conformes au pattern « include juste après `group-info` » et ne sont pas modifiés. Steps 4 et 6 voient l'include déplacé. Steps 7 et 9 reçoivent en plus un bandeau `group-info` conditionnel à `group_submission`.

**Tech Stack:** PHP 8.1+ (Moodle 5.0+), HTML, CSS Grid, Mustache (canvas FAST step 9), pas de JavaScript ajouté.

**Spec :** `docs/superpowers/specs/2026-05-07-ai-feedback-2col-and-step9-design.md`

---

### Task 1 : Refonte HTML du composant de feedback

**Files:**
- Modify: `gestionprojet/pages/student_ai_feedback_display.php` — uniquement la partie HTML à partir de la ligne 141 (la logique PHP de la ligne 1 à 139 est conservée intacte)

- [ ] **Step 1 : Lire le fichier complet pour vérifier la délimitation HTML**

Lire `gestionprojet/pages/student_ai_feedback_display.php`. Confirmer que :
- Lignes 1-139 = logique PHP (récupération des données, parsing JSON, calcul `$gradeColorClass`).
- Ligne 140 = `?>` qui ferme le bloc PHP.
- Lignes 141 à fin = HTML à remplacer.

- [ ] **Step 2 : Remplacer l'intégralité du HTML à partir de la ligne 141**

Avec `Edit`, remplacer le bloc HTML existant (de la ligne 141 `<div class="student-feedback">` jusqu'à la fin du fichier `</div>`) par le markup ci-dessous.

```html
<div class="student-feedback">
    <div class="feedback-header">
        <div class="feedback-title">
            <span class="icon">📊</span>
            <span><?php echo get_string('grade', 'gestionprojet'); ?></span>
        </div>
        <div class="grade-badge <?php echo $gradeColorClass; ?>">
            <span>⭐</span>
            <span><?php echo format_float($submission->grade, 1); ?> / 20</span>
        </div>
    </div>

    <?php if ($hasDetailedFeedback): ?>

    <div class="feedback-body">
        <div class="feedback-col-left">
            <div class="feedback-grade-card">
                <div class="grade-value"><?php echo format_float($submission->grade, 1); ?>/20</div>
                <div class="grade-label"><?php echo get_string('ai_grade_suggested', 'gestionprojet'); ?></div>
            </div>

            <?php if ($showFeedback && !empty($submission->feedback)): ?>
            <div class="feedback-section">
                <div class="section-title">
                    <span class="section-icon">💬</span>
                    <span><?php echo get_string('feedback', 'gestionprojet'); ?></span>
                </div>
                <div class="feedback-text">
                    <?php echo nl2br(s($submission->feedback)); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($showSuggestions && !empty($suggestions)): ?>
            <div class="feedback-section">
                <div class="section-title">
                    <span class="section-icon">💡</span>
                    <span><?php echo get_string('ai_suggestions', 'gestionprojet'); ?></span>
                </div>
                <div class="suggestions-list">
                    <ul>
                        <?php foreach ($suggestions as $suggestion): ?>
                        <li><?php echo s($suggestion); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="feedback-col-right">
            <?php if ($showCriteria && !empty($criteria)): ?>
            <div class="feedback-section">
                <div class="section-title">
                    <span class="section-icon">📋</span>
                    <span><?php echo get_string('ai_criteria', 'gestionprojet'); ?></span>
                </div>
                <div class="criteria-grid">
                    <?php foreach ($criteria as $criterion):
                        $score = $criterion['score'] ?? 0;
                        $max = $criterion['max'] ?? 5;
                        $percentage = ($max > 0) ? ($score / $max) * 100 : 0;
                        $scoreClass = ($percentage >= 70) ? 'score-high' : (($percentage >= 50) ? 'score-medium' : 'score-low');
                    ?>
                    <div class="criteria-item">
                        <div class="criteria-info">
                            <div class="criteria-name"><?php echo s($criterion['name'] ?? ''); ?></div>
                            <?php if (!empty($criterion['comment'])): ?>
                            <div class="criteria-comment"><?php echo s($criterion['comment']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="criteria-score <?php echo $scoreClass; ?>">
                            <?php echo format_float($score, 1); ?> / <?php echo format_float($max, 0); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($showKeywordsFound && !empty($keywordsFound)) || ($showKeywordsMissing && !empty($keywordsMissing))): ?>
    <div class="feedback-keywords-row">
        <div class="keywords-container">
            <?php if ($showKeywordsFound && !empty($keywordsFound)): ?>
            <div class="keywords-box found">
                <div class="keywords-box-title">
                    <span>✓</span>
                    <span><?php echo get_string('ai_keywords_found', 'gestionprojet'); ?></span>
                </div>
                <div class="keywords-list">
                    <?php foreach ($keywordsFound as $keyword): ?>
                    <span class="keyword-tag found"><?php echo s($keyword); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($showKeywordsMissing && !empty($keywordsMissing)): ?>
            <div class="keywords-box missing">
                <div class="keywords-box-title">
                    <span>!</span>
                    <span><?php echo get_string('ai_keywords_missing', 'gestionprojet'); ?></span>
                </div>
                <div class="keywords-list">
                    <?php foreach ($keywordsMissing as $keyword): ?>
                    <span class="keyword-tag missing"><?php echo s($keyword); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
```

- [ ] **Step 3 : Ajouter la chaîne `ai_grade_suggested` dans les fichiers de langue**

Le label « Note suggérée par l'IA » de la carte note utilise une nouvelle clé.

Dans `gestionprojet/lang/fr/gestionprojet.php`, ajouter (à un endroit cohérent avec les autres clés `ai_*`) :

```php
$string['ai_grade_suggested'] = 'Note suggérée par l\'IA';
```

Dans `gestionprojet/lang/en/gestionprojet.php`, ajouter :

```php
$string['ai_grade_suggested'] = 'AI-suggested grade';
```

- [ ] **Step 4 : Vérifier que la structure HTML est correcte**

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && php -l pages/student_ai_feedback_display.php
```
Expected output : `No syntax errors detected in pages/student_ai_feedback_display.php`

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && grep -c 'class="feedback-body"\|class="feedback-col-left"\|class="feedback-col-right"\|class="feedback-keywords-row"\|class="feedback-grade-card"' pages/student_ai_feedback_display.php
```
Expected : `5`

- [ ] **Step 5 : Commit**

```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet && git add gestionprojet/pages/student_ai_feedback_display.php gestionprojet/lang/fr/gestionprojet.php gestionprojet/lang/en/gestionprojet.php && git commit -m "$(cat <<'EOF'
feat(student-feedback): refonte 2 colonnes du retour IA

- grille CSS 1/3 (note + commentaire + suggestions) + 2/3 (criteres)
- bloc mots-cles deplace en pleine largeur sous les colonnes
- nouvelle cle de langue ai_grade_suggested pour la carte note compacte
- logique PHP de recuperation des donnees inchangee

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 2 : Ajout des styles CSS

**Files:**
- Modify: `gestionprojet/styles.css` — ajout en fin de bloc « student-feedback » (après la ligne 3649 environ qui termine la section actuelle des styles `student-feedback`)

- [ ] **Step 1 : Localiser la fin de la section `student-feedback` dans `styles.css`**

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && grep -n "student-feedback .suggestions-list li:last-child\|student-feedback .keyword-tag.missing" styles.css | tail -5
```
Repérer la ligne de la dernière déclaration `.student-feedback` avant le bloc suivant. C'est l'ancre où insérer le nouveau CSS.

- [ ] **Step 2 : Ajouter les styles 2 colonnes**

Avec `Edit`, ajouter le bloc suivant **après** la dernière déclaration `.path-mod-gestionprojet .student-feedback ...` existante (typiquement après `.suggestions-list li:last-child`) :

```css
/* New 2-column layout for student feedback (1/3 left + 2/3 right). */
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

- [ ] **Step 3 : Vérifier la présence des nouvelles classes**

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && grep -c "feedback-body\|feedback-col-left\|feedback-col-right\|feedback-grade-card\|feedback-keywords-row" styles.css
```
Expected : `>= 10` (chaque sélecteur apparaît au moins une fois, certains plusieurs fois pour les media queries et pseudo-classes).

- [ ] **Step 4 : Commit**

```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet && git add gestionprojet/styles.css && git commit -m "$(cat <<'EOF'
feat(styles): grille CSS 2 colonnes pour le retour IA eleve

- .feedback-body en CSS Grid 1fr 2fr, gap 1.5rem
- empilage 1 colonne en dessous de 768px
- carte note compacte (.feedback-grade-card) avec value 2.25rem
- ligne mots-cles pleine largeur sous les colonnes

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3 : Step 4 — déplacer l'include juste après `group-info`

**Files:**
- Modify: `gestionprojet/pages/step4.php` — déplacer l'include actuellement situé après le bloc `description` (ligne 169-170) pour le placer après `group-info` (ligne 161)

- [ ] **Step 1 : Lire la zone concernée**

Lire `gestionprojet/pages/step4.php` lignes 155 à 175 pour confirmer la structure :
- Lignes 157-161 : `<div class="group-info">...</div>`
- Lignes 163-167 : `<div class="description">...</div>`
- Lignes 169-170 : `<!-- AI Evaluation Feedback Display --> <?php require_once(...); ?>`

- [ ] **Step 2 : Supprimer l'include de sa position actuelle**

Avec `Edit`, supprimer le bloc :

```php
    <!-- AI Evaluation Feedback Display -->
    <?php require_once(__DIR__ . '/student_ai_feedback_display.php'); ?>

```
en remplaçant par une chaîne vide. Bien conserver la ligne blanche entre `</div>` (description) et `<?php`.

- [ ] **Step 3 : Insérer l'include juste après `group-info`**

Avec `Edit`, remplacer :

```php
    <!-- Group info -->
    <div class="group-info">
        👥 <strong><?php echo get_string('your_group', 'gestionprojet'); ?>:</strong>
        <?php echo format_string($group->name); ?>
    </div>

    <!-- Description -->
```

par :

```php
    <!-- Group info -->
    <div class="group-info">
        👥 <strong><?php echo get_string('your_group', 'gestionprojet'); ?>:</strong>
        <?php echo format_string($group->name); ?>
    </div>

    <!-- AI Evaluation Feedback Display -->
    <?php require_once(__DIR__ . '/student_ai_feedback_display.php'); ?>

    <!-- Description -->
```

- [ ] **Step 4 : Vérifier le déplacement**

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && grep -n "group-info\|student_ai_feedback_display\|class=\"description\"" pages/step4.php
```
Expected : la ligne `student_ai_feedback_display` apparaît entre la ligne `group-info` et la ligne `class="description"`.

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && php -l pages/step4.php
```
Expected : `No syntax errors detected in pages/step4.php`

- [ ] **Step 5 : Commit**

```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet && git add gestionprojet/pages/step4.php && git commit -m "$(cat <<'EOF'
feat(step4): deplacer le retour IA juste apres group-info

Avant : group-info -> description -> AI feedback -> form
Apres : group-info -> AI feedback -> description -> form

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 4 : Step 6 — déplacer l'include juste après `group-info`

**Files:**
- Modify: `gestionprojet/pages/step6.php` — déplacer l'include actuellement situé après la `info-box` (ligne 150-151) pour le placer juste après `group-info` (ligne 142)

- [ ] **Step 1 : Lire la zone concernée**

Lire `gestionprojet/pages/step6.php` lignes 138 à 155 pour confirmer la structure :
- Lignes 138-142 : `<div class="group-info">...</div>`
- Lignes 144-148 : `<div class="info-box">...</div>`
- Lignes 150-151 : `<!-- AI Evaluation Feedback Display --> <?php require_once(...); ?>`

- [ ] **Step 2 : Supprimer l'include de sa position actuelle**

Avec `Edit`, supprimer le bloc :

```php
    <!-- AI Evaluation Feedback Display -->
    <?php require_once(__DIR__ . '/student_ai_feedback_display.php'); ?>

```

- [ ] **Step 3 : Insérer l'include juste après `group-info`**

Avec `Edit`, remplacer :

```php
    <!-- Group info -->
    <div class="group-info">
        👥 <strong><?php echo get_string('your_group', 'gestionprojet'); ?>:</strong>
        <?php echo format_string($group->name); ?>
    </div>

    <!-- Info box -->
```

par :

```php
    <!-- Group info -->
    <div class="group-info">
        👥 <strong><?php echo get_string('your_group', 'gestionprojet'); ?>:</strong>
        <?php echo format_string($group->name); ?>
    </div>

    <!-- AI Evaluation Feedback Display -->
    <?php require_once(__DIR__ . '/student_ai_feedback_display.php'); ?>

    <!-- Info box -->
```

- [ ] **Step 4 : Vérifier le déplacement**

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && grep -n "group-info\|student_ai_feedback_display\|class=\"info-box\"" pages/step6.php
```
Expected : la ligne `student_ai_feedback_display` apparaît entre la ligne `group-info` et la ligne `class="info-box"`.

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && php -l pages/step6.php
```
Expected : `No syntax errors detected in pages/step6.php`

- [ ] **Step 5 : Commit**

```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet && git add gestionprojet/pages/step6.php && git commit -m "$(cat <<'EOF'
feat(step6): deplacer le retour IA juste apres group-info

Avant : group-info -> info-box -> AI feedback -> form
Apres : group-info -> AI feedback -> info-box -> form

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 5 : Step 7 — ajout du bandeau `group-info` conditionnel + déplacement de l'include

**Files:**
- Modify: `gestionprojet/pages/step7.php` — ajouter résolution de `$group` (en haut de la zone d'affichage), ajouter le bandeau `group-info` conditionnel, déplacer l'include

- [ ] **Step 1 : Lire la zone concernée**

Lire `gestionprojet/pages/step7.php` lignes 100 à 125 pour confirmer la structure actuelle :
- Ligne 105 : `echo '<div class="gp-student">';`
- Ligne 108 : `echo $OUTPUT->heading(...)` titre step 7
- Lignes 110-119 : `<div class="alert alert-info">...</div>` (description Bête à cornes)
- Ligne 121-122 : `// AI Evaluation Feedback Display` + `require_once(...)`

Vérifier aussi que `$groupid` est bien déjà résolu plus haut dans le fichier (utilisé pour `gestionprojet_get_or_create_submission`).

- [ ] **Step 2 : Ajouter la résolution de `$group` avant le `echo $OUTPUT->header();`**

Avec `Edit`, repérer la ligne `echo $OUTPUT->header();` (vers la ligne 87) et insérer juste **avant** :

```php
// Resolve group info (used by group-info banner below).
$group = null;
if ($gestionprojet->group_submission && $groupid) {
    $group = groups_get_group($groupid);
}

```

- [ ] **Step 3 : Ajouter le bandeau `group-info` conditionnel et déplacer l'include**

L'include est actuellement aux lignes 121-122 (après la description). Avec `Edit`, supprimer ces lignes :

```php
// AI Evaluation Feedback Display
require_once(__DIR__ . '/student_ai_feedback_display.php');

```

Puis avec un autre `Edit`, insérer le bandeau + include **juste après** `echo '<div class="gp-student">';` (ligne 105) et **avant** le heading. Remplacer :

```php
// Open student wrapper for full-width + blue accent.
echo '<div class="gp-student">';

// Moodle-native heading (replaces legacy emoji-prefixed h2).
echo $OUTPUT->heading(get_string('step7', 'gestionprojet'), 2);
```

par :

```php
// Open student wrapper for full-width + blue accent.
echo '<div class="gp-student">';

// Group info banner (only when group submission is enabled).
if ($gestionprojet->group_submission && $group) {
    echo '<div class="group-info">';
    echo '👥 <strong>' . get_string('your_group', 'gestionprojet') . ':</strong> ';
    echo format_string($group->name);
    echo '</div>';
}

// AI Evaluation Feedback Display.
require_once(__DIR__ . '/student_ai_feedback_display.php');

// Moodle-native heading (replaces legacy emoji-prefixed h2).
echo $OUTPUT->heading(get_string('step7', 'gestionprojet'), 2);
```

- [ ] **Step 4 : Vérifier la nouvelle structure**

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && grep -n "gp-student\|group-info\|student_ai_feedback_display\|step7'\|alert alert-info" pages/step7.php | head -10
```
Expected : l'ordre doit être `gp-student` → `group-info` → `student_ai_feedback_display` → `step7` (heading) → `alert alert-info` (description).

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && php -l pages/step7.php
```
Expected : `No syntax errors detected in pages/step7.php`

- [ ] **Step 5 : Commit**

```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet && git add gestionprojet/pages/step7.php && git commit -m "$(cat <<'EOF'
feat(step7): ajouter group-info + deplacer le retour IA en haut

- ajout de la resolution \$group via groups_get_group(\$groupid)
- ajout du bandeau group-info conditionnel a group_submission
- deplacement de l'include feedback IA : etait apres la description,
  maintenant juste apres le bandeau group-info, avant le heading

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 6 : Step 9 — ajout du bandeau `group-info` conditionnel + ajout de l'include

**Files:**
- Modify: `gestionprojet/pages/step9.php` — ajouter résolution de `$group`, ajouter le bandeau et l'include avant le wrapper `gp-student` qui contient le canvas FAST

- [ ] **Step 1 : Lire la zone concernée**

Lire `gestionprojet/pages/step9.php` lignes 105 à 145 pour confirmer la structure :
- Ligne 108 : `echo $OUTPUT->header();`
- Lignes 109-112 : tabs
- Ligne 113 : heading step 9
- Lignes 115-121 : notification submitted_on
- Lignes 123-126 : `<div class="description">...`
- Lignes 128-137 : intro_text enseignant (alert gp-consigne-intro)
- Ligne 139 : `echo html_writer::start_div('gp-student');`
- Lignes 141-151 : canvas FAST + lock wrapper

Confirmer aussi que `$groupid` est résolu plus haut dans le fichier (lignes 38-45).

- [ ] **Step 2 : Ajouter la résolution de `$group` avant `echo $OUTPUT->header();`**

Avec `Edit`, repérer la ligne `echo $OUTPUT->header();` (ligne 108) et insérer juste **avant** :

```php
// Resolve group info (used by group-info banner below).
$group = null;
if ($gestionprojet->group_submission && $groupid) {
    $group = groups_get_group($groupid);
}

```

- [ ] **Step 3 : Insérer le bandeau `group-info` + l'include avant le wrapper `gp-student`**

Avec `Edit`, remplacer :

```php
echo html_writer::start_div('gp-student');

// Lock the FAST canvas after submission.
```

par :

```php
// Group info banner (only when group submission is enabled).
if ($gestionprojet->group_submission && $group) {
    echo html_writer::start_div('group-info');
    echo '👥 <strong>' . get_string('your_group', 'gestionprojet') . ':</strong> ';
    echo format_string($group->name);
    echo html_writer::end_div();
}

// AI Evaluation Feedback Display.
require_once(__DIR__ . '/student_ai_feedback_display.php');

echo html_writer::start_div('gp-student');

// Lock the FAST canvas after submission.
```

- [ ] **Step 4 : Vérifier la nouvelle structure**

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && grep -n "groups_get_group\|group-info\|student_ai_feedback_display\|gp-student\|gp-fast-readonly" pages/step9.php | head -10
```
Expected : l'ordre doit être `groups_get_group` (résolution) → `group-info` → `student_ai_feedback_display` → `gp-student` → `gp-fast-readonly`.

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && php -l pages/step9.php
```
Expected : `No syntax errors detected in pages/step9.php`

- [ ] **Step 5 : Commit**

```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet && git add gestionprojet/pages/step9.php && git commit -m "$(cat <<'EOF'
feat(step9): afficher le retour IA + ajouter group-info

- ajout de la resolution \$group via groups_get_group(\$groupid)
- ajout du bandeau group-info conditionnel a group_submission
- ajout de l'include student_ai_feedback_display.php juste avant
  le wrapper gp-student qui contient le canvas FAST
- l'evaluation IA etait deja generee a la soumission (cf submit_step.php)
  mais n'etait jamais affichee a l'eleve sur cette etape

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 7 : Bump version

**Files:**
- Modify: `gestionprojet/version.php`

- [ ] **Step 1 : Lire la version actuelle**

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && grep -E '\$plugin->(version|release)' version.php
```
Expected : `$plugin->version = 2026050900;` et `$plugin->release = '2.10.0';`

- [ ] **Step 2 : Modifier `version.php`**

Avec `Edit`, remplacer :

```php
$plugin->version = 2026050900;  // YYYYMMDDXX format
```

par :

```php
$plugin->version = 2026050700;  // YYYYMMDDXX format
```

Note : la nouvelle date `2026050700` correspond au 7 mai 2026, séquence `00`. Si la séquence du jour est déjà utilisée, incrémenter à `01`, `02`, etc. Vérifier avec :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && git log --oneline | grep -c "2026050700" || true
```

Puis avec `Edit`, remplacer :

```php
$plugin->release = '2.10.0';
```

par :

```php
$plugin->release = '2.10.1';
```

- [ ] **Step 3 : Vérifier le bump**

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet/gestionprojet && grep -E '\$plugin->(version|release)' version.php
```
Expected : `$plugin->version = 2026050700;` et `$plugin->release = '2.10.1';` (ou la séquence ajustée).

- [ ] **Step 4 : Commit**

```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet && git add gestionprojet/version.php && git commit -m "$(cat <<'EOF'
chore(version): bump 2.10.0 -> 2.10.1

Refonte 2 colonnes du retour IA eleve + extension step 9 (FAST).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 8 : Déploiement preprod + tests manuels

**Files:**
- Pas de fichier source modifié — seulement build du ZIP, SCP, purge cache

- [ ] **Step 1 : Construire le ZIP de déploiement**

Run :
```bash
cd /Volumes/DONNEES/Claude\ code/mod_gestionprojet && rm -f gestionprojet-v2.10.1.zip && zip -r gestionprojet-v2.10.1.zip gestionprojet/ -x "*.git*" "*.claude*" "*lessons.md"
```
Expected : `gestionprojet-v2.10.1.zip` créé, taille proche de la version 2.10.0 (≈ quelques MB).

- [ ] **Step 2 : Récupérer les coordonnées preprod**

Lire `TESTING.md` à la racine du repo pour confirmer l'hôte SSH et le chemin de déploiement preprod (référence stockée en mémoire `preprod_access.md`).

- [ ] **Step 3 : Déployer sur preprod via SCP + extraction**

Suivre la procédure standard documentée dans `TESTING.md` :
1. SCP le ZIP vers le serveur preprod.
2. Extraire dans le répertoire `mod/gestionprojet` du Moodle preprod.
3. Purger les caches : `php admin/cli/purge_caches.php`.
4. Lancer la migration si proposée par Moodle (notification `Plugin upgrade in progress`).

- [ ] **Step 4 : Tests manuels — affichage 2 colonnes (steps 4, 5, 6, 7, 8)**

Pour chacun de ces 5 steps :
1. Connecté en tant qu'élève d'un groupe ayant déjà soumis et reçu une note IA appliquée.
2. Vérifier le bandeau en-tête (icône 📊 + titre + badge note coloré).
3. Vérifier la colonne gauche 1/3 : carte note compacte (~2.25rem, fond blanc, bord gris clair) → commentaire principal → suggestions.
4. Vérifier la colonne droite 2/3 : critères avec barres de progression colorées.
5. Vérifier la ligne pleine largeur sous les colonnes : mots-clés trouvés (vert) + manquants (rouge).
6. Réduire la fenêtre à < 768 px → vérifier que la grille passe à 1 colonne, ordre : carte note + commentaire + suggestions, puis critères, puis mots-clés.

- [ ] **Step 5 : Tests manuels — step 9 (FAST)**

1. Connecté en tant qu'élève d'un groupe ayant soumis le FAST. Si aucune évaluation IA n'a été appliquée, l'enseignant doit appliquer une note depuis `grading.php` (vérifié sur les autres steps que ce flux fonctionne).
2. Recharger la page step 9.
3. Vérifier l'apparition du bandeau « Vous travaillez en groupe : <nom> » au-dessus du canvas FAST (en mode `group_submission=1`).
4. Vérifier que le bloc d'évaluation IA s'affiche juste sous le bandeau, avant le canvas FAST.
5. Vérifier que le canvas FAST reste accessible (et verrouillé après soumission).
6. Tester en mode `group_submission=0` : le bandeau ne doit pas s'afficher, le bloc IA reste visible en haut.

- [ ] **Step 6 : Tests manuels — sections désactivables**

Côté enseignant, dans `grading.php`, désactiver tour à tour chacun des flags :
- `show_feedback`
- `show_criteria`
- `show_keywords_found`
- `show_keywords_missing`
- `show_suggestions`

Pour chaque cas, retourner côté élève et vérifier :
- La grille 1/3 + 2/3 reste rigide (la colonne concernée se vide partiellement).
- La ligne mots-clés disparaît si les deux flags `show_keywords_*` sont désactivés.
- Aucune erreur PHP ni mise en page cassée.

- [ ] **Step 7 : Tests manuels — cas limites**

1. Élève sans note appliquée → aucun bloc IA ne s'affiche (composant `return` immédiat).
2. Note manuelle sans `gestionprojet_ai_evaluations` (passer une note via grading manuel sans cliquer Apply IA) → en-tête + carte note + commentaire texte uniquement, colonne droite vide.
3. Côté enseignant `grading.php` : vérifier qu'aucune régression visuelle n'apparaît (la vue enseignant n'a pas été touchée).

- [ ] **Step 8 : Validation finale**

Si tous les tests passent, signaler à l'utilisateur que la 2.10.1 est prête à pousser en prod (il décide du déploiement). Si des bugs sont identifiés, créer une nouvelle tâche d'investigation.

Pas de commit à cette étape (artefact ZIP exclu du repo via `.gitignore` si présent, sinon ne pas le committer).

---

## Self-Review

**Spec coverage** :
- Refonte HTML 2 colonnes → Task 1 ✓
- CSS Grid + responsive → Task 2 ✓
- Mots-clés pleine largeur → Task 1 (markup) + Task 2 (style) ✓
- Step 4 déplacement → Task 3 ✓
- Step 6 déplacement → Task 4 ✓
- Step 7 ajout group-info + déplacement → Task 5 ✓
- Step 9 ajout group-info + ajout include → Task 6 ✓
- Steps 5 et 8 inchangés → confirmé dans la spec et dans l'architecture du plan ✓
- Bump version → Task 7 ✓
- Tests manuels preprod → Task 8 ✓

**Placeholder scan** : aucun TBD/TODO. Tous les blocs de code sont complets. Aucune référence à des fonctions ou méthodes non définies (toutes les fonctions appelées sont des fonctions Moodle natives existantes : `groups_get_group`, `format_string`, `get_string`, `format_float`, `s`, `nl2br`, `html_writer::*`).

**Type consistency** : les noms de classes CSS sont cohérents entre Task 1 (HTML) et Task 2 (CSS). Les nouvelles classes sont : `feedback-body`, `feedback-col-left`, `feedback-col-right`, `feedback-grade-card`, `grade-value`, `grade-label`, `feedback-keywords-row`. Toutes apparaissent dans le HTML (Task 1) et toutes celles qui ont besoin de styles propres ont une règle CSS (Task 2).

**Nouvelle clé de langue** : `ai_grade_suggested` est définie dans Task 1 Step 3 (lang/fr et lang/en) et utilisée dans le HTML (Task 1 Step 2). Pas d'incohérence.
