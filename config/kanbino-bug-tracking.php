<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DSN Key
    |--------------------------------------------------------------------------
    | The DSN key for your Kanbino Bug Tracking project.
    | Get this from your Bug Tracking project settings in Kanbino.
    */
    'dsn' => env('KANBINO_BUG_TRACKING_DSN'),

    /*
    |--------------------------------------------------------------------------
    | Kanbino URL
    |--------------------------------------------------------------------------
    | The base URL of your Kanbino instance.
    */
    'url' => env('KANBINO_BUG_TRACKING_URL', 'https://app.kanbino.com'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    | The current environment name. Defaults to APP_ENV.
    */
    'environment' => env('KANBINO_BUG_TRACKING_ENV', env('APP_ENV', 'production')),

    /*
    |--------------------------------------------------------------------------
    | Release
    |--------------------------------------------------------------------------
    | The current release/version. Useful for tracking regressions.
    */
    'release' => env('KANBINO_BUG_TRACKING_RELEASE'),

    /*
    |--------------------------------------------------------------------------
    | Server Name
    |--------------------------------------------------------------------------
    | The server hostname. Defaults to the machine hostname.
    */
    'server_name' => env('KANBINO_BUG_TRACKING_SERVER_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Whether to send error reports via the Laravel queue (recommended).
    | Set to false to send synchronously.
    */
    'queue' => env('KANBINO_BUG_TRACKING_QUEUE', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Connection & Queue Name
    |--------------------------------------------------------------------------
    */
    'queue_connection' => env('KANBINO_BUG_TRACKING_QUEUE_CONNECTION'),
    'queue_name' => env('KANBINO_BUG_TRACKING_QUEUE_NAME', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Sample Rate
    |--------------------------------------------------------------------------
    | Float between 0.0 and 1.0. 1.0 = capture all errors.
    */
    'sample_rate' => env('KANBINO_BUG_TRACKING_SAMPLE_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    | Exception classes that should not be reported.
    */
    'ignored_exceptions' => [
        \Illuminate\Validation\ValidationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \Illuminate\Session\TokenMismatchException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Before Send Callback
    |--------------------------------------------------------------------------
    | A callable that receives the payload before sending.
    | Return null to discard the event, or the modified payload to send.
    |
    | Example: 'before_send' => [App\Services\BugFilter::class, 'filter'],
    */
    'before_send' => null,

    /*
    |--------------------------------------------------------------------------
    | Breadcrumbs
    |--------------------------------------------------------------------------
    | Configure which breadcrumbs to capture.
    */
    'breadcrumbs' => [
        'queries' => true,      // DB::listen
        'logs' => true,         // Log channel
        'http_client' => true,  // HTTP client events
        'max_breadcrumbs' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Data
    |--------------------------------------------------------------------------
    | What request data to capture. Headers listed in 'sanitize_headers'
    | will have their values replaced with [FILTERED].
    */
    'request' => [
        'capture_body' => true,
        'max_body_size' => 10000,  // bytes
        'sanitize_headers' => [
            'Authorization',
            'Cookie',
            'X-CSRF-TOKEN',
        ],
        'sanitize_body_keys' => [
            'password',
            'password_confirmation',
            'secret',
            'token',
            'credit_card',
            'card_number',
            'cvv',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JavaScript Error Capture
    |--------------------------------------------------------------------------
    | Configure the @kanbinoScripts Blade directive.
    */
    'javascript' => [
        'enabled' => env('KANBINO_BUG_TRACKING_JS_ENABLED', true),
        'capture_console' => true,
        'batch_interval' => 5000,  // ms
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Replay (rrweb)
    |--------------------------------------------------------------------------
    | Configure the @kanbinoReplay Blade directive.
    */
    'replay' => [
        'enabled' => env('KANBINO_BUG_TRACKING_REPLAY_ENABLED', false),
        'sample_rate' => env('KANBINO_BUG_TRACKING_REPLAY_SAMPLE_RATE', 0.1),
        'mask_inputs' => true,
        'chunk_interval' => 10000,  // ms
    ],
];
