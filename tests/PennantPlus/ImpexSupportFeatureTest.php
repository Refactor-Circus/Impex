<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use RefactorCircus\Atrium\Domains\Navigation\Data\NavItem;
use RefactorCircus\Atrium\Domains\Navigation\Services\NavigationRegistry;
use RefactorCircus\Impex\Atrium\Features\ImpexSupportFeature;
use RefactorCircus\Impex\Atrium\ImpexPlugin;
use Workbench\App\Models\User;

// Pennant's array store keeps its values in the cache Feature::flushCache()
// clears, so these tests never call it.

beforeEach(function (): void {
    // Atrium denies access outside local until a gate is defined.
    app()->detectEnvironment(fn (): string => 'local');

    $this->user = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);
});

/**
 * @return array<int, string>
 */
function navigationLabels(?Authenticatable $user): array
{
    $request = Request::create('/atrium');
    $request->setUserResolver(fn (): ?Authenticatable => $user);

    return array_map(fn (NavItem $item): string => $item->label, app(NavigationRegistry::class)->items($request));
}

/**
 * Off until its global value is set, to show the class can be replaced.
 */
class OffImpexSupportFeature extends ImpexSupportFeature
{
    protected function default(): bool
    {
        return false;
    }
}

it('gates impex on the bundled feature by default', function (): void {
    expect(app(ImpexPlugin::class)->features())->toBe([ImpexSupportFeature::class]);
});

it('shows impex until the feature is turned off globally', function (): void {
    expect(navigationLabels($this->user))->toContain('Runs');

    $this->actingAs($this->user)->get(route('atrium.impex.runs.index'))->assertOk();

    Feature::for(null)->deactivate(ImpexSupportFeature::class);

    expect(navigationLabels($this->user))->not->toContain('Runs')->not->toContain('Channels');

    $this->actingAs($this->user)->get(route('atrium.impex.runs.index'))->assertNotFound();
    $this->actingAs($this->user)->get(route('atrium.impex.channels.index'))->assertNotFound();
});

it('only counts the global value, leaving per-user access to the policies', function (): void {
    Feature::for($this->user)->deactivate(ImpexSupportFeature::class);

    expect(navigationLabels($this->user))->toContain('Runs');

    $this->actingAs($this->user)->get(route('atrium.impex.runs.index'))->assertOk();
});

it('uses a subclass named in the config instead', function (): void {
    config()->set('impex.atrium.features', [OffImpexSupportFeature::class]);

    expect(navigationLabels($this->user))->not->toContain('Runs');

    Feature::for(null)->activate(OffImpexSupportFeature::class);

    expect(navigationLabels($this->user))->toContain('Runs');
});
