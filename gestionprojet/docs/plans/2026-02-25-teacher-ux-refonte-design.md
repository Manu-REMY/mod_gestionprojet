# Teacher UX Refonte — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Modernize the entire teacher UX — replace emoji icons with Lucide SVGs, add a teacher dashboard, unify feedback/toasts, refactor the grading layout, simplify navigation, and add responsive breakpoints. Fix Moodle compliance violations (inline JS/CSS) found in grading.php.

**Architecture:** Component-based approach — a centralized PHP icon helper class (`classes/output/icon.php`) serves SVG icons from `pix/lucide/`. A new AMD toast module provides unified feedback. Templates and PHP files are updated to use the icon helper instead of raw HTML entities/emojis. The grading page is refactored to move manual grading above AI evaluation, and inline JS/CSS is extracted to AMD modules and styles.css.

**Tech Stack:** PHP 8.1+, Moodle DML, Mustache templates, AMD/RequireJS modules, CSS (styles.css only), Lucide SVG icons (embedded, no CDN).

---

## Task 1: Create Lucide SVG Icon Library

**Files:**
- Create: `pix/lucide/` directory with 24 SVG files
- Create: `classes/output/icon.php` — icon helper class
- Modify: `styles.css` — add icon sizing/color CSS classes

**Step 1: Create the pix/lucide directory and download SVGs**

Create the directory `pix/lucide/` and add these 24 Lucide SVG icon files (24x24 viewBox, stroke-based, no fill):

```
clipboard-list.svg, target.svg, calendar-range.svg, flask-conical.svg,
file-text.svg, book-open.svg, lock.svg, lock-open.svg, check-circle.svg,
x-circle.svg, bot.svg, zap.svg, save.svg, pencil.svg, refresh-cw.svg,
alert-triangle.svg, users.svg, bar-chart-3.svg, message-circle.svg,
eye.svg, chevron-left.svg, chevron-right.svg, chevron-down.svg,
home.svg, award.svg, settings.svg
```

Each SVG must follow this format (example for `clipboard-list.svg`):
```xml
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="2"
     stroke-linecap="round" stroke-linejoin="round">
  <rect width="8" height="4" x="8" y="2" rx="1" ry="1"/>
  <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
  <path d="M12 11h4"/><path d="M12 16h4"/>
  <path d="M8 11h.01"/><path d="M8 16h.01"/>
</svg>
```

Download from https://unpkg.com/lucide-static@latest/icons/ or copy from the Lucide GitHub repo. Each file is approx 200-500 bytes.

**Step 2: Create the icon helper class**

Create `classes/output/icon.php` with:
- Static `render($name, $size, $color)` method returning `<span class="gp-icon gp-icon-{size} gp-icon-{color}" aria-hidden="true">{svg}</span>`
- Static `render_step($stepnum, $size, $color)` using STEP_ICONS constant map
- Private `load($name)` that reads from `pix/lucide/{name}.svg` with file cache
- STEP_ICONS mapping: 1=>clipboard-list, 2=>target, 3=>calendar-range, 4=>clipboard-list, 5=>flask-conical, 6=>file-text, 7=>target, 8=>book-open
- Full GPL header, namespace `mod_gestionprojet\output`, `clean_param($name, PARAM_ALPHANUMEXT)` for safety

**Step 3: Add icon CSS classes to styles.css**

Append after the "Global / Common Styles" section:

```css
/* Lucide Icon System */
.path-mod-gestionprojet .gp-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    vertical-align: middle;
    flex-shrink: 0;
}
.path-mod-gestionprojet .gp-icon svg { width: 100%; height: 100%; }

/* Sizes */
.path-mod-gestionprojet .gp-icon-xs { width: 14px; height: 14px; }
.path-mod-gestionprojet .gp-icon-sm { width: 18px; height: 18px; }
.path-mod-gestionprojet .gp-icon-md { width: 24px; height: 24px; }
.path-mod-gestionprojet .gp-icon-lg { width: 32px; height: 32px; }
.path-mod-gestionprojet .gp-icon-xl { width: 48px; height: 48px; }

/* Colors */
.path-mod-gestionprojet .gp-icon-inherit { color: inherit; }
.path-mod-gestionprojet .gp-icon-purple { color: #667eea; }
.path-mod-gestionprojet .gp-icon-green { color: #48bb78; }
.path-mod-gestionprojet .gp-icon-blue { color: #4299e1; }
.path-mod-gestionprojet .gp-icon-gray { color: #718096; }
.path-mod-gestionprojet .gp-icon-red { color: #e53e3e; }
.path-mod-gestionprojet .gp-icon-orange { color: #ed8936; }
.path-mod-gestionprojet .gp-icon-white { color: #ffffff; }
```

