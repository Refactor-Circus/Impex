<?php

declare(strict_types=1);

use RefactorCircus\Atrium\Domains\Navigation\Data\NavItem;
use RefactorCircus\Impex\Atrium\ImpexPlugin;

it('links its own audit log from its sidebar group', function (): void {
    $urls = array_map(fn (NavItem $item): ?string => $item->resolveUrl(), app(ImpexPlugin::class)->navigation());

    expect($urls)->toContain(route('atrium.history.show', 'impex'));
});
