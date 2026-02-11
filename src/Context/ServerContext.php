<?php

namespace Kanbino\BugTracking\Context;

class ServerContext
{
    public static function capture(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'os' => PHP_OS . ' ' . php_uname('r'),
            'memory_peak' => self::formatBytes(memory_get_peak_usage(true)),
            'sapi' => PHP_SAPI,
            'hostname' => gethostname(),
        ];
    }

    protected static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
