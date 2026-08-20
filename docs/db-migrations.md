# DB migrations

Schema changes are applied by migrations: one PHP file per change, in
`lpm-core/modules/db/migrations/`, applied in filename order and recorded in the
`lpm_db_migrations` table.

Everything before version 0.27.0 lives in `.dev/db/legacy/` and is frozen — see
the README there if you are upgrading an install older than 0.26.0.

## Creating a migration

```bash
php lpm-cli/migrate.php create add_project_ai_summary
# -> lpm-core/modules/db/migrations/20260811093012_add_project_ai_summary.php
```

The generated file returns an anonymous class:

```php
<?php
/**
 * Признак того, что для задач проекта доступна ИИ-сводка обсуждения.
 */
return new class extends DbMigration {
    public function up()
    {
        $this->exec("ALTER TABLE `{$this->t(LPMTables::PROJECTS)}`
            ADD `aiSummary` tinyint(1) NOT NULL DEFAULT '0'
            COMMENT 'Для задач проекта доступна ИИ-сводка' AFTER `scrum`");
    }

    public function down()
    {
        $this->exec("ALTER TABLE `{$this->t(LPMTables::PROJECTS)}` DROP `aiSummary`");
    }
};
```

Available in a migration:

| | |
|---|---|
| `exec($sql)` | run one statement; throws with the statement text on error |
| `execFile($name)` | run a whole `.sql` file sitting next to the migration |
| `t($table)` | table name with this install's prefix — pass an `LPMTables` constant |
| `tableExists($table)` | whether a table exists (name including prefix) |
| `columnExists($table, $column)` | whether a column exists — guard an `ADD`/`DROP COLUMN` with it so the migration survives a rerun |

Never hardcode `lpm_` — use `t(LPMTables::X)`, the prefix is configurable.

## Applying

```bash
php lpm-cli/migrate.php status              # what is applied and what is not
php lpm-cli/migrate.php apply --dry-run     # what apply would do, changes nothing
php lpm-cli/migrate.php apply               # apply everything pending
php lpm-cli/migrate.php rollback --step=2   # roll back the last N (default 1)
php lpm-cli/migrate.php baseline            # mark all pending as applied, running nothing
```

In local development, `.dev/bin/lpm migrate <args>` runs the same commands inside
the container against PHP 7.3.

Exit codes: `0` success, `1` failure, `2` there are pending migrations (`status`
only, so it can gate a CI step).

Long `ALTER`s belong here rather than in the admin UI: the CLI has no PHP time
limit and no web-server timeout, and its output lands in the deploy log.

## From the admin UI

`Настройки` has a **База данных** card showing every migration and its state,
with an *Применить миграции* button when something is pending. It runs the same
`apply` as the CLI, records the admin's user id in the journal, and reports the
failing migration inline.

Rollback is deliberately not exposed there — it is destructive and belongs on
the CLI. The same goes for anything long-running: the request has no PHP time
limit, but the web server's own timeout still applies.

## On deploy

The CI deploy runs `apply` over SSH after the code is copied, so a release
carries its own schema change. A non-zero exit aborts the deploy and reports it
to Slack through the usual failure path.

Two environment variables control it:

| | |
|---|---|
| `DEPLOY_RUN_MIGRATIONS` | `0`/`false`/`no` skips the step; anything else (default) runs it |
| `DEPLOY_MIGRATE_CMD` | the command itself, run from the app directory; default `php lpm-cli/migrate.php apply` |

The default assumes a PHP CLI on the deploy host. Where the app runs in a
container, point `DEPLOY_MIGRATE_CMD` at it instead, e.g.
`docker exec <container> php /var/www/html/lpm-cli/migrate.php apply` — which
requires the deploy user to have Docker access. Per-environment settings and
host prerequisites are in [deploy.md](deploy.md).

Because the code is copied before migrations run, there is a window where new
code sees the old schema. Prefer additive changes: add a column in one release,
start depending on it in the next.

## What happens on first run

**Empty database** — every migration runs, starting with the initial schema,
which creates all tables from scratch. Nothing else is needed to install.

**Database already at 0.26.0 or newer** — the schema exists already, so on the
first run every migration up to `DbMigrator::BASELINE` is recorded as applied
*without being executed*, and only newer ones actually run. Upgrading across
several versions at once is therefore safe: migrations released after the
cutover are not skipped.

**Database older than 0.26.0** — bring it up to 0.26.0 from
`.dev/db/legacy/changes-log.sql` first, then run migrations.

## Rules

- **One statement per `exec()`.** For many statements, put them in a `.sql` file
  next to the migration and use `execFile()` — the file goes to the server as a
  batch, so quoting, comments and semicolons inside strings are handled by MySQL,
  not by us.
- **No `DELIMITER`, stored procedures or triggers.** `DELIMITER` is a directive
  of the `mysql` client, not of the server, so it cannot be sent over the wire.
- **No transactions.** MySQL executes DDL outside transactions, so a migration
  that fails halfway leaves the schema partially changed. Write migrations so a
  re-run after a fix is safe (`IF NOT EXISTS`, `INSERT IGNORE`, and so on).
- **Never edit a migration that has been applied anywhere.** Add a new one
  instead. `status` reports files whose checksum no longer matches what was
  applied — for a migration with a `.sql` companion the checksum covers both
  files.
- **`down()` is optional.** Without it the migration is irreversible and
  `rollback` refuses to touch it. The initial schema has no `down()` on purpose.
- Migrations are applied in filename order, and the timestamp prefix comes from
  the moment `create` ran — so two branches merged in any order still apply the
  union of their migrations, each exactly once.

## The journal

`lpm_db_migrations` holds one row per migration: `status` (`running` / `done` /
`failed`), the `checksum` at the time it was applied, `baseline` (recorded
without being executed), the `error` of the last failed attempt, `execTime`,
`userId` (0 for CLI) and `appliedAt`.

The row is written *before* the migration runs and updated afterwards, so an
interrupted run stays visible as `running` instead of disappearing.

## When something goes wrong

**A migration failed.** Everything after it is left alone. `status` shows the
error and which statement failed. Fix the cause — usually the migration itself —
and run `apply` again; the failed migration is retried. Statements that already
succeeded stay applied, so the migration has to tolerate a re-run.

**Status shows `применение прервано`.** The process died mid-migration
(timeout, lost connection). The schema is partially changed and nobody knows how
far it got — check the table by hand, then either re-run `apply` or mark it with
`baseline` if the change is in fact complete.

**`миграции уже выполняются другим процессом`.** Another `apply` holds the lock
— a parallel deploy, or an admin who pressed the button. Wait and retry;
`status` works regardless.

**Journal rows without files.** Reported by `status`. Someone deleted or renamed
an applied migration; there is nothing left to roll back. Harmless, but it means
the repository no longer describes how that database got its schema.
