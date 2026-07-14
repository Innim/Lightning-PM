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
- For icons FontAwesome 7 is used (free version).
- Keep JS modular and colocated with related UI screens when possible.
- Try to use Bootstrap 5 components and utilities before adding custom CSS.
- For dialogs/modals, prefer the `lpm.dialog` wrapper in `lpm-scripts/lightning.js`: `lpm.dialog.show({title, text|content, primaryBtn, onPrimary, secondaryBtn, onSecondary, ...})` and `lpm.dialog.confirm({...})`. It clones the base `#dynamicModal` template in `lpm-themes/default/page.html`. Do not add jQuery UI dialogs or hand-rolled modals.
- jQuery UI is being retired in favor of Bootstrap 5. Dialogs, the comment editor/preview Tabs, and the completion-date picker are already migrated; only the global tooltip (`uitooltip` bridge + `$(document).uitooltip(...)` in `lpm-scripts/lightning.js`, read in `lpm-scripts/issues.js`) still depends on it, which is why the lib is still loaded via `PageConstructor.php`/`PagePrinter.php`. Don't introduce new jQuery UI usage.
- Bootstrap+jQuery gotcha: `lpm-scripts/lightning.js` polyfills `Element.prototype.hide()`/`show()` to set `style.display`. Because jQuery invokes an element's native method matching a triggered event's base type, Bootstrap's `hide.bs.*`/`show.bs.*` events make jQuery call `.hide()`/`.show()` on the event target — unexpectedly hiding deselected tabs, dropdown toggles, etc. Fix by restoring `display` on the paired `hidden.bs.*` event, or in a microtask on `hide.bs.*` to avoid flicker with fade transitions (see the `hidden.bs.dropdown` and `hide.bs.tab` handlers in `lightning.js`).
- Keep templates minimal: templates in `lpm-themes/` should only contain markup-related code. Move business logic and data shaping into PHP classes/services. For example, use model helpers like `LPMFile::isVideo()` to check file types instead of MIME checks in templates, and prefer rendering via `PagePrinter` methods.
- At the top of each template, document all required external variables in a `Требуются:` PHP comment, following the pattern used in `lpm-themes/default/comment-text.html`.

## Validation
- There is no project-wide automated test suite. Validate by:
  - Static review and targeted runtime checks where feasible.
  - Running the app in Docker when requested to verify critical paths.
  - For frontend behavior/appearance, a fast check is a headless Chrome/Chromium screenshot: `<chrome> --headless=new --disable-gpu --screenshot=out.png --virtual-time-budget=2500 file://<repro>.html`, then read the PNG. Build a minimal repro whose `<base href>` points at `lpm-themes/default/css/` and that loads the real `lpm-scripts/libs/*` (jQuery, `bootstrap.bundle.min.js`) so the real CSS cascade and JS behavior are reproduced without the running app.
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
- DO NOT add description of meaningless changes like "update changelog" unless this is ONLY committed change.
- Again: do not mention changelog updates unless this is the only change.

## Changelog Language
- All entries in `CHANGELOG.md` must be written in Russian.

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
