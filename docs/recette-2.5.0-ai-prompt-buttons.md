# Recette manuelle — Boutons de génération du prompt IA (v2.5.0)

## Pré-requis

- Plugin déployé sur la preprod ou en local.
- Activité de test avec `ai_enabled = 1` et un provider configuré (Albert recommandé pour les tests : clé API intégrée).
- Compte enseignant avec la capacité `mod/gestionprojet:configureteacherpages`.

## Procédure

Pour chaque étape ∈ {4, 5, 6, 7, 8, 9}, ouvrir la page de modèle de correction enseignant correspondante et exécuter les vérifications suivantes.

### 1. Affichage des boutons

- [ ] Les deux boutons « Modèle par défaut » et « Générer depuis le modèle » apparaissent au-dessus du `<textarea>` des instructions IA.
- [ ] Le style respecte la palette du plugin (boutons cohérents avec `.btn-save` / `.btn-add`).

### 2. Désactivation quand le modèle est vide (cas 1a)

- [ ] Avec un modèle entièrement vide (aucun champ rempli), le bouton « Générer depuis le modèle » est **désactivé**.
- [ ] Survol du bouton désactivé → tooltip « Remplissez d'abord le modèle de correction ».
- [ ] Le bouton « Modèle par défaut » reste **actif** (il n'a pas besoin de modèle rempli).

### 3. Réactivation après remplissage

- [ ] Remplir au moins un champ du modèle (ex: `produit` pour step 4, `nom_essai` pour step 5, etc.).
- [ ] Le bouton « Générer depuis le modèle » devient **actif** automatiquement (sans rechargement).
- [ ] Vider à nouveau le champ → bouton se désactive.

### 4. Insertion du modèle par défaut (textarea vide)

- [ ] Cliquer « Modèle par défaut » avec le textarea vide.
- [ ] Le textarea se remplit avec la chaîne `ai_instructions_default_step{N}`.
- [ ] L'autosave se déclenche (mention « Saved » ou équivalent dans le formulaire).

### 5. Confirmation avant écrasement (cas 2a)

- [ ] Avec le textarea déjà rempli, cliquer « Modèle par défaut ».
- [ ] Une boîte `confirm()` apparaît : « Remplacer le contenu actuel des instructions ? ».
- [ ] OK → contenu remplacé. Annuler → contenu inchangé.

### 6. Génération via l'IA (textarea vide)

- [ ] Cliquer « Générer depuis le modèle ».
- [ ] Le bouton se désactive immédiatement, son libellé devient « Génération en cours… ».
- [ ] Après quelques secondes (latence dépendante du provider), le textarea est rempli avec un texte cohérent en français.
- [ ] Toast de succès « Instructions générées avec succès. ».
- [ ] Le bouton se réactive automatiquement.
- [ ] L'autosave se déclenche.

### 7. Génération via l'IA (textarea non-vide)

- [ ] Cliquer « Générer depuis le modèle » avec le textarea rempli.
- [ ] Confirmation `confirm()` apparaît.
- [ ] OK → génération + remplacement. Annuler → contenu inchangé, bouton reste actif.

### 8. IA désactivée

- [ ] Modifier la config de l'activité : décocher `ai_enabled`.
- [ ] Recharger la page de modèle de correction.
- [ ] Le bouton « Générer depuis le modèle » est désactivé.
- [ ] Tooltip : « IA désactivée dans la configuration de l'activité ».
- [ ] Le bouton « Modèle par défaut » reste actif.

### 9. Erreur du provider (test optionnel)

- [ ] Configurer une fausse clé API (provider OpenAI/Anthropic/Mistral).
- [ ] Cliquer « Générer depuis le modèle » → le serveur renvoie une erreur.
- [ ] Toast d'erreur : « Échec de la génération. Réessayez. » (générique — la clé n'est PAS leakée dans la réponse).
- [ ] Le bouton se réactive.

### 10. Spécifique step 6 — fix `besoins`

- [ ] Sur le modèle de correction step 6, remplir UNIQUEMENT le champ « Besoins » (pas les autres).
- [ ] Cliquer « Générer depuis le modèle ».
- [ ] Vérifier (idéalement en activant `debugging` ou en lisant le prompt envoyé) que le contenu de `besoins` apparaît bien dans le méta-prompt — pas seulement filtré.
- [ ] Sans ce fix (commit `bddeb6e`), le contenu était silencieusement perdu.

### 11. Spécifique step 9 — FAST

- [ ] Avec un diagramme FAST vide (`fonctionsPrincipales: []`, `fonctions: []`), le bouton « Générer » est désactivé.
- [ ] Ajouter au moins une FT dans le diagramme → bouton actif.
- [ ] Cliquer « Générer » → instructions adaptées au diagramme.

## Compteur de couverture

54 vérifications nominales (9 cas × 6 étapes) + 2 cas spécifiques = **56 cases à cocher**.
