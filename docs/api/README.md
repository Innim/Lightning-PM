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

## Current user

```http
GET /api/v1/me
```

Answers with the authenticated user: the common user payload described below plus `email`.

## Common payload conventions

### Dates

Every date in every payload is ISO-8601, in the same shape it is accepted in:

- a moment in time carries the time and the offset — `"2026-07-15T12:34:56+03:00"`;
- a field that means a calendar day and has no time carries only the day — `"2026-07-15"`. `completeDate` of an issue is the one such field, and it round-trips: the value returned by a read is exactly what a write accepts;
- a date that is not set is `null`, never `0` or an empty string.

### Users

Every user — an issue author, a comment author, an issue member, tester, or master, and `GET /api/v1/me` — comes in one shape:

```json
{
  "id": 60,
  "name": "Ivan Petrov",
  "nick": "petrov",
  "firstName": "Ivan",
  "lastName": "Petrov",
  "avatarUrl": "https://example.com/avatar.jpg",
  "url": "https://example.com/user/60"
}
```

`name` is the display name Lightning PM composes from the parts, so there is no need to assemble it. Issue members carry one extra field, `sp` — the share of the issue estimate assigned to that member.

## Listing projects

List projects available to the authenticated user:

```http
GET /api/v1/projects
```

Add `?archive=1` to list archived projects instead of active ones. Each item has the shape `{id, uid, name, url, scrum}`. Moderators receive every project; other users receive only the projects they are members of.

## Listing project issues

List issues of a project:

```http
GET /api/v1/projects/{projectId}/issues
```

Query parameters (all optional):

- `status` — comma-separated list of `inWork`, `test`, `completed` (or the numeric codes `0`, `1`, `2`). `all` or an omitted parameter means any status.
- `type` — comma-separated list of `develop`, `bug`, `support` (or `0`, `1`, `2`).
- `label` — comma-separated list of labels; an issue must have **all** of them. Matching ignores case and covers only the `[label]` prefixes of the issue name, so a label cannot itself contain a comma.
- `search` — substring of the issue name, or the beginning of `idInProject`.
- `limit` — page size, `50` by default, `200` maximum.
- `offset` — number of issues to skip, `0` by default.

An unknown `status` or `type` value is rejected with `400`.

Issues come in the same order as in the web UI: issues in test first, then in work, then completed. The response is:

```json
{
  "project": {"id": 70, "uid": "demo", "name": "Demo", "url": "...", "scrum": true},
  "issues": [
    {
      "id": 25355,
      "idInProject": 2797,
      "name": "[api][ui] Payment retry duplicates the request",
      "url": "https://example.com/project/demo/issue/2797",
      "type": 0,
      "status": 1,
      "substatus": "passedTest",
      "priority": 78,
      "hours": 2,
      "hoursUnit": "storyPoints",
      "labels": ["api", "ui"],
      "commentsCount": 19,
      "isOnBoard": true,
      "boardColumn": "testing",
      "createDate": "2025-07-17T14:30:17+03:00",
      "modifiedDate": "2025-08-22T08:29:06+03:00",
      "completeDate": null,
      "completedDate": null,
      "author": {"id": 60, "name": "Ivan Petrov", "nick": "petrov", "firstName": "Ivan", "lastName": "Petrov", "avatarUrl": "...", "url": "..."}
    }
  ],
  "paging": {"limit": 50, "offset": 0, "total": 2607}
}
```

`priority` is the value shown in the web UI, an integer `1..100`. Items carry no description, comments, or attachments — request `GET /api/v1/issues/{issueId}` for the full issue.

### Estimate

`hours` is the issue estimate and `hoursUnit` says what the number means, exactly as the web UI labels it: `storyPoints` in a scrum project, `hours` in any other one. The unit follows the project, not the issue, so every issue of one project reports the same `hoursUnit`.

### Status and substatus

`status` is the coarse state of the issue — `0` in work, `1` waiting for test, `2` completed. `substatus` refines it the way the web UI badge does, and is `null` when there is nothing to refine:

- `backlog` — in work, not on the scrum board;
- `todo` — in work, on the board in the *TO DO* column;
- `inProgress` — in work, on the board past *TO DO*;
- `underTesting` — waiting for test and a tester is checking it right now;
- `passedTest` — waiting for test and already marked as having passed it.

An issue being checked reports `underTesting` even if it passed a test earlier: the new check outranks the old result.

A project without a scrum board has no board columns to refine "in work" with, so its issues in work report `substatus: null`.

`isOnBoard` and `boardColumn` report the board position itself: `boardColumn` is one of `todo`, `inProgress`, `testing`, `done`, or `null` for an issue that is not on the board.

## Listing project labels

List labels (tags) available in a project:

```http
GET /api/v1/projects/{projectId}/labels
```

