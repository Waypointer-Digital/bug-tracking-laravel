<?php

namespace Kanbino\BugTracking\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kanbino\BugTracking\Breadcrumbs\BreadcrumbRecorder;
use Kanbino\BugTracking\KanbinoClient;
use Symfony\Component\HttpFoundation\Response;

class KanbinoRequestContext
{
    public function __construct(
        protected KanbinoClient $client,
        protected BreadcrumbRecorder $recorder,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Clear breadcrumbs for each request
        $this->recorder->clear();

        // Add request breadcrumb
        $this->recorder->add(
            'navigation',
            'http.request',
            "{$request->method()} {$request->path()}",
            ['url' => $request->fullUrl()]
        );

        // Set request context on the client
        $this->client->setContext([
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'route' => $request->route()?->getName(),
        ]);

        return $next($request);
    }
}
