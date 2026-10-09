<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use RefactorCircus\Impex\Mcp\ImpexServer;
use RefactorCircus\Impex\Mcp\Tools\ListImpexHistoryTool;

it('serves the history route inside the JSON API group', function (): void {
    expect(Route::has('impex.history.index'))->toBeTrue()
        ->and(route('impex.history.index', absolute: false))->toBe('/impex/history');
});

it('answers 404 for history while no audit log is installed', function (): void {
    $this->getJson('/impex/history')
        ->assertNotFound()
        ->assertJsonPath('message', 'No audit log is installed. Install refactor-circus/keen to record history.');
});

it('lists the history tool on the MCP server', function (): void {
    expect(ImpexServer::TOOLS)->toContain(ListImpexHistoryTool::class)
        ->and(app(ListImpexHistoryTool::class)->name())->toBe('list-impex-history-tool');
});

it('answers the history tool with guidance while no audit log is installed', function (): void {
    mcpTool(ListImpexHistoryTool::class)
        ->assertHasErrors(['No audit log is installed']);
});
