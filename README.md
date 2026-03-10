## Laravel Index Advisor

[![Latest Version](https://img.shields.io/packagist/v/erencagliz/laravel-index-advisor.svg?style=flat-square)](https://packagist.org/packages/erencagliz/laravel-index-advisor)
[![Build Status](https://github.com/erencagliz/laravel-index-advisor/actions/workflows/index-advisor.yml/badge.svg)](https://github.com/erencagliz/laravel-index-advisor/actions)
[![License](https://img.shields.io/github/license/erencagliz/laravel-index-advisor.svg?style=flat-square)](LICENSE)

Laravel Index Advisor is a Laravel package that listens to your production queries and, based on recurring query patterns, produces **deterministic, explainable index recommendations** for missing or suboptimal indexes.

It is designed to be **safe to run in production**, with low overhead and clear, actionable output.

---

### What problem does it solve?

You already have tools like Telescope and Pulse that tell you **which queries are slow**.  
What they don’t tell you is: **“Which index should I create?”**

Laravel Index Advisor:

- Observes queries at runtime (via `QueryExecuted` events),
- Normalizes them and aggregates by **query shape** (fingerprint),
- Inspects existing indexes on the target tables,
- Uses heuristics + EXPLAIN hints to detect **missing or weak indexes**,
- Produces index suggestions with a **confidence score** and a clear **reason**.

---

### Features

- **Production-safe query observation**  
  - Hooks into Laravel’s `QueryExecuted` events.  
  - Supports sampling and minimum duration thresholds to keep overhead low.

- **Query normalization & fingerprinting**  
  - Rewrites literals to placeholders (`?`) and normalizes whitespace.  
  - Groups the same logical query shape under a single fingerprint across different bindings.

- **AST-based SQL shape analysis**  
  - Uses `php-sql-parser` under the hood.  
  - Extracts tables, where / join / group / order columns and limit.  
  - Detects subqueries and flags them conservatively.

- **Schema inspection** (Doctrine DBAL)  
  - Reads existing indexes for each table (columns, order, uniqueness).  
  - Detects similar or overlapping indexes to avoid noisy duplicates.

- **Rule-based suggestion engine with EXPLAIN hints**  
  - Classic index patterns:
    - Single-column equality filter (`where user_id = ?`)  
    - Multi-column equality (`where tenant_id = ? and status = ?`)  
    - Equality + sort (`... order by created_at desc`)  
    - Multi-tenant + soft delete (`tenant_id + deleted_at`)
  - Confidence score (0–100) uses:
    - Execution frequency,
    - Latency (avg / p95),
    - Existing indexes and similar indexes,
    - EXPLAIN signals (full scan, rows examined, filesort, temporary),
    - Parse confidence (AST success / subquery presence).

- **Workflow support**  
  - Ignore rules (by fingerprint, table or table+columns).  
  - Mark suggestions as accepted or dismissed with optional reasons.

- **Developer-friendly tooling**  
  - Artisan commands for reporting, suggesting, analyzing and cleaning.  
  - Facade/API to consume suggestions programmatically.  
  - CI workflow example and comprehensive README.

---

### Installation

```bash
composer require erencagliz/laravel-index-advisor

php artisan vendor:publish --tag=index-advisor-config
php artisan vendor:publish --tag=index-advisor-migrations
php artisan migrate
```

#### Requirements

- **Laravel**: 11, 12  
- **PHP**: 8.2+  
- **Databases**:
  - MySQL 8+ / MariaDB 10.6+  
  - PostgreSQL 14+  
  - SQLite (mainly for local/test usage)

---

### Quick start

1. Install and publish config + migrations (see above).
2. Ensure `INDEX_ADVISOR_ENABLED=true` (or `enabled => true` in `config/index-advisor.php`).
3. Let your application handle real traffic for a while.

Then, inspect the collected queries:

```bash
php artisan index-advisor:report
```

This shows the most frequent and slowest query shapes (CLI table + optional `--json`).

Get index suggestions:

```bash
php artisan index-advisor:suggest --min-score=60
```

Persist suggestions for later review:

```bash
php artisan index-advisor:suggest --persist
```

Generate a migration from a specific suggestion:

```bash
php artisan index-advisor:generate-migration --suggestion=1
```

This creates a migration file under `database/migrations` that adds the recommended index.

---

### Example CLI output

```bash
php artisan index-advisor:suggest --min-score=60
```

```text
+--------+------------------------------+-------+------------+---------------------------------------------+
| Table  | Columns                      | Type  | Confidence | Reason                                      |
+--------+------------------------------+-------+------------+---------------------------------------------+
| orders | tenant_id, status, created_at| index | 87         | Frequent equality filters followed by sort…|
+--------+------------------------------+-------+------------+---------------------------------------------+

Use --json for full machine-readable details or --persist to store suggestions.
```

---

### Workload report

To get a high-level view of which tables are most heavily queried over a recent time window:

```bash
php artisan index-advisor:workload --days=7
```

This prints a summary and a table of per-table executions and timings.  
Use `--json` to integrate with dashboards or CI tooling.

---

### Configuration

Main settings in `config/index-advisor.php`:

- **`enabled`**  
  Turn the entire package on or off.

- **`sample_rate`**  
  Float between 0.0–1.0. Controls what fraction of queries are sampled.  
  For production, `0.1`–`0.5` is usually sufficient.

- **`min_query_time_ms`**  
  Queries faster than this threshold are ignored. Helps reduce noise from cheap queries.

- **`min_executions`**  
  Minimum number of executions per fingerprint before it is considered for suggestions.  
  Very rare queries are ignored.

- **`retention_days`**  
  How long to keep historical aggregates. Used together with `index-advisor:flush`.

- **`store_raw_sql_sample`**  
  Defaults to `false`.  
  When `true`, stores a single raw SQL sample per fingerprint (be mindful of PII/compliance).

- **`ignore_connections` / `ignore_tables` / `ignore_paths`**  
  Connections, tables, and request paths to exclude from observation.

- **`explain.enabled`**  
  Enables EXPLAIN-based analysis of sampled queries.  
  When enabled, some EXPLAIN signals are included in `supporting_stats` and confidence scoring.

---

### How it works (high-level)

1. **Query observation**  
   The service provider wires `QueryWatcher` to Laravel’s `QueryExecuted` events.

2. **Filtering**  
   `QueryWatcher` checks:
   - `enabled`,
   - `sample_rate`,
   - `min_query_time_ms`,
   - `ignore_connections`, `ignore_tables`, `ignore_paths`
   to decide whether to record a query.

3. **Normalization & fingerprinting**  
   - Literals are rewritten to placeholders (`?`), whitespace is normalized.  
   - A deterministic fingerprint is generated from normalized SQL + connection + primary table.

4. **Aggregation**  
   - `index_advisor_queries` stores per-fingerprint statistics:
     - executions, total_time_ms, avg_time_ms, p95_time_ms, max_time_ms, first/last seen.

5. **Shape & schema analysis**  
   - AST-based parser extracts tables and column usage.  
   - Doctrine DBAL reads existing indexes for the relevant tables.

6. **Suggestion engine**  
   - Rule-based heuristics detect missing or suboptimal indexes for common patterns.  
   - Similar indexes reduce confidence to avoid noisy/duplicate suggestions.  
   - EXPLAIN is used (when enabled) to see whether the current plan does full scans, filesort, temporary tables, etc.

7. **Migration generation**  
   - For any selected suggestion, a Laravel migration stub is generated, including both:
     - `up` (add index),  
     - `down` (drop the same index).

---

### Noise management (ignore / accept / dismiss)

To keep long-term usage low-noise and focused:

- **Mark suggestions** as accepted or dismissed:

  ```bash
  php artisan index-advisor:mark 5 accepted
  php artisan index-advisor:mark 7 dismissed --reason="Handled manually"
  ```

- **Ignore patterns** you never want to see again:

  - Ignore by fingerprint:

    ```bash
    php artisan index-advisor:ignore --fingerprint=3f8b1d7a2c91 --reason="Legacy query"
    ```

  - Ignore an entire table:

    ```bash
    php artisan index-advisor:ignore --table=audits --reason="Log table"
    ```

  - Ignore a specific column pattern on a table:

    ```bash
    php artisan index-advisor:ignore --table=orders --columns=tenant_id,deleted_at --reason="Known pattern"
    ```

Ignored patterns are stored in the `index_advisor_ignores` table and are taken into account by the suggestion engine.

---

### Programmatic usage (Facade / service)

You can also access suggestions programmatically via the facade:

```php
use IndexAdvisor;

$suggestions = IndexAdvisor::suggest([
    'table' => 'orders',
    'min_score' => 70,
]);
```

Each suggestion is returned as an array with:

- table,
- columns,
- index_type,
- reason,
- confidence,
- fingerprint,
- supporting_stats (including optional EXPLAIN info),
- existing_similar_indexes,
- warnings.

This makes it easy to integrate with your own dashboards or tooling.

---

### CI integration (example)

An example GitHub Actions workflow is provided in `.github/workflows/index-advisor.yml`:

- Runs `composer install` and `vendor/bin/pest` on each push/PR.
- Ensures the package stays stable as you evolve it.

In host applications, you can additionally:

- Seed a realistic dataset,
- Hit a few critical endpoints/commands,
- Run `php artisan index-advisor:suggest --json` and attach the JSON to PRs as an artifact or PR comment.

---

### Tests

The package uses Pest + Orchestra Testbench.

```bash
composer install

vendor/bin/pest
```

Key coverage areas:

- Query normalizer and fingerprint determinism  
- AST-based SQL shape parser behavior  
- Suggestion engine heuristics:
  - Single-column equality
  - Multi-column equality + sort
  - Confidence reduction when similar indexes exist
- Service provider + config integration

---

### Contributing

When opening an issue, please include:

- Environment details (PHP, Laravel, DB versions)  
- A sample of the relevant query or queries  
- Expected vs. actual behavior

When sending a PR:

- Run the existing test suite  
- Add unit/feature tests for new behavior  
- Update the README where appropriate

Any feedback or contributions are very welcome and will make the package more useful for the community.