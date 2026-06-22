# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A headless Symfony 7.1 console application (no HTTP layer) that runs a long-lived
worker consuming jobs from a **beanstalkd** queue and dispatching them as notifications.
Supported platforms: **LINE Messaging API**, **Telegram**, and **ntfy** (selected per
job via the payload's `platform` field, default `line`). It is a CLI-only Symfony app —
there are no controllers or routes in use.

> Note: the original `LINE Notify` integration was removed — LINE Notify was officially
> terminated on 2025-03-31. `LineMessagingNotification` (the LINE Messaging API push
> endpoint) is its successor.

## Commands

```bash
# Run the worker (long-running; blocks reserving jobs from the queue)
php bin/console app:notify-worker      # alias: app:notify

# Clear the Symfony cache
php bin/console cache:clear

# Install dependencies
composer install
```

There is currently **no test suite** (the `tests/` dir does not exist yet, though
`composer.json` maps the `App\Tests\` PSR-4 namespace to it). If you add tests,
PHPUnit is not yet installed — add `symfony/test-pack` or `phpunit/phpunit` first.

## Required environment

Set in `.env.local` (gitignored, not committed):

- `BEANSTALKD_HOST` — beanstalkd server address (e.g. `localhost`). Wired into
  `PheanstalkService` via the `beanstalkd_host` parameter in `config/services.yaml`.

Per-platform credentials, injected into `NotifyWorkerService` via `#[Autowire('%env(...)%')]`
(empty defaults live in `.env`, so only the platforms you use need real values):
- `LINE_MESSAGING_CHANNEL_ACCESS_TOKEN` — LINE Messaging API channel access token.
- `TELEGRAM_BOT_TOKEN` — Telegram bot token (from @BotFather).
- `NTFY_SERVER_URL` — ntfy base URL (default `https://ntfy.sh`), injected into `NtfyNotification`.
- `NTFY_AUTH_TOKEN` — optional ntfy access token (empty for public topics).

A running beanstalkd instance is required for the worker to do anything.

## Job payload

Jobs are JSON. `NotifyWorkerService::exec()` reads:
- `platform` — `line` | `telegram` | `ntfy` (optional, defaults to `line`).
- `message` — text to send (required, must be non-empty).
- `target` — recipient: LINE user/group/room id, Telegram chat id, or ntfy topic.
- `image_url` — optional HTTPS image URL.
- `retry_count` / `fail_message` — managed by the command's retry protocol (see below).

## Architecture

The worker is a queue-consumer loop with a retry/dead-letter protocol layered on top
of beanstalkd. Understanding the job lifecycle requires reading these files together:

**`NotifyWorkerCommand`** (`src/Command/`) — the entry point and orchestrator. Its
`execute()` runs `runWorker()` in a loop until `GracefulShutdown` catches a signal.
Key constants define the queue protocol:
- `TUBE_NAME = 'notify-message'` — the tube it watches/consumes.
- `FAIL_TUBE_NAME = 'notify-message.fail.job'` — dead-letter tube.
- `MAX_RETRIES = 3` — after this many attempts a job is moved to the fail tube.

The retry mechanism is **manual, not beanstalkd's native release/bury**: on job failure,
`handleJobRetry()` deletes the original job and re-`put`s a new job with an incremented
`retry_count` field embedded in the JSON payload, at low priority with a delay. When
`retry_count >= MAX_RETRIES`, `handleJobMaxRetriesToFail()` copies the payload to the
fail tube and deletes it. So job state (retry count, fail message) lives in the JSON
payload, not in beanstalkd metadata.

**`PheanstalkService`** (`src/Service/`) — thin wrapper over the `pda/pheanstalk` client.
`watch()` always ignores the `default` tube so the worker only consumes `notify-message`.
All queue operations (reserve, delete, put, touch, stats) go through here.

**`NotifyWorkerService`** (`src/Service/`) — the per-job business logic invoked by the
command. `exec()` decodes the JSON payload, picks the `platform`, asks `NotificationFactory`
for the matching notifier, then fluently `setToken()->setTarget()->send()`. The token is
resolved per platform from the env-injected credentials (`resolveToken()`); the `target`
comes from the payload.

**Notification subsystem** (`src/Service/Notification/`):
- `NotificationServiceInterface` — `setToken()` + `setTarget()` + `send(message, imageUrl?)`,
  all fluent (`setToken`/`setTarget` return `static`).
- `NotificationFactory` — `create(string $platform)` returns a notifier by name via a
  `match` (`line` / `telegram` / `ntfy`); anything else throws.
- `LineMessagingNotification` — LINE Messaging API push (`POST /v2/bot/message/push`),
  bearer channel access token, image sent as a second message object.
- `TelegramNotification` — Telegram Bot API (`sendMessage`, or `sendPhoto` when an image
  is present); token in the URL, `target` is the chat id.
- `NtfyNotification` — publishes to `{NTFY_SERVER_URL}/{topic}`; optional bearer token,
  image attached via the `Attach` header.

To add a new notification platform: implement `NotificationServiceInterface`, inject it
into `NotificationFactory`, add a `match` arm, and (if it needs a credential) inject a new
env token into `NotifyWorkerService::resolveToken()`.

## Service wiring

All classes under `src/` autoregister/autowire normally (`config/services.yaml`).
`src/Service/Notification/` is **not** excluded — the concrete notifiers and the factory
are registered services; the `NotificationServiceInterface` is skipped automatically (it
is not instantiable). The notifiers are stateful (`setToken`/`setTarget` mutate the shared
instance), which is safe here because the worker is single-threaded and processes one job
at a time.
