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

## Core workflow

1. Resolve a pasted issue URL:

```http
GET /api/v1/issues/resolve?url=https://example.com/project/demo/issue/123
```

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

## Scope of v1

- Read issue details by URL or issue id.
- Read comments, images, and files.
- List repositories and branches available to the user in GitLab integration.
- Create a branch for an issue.
- Add a comment to an issue.
