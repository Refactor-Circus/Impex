<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\CreateChannelTool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\DeleteChannelTool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\ListChannelsTool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\ShowChannelTool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\UpdateChannelTool;
use RefactorCircus\Impex\Domains\Message\Mcp\Tools\ListMessagesTool;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\AttachRunOwnerTool;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\ListRunOwnersTool;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\ListRunsTool;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\RetryRunTool;
use RefactorCircus\Impex\Domains\Run\Mcp\Tools\ShowRunTool;
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
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\Detector;
use RefactorCircus\Impex\Domains\Subscription\Services\Dispatcher;
use RefactorCircus\Impex\Impex;
use RefactorCircus\Impex\Tests\Fixtures\Calls;
use RefactorCircus\Impex\Tests\Fixtures\FakeCatalog;
use RefactorCircus\Impex\Tests\Fixtures\LinearFlow;
use RefactorCircus\Impex\Tests\Fixtures\OrderStreamFixture;
use RefactorCircus\Impex\Tests\Fixtures\ProductStreamFixture;
use RefactorCircus\Impex\Tests\Fixtures\RollbackFlow;

// mcpTool() checks every structured answer against the tool's declared
// output schema, so each tool here answers once with real records.

beforeEach(function (): void {
    Calls::reset();
    FakeCatalog::reset();
    config()->set('queue.default', 'sync');
    config()->set('impex.flows', ['linear' => LinearFlow::class, 'rollback' => RollbackFlow::class]);
    config()->set('impex.streams', [ProductStreamFixture::class, OrderStreamFixture::class]);
    config()->set('impex.outbound.guard', false);
});

it('answers the channel tools within their output schemas', function (): void {
    mcpTool(CreateChannelTool::class, [
        'name' => 'partner-hooks',
        'direction' => 'outbound',
        'transport' => 'http',
        'options' => ['url' => 'https://partner.test/hooks'],
    ])->assertOk();

    mcpTool(ListChannelsTool::class)->assertOk()->assertSee('partner-hooks');
    mcpTool(ShowChannelTool::class, ['channel' => 'partner-hooks'])->assertOk();
    mcpTool(UpdateChannelTool::class, ['channel' => 'partner-hooks', 'status' => 'disabled'])->assertOk()->assertSee('disabled');
    mcpTool(DeleteChannelTool::class, ['channel' => 'partner-hooks'])->assertOk();
});

it('answers the run and ledger tools within their output schemas', function (): void {
    $run = app(Impex::class)->run('linear', [1]);
    MessageModel::factory()->create(['run_id' => $run->id]);

    mcpTool(ListRunsTool::class, ['per_page' => 1])->assertOk()->assertSee($run->id);
    mcpTool(ShowRunTool::class, ['run' => $run->id])->assertOk();
    mcpTool(AttachRunOwnerTool::class, ['run' => $run->id, 'owner_type' => 'team', 'owner_id' => '7', 'role' => 'viewer'])->assertOk();
    mcpTool(ListRunOwnersTool::class, ['run' => $run->id])->assertOk()->assertSee('viewer');
    mcpTool(ListMessagesTool::class, ['run' => $run->id])->assertOk();

    $failed = app(Impex::class)->run('rollback', [1]);

    expect($failed->refresh()->status)->toBe(RunStatus::Failed);

    mcpTool(RetryRunTool::class, ['run' => $failed->id])->assertOk();
});

it('answers the subscriber and subscription tools within their output schemas', function (): void {
    Queue::fake();
    Http::fake(['vendor.test/*' => Http::response()]);

    mcpTool(CreateSubscriberTool::class, ['name' => 'Acme'])->assertOk();
    $subscriber = SubscriberModel::query()->sole();

    mcpTool(ListSubscribersTool::class)->assertOk()->assertSee('Acme');
    mcpTool(ShowSubscriberTool::class, ['subscriber' => $subscriber->id])->assertOk();
    mcpTool(UpdateSubscriberTool::class, ['subscriber' => $subscriber->id, 'name' => 'Acme Supply'])->assertOk()->assertSee('Acme Supply');
    mcpTool(ListStreamsTool::class)->assertOk()->assertSee('fixture.orders');

    mcpTool(CreateSubscriptionTool::class, [
        'subscriber' => $subscriber->id,
        'stream' => 'fixture.orders',
        'format' => 'full',
        'endpoint' => ['url' => 'https://vendor.test/orders'],
    ])->assertOk();
    $subscription = SubscriptionModel::query()->sole();

    app(Impex::class)->streams()->publish('fixture.orders', 'order-1', 'order.placed', ['total' => 25]);

    while (app(Detector::class)->run('fixture.orders') > 0) {
        //
    }

    app(Dispatcher::class)->deliver($subscription->id);

    mcpTool(ListSubscriptionsTool::class)->assertOk()->assertSee('https://vendor.test/orders');
    mcpTool(ShowSubscriptionTool::class, ['subscription' => $subscription->id])->assertOk();
    mcpTool(UpdateSubscriptionTool::class, ['subscription' => $subscription->id, 'status' => 'paused'])->assertOk()->assertSee('paused');
    mcpTool(UpdateSubscriptionSubjectsTool::class, ['subscription' => $subscription->id, 'add' => ['order-1']])->assertOk();
    mcpTool(ListSubscriptionEventsTool::class, ['subscription' => $subscription->id])->assertOk()->assertSee('order.placed');
    mcpTool(ListDeliveriesTool::class, ['subscription' => $subscription->id])->assertOk()->assertSee('succeeded');
    mcpTool(PingSubscriptionTool::class, ['subscription' => $subscription->id])->assertOk();
    mcpTool(ExportSubscriptionTool::class, ['subscription' => $subscription->id])->assertOk();
    mcpTool(DeleteSubscriptionTool::class, ['subscription' => $subscription->id])->assertOk();
    mcpTool(DeleteSubscriberTool::class, ['subscriber' => $subscriber->id])->assertOk();
});
