# AGENTS.md – Working Instructions for the AI Assistant

This file tells the coding assistant how to safely and efficiently work in this repository.

## Scope & Priorities
- Safety first: keep changes minimal, focused, and reversible.
- No unrelated refactors or dependency bumps unless explicitly requested.
- Prefer surgical fixes that address the root cause without side effects.
- Update docs/user‑visible notes only when behavior changes or when asked.

## Repo Basics
- Backend: PHP 7.3 under `lpm-core` and related modules.
- Frontend: Vanilla JS under `lpm-scripts`, templates under `lpm-themes`, CSS in `lpm-themes/default/css`.
- Config: `lpm-config.inc.php` (runtime), Docker env in `.dev/docker-env/`.
- Fixed, non-secret, non-deployment constants (display limits, size caps) live in `lpm-core/consts.inc.php` (peers: `MAX_FILE_SIZE_MB`, `MAX_IMAGE_SIZE_MB`, `COPY_YEAR`). Prefer this over overloading domain classes (e.g. `Issue`) or `lpm-config.inc.php` (which is for per-deployment/runtime config and secrets).
- Data: app files in `lpm-files/`; logs under `_private/logs/` (create and ensure writable if needed).
- DB: schema dump in `.dev/db/dump.sql`, historical migrations in `.dev/db/changes-log.sql`.
- Formatting: PHP uses php-cs-fixer with `@PSR1,@PSR2` (see `README.md`).
- `CLAUDE.md` is a symlink to this `AGENTS.md` — edit `AGENTS.md` directly; writes through the `CLAUDE.md` symlink are refused.

