# Laravel Telescope Improve

## Instalation

```shell
composer require wamesk/laravel-telescope
```

- No setup is required.
- Middleware is added via ServiceProvider in the package.


## Search
Adds fulltext search in path, content.
Add tags to all requests.

| Key      | .env                    | Default | Search without tag | Example                                                     |
|----------|-------------------------|---------|:------------------:|-------------------------------------------------------------|
| /        |                         |         |         ✓          | `/api/v1`                                                   |
| Api      | TELESCOPE_TAG_API       | false   |         ×          | `Api:GET /api/v1/users`                                     |
| Code     | TELESCOPE_TAG_CODE      | true    |         ✓          | `Code:1.2.3`                                                |
| Content  |                         |         |         ✓          | `Content:ip_address":"127.0.0.1"`                           |
| Date     | TELESCOPE_TAG_DATE      | false   |         ✓          | `Date:2023-01-01`                                           |
| DateTime | TELESCOPE_TAG_DATE_TIME | false   |         ✓          | `DateTime:2023-01-01 11:22:33`                              |
| Email    | TELESCOPE_TAG_EMAIL     | true    |         ×          | `Email:john.doe@example.com` or `Email:none`                |
| Errors   | TELESCOPE_TAG_ERROR     | true    |         ×          | `Errors:true`                                               |
| From     |                         |         |         ✓          | `From:2024-01-01`, `From:12:34`, `From:2024-01-01 12:34`... |
| Hour     | TELESCOPE_TAG_HOUR      | false   |         ✓          | `Hour:0` - `Hour:23`                                        |
| Method   | TELESCOPE_TAG_METHOD    | false   |         ✓          | `Method:GET`, `Method:POST`, `Method:UPDATE`...             |
| Month    | TELESCOPE_TAG_MONTH     | false   |         ✓          | `Month:1` - `Month:12`                                      |
| Path     | TELESCOPE_TAG_PATH      | true    |         ✓          | `Path:/api/v1`                                              |
| Status   | TELESCOPE_TAG_STATUS    | false   |         ✓          | `Status:200`, `Status:400`, `Status:500`...                 |
| Time     | TELESCOPE_TAG_TIME      | false   |         ✓          | `Time:11:22`                                                |
| To       |                         |         |         ✓          | `To:2024-01-01`, `To:12:34`, `To:2024-01-01 12:34`...       |
| Url      | TELESCOPE_TAG_URL       | false   |         ✓          | `Url:/nova-api/user-groups?page=1&perPage=25`               |

- To disable fulltext search, set `TELESCOPE_SEARCH=false` in `.env`.
- You can set the tag saving settings by adding a parameter to the `.env` e.g. `TELESCOPE_TAG_CODE=false` 
- It is possible to search according to these keys even if saving tags is not allowed.
- By turning off the tag, you will only lose a quick filter.
- Keys can be written in case-insensitive
- Several keys separated by a comma must all match, e.g. `Email:john.doe@example.com, Code:1.2.3`.
- A date, time, hour or month that cannot be parsed is ignored instead of failing the search.
- The search route (`POST {telescope.path}/telescope-api/requests`) uses Telescope's domain, path and
  middleware and always Telescope's `Authorize` middleware, so it is never reachable anonymously,
  not even while Telescope is disabled.

### Tags
- Only requests whose URI contains `/api/v` are tagged.
- Tagging never changes the response: file downloads, streamed responses and payloads that cannot
  be read by key are tagged without `Code` / `Errors`, and any failure while tagging is reported
  instead of failing the request.

## Storing entries
The package replaces Telescope's database repository (`EntriesRepository` and `PrunableRepository`
bindings, database driver only).

### Exceptions
Telescope shows only the latest occurrence of an exception family (the file and line the exception
was thrown at) on the index. To hide the earlier ones it runs one
`UPDATE … WHERE type = 'exception' AND family_hash = ?`, which locks every stored occurrence of the
family and the gaps around them. Every `QueryException` of an application is thrown at the same line
of `Illuminate\Database\Connection`, so all database errors form one family that can hold thousands of rows,
and requests failing on the database at the same time end with `1205 Lock wait timeout exceeded`
while storing their entries.

