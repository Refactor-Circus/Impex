<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use RefactorCircus\Impex\Mcp\ImpexServer;
use RefactorCircus\Impex\Mcp\Tools\ListImpexHistoryTool;
use RefactorCircus\Keystone\Packages\PackageRegistry;

it('serves the history route inside the JSON API group', function (): void {
    expect(Route::has('impex.history.index'))->toBeTrue()
        ->and(route('impex.history.index', absolute: false))->toBe('/impex/history');
});

it('answers 404 for history while no audit log is installed', function (): void {
    $this->getJson('/impex/history')
        ->assertNotFound()
        ->assertJsonPath('message', 'No audit log is installed. Install refactor-circus/keen to record history.');
});

it('declares the history tool, but offers it only while Impex audits', function (): void {
    expect(ImpexServer::TOOLS)->toContain(ListImpexHistoryTool::class)
        ->and(app(ListImpexHistoryTool::class)->name())->toBe('list-impex-history-tool')
        ->and(app(PackageRegistry::class)->get('impex')->audits())->toBeFalse()
        ->and((new ImpexServer(new FakeTransporter))->createContext()->tools()->contains(fn (mixed $tool): bool => $tool instanceof ListImpexHistoryTool))->toBeFalse();
});