## Architecture Notes
- Two frameworks coexist: a legacy one bundled in `lpm-libs/gm-framework-v1.1.1.phar` and the newer `GMFramework\*` under `lpm-libs/framework/`. The project is migrating off the legacy one. Watch for classes that still extend legacy bases (e.g. `LPMOptions extends Options` resolves to the phar's `Options`, which has no `saveOptions()`/`change()`).
- DB access: new code uses the V2 query builder `\GMFramework\DBQueryBuilder` via `LPMBaseObject` helpers `buildAndSaveToDbV2($sqlHash)` and `loadFromDV2()`/`loadAndParseV2()`. Do NOT hand-write SQL strings or use the legacy `DBConnect::queryt()`/`preparet()`. Hash form: `['INSERT' => $assoc, 'INTO' => LPMTables::X, 'ODKU' => ['field']]` (upsert), `['UPDATE' => ..., 'SET' => [...], 'WHERE' => [...]]`, `['DELETE' => ..., 'WHERE' => [...]]`. The builder backticks table/column names (reserved words like `option`/`value` are safe) and escapes values; throw `\GMFramework\ProviderSaveException` on failure. Canonical connection: `LPMGlobals::getInstance()->getDBConnect()`.
- Service layer (AJAX): JS calls `srv.<service>.<method>(args, onResult)` — `BaseService` is defined in `lpm-scripts/lightning.js`, per-screen bindings live in their own JS (e.g. `srv.admin` in `lpm-scripts/admin.js`). Requests hit `lpm-libs/flash2php/gateway.php` and dispatch to a PHP service extending `LPMBaseService` that returns `$this->answer()` / `$this->error('msg')`; the client sees `res.success` and `res.error`. Admin-only services override `beforeFilter()` to also require `checkRole(User::ROLE_ADMIN)` (see `lpm-core/services/AdminService.php`).
- Page routing: pages extend `LPMPage` (base `BasePage`); constructor is `(uid, title, needAuth, notInMenu, pattern, label, reqRole)`, and are registered manually in `PagesManager::__construct`. Restrict a page to admins with `reqRole = User::ROLE_ADMIN`; the menu auto-filters by `checkUserRole()`. Page/model classes are autoloaded via `lpm-core/classes.dump` (auto-regenerated on cache miss, not git-tracked), so new classes are picked up without manual registration in the autoloader. Render a template by setting `$this->_pattern` and passing data with `addTmplVar()`.
- Active menu highlighting: `PagesManager::getLinks4Menu()` marks a top-level `Link` current when its page `uid` equals the current page's `getMenuSectionUid()` (rendered in `page.html` as the `.active` nav-link). A page's section defaults to its own `uid`; a nested page with no menu entry of its own (`notInMenu`, e.g. `ProjectPage`, `UserPage`) declares which section it belongs to by setting `$this->_menuSectionUid` in its constructor (e.g. `ProjectsPage::UID`) — keep this in the page, not in a central map. Submenu active state is per-current-page: `getSubMenu()` marks the current subpage's `Link` current.
- App options: `LPMOptions` is a singleton over the `lpm_options` table (`option`/`value`). Read with `LPMOptions::getInstance()->prop`, persist with the static `LPMOptions::save(['name' => $value, ...])`. Note `cookieExpire` is stored in days but exposed in seconds in memory (×86400 on load) — edit it in days and write days back.

## Local Dev Environment
- Docker compose lives in `.dev/docker-env/`.
  - Start: from `.dev/docker-env/` run `docker-compose up` (or `-d`).
  - Rebuild: `docker-compose up --build` if `Dockerfile` changes.
- Dev helper `.dev/bin/lpm` wraps common container commands (composer, lint, php, exec, shell) so they run against the app's PHP 7.3 with one approval instead of per-command. Prefer it over raw `docker exec`:
  - Composer: `.dev/bin/lpm composer install` (runs bundled `composer.phar` in `lpm-libs/`).
  - Lint: `.dev/bin/lpm lint <repo-relative-path> [...]`.
  - Arbitrary: `.dev/bin/lpm exec <cmd>` / `.dev/bin/lpm php <args>` / `.dev/bin/lpm shell`.
  - Override container/mount via `LPM_CONTAINER` / `LPM_MOUNT` env vars (defaults: `lightning-pm`, `/var/www/html`).
- PHP settings: `short_open_tag = On` (see `README.md`).

## Editing Rules (for the assistant)
- Use patch-based edits only; do not run destructive shell commands without approval.
- Do not modify `lpm-libs/vendor/` or introduce new dependencies without explicit request.
- Keep PHP 7.3 compatibility; avoid newer language features.
- Match existing style and structure; follow patterns present in nearby files.
- Member order in classes: static methods come first — before properties/constants and the constructor. Place a new static method up top with the others, not after the constructor.
- Docblocks describe the contract (params, return, thrown exceptions, observable behavior), NOT internal implementation (which query builder is used, upsert/ODKU mechanics, storage details, casting tricks). The same applies to field docblocks — document what the field means, not how it is stored.
- When changing behavior, update inline PHPDoc/comments and, if user asks, `CHANGELOG.md`.
- In frontend JS within project pages, assume shared globals (`srv`, `showError`, `redirectTo`, `bootstrap`) are present; avoid redundant existence checks unless adding code outside the app context.
 - For UI components, prefer adding a `PagePrinter` method that includes the template and expose it via an alias in `lpm-core/aliases.inc.php` (e.g., `lpm_print_goto_issue($project)`), then call the alias in templates instead of `includePattern()` directly.

## DB Changes
- If a change requires schema updates:
  - Append the SQL to `.dev/db/changes-log.sql` with a timestamped comment.
  - Do not auto-edit `.dev/db/dump.sql`; it is updated manually by maintainers.
- Avoid breaking migrations; prefer additive changes and non-destructive scripts.

## Frontend Conventions
- Stick to existing patterns in `lpm-scripts/*.js` and template files in `lpm-themes/`.
- Bootstrap 5 is used; overrides live in `lpm-themes/default/css/bootstrap-reset.css`. The version is exactly **5.1.3** (`lpm-themes/default/css/bootstrap.min.css`) — utilities added in 5.2+ (`text-bg-*`) and 5.3+ (subtle `bg-*-subtle` / `text-*-emphasis`) are NOT available; use 5.1 equivalents (e.g. `bg-secondary` + `text-white`).
- Negative-margin utilities are DISABLED in this build (`$enable-negative-margins: false`) — `ms-n*`, `mt-n*`, `m-n*` etc. don't exist and silently do nothing. To pull/align an element left of the container edge (e.g. to line a first `nav-link` up with the brand/footer), drop the link padding (`px-0`) and space items with `gap-*`, or restructure — do NOT reach for negative margins. `gap-*` utilities ARE present.
- For icons FontAwesome 7 is used (free version).
- Keep JS modular and colocated with related UI screens when possible.
- Try to use Bootstrap 5 components and utilities before adding custom CSS.
- For dialogs/modals, prefer the `lpm.dialog` wrapper in `lpm-scripts/lightning.js`: `lpm.dialog.show({title, text|content, primaryBtn, onPrimary, secondaryBtn, onSecondary, ...})` and `lpm.dialog.confirm({...})`. It clones the base `#dynamicModal` template in `lpm-themes/default/page.html`. Do not add jQuery UI dialogs or hand-rolled modals.
- jQuery UI is not used — the project is fully on Bootstrap 5 for dialogs, tabs, the completion-date picker, and tooltips. Don't add it back.
- The global tooltip is one delegated Bootstrap tooltip on `<body>` in `lpm-scripts/lightning.js` (selector `[title]:not([data-tooltip]):not([data-bs-toggle]), [data-bs-toggle="tooltip"][title]:not([data-tooltip])`, `container: 'body'`), so any `[title]` element (including dynamically added ones) gets a tooltip. Its priority-change refresh lives in `lpm-scripts/issues.js`.
- Bootstrap 5 allows only ONE component instance per element (`Data.set`). A `[title]` element that also hosts another Bootstrap component (a `data-bs-toggle="dropdown"`/`"collapse"`/`"modal"`/… toggle) therefore CANNOT also get a Tooltip — the instance is silently not stored and the tip never hides (stays stuck open). Such toggles are excluded from the global tooltip (hence the selector above; `data-bs-toggle="tooltip"` is re-included because it hosts no conflicting component). To give such a toggle a styled tooltip anyway, put the `title` on an inner icon (no component there) and keep an `aria-label` on the toggle for its accessible name — see the `.` menu in `issue.html` and `goto-issue.html`; `lightning.js` hides that icon tooltip on `show.bs.dropdown`/`show.bs.collapse` so it doesn't linger over the opened menu/panel.
- Don't use the `.tooltip` class for custom widgets — Bootstrap's tooltip element owns it. The homegrown hover widget on the issue-id cell uses `.copy-tooltip` for this reason.
- Bootstrap+jQuery gotcha: `lpm-scripts/lightning.js` polyfills `Element.prototype.hide()`/`show()` to set `style.display`. Because jQuery invokes an element's native method matching a triggered event's base type, Bootstrap's `hide.bs.*`/`show.bs.*` events make jQuery call `.hide()`/`.show()` on the event target — unexpectedly hiding deselected tabs, dropdown toggles, etc. Fix by restoring `display` on the paired `hidden.bs.*` event, or in a microtask on `hide.bs.*` to avoid flicker with fade transitions (see the `hidden.bs.dropdown` and `hide.bs.tab` handlers in `lightning.js`).
- Keep templates minimal: templates in `lpm-themes/` should only contain markup-related code. Move business logic and data shaping into PHP classes/services. For example, use model helpers like `LPMFile::isVideo()` to check file types instead of MIME checks in templates, and prefer rendering via `PagePrinter` methods.
- At the top of each template, document all required external variables in a `Требуются:` PHP comment, following the pattern used in `lpm-themes/default/comment-text.html`.

## Validation
- There is no project-wide automated test suite. Validate by:
  - Static review and targeted runtime checks where feasible.
  - Running the app in Docker when requested to verify critical paths.
  - For frontend behavior/appearance, a fast check is a headless Chrome/Chromium screenshot: `<chrome> --headless=new --disable-gpu --screenshot=out.png --virtual-time-budget=2500 file://<repro>.html`, then read the PNG. Build a minimal repro whose `<base href>` points at `lpm-themes/default/css/` and that loads the real `lpm-scripts/libs/*` (jQuery, `bootstrap.bundle.min.js`) so the real CSS cascade and JS behavior are reproduced without the running app.
    - Gotchas: headless Chrome's minimum layout viewport is ~500px wide, so `--window-size` widths below 500 still lay out at 500px while the PNG is the narrower width — right-aligned content (e.g. a navbar toggler) gets cropped off the image edge; screenshot at ≥500px to see it. Responsive `@media` breakpoints only apply if the repro has a `<meta name="viewport" content="width=device-width, initial-scale=1">` (the app's `page.html` has none, so its media queries evaluate at the default ~980px). To verify a collapsed/expanded state directly, add/remove the `.show` class on the `.collapse` element in the repro.
