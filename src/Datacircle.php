<?php

declare(strict_types=1);

namespace Datacircle\LaravelBoost;

use Datacircle\LaravelBoost\Tools\BestPlans;
use Datacircle\LaravelBoost\Tools\ListApis;
use Datacircle\LaravelBoost\Tools\Provider;
use Datacircle\LaravelBoost\Tools\SearchPlans;

/**
 * Datacircle's four Laravel Boost tools: find the best GTM APIs. https://datacircle.dev
 */
final class Datacircle
{
    /**
     * datacircle-list-apis, datacircle-best-plans, datacircle-search-plans and datacircle-provider. The service
     * provider adds each to config('boost.mcp.tools.include'), but those in config('boost.mcp.tools.exclude').
     */
    public const TOOLS = [
        ListApis::class,
        BestPlans::class,
        SearchPlans::class,
        Provider::class,
    ];
}
