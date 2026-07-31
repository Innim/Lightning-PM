# .dev/bin — dev helper scripts

Small local helpers for working with the app in Docker. Run them from anywhere
in the repo; each reads its settings from `.dev/docker-env/.env` and
`lpm-config.inc.php` at runtime, so no secrets are stored here.

| Script            | What it does |
|-------------------|--------------|
| `lpm`             | Runs common commands inside the PHP 7.3 container (`composer`, `lint`, `php`, `exec`, `shell`) so they can be approved once and don't hit host PHP. |
| `test-mr-comment` | End-to-end test for the GitLab merge_request "open" webhook: fires a crafted webhook at `/api/gitlab/`, prints the auto-generated MR-link comment, and leaves it on the issue so you can view it in the UI. Repeatable — each run first clears the previous one, so it never accumulates. `--clean` removes the comment afterwards, `--issue <id>` targets a specific linked issue. |

See the header comment at the top of each script for full usage.
