<?php

namespace Kanbino\BugTracking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SendErrorReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        protected array $payload,
        protected string $dsn,
        protected string $url,
    ) {}

    public function handle(): void
    {
        Http::withHeaders(['X-BT-Key' => $this->dsn])
            ->timeout(10)
            ->post($this->url, $this->payload);
    }
}
