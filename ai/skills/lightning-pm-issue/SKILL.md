---
name: lightning-pm-issue
description: Implement work described in a Lightning PM issue by using its HTTP API as the source of truth. Use when the user provides a Lightning PM issue link or asks the agent to start work from a Lightning PM issue - read the issue, comments, and attachments, choose a repository and base branch, create an issue branch, and then carry out the implementation in the local codebase without scraping HTML.
---

# Lightning PM Issue

## Overview

Use Lightning PM as the source of truth for task context. Use the API, not HTML scraping, whenever API access is available.

Treat a pasted Lightning PM issue link as an instruction to prepare and perform the implementation work described in that issue, not just to inspect it.

Load [references/api.md](references/api.md) when exact endpoints, request bodies, or auth details matter.

Prefer the bundled helper script `scripts/lpm-api.sh` over raw `curl` so API calls can be approved once via a single command prefix.

## Workflow

1. Ask for `LIGHTNING_PM_API_KEY` if it is not already available in the environment or task context.
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
13. Implement the described change in the local repository, using the issue context to guide scope, edge cases, and validation.
14. Draft the exact comment text and get explicit user approval before posting any issue comment.
15. Add a comment only when it provides value for humans, not as a progress log.

## Implementation Expectation

After reading the issue, continue into implementation unless the user explicitly asks only for analysis, triage, or clarification.

Do not stop after:

- resolving the URL
- summarizing the task
- choosing a repository
- creating the branch

Use the issue context to drive the actual code changes and only ask follow-up questions when a blocking ambiguity cannot be resolved safely from the issue, attachments, repository structure, or local code.

## Authentication

Send either:

- `Authorization: Bearer <LIGHTNING_PM_API_KEY>`
- `X-LPM-API-Key: <LIGHTNING_PM_API_KEY>`

Prefer `X-LPM-API-Key` for this skill and reuse the same auth when downloading protected file URLs returned by the API.

For the helper script:

- set `LIGHTNING_PM_API_KEY`
- call `bash ai/skills/lightning-pm-issue/scripts/lpm-api.sh <ROOT_URL> ...`

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

## API Requests

Prefer the bundled helper script for all Lightning PM requests:

```bash
bash ai/skills/lightning-pm-issue/scripts/lpm-api.sh 'https://pm.example.com/project/demo/issue/891' GET '/api/v1/issues/resolve?url=https://pm.example.com/project/demo/issue/891'
```

```bash
bash ai/skills/lightning-pm-issue/scripts/lpm-api.sh 'https://pm.example.com/project/demo/issue/891' POST /api/v1/issues/43210/branches '{"name":"891.inner-store-payment-method","repositoryId":12,"parentBranch":"develop"}'
```

The script derives the Lightning PM origin from the passed root or issue URL and always sends `X-LPM-API-Key`, which makes approval simpler than repeated direct `curl` invocations.

Important id rule:

- call `resolve` once at the start and keep both `id` and `idInProject`
- use only global `id` in `/api/v1/issues/{issueId}/...` endpoints
- use `idInProject` only for display, human-facing references, branch naming, and matching the issue URL

## Comment Policy

Never post routine status comments such as:

- branch created
- implementation started
- working on this
- ready for review, unless accompanied by concrete tester guidance or a meaningful caveat

Post a comment only when it helps humans later. Good examples:

- tester instructions
- important implementation caveats
- known limitations
- assumptions that are not obvious from the issue
- rollout or recheck notes

Comment text may use Markdown when it improves readability.

Keep the comment short, concrete, and action-oriented.

Always append a signature that makes it clear the comment was posted by the agent on the user's behalf. Include the current agent name in the signature. Default signature template:

```text
[AI-assisted comment by <agent-name>, approved by user]
```

Replace `<agent-name>` with the actual agent name before showing the draft to the user.

Before posting, show the exact final comment text to the user, including the signature, and wait for approval. Do not post a paraphrased or modified version after approval unless the user approves the updated text too.

**ONLY** post a comment, when user already accepted implementation or review work.

## Attachment Handling

Inspect images and files when they affect implementation, reproduction, or validation. Do not ignore attachments that change the meaning of the task.

Prefer to summarize relevant attachment findings back into the working context instead of echoing raw file contents.
