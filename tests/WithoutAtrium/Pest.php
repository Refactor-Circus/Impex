<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;
use RefactorCircus\Impex\Mcp\ImpexServer;
use RefactorCircus\Impex\Tests\WithoutAtrium\TestCase;
use RefactorCircus\Keystone\Testing\McpTools;

/*
 * The WithoutAtrium suite's Pest file, loaded instead of tests/Pest.php
 * because `composer test:without-atrium` runs Pest with
 * --test-directory=tests/WithoutAtrium. tests/Pest.php names Atrium's
 * ResetsScreenExtensions and test cases that boot Atrium, so it must not load
 * here. Pest loads this file before PHPUnit's bootstrap, so it takes Atrium
 * out of the autoloader itself, before anything below can load a class.
 */
require_once __DIR__.'/bootstrap.php';

uses(TestCase::class)->in(__DIR__);

/**
 * Call a catalog tool through the execute_tools entry point, checking a
 * structured answer against the tool's output schema: tests/Pest.php's
 * helper, which this suite cannot load.
 *
 * @param  class-string<Tool>  $tool
 * @param  array<string, mixed>  $arguments
 */
function mcpTool(string $tool, array $arguments = []): TestResponse
{
    return McpTools::call(ImpexServer::class, $tool, $arguments);
}
