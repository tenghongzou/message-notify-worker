# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A headless Symfony 7.1 console application (no HTTP layer) that runs a long-lived
worker consuming jobs from a **beanstalkd** queue and dispatching them as notifications
(currently LINE). It is a CLI-only Symfony app — there are no controllers or routes in use.

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
- `LINE_NOTIFY_ACCESS_TOKEN` — LINE Notify bearer token, read in `NotifyWorkerService`.

A running beanstalkd instance is required for the worker to do anything.

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
command. Calls `NotificationFactory` to obtain a notifier and sends. **Note: `exec()` is
currently a stub** — it creates the LINE notifier and sets the token but does not yet
parse the job payload or call `send()`. This is the main place to implement actual
dispatch logic.

**Notification subsystem** (`src/Service/Notification/`):
- `NotificationServiceInterface` — `setToken()` + `send(message, imageUrl?)`.
- `NotificationFactory` — `create(string $platform)` returns a notifier by name via a
  `match`; only `'line'` is supported, anything else throws.
- `LineNotification` — posts to the LINE Notify API using Symfony HttpClient.

To add a new notification platform: implement `NotificationServiceInterface`, inject it
into `NotificationFactory`, and add a `match` arm.

## Service wiring gotcha

`config/services.yaml` **excludes `src/Service/Notification/` from autoregistration**.
Despite this, `NotificationFactory` and `LineNotification` are still autowired because
they are pulled in as constructor dependencies of registered services — the exclude only
prevents them from being registered as standalone service entries. If you add a class in
that directory that must be a directly-fetchable service, you'll need an explicit
definition or to adjust the exclude list.