**Step 4: Commit**

```bash
git add classes/output/icon.php pix/lucide/ styles.css
git commit -m "feat: add Lucide SVG icon system with PHP helper class"
```

---

## Task 2: Replace Icons in Home Page and Templates

**Files:**
- Modify: `pages/home.php` (lines 36-45 icon map + add icon template vars)
- Modify: `templates/home.mustache` (all 15+ emoji HTML entities)
- Modify: `pages/correction_models.php` (icon map)
- Modify: `templates/correction_models.mustache` (all emoji entities)

**Step 1: Update pages/home.php**

Add `use mod_gestionprojet\output\icon;` and replace the emoji icon map (lines 36-45):

```php
$stepicons = [];
for ($i = 1; $i <= 8; $i++) {
    $stepicons[$i] = icon::render_step($i, 'xl', 'purple');
}
```

Add icon template variables to `$templatecontext`:

```php
$templatecontext['icon_lock'] = icon::render('lock', 'sm', 'red');
$templatecontext['icon_check'] = icon::render('check-circle', 'sm', 'green');
$templatecontext['icon_incomplete'] = icon::render('x-circle', 'sm', 'orange');
$templatecontext['icon_correction'] = icon::render('file-text', 'xl', 'purple');
$templatecontext['icon_bot'] = icon::render('bot', 'sm', 'purple');
$templatecontext['icon_pencil'] = icon::render('pencil', 'md', 'purple');
$templatecontext['icon_warning'] = icon::render('alert-triangle', 'sm', 'orange');
$templatecontext['icon_award'] = icon::render('award', 'md', 'purple');
$templatecontext['icon_error'] = icon::render('x-circle', 'sm', 'red');
$templatecontext['icon_users'] = icon::render('users', 'sm', 'blue');
$templatecontext['icon_eye'] = icon::render('eye', 'sm', 'gray');
$templatecontext['icon_bar_chart'] = icon::render('bar-chart-3', 'md', 'purple');
$templatecontext['icon_clipboard'] = icon::render('clipboard-list', 'md', 'purple');
```

**Step 2: Update templates/home.mustache**

Replace every hardcoded HTML entity:
- `&#128203;` (line 70) with `{{{icon_clipboard}}}`
- `&#128274;` (line 83) with `{{{icon_lock}}}`
- `&#10003;` (lines 89, 210) with `{{{icon_check}}}`
- `&#9203;` (lines 94, 215) with `{{{icon_incomplete}}}`
- `&#128221;` (line 107) with `{{{icon_correction}}}`
- `&#129302;` (line 112) with `{{{icon_bot}}}`
- `&#9998;` (line 125) with `{{{icon_pencil}}}`
- `&#9888;` (lines 131, 161) with `{{{icon_warning}}}`
- `&#127919;` (line 155) with `{{{icon_award}}}`
- `&#10060;` (lines 168, 174) with `{{{icon_error}}}`
- `&#128101;` (line 180) with `{{{icon_users}}}`
- `&#128065;` (line 192) with `{{{icon_eye}}}`

Also remove the inline `style=""` attributes on lines 186, 191, 195 (consultation cards) and move to CSS classes.

**Step 3: Update correction_models.php and template**

Same pattern — replace emoji map with `icon::render_step()` and pass icon template vars.

In `templates/correction_models.mustache` replace:
- `&#8592;` (line 55) with `{{{icon_chevron_left}}}`
- `&#10003;` (lines 61, 107) with `{{{icon_check}}}`
- `&#9888;` (line 67) with `{{{icon_warning}}}`
- `&#9203;` (line 112) with `{{{icon_incomplete}}}`
- `&#129302;` (line 118) with `{{{icon_bot}}}`

