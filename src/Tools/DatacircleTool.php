<?php

declare(strict_types=1);

namespace Datacircle\LaravelBoost\Tools;

use Datacircle\LaravelBoost\Client;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

/**
 * A Laravel Boost tool on Datacircle's open API. Boost runs each tool in a boost:execute-tool process, which calls
 * handle() itself and reads one plain Response: the result is a JSON object as text. An answer of 4xx is an error
 * result with the API's own words, so that the model reads them and can call again, and so is a failed network: a
 * thrown error reaches the model in Boost's own words around ours. Each tool is read-only, idempotent and open-world:
 * Laravel MCP reads a tool's annotations on its own class only.
 */
abstract class DatacircleTool extends Tool
{
    protected Client $client;

    /**
     * @param  Client|null  $client  The API's client: the container's Client when Boost makes the tool.
     */
    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client;
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->answer($request);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }
    }

    /**
     * The tool's result. An ApiError or any RuntimeException it throws is the tool's error result.
     */
    abstract protected function answer(Request $request): Response;

    /**
     * @param  array<string, mixed>  $result
     */
    protected static function json(array $result): Response
    {
        return Response::json($result);
    }

    protected static function error(string $message): Response
    {
        return Response::error($message);
    }

    /**
     * A string argument as the model sent it: trimmed, '' when it sent none or something else.
     */
    protected static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
