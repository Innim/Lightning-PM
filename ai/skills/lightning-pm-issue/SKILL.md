---
name: lightning-pm-issue
description: Implement work described in a Lightning PM issue by using its HTTP API as the source of truth. Use when the user provides a Lightning PM issue link or asks the agent to start work from a Lightning PM issue - read the issue, comments, and attachments, choose a repository and base branch, create an issue branch, and then carry out the implementation in the local codebase without scraping HTML.
---

# Lightning PM Issue

## Overview

Use Lightning PM as the source of truth for task context. Use the API, not HTML scraping, whenever API access is available.

Treat a pasted Lightning PM issue link as an instruction to prepare and perform the implementation work described in that issue, not just to inspect it.

Load [references/api.md](references/api.md) when exact endpoints, request bodies, or auth details matter.

Prefer the bundled helper script `scripts/lpm-api.sh` over raw `curl` so API calls can be approved once via a single command prefix. The script is always at `scripts/lpm-api.sh` relative to this SKILL.md file — derive the path from the skill's own directory and express it with a `~/` prefix. Never expand `~/` to an absolute path like `/Users/<name>/...`, and never run `find` or any search to locate the script.

Before making any API call, verify that the agent's execution environment allows running the helper script. If the environment requires explicit authorization for shell commands (e.g., an allowlist, sandbox policy, or per-command approval), proactively ask the user to authorize `lpm-api.sh` for unrestricted use — ideally before the first call. A broad approval for the script path is better than repeated per-call prompts. Do not silently fail or fall back to raw `curl` without telling the user why.

## Workflow

1. Do not run a separate shell check for `LIGHTNING_PM_API_KEY`. The helper script validates the key before every API call and exits with a clear error message if it is missing — treat that failure as the signal to ask the user for the key.
2. Use `scripts/lpm-api.sh` for API calls unless there is a specific reason not to.
3. Resolve the pasted issue URL with `GET /api/v1/issues/resolve?url=<ISSUE_URL>`.
4. Save both identifiers from the resolve payload once at the start:
   - `id`: global unique issue id for API endpoints such as `/api/v1/issues/{issueId}/...`
   - `idInProject`: project-local issue number from the URL, used only for display, human references, and URL matching
5. Read the returned issue payload carefully:
   - title and description
   - comments
   - images
   - attached files
   - project and issue ids
   - action URLs or repository hints when present
