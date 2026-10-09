<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Mcp;

use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolSearch;
use RefactorCircus\Foundation\Mcp\Server;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\CreateChannelTool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\DeleteChannelTool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\ListChannelsTool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\RotateChannelSecretTool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\ShowChannelTool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\UpdateChannelTool;
use RefactorCircus\Impex\Domains\Flow\Mcp\Tools\ListFlowsTool;
use RefactorCircus\Impex\Domains\Flow\Mcp\Tools\RunFlowTool;
use RefactorCircus\Impex\Domains\Message\Mcp\Tools\ListMessagesTool;
use RefactorCircus\Impex\Domains\Message\Mcp\Tools\ShowMessageTool;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\AttachRunOwnerTool;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\CancelRunTool;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\DetachRunOwnerTool;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\ListRunOwnersTool;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\ListRunStepsTool;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\ListRunsTool;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\RetryRunTool;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\ShowRunTool;
use RefactorCircus\Impex\Domains\Signal\Mcp\Tools\SignalRunTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\CreateSubscriberTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\CreateSubscriptionTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\DeleteSubscriberTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\DeleteSubscriptionTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\ExportSubscriptionTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\ListDeliveriesTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\ListStreamsTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\ListSubscribersTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\ListSubscriptionEventsTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\ListSubscriptionsTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\PingSubscriptionTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\ShowSubscriberTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\ShowSubscriptionTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\UpdateSubscriberTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\UpdateSubscriptionSubjectsTool;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\UpdateSubscriptionTool;
use RefactorCircus\Impex\Mcp\Tools\ListImpexHistoryTool;

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
    'inlined in listings — large results live on an artifact disk and are referenced by id. '.
    'Every channel (list-channels-tool) is a named way in or out — http, mail or file — and every crossing is in the '.
    'ledger. Subscribers follow streams (list-streams-tool) through subscriptions: changes are pushed to a '.
    'subscription\'s endpoint in batches, or pulled from its feed (list-subscription-events-tool); '.
    'list-deliveries-tool shows how each push went.',
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

        // Channels
        ListChannelsTool::class,
        ShowChannelTool::class,
        CreateChannelTool::class,
        UpdateChannelTool::class,
        DeleteChannelTool::class,
        RotateChannelSecretTool::class,

        // Subscriptions
        ListStreamsTool::class,
        ListSubscribersTool::class,
        ShowSubscriberTool::class,
        CreateSubscriberTool::class,
        UpdateSubscriberTool::class,
        DeleteSubscriberTool::class,
        ListSubscriptionsTool::class,
        ShowSubscriptionTool::class,
        CreateSubscriptionTool::class,
        UpdateSubscriptionTool::class,
        DeleteSubscriptionTool::class,
        PingSubscriptionTool::class,
        ExportSubscriptionTool::class,
        UpdateSubscriptionSubjectsTool::class,
        ListSubscriptionEventsTool::class,
        ListDeliveriesTool::class,

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
