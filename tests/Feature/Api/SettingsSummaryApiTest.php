<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\RunServiceProvider;
use RefactorCircus\Impex\Tests\Fixtures\Policies\RefusingRunPolicy;
use Workbench\App\Models\User;

it('answers with exactly the whitelisted settings', function (): void {
    config()->set('impex.retention.host_days', 7);
    config()->set('impex.artifacts.secret', 'do-not-send');
    config()->set('impex.limits.lease_seconds', '600');

    $response = $this->getJson(route('impex.settings-summary.show'))
        ->assertOk()
        ->assertJsonPath('data.limits.lease_seconds', 600)
        ->assertJsonPath('data.artifacts.disk', null)
        ->assertJsonPath('data.artifacts.path', 'impex')
        ->assertJsonPath('data.preview_bytes', 2048);

    expect(array_keys($response->json()))->toBe(['data'])
        ->and(array_keys($response->json('data')))->toBe(['retention', 'limits', 'artifacts', 'preview_bytes'])
        ->and(array_keys($response->json('data.retention')))->toBe(['completed_runs_days', 'failed_runs_days', 'messages_days', 'artifacts_days', 'events_days', 'deliveries_days'])
        ->and(array_keys($response->json('data.limits')))->toBe(['max_step_seconds', 'resume_margin_seconds', 'lease_seconds', 'lock_seconds', 'max_resumptions', 'fan_out_max', 'sync_seconds'])
        ->and(array_keys($response->json('data.artifacts')))->toBe(['disk', 'path', 'inline_threshold', 'memo_bytes'])
        ->and($response->json('data.retention'))->each->toBeInt()
        ->and($response->json('data.limits'))->each->toBeInt()
        ->and($response->json('data.artifacts.inline_threshold'))->toBeInt()
        ->and($response->json('data.artifacts.memo_bytes'))->toBeInt();
});

it('asks a guest to sign in when authorization is on', function (): void {
    config()->set('impex.authorization', true);

    $this->getJson(route('impex.settings-summary.show'))->assertUnauthorized();
});

it('refuses a user the run policy does not let list runs', function (): void {
    config()->set('impex.authorization', true);
    config()->set('impex.policies.'.RunModel::class, RefusingRunPolicy::class);
    Gate::policy(RunModel::class, RefusingRunPolicy::class);

    $ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);

    $this->actingAs($ann)->getJson(route('impex.settings-summary.show'))->assertForbidden();
});

it('lets a signed-in user the policy allows read the summary', function (): void {
    config()->set('impex.authorization', true);

    $ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);

    $this->actingAs($ann)->getJson(route('impex.settings-summary.show'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['retention', 'limits', 'artifacts', 'preview_bytes']]);
});

it('serves the route under the configured prefix and middleware', function (): void {
    config()->set('impex.routes.prefix', 'operations');
    config()->set('impex.routes.middleware', ['api', 'operator-gate']);

    $router = app('router');
    $router->setRoutes(new RouteCollection);

    (new RunServiceProvider(app()))->boot();

    $router->getRoutes()->refreshNameLookups();
    $route = $router->getRoutes()->getByName('impex.settings-summary.show');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('operations/settings-summary')
        ->and($route->methods())->toContain('GET')
        ->and($route->middleware())->toBe(['api', 'operator-gate']);
});

it('has no route while the API is turned off', function (): void {
    config()->set('impex.routes.enabled', false);

    $router = app('router');
    $router->setRoutes(new RouteCollection);

    (new RunServiceProvider(app()))->boot();

    $router->getRoutes()->refreshNameLookups();

    expect($router->getRoutes()->getByName('impex.settings-summary.show'))->toBeNull();
});