6. Reuse the saved `id` and `idInProject` values in the rest of the workflow. Do not re-resolve before each request unless the issue URL itself changes.
7. Fetch `GET /api/v1/issues/{issueId}` if the resolve payload looks partial, stale, or omits fields you need. Use only the saved global `id` as `{issueId}`.
8. Download protected file URLs with the same auth headers when attachments matter for implementation or review.
9. Infer what change needs to be implemented in the local codebase from the issue description, comments, and attachments. Use Lightning PM as the source of truth when local assumptions conflict with issue context.
10. Infer the repository and base branch. Prefer `develop` unless the issue, repository structure, or branch list clearly indicates another parent.
11. Ask the user for repository choice only when it cannot be inferred safely.
12. Create the issue branch through `POST /api/v1/issues/{issueId}/branches` when implementation work is needed. Use only the saved global `id` as `{issueId}`.
13. Check out the issue branch locally (see [Branch Creation](#branch-creation)) before planning or editing, so any plan is made against the correct base code and not an unknown local state.
14. For non-trivial changes, propose a short plan and get user confirmation before editing code (see [Implementation Expectation](#implementation-expectation)).
15. Implement the described change in the local repository, using the issue context to guide scope, edge cases, and validation.
16. Validate the change with the target project's own tooling before proposing a commit (see [Validation](#validation)).
17. Draft the exact comment text and get explicit user approval before posting any issue comment.
18. Add a comment only when it provides value for humans, not as a progress log.

## Implementation Expectation

After reading the issue, continue into implementation unless the user explicitly asks only for analysis, triage, or clarification.

Do not stop after:

- resolving the URL
- summarizing the task
- choosing a repository
- creating the branch

Use the issue context to drive the actual code changes and only ask follow-up questions when a blocking ambiguity cannot be resolved safely from the issue, attachments, repository structure, or local code.

For **non-trivial changes**, first check out the issue branch, then propose a short plan and get user confirmation before editing code. "Non-trivial" means multi-file changes, anything touching the DB schema, public API, or user-visible behavior, or where more than one reasonable approach exists. The plan is a brief list of steps and files to touch, not a full analysis. For an obvious, single-spot fix, skip the plan and proceed.

Always plan against the **checked-out issue branch**, never the pre-switch working tree — before switching, the local tree may sit on an unknown or stale code version, so a plan made there can be wrong.

Pausing for plan approval on a non-trivial task is the one expected exception to the "continue into implementation" rule above — it is not a violation of it.

Changing the issue's status or assignee/membership is **out of scope**: the API surface used by this skill does not expose it. Do not try to move the ticket "in progress", assign it, or otherwise mutate issue metadata by guessing endpoints or scraping HTML — leave those actions to the user.

## Authentication

Send either:

- `Authorization: Bearer <LIGHTNING_PM_API_KEY>`
- `X-LPM-API-Key: <LIGHTNING_PM_API_KEY>`

Prefer `X-LPM-API-Key` for this skill and reuse the same auth when downloading protected file URLs returned by the API.

For the helper script:

- set `LIGHTNING_PM_API_KEY`
- call `bash ~/path/to/skill/scripts/lpm-api.sh <ROOT_URL> ...` where the path is derived from this skill's own directory

Use the helper script for both JSON API requests and protected file downloads.

## Repository Selection

Infer the repository without asking when there is a clear signal such as:

- only one repository in the project
- repository name matches the issue scope, **labels**, component, or file paths mentioned in the issue
- existing issue metadata or returned action URLs point to a specific repository
- branch naming or comments clearly reference one repository

Ask the user before branching when multiple repositories are plausible and the payload does not make one clearly safer than the others.

## Branch Creation

Use `POST /api/v1/issues/{issueId}/branches` with:

- `repositoryId`: the chosen repository
- `parentBranch`: usually `develop`
- `name`: a concise issue-specific branch name without a `feature/` prefix unless the project explicitly requires it

The Lightning PM backend prepends `feature/` automatically. Pass only the branch suffix.

Preferred format:

- `{issueNumber}.{short-kebab-slug}`
- example request body field: `891.inner-store-payment-method`
- resulting branch in Git: `feature/891.inner-store-payment-method`

Do not send names like `feature/891-inner-store-payment-method`, because that duplicates the prefix and loses the expected dot-separated task number format.

Prefer a human-readable slug that includes the issue number or a short task cue when appropriate.

After the `POST .../branches` call succeeds, Lightning PM has already created the branch **on the remote git server, at the correct base commit**. That remote branch — not any local ref — is the source of truth for the base. Get the local branch by fetching and checking out that exact remote branch. Remote branch creation in Lightning PM is not sufficient by itself; you still need the local checkout, but it must come *from the remote branch*.

Required local branch workflow:

1. Fetch the exact remote branch the API just created, then check it out:
   ```bash
   git fetch <remote> <feature/branch-name>
   git checkout -B <feature/branch-name> <remote>/<feature/branch-name>
   ```
   (`<remote>` is usually `origin`; `<feature/branch-name>` is the full name from the API response, e.g. `feature/891.inner-store-payment-method`.)
2. Immediately verify the current local branch with `git branch --show-current`.
3. If the local branch is not the intended issue branch, stop and fix branch state before making changes.
4. Re-check the current branch again before `git add` or `git commit`.

**Never reconstruct the issue branch from a local base ref** (`git checkout -b <branch> master`/`develop`). Local integration refs are frequently **stale** — the local `master`/`develop` can sit far behind the server's real branch, so branching from it silently produces an issue branch missing recent commits. The API-created remote branch already encodes the right base; fetch it instead of guessing the base yourself.

Equally, **never `git reset`/re-point the issue branch onto a different local branch** (a `pipe/*`, integration, or release branch) to "fix" a perceived wrong base. If the base looks wrong, re-fetch the remote issue branch and check it out again — do not graft it onto unrelated history.

Treat local branch fetch/checkout failure as a hard blocker. Do not continue implementation, staging, or committing on `develop`, `master`, or any other protected/base branch after a branch-switch failure.

If the fetch or checkout fails, inspect the cause first, for example:

- the remote branch name differs from what you expect (re-read the API response `branch.name`)
- conflicting local ref, packed refs, permissions, or lock-file problems

If the local branch cannot be checked out from the remote safely, stop and tell the user instead of continuing on the wrong branch.

## API Requests

Prefer the bundled helper script for all Lightning PM requests:

```bash
bash ~/path/to/skill/scripts/lpm-api.sh 'https://pm.example.com/project/demo/issue/891' GET '/api/v1/issues/resolve?url=https://pm.example.com/project/demo/issue/891'
```

```bash
bash ~/path/to/skill/scripts/lpm-api.sh 'https://pm.example.com' POST /api/v1/issues/43210/branches '{"name":"891.inner-store-payment-method","repositoryId":12,"parentBranch":"develop"}'
```

The script derives the Lightning PM origin from the passed root or issue URL and always sends `X-LPM-API-Key`, which makes approval simpler than repeated direct `curl` invocations.

Important id rule:

- call `resolve` once at the start and keep both `id` and `idInProject`
- use only global `id` in `/api/v1/issues/{issueId}/...` endpoints
- use `idInProject` only for display, human-facing references, branch naming, and matching the issue URL

## Validation

Before proposing a commit, validate the change with the **target project's own tooling** — lint, tests, or a build as appropriate — not host defaults. Host tooling often disagrees with the project's real runtime (language version, containerized toolchain) and produces false positives or negatives.

Find the correct commands in the target project's contributor docs (`AGENTS.md` / `CLAUDE.md` / `README`) and run them there. Reach the "verified-done" state in [Wrap-up After Implementation](#wrap-up-after-implementation) only after validation passes or is confirmed not applicable to the change.

If validation cannot be run (no tooling, environment not available), say so explicitly instead of implying the change is green.

## Commit Policy

Never create a git commit without explicit user approval.

Before committing:

1. Show the user the planned commit message.
2. Summarize what files will be staged and why.
3. Wait for the user to confirm before running `git add` + `git commit`.

Do not assume approval from silence, a general "looks good", or acceptance of the implementation. The user must explicitly say to commit (e.g. "commit it", "go ahead", "yes commit").

This applies to every commit, including fixup commits, test commits, and refactor commits.

## Comment Policy

Never post routine status comments such as:

- branch created
- implementation started
- working on this
- ready for review, unless accompanied by concrete tester guidance or a meaningful caveat

Never write the branch name in a comment. Lightning PM already tracks the issue branch, so naming it in a comment is redundant noise — reference the work by what changed, not by the git branch it landed on.

Post a comment only when it helps humans later. Good examples:

- tester instructions
- important implementation caveats
- known limitations
- assumptions that are not obvious from the issue
- rollout or recheck notes

Comment text may use Markdown when it improves readability. For section structure use heading syntax (`###` and below — never `#` or `##`), not bold runs like `**Поведение:**` followed by a list. Bold is fine for in-paragraph emphasis.

Write the comment in the same language as the issue. If the issue is in Russian, write the comment in Russian; if in English, write in English; and so on.

Keep the comment short, concrete, and action-oriented.

Always append a signature that makes it clear the comment was posted by the agent on the user's behalf. Include the current agent name in the signature. Default signature template:

```text
[AI-assisted comment by <agent-name>, approved by user]
```

Replace `<agent-name>` with the actual agent name before showing the draft to the user. Use the name the agent is known by in the current environment (e.g. "Claude Code", "Codex"). If no specific agent name is available, fall back to the underlying model name, or to the generic `AI assistant` — never invent a name or leave the `<agent-name>` placeholder in the posted text.

Before posting, show the exact final comment text to the user, including the signature, and wait for approval. Do not post a paraphrased or modified version after approval unless the user approves the updated text too.

**ONLY** post a comment, when user already accepted implementation or review work.

## Wrap-up After Implementation

Implementation isn't done when the code change lands locally — there is still commit, push, and optionally an issue comment. Do not stop silently after the last edit and force the user to remember the remaining ship steps. Walk through them in two stages: a **commit-and-push proposal** first, then a **separate comment proposal**.

### When to propose

Trigger the commit-and-push proposal once the implementation reaches a verified-done state:

- the change is in the working tree
- build / lint / tests are green (or unaffected)
- the user's most recent message did not request further changes (it was acceptance, neutral, or shifted focus to wrapping up)

Do **not** re-propose on every iteration. While the user is still asking for edits — even small ones — finish the requested change, summarize it briefly, and wait. Each "verified-done" state only triggers the proposal once; further change requests reset it, and the next verified-done state may propose again.

### Stage 1 — commit + push proposal

Make the commit proposal per the [Commit Policy](#commit-policy): draft the message, list the files to stage and why, and wait for explicit approval. Add one push-specific point: state that the branch will be pushed to its tracked remote immediately after the commit succeeds — push has no separate gate, nothing to discuss. Approving the commit message authorizes only that immediate push, nothing else.

After the commit lands, push the branch in the same turn without re-asking. If push fails (no upstream, auth, etc.), surface the failure and ask before retrying or changing remotes.

### Stage 2 — comment proposal (separate)

After push succeeds, address the comment as a **separate** message — commit/push has nothing to discuss, while a comment's wording, structure, and language usually do.

Follow the [Comment Policy](#comment-policy): if a comment provides value, draft the full final text (including signature), get approval, and iterate on wording until accepted. If no comment is warranted (routine bug fix, no caveats, no tester guidance, no rollout notes), say so explicitly in one sentence and skip the step instead of going silent.

### Pull requests

Opening a pull request is **out of scope by default** — stop after push. Target projects differ in review process (some merge integration branches manually rather than a PR per issue), so do not create a PR automatically.

Propose a PR only when there is a clear signal that the target repo expects one — the user asks, or the project's docs/branch conventions make PR-per-issue the norm. Even then, draft the PR title and body and get explicit approval before creating it, the same as for a commit or comment.

## Attachment Handling

Inspect images and files when they affect implementation, reproduction, or validation. Do not ignore attachments that change the meaning of the task.

Prefer to summarize relevant attachment findings back into the working context instead of echoing raw file contents.
