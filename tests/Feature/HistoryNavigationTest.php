<?php

declare(strict_types=1);

use RefactorCircus\Atrium\Domains\Navigation\Data\NavItem;
use RefactorCircus\Impex\Atrium\ImpexPlugin;

/**
 * Every route the items lead to, children included.
 *
 * @param  array<int, NavItem>  $items
 * @return list<string|null>
 */
function historyNavigationRoutes(array $items): array
{
    return array_merge([], ...array_map(
        fn (NavItem $item): array => [$item->route, ...historyNavigationRoutes($item->children)],
        array_values($items),
    ));
}

// History comes from Keen, which attaches it to every screen; Impex's sidebar
// group no longer links an audit log of its own.
it('links no audit log from its sidebar group', function (): void {
    expect(historyNavigationRoutes(app(ImpexPlugin::class)->navigation()))
        ->toContain('atrium.impex.runs.index')
        ->not->toContain('atrium.history.show');
});
