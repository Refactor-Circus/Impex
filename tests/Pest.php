<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;
use RefactorCircus\Impex\Mcp\ImpexServer;
use RefactorCircus\Impex\Tests\CortexTestCase;
use RefactorCircus\Impex\Tests\PennantPlusTestCase;
use RefactorCircus\Impex\Tests\RenamedTablesTestCase;
use RefactorCircus\Impex\Tests\TestCase;
use RefactorCircus\Keystone\Testing\McpTools;
use RefactorCircus\Keystone\Testing\Parity;

uses(TestCase::class)->in('Feature', 'Unit');
uses(CortexTestCase::class)->in('Cortex');
uses(PennantPlusTestCase::class)->in('PennantPlus');
uses(RenamedTablesTestCase::class)->in('Modes/RenamedTables');

/**
 * Call a catalog tool the way a client must now reach it: through the
 * execute_tools entry point, since every Impex tool sits behind ToolSearch.
 * A structured answer is checked against the tool's output schema.
 *
 * @param  class-string<Tool>  $tool
 * @param  array<string, mixed>  $arguments
 */
function mcpTool(string $tool, array $arguments = []): TestResponse
{
    return McpTools::call(ImpexServer::class, $tool, $arguments);
}

// The dashboard mounts its route at boot, so it needs a test case that enables
// it before the application is created.

/**
 * Use-case Actions with no MCP tool, and why.
 *
 * @var array<string, string>
 */
const MCP_EXCEPTIONS = [
    // Nothing yet: every Action currently has a tool. An artifact download
    // would land here when it is added, because a binary stream has no
    // sensible MCP shape.
];

/**
 * Actions missing from the HTTP API or MCP, by surface.
 *
 * @return array<string, array<int, string>>
 */
function parityGaps(): array
{
    $root = dirname(__DIR__).'/src';

    return Parity::gaps($root.'/Domains/*/Actions/*.php', [
        'http' => $root.'/Domains/*/Http/Requests/*.php',
        'mcp' => $root.'/Domains/*/Mcp/Requests/*.php',
    ], array_map(fn (): array => ['mcp'], MCP_EXCEPTIONS));
}
