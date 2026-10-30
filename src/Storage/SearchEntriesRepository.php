<?php

namespace Wame\LaravelTelescope\Storage;

use Laravel\Telescope\EntryResult;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * Entries repository of the package request search ("POST /telescope/telescope-api/requests").
 *
 * Lists the entries through the package EntryModel, which turns the tag filter into
 * the full-text search described in the README. Kept apart from DatabaseEntriesRepository,
 * which the container binds as Telescope's own repository, so the other Telescope
 * screens keep Telescope's tag filter.
 */
class SearchEntriesRepository extends DatabaseEntriesRepository
{
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
