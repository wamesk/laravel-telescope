<?php

namespace Wame\LaravelTelescope\Storage;

use DateTimeInterface;
use Illuminate\Database\DetectsConcurrencyErrors;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\Storage\EntryQueryOptions;
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

    public function get($type, EntryQueryOptions $options)
    {
        return EntryModel::on($this->connection)
            ->withTelescopeOptions($type, $options)
            ->take($options->limit)
            ->orderByDesc('sequence')
            ->get()->reject(function ($entry) {
                return ! is_array($entry->content);
            })->map(function ($entry) {
                return new EntryResult(
                    $entry->uuid,
                    $entry->sequence,
                    $entry->batch_id,
                    $entry->type,
                    $entry->family_hash,
                    $entry->content,
                    $entry->created_at,
                    []
                );
            })->values();
    }
}
