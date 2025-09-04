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
- Data: app files in `lpm-files/`; logs under `_private/logs/` (create and ensure writable if needed).
- DB: schema dump in `.dev/db/dump.sql`, historical migrations in `.dev/db/changes-log.sql`.
- Formatting: PHP uses php-cs-fixer with `@PSR1,@PSR2` (see `README.md`).

## Local Dev Environment
- Docker compose lives in `.dev/docker-env/`.
  - Start: from `.dev/docker-env/` run `docker-compose up` (or `-d`).
  - Rebuild: `docker-compose up --build` if `Dockerfile` changes.
  - Composer (inside container): `docker exec -w /var/www/lpm-libs/ lightning-pm php composer.phar install`.
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
- Bootstrap 5 is used; overrides live in `lpm-themes/default/css/bootstrap-reset.css`.
- For icons FontAwesome 7 is used (free version).
- Keep JS modular and colocated with related UI screens when possible.
- Try to use Bootstrap 5 components and utilities before adding custom CSS.

## Validation
- There is no project-wide automated test suite. Validate by:
  - Static review and targeted runtime checks where feasible.
  - Running the app in Docker when requested to verify critical paths.
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

## File Reference Style (for assistant responses)
- Use clickable paths (e.g., `lpm-core/base/LightningEngine.php:42`). No ranges.
- Wrap commands, paths, and identifiers in backticks.

## Done Checklist (before handing off)
- Changes are minimal, coherent, and consistent with style.
- No stray debug statements or unused code.
- Docs updated if behavior changed (or user requested).
- Provided short verification steps or commands, if applicable.

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
- Push to all remotes:
  - `git push --all --follow-tags` (or `git remote | xargs -I R git push R master --tags && git remote | xargs -I R git push R develop`).
- Return to `develop`:
  - `git checkout develop` and push if ahead.
