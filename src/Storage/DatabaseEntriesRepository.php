<?php

namespace Wame\LaravelTelescope\Storage;

use Closure;
use DateTimeInterface;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Telescope;
use Throwable;

class DatabaseEntriesRepository extends \Laravel\Telescope\Storage\DatabaseEntriesRepository
{
    use DetectsConcurrencyErrors;

    /**
     * How many times the prune tries to delete one chunk before it skips the chunk.
     */
    public const PRUNE_ATTEMPTS = 3;

    /**
     * Pause between two attempts to delete the same chunk, in milliseconds.
     */
    public const PRUNE_RETRY_SLEEP_MS = 5000;

    /**
     * Chunks skipped in a row after which the prune stops and leaves the rest to the next run.
     */
    public const PRUNE_MAX_SKIPPED_CHUNKS = 3;

    /**
     * How many open transactions the warning about skipped chunks lists.
     */
    public const PRUNE_REPORTED_TRANSACTIONS = 5;

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

        $this->withLockWaitTimeout($this->storeLockWaitTimeout(), fn () => parent::store($entries));
    }

    /**
     * Apply the given entry updates (job status changes), waiting only briefly for a row lock.
     *
     * Telescope runs them right after store(), while the application terminates, and
     * the queued ProcessPendingUpdates job runs them again later - both with the same
     * short lock wait as store().
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Telescope\EntryUpdate>  $updates
     * @return \Illuminate\Support\Collection<int, \Laravel\Telescope\EntryUpdate>
     */
    public function update(Collection $updates)
    {
        if ($updates->isEmpty()) {
            return collect();
        }

        return $this->withLockWaitTimeout($this->storeLockWaitTimeout(), fn () => parent::update($updates));
    }

    /**
     * Prune the stale entries in short chunks deleted by primary key.
     *
     * Telescope deletes with "DELETE ... WHERE created_at < ? ORDER BY sequence LIMIT n".
     * MySQL / MariaDB run that statement as a range scan of the created_at index followed
     * by a filesort, so every chunk reads and locks every stale entry, not only the n it
     * deletes: with two million stale entries one chunk held 4.5 million row locks for
     * 15 seconds, and the prune ran for hours - long enough to outlive the overlap lock
     * of the schedule and to wait on the locks of its own previous run.
     *
     * Here the primary keys of a chunk are read by a plain, non-locking read of the
     * created_at index alone (see staleChunk()), and the chunk is deleted by primary key,
     * so a chunk locks only its own rows (and their tags) and takes milliseconds.
     *
     * A chunk that hits a lock wait timeout or a deadlock is tried again after a pause.
     * A chunk still locked after the last attempt is skipped and left to the next run;
     * after several skipped chunks in a row the run stops. Skipped entries end in one
     * warning naming the oldest open transactions, never in an exception. Any other
     * error is rethrown at once.
     *
     * Every row lock is waited for at most wame-telescope.prune_lock_wait_timeout seconds,
     * and the statements of the prune are not recorded by Telescope.
     *
     * @param  bool  $keepExceptions
     * @return int Number of deleted entries.
     */
    public function prune(DateTimeInterface $before, $keepExceptions)
    {
        return Telescope::withoutRecording(fn (): int => $this->withLockWaitTimeout(
            $this->pruneLockWaitTimeout(),
            fn (): int => $this->pruneInChunks($before, (bool) $keepExceptions),
        ));
    }

    /**
     * Delete the stale entries chunk by chunk, see prune().
     */
    protected function pruneInChunks(DateTimeInterface $before, bool $keepExceptions): int
    {
        $after = null;
        $deleted = 0;
        $skipped = 0;
        $skippedInRow = 0;
        $stopped = false;
        $transactions = null;

        while ([] !== ($chunk = $this->staleChunk($before, $keepExceptions, $after))) {
            $after = end($chunk);
            $sequences = array_map(fn (object $entry): int => (int) $entry->sequence, $chunk);

            try {
                $deleted += $this->deleteStaleChunk($sequences, $before);
                $skippedInRow = 0;
            } catch (Throwable $e) {
                if (! $this->causedByConcurrencyError($e)) {
                    throw $e;
                }

                $skipped += count($sequences);
                $transactions ??= $this->openTransactions();

                if (++$skippedInRow >= static::PRUNE_MAX_SKIPPED_CHUNKS) {
                    $stopped = true;

                    break;
                }
            }
        }

        if ($skipped > 0) {
            Log::warning('Telescope prune skipped entries locked by another transaction, the next run deletes them.', [
                'before' => $before->format('Y-m-d H:i:s'),
                'deleted' => $deleted,
                'skipped' => $skipped,
                'stopped' => $stopped,
                'lock_wait_timeout' => $this->pruneLockWaitTimeout(),
                'transactions' => $transactions,
            ]);
        }

        return $deleted;
    }

    /**
     * The next chunk of stale entries behind the given one, read without locking.
     *
     * Walks the created_at index in its own order (created_at, then the primary key the
     * index carries), so MySQL / MariaDB read only that index - never the stored content
     * and never the entries newer than the cut-off - and need no filesort. The walk goes
     * on behind the last chunk, so deleted or skipped entries are not read again.
     *
     * @return array<int, object{sequence: int|string, created_at: string}>
     */
    protected function staleChunk(DateTimeInterface $before, bool $keepExceptions, ?object $after): array
    {
        $query = $this->table('telescope_entries')
            ->select(['sequence', 'created_at'])
            ->where('created_at', '<', $before);

        if (null !== $after) {
            $query->where(function ($query) use ($after): void {
                $query->where('created_at', '>', $after->created_at)
                    ->orWhere(function ($query) use ($after): void {
                        $query->where('created_at', $after->created_at)->where('sequence', '>', $after->sequence);
                    });
            });
        }

        if ($keepExceptions) {
            $query->where('type', '!=', EntryType::EXCEPTION);
        }

        return $query->orderBy('created_at')
            ->orderBy('sequence')
            ->limit(max(1, (int) $this->chunkSize))
            ->get()
            ->all();
    }

    /**
     * Delete one chunk, trying again after a pause while another transaction locks its rows.
     *
     * @param  array<int, int>  $sequences
     */
    protected function deleteStaleChunk(array $sequences, DateTimeInterface $before): int
    {
        return retry(
            static::PRUNE_ATTEMPTS,
            fn (): int => $this->deleteEntries($sequences, $before),
            static::PRUNE_RETRY_SLEEP_MS,
            fn (Throwable $e): bool => $this->causedByConcurrencyError($e),
        );
    }

    /**
     * Delete the given stale entries by primary key; their tags follow by the foreign key.
     *
     * @param  array<int, int>  $sequences
     */
    protected function deleteEntries(array $sequences, DateTimeInterface $before): int
    {
        return $this->table('telescope_entries')
            ->whereIn('sequence', $sequences)
            ->where('created_at', '<', $before)
            ->delete();
    }

    /**
     * The oldest open transactions of the database server, to name what held the rows.
     *
     * Best effort: information_schema.INNODB_TRX exists on MySQL / MariaDB only and needs
     * the PROCESS privilege, so any failure returns its reason instead.
     *
     * The list covers every transaction of the server, other applications' databases
     * included, and goes to the application log. So it holds only what identifies the
     * session (thread id, schema, start, size) - never the statement text, which carries
     * literal values such as e-mails or tokens, nor the database user and client host.
     *
     * @return array<int, array<string, mixed>>|string
     */
    protected function openTransactions(): array|string
    {
        $connection = DB::connection($this->connection);

        if (! in_array($connection->getDriverName(), static::LOCK_WAIT_DRIVERS, true)) {
            return 'Not available on the ' . $connection->getDriverName() . ' driver.';
        }

        try {
            $rows = $connection->select(
                'select t.trx_id as trx_id, t.trx_state as state, t.trx_started as started,'
                . ' t.trx_mysql_thread_id as thread_id, t.trx_rows_locked as rows_locked,'
                . ' t.trx_rows_modified as rows_modified,'
                . ' p.db as db, p.command as command, p.time as seconds'
                . ' from information_schema.innodb_trx t'
                . ' left join information_schema.processlist p on p.id = t.trx_mysql_thread_id'
                . ' where t.trx_mysql_thread_id <> connection_id()'
                . ' order by t.trx_started'
                . ' limit ' . static::PRUNE_REPORTED_TRANSACTIONS
            );

            return array_map(fn (object $row): array => (array) $row, $rows);
        } catch (Throwable $e) {
            return 'Unavailable: ' . $e->getMessage();
        }
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
     * Seconds the store and the entry updates wait for a row lock, 0 for the server default.
     */
    protected function storeLockWaitTimeout(): int
    {
        return (int) config('wame-telescope.store_lock_wait_timeout');
    }

    /**
     * Seconds the prune waits for a row lock before it retries the chunk, 0 for the server default.
     */
    protected function pruneLockWaitTimeout(): int
    {
        return (int) config('wame-telescope.prune_lock_wait_timeout');
    }

    /**
     * Run the callback with the given InnoDB lock wait on the Telescope connection.
     *
     * The session value is set back to the server default afterwards, also when the
     * callback throws, so long-running processes (queue workers, the prune) keep the
     * default for every other query on the connection. Drivers without the variable
     * and a value below one second run the callback unchanged.
     */
    protected function withLockWaitTimeout(int $seconds, Closure $callback): mixed
    {
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