**Step 4: Commit**

```bash
git add pages/home.php templates/home.mustache pages/correction_models.php templates/correction_models.mustache
git commit -m "feat: replace emoji icons with Lucide SVGs on home and correction models pages"
```

---

## Task 3: Replace Icons in Grading Page

**Files:**
- Modify: `grading.php` (lines 183-189 icon map + all ~25 emoji entities throughout)
- Modify: `templates/grading_navigation.mustache` (emoji entities in nav)

**Step 1: Update grading.php icon definitions (lines 183-189)**

Add `use mod_gestionprojet\output\icon;` and replace:

```php
$steps = [
    7 => ['icon' => icon::render_step(7, 'md', 'purple'), 'name' => get_string('step7', 'gestionprojet')],
    4 => ['icon' => icon::render_step(4, 'md', 'purple'), 'name' => get_string('step4', 'gestionprojet')],
    5 => ['icon' => icon::render_step(5, 'md', 'purple'), 'name' => get_string('step5', 'gestionprojet')],
    8 => ['icon' => icon::render_step(8, 'md', 'purple'), 'name' => get_string('step8', 'gestionprojet')],
    6 => ['icon' => icon::render_step(6, 'md', 'purple'), 'name' => get_string('step6', 'gestionprojet')],
];
```

**Step 2: Replace all inline emoji entities in grading.php body**

Systematic replacement (approx 25 instances):
- `&#10060;` (line 342) -> `icon::render('x-circle', 'sm', 'red')`
- `&#9989;` (line 350) -> `icon::render('check-circle', 'sm', 'green')`
- `&#128275;` (lines 362, 954, 960) -> `icon::render('lock-open', 'sm', 'inherit')`
- `&#128221;` (line 368) -> `icon::render('file-text', 'sm', 'orange')`
- `&#129302;` (line 636) -> `icon::render('bot', 'md', 'purple')`
- `&#128640;` (line 647) -> `icon::render('zap', 'sm', 'inherit')`
- `&#9888;` (line 664) -> `icon::render('alert-triangle', 'sm', 'red')`
- `&#128260;` (lines 674, 823, 992, 998) -> `icon::render('refresh-cw', 'sm', 'inherit')`
- `&#9989;` (lines 687, 813) -> `icon::render('check-circle', 'sm', 'green')`
- `&#128202;` (line 704) -> `icon::render('bar-chart-3', 'sm', 'purple')`
- `&#9660;` (lines 705, 738) -> `icon::render('chevron-down', 'xs', 'gray')`
- `&#128172;` (line 737) -> `icon::render('message-circle', 'sm', 'blue')`
- `&#10003;` (line 755) -> `icon::render('check-circle', 'xs', 'green')`
- `&#10007;` (line 763) -> `icon::render('x-circle', 'xs', 'red')`
- `&#128065;` (line 796) -> `icon::render('eye', 'sm', 'gray')`
- `&#9998;` (lines 817, 836) -> `icon::render('pencil', 'sm', 'inherit')`
- `&#128190;` (line 856) -> `icon::render('save', 'sm', 'inherit')`

**Step 3: Update grading_navigation.mustache**

Pass icon variables in `$navcontext` and replace:
- `&#8592;` (lines 75, 101, 105) -> `{{{icon_chevron_left}}}`
- `&#8594;` (lines 114, 118) -> `{{{icon_chevron_right}}}`
- `&#128260;` (line 91) -> `{{{icon_refresh}}}`

**Step 4: Commit**

```bash
git add grading.php templates/grading_navigation.mustache
git commit -m "feat: replace all emoji icons with Lucide SVGs in grading page"
```

---

## Task 4: Replace Icons in Step Pages (1-8) and Teacher Model Pages

**Files:**
- Modify: `pages/step1.php`, `pages/step2.php`, `pages/step3.php`
- Modify: `pages/step4.php` through `pages/step8.php`
- Modify: `pages/step4_teacher.php` through `pages/step8_teacher.php`
- Modify: `styles.css` (replace emoji in CSS toggle switch)

**Step 1: Replace emojis in all step pages**

