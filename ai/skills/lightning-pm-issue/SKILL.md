---
name: lightning-pm-issue
description: Implement work described in a Lightning PM issue by using its HTTP API as the source of truth. Use when the user provides a Lightning PM issue link or asks the agent to start work from a Lightning PM issue - read the issue, comments, and attachments, choose a repository and base branch, create an issue branch, and then carry out the implementation in the local codebase without scraping HTML.
---

# Lightning PM Issue

## Overview

Use Lightning PM as the source of truth for task context. Use the API, not HTML scraping, whenever API access is available.

Treat a pasted Lightning PM issue link as an instruction to prepare and perform the implementation work described in that issue, not just to inspect it.

Load [references/api.md](references/api.md) when exact endpoints, request bodies, or auth details matter.

## Workflow

1. Ask for `LIGHTNING_PM_API_KEY` if it is not already available in the environment or task context.
2. Resolve the pasted issue URL with `GET /api/v1/issues/resolve?url=<ISSUE_URL>`.
3. Read the returned issue payload carefully:
   - title and description
   - comments
   - images
   - attached files
   - project and issue ids
   - action URLs or repository hints when present
4. Fetch `GET /api/v1/issues/{issueId}` if the resolve payload looks partial, stale, or omits fields you need.
5. Download protected file URLs with the same auth headers when attachments matter for implementation or review.
6. Infer what change needs to be implemented in the local codebase from the issue description, comments, and attachments. Use Lightning PM as the source of truth when local assumptions conflict with issue context.
7. Infer the repository and base branch. Prefer `develop` unless the issue, repository structure, or branch list clearly indicates another parent.
8. Ask the user for repository choice only when it cannot be inferred safely.
9. Create the issue branch through `POST /api/v1/issues/{issueId}/branches` when implementation work is needed.
10. Implement the described change in the local repository, using the issue context to guide scope, edge cases, and validation.
11. Add a comment only when it provides value for humans, not as a progress log.

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

Prefer the bearer header by default. Reuse the same auth when downloading protected file URLs returned by the API.

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

Prefer a human-readable slug that includes the issue number or a short task cue when appropriate.

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

Keep the comment short, concrete, and action-oriented.

**ONLY** post a comment, when user already accepted implementation or review work.

## Attachment Handling

Inspect images and files when they affect implementation, reproduction, or validation. Do not ignore attachments that change the meaning of the task.

Prefer to summarize relevant attachment findings back into the working context instead of echoing raw file contents.
