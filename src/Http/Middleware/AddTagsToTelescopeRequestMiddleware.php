<?php

namespace Wame\LaravelTelescope\Http\Middleware;

use ArrayAccess;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AddTagsToTelescopeRequestMiddleware
{
    private array $config;
    private array $tags = [];

    /**
     * Tag the API requests for Telescope.
     *
     * Tagging only describes the request, so it never changes the response: a failure
     * while building the tags is reported and the response is returned unchanged.
     */
    public function handle(Request $request, Closure $next)
    {
        $requestUri = $request->getRequestUri();
        $response = $next($request);

        if (!str_contains($requestUri, '/api/v') || !$response instanceof Response) {
            return $response;
        }

        try {
            $this->tagRequest($request, $response);
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
    }

    private function tagRequest(Request $request, Response $response): void
    {
        $this->config = config('wame-telescope');
        $payload = $this->payload($response);

        $this->tagApi($request);
        $this->tagCode($payload);
        $this->tagDate();
        $this->tagDateTime();
        $this->tagEmail();
        $this->tagErrors($payload);
        $this->tagHour();
        $this->tagMethod($request);
        $this->tagMonth();
        $this->tagPath($request);
        $this->tagStatus($response);
        $this->tagTime();
        $this->tagUrl($request);

        if (count($this->tags) > 0) {
            $tags = $this->tags;

            Telescope::tag(fn () => $tags);
        }
    }

    /**
     * The value the controller returned, when it can be read by key.
     *
     * File downloads, streamed responses and PSR-7 streams have no readable payload:
     * Symfony responses without Laravel's ResponseTrait carry no "original" property
     * at all, and a stream, a view or a plain object cannot be read as an array.
     */
    private function payload(Response $response): array|ArrayAccess|null
    {
        $original = property_exists($response, 'original') ? $response->original : null;

        return is_array($original) || $original instanceof ArrayAccess ? $original : null;
    }

    private function tagApi($request): void
    {
        if ($this->config['api']) {
            $this->tags[] = 'Api:' . $request->getMethod() . ' ' . $request->getRequestUri();
        }
    }

    private function tagCode(array|ArrayAccess|null $payload): void
    {
        if (!$this->config['code'] || null === $payload) {
            return;
        }

        $code = $payload['code'] ?? null;

        if (is_scalar($code)) {
            $this->tags[] = 'Code:' . $code;
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
        if (!$this->config['email']) {
            return;
        }

        $email = 'none';

        if (auth()->check()) {
            $user = auth()->user();

            if ($user instanceof Model) {
                $attrs = $user->getAttributes();
                if (array_key_exists('email', $attrs) && $attrs['email'] !== null) {
                    $email = (string) $attrs['email'];
                } elseif (method_exists($user, 'getEmailAttribute')) {
                    $email = (string) ($user->email ?? 'none');
                }
            }
        }

        $this->tags[] = 'Email:' . $email;
    }

    private function tagErrors(array|ArrayAccess|null $payload): void
    {
        if ($this->config['errors'] && null !== $payload && !empty($payload['errors'] ?? null)) {
            $this->tags[] = 'Errors:true';
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

    private function tagStatus(Response $response): void
    {
        if ($this->config['status']) {
            $this->tags[] = 'Status:' . $response->getStatusCode();
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
