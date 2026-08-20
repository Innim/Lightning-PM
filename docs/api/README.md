# Lightning PM API

Lightning PM exposes version 1 of its authenticated API under `/api/v1`.

## Authentication

Create or revoke user API keys in `Profile -> API Keys`.

Use one of these headers:

```http
Authorization: Bearer lpm_u123_...
```

or:

```http
X-LPM-API-Key: lpm_u123_...
```

The same auth works for protected issue file URLs returned by the API.

## Listing projects

List projects available to the authenticated user:

```http
GET /api/v1/projects
```

Add `?archive=1` to list archived projects instead of active ones. Each item has the shape `{id, uid, name, url, scrum}`. Moderators receive every project; other users receive only the projects they are members of.

## Core workflow

1. Resolve a pasted issue URL:

```http
GET /api/v1/issues/resolve?url=https://example.com/project/demo/issue/123
```

Note on ids:

- the issue URL `/project/.../issue/123` contains `idInProject`, the project-local issue number
- API endpoints `/api/v1/issues/{issueId}/...` expect the global unique issue `id`
- save both values from `resolve` and use the global `id` for later `/issues/{issueId}/...` requests

Example:

- issue URL: `https://example.com/project/demo/issue/123`
- resolve response: `{"id":4567,"idInProject":123,...}`
- branch endpoint: `POST /api/v1/issues/4567/branches`

2. Read the issue description, comments, images, and files from the JSON response.
3. List repositories for the project:

```http
GET /api/v1/projects/{projectId}/repositories
```

4. List branches in the selected repository:

```http
GET /api/v1/projects/{projectId}/repositories/{repositoryId}/branches
```

5. Create a task branch:

```http
POST /api/v1/issues/{issueId}/branches
Content-Type: application/json

{
  "name": "374.ai-agent-friendly",
  "repositoryId": 123,
  "parentBranch": "develop"
}
```

6. Add a comment only when there is useful human-facing information to preserve in the task:

```http
POST /api/v1/issues/{issueId}/comments
Content-Type: application/json

{
  "text": "Готово к проверке. Проверьте сценарий оплаты повторной покупкой: исправлено дублирование запроса при двойном клике."
}
```

Do not post routine progress comments such as branch creation or "implementation started". Use comments for handoff details, tester instructions, limitations, or clarifications that are not obvious from the issue itself.

## Creating an issue

Create a new issue in a project:

```http
POST /api/v1/issues
Content-Type: application/json

{
  "projectId": "demo",
  "name": "Payment retry duplicates the request",
  "desc": "Double-clicking the pay button sends the purchase request twice.",
  "type": 1,
  "priority": 5,
  "hours": 2,
  "completeDate": "2026-07-15"
}
```

Fields:

- `projectId` (required) — project `id` or `uid`; the authenticated user must have access to it.
- `name` (required) — issue title. Labels are the `[label]` prefixes of the name (`[api][ui] Title`), so the name must contain a title besides them. When the project has *«Задачи должны иметь теги»* enabled, the name must also start with at least one label; otherwise the request is rejected with `400`.
- `desc` (optional) — issue description, up to 60000 characters.
- `type` (optional, default `0`) — `0` develop, `1` bug, `2` support.
- `priority` (optional, default `49` — normal) — integer clamped to `0..99`.
- `hours` (optional, default `0`) — story points estimate; only `0.5` is accepted as a fraction, other values are treated as integers.
- `completeDate` (optional) — target date in `YYYY-MM-DD` format.

The response returns the created issue payload (same shape as `GET /api/v1/issues/{issueId}`) with HTTP status `201`. Read the global `id` and `idInProject` from it for later requests. The issue is created without members, testers, or a scrum-board sticker; assign those through the web UI if needed.

## Scope of v1

- List projects available to the user.
- Read issue details by URL or issue id.
- Read comments, images, and files.
- List repositories and branches available to the user in GitLab integration.
- Create an issue in a project.
- Create a branch for an issue.
- Add a comment to an issue.