The package finds the occurrences to hide with a plain, non-locking read and updates them by primary
key, so the store locks only the occurrence shown on the index. Two occurrences stored at the same
moment may both stay shown until the next occurrence of the family hides them.

### Lock wait
Telescope stores entries after the response was sent, but the PHP worker stays busy until the store
finishes. On MySQL / MariaDB the store waits at most 5 seconds for a row lock instead of the server
default (`innodb_lock_wait_timeout`, 50 seconds); the session value is set back afterwards.
The same limit applies to the entry updates (job status changes) Telescope writes right after the
store and from its `ProcessPendingUpdates` job.
```
TELESCOPE_STORE_LOCK_WAIT_TIMEOUT=5
```
Set it to `0` or `false` to keep the server default.

### Request search
The full-text request search runs through `SearchEntriesRepository`, so the other Telescope screens
keep Telescope's own tag filter.

## Data pruning
The package contains command to pruning data.
In `.env` it is possible to set the number of hours how much data to remember. (Default 48 hours)
```
TELESCOPE_PRUNE_HOURS=168
```
To cancel the command, set the value to `false`.
```
TELESCOPE_PRUNE_HOURS=false
```

The prune runs once a day at 03:30 (application timezone), away from midnight when
database backups and maintenance commonly hold locks. Change the time of day with:
```
TELESCOPE_PRUNE_AT=04:15
```
The value must be a `H:MM` / `HH:MM` time between `0:00` and `23:59`. Anything else
(`25:00`, `abc`) falls back to 03:30 and logs a warning on every console boot until
it is fixed, instead of breaking the cron expression of the whole schedule.

### How the prune deletes
Telescope prunes with `DELETE … WHERE created_at < ? ORDER BY sequence LIMIT 1000`. MySQL / MariaDB
run it as a range scan of the `created_at` index plus a filesort, so **every** chunk reads and locks
every stale entry, not only the 1000 it deletes. Measured on MariaDB 10.11 with 2.26 million stale
entries: one chunk held 4.5 million row locks for 15 seconds, so the whole prune would take hours
and blocks everyone touching those rows meanwhile.

The package reads the primary keys of each chunk with a plain, non-locking read of the `created_at`
index alone (in index order, continuing behind the previous chunk) and deletes the chunk by primary
key: about 1 400 row locks (the entries and their tags) and 0.04 seconds per chunk on the same data.
The prune is not recorded by Telescope.

### Lock conflicts
- The prune waits at most 10 seconds for a row lock (MySQL / MariaDB):
  ```
  TELESCOPE_PRUNE_LOCK_WAIT_TIMEOUT=10
  ```
  Set it to `0` or `false` to keep the server default.
- A chunk that hits a lock wait timeout (`1205`) or a deadlock (`1213`) is tried again after
  5 seconds, up to 3 attempts. A chunk still locked is skipped and deleted by the next run;
  after 3 skipped chunks in a row the run stops.
- Skipped entries are logged as one warning (deleted / skipped counts and the oldest open
  transactions of the server from `information_schema.INNODB_TRX` when the database user has
  the `PROCESS` privilege), never as an exception. Any other database error fails the command
  at once.

### Schedule safety
- The prune runs on a single server (`onOneServer()`) and never overlaps a previous run
  that is still deleting (`withoutOverlapping()`, lock released after 12 hours). Both use
  the application's default cache store, which every server must share.
- The prune runs in the background (`runInBackground()`), so a run that waits on locks
  never delays the other tasks due in the same minute.

### Config caching
The retention window and the time of day are read from `config('wame-telescope.prune_hours')`
and `config('wame-telescope.prune_at')`, not from `env()` at runtime. If the application runs
`php artisan config:cache`, Laravel stops loading the `.env` file altogether, so publish the
config into the application to make the values part of the cached configuration:
```shell
php artisan vendor:publish --tag=wame-telescope-config
```
Without the published file the merged package config is still evaluated at runtime and
`TELESCOPE_PRUNE_HOURS` silently falls back to the 48 hour default on a cached config.
