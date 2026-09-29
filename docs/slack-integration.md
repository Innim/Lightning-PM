# Slack integration

The app posts issue notifications into a Slack channel and reads user avatars
from Slack profiles. It is optional: with no token configured, nothing is sent
and no Slack API call is made.

All notifications about one issue are grouped into a single Slack thread: the
first message opens it, and the app remembers that message so later
notifications reply to it.

## What the integration needs

A Slack app with a **bot token** (it starts with `xoxb-`) and these scopes:

| scope | why |
|---|---|
| `chat:write` | send the notifications |
| `chat:write.public` | "Send messages to channels your Slack app isn't a member of" — required to post into a public channel without adding the app to it; Slack requires `chat:write` alongside it |
| `users.profile:read` | read user avatars from Slack profiles |

`chat:write.public` is **bot-only**. A user token (`xoxp-`) cannot use it: Slack
answers `not_in_channel` — "Cannot post user messages to a channel they are not
in" — so with a user token notifications reach only the channels its owner has
joined. An install still on a user token should be moved to a bot token.

`incoming-webhook` is **not** needed: the app talks to the Web API
(`chat.postMessage`, `users.profile.get`) and never posts through a webhook URL.

## Private channels

`chat:write.public` covers **public** channels only — that is what the scope
means. There is no scope that lets an app write into a private channel it is not
a member of, so the app has to be invited into every private channel it should
post to, once per channel:

```
/invite @<app name>
```

Until then Slack refuses the message with `channel_not_found` — from outside, a
private channel is indistinguishable from one that does not exist.

The project settings page shows the app name to invite and has a **Проверить**
button that posts a test message into the channel and reports what came back, so
a moderator sees the problem while setting the channel up instead of noticing
that notifications never arrived.

## Issuing the token

1. Create an app at https://api.slack.com/apps for the workspace that will
   receive the notifications.
2. Open **OAuth & Permissions** in the app settings and add the three scopes
   above under the bot token scopes.
3. Install the app to the workspace from the same page and copy the bot token
   it issues — the one starting with `xoxb-`.

Re-installing the app is required after adding a scope: a token issued earlier
does not gain scopes retroactively.

## Where the token goes

Into `lpm-config.inc.php` on the host — the runtime config, which is not in the
repository and is never overwritten by a deploy (see [deploy.md](deploy.md)):

```php
define('SLACK_TOKEN', 'xoxb-...');
```

Notifications can be switched off without removing the token:

```php
define('SLACK_NOTIFICATION_ENABLED', false);
```

The switch stops the automatic notifications only. The **Проверить** button in
the project settings still posts its test message, so a channel can be set up
and verified before notifications are turned on; the result says plainly that
they are off.

Never commit the token. `lpm-config.inc.template.php` is a template and must
keep `SLACK_TOKEN` empty.

## Per-project channel

Notifications go to the channel set per project, in **Настройки проекта** →
*ID канала оповещений в Slack*. The field takes the channel **ID**, not its
name: in Slack, right-click the channel, choose *Copy link*, and take the last
part of the copied URL. A project with an empty field gets no notifications.

Changing the channel starts a fresh thread for every issue in the new channel;
the threads of the previous channel are remembered and reused if the project is
pointed back at it.

## When notifications do not arrive

A Slack failure never interrupts the action that triggered it — the issue is
still saved and the e-mail notification is still sent — so the only trace is the
log: `_private/logs/slack.error.log` (channel `slack`). Each entry carries the
channel ID and the error Slack returned.

| error | meaning |
|---|---|
| `not_in_channel` | the token may not post there: a user token whose owner has not joined the channel, or a bot missing `chat:write.public` |
| `channel_not_found` | the app was not invited into that private channel, or the value in the project settings is not a channel ID of this workspace |
| `missing_scope` | the token was issued before a scope was added — re-install the app |
| `invalid_auth`, `token_revoked` | the token is no longer valid and has to be re-issued |
