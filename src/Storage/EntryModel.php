<?php

namespace Wame\LaravelTelescope\Storage;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Request;
use Laravel\Telescope\Storage\EntryQueryOptions;
use Throwable;

class EntryModel extends \Laravel\Telescope\Storage\EntryModel
{
    /**
     * Filter the entries by the search text (see the README for the supported keys).
     *
     * Every condition must match: each tag is looked up on its own, so "Email:x, Code:y"
     * finds the entries tagged with both.
     *
     * {@inheritDoc}
     */
    protected function whereTag($query, EntryQueryOptions $options)
    {
        $query->when($options->tag, function ($query, $text) {
            $values = $this->prepareValues((string) $text);

            if (!$values) {
                return $query;
            }

            // Path
            if (isset($values['path'])) {
                $query->where('content->uri', 'LIKE', '%' . $values['path'] . '%');
            }

            // Content
            if (isset($values['content'])) {
                $query->where('content', 'LIKE', '%' . $values['content'] . '%');
            }

            // Date
            if (isset($values['from'])) {
                $query->where('created_at', '>=', $values['from']);
            }
            if (isset($values['to'])) {
                $query->where('created_at', '<=', $values['to']);
            }
            if (isset($values['date'])) {
                $query->whereDate('created_at', $values['date']);
            }
            if (isset($values['datetime'])) {
                $query->where('created_at', $values['datetime']);
            }
            if (isset($values['hour'])) {
                $query->whereRaw('HOUR(created_at) = ?', [$values['hour']]);
            }
            if (isset($values['month'])) {
                $query->whereMonth('created_at', $values['month']);
            }
            if (isset($values['time'])) {
                $format = 5 === strlen($values['time']) ? '%H:%i' : '%H:%i:%s';
                $query->whereRaw('DATE_FORMAT(created_at, ?) = ?', [$format, $values['time']]);
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
            foreach ($values['tags'] ?? [] as $tag) {
                $query->whereIn('uuid', function ($query) use ($tag) {
                    $query->select('entry_uuid')->from('telescope_entries_tags')->where('tag', $tag);
                });
            }

            foreach ($values['like'] ?? [] as $tag) {
                $query->whereIn('uuid', function ($query) use ($tag) {
                    $query->select('entry_uuid')->from('telescope_entries_tags')->where('tag', 'like', $tag . '%');
                });
            }

            return $query;
        });

        return $this;
    }

    /**
     * Split the search text into the filters applied by whereTag().
     *
     * A value that is not a valid date, time, hour or month is dropped instead of
     * failing the whole search.
     *
     * @return array<string, mixed>|false
     */
    private function prepareValues(string $text): array|false
    {
        $text = trim($text);

        // Path
        if (str_starts_with($text, '/')) {
            return ['path' => $text];
        }

        if (str_starts_with($text, 'http://') || str_starts_with($text, 'https://')) {
            return ['path' => Request::create($text)->path()];
        }

        // Tags
        $tags = collect(explode(',', $text))
            ->map(fn (string $tag): string => trim($tag))
            ->filter(fn (string $tag): bool => '' !== $tag);

        if ($tags->isEmpty()) {
            return false;
        }

        $config = config('wame-telescope');
        $return = [];

        foreach ($tags as $tag) {
            $explode = explode(':', $tag, 2);
            $key = strtolower(trim($explode[0]));
            $value = isset($explode[1]) ? trim($explode[1]) : '';

            // Path
            if ('' === $value) {
                $return['path'] = $tag;
            }

            // Content
            elseif (in_array($key, ['body', 'content'])) {
                $return['content'] = $value;
            }

            // Date
            elseif ($key === 'from') {
                $this->putValue($return, 'from', $this->parseDateTime($value));
            } elseif ($key === 'to') {
                $this->putValue($return, 'to', $this->parseDateTime($value));
            } elseif ($key == 'date' && $config[$key] === false) {
                $this->putValue($return, 'date', $this->parseDate($value));
            } elseif ($key == 'datetime' && $config['date_time'] === false) {
                $this->putValue($return, 'datetime', $this->parseDateTime($value));
            } elseif ($key == 'hour' && $config[$key] === false) {
                $this->putValue($return, 'hour', $this->parseNumber($value, 0, 23));
            } elseif ($key == 'month' && $config[$key] === false) {
                $this->putValue($return, 'month', $this->parseNumber($value, 1, 12));
            } elseif ($key == 'time' && $config[$key] === false) {
                $this->putValue($return, 'time', $this->parseTime($value));
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

    /**
     * Set the filter unless its value could not be parsed.
     *
     * @param  array<string, mixed>  $values
     */
    private function putValue(array &$values, string $key, mixed $value): void
    {
        if (null !== $value) {
            $values[$key] = $value;
        }
    }

    private function parseDateTime(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDate(string $value): ?string
    {
        return $this->parseDateTime($value)?->format('Y-m-d');
    }

    /**
     * "HH:MM" or "HH:MM:SS", with a one-digit hour accepted.
     */
    private function parseTime(string $value): ?string
    {
        if (1 !== preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $matches)) {
            return null;
        }

        $time = sprintf('%02d:%s', (int) $matches[1], $matches[2]);

        return isset($matches[3]) ? $time . ':' . $matches[3] : $time;
    }

    private function parseNumber(string $value, int $min, int $max): ?int
    {
        if (1 !== preg_match('/^\d{1,2}$/', $value) || (int) $value < $min || (int) $value > $max) {
            return null;
        }

        return (int) $value;
    }
}
