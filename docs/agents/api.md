# External HTTP API

Read this before adding, changing or removing an `/api/v1` endpoint, or touching
`lpm-core/modules/api/**`. Split out of `AGENTS.md`; the rules are unchanged.

- External HTTP API (`/api/v1`): lives in `lpm-core/modules/api/`. Requests dispatch through `ApiRouter` to controllers extending `ApiControllerBase` (e.g. `ApiProjectController`, `ApiIssueController`); each `dispatch(array $path)` matches on HTTP method + path segments and returns `ApiResponse::success($data[, $code])` / `ApiResponse::error($msg, $code)`. Auth is API-key only (`LPMExternalApi::run()` requires `isApiKeyAuth()`); the authenticated user is available via `$this->user()` and the engine singleton. Shape payloads through `ApiPayloadSerializer`, not ad-hoc arrays. **When you add/change/remove an endpoint, update ALL THREE API docs in the same change — they are easy to forget and drift out of date: `docs/api/README.md` (full reference), `llms.txt` (the terse agent-facing endpoint list), and `ai/skills/lightning-pm-issue/references/api.md` (the issue skill's API reference).**