- Run PHP checks (e.g. `php -l`) inside the running Docker container, not host PHP. The host may run a newer PHP (e.g. 8.x) that flags PHP 7.3-valid syntax (like `$str{...}` offsets) as errors — false positives. Lint via `.dev/bin/lpm lint <repo-relative-path>` (container `lightning-pm`, PHP 7.3, repo mounted at `/var/www/html`).
- Run composer only within the container if needed and approved.

## Common Tasks Cheat Sheet
- Backend feature/fix:
  1) Update PHP in `lpm-core/...`.
  2) Adjust templates in `lpm-themes/...` if needed.
  3) Wire JS in `lpm-scripts/...` for UI interactions.
- Adding config:
  - Runtime: 
    - template `lpm-config.inc.template.php` (do not commit secrets);
    - local `lpm-config.inc.php` (local only, not committed).
  - Docker: 
    - template `.dev/docker-env/.env.template`;
    - local `.dev/docker-env/.env` (local only, not committed).
- Logging:
  - Write to `_private/logs/` if enabled; ensure directory exists and is writable.

## Approval & Safety
- Networked commands, dependency installs, or destructive actions require explicit approval.
- Prefer reading and patching files over shell mutations.
- Never commit secrets. Do not hardcode tokens or passwords.

## Commit Messages
- Commit messages must be in English
- Use Conventional Commits style: `type: summary` or `type(scope): summary`.
- Prefer a concise one-line summary, add detailed descriptions ONLY for important or big changes.
- Common types: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `release`.
- Use `refactor` ONLY for behavior-preserving changes (pure code rearrangement, no change to UI, appearance, or behavior). Replacing one UI component with another (e.g. jQuery UI → Bootstrap) changes appearance/behavior and adds logic — that is a `feat` (or `fix` if it repairs broken behavior), not a `refactor`.
- DO NOT add description of meaningless changes like "update changelog" unless this is ONLY committed change.
- Again: do not mention changelog updates unless this is the only change.

