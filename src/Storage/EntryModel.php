<?php

namespace Wame\LaravelTelescope\Storage;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Request;
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
                $query->where('content->uri', 'LIKE', '%' . $values['path'] . '%');
            }

            // Content
            if (isset($values['content'])) {
                $query->where('content', 'LIKE', '%' . $tag . '%');
            }

            // Date
            if (isset($values['from'])) {
                $query->where('created_at', '>=', $values['from']);
            }
            if (isset($values['to'])) {
                $query->where('created_at', '<=', $values['to']);
            }
            if (isset($values['date'])) {
                $query->where('DATE(created_at)', $values['date']);
            }
            if (isset($values['datetime'])) {
                $query->where('created_at', $values['datetime']);
            }
            if (isset($values['hour'])) {
                $query->where('HOUR(created_at)', $values['hour']);
            }
            if (isset($values['month'])) {
                $query->where('MONTH(created_at)', $values['month']);
            }
            if (isset($values['time'])) {
                $query->where('TIME(created_at)', $values['time']);
            }

            // Code
            if (isset($values['code'])) {
                $query->where('content->response->code', $values['code']);
            }

            // Method
            if (isset($values['method'])) {
                $query->where('content->method', $values['method']);
            }

            // Status
            if (isset($values['status'])) {
                $query->where('content->response_status', $values['status']);
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


    private function prepareValues($text)
    {
        // Path
        if (str($text)->startsWith('/')) {
            return ['path' => $text];
        }

        if (str($text)->startsWith(['http://', 'https://'])) {
            return ['path' => Request::create($url)->path()];
        }

        // Tags
        $tags = collect(explode(',', $text))->map(fn ($tag) => trim($tag));

        if ($tags->isEmpty()) {
            return false;
        }

        $config = config('wame-telescope');
        $return = [];

        foreach ($tags as $tag) {
            $explode = explode(':', $tag, 2);
            $key = strtolower($explode[0]) ?? null;
            $value = $explode[1] ?? null;

            if (!$key && !$value) {
                return false;
            }

            // Path
            elseif (!$value) {
                $return['path'] = $tag;
            }

            // Content
            elseif (in_array($key, ['body', 'content'])) {
                $return['content'] = $value;
            }

            // Date
            elseif ($key === 'from') {
                $return['from'] = new CarbonImmutable($value);
            } elseif ($key === 'to') {
                $return['to'] = new CarbonImmutable($value);
            } elseif ($key == 'date' && $config[$key] === false) {
                $return[$key] = $value;
            } elseif ($key == 'datetime' && $config['date_time'] === false) {
                $return[$key] = new CarbonImmutable($value);
            } elseif ($key == 'hour' && $config[$key] === false) {
                $return[$key] = ltrim($value, '0');
            } elseif ($key == 'month' && $config[$key] === false) {
                $return[$key] = ltrim($value, '0');
            } elseif ($key == 'time' && $config[$key] === false) {
                $return[$key] = $value;
            }

            // Code
            elseif ($key == 'code' && $config[$key] === false) {
                $return[$key] = $value;
            }

            // Method
            elseif ($key == 'method' && $config[$key] === false) {
                $return[$key] = $value;
            }

            // Path
            elseif (in_array($key, ['path', 'uri']) && $config['path'] === false) {
                $return['path'] = $value;
            }

            // Status
            elseif ($key == 'status' && $config[$key] === false) {
                $return['status'] = $value;
            }

            // Url
            elseif ($key == 'url' && $config['url'] === false) {
                $return['path'] = $value;
            }

            // Tags
            elseif (in_array($key, ['code', 'email', 'errors', 'method', 'status', 'url'])) {
                $return['tags'][] = $tag;
            } else {
                $return['like'][] = $tag;
            }
        }

        return $return;
    }
}
