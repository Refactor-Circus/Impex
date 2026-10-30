<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Blade;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\ListRunsTool;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\ImpexServiceProvider;
use Workbench\App\Flows\ExportOrdersFlow;

/*
 * Impex boots and serves its JSON API and MCP with Atrium out of the
 * autoloader (tests/WithoutAtrium/bootstrap.php) and unregistered. Run by
 * `composer test:without-atrium`; the without-atrium CI job runs the same
 * tests with Atrium uninstalled, which also catches what this in-process run
 * cannot: Composer `files` and classmap leaks.
 */

const WITHOUT_ATRIUM_PREFIX = 'RefactorCircus\\Atrium\\';

/**
 * Every loaded class, interface and trait under Atrium's namespace.
 *
 * @return list<string>
 */
function loadedAtriumClasses(): array
{
    return array_values(array_filter(
        [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()],
        fn (string $class): bool => str_starts_with($class, WITHOUT_ATRIUM_PREFIX),
    ));
}

beforeEach(function (): void {
    config()->set('queue.default', 'sync');
    config()->set('impex.flows', ['order-export' => ExportOrdersFlow::class]);
    config()->set('impex.channels', ['partner-sftp' => ['direction' => 'outbound']]);
});

afterEach(function (): void {
    // Whatever a test did, it never loaded an Atrium class.
    expect(loadedAtriumClasses())->toBe([]);
});

it('has no atrium to load', function (): void {
    $prefixes = array_merge(...array_map(
        fn (ClassLoader $loader): array => array_keys($loader->getPrefixesPsr4()),
        array_values(ClassLoader::getRegisteredLoaders()),
    ));

    expect(class_exists('RefactorCircus\Atrium\Atrium'))->toBeFalse()
        ->and($prefixes)->not->toBeEmpty()
        ->and($prefixes)->not->toContain(WITHOUT_ATRIUM_PREFIX);
});

it('boots impex without the atrium provider', function (): void {
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey(ImpexServiceProvider::class)
        ->and($providers)->not->toHaveKey('RefactorCircus\Atrium\AtriumServiceProvider')
        ->and(class_exists('Workbench\App\Http\Controllers\TestHostScreenController', false))->toBeFalse();
});

it('registers the @impexCan condition without loading atrium', function (): void {
    expect(Blade::compileString("@impexCan('view', 'run') shown @endimpexCan"))
        ->toContain("Blade::check('impexCan'");
});

it('mounts no atrium route', function (): void {
    $atrium = array_values(array_filter(
        array_map(fn (Route $route): ?string => $route->getName(), iterator_to_array(app('router')->getRoutes())),
        fn (?string $name): bool => $name !== null && str_starts_with($name, 'atrium.'),
    ));

    expect($atrium)->toBe([])
        ->and(app('router')->getRoutes()->getByName('impex.runs.index'))->not->toBeNull();
});

it('starts a workbench flow and lists its run over the json api', function (): void {
    $run = $this->postJson(route('impex.flows.runs.store', 'order-export'), ['arguments' => ['2026-10-01']])
        ->assertStatus(202)
        ->assertJsonPath('data.flow', 'order-export')
        ->assertJsonPath('data.status', 'completed')
        ->json('data.id');

    $this->getJson(route('impex.runs.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $run)
        ->assertJsonPath('data.0.flow', 'order-export');
});

it('answers the runs list mcp tool', function (): void {
    $run = RunModel::factory()->create(['flow' => 'order-export']);

    mcpTool(ListRunsTool::class)->assertOk()->assertSee($run->id);
});
