<?php

declare(strict_types=1);

namespace Datacircle\LaravelBoost\Tests;

use Datacircle\LaravelBoost\Client;
use Datacircle\LaravelBoost\Datacircle;
use Datacircle\LaravelBoost\DatacircleServiceProvider;
use Datacircle\LaravelBoost\Tools\BestPlans;
use Datacircle\LaravelBoost\Tools\ListApis;
use Datacircle\LaravelBoost\Tools\Provider;
use Datacircle\LaravelBoost\Tools\SearchPlans;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Boost\Mcp\ToolRegistry;
use Laravel\Boost\Mcp\Tools\SearchDocs;

class ToolsTest extends TestCase
{
    public function test_boost_lists_the_four_read_only_tools_beside_its_own(): void
    {
        $available = ToolRegistry::getAvailableTools();
        $this->assertContains(SearchDocs::class, $available);
        $this->assertSame(Datacircle::TOOLS, array_values(array_intersect($available, Datacircle::TOOLS)));

        $tools = array_map(fn (string $tool): array => $this->app->make($tool)->toArray(), Datacircle::TOOLS);

        $this->assertSame(
            ['datacircle-list-apis', 'datacircle-best-plans', 'datacircle-search-plans', 'datacircle-provider'],
            array_column($tools, 'name'),
        );
        foreach ($tools as $tool) {
            $this->assertSame(['readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => true],
                array_diff_key($tool['annotations'], ['title' => true]));
            $this->assertStringContainsString('https://datacircle.dev', $tool['description']);
        }
        $this->assertSame('Who is best for an API', $tools[1]['title']);
        $this->assertSame(['slug'], $tools[1]['inputSchema']['required']);
        $this->assertSame(['score', 'provider', 'price'], $tools[2]['inputSchema']['properties']['sort']['enum']);
    }

    public function test_the_apps_own_tools_stay_and_an_excluded_one_is_left_out(): void
    {
        config([
            'boost.mcp.tools.include' => ['App\\Mcp\\Tools\\Mine', BestPlans::class],
            'boost.mcp.tools.exclude' => [Provider::class],
        ]);

        (new DatacircleServiceProvider($this->app))->register();
        (new DatacircleServiceProvider($this->app))->register();

        $this->assertSame(['App\\Mcp\\Tools\\Mine', BestPlans::class, ListApis::class, SearchPlans::class], config('boost.mcp.tools.include'));
    }

    public function test_list_apis_puts_the_most_words_matched_first(): void
    {
        Http::fake(['api.datacircle.dev/v1/apis' => Http::response([
            ['api' => 'Employee phone', 'slug' => 'phone-finder', 'plans' => 3, 'providers' => 2, 'terms' => 'dropped'],
            ['api' => 'Employee email', 'slug' => 'email-finder', 'plans' => 5, 'providers' => 4],
            ['api' => 'Email validation', 'slug' => 'email-verification', 'plans' => 2, 'providers' => 2],
        ])]);

        $result = self::answer($this->boost(ListApis::class, ['query' => 'email finder']));

        $this->assertSame(3, $result['total']);
        $this->assertSame(['email-finder', 'phone-finder', 'email-verification'], array_column($result['apis'], 'slug'));
        $this->assertArrayNotHasKey('terms', $result['apis'][1]);
    }

    public function test_best_plans_sends_the_slug_and_limit_and_briefs_each_plan(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::response([
            'api' => 'Employee email', 'slug' => 'email-finder', 'total' => 1,
            'plans' => [['rank' => 1, 'provider' => 'A', 'plan' => 'Pro', 'score' => 80.5, 'perThousand' => 2.5, 'aggregators' => ['x']]],
        ])]);

        $result = self::answer($this->boost(BestPlans::class, ['slug' => 'Email Finder', 'limit' => '70']));

