<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * Impex renders no history of its own: its history is the JSON history route
 * and MCP tool, offered while Keen audits Impex (HistoryTest). The scan list
 * is exactly these paths, so it never reads this file.
 */

it('names no audit trail component or history nav item in its code or docs', function (): void {
    $root = dirname(__DIR__, 2);

    $files = [$root.'/README.md', $root.'/CLAUDE.md'];

    foreach (Finder::create()->files()->in([$root.'/resources', $root.'/src', $root.'/docs'])->exclude('agent/specs') as $file) {
        $files[] = $file->getPathname();
    }

    $offenders = [];

    foreach ($files as $file) {
        foreach (['audit-trail', 'historyNavItem'] as $needle) {
            if (str_contains((string) file_get_contents($file), $needle)) {
                $offenders[] = substr($file, strlen($root) + 1).': '.$needle;
            }
        }
    }

    expect($files)->not->toBeEmpty()
        ->and($offenders)->toBe([]);
});
