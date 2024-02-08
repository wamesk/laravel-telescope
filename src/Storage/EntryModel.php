<?php

namespace Wame\LaravelTelescope\Storage;

use Laravel\Telescope\Storage\EntryQueryOptions;

class EntryModel extends \Laravel\Telescope\Storage\EntryModel
{
    /** {@inheritDoc} */
    protected function whereTag($query, EntryQueryOptions $options)
    {
        $query->when($options->tag, function ($query, $tag) {
            $tags = collect(explode(',', $tag))->map(fn ($tag) => trim($tag));

            if ($tags->isEmpty()) {
                return $query;
            }

            foreach ($tags as $index => $tag) {
                if ($index === 0) {
                    $query->where('content->uri', 'like', '%' . $tag . '%');
                } else {
                    $query->orWhere('content->uri', 'like', '%' . $tag . '%');
                }
            }

            return $query;
        });

        return $this;
    }
}