        $this->assertSame(['rank' => 1, 'provider' => 'A', 'plan' => 'Pro', 'score' => 80.5, 'perThousand' => 2.5], $result['plans'][0]);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.datacircle.dev/v1/apis/email-finder/best?limit=50'
            && str_starts_with($request->header('User-Agent')[0], 'laravel-boost-datacircle/'));
    }

    public function test_a_missing_slug_asks_for_one_without_a_call(): void
    {
        Http::fake();

        $this->assertStringContainsString("Give an API's slug", self::errorText($this->boost(BestPlans::class)));
        $this->assertStringContainsString("Give a provider's slug", self::errorText($this->boost(Provider::class, ['slug' => ['x']])));
        Http::assertNothingSent();
    }

    public function test_an_answer_of_4xx_is_an_error_result_in_the_apis_words(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::response(['error' => 'no API has the slug nope'], 404)]);

        $this->assertSame(
            "no API has the slug nope. datacircle-list-apis gives every API's slug, and datacircle-search-plans finds a provider's plans by its name as query.",
            self::errorText($this->boost(BestPlans::class, ['slug' => 'nope'])),
        );
    }

    public function test_a_failure_of_the_server_or_the_network_is_an_error_result(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::response('', 503)]);
        $this->assertSame('datacircle: 503 Service Unavailable', self::errorText($this->boost(Provider::class, ['slug' => 'bright-data'])));

        Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host: api.datacircle.dev'));
        $this->assertSame('datacircle: cURL error 6: Could not resolve host: api.datacircle.dev', self::errorText($this->boost(ListApis::class)));
    }

    public function test_search_plans_takes_a_provider_sent_as_the_api_as_its_name(): void
    {
        Http::fake(function (HttpRequest $request) {
            return isset($request->data()['api'])
                ? Http::response(['error' => 'api must be one of: Employee email'], 400)
                : Http::response(['total' => 1, 'offset' => 0, 'plans' => [['provider' => 'Bright Data', 'plan' => 'Free']]]);
        });

        $result = self::answer($this->boost(SearchPlans::class, ['api' => 'Bright Data', 'sort' => 'cheap']));

        $this->assertSame(1, $result['total']);
        $this->assertStringContainsString('No API is named "Bright Data"', $result['note']);

        Http::assertSent(fn (HttpRequest $request): bool => $request->data() === ['query' => 'Bright Data']);
    }

    public function test_search_plans_retries_a_slug_with_its_words(): void
    {
        Http::fake(fn (HttpRequest $request) => Http::response(['total' => $request->data()['query'] === 'bright data' ? 2 : 0, 'offset' => 0, 'plans' => []]));

        $result = self::answer($this->boost(SearchPlans::class, ['query' => 'bright-data', 'limit' => 5, 'offset' => '10']));

        $this->assertSame(2, $result['total']);
        $this->assertStringContainsString('"bright data"', $result['note']);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.datacircle.dev/v1/plans?query=bright%20data&limit=5&offset=10');
    }

    public function test_provider_gives_its_best_rank_first(): void
    {
        Http::fake(['api.datacircle.dev/v1/providers/bright-data' => Http::response([
            'provider' => 'Bright Data', 'slug' => 'bright-data',
            'apis' => [
                ['api' => 'A', 'slug' => 'a', 'rank' => null, 'plans' => [['plan' => 'x', 'rank' => null]]],
                ['api' => 'B', 'slug' => 'b', 'rank' => 7, 'plans' => [['plan' => 'y', 'rank' => 9], ['plan' => 'z', 'rank' => 7, 'score' => 50]]],
                ['api' => 'C', 'slug' => 'c', 'rank' => 2, 'plans' => [['plan' => 'w', 'rank' => 2]]],
            ],
        ])]);

        $result = self::answer($this->boost(Provider::class, ['slug' => 'Bright Data']));

        $this->assertSame(['c', 'b', 'a'], array_column($result['apis'], 'slug'));
        $this->assertSame(['plan' => 'z', 'score' => 50], $result['apis'][1]['best']);
        $this->assertSame([3, 2], [$result['total'], $result['ranked']]);
    }

    public function test_a_429_is_waited_for_once(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::sequence()
            ->push(['error' => 'slow down'], 429, ['Retry-After' => '1'])
            ->push(['provider' => 'X', 'slug' => 'x', 'apis' => []])]);

        $this->assertSame(0, self::answer($this->boost(Provider::class, ['slug' => 'x']))['total']);

        Http::assertSentCount(2);
    }

    public function test_the_containers_client_is_the_tools(): void
    {
        Http::fake(['127.0.0.1:9/v1/apis' => Http::response([])]);
        $this->app->instance(Client::class, new Client('http://127.0.0.1:9/v1/'));

        $this->assertSame(0, self::answer($this->boost(ListApis::class))['total']);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://127.0.0.1:9/v1/apis');
    }

    public function test_slugify(): void
    {
        $this->assertSame('bright-data', Client::slugify(' Bright Data '));
        $this->assertSame('societe-generale', Client::slugify('Société Générale'));
        $this->assertSame('', Client::slugify(null));
    }
}
