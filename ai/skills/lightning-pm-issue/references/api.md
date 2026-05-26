# Lightning PM Issue API Reference

Use this reference when exact endpoint names, auth headers, or payload shapes matter.

## Base Rules

- Use the HTTP API under `/api/v1`.
- Use API access instead of scraping HTML whenever the API is available.
- Reuse the same auth headers for protected attachment downloads.
- Treat Lightning PM as the source of truth for issue context.
- Treat a pasted issue link as implementation input by default, not as a read-only inspection request, unless the user explicitly asks only for analysis.
- `idInProject` from the issue URL is not the same as the global `id` used by `/api/v1/issues/{issueId}/...` endpoints.

## Authentication

Set this environment variable before local shell calls when possible:

```bash
export LIGHTNING_PM_API_KEY='lpm_u123_...'
```

Send one of these headers:

```http
Authorization: Bearer <LIGHTNING_PM_API_KEY>
```

or:

```http
X-LPM-API-Key: <LIGHTNING_PM_API_KEY>
```

## Core Issue Workflow

Prefer the bundled helper script for local calls:

```bash
bash ~/path/to/skill/scripts/lpm-api.sh 'https://pm.example.com' GET '/api/v1/issues/resolve?url=https://pm.example.com/project/demo/issue/891'
```

Resolve the pasted issue URL:

```http
GET /api/v1/issues/resolve?url=<ISSUE_URL>
```

Save both ids from the resolve response once and reuse them:

- `id`: global unique issue id for `/api/v1/issues/{issueId}/...`
- `idInProject`: project-local issue number from `/project/.../issue/{idInProject}`

Example:

- issue URL: `https://pm.example.com/project/demo/issue/891`
- resolve response: `{"id":43210,"idInProject":891,...}`
- correct issue endpoint: `GET /api/v1/issues/43210`

Read the issue by id when needed:

```http
GET /api/v1/issues/{issueId}
```

Use the resolved global `id` as `{issueId}`, not the number from the issue URL.

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
  "name": "374.fix-duplicate-submit",
  "repositoryId": 123,
  "parentBranch": "develop"
}
```

If the URL is `/project/demo/issue/374`, that `374` is usually `idInProject`. The `{issueId}` in this endpoint must be the resolved global `id`, for example `/api/v1/issues/43210/branches`.

Notes:

- Prefer `develop` as the default parent branch unless the task or repository clearly requires another base.
- The backend prepends `feature/` automatically, so pass only the suffix in `name`.
- Preferred format: `{issueNumber}.{short-kebab-slug}`.
- Example: request name `891.inner-store-payment-method` becomes Git branch `feature/891.inner-store-payment-method`.
- Do not send names like `feature/891-inner-store-payment-method`.
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

Use the same resolved global `id` here as well. Do not substitute `idInProject` from the issue URL into `/api/v1/issues/{issueId}/comments`.

Good comment content:

- tester instructions
- important implementation caveats
- limitations
- assumptions not obvious from the issue
- rollout or recheck notes

Comment text may use Markdown when it improves readability.

Always append a signature that makes it clear the comment was posted by the agent on the user's behalf. Include the current agent name in the signature. Default signature template:

```text
[AI-assisted comment by <agent-name>, approved by user]
```

Replace `<agent-name>` with the actual agent name before showing the draft to the user.

Before posting, show the exact final comment text to the user, including the signature, and get explicit approval.

Do not post routine progress comments such as:

- branch created
- started implementation
- work in progress

## File Download

Attached files and images in the issue payload are served from protected URLs. Download a file using the URL from the payload:

```http
GET <file_url>
```

`<file_url>` comes from the issue payload (e.g. inside `images` or `attachments` fields). Save the result to `/tmp/` to keep it isolated from the working tree.

To save with a specific name, pass `--output` through the helper script:

```bash
bash ~/path/to/skill/scripts/lpm-api.sh 'https://pm.example.com' GET 'https://pm.example.com/lpm-files/protected/screenshot.png' -- --output /tmp/screenshot.png
```
