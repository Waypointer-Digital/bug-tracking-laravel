<?php

namespace Kanbino\BugTracking\Breadcrumbs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class BreadcrumbRecorder
{
    protected array $breadcrumbs = [];
    protected int $maxBreadcrumbs;

    public function __construct()
    {
        $this->maxBreadcrumbs = config('kanbino-bug-tracking.breadcrumbs.max_breadcrumbs', 50);
    }

    public function add(string $type, string $category, string $message, ?array $data = null): void
    {
        $this->breadcrumbs[] = [
            'type' => $type,
            'category' => $category,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];

        // Keep only the last N breadcrumbs
        if (count($this->breadcrumbs) > $this->maxBreadcrumbs) {
            $this->breadcrumbs = array_slice($this->breadcrumbs, -$this->maxBreadcrumbs);
        }
    }

    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
    }

    public function clear(): void
    {
        $this->breadcrumbs = [];
    }

    public function registerQueryBreadcrumb(): void
    {
        DB::listen(function ($query) {
            $sql = $query->sql;
            if (strlen($sql) > 500) {
                $sql = substr($sql, 0, 500) . '...';
            }

            $this->add('query', 'db.query', $sql, [
                'duration_ms' => round($query->time, 2),
                'connection' => $query->connectionName,
            ]);
        });
    }

    public function registerLogBreadcrumb(): void
    {
        // Listen to log events
        Event::listen(\Illuminate\Log\Events\MessageLogged::class, function ($event) {
            $message = $event->message;
            if (strlen($message) > 500) {
                $message = substr($message, 0, 500) . '...';
            }

            $this->add('log', "log.{$event->level}", $message, [
                'level' => $event->level,
            ]);
        });
    }

    public function registerHttpBreadcrumb(): void
    {
        Event::listen(\Illuminate\Http\Client\Events\ResponseReceived::class, function ($event) {
            $request = $event->request;
            $response = $event->response;

            $this->add('http', 'http.client', "{$request->method()} {$request->url()}", [
                'method' => $request->method(),
                'url' => $request->url(),
                'status' => $response->status(),
            ]);
        });
    }
}
