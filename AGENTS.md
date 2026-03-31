# Repository Guidelines

## Project Structure & Module Organization
This repository is a Laravel 12 + Inertia/Vue application.
- `app/`: backend code (`Http/Controllers`, `Http/Requests`, `Models`, `Actions`, `Providers`).
- `routes/`: HTTP and API routes (`web.php`, `api.php`).
- `resources/js/`: frontend Vue code (`Pages`, `Components`, `Layouts`, `lib`).
- `resources/css/app.css`: Tailwind base styles and design tokens.
- `database/`: migrations, factories, and seeders.
- `tests/Unit` and `tests/Feature`: PHPUnit test suites.
- `public/build`: compiled assets; do not edit manually.

## Build, Test, and Development Commands
- `composer setup`: install PHP/Node dependencies, copy env, generate app key, run migrations, build frontend.
- `composer dev`: run local stack (Laravel server, queue listener, logs via pail, Vite dev server).
- `npm run dev`: run only Vite for frontend development.
- `npm run build`: production frontend build.
- `composer test`: clear config and run test suite (`php artisan test`).
- `php artisan migrate --seed`: apply schema changes with seed data when needed.

## Coding Style & Naming Conventions
- Follow `.editorconfig`: UTF-8, LF, 4 spaces (2 spaces for YAML).
- Use strict typing in PHP files (`declare(strict_types=1);`) and typed method signatures.
- Keep controllers thin; put validation in Form Requests and business logic in `app/Actions`.
- Naming patterns: `StoreXRequest`/`UpdateXRequest`, singular model names (`Customer`, `Order`), PascalCase Vue components/pages.
- Prefer reusable UI components and Tailwind tokens from CSS variables (avoid hardcoded palette classes when a token exists).
- Run `vendor/bin/pint` before opening a PR.

## Testing Guidelines
- Framework: PHPUnit through Laravel’s test runner.
- Place fast logic tests in `tests/Unit`; HTTP/auth/permission/database flows in `tests/Feature`.
- Test files must end with `Test.php`.
- Default test DB is in-memory SQLite (configured in `phpunit.xml`).
- Add or update tests for new routes, request validation rules, and authorization checks.

## Commit & Pull Request Guidelines
Git history is not available in this workspace snapshot (`.git` missing), so follow this convention:
- Commit format: `type(scope): short imperative summary` (example: `feat(customers): add alias validation`).
- Keep commits focused to one logical change.
- PRs should include: purpose, key files changed, migration/env impacts, and test evidence (`composer test`).
- Include screenshots for UI changes under `resources/js/Pages/*`.

## Security & Configuration Tips
- Never commit secrets; keep `.env` local and update `.env.example` for new variables.
- Enforce authorization in requests/policies and use DB transactions for multi-step writes.
- Use eager loading for relations and queues for heavy background work.
