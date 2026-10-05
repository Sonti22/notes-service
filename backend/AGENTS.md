# Notes API conventions

- Laravel 13, PHP 8.4+, SQLite. Vue lives in ../frontend.
- API routes are in routes/api.php; keep validation in Form Requests and responses in Resources.
- Run php artisan test and vendor/bin/pint --test after backend changes.
- Tests use an in-memory database. Never use the persistent application's database for tests.
- Keep public/docs/openapi.json consistent with API changes.
- Never commit .env, database files, dependencies or generated frontend bundles.
