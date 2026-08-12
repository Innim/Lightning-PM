# Deploy

Deployment runs from GitLab CI (`ci/.gitlab-ci.yml`) through the Python workers
in `ci/`. Each job connects to its target host over SSH and, in order:

1. clones the repository into a temporary directory under `DEPLOY_UPLOAD_PATH`;
2. checks out the job's branch;
3. deletes the parts that never ship (`.dev`, `ci`, `docs`, `ai`, `_private`,
   `README.md`, `AGENTS.md`, `CLAUDE.md`, `lpm-config.inc.template.php`, `.git`);
4. copies the rest over `DEPLOY_APP_PATH`;
5. removes the temporary directory;
6. applies DB migrations.

`lpm-config.inc.php` is not in the repository and is never overwritten — it
lives on the host.

Any step exiting non-zero aborts the deploy and reports the failure to Slack.

## Environments

| Branch | Environment | App path | Migrations |
|---|---|---|---|
| `master` | prod | `/srv/docker/task/task.innim.ru/http/` | inside `lightning-pm-prod` |
| `pipe/wip` | wip | `/srv/docker/task/wip.task.innim.ru/http/` | inside `lightning-pm-wip` |
| `pipe/test` | test | `/var/www/` | disabled |

Host credentials come from GitLab CI variables (`PROD_HOST`, `PROD_USER`,
`PROD_TASK_PASS` and the equivalents per environment) — never from the
repository.

## Migration settings

| Variable | Default | Effect |
|---|---|---|
| `DEPLOY_RUN_MIGRATIONS` | on | `0` / `false` / `no` skips the migration step |
| `DEPLOY_MIGRATE_CMD` | `php lpm-cli/migrate.php apply` | the command, run from the app directory |

The default assumes a PHP CLI on the deploy host. Prod and wip run the app in a
container and have no host PHP, so both point the command at the container
instead; `DEPLOY_APP_PATH` is mounted at `/var/www/html` inside it.

See [db-migrations.md](db-migrations.md) for what the migrations themselves do
and how to recover from a failed one.

## Host prerequisites

### Docker access for the deploy user

Where migrations run via `docker exec`, the SSH user in `DEPLOY_USER` must be
able to reach the Docker daemon. Without it the deploy fails at the migration
step with `permission denied while trying to connect to the Docker daemon
socket`.

Check as that user:

```bash
docker ps
```

If it fails, add the user to the `docker` group (on the target host, as root):

```bash
usermod -aG docker <deploy-user>
```

The user must reconnect for the new group to apply — group membership is
resolved at login, so an existing SSH session keeps the old set.

Note that membership in `docker` is equivalent to root on that host: the daemon
runs as root and any user who can talk to it can start a container that mounts
the host filesystem. If that is not acceptable, grant one specific command
through sudo instead and put that in `DEPLOY_MIGRATE_CMD`:

```
# /etc/sudoers.d/lpm-migrate
<deploy-user> ALL=(root) NOPASSWD: /usr/bin/docker exec lightning-pm-prod php /var/www/html/lpm-cli/migrate.php apply
```

```yaml
DEPLOY_MIGRATE_CMD: 'sudo docker exec lightning-pm-prod php /var/www/html/lpm-cli/migrate.php apply'
```

The sudoers line must match the command exactly, so it has to be updated
alongside `DEPLOY_MIGRATE_CMD` if either the container name or the path changes.

### Writable directories

`lpm-files/` must be writable by the user PHP runs as (recursively), and
`_private/logs/` must exist and be writable if logging is enabled.

## Deploying without CI

The same steps by hand, from the app directory on the host:

```bash
git -C <tmp> clone <repo> && git -C <tmp> checkout <branch>
cp -r <tmp>/. <app-path>
docker exec <container> php /var/www/html/lpm-cli/migrate.php apply
```

Check the result with `migrate status` — exit code `2` means something is still
pending.