The response is:

```json
{
  "project": {"id": 70, "uid": "demo", "name": "Demo", "url": "...", "scrum": true},
  "labels": [
    {"id": 176, "label": "api", "uses": 1759, "totalUses": 1869, "isCommon": false}
  ]
}
```

Labels are sorted by popularity in this project: `uses` counts how many times the label was used here, `totalUses` counts uses across all projects. A label with `isCommon: true` is shared by all projects, otherwise it belongs to this one. Use `label` from the list as the `label` filter of the issues endpoint or as an `[label]` prefix when creating an issue.

## Reading the scrum board

List the issues placed on the scrum board of a project, grouped by board columns:

```http
GET /api/v1/projects/{projectId}/board
```

The project must be a scrum one, otherwise the request is rejected with `400`. The response is:

```json
{
  "project": {"id": 70, "uid": "demo", "name": "Demo", "url": "...", "scrum": true},
  "columns": [
    {
      "state": 1,
      "key": "todo",
      "name": "TO DO",
      "issues": [
        {
          "id": 25240,
          "idInProject": 933,
          "name": "[api] Operations list glitches while a new one is added",
          "url": "https://example.com/project/demo/issue/933",
          "type": 0,
          "status": 0,
          "substatus": "todo",
          "priority": 70,
          "hours": 0.5,
          "hoursUnit": "storyPoints",
          "labels": ["api"],
          "commentsCount": 4,
          "isOnBoard": true,
          "boardColumn": "todo",
          "createDate": "2025-06-27T13:51:04+03:00",
          "modifiedDate": null,
          "completeDate": null,
          "completedDate": null,
          "author": {"id": 60, "name": "Ivan Petrov", "nick": "petrov", "firstName": "Ivan", "lastName": "Petrov", "avatarUrl": "...", "url": "..."},
          "stickerState": 1,
          "addedToBoard": "2025-07-29T11:21:37+03:00"
        }
      ]
    },
    {"state": 2, "key": "inProgress", "name": "В работе", "issues": []},
    {"state": 3, "key": "testing", "name": "Тестируется", "issues": []},
    {"state": 4, "key": "done", "name": "Готово", "issues": []}
  ]
}
```

All four columns are always present and come in board order: `todo`, `inProgress`, `testing`, `done`. A column carries its sticker `state` code, a machine-readable `key`, and the `name` shown in the web UI; an empty column has an empty `issues` list.

Issues inside a column come in the same order as on the board. An item is the short issue payload of the issues endpoint plus two board fields: `stickerState` (equals the column `state`) and `addedToBoard` (the moment the issue was put on the board). Issues in the backlog are not on the board and never appear here — use `GET /api/v1/projects/{projectId}/issues` for the whole project.

## Moving an issue on the scrum board

Put an issue on the board or move it to another column:

```http
PUT /api/v1/issues/{issueId}/board
Content-Type: application/json

{
  "column": "inProgress"
}
```

- `column` (optional) — `todo`, `inProgress`, `testing` or `done`, the same column keys the board endpoint returns. An unknown value is rejected with `400`.
- Without `column` the issue goes to the column matching its status, exactly like the *«На доску»* button in the web UI: an issue in work lands in `todo`, an issue in test in `testing`, a completed one in `done`.
- With `column` the issue is placed in that column even if it was not on the board before.

Moving an issue to `testing` puts it on test, moving it to `done` completes it, and moving an issue that waits for test back to `todo` or `inProgress` reopens it — the same status changes the board does in the web UI.

Take an issue off the board (it returns to the backlog, its status does not change):

```http
DELETE /api/v1/issues/{issueId}/board
```

Both requests answer with the updated issue payload, the same shape as `GET /api/v1/issues/{issueId}`. A non-scrum project is rejected with `400`. When the project requires labels, an issue without any label cannot be moved out of the backlog and the request is rejected with `400`.

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

The full issue payload of `GET /api/v1/issues/{issueId}` — the same payload `resolve`, `POST /api/v1/issues` and both board endpoints answer with — is the short payload above plus:

- `desc` — the issue description as Markdown source;
- `members`, `testers`, `masters` — users assigned to the issue, in the common user shape;
- `images` — screenshots attached to the issue, `{imgId, source, preview}`;
- `files` — files attached to the issue, see [Attachments](#attachments);
- `linked` — issues linked to this one, each in the short payload plus `desc` and `isBaseLinked`;
- `project` — the project of the issue, `{id, uid, name, url, scrum}`;
- `comments` — every comment of the issue, `{id, text, createdAt, author, type, meta, files, url}`;
- `actions` — API URLs for the operations available on this issue.

3. List repositories for the project:

```http
GET /api/v1/projects/{projectId}/repositories
```

Each repository is `{id, name, path, url, lastActivity}`, where `lastActivity` is the moment of the last activity in GitLab (`null` when GitLab reports none).

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

## Attachments

Attachments live in two places of the issue payload: `files` holds the files attached to the issue itself, and `comments[].files` holds the files attached to that comment. Both use the same item shape, so one parser covers them:

```json
{
  "fileId": 29,
  "uid": "5VbGulWzO8XAZSrpiep7LOx2ZqZ5Ymph",
  "name": "screenshot.jpg",
  "mimeType": "image/jpeg",
  "size": 21761,
  "sizeFormatted": "21.3 Кб",
  "created": "2026-06-07T18:37:40+03:00",
  "url": "https://example.com/file/5VbGulWzO8XAZSrpiep7LOx2ZqZ5Ymph",
  "requiresAuthentication": true
}
```

An image attached to a comment arrives here as a regular file with an image `mimeType`; the separate `images` collection of the issue payload never contains comment attachments. `requiresAuthentication: true` means `url` needs the same auth header as any other API request.

## Issue guidelines

Read how issues are expected to be written in a project, before creating one:

```http
GET /api/v1/projects/{projectId}/issue-guidelines
```

The response is:

```json
{
  "project": {"id": 70, "uid": "demo", "name": "Demo", "url": "...", "scrum": true},
  "descriptionTemplate": "### Проблема\n\n\n\n### Что сделать\n\n",
  "guidelines": "- название — одна короткая строка по сути задачи...",
  "naming": {
    "requireTitle": true,
    "requireLabels": false,
    "example": "[api] Кнопка оплаты дублирует запрос"
  }
}
```

- `descriptionTemplate` — the empty description skeleton the web UI inserts with its *«Шаблон описания»* button. Fill its sections instead of inventing your own structure.
- `guidelines` — the team's issue-writing rules as free-form text. Follow them when composing `name`, `desc` and `type`; they are the same rules the built-in AI draft follows.
- `naming.requireTitle` — the name must contain a title besides its `[label]` prefixes (always `true`).
- `naming.requireLabels` — the project requires at least one `[label]` prefix in the name; pick labels from `GET /api/v1/projects/{projectId}/labels`.
- `naming.example` — a name in the expected shape.

Both texts are an app-wide setting an administrator can change, so read them at issue-creation time rather than caching them. They are returned per project because the naming rules already differ between projects and the texts may later be overridden per project too.

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
  "priority": 80,
  "hours": 2,
  "completeDate": "2026-07-15",
  "board": "todo"
}
```

Fields:

- `projectId` (required) — project `id` or `uid`; the authenticated user must have access to it.
- `name` (required) — issue title. Labels are the `[label]` prefixes of the name (`[api][ui] Title`), so the name must contain a title besides them. When the project has *«Задачи должны иметь теги»* enabled, the name must also start with at least one label; otherwise the request is rejected with `400`.
- `desc` (optional) — issue description, up to 60000 characters. Write it to the project's [issue guidelines](#issue-guidelines) — the format is a team convention, not a free choice.
- `type` (optional, default `0`) — `0` develop, `1` bug, `2` support.
- `priority` (optional, default `50` — normal) — integer `1..100`, the same value the web UI shows for the issue; anything outside the range is rejected with `400`. Earlier the API used the internal scale `0..99`, one less than the displayed value; see the changelog for the release that switched it.
- `hours` (optional, default `0`) — the estimate, in the unit the project uses (see [Estimate](#estimate)); only `0.5` is accepted as a fraction, other values are treated as integers.
- `completeDate` (optional) — target date in `YYYY-MM-DD` format, the same shape it is returned in.
- `board` (optional, default `false`) — put the new issue straight on the scrum board: `true` places it in the column matching its status, a column key (`todo`, `inProgress`, `testing`, `done`) places it in that column. A non-scrum project or an unknown column is rejected with `400` and no issue is created.

The response returns the created issue payload (same shape as `GET /api/v1/issues/{issueId}`) with HTTP status `201`. Read the global `id` and `idInProject` from it for later requests. The issue is created without members and testers; assign those through the web UI if needed.

An issue URL of this Lightning PM instance written in `desc` or in a comment text automatically links the two issues, the same as in the web UI.

## Scope of v1

- List projects available to the user.
- List issues of a project with filters and paging.
- List labels of a project with their popularity.
- Read the scrum board of a project with its issues grouped by columns.
- Put an issue on the scrum board, move it between columns, or take it off the board.
- Read issue details by URL or issue id.
- Read comments, images, and files of the issue and of its comments.
- List repositories and branches available to the user in GitLab integration.
- Read the issue-writing guidelines of a project.
- Create an issue in a project.
- Create a branch for an issue.
- Add a comment to an issue.
