# 2.16.0 — Step 4: each group completes a few service functions drawn at random (requests 42 and 43, D-088, D-089)

## Goal

Fewer boxes to fill at step 4 (21 to 23 per group today). A setting gives the number of FS each group completes
(3 for ROBOT MARTIEN and Projet PIEGE). Each group draws them at random on its first visit; the other FS are shown
complete (correction model text, provided). The AI grade, the grid, the level and the spelling only judge the drawn FS.

## Data

- `gestionprojet.step4_fs_tirage` INT(2) default 0: number of FS per copy; 0 = no draw (behaviour unchanged).
- `gestionprojet_cdcf.fs_tirees` TEXT null: JSON list of the drawn FS ids of this copy; null = no draw.

## Pure class `classes/fs_tirage.php` (no Moodle call)

- `eligible(consigne)`: ids of the FS with at least one field to complete (gap or prefill, on the FS or a criterion,
  text or level). A fully provided FS (ROBOT MARTIEN FS2) is never drawn.
- `choose(eligible, n, classcounts, totalcounts, rand)`: the n FS drawn the fewest times in the group's class, then in
  the activity; ties broken at random. n >= number of eligible FS: all of them.
- `consigne(consigne, model, drawn)`: the consigne this copy sees. Drawn FS: unchanged, flag `tiree`. Other FS: every
  field to complete takes the correction model text (FS and criterion matched by id) and becomes provided; flag
  `donnee`. `drawn` empty: consigne unchanged.
- `missing(consigne, model)`: fields to complete that the model does not fill (FS id + what), shown to the teacher.

`tiree` and `donnee` join `consigne_lock::FLAG_KEYS` (never stored in a copy, copied by `with_flags`).

## Where the copy's consigne replaces the shared consigne

One function `gestionprojet_cdcf_consigne($gestionprojet, $copy)` (lib.php) returns the consigne of a copy. Used by:
the student page (`with_flags`), `gestionprojet_cdcf_enforce_consigne`, seeding in `gestionprojet_get_or_create_submission`,
`reset_helper`, `gestionprojet_cdcf_follow_consigne` (per copy, old and new), `gestionprojet_cdcf_reseed_untouched`,
and the AI evaluator (prompt flags, "no modification" check, spelling texts). The model change also makes the
copies follow (non-drawn FS carry the model text).

## Draw

`gestionprojet_cdcf_draw($gestionprojet, $copy)` in `gestionprojet_get_or_create_submission`: setting > 0, consigne
shown, copy without draw, still a starting copy (`is_untouched`), no AI evaluation, draft. Class of a group: the
smallest grouping holding it other than the activity grouping (4A, 3B…). Started copies get no draw unless the teacher
applies it (button, with history row of the replaced copy).

## Teacher

- `mod_form`: "Number of FS to complete per group" (0 = all).
- Consigne page of step 4: draw table (group, drawn FS, state); "Draw again" for a copy not started; "Apply the draw"
  for a started copy (asks for confirmation); warning listing the fields the correction model does not fill.

## Student page (D-089)

Drawn FS: thick dark blue frame, header band "✏️ To complete by your group · FS n". Given FS: very light grey
background, thin frame, "🔒 Complete, given · FS n"; no criterion added or removed. Notice above the FS list.

## Request 43: state readable by the class tool

Fields to read, documented in the vault page: `gestionprojet_cdcf.fs_tirees`, `status`, `timesubmitted`,
`gestionprojet_ai_evaluations` (step 4, `submissionid`, `status`, `parsed_grade`, `langue_points`), level fields.
Public function `gestionprojet_step4_etat($gestionprojet, $groupid, $userid)`.
