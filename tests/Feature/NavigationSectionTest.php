<?php

declare(strict_types=1);

use RefactorCircus\Atrium\Support\Icons;
use RefactorCircus\Impex\Atrium\ImpexPlugin;

it('gives its sidebar section its own icon', function (): void {
    [$group] = app(ImpexPlugin::class)->navigationGroups();

    expect($group->icon)->toBe(Icons::svg('arrows-right-left'))
        ->and($group->sort)->toBe(40);
});

it('collects its pages in that section', function (): void {
    $plugin = app(ImpexPlugin::class);
    [$group] = $plugin->navigationGroups();

    $labels = array_values(array_unique(array_map(fn ($item): ?string => $item->group, $plugin->navigation())));

    expect($labels)->toBe([$group->name]);
});
