<?php

namespace Kanbino\BugTracking;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Kanbino\BugTracking\Breadcrumbs\BreadcrumbRecorder;

class KanbinoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/kanbino-bug-tracking.php', 'kanbino-bug-tracking');

        $this->app->singleton(KanbinoClient::class, function ($app) {
            return new KanbinoClient(
                dsn: config('kanbino-bug-tracking.dsn'),
                url: config('kanbino-bug-tracking.url'),
                environment: config('kanbino-bug-tracking.environment'),
                release: config('kanbino-bug-tracking.release'),
                serverName: config('kanbino-bug-tracking.server_name'),
            );
        });

        $this->app->singleton(BreadcrumbRecorder::class);
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/kanbino-bug-tracking.php' => config_path('kanbino-bug-tracking.php'),
        ], 'kanbino-bug-tracking-config');

        // Publish JS assets
        $this->publishes([
            __DIR__ . '/../resources/js/kanbino-bug-tracking.js' => public_path('vendor/kanbino/bug-tracking.js'),
        ], 'kanbino-bug-tracking-assets');

        // Register Blade directives
        Blade::directive('kanbinoScripts', function () {
            return "<?php echo app(\Kanbino\BugTracking\KanbinoClient::class)->renderScriptTag(); ?>";
        });

        Blade::directive('kanbinoReplay', function () {
            return "<?php echo app(\Kanbino\BugTracking\KanbinoClient::class)->renderReplayTag(); ?>";
        });

        // Register exception handler integration
        if (config('kanbino-bug-tracking.dsn')) {
            $this->registerExceptionHandler();
            $this->registerBreadcrumbs();
        }
    }

    protected function registerExceptionHandler(): void
    {
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->reportable(function (\Throwable $e) {
                $handler = new KanbinoExceptionHandler(
                    $this->app->make(KanbinoClient::class),
                    $this->app->make(BreadcrumbRecorder::class),
                );
                $handler->report($e);
            });
    }

    protected function registerBreadcrumbs(): void
    {
        $recorder = $this->app->make(BreadcrumbRecorder::class);

        if (config('kanbino-bug-tracking.breadcrumbs.queries')) {
            $recorder->registerQueryBreadcrumb();
        }

        if (config('kanbino-bug-tracking.breadcrumbs.logs')) {
            $recorder->registerLogBreadcrumb();
        }

        if (config('kanbino-bug-tracking.breadcrumbs.http_client')) {
            $recorder->registerHttpBreadcrumb();
        }
    }
}
