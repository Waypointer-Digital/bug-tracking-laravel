# Kanbino Bug Tracking SDK for Laravel

Capture PHP exceptions and JavaScript errors from your Laravel application and send them to [Kanbino](https://kanbino.com).

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

Add the repository to your project:

```bash
composer config repositories.kanbino-bt vcs https://github.com/Waypointer-Digital/bug-tracking-laravel.git
```

Install the package:

```bash
composer require waypointer-digital/bug-tracking-laravel
```

Publish the config file:

```bash
php artisan vendor:publish --tag=kanbino-bug-tracking-config
```

Add your DSN key and Kanbino URL to `.env`:

```env
KANBINO_BUG_TRACKING_DSN=bt_your_dsn_key_here
KANBINO_BUG_TRACKING_URL=https://app.kanbino.com
```

You can find your DSN key in **Kanbino > Bug Tracking > Projects > [Your Project]**.

## How It Works

Once installed, the SDK automatically:

- Catches all unhandled PHP exceptions and sends them to Kanbino
- Records breadcrumbs from database queries, log entries, and HTTP client calls
- Captures request data, authenticated user info, and runtime details (PHP version, memory, etc.)
- Deduplicates errors server-side via fingerprinting

Error reports are sent **asynchronously via your Laravel queue** by default, so they don't slow down your application.

## JavaScript Error Capture

To also capture frontend JavaScript errors, publish the JS asset and add the Blade directive to your layout:

```bash
php artisan vendor:publish --tag=kanbino-bug-tracking-assets
```

Then in your Blade layout (before `</head>`):

```blade
@kanbinoScripts
```

This captures:
- `window.onerror` and unhandled promise rejections
- Console errors and warnings
- XHR/fetch requests as breadcrumbs
- Click and navigation events as breadcrumbs

## Configuration

All options are documented in `config/kanbino-bug-tracking.php`. Key settings:

| Option | Default | Description |
|--------|---------|-------------|
| `dsn` | — | Your project's DSN key (required) |
| `url` | `https://app.kanbino.com` | Your Kanbino instance URL |
| `environment` | `APP_ENV` | Environment name sent with reports |
| `release` | `null` | App version or commit hash for tracking regressions |
| `queue` | `true` | Send reports via queue (recommended) |
| `sample_rate` | `1.0` | `0.0` to `1.0` — fraction of errors to capture |

### Ignored Exceptions

By default, these exceptions are not reported (they produce noise):

- `ValidationException`
- `NotFoundHttpException`
- `AuthenticationException`
- `MethodNotAllowedHttpException`
- `TokenMismatchException`

Edit the `ignored_exceptions` array in the config to customize.

### Before Send Callback

Filter or modify payloads before they're sent:

```php
// config/kanbino-bug-tracking.php
'before_send' => [App\Services\BugFilter::class, 'filter'],
```

```php
// app/Services/BugFilter.php
class BugFilter
{
    public static function filter(array $payload, \Throwable $e): ?array
    {
        // Return null to discard, or modify and return $payload
        if (str_contains($e->getMessage(), 'some noise')) {
            return null;
        }

        return $payload;
    }
}
```

### Sensitive Data

Request headers and body fields are automatically sanitized. By default, these headers are filtered:

- `Authorization`, `Cookie`, `X-CSRF-TOKEN`

And these body keys:

- `password`, `password_confirmation`, `secret`, `token`, `credit_card`, `card_number`, `cvv`

Customize via the `request.sanitize_headers` and `request.sanitize_body_keys` config arrays.

## Manual Capture

You can manually capture exceptions or add context:

```php
use Kanbino\BugTracking\Facades\Kanbino;

// Capture a caught exception
try {
    $this->riskyOperation();
} catch (\Exception $e) {
    Kanbino::captureException($e);
}

// Add custom context (included in all subsequent reports)
Kanbino::setContext(['order_id' => $order->id]);
```

## Middleware

The SDK includes a middleware that clears breadcrumbs per request and captures the current URL:

```php
// bootstrap/app.php or app/Http/Kernel.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Kanbino\BugTracking\Middleware\KanbinoRequestContext::class);
})
```

## Breadcrumbs

Breadcrumbs are a trail of events leading up to an error. The SDK automatically records:

| Type | Source | Example |
|------|--------|---------|
| Database queries | `DB::listen` | `SELECT * FROM users WHERE id = ?` (42ms) |
| Log entries | Log channel | `User login failed for email@example.com` |
| HTTP client calls | `Http::get()` | `GET https://api.example.com/data` (200, 150ms) |

Disable individual breadcrumb types in the config:

```php
'breadcrumbs' => [
    'queries' => true,
    'logs' => true,
    'http_client' => true,
    'max_breadcrumbs' => 50,
],
```

## License

MIT
