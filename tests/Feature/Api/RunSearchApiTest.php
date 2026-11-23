<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\RunServiceProvider;
use RefactorCircus\Impex\Tests\Fixtures\Policies\RefusingRunPolicy;
use Workbench\App\Models\User;

it('answers with the matching runs as a run collection', function (): void {
    $older = RunModel::factory()->create(['flow' => 'sync-orders', 'created_at' => Carbon::now()->subMinute()]);
    $newer = RunModel::factory()->create(['flow' => 'sync-orders', 'created_at' => Carbon::now()]);
    RunModel::factory()->create(['flow' => 'sync-customers']);

    $response = $this->getJson(route('impex.search.index', ['q' => 'orders']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'flow', 'status', 'created_at']]])
        ->assertJsonPath('data.0.id', $newer->getKey())
        ->assertJsonPath('data.1.id', $older->getKey());

    expect(array_keys($response->json()))->toBe(['data']);
});

it('trims the query and applies the limit', function (): void {
    RunModel::factory()->count(3)->create(['flow' => 'sync-orders']);

    $this->getJson(route('impex.search.index', ['q' => '  orders  ', 'limit' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('rejects a missing, blank or overlong query', function (array $query): void {
    $this->getJson(route('impex.search.index', $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
})->with([
    'missing' => [[]],
    'blank after trimming' => [['q' => '   ']],
    '256 characters' => [['q' => str_repeat('a', 256)]],
]);

it('accepts a 255-character query', function (): void {
    $this->getJson(route('impex.search.index', ['q' => str_repeat('a', 255)]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('rejects a limit out of range or not an integer', function (mixed $limit): void {
    $this->getJson(route('impex.search.index', ['q' => 'orders', 'limit' => $limit]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('limit');
})->with([
    'zero' => [0],
    'above 25' => [26],
    'not a number' => ['abc'],
]);

it('asks a guest to sign in when authorization is on', function (): void {
    config()->set('impex.authorization', true);

    $this->getJson(route('impex.search.index', ['q' => 'orders']))->assertUnauthorized();
});

it('refuses a user the run policy does not let list runs', function (): void {
    config()->set('impex.authorization', true);
    config()->set('impex.policies.'.RunModel::class, RefusingRunPolicy::class);
    Gate::policy(RunModel::class, RefusingRunPolicy::class);

    $ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);

    $this->actingAs($ann)->getJson(route('impex.search.index', ['q' => 'orders']))->assertForbidden();
});

it('searches only the caller\'s own runs when authorization is on', function (): void {
    config()->set('impex.authorization', true);

    $ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);
    $mine = RunModel::factory()->create(['flow' => 'sync-orders']);
    $mine->owners()->create(['owner_type' => $ann->getMorphClass(), 'owner_id' => (string) $ann->getKey(), 'role' => 'user']);
    RunModel::factory()->create(['flow' => 'sync-orders']);

    $this->actingAs($ann)->getJson(route('impex.search.index', ['q' => 'orders']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->getKey());
});

it('serves the route under the configured prefix and middleware', function (): void {
    config()->set('impex.routes.prefix', 'operations');
    config()->set('impex.routes.middleware', ['api', 'operator-gate']);

    $router = app('router');
    $router->setRoutes(new RouteCollection);

    (new RunServiceProvider(app()))->boot();

    $router->getRoutes()->refreshNameLookups();
    $route = $router->getRoutes()->getByName('impex.search.index');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('operations/search')
        ->and($route->methods())->toContain('GET')
        ->and($route->middleware())->toBe(['api', 'operator-gate']);
});

it('has no route while the API is turned off', function (): void {
    config()->set('impex.routes.enabled', false);

    $router = app('router');
    $router->setRoutes(new RouteCollection);

    (new RunServiceProvider(app()))->boot();

    $router->getRoutes()->refreshNameLookups();

    expect($router->getRoutes()->getByName('impex.search.index'))->toBeNull();
});