In each step file, add `use mod_gestionprojet\output\icon;` at top and replace:
- All direct emoji characters (e.g. home, arrows, step icons)
- All HTML entities for navigation, status, and save buttons

Common replacements across all step pages:
- Home button emoji -> `icon::render('home', 'sm', 'white')`
- Arrow right -> `icon::render('chevron-right', 'sm', 'inherit')`
- Arrow left -> `icon::render('chevron-left', 'sm', 'inherit')`
- Step-specific icon -> `icon::render_step($step, 'lg', 'purple')`
- Save floppy -> `icon::render('save', 'sm', 'inherit')`
- Users emoji -> `icon::render('users', 'sm', 'blue')`
- Bot/AI emoji -> `icon::render('bot', 'sm', 'purple')`
- Settings gear -> `icon::render('settings', 'sm', 'gray')`

**Step 2: Replace emoji in CSS toggle switch (styles.css lines 191, 211)**

Replace the lock/unlock emoji content properties with inline SVG data URIs:

```css
.path-mod-gestionprojet .slider:before {
    content: "";
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23667eea' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect width='18' height='11' x='3' y='11' rx='2' ry='2'/%3E%3Cpath d='M7 11V7a5 5 0 0 1 9.9-1'/%3E%3C/svg%3E");
    background-size: 14px 14px;
    background-repeat: no-repeat;
    background-position: center;
}

.path-mod-gestionprojet input:checked+.slider:before {
    content: "";
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect width='18' height='11' x='3' y='11' rx='2' ry='2'/%3E%3Cpath d='M7 11V7a5 5 0 0 1 10 0v4'/%3E%3C/svg%3E");
}
```

**Step 3: Commit**

```bash
git add pages/ styles.css
git commit -m "feat: replace all emoji icons across step pages and teacher model pages"
```

---

## Task 5: Fix Moodle Compliance — Extract Inline JS/CSS from grading.php

**Files:**
- Modify: `grading.php` (remove `<script>` block lines 867-1004, remove inline `style` attributes)
- Create: `amd/src/grading.js` (new AMD module)
- Modify: `styles.css` (add classes for extracted inline styles)

**Step 1: Create AMD module amd/src/grading.js**

Extract the inline JS (lines 867-1004) into a proper AMD module that:
- Initializes via `$PAGE->requires->js_call_amd()` with config params
- Handles toggle sections via event delegation (querySelectorAll `.ai-section-toggle`)
- Handles AI trigger, apply, retry buttons
- Handles unlock submission button
- Handles bulk reevaluate button
- Uses `core/str` for confirmation messages (passed via config)
- Uses `core/ajax` for AJAX calls
- Note: use `element.textContent` for safe text insertion, not innerHTML for untrusted data

**Step 2: Replace inline script in grading.php**

Remove the entire `<script>...</script>` block (lines 867-1004).
Also remove the auto-reload script on line 660 (`setTimeout...reload`).

Add AMD call near top after `$PAGE` setup:

```php
$PAGE->requires->js_call_amd('mod_gestionprojet/grading', 'init', [[
    'cmid' => $cm->id,
    'step' => $step,
    'submissionid' => $submission ? ($submission->id ?? 0) : 0,
    'strings' => [
        'confirm_unlock' => get_string('confirm_unlock_submission', 'gestionprojet'),
        'confirm_bulk' => get_string('confirm_bulk_reevaluate', 'gestionprojet'),
        'error' => get_string('error_invaliddata', 'gestionprojet'),
        'network_error' => get_string('toast_network_error', 'gestionprojet'),
        'bulk_reevaluate' => get_string('bulk_reevaluate', 'gestionprojet'),
        'unlock_submission' => get_string('unlock_submission', 'gestionprojet'),
        'bulk_processing' => get_string('bulk_reevaluate_processing', 'gestionprojet'),
    ]
]]);
```

For auto-reload, add `data-auto-reload="10000"` attribute to the `.ai-pending` div, and handle it in the AMD module.

**Step 3: Extract inline styles to CSS classes**

Replace all `style="..."` attributes in grading.php with CSS classes. Add to styles.css:

