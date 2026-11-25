# Datacircle tools for Laravel Boost

Find the best GTM APIs. We benchmark all the GTM APIs and we put the results on our website.

https://datacircle.dev

This package adds four tools to the MCP server of [Laravel Boost](https://github.com/laravel/boost). The tools call Datacircle's public API, https://api.datacircle.dev/v1. You need no key and no account.

| Tool | What the tool gives | Call |
| --- | --- | --- |
| `datacircle-list-apis` | Every GTM API on Datacircle, with the API's slug, number of plans, number of providers and best plan | `GET /apis` |
| `datacircle-best-plans` | One API's plans, sorted by score out of 100, best first. Each plan comes with a rank and a price per 1,000 calls | `GET /apis/{slug}/best` |
| `datacircle-search-plans` | Plans that match a provider name or plan name. Search all APIs or one API. Sort the plans by score, provider or price | `GET /plans` |
| `datacircle-provider` | The APIs a provider sells, and the provider's rank on each API | `GET /providers/{slug}` |

## Install

```bash
composer require datacircle/laravel-boost --dev
```

The package needs PHP 8.2 or later, Laravel 12 or 13 and Laravel Boost 2.

Laravel finds the package by itself. Boost lists the four tools from this package beside Boost's own tools. Your MCP client sees the four tools the next time the client starts `php artisan boost:mcp`.

## Leave a tool out

Publish Boost's config with `php artisan vendor:publish --tag=boost-config`. Then add the tool's class to the `exclude` list in `config/boost.php`:

```php
'mcp' => [
    'tools' => [
        'exclude' => [
            Datacircle\LaravelBoost\Tools\SearchPlans::class,
        ],
    ],
],
```

## Tool results

Each tool returns a JSON object as text. `datacircle-best-plans` and `datacircle-search-plans` return plans. Each plan has a rank, provider, plan name, score, price per 1,000 (`perThousand`, in dollars) and a page on datacircle.dev. The page on datacircle.dev lists every field.

An API error, like an unknown slug, gives an error result with the API's own message. A network failure gives an error result too. The model can then call again.

The tools are read-only and idempotent. They call through Laravel's HTTP client. `Client` sets the API address and the timeout in seconds. The timeout is 30 seconds by default. To change the timeout, bind a client in your `AppServiceProvider`:

```php
use Datacircle\LaravelBoost\Client;

$this->app->bind(Client::class, fn () => new Client(timeout: 10));
```

## Network

Each tool call makes one HTTPS GET request to https://api.datacircle.dev/v1 and nothing else. The request holds only the tool's own arguments: no key and nothing from your app. Boost runs each call in a new PHP process. The API takes up to 60 calls a minute from one IP address. The tool waits once, for a minute at most, when the API asks the tool to wait. The package stores nothing.

## Links

- Datacircle: https://datacircle.dev
- The API: https://datacircle.dev/api
- The API's spec: https://api.datacircle.dev/v1/openapi.json
- What each key means: https://datacircle.dev/fields

MIT license.
