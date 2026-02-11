<?php

namespace Kanbino\BugTracking\Facades;

use Illuminate\Support\Facades\Facade;
use Kanbino\BugTracking\KanbinoClient;

/**
 * @method static void captureException(\Throwable $e, array $breadcrumbs = [], array $extraContext = [])
 * @method static void setContext(array $context)
 * @method static bool isEnabled()
 *
 * @see \Kanbino\BugTracking\KanbinoClient
 */
class Kanbino extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return KanbinoClient::class;
    }
}
