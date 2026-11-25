<?php

declare(strict_types=1);

namespace Datacircle\LaravelBoost\Tests;

use Datacircle\LaravelBoost\DatacircleServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Laravel\Boost\BoostServiceProvider;
use Laravel\Boost\Console\ExecuteToolCommand;
use Laravel\Boost\Mcp\ToolExecutor;
use Laravel\Boost\Mcp\ToolRegistry;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;
use ReflectionMethod;

abstract class TestCase extends Testbench
{
    protected function setUp(): void
    {
        parent::setUp();
        ToolRegistry::clearCache();
        // Boost registers its commands outside of tests only.
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ExecuteToolCommand::class));
    }

    protected function getPackageProviders($app): array
    {
        return [McpServiceProvider::class, BoostServiceProvider::class, DatacircleServiceProvider::class];
    }

    /**
     * A tool's result as Boost gives it to the client: its boost:execute-tool command run on the arguments, then its
     * output read back by Boost's ToolExecutor, as the subprocess and Boost's server do.
     *
     * @param  class-string  $tool
     * @param  array<string, mixed>  $arguments
     */
    protected function boost(string $tool, array $arguments = []): Response
    {
        ob_start();
        $status = Artisan::call('boost:execute-tool', ['tool' => $tool, 'arguments' => base64_encode((string) json_encode($arguments))]);
        $output = (string) ob_get_clean();
        $this->assertSame(0, $status, Artisan::output());

        $read = new ReflectionMethod(ToolExecutor::class, 'reconstructResponse');

        return $read->invoke(new ToolExecutor, json_decode($output, true));
    }

    /**
     * An answer's JSON, after it was asserted no error.
     *
     * @return array<string, mixed>
     */
    protected static function answer(Response $response): array
    {
        self::assertFalse($response->isError(), (string) $response->content());

        return json_decode((string) $response->content(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected static function errorText(Response $response): string
    {
        self::assertTrue($response->isError(), (string) $response->content());

        return (string) $response->content();
    }
}
