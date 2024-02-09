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
        $response = $next($request);

        $this->config = config('wame-telescope');

        $this->tagApi($request);
        $this->tagCode($request, $response);
        $this->tagDate($request);
        $this->tagDateTime($request);
        $this->tagEmail($request);
        $this->tagErrors($request, $response);
        $this->tagHour($request);
        $this->tagMethod($request);
        $this->tagMonth($request);
        $this->tagPath($request);
        $this->tagStatus($request);
        $this->tagTime($request);
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

    private function tagCode($request, $response): void
    {
        if ($this->config['code']) {
            $code = $response->original['code'] ?? null;

            if (!is_null($code)) {
                $this->tags[] = 'Code:' . $code;
            }
        }
    }

    private function tagDate($request): void
    {
        if ($this->config['date']) {
            $this->tags[] = 'Date:' . date('Y-m-d');
        }
    }

    private function tagDateTime($request): void
    {
        if ($this->config['date_time']) {
            $this->tags[] = 'DateTime:' . date('Y-m-d H:i:s');
        }
    }

    private function tagEmail($request): void
    {
        if ($this->config['email']) {
            $this->tags[] = 'Email:' . (auth()->check() ? auth()->user()->email : 'none');
        }
    }

    private function tagErrors($request, $response): void
    {
        if ($this->config['errors']) {
            $errors = false;

            if (isset($response->original['errors']) && $response->original['errors']) {
                $errors = 'true';
            }

            if ($errors !== false) {
                $this->tags[] = 'Errors:' . $errors;
            }
        }
    }

    private function tagHour($request): void
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

    private function tagMonth($request): void
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

    private function tagStatus($request): void
    {
        if ($this->config['status']) {
            $this->tags[] = 'Status:' . $response->status();
        }
    }

    private function tagTime($request): void
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
