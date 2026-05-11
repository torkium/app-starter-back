# starter_back

Starter backend Symfony DDD centré sur `User`, sans notion de `Profile`.

## Principes

- Architecture par domaine : `Domain`, `Application`, `Infrastructure`, `Presentation`
- Auth JWT stateless
- Outbox transactionnelle
- Ports et adapters pour mail, Stripe et realtime
- Docker comme mode d'exécution par défaut
