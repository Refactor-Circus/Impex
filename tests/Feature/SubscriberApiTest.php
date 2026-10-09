<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RefactorCircus\Impex\Domains\Subscription\Contracts\ResolvesSubscriber;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\CreateSubscriberTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\CreateSubscriptionTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\ListStreamsTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\ListSubscriptionsTool;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Support\OAuthClientResolver;
use RefactorCircus\Impex\Tests\Fixtures\FakeCatalog;
use RefactorCircus\Impex\Tests\Fixtures\ProductStreamFixture;

beforeEach(function (): void {
    FakeCatalog::reset();
    Queue::fake();
    config()->set('impex.streams', [ProductStreamFixture::class]);
    config()->set('impex.outbound.guard', false);

    // The application's OAuth middleware would set this; here a header does.
    app()->singleton(ResolvesSubscriber::class, fn (): ResolvesSubscriber => new class implements ResolvesSubscriber
    {
        public function resolve(Request $request): ?SubscriberModel
        {
            $request->attributes->set('oauth_client_id', $request->header('X-Client'));

            return (new OAuthClientResolver)->resolve($request);
        }
    });
});

it('refuses a caller that is no registered subscriber', function (): void {
    $this->getJson('/impex/subscriber/subscriptions', ['X-Client' => 'nobody'])->assertForbidden();
});

it('lets a subscriber subscribe, see only its own subscriptions, and read its feed', function (): void {
    $acme = SubscriberModel::factory()->create(['client_id' => 'acme']);
    SubscriberModel::factory()->create(['client_id' => 'globex']);

    $this->getJson('/impex/subscriber/streams', ['X-Client' => 'acme'])
        ->assertOk()
        ->assertJsonPath('data.0.key', 'fixture.products')
        ->assertJsonPath('data.0.topics', ['pricing', 'content'])
        ->assertJsonPath('data.0.filters', ['category']);

    $created = $this->postJson('/impex/subscriber/subscriptions', [
        'stream' => 'fixture.products',
        'topics' => ['pricing'],
        'filter' => ['category' => '/tools/'],
        'endpoint' => ['url' => 'https://acme.test/hooks'],
    ], ['X-Client' => 'acme'])
        ->assertCreated()
        ->assertJsonPath('data.topics', ['pricing'])
        ->assertJsonPath('data.selection', 'filter')
        ->assertJsonPath('data.endpoint.url', 'https://acme.test/hooks')
        // The database's defaults, not blanks, on the response that creates it.
        ->assertJsonPath('data.cursor', '0')
        ->assertJsonPath('data.failures', 0)
        ->assertJsonPath('data.status', 'active');

    expect($created->json('secret'))->toStartWith('whsec_');

    $id = $created->json('data.id');

    $this->getJson('/impex/subscriber/subscriptions', ['X-Client' => 'acme'])->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/impex/subscriber/subscriptions', ['X-Client' => 'globex'])->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/impex/subscriber/subscriptions/{$id}", ['X-Client' => 'globex'])->assertForbidden();

    // The secret is shown once; reading the subscription never shows it.
    expect($this->getJson("/impex/subscriber/subscriptions/{$id}", ['X-Client' => 'acme'])->content())
        ->not->toContain((string) $created->json('secret'));

    $this->getJson("/impex/subscriber/subscriptions/{$id}/events", ['X-Client' => 'acme'])
        ->assertOk()
        ->assertJsonPath('has_more', false);

    expect(SubscriptionModel::query()->sole()->subscriber_id)->toBe($acme->id);
});

it('rejects a topic or a filter the stream does not have', function (): void {
    SubscriberModel::factory()->create(['client_id' => 'acme']);

    $this->postJson('/impex/subscriber/subscriptions', ['stream' => 'fixture.products', 'topics' => ['weather']], ['X-Client' => 'acme'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'The stream [fixture.products] has no such topic. Choose from: pricing, content.');

    $this->postJson('/impex/subscriber/subscriptions', ['stream' => 'fixture.products', 'filter' => ['colour' => 'red']], ['X-Client' => 'acme'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('filter.category');
});

it('pings, pauses, resumes and replays a subscription', function (): void {
    Http::fake(['acme.test/*' => Http::response()]);
    SubscriberModel::factory()->create(['client_id' => 'acme']);
    $headers = ['X-Client' => 'acme'];

    $id = $this->postJson('/impex/subscriber/subscriptions', [
        'stream' => 'fixture.products',
        'endpoint' => ['url' => 'https://acme.test/hooks'],
    ], $headers)->json('data.id');

    $this->postJson("/impex/subscriber/subscriptions/{$id}/ping", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.successful', true);

    Http::assertSent(fn ($request): bool => $request->data()['type'] === 'ping');

    $this->patchJson("/impex/subscriber/subscriptions/{$id}", ['status' => 'paused'], $headers)->assertJsonPath('data.status', 'paused');
    $this->patchJson("/impex/subscriber/subscriptions/{$id}", ['status' => 'active', 'replay_from' => 5], $headers)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.cursor', '4');
});

it('manages subscribers and subscriptions over MCP', function (): void {
    mcpTool(ListStreamsTool::class)->assertOk()->assertSee('fixture.products');

    $subscriber = mcpTool(CreateSubscriberTool::class, ['name' => 'Acme', 'client_id' => 'acme'])->assertOk();

    $id = SubscriberModel::query()->sole()->id;

    mcpTool(CreateSubscriptionTool::class, ['subscriber' => $id, 'stream' => 'fixture.products', 'topics' => ['content']])
        ->assertOk()
        ->assertSee('content');

    mcpTool(ListSubscriptionsTool::class, ['stream' => 'fixture.products'])->assertOk()->assertSee($id);

    unset($subscriber);
});
