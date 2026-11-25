<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\RunServiceProvider;
use RefactorCircus\Impex\Tests\Fixtures\Policies\RefusingMessagePolicy;
use RefactorCircus\Impex\Tests\Fixtures\Policies\RefusingRunPolicy;
use Workbench\App\Models\User;

/**
 * Point a model at another policy, as a host does through `impex.policies`.
 *
 * @param  class-string  $model
 * @param  class-string  $policy
 */
function useStatsPolicy(string $model, string $policy): void
{
    config()->set('impex.policies.'.$model, $policy);

    Gate::policy($model, $policy);
}

it('answers with run and message stats', function (): void {
    $failed = RunModel::factory()->create(['status' => RunStatus::Failed, 'finished_at' => Carbon::now()]);
    RunModel::factory()->create(['status' => RunStatus::Running]);
    MessageModel::factory()->create(['direction' => Direction::Inbound]);

    $response = $this->getJson(route('impex.stats.show'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'runs' => [
                    'by_status' => array_column(RunStatus::cases(), 'value'),
                    'active',
                    'recent_failures' => [['id', 'flow', 'status', 'finished_at']],
                ],
                'messages' => ['window_hours', 'inbound', 'outbound'],
            ],
        ])
        ->assertJsonPath('data.runs.by_status.failed', 1)
        ->assertJsonPath('data.runs.by_status.pending', 0)
        ->assertJsonPath('data.runs.active', 1)
        ->assertJsonPath('data.runs.recent_failures.0.id', $failed->getKey())
        ->assertJsonPath('data.messages.window_hours', 24)
        ->assertJsonPath('data.messages.inbound', 1)
        ->assertJsonPath('data.messages.outbound', 0);

    expect(array_keys($response->json('data')))->toBe(['runs', 'messages'])
        ->and(array_keys($response->json('data.runs')))->toBe(['by_status', 'active', 'recent_failures'])
        ->and(array_keys($response->json('data.messages')))->toBe(['window_hours', 'inbound', 'outbound']);
});

it('asks a guest to sign in when authorization is on', function (): void {
    config()->set('impex.authorization', true);

    $this->getJson(route('impex.stats.show'))->assertUnauthorized();
});

it('refuses a user the run policy does not let list runs', function (): void {
    config()->set('impex.authorization', true);
    useStatsPolicy(RunModel::class, RefusingRunPolicy::class);

    $ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);

    $this->actingAs($ann)->getJson(route('impex.stats.show'))->assertForbidden();
});

it('counts only the caller\'s own runs when authorization is on', function (): void {
    config()->set('impex.authorization', true);

    $ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);
    $mine = RunModel::factory()->create(['status' => RunStatus::Running]);
    $mine->owners()->create(['owner_type' => $ann->getMorphClass(), 'owner_id' => (string) $ann->getKey(), 'role' => 'user']);
    RunModel::factory()->create(['status' => RunStatus::Running]);

    $this->actingAs($ann)->getJson(route('impex.stats.show'))
        ->assertOk()
        ->assertJsonPath('data.runs.by_status.running', 1)
        ->assertJsonPath('data.runs.active', 1);
});

it('sends no message counts to a caller who may not list messages', function (): void {
    config()->set('impex.authorization', true);
    useStatsPolicy(MessageModel::class, RefusingMessagePolicy::class);

    $ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);

    $this->actingAs($ann)->getJson(route('impex.stats.show'))
        ->assertOk()
        ->assertJsonPath('data.messages', null)
        ->assertJsonStructure(['data' => ['runs' => ['by_status', 'active', 'recent_failures']]]);
});

it('serves the route under the configured prefix and middleware', function (): void {
    config()->set('impex.routes.prefix', 'operations');
    config()->set('impex.routes.middleware', ['api', 'operator-gate']);

    $router = app('router');
    $router->setRoutes(new RouteCollection);

    (new RunServiceProvider(app()))->boot();

    $router->getRoutes()->refreshNameLookups();
    $route = $router->getRoutes()->getByName('impex.stats.show');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('operations/stats')
        ->and($route->methods())->toContain('GET')
        ->and($route->middleware())->toBe(['api', 'operator-gate']);
});

it('has no route while the API is turned off', function (): void {
    config()->set('impex.routes.enabled', false);

    $router = app('router');
    $router->setRoutes(new RouteCollection);

    (new RunServiceProvider(app()))->boot();

    $router->getRoutes()->refreshNameLookups();

    expect($router->getRoutes()->getByName('impex.stats.show'))->toBeNull();
});
