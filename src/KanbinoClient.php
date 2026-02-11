<?php

namespace Kanbino\BugTracking;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class KanbinoClient
{
    protected array $context = [];

    public function __construct(
        protected ?string $dsn,
        protected string $url,
        protected string $environment,
        protected ?string $release = null,
        protected ?string $serverName = null,
    ) {
        $this->serverName = $serverName ?: gethostname();
    }

    public function isEnabled(): bool
    {
        return !empty($this->dsn);
    }

    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    public function captureException(\Throwable $e, array $breadcrumbs = [], array $extraContext = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        // Sample rate check
        $sampleRate = (float) config('kanbino-bug-tracking.sample_rate', 1.0);
        if ($sampleRate < 1.0 && mt_rand() / mt_getrandmax() > $sampleRate) {
            return;
        }

        // Check ignored exceptions
        $ignored = config('kanbino-bug-tracking.ignored_exceptions', []);
        foreach ($ignored as $ignoredClass) {
            if ($e instanceof $ignoredClass) {
                return;
            }
        }

        $payload = $this->buildPayload($e, $breadcrumbs, $extraContext);

        // Before send callback
        $beforeSend = config('kanbino-bug-tracking.before_send');
        if ($beforeSend && is_callable($beforeSend)) {
            $payload = call_user_func($beforeSend, $payload, $e);
            if ($payload === null) {
                return;
            }
        }

        if (config('kanbino-bug-tracking.queue', true)) {
            $this->sendAsync($payload);
        } else {
            $this->sendSync($payload);
        }
    }

    protected function buildPayload(\Throwable $e, array $breadcrumbs, array $extraContext): array
    {
        $stacktrace = $this->buildStacktrace($e);

        $payload = [
            'title' => $this->formatTitle($e),
            'message' => $e->getMessage(),
            'type' => get_class($e),
            'level' => 'error',
            'platform' => 'php',
            'environment' => $this->environment,
            'server_name' => $this->serverName,
            'release' => $this->release,
            'stacktrace' => $stacktrace,
            'breadcrumbs' => array_slice($breadcrumbs, -50),
            'runtime' => $this->getRuntimeInfo(),
            'context' => array_merge($this->context, $extraContext),
        ];

        // Add request data if in HTTP context
        if (app()->runningInConsole() === false) {
            $payload['request_data'] = $this->getRequestData();
            $payload['user_data'] = $this->getUserData();
        }

        return $payload;
    }

    protected function formatTitle(\Throwable $e): string
    {
        $class = class_basename($e);
        $message = $e->getMessage();

        if (strlen($message) > 200) {
            $message = substr($message, 0, 200) . '...';
        }

        return "{$class}: {$message}";
    }

    protected function buildStacktrace(\Throwable $e): array
    {
        $frames = [];

        // Add the exception's file/line as the first frame
        $frames[] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'function' => null,
            'context' => $this->getCodeContext($e->getFile(), $e->getLine()),
        ];

        foreach ($e->getTrace() as $trace) {
            $frames[] = [
                'file' => $trace['file'] ?? '[internal]',
                'line' => $trace['line'] ?? 0,
                'function' => isset($trace['class'])
                    ? "{$trace['class']}{$trace['type']}{$trace['function']}"
                    : ($trace['function'] ?? null),
                'context' => isset($trace['file']) && isset($trace['line'])
                    ? $this->getCodeContext($trace['file'], $trace['line'])
                    : null,
            ];
        }

