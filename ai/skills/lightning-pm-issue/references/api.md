# Lightning PM Issue API Reference

Use this reference when exact endpoint names, auth headers, or payload shapes matter.

## Base Rules

- Use the HTTP API under `/api/v1`.
- Use API access instead of scraping HTML whenever the API is available.
- Reuse the same auth headers for protected attachment downloads.
- Treat Lightning PM as the source of truth for issue context.
- Treat a pasted issue link as implementation input by default, not as a read-only inspection request, unless the user explicitly asks only for analysis.

## Authentication

Send one of these headers:

```http
Authorization: Bearer <LIGHTNING_PM_API_KEY>
```

or:

```http
X-LPM-API-Key: <LIGHTNING_PM_API_KEY>
```

## Core Issue Workflow

Resolve the pasted issue URL:

```http
GET /api/v1/issues/resolve?url=<ISSUE_URL>
```

Read the issue by id when needed:

```http
GET /api/v1/issues/{issueId}
```

Use the payload to inspect:

- issue title
- issue description
- comments
- images
- attached files
- project id
- issue id
- action URLs or repository hints if present

## Repository and Branch Workflow

List repositories for the project:

```http
GET /api/v1/projects/{projectId}/repositories
```

List branches for a repository:

```http
GET /api/v1/projects/{projectId}/repositories/{repositoryId}/branches
```

Create a feature branch for the issue:

```http
POST /api/v1/issues/{issueId}/branches
Content-Type: application/json

{
  "name": "374-fix-duplicate-submit",
  "repositoryId": 123,
  "parentBranch": "develop"
}
```

Notes:

- Prefer `develop` as the default parent branch unless the task or repository clearly requires another base.
- Pass a concise branch name without a `feature/` prefix unless the project convention clearly requires one.
- Ask the user for repository choice only when it cannot be inferred safely.

## Comment Workflow

Add a comment only when it contains useful human-facing information:

```http
POST /api/v1/issues/{issueId}/comments
Content-Type: application/json

{
  "text": "Проверьте сценарий повторной оплаты с двойным кликом. Исправление не меняет серверную валидацию, только блокирует повторный клиентский запрос."
}
```

Good comment content:

- tester instructions
- important implementation caveats
- limitations
- assumptions not obvious from the issue
- rollout or recheck notes

Do not post routine progress comments such as:

- branch created
- started implementation
- work in progress