```css
.path-mod-gestionprojet .grading-timestamp { margin-left: auto; font-size: 13px; }
.path-mod-gestionprojet .grading-hint-text { margin-bottom: 12px; font-size: 13px; color: #666; }
.path-mod-gestionprojet .grading-draft-hint { color: #9ca3af; font-style: italic; font-size: 13px; }
.path-mod-gestionprojet .ai-error-box { padding: 12px; background: #fef2f2; border-radius: 6px; border: 1px solid #fecaca; margin-bottom: 12px; }
.path-mod-gestionprojet .ai-error-title { color: #dc2626; font-weight: bold; }
.path-mod-gestionprojet .ai-error-detail { color: #991b1b; margin: 8px 0 0; font-family: monospace; font-size: 12px; }
.path-mod-gestionprojet .ai-section-title { margin: 0; }
```

**Step 4: Build AMD and commit**

```bash
grunt amd --root=mod/gestionprojet
git add grading.php amd/src/grading.js amd/build/ styles.css
git commit -m "fix: extract inline JS and CSS from grading.php for Moodle compliance"
```

---

## Task 6: Teacher Dashboard Enhancement

**Files:**
- Modify: `pages/home.php` (add submission counts, dashboard data)
- Modify: `templates/home.mustache` (add progress bar + summary table)
- Modify: `styles.css` (dashboard styles)
- Modify: `lang/en/gestionprojet.php` (8 new strings)
- Modify: `lang/fr/gestionprojet.php` (8 new strings)

**Step 1: Add dashboard data queries in pages/home.php**

In the `if ($isteacher)` block, after the teacher steps loop, add:
- Count submissions per student step using `$DB->count_records_select()`
- Count graded submissions per step (where `grade IS NOT NULL`)
- Count correction models completed
- Build a `$templatecontext['dashboard']` array with: teachercomplete, teachertotal, modelscomplete, modelstotal, totalungraded, submissions array, hassubmissions bool

**Step 2: Add dashboard section in templates/home.mustache**

Insert after `{{#isteacher}}` before existing teacher steps:
- A `.gp-dashboard` div containing:
  - `.gp-dashboard-progress` with 3 segments (config X/Y, models X/Y, N to grade)
  - `.gp-dashboard-summary` table with per-step: submitted, graded, ungraded (with red badge)

**Step 3: Add dashboard CSS (see styles in design doc)**

Key classes: `.gp-dashboard`, `.gp-dashboard-progress`, `.gp-progress-segment`, `.gp-summary-table`, `.gp-badge-ungraded`
Include mobile responsive rule for stacking segments at 768px.

**Step 4: Add lang strings to both en and fr files**

EN: dashboard_config, dashboard_models, dashboard_grading, dashboard_to_grade, dashboard_submissions, dashboard_submitted, dashboard_graded, step
FR: same keys with French translations

**Step 5: Commit**

```bash
git add pages/home.php templates/home.mustache styles.css lang/
git commit -m "feat: add teacher dashboard with progress bar and submissions summary"
```

---

## Task 7: Toast Notification System

**Files:**
- Create: `amd/src/toast.js`
- Modify: `styles.css` (toast container and animation styles)
- Modify: `amd/src/autosave.js` (integrate toast on save)

**Step 1: Create amd/src/toast.js**

AMD module with:
- `show(message, type, duration)` — creates toast element, appends to container, animates in, auto-dismisses
- `success(msg)`, `info(msg)`, `warning(msg)`, `error(msg)` convenience methods
- Container created lazily (`.gp-toast-container` fixed bottom-right)
- Close button on each toast
- Note: use `document.createTextNode()` for message text (safe, no XSS), not innerHTML

**Step 2: Add toast CSS**

Toast container (fixed, bottom-right, z-index 10000), toast variants (success=green, info=blue, warning=orange, error=red), enter/exit animations (translateX slide-in), close button styling.

**Step 3: Integrate with autosave.js**

Add `'mod_gestionprojet/toast'` dependency to autosave module. After successful save, call `Toast.success()` with the saved string.

**Step 4: Build and commit**

```bash
grunt amd --root=mod/gestionprojet
git add amd/ styles.css
git commit -m "feat: add toast notification system and integrate with autosave"
```

---

## Task 8: Grading Layout Refonte

