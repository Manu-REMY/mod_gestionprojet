# Fenêtre du retour IA (étapes élève 4 à 9) — conception

Date : 18/11/2026. Modèle : fenêtre de [[mod-redaction]] 2.10.0 (registre D-086, « Adoptable ailleurs : mod_gestionprojet »).
Validée par Emmanuel le 18/11 (« go »).

## Intention

Les élèves ne repèrent pas les conseils ni ce qui manque : le retour n'apparaît que dans le volet de droite, après un
clic « Afficher le retour ». Comme dans mod_redaction, le retour s'ouvre au centre de l'écran, dit ce qui va et ce qui
manque, donne le niveau atteint et propose la soumission dès que le niveau SAT est atteint.

## Décisions d'Emmanuel (18/11)

- La soumission est mise en avant **à partir de SAT** (SAT ou TS atteint).
- Le **niveau atteint est indiqué à chaque retour**.
- La fenêtre d'attente actuelle (demande 36, fermable) est **remplacée** par la fenêtre de mod_redaction (non fermable).

## Approches

- **Retenue : le résultat s'affiche dans la fenêtre sans recharger la page.** Le point d'accès d'état
  (`ajax/get_evaluation_status.php`) renvoie déjà le retour filtré rendu par le serveur ; il renvoie en plus le contenu
  de la fenêtre (`result_html`, rendu par une fonction PHP, valeurs échappées) et `can_submit`. Garde l'acquis de 2.12.2
  (pas de rechargement, rien de tapé perdu).
- Écartée : recharger la page et ouvrir une fenêtre rendue par le gabarit (marque `sessionStorage`), comme
  mod_redaction : ajoute un rechargement que le plugin a supprimé en 2.12.2.

## Conception

1. **Attente** : dès le clic « Demander un retour », et à l'ouverture d'une page dont un retour est en préparation :
   fenêtre au centre, sans croix, Échap sans effet, focus gardé dedans. Anneau qui tourne avec minuteur (mm:ss depuis
   la demande), trois étapes vraies : ✓ Copie envoyée ; L'IA corrige (« en file d'attente » tant que le statut est
   `pending`, « en cours » quand il est `processing`) ; Ton retour s'affiche. Phrase de patience.
2. **Résultat** (statut `completed`) : la fenêtre se remplit, sans rechargement :
   - **niveau atteint**, toujours en toutes lettres (bandeau existant `gestionprojet_level_banner_html` : sigle +
     mot, jamais la couleur seule) et ce qui manque pour le niveau suivant ;
   - texte du retour (filtré) ;
   - deux colonnes **✓ Ce qui va** (critères notés au maximum, fond bleu clair) / **✗ Ce qui manque** (critères sous le
     maximum, avec leur commentaire, cadre orange) ; une colonne vide n'est pas affichée ; critères masqués par le
     professeur : pas de colonnes ;
   - encadré **→ Pour ton prochain retour** (les pistes, cadre bleu) ;
   - langue : fautes barrées puis corrigées (« ~~recoit~~ → reçoit »), note de langue.
   - Boutons : **SAT ou TS atteint** et soumission possible → « Soumettre ma version finale » (plein, ferme la fenêtre
     et clique le bouton de soumission du volet, donc la confirmation habituelle) + « Améliorer encore (N retours
     restants) » (absent à 0) ; sinon « Retourner à ma copie » (plein). Croix et Échap ferment la fenêtre de résultat.
3. **Échec** (statut `failed`) : « Le retour n'a pas pu être préparé. Il n'est pas décompté : redemande-le. »,
   bouton « Retourner à ma copie ».
4. **Bandeau bleu** en haut de la page de l'étape (au-dessus du formulaire) tant que le dernier retour atteint SAT et
   que la soumission est possible : « ✓ Niveau SAT atteint » + bouton « Soumettre ma version finale » (renvoie au bouton
   du volet). Absent pendant une préparation, après soumission, en lecture seule (professeur).
5. **Volet de droite** : le dernier retour garde le même code couleur et les libellés « Ce qui va / Ce qui manque /
   Pour ton prochain retour » (même fonction de rendu que la fenêtre, sans boutons).

## Choix de l'agent (à corriger si besoin)

- Étapes 5 à 9 : pas de niveau (grille sans niveaux) → fenêtre avec colonnes, sans proposition de soumission.
- Mode paliers (ancien) : inchangé.
- Pas de piste sous chaque champ : les critères ne correspondent pas un à un aux cases du formulaire.
- Pas de compteur d'éléments attendus : le corrigé n'a pas de repères E1, E2…
- Dans une activité de groupe, tout membre dont la page suit un retour en préparation voit la fenêtre.
- « Ce qui va » = score ≥ max (critère plein) ; un critère sans max est classé dans « Ce qui manque » s'il a un
  commentaire, sinon ignoré.

## Hors périmètre

Écran du professeur, textes produits par l'IA, grille, seuils, mode paliers, soumission (fenêtre de confirmation
inchangée).

## Tests

Tests purs : classement des critères (va / manque), présence des boutons selon niveau et soumission, échappement,
absence d'`innerHTML` dans le script, build à jour. Rendu vérifié à l'écran en préprod (bureau et téléphone).
