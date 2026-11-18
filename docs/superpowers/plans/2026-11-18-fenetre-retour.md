# Fenêtre du retour IA Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Le retour demandé par l'élève (étapes 4 à 9) s'attend et s'affiche dans une fenêtre au centre (comme
mod_redaction D-086), avec le niveau atteint et la soumission proposée dès SAT.

**Architecture:** Une classe pure `classes/retour_view.php` classe les critères et décide de la proposition de
soumission ; `lib.php` rend le corps du retour (fenêtre et volet) ; `ajax/get_evaluation_status.php` renvoie
`result_html` et les drapeaux des boutons ; `amd/src/formative_button.js` remplace la fenêtre d'attente par la
nouvelle fenêtre (attente non fermable, puis résultat sans rechargement).

**Tech Stack:** Moodle 5.0+ (PHP 8.1+), AMD, CSS `.path-mod-gestionprojet`.

**Spec:** `docs/superpowers/specs/2026-11-18-fenetre-retour-design.md`

## Global Constraints

- Chaînes en `lang/en` et `lang/fr` ; commentaires en anglais ; pas de style ni de script en ligne ; pas d'`innerHTML`.
- Bleu + ✓ = ce qui va ; orange + ✗ = ce qui manque ; → = prochain retour ; la couleur n'est jamais seule.
- Soumission mise en avant si niveau atteint ∈ {S, TS} et soumission possible. Niveau toujours écrit.
- Filtre anti-corrigé appliqué (déjà fait par `gestionprojet_progress_state`).
- `amd/build` régénéré (`npx grunt amd` ou terser) ; version 2.17.0 (2026111800).

## Review Focus

- Critère sans `max` ou `score` non numérique → pas d'avertissement PHP (test Task 1).
- Copie déjà soumise / lecture seule professeur → pas de bouton « Soumettre », pas de bandeau (test Task 2).
- Retour d'une étape sans niveau (5 à 9) → pas de proposition de soumission (test Task 1).
- Statut `failed` → fenêtre d'échec, focus rendu, bouton « Demander un retour » rétabli (test Task 3).
- Commentaire d'IA contenant `<script>` → échappé dans `result_html` (test Task 2).

### Task 1: Classe pure `retour_view`

**Files:** Create `classes/retour_view.php`, Test `tests/pure/test_retour_view.php`

**Interfaces — Produces:**
- `retour_view::split_criteria(array $criteria): array` → `['va' => [['name','comment']…], 'manque' => […]]` ;
  va = score ≥ max (max > 0) ; manque = score < max ; sans max numérique : manque si commentaire, sinon ignoré.
- `retour_view::propose_submit(?array $level, bool $cansubmit): bool` → `$cansubmit && reached ∈ {S, TS}`.

Tests : 4/4 → va ; 2/4 avec commentaire → manque ; sans max sans commentaire → ignoré ; non-tableau ignoré ;
propose_submit(S, true) vrai, (TS, true) vrai, (F, true) faux, (S, false) faux, (null, true) faux.

### Task 2: Rendu serveur (fenêtre, volet, bandeau, point d'accès)

**Files:** Modify `lib.php` (`gestionprojet_progress_feedback_html`, nouveau `gestionprojet_feedback_columns_html`),
`pages/student_progress_panel.php`, `ajax/get_evaluation_status.php`, lang en/fr, `styles.css`.
Test `tests/pure/test_retour_window.php`.

- `gestionprojet_feedback_columns_html(array $feedback): string` : texte, colonnes va/manque, encadré pistes.
- `gestionprojet_progress_feedback_html` : niveau, colonnes, langue (faute barrée `<del>` → correction).
- Point d'accès : `result_html` (= feedback_html), `propose_submit`, `improve_label` (si retours restants).
- Volet : bandeau `gp-retour-seuil` (si `propose_submit` sur le dernier retour formatif, rien en attente).

### Task 3: Script de la fenêtre

**Files:** Modify `amd/src/formative_button.js`, `amd/src/progress_panel.js` (bandeau remonté en haut),
`amd/build/*`, `tests/pure/test_formative_modal.php`, `tests/pure/test_formative_live.php`.

- Attente non fermable : minuteur, 3 étapes (pending → file d'attente, processing → en cours).
- Résultat : `Templates.replaceNodeContents(body, result_html)` ; boutons selon `propose_submit` / `improve_label`.
- Échec : message, bouton retour. Soumettre : ferme et clique `#submitButton`.

### Task 4: Version, préprod, vault

Version 2.17.0, ZIP, préprod, vérification à l'écran, demande 44, registre, journal.
