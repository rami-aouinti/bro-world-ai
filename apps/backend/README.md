# Symfony backend

This directory is reserved for the Symfony 7.4 + API Platform business application.

Symfony is the source of truth for planning, capacity, content versions, approval decisions and simulated publishing handoff. It must reject incomplete or stale approvals.

Bootstrap in the next implementation step with:

```bash
composer create-project symfony/skeleton:"7.4.*" .
composer require api symfony/orm-pack symfony/validator symfony/serializer-pack symfony/uid
composer require --dev symfony/maker-bundle symfony/test-pack
```
