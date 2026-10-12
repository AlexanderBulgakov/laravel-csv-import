# CSV Import

**Original task (Ukrainian):**
>Завдання: зробити сторінку імпорту заявок (файл для імпорту надається).
>Задача рахується виконаною, якщо в базі запишуться всі рядки при стандартному max_execution_time=30
>Тестове завдання потрібно скинути на github (файли і структуру БД) і надати посилання.

![Task](screenshots/task.png)

## Environment

The project targets **Laravel 8** on purpose. The job description mentions working
with legacy code, so the task is solved with the constraints of an older stack
rather than the latest Laravel release: Laravel Mix instead of Vite, no PHP 8.1+
features, and MySQL 5.7.

| Component  | Version          | Notes                                              |
|------------|------------------|----------------------------------------------------|
| PHP        | 7.4 (FPM)        | `max_execution_time = 30` for web requests         |
| Laravel    | 8.x              |                                                    |
| MySQL      | 5.7              |                                                    |
| Valkey     | 8                | Redis-compatible fork; used for queue, cache, sessions |
| Node.js    | 18               | Builds assets with Laravel Mix                     |
| nginx      | alpine           |                                                    |

### Services (`docker-compose.yml`)

| Service        | Purpose                                                                 |
|----------------|-------------------------------------------------------------------------|
| `app`          | PHP-FPM application                                                     |
| `queue-worker` | Runs `php artisan queue:work`. `restart: unless-stopped` acts as the process supervisor: Docker brings the worker back if it exits on a job timeout |
| `nginx`        | Web server, <http://localhost> (port set by `APP_PORT`)                 |
| `db`           | MySQL 5.7                                                               |
| `valkey`       | Queue, cache and session storage                                        |
| `node`         | Installs npm packages and runs `npm run watch-poll`. The first start takes a few minutes |
| `adminer`      | Database UI at <http://localhost:8080> for inspecting `leads`, `imports` and `import_errors` |

### PHP limits

| Setting               | Web (FPM) | CLI (worker) |
|-----------------------|-----------|--------------|
| `max_execution_time`  | 30        | 0 (unlimited); jobs are limited by the worker `--timeout` instead |
| `upload_max_filesize` | 100M      | —            |
| `post_max_size`       | 100M      | —            |

Upload validation allows files up to 50 MB, below the PHP and nginx limits, so an
oversized file gets a normal validation error instead of a 413 response.

## Getting started

1. Copy the environment example:

   ```
   cp env.example .env
   ```

2. Build and start:

   ```
   docker compose up -d --build
   ```

   If `src/` is empty, the `app` container bootstraps a fresh Laravel project
   into it on first run (see `docker/php/entrypoint.sh`) and generates
   `APP_KEY`. If `src/` already has an app, it's reused as-is; `composer install`
   only runs when `vendor/` is missing.

3. Run the migrations:

   ```
   docker compose exec app php artisan migrate
   ```

4. Open <http://localhost> (port configurable via `APP_PORT` in `.env`).

5. Open /imports/create, upload a CSV, the queue-worker service processes it in the background; the page shows progress and a link to the error report

### Result on the provided file (100 000 rows)

| Outcome                           | Rows   | Where                         |
|-----------------------------------|--------|-------------------------------|
| Imported as leads                 | 98 976 | `leads`                       |
| Repeated `external_id` (merged)   | 202    | `leads` (updated, not duplicated) |
| Invalid, reported with line number| 822    | `import_errors` (with raw data)   |
| **Total**                         | **100 000** |                          |

## Generating a test file

The client's file is not included in the repository. To get a file in the same
format, use the `leads:generate-csv` command:

```
docker compose exec app php artisan leads:generate-csv
```

This writes 100 000 rows to `storage/app/generated/leads-100000.csv`.
Upload the file at `/imports/create` like any other CSV.

### Arguments and options

| Argument / option | Default | Description |
|-------------------|---------|-------------|
| `rows`            | `100000` | Number of data rows (the header is not counted) |
| `--invalid=`      | `1`     | Share of invalid rows, in percent (`1` = 1%) |
| `--duplicates=`   | `0`     | Share of rows that repeat an earlier `external_id`, in percent (`0.2` = 0.2%) |
| `--seed=`         | random  | Seed for reproducible output: the same seed and options produce an identical file |
| `--path=`         | `leads-{rows}.csv` | Output file name, relative to `storage/app/generated/` |

Example: 5 000 rows, 2% invalid, 0.5% duplicates, reproducible:

```
docker compose exec app php artisan leads:generate-csv 5000 --invalid=2 --duplicates=0.5 --seed=42
```

When it finishes, the command prints how many rows it generated, how many are
invalid and how many are duplicates, plus the time and memory it used.

### What the generated data contains

- Leads in the same format as the provided file: `external_id` like `LD-000001`,
  dates as `Y-m-d H:i:s`, budget with a space as the thousands separator
  (`23 700`), phone numbers like `380XXXXXXXXX`.
- Empty optional fields (first name, email, UTM campaign, budget, manager,
  comment) in roughly the same proportions as the provided file.
- Comments containing commas and quotes, so some fields are written in quotes.
- `next_contact_at` is always later than `created_at`, and empty for leads with
  status `won`, `lost` or `spam`.
- Invalid rows of several kinds: empty `external_id`, wrong date format,
  impossible date (`2025-13-45`), empty `last_name`, invalid email, negative or
  non-numeric budget, an extra column.
- Duplicates both within one import chunk and across chunks, to exercise
  deduplication and the upsert.

The data is generated with Faker and does not contain any data from the
client's file.

Invalid rows and duplicates are chosen at random row by row, so their final
counts are close to the requested percentages but not exact. A row can also
end up both invalid and a duplicate, so after an import the number of merged
duplicates can be slightly lower than the number the generator reports.

## Running tests

Tests use a separate `laravel_testing` database, so the test suite does not touch the regular `laravel` database.

Create the test database once:

```
docker compose exec db mysql -u root -p
```

Enter the MySQL root password specified in .env (MYSQL_ROOT_PASSWORD).

Then create the database and grant access to the MySQL user specified by
`DB_USERNAME` in `.env`.

Replace `laravel` in the `GRANT` statement with the value of `DB_USERNAME` from your `.env` file:

```
CREATE DATABASE laravel_testing;
GRANT ALL PRIVILEGES ON laravel_testing.* TO 'laravel'@'%';
FLUSH PRIVILEGES;
```

Then type `exit` and run the tests:

```
docker compose exec app php artisan test
```