**Files:**
- Modify: `grading.php` (reorder sidebar sections, add ungraded counts)
- Modify: `templates/grading_navigation.mustache` (add badge to tabs)
- Modify: `styles.css` (collapsible AI section, tab badges, mobile responsive)

**Step 1: Reorder grading sidebar**

In grading.php, move the "Manual Grading Form" (currently lines 833-860) ABOVE the "AI Evaluation Section" (lines 629-831). The sidebar now shows:
1. Manual grading form (top, always visible)
2. AI evaluation (below, collapsible accordion)

**Step 2: Make AI section collapsible**

Wrap AI content in a toggle with header. Collapsed by default if submission already has a manual grade (`$submission->grade !== null`). Use data attribute for JS handling in the grading AMD module.

**Step 3: Add ungraded badges to step tabs**

In grading.php, count ungraded per step and add to step tab data. Update grading_navigation.mustache to show `<span class="gp-tab-badge">N</span>` when hasungraded is true.

**Step 4: Add CSS for tab badges, mobile stacking**

- `.gp-tab-badge` — red circle badge on tabs
- `.ai-section-body.collapsed { display: none; }` for collapsible AI
- `@media (max-width: 768px)` — stack submission-panel into single column, horizontal-scroll step tabs

**Step 5: Commit**

```bash
git add grading.php templates/grading_navigation.mustache styles.css
git commit -m "feat: reorder grading sidebar, add ungraded badges, mobile responsive"
```

---

## Task 9: Responsive Breakpoints and Navigation Cleanup

**Files:**
- Modify: `styles.css` (comprehensive responsive rules)
- Modify: step pages (remove duplicated bottom navigation blocks)

**Step 1: Add responsive CSS rules**

At 768px (tablet):
- Cards grid: 1 column
- Grading cards: 1 column
- Models grid: 1 column
- Form layout: 1 column
- Navigation: column direction
- Card icons: smaller (36px)
- Header section: column + centered text

At 480px (mobile):
- Card padding: 15px
- Nav buttons: smaller padding + font
- Dashboard segments: no min-width

**Step 2: Remove duplicated bottom navigation**

In step pages that have both top and bottom nav, remove the bottom copy. Keep only the sticky top navigation.

**Step 3: Commit**

```bash
git add styles.css pages/
git commit -m "feat: add responsive breakpoints and remove duplicated bottom navigation"
```

---

## Task 10: Lang Strings, Version Bump and Final Verification

**Files:**
- Modify: `lang/en/gestionprojet.php` (verify all new strings)
- Modify: `lang/fr/gestionprojet.php` (verify all new strings)
- Modify: `version.php` (bump version)

**Step 1: Verify all lang strings**

Run grep for all `get_string()` calls and verify each key exists in both lang files. Add any missing strings.

**Step 2: Bump version in version.php**

```php
$plugin->version = 2026022500;
$plugin->release = '2.1.0';
```

**Step 3: Build AMD modules**

```bash
grunt amd --root=mod/gestionprojet
```

**Step 4: Purge caches and test**

```bash
php admin/cli/purge_caches.php
```

**Step 5: Final commit**

```bash
git add -A
git commit -m "chore: bump version to 2.1.0, verify lang strings, build AMD"
```

---

## Moodle Compliance Verification Checklist

After completing all tasks, verify:

- [ ] `grep -rl '<style' --include="*.php" .` returns 0 results
- [ ] `grep -rl '<script' --include="*.php" .` returns 0 results
- [ ] `grep -rL "distributed in the hope" --include="*.php" .` returns 0 results (GPL header)
- [ ] `grep -rn 'var_dump\|print_r\|error_log' --include="*.php" .` returns 0 results
- [ ] `grep -rn '\$_GET\|\$_POST\|\$_REQUEST' --include="*.php" .` returns 0 results
- [ ] All new PHP files have full two-paragraph GPL header
- [ ] All new strings present in both en/ and fr/ lang files
- [ ] All styles in styles.css with `.path-mod-gestionprojet` namespace
- [ ] All JS in `amd/src/` as proper AMD modules
- [ ] All icon SVGs in `pix/lucide/` directory
- [ ] delete_instance() in lib.php has not been broken (still covers all 17 tables)
