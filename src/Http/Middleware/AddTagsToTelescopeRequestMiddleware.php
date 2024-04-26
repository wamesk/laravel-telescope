<?php

namespace Wame\LaravelTelescope\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;

class AddTagsToTelescopeRequestMiddleware
{
    private array $config;
    private array $tags = [];

    public function handle(Request $request, Closure $next)
    {
        $requestUri = $request->getRequestUri();
        $response = $next($request);

        if (str_contains($requestUri, '/nova-api/') || str_starts_with($requestUri, '/nova-vendor/')) {
            return $response;
        }

        $this->config = config('wame-telescope');

        $this->tagApi($request);
        $this->tagCode($response);
        $this->tagDate();
        $this->tagDateTime();
        $this->tagEmail();
        $this->tagErrors($response);
        $this->tagHour();
        $this->tagMethod($request);
        $this->tagMonth();
        $this->tagPath($request);
        $this->tagStatus($response);
        $this->tagTime();
        $this->tagUrl($request);

        if (count($this->tags) > 0) {
            Telescope::tag(fn () => $this->tags);
        }

        return $response;
    }

    private function tagApi($request): void
    {
        if ($this->config['api']) {
            $this->tags[] = 'Api:' . $request->getMethod() . ' ' . $request->getRequestUri();
        }
    }

    private function tagCode($response): void
    {
        if ($this->config['code']) {
            if (gettype($response->original) === 'object' && get_class($response->original) === 'Nyholm\Psr7\Stream') {
                return;
            }

            $code = $response->original['code'] ?? null;

            if (!is_null($code)) {
                $this->tags[] = 'Code:' . $code;
            }
        }
    }

    private function tagDate(): void
    {
        if ($this->config['date']) {
            $this->tags[] = 'Date:' . date('Y-m-d');
        }
    }

    private function tagDateTime(): void
    {
        if ($this->config['date_time']) {
            $this->tags[] = 'DateTime:' . date('Y-m-d H:i:s');
        }
    }

    private function tagEmail(): void
    {
        if ($this->config['email']) {
            $this->tags[] = 'Email:' . (auth()->check() ? auth()->user()->email : 'none');
        }
    }

    private function tagErrors($response): void
    {
        if ($this->config['errors']) {
            if (gettype($response->original) === 'object' && get_class($response->original) === 'Nyholm\Psr7\Stream') {
                return;
            }

            $errors = false;

            if (isset($response->original['errors']) && $response->original['errors']) {
                $errors = 'true';
            }

            if ($errors !== false) {
                $this->tags[] = 'Errors:' . $errors;
            }
        }
    }

    private function tagHour(): void
    {
        if ($this->config['hour']) {
            $this->tags[] = 'Hour:' . date('G');
        }
    }

    private function tagMethod($request): void
    {
        if ($this->config['method']) {
            $this->tags[] = 'Method:' . $request->getMethod();
        }
    }

    private function tagMonth(): void
    {
        if ($this->config['month']) {
            $this->tags[] = 'Month:' . date('n');
        }
    }

    private function tagPath($request): void
    {
        if ($this->config['path']) {
            $this->tags[] = 'Path:/' . $request->path();
        }
    }

    private function tagStatus($response): void
    {
        if ($this->config['status']) {
            $this->tags[] = 'Status:' . $response->status();
        }
    }

    private function tagTime(): void
    {
        if ($this->config['time']) {
            $this->tags[] = 'Time:' . date('H:i');
        }
    }

    private function tagUrl($request): void
    {
        if ($this->config['url']) {
            $this->tags[] = 'Url:' . $request->getRequestUri();
        }
    }
}
