<?php

namespace Kanbino\BugTracking\Context;

use Illuminate\Http\Request;

class RequestContext
{
    public static function capture(Request $request): array
    {
        $config = config('kanbino-bug-tracking.request', []);
        $sanitizeHeaders = array_map('strtolower', $config['sanitize_headers'] ?? []);

        $headers = collect($request->headers->all())
            ->map(function ($values, $key) use ($sanitizeHeaders) {
                if (in_array(strtolower($key), $sanitizeHeaders)) {
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
            $sanitizeKeys = array_map('strtolower', $config['sanitize_body_keys'] ?? []);

            array_walk_recursive($body, function (&$value, $key) use ($sanitizeKeys) {
                if (in_array(strtolower($key), $sanitizeKeys)) {
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
}
