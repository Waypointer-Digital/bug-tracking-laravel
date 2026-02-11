<?php

namespace Kanbino\BugTracking;

use Kanbino\BugTracking\Breadcrumbs\BreadcrumbRecorder;

class KanbinoExceptionHandler
{
    public function __construct(
        protected KanbinoClient $client,
        protected BreadcrumbRecorder $recorder,
    ) {}

    public function report(\Throwable $e): void
    {
        $this->client->captureException(
            $e,
            $this->recorder->getBreadcrumbs(),
        );
    }
}
