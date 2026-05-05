# Scénarios de test v2.6.3 — Bouton Soumettre + déclenchement IA auto

**Spec source :** `docs/superpowers/specs/2026-05-05-student-submit-ai-trigger-design.md` section 8
**Branche :** `feat/student-submit-ai-trigger`
**Version :** 2.6.3 (2026050505)

> Les credentials et URLs de la preprod sont dans `TESTING.md` (gitignored).

## Pré-requis

- Plugin déployé en preprod sur la version 2.6.3.
- Une activité Gestion de Projet avec au moins une étape soumissible (4-9) et un modèle prof configuré.
- Un compte enseignant et au moins deux comptes élèves (un seul + un groupe).
- Cron Moodle déclenchable manuellement (`php admin/cli/cron.php`).

## 10 scénarios

### 1. Soumission IA OK
**Contexte :** IA activée, clé valide, modèle prof rempli, élève seul.
**Étapes :**
1. Élève remplit step 4 (CDCF), clique « Soumettre mon travail ».
2. Modale s'ouvre, bouton « Soumettre définitivement » désactivé.
3. Élève coche la case « J'ai relu… » → bouton activé.
4. Élève clique → page recharge.
**Attendu :** bandeau « Évaluation IA en cours… » visible, tous les inputs en `disabled readonly`. Après cron : page rechargée auto, note + feedback IA affichés.

### 2. IA désactivée dans l'instance
**Contexte :** `ai_enabled = 0` dans la config de l'activité.
**Attendu :** modale sans la mention « La correction IA automatique démarrera immédiatement », soumission OK, pas de bandeau IA après reload, travail verrouillé.

### 3. IA activée mais clé manquante / modèle prof manquant
**Contexte :** `ai_enabled = 1` mais pas de clé API valide OU pas de modèle prof pour le step.
**Attendu :** soumission OK (status passe à 1), pas de bandeau IA après reload (`queue_evaluation()` a levé une exception attrapée).

### 4. Mode groupe — A soumet, B voit verrouillé
**Contexte :** mode groupe activé, A et B membres du même groupe.
**Étapes :**
1. A soumet step 4.
2. B ouvre la page step 4.
**Attendu :** B voit le travail en lecture seule + bandeau IA en cours / résultat. La modale, si B clique « Soumettre », doit afficher l'avertissement « tout le groupe ».

### 5. Erreur IA simulée → notif prof
**Contexte :** clé API invalide ou modèle injoignable.
**Étapes :**
1. Soumettre.
2. Lancer cron.
**Attendu :** éval flag `failed` en DB. Élève voit bandeau « IA indisponible, ton prof corrigera ». Prof reçoit popup Moodle (cloche) **et** email (selon ses prefs).

### 6. Re-soumission après unlock prof
**Étapes :**
1. Prof unlock un travail soumis (status → 0).
2. Élève modifie, soumet à nouveau.
**Attendu :** nouvelle éval créée. SQL : `SELECT * FROM mdl_gestionprojet_ai_evaluations WHERE submissionid = X ORDER BY timecreated DESC` retourne au moins 2 records.

### 7. Steps 4 à 9
Tester le flux complet sur step 4 (CDCF), 5 (essai), 6 (rapport), 7 (besoin élève), 8 (carnet), puis 9 (FAST si activé).
**Attendu :** modale + soumission + bandeau IA fonctionnent sur tous les steps.

### 8. Bypass — vérifier le verrouillage backend
**Étapes :**
1. Sur un travail soumis, ouvrir DevTools.
2. Modifier un `<input>` pour retirer `disabled readonly`, taper du texte.
3. Attendre l'autosave (10s).
**Attendu :** autosave AJAX retourne erreur `submissionlocked`, le contenu en DB n'est pas modifié.

### 9. Checkbox de relecture
**Étapes :**
1. Ouvrir la modale, ne pas cocher la case.
**Attendu :** bouton « Soumettre définitivement » reste `disabled`. Impossible de soumettre sans cocher.

### 10. Annulation modale
**Étapes :**
1. Ouvrir la modale, cliquer Annuler ou X.
**Attendu :** modale ferme, aucune requête AJAX, statut inchangé.

## Inspecter les évaluations IA en DB

```sql
SELECT id, step, submissionid, status, error_message, timecreated, timemodified
FROM mdl_gestionprojet_ai_evaluations
WHERE gestionprojetid = <X>
ORDER BY timecreated DESC LIMIT 10;
```
