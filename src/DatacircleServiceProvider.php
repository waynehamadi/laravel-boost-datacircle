<?php

declare(strict_types=1);

namespace Datacircle\LaravelBoost;

use Illuminate\Support\ServiceProvider;

/**
 * Adds the four tools to Laravel Boost's MCP server: Boost lists and runs the classes in boost.mcp.tools.include. A tool
 * in boost.mcp.tools.exclude is left out. Laravel finds this provider by itself (extra.laravel.providers).
 */
class DatacircleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app->make('config');
        $include = (array) $config->get('boost.mcp.tools.include', []);
        $exclude = (array) $config->get('boost.mcp.tools.exclude', []);

        foreach (Datacircle::TOOLS as $tool) {
            // A cached config already holds them.
            if (! in_array($tool, $include, true) && ! in_array($tool, $exclude, true)) {
                $include[] = $tool;
            }
        }

        $config->set('boost.mcp.tools.include', $include);
    }
}
