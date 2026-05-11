# starter_back

Production-minded Symfony 8 starter backend built around DDD, ports and adapters, and a single `User` aggregate as the main application actor.

This repo is designed to be reusable on its own, or as part of the full starter trio with:
- `starter_front`: the companion Next.js frontend starter
- `starter_infra`: the companion Docker/CI-CD/operations starter

## Purpose

Use `starter_back` when you want a backend foundation that already handles the application plumbing so you can focus on business domains:

- authentication and account lifecycle
- transactional email flows
- realtime events
- billing foundations
- media upload foundations
- async processing
- observability-ready runtime conventions

The starter intentionally contains no dating-specific concepts, no `Profile`, and no product-specific business workflows.

## What Is Included

- Symfony 8
- PHP 8.4 runtime
- Doctrine ORM 3 + DBAL 4 + Doctrine Migrations 4
- JWT authentication with refresh tokens
- registration, login, logout, forgot/reset password, verify email, change email
- `GET /api/account/me`
- transactional outbox persisted in database
- ports/adapters for mail, Stripe, storage, and realtime publishing
- Mercure-ready realtime integration
- direct media upload flow with short-lived upload token + authenticated retrieval
- Nelmio API documentation on `/api/doc`
- reusable functional test base (`ApiTestCase`, factories, starter flows)
- local Docker setup with MySQL, Mercure, and Mailpit
- Make targets for local lifecycle, tests, and operations

## Prerequisites

- Docker Engine with Docker Compose support
- GNU Make
- OpenSSL for local JWT key generation during `make init`

## Related Starters

- `starter_front`
  Use it if you also want a ready-made Next.js frontend already wired for SSR auth, API proxying, runtime config, PWA, and realtime.

- `starter_infra`
  Use it if you want the full deployment/orchestration layer with Docker Compose, Nginx, Mercure, workers, scheduler, observability, and GitHub Actions.

Typical combinations:
- `starter_back` alone: bring your own frontend and infrastructure
- `starter_back` + `starter_front`: application layer only, with your own infra
- `starter_back` + `starter_front` + `starter_infra`: full starter platform

## Quick Start

```bash
make init
make up
make migrate
```

To turn this starter into a named project repository, run:

```bash
./scripts/init-project.sh \
  --project-name my-app \
  --back-repo my-app-back \
  --front-repo my-app-front \
  --infra-repo my-app-infra
```

Local API:

```text
http://localhost:8080/api
```

`make init` creates:
- `.env` from `.env.example` if missing
- `config/jwt/private.pem`
- `config/jwt/public.pem`

JWT keys are never generated automatically at Docker runtime. In remote environments, provide stable keys through the runtime environment expected by the image.

## Local Validation

```bash
make build
make test
```

`make test` uses the dedicated test Compose override so it does not need to reuse the same published ports as another local stack.

## Useful Commands

```bash
make ps
make config
make restart
make health
make logs
make logs-workers
make sh
make outbox-consume
```

## Runtime Notes

- Messenger Doctrine transports are enabled by default with `auto_setup=1`
- `make messenger-setup` is available if you want to force transport initialization explicitly
- local media storage stays outside the default public tree and is served through authenticated endpoints

## Suggested Workflow With The Other Starters

If you use the full trio:

1. Initialize `starter_back`
2. Initialize `starter_front`
3. Initialize `starter_infra`
4. Start the full stack from `starter_infra`

For full-stack setup and deployment bootstrap, see the related documentation in `starter_infra`.
