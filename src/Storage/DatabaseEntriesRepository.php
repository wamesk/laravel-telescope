<?php

namespace Wame\LaravelTelescope\Storage;

use Closure;
use DateTimeInterface;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\EntryType;
use Throwable;

class DatabaseEntriesRepository extends \Laravel\Telescope\Storage\DatabaseEntriesRepository
{
    use DetectsConcurrencyErrors;

    /**
     * How many times the prune is attempted before a lock conflict is rethrown.
     */
    public const PRUNE_ATTEMPTS = 3;

    /**
     * Pause between two prune attempts, in milliseconds.
     */
    public const PRUNE_RETRY_SLEEP_MS = 30000;

    /**
     * Database drivers that know the "innodb_lock_wait_timeout" session variable.
     */
    public const LOCK_WAIT_DRIVERS = ['mysql', 'mariadb'];

    /**
     * Store the given entries, waiting only briefly for a row lock.
     *
     * Telescope stores the entries while the application terminates: the response is
     * already sent, but the PHP worker stays busy until the store finishes. With the
     * server default of 50 seconds, a lock held elsewhere keeps the worker that long
     * for entries that are lost anyway once the store fails.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Telescope\IncomingEntry>  $entries
     * @return void
     */
    public function store(Collection $entries)
    {
        if ($entries->isEmpty()) {
            return;
        }

        $this->withLockWaitTimeout(fn () => parent::store($entries));
    }

    /**
     * Prune the stale entries, retrying when the database reports a lock conflict.
     *
     * A lock wait timeout or a deadlock means another transaction (a backup, a
     * maintenance job, a concurrent write) holds the rows for a while; the prune
     * waits and tries again instead of failing the whole run. Every chunk Telescope
     * deletes is its own autocommit statement, so what an attempt already deleted
     * stays deleted and the next attempt continues with the rest. Any other error
     * is rethrown at once. The returned count covers the last attempt only.
     *
     * @param  bool  $keepExceptions
     * @return int
     */
    public function prune(DateTimeInterface $before, $keepExceptions)
    {
        return retry(
            static::PRUNE_ATTEMPTS,
            fn () => parent::prune($before, $keepExceptions),
            static::PRUNE_RETRY_SLEEP_MS,
            fn (Throwable $e): bool => $this->causedByConcurrencyError($e),
        );
    }

    /**
     * Store the given exception entries.
     *
     * Same as Telescope, except that the earlier occurrences are hidden from the
     * index through hideEarlierOccurrences().
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Telescope\IncomingEntry>  $exceptions
     * @return void
     */
    protected function storeExceptions(Collection $exceptions)
    {
        $exceptions->chunk($this->chunkSize)->each(function ($chunked) {
            $this->table('telescope_entries')->insert($chunked->map(function ($exception) {
                $occurrences = $this->countExceptionOccurences($exception);

                $this->hideEarlierOccurrences($exception->familyHash());

                return array_merge($exception->toArray(), [
                    'family_hash' => $exception->familyHash(),
                    'content' => json_encode(
                        array_merge($exception->content, ['occurrences' => $occurrences + 1]),
                        JSON_INVALID_UTF8_SUBSTITUTE
                    ),
                ]);
            })->toArray());
        });

        $this->storeTags($exceptions->pluck('tags', 'uuid'));
    }

    /**
     * Hide the earlier occurrences of an exception family from the index.
     *
     * Telescope does this with one UPDATE filtered by family_hash. That UPDATE locks
     * every stored occurrence of the family together with the gaps around them, not
     * just the one shown on the index. A family is the file and line an exception was
     * thrown at, so every QueryException of the application (all thrown at the same
     * line of Illuminate\Database\Connection) is a single family of thousands of rows.
     * Requests failing on the database at the same time then queue on those locks
     * until they give up with "1205 Lock wait timeout exceeded".
     *
     * Here the rows to hide are found by a plain, non-locking read and updated by
     * primary key, so only those rows are locked - normally the one occurrence shown
     * on the index. Two occurrences stored at the same moment may both stay shown;
     * the next occurrence of the family hides them both.
     */
    protected function hideEarlierOccurrences(string $familyHash): void
    {
        $sequences = $this->table('telescope_entries')
            ->where('type', EntryType::EXCEPTION)
            ->where('should_display_on_index', true)
            ->where('family_hash', $familyHash)
            ->pluck('sequence');

        if ($sequences->isEmpty()) {
            return;
        }

        $this->table('telescope_entries')
            ->whereIn('sequence', $sequences->all())
            ->where('should_display_on_index', true)
            ->update(['should_display_on_index' => false]);
    }

    /**
     * Run the callback with the configured InnoDB lock wait on the Telescope connection.
     *
     * The session value is set back to the server default afterwards, also when the
     * callback throws, so long-running processes (queue workers, the prune) keep the
     * default for every other query on the connection. Drivers without the variable
     * and a disabled setting run the callback unchanged.
     */
    protected function withLockWaitTimeout(Closure $callback): mixed
    {
        $seconds = (int) config('wame-telescope.store_lock_wait_timeout');
        $connection = DB::connection($this->connection);

        if ($seconds < 1 || ! in_array($connection->getDriverName(), static::LOCK_WAIT_DRIVERS, true)) {
            return $callback();
        }

        $connection->statement('set session innodb_lock_wait_timeout = ' . $seconds);

        try {
            return $callback();
        } finally {
            $connection->statement('set session innodb_lock_wait_timeout = default');
        }
    }
}
