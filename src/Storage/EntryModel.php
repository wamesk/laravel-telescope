<?php

namespace Wame\LaravelTelescope\Storage;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Laravel\Telescope\Storage\EntryQueryOptions;

class EntryModel extends \Laravel\Telescope\Storage\EntryModel
{
    /** {@inheritDoc} */
    protected function whereTag($query, EntryQueryOptions $options)
    {
        $query->when($options->tag, function ($query, $tags) {
            $values = $this->prepareValues($tags);

            if (!$values) {
                return $query;
            }

            // Path
            if (isset($values['path'])) {
                foreach ($values['path'] as $index => $tag) {
                    if ($index === 0) {
                        $query->where('content->uri', 'like', '%' . $tag . '%');
                    } else {
                        $query->orWhere('content->uri', 'like', '%' . $tag . '%');
                    }
                }
            }

            // Date from
            if (isset($values['from'])) {
                $query->where('created_at', '>=', $values['from']);
            }

            // Date to
            if (isset($values['to'])) {
                $query->where('created_at', '<=', $values['to']);
            }

            // Tags
            if (isset($values['tags']) || isset($values['like'])) {
                $query->whereIn('uuid', function ($query) use ($values) {
                    $query->select('entry_uuid')->from('telescope_entries_tags')
                        ->whereIn('entry_uuid', function ($query) use ($values) {
                            $query->select('entry_uuid')->from('telescope_entries_tags');

                            if (isset($values['tags'])) {
                                foreach ($values['tags'] as $tag) {
                                    $query->where('tag', $tag);
                                }
                            }

                            if (isset($values['like'])) {
                                foreach ($values['like'] as $tag) {
                                    $query->where('tag', 'like', $tag . '%');
                                }
                            }
                        });
                });
            }

            return $query;
        });

        return $this;
    }


    private function prepareValues($tags)
    {
        $tags = collect(explode(',', $tags))->map(fn ($tag) => trim($tag));

        if ($tags->isEmpty()) {
            return false;
        }

        $return = [];

        foreach ($tags as $tag) {
            $explode = explode(':', $tag, 2);
            $key = $explode[0] ?? null;
            $value = $explode[1] ?? null;

            // Path
            if (!$value || in_array($key, ['http', 'https'])) {
                $return['path'][] = $tag;
            }

            // Date
            elseif ($key === 'from') {
                $return['from'] = new CarbonImmutable($value);
            } elseif ($key === 'to') {
                $return['to'] = new CarbonImmutable($value);
            }

            // Tags
            elseif (in_array($key, ['method', 'status', 'url', 'user_id', 'user_email', 'code'])) {
                $return['tags'][] = $tag;
            } else {
                $return['like'][] = $tag;
            }
        }

        return $return;
    }
}
