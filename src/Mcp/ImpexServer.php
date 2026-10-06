<?php

declare(strict_types=1);

namespace JayI\Impex\Mcp;

use JayI\Foundation\Mcp\Server;
use JayI\Impex\Domains\Flow\Mcp\Tools\ListFlowsTool;
use JayI\Impex\Domains\Flow\Mcp\Tools\RunFlowTool;
use JayI\Impex\Domains\Message\Mcp\Tools\ListChannelsTool;
use JayI\Impex\Domains\Message\Mcp\Tools\ListMessagesTool;
use JayI\Impex\Domains\Message\Mcp\Tools\ShowMessageTool;
use JayI\Impex\Domains\Run\Mcp\Tools\AttachRunOwnerTool;
use JayI\Impex\Domains\Run\Mcp\Tools\CancelRunTool;
use JayI\Impex\Domains\Run\Mcp\Tools\DetachRunOwnerTool;
use JayI\Impex\Domains\Run\Mcp\Tools\ListRunOwnersTool;
use JayI\Impex\Domains\Run\Mcp\Tools\ListRunStepsTool;
use JayI\Impex\Domains\Run\Mcp\Tools\ListRunsTool;
use JayI\Impex\Domains\Run\Mcp\Tools\RetryRunTool;
use JayI\Impex\Domains\Run\Mcp\Tools\ShowRunTool;
use JayI\Impex\Domains\Signal\Mcp\Tools\SignalRunTool;
use JayI\Impex\Mcp\Tools\ListImpexHistoryTool;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolSearch;

#[Name('Impex')]
#[Version('1.0.0')]
#[Instructions(
    'Track and control Impex workflows, and inspect the flow of data in and out of this application. '.
    'A run is one execution of a flow; its steps are the recorded history of what it did, in replay order. '.
    'Starting a run is always asynchronous — run-flow-tool returns a pending run and the work is queued, so poll '.
    'show-run-tool rather than expecting a result. A run with status "waiting" is blocked on a signal or a timer: '.
    'signal-run-tool releases it. A failed run may have rolled back: list-run-steps-tool with phase "rollback" '.
    'shows what was rolled back. The ledger (list-messages-tool) records every payload that has crossed the '.
    'application boundary in either direction, linked to the run and step that caused it. Payloads are never '.
    'inlined in listings — large results live on an artifact disk and are referenced by id.',
)]
final class ImpexServer extends Server
{
    /**
     * Every tool the server offers, behind ToolSearch. Also registered with
     * Cortex when it is installed.
     *
     * @var array<int, class-string<Tool>>
     */
    public const array TOOLS = [
        // Flows
        ListFlowsTool::class,
        RunFlowTool::class,

        // Runs
        ListRunsTool::class,
        ShowRunTool::class,
        CancelRunTool::class,
        RetryRunTool::class,

        // Run detail
        ListRunStepsTool::class,
        SignalRunTool::class,

        // Ownership
        ListRunOwnersTool::class,
        AttachRunOwnerTool::class,
        DetachRunOwnerTool::class,

        // Ledger
        ListMessagesTool::class,
        ShowMessageTool::class,
        ListChannelsTool::class,

        // History
        ListImpexHistoryTool::class,
    ];

    /**
     * @var array<class-string<ToolSearch>, array<int, class-string<Tool>|Tool>>
     */
    protected array $tools = [
        ToolSearch::class => self::TOOLS,
    ];
}