## Changelog Language
- All entries in `CHANGELOG.md` must be written in Russian.
- Entries describe the user-facing change/behavior only — no implementation details (CSS selectors, class names, function names, root-cause internals). Put the "how" in the commit message/code.
- Be terse — no filler. Don't pad an entry by contrasting against the old state (e.g. avoid "вместо прежнего широкого блока …"); just state the change.

## File Reference Style (for assistant responses)
- Use clickable paths (e.g., `lpm-core/base/LightningEngine.php:42`). No ranges.
- Wrap commands, paths, and identifiers in backticks.

## Done Checklist (before handing off)
- Changes are minimal, coherent, and consistent with style.
- No stray debug statements or unused code.
- Docs updated if behavior changed (or user requested).
- Provided short verification steps or commands, if applicable.
- If a release was performed:
  - Current branch is `develop`.
  - Tag `version/{version}` exists locally and is pushed.
  - `CHANGELOG.md` contains the dated section for `{version}`.

## Release Process
- Verify version: set target in `lpm-core/version.inc.php` (`VERSION`).
- Update changelog:
  - Move items from `## Next` to `## {version} - {YYYY-MM-DD}` in `CHANGELOG.md`.
  - Keep unrelated items under `## Next` for future.
- DB changes:
  - If `.dev/db/changes-log.sql` contains new statements since last release, replace the latest placeholder comment (e.g., `--NEXT`) with `-- {version}` directly above the new block.
  - Do not edit `.dev/db/dump.sql`.
- Commit on `develop`:
  - `git add -A && git commit -m "release: {version}"`.
- Merge to `master` and tag:
  - `git checkout master && git merge --no-ff develop -m "merge: release {version}"`.
  - `git tag -a version/{version} -m "Release {version}"`.
- Push:
  - `git push origin master develop --follow-tags`
  - Multiple remotes:
    - `git remote | xargs -I R git push R master --follow-tags`
    - `git remote | xargs -I R git push R develop`
- Return to `develop`:
  - `git checkout develop` and push if ahead.
  - Verify branch: `git rev-parse --abbrev-ref HEAD` → `develop`.
  - Verify clean tree: `git status -sb` shows no changes.
  - Verify tag exists: `git tag -l 'version/{version}'` (optional remote check: `git ls-remote --tags origin 'version/{version}'`).