        return array_slice($frames, 0, 30);
    }

    protected function getCodeContext(string $file, int $line, int $contextLines = 5): ?array
    {
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        try {
            $lines = file($file);
            if ($lines === false) {
                return null;
            }

            $start = max(0, $line - $contextLines - 1);
            $end = min(count($lines), $line + $contextLines);

            $context = [];
            for ($i = $start; $i < $end; $i++) {
                $context[$i + 1] = rtrim($lines[$i]);
            }

            return $context;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getRequestData(): array
    {
        $request = request();
        $config = config('kanbino-bug-tracking.request', []);

        $headers = collect($request->headers->all())
            ->map(function ($values, $key) use ($config) {
                $sanitize = $config['sanitize_headers'] ?? [];
                if (in_array($key, array_map('strtolower', $sanitize))) {
                    return '[FILTERED]';
                }
                return implode(', ', $values);
            })
            ->toArray();

        $data = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'headers' => $headers,
        ];

        if ($config['capture_body'] ?? true) {
            $body = $request->all();
            $sanitizeKeys = $config['sanitize_body_keys'] ?? [];

            array_walk_recursive($body, function (&$value, $key) use ($sanitizeKeys) {
                if (in_array(strtolower($key), array_map('strtolower', $sanitizeKeys))) {
                    $value = '[FILTERED]';
                }
            });

            $encoded = json_encode($body);
            $maxSize = $config['max_body_size'] ?? 10000;
            if (strlen($encoded) <= $maxSize) {
                $data['body'] = $body;
            }
        }

        return $data;
    }

    protected function getUserData(): ?array
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'email' => $user->email ?? null,
            'name' => $user->name ?? null,
            'ip' => request()->ip(),
        ];
    }

    protected function getRuntimeInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'os' => PHP_OS,
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'sapi' => PHP_SAPI,
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    protected function sendAsync(array $payload): void
    {
        $dsn = $this->dsn;
        $url = $this->getEndpointUrl();

        dispatch(function () use ($payload, $dsn, $url) {
            Http::withHeaders(['X-BT-Key' => $dsn])
                ->timeout(10)
                ->post($url, $payload);
        })->onConnection(config('kanbino-bug-tracking.queue_connection'))
            ->onQueue(config('kanbino-bug-tracking.queue_name', 'default'));
    }

    protected function sendSync(array $payload): void
    {
        try {
            Http::withHeaders(['X-BT-Key' => $this->dsn])
                ->timeout(5)
                ->post($this->getEndpointUrl(), $payload);
        } catch (\Throwable) {
            // Silently fail - don't let error reporting cause errors
        }
    }

    protected function getEndpointUrl(): string
    {
        return rtrim($this->url, '/') . "/api/bug-tracking/{$this->dsn}/store";
    }

    public function renderScriptTag(): string
    {
        if (!$this->isEnabled() || !config('kanbino-bug-tracking.javascript.enabled', true)) {
            return '';
        }

        $configData = [
            'dsn' => $this->dsn,
            'url' => rtrim($this->url, '/') . "/api/bug-tracking/{$this->dsn}",
            'environment' => $this->environment,
            'release' => $this->release,
            'captureConsole' => config('kanbino-bug-tracking.javascript.capture_console', true),
            'batchInterval' => config('kanbino-bug-tracking.javascript.batch_interval', 5000),
        ];

        if (config('kanbino-bug-tracking.replay.enabled', false)) {
            $configData['enableReplay'] = true;
            $configData['replayBufferSeconds'] = (int) config('kanbino-bug-tracking.replay.buffer_seconds', 60);
            $configData['replayMaskAllInputs'] = config('kanbino-bug-tracking.replay.mask_inputs', true);
        }

        $html = '<script>window.__KANBINO_BT_CONFIG=' . json_encode($configData) . ';';

        if (auth()->check()) {
            $user = auth()->user();
            $html .= 'window.__KANBINO_BT_USER=' . json_encode([
                'id' => (string) $user->id,
                'email' => $user->email ?? null,
                'name' => $user->name ?? null,
            ]) . ';';
        }

        $html .= "</script>\n"
            . '<script src="' . asset('vendor/kanbino/bug-tracking.js') . '" defer></script>';

        return $html;
    }

    public function renderReplayTag(): string
    {
        if (!$this->isEnabled() || !config('kanbino-bug-tracking.replay.enabled', false)) {
            return '';
        }

        $config = json_encode([
            'dsn' => $this->dsn,
            'url' => rtrim($this->url, '/') . "/api/bug-tracking/{$this->dsn}",
            'sampleRate' => config('kanbino-bug-tracking.replay.sample_rate', 0.1),
            'maskInputs' => config('kanbino-bug-tracking.replay.mask_inputs', true),
            'chunkInterval' => config('kanbino-bug-tracking.replay.chunk_interval', 10000),
        ]);

        return "<script>window.__KANBINO_REPLAY_CONFIG={$config};</script>";
    }
}
