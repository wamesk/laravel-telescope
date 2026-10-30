<?php

namespace Wame\LaravelTelescope\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Storage\EntryQueryOptions;
use Wame\LaravelTelescope\Storage\SearchEntriesRepository;

class RequestsController extends \Laravel\Telescope\Http\Controllers\RequestsController
{
    public function index(Request $request, EntriesRepository $storage)
    {
        $entries = (new SearchEntriesRepository(
            config('telescope.storage.database.connection'),
            config('telescope.storage.database.chunk')
        ))->get($this->entryType(), EntryQueryOptions::fromRequest($request));

        return response()->json([
            'entries' => $entries,
            'status' => $this->status(),
        ]);
    }
}
