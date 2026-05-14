# app-starter-back

My App backend Symfony DDD centré sur `User`.

## Principes

- Architecture par domaine : `Domain`, `Application`, `Infrastructure`, `Presentation`
- Auth JWT stateless
- Outbox transactionnelle
- Ports et adapters pour mail, Stripe et realtime
- Docker comme mode d'exécution par défaut
