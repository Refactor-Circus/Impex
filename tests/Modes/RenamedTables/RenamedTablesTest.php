<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RefactorCircus\Impex\Domains\Flow\Models\FlowOverrideModel;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Subscription\Enums\DeliveryStatus;
use RefactorCircus\Impex\Domains\Subscription\Mcp\Tools\CreateSubscriptionTool;
use RefactorCircus\Impex\Domains\Subscription\Models\DeliveryModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\Detector;
use RefactorCircus\Impex\Domains\Subscription\Services\Dispatcher;
use RefactorCircus\Impex\Domains\Subscription\Services\SubscriptionSweeper;
use RefactorCircus\Impex\Impex;
use RefactorCircus\Impex\Support\Tables;
use RefactorCircus\Impex\Tests\Fixtures\BatchFlow;
use RefactorCircus\Impex\Tests\Fixtures\Calls;
use RefactorCircus\Impex\Tests\Fixtures\FakeCatalog;
use RefactorCircus\Impex\Tests\Fixtures\LargePayloadFlow;
use RefactorCircus\Impex\Tests\Fixtures\OrderStreamFixture;
use RefactorCircus\Impex\Tests\Fixtures\PayloadFlow;
use RefactorCircus\Impex\Tests\Fixtures\ProductStreamFixture;
use RefactorCircus\Impex\Tests\Fixtures\SignalFlow;
use RefactorCircus\Impex\Tests\Fixtures\SleepingFlow;
use Workbench\App\Models\User;

beforeEach(function (): void {
    Calls::reset();
    FakeCatalog::reset();
    config()->set('queue.default', 'sync');
    config()->set('impex.flows', [
        'batching' => BatchFlow::class,
        'large' => LargePayloadFlow::class,
        'ingest' => PayloadFlow::class,
        'signal' => SignalFlow::class,
        'sleeping' => SleepingFlow::class,
    ]);
    config()->set('impex.streams', [ProductStreamFixture::class, OrderStreamFixture::class]);
    config()->set('impex.outbound.guard', false);
    config()->set('impex.channels', [
        'supplier-feed' => [
            'direction' => 'inbound',
            'signing_secret' => 'shhh',
            'signature_header' => 'X-Signature',
            'flow' => 'ingest',
        ],
    ]);
});

/**
 * How many rows one of Impex's tables holds, by its key.
 */
function renamedRows(string $key): int
{
    return DB::table(Tables::name($key))->count();
}

it('migrates every table under its configured name', function (): void {
    foreach (Tables::DEFAULTS as $key => $default) {
        expect(Tables::name($key))->toBe('crm_'.$key)
            ->and(Schema::hasTable('crm_'.$key))->toBeTrue()
            ->and(Schema::hasTable($default))->toBeFalse();
    }
});

it('runs a flow through steps, owners, a signal, a timer, a batch and an artifact', function (): void {
    Storage::fake('local');
    $ann = User::forceCreate(['name' => 'Ann', 'email' => 'ann@example.test', 'password' => 'x']);
    $impex = app(Impex::class);

    // A signal wait: the run parks, then the signal wakes it.
    $signalled = $impex->run('signal', owners: ['user' => $ann]);
    expect($signalled->refresh()->status)->toBe(RunStatus::Waiting);
    $impex->signal($signalled, 'approval', ['approved' => true]);
    expect($signalled->refresh()->status)->toBe(RunStatus::Completed);

    // A long sleep: a timer row, claimed and fired by the tick sweep.
    $sleeping = $impex->run('sleeping');
    expect($sleeping->refresh()->status)->toBe(RunStatus::Waiting);
    Carbon::setTestNow(Carbon::now()->addDays(4));
    $this->artisan('impex:tick')->assertSuccessful();
    Carbon::setTestNow();
    expect($sleeping->refresh()->status)->toBe(RunStatus::Completed);

    // A batch: seeded items, each claimed under a lease.
    $batch = $impex->run('batching', [3]);
    expect($batch->refresh()->status)->toBe(RunStatus::Completed);

    // An oversized result, offloaded to the artifact disk.
    $large = $impex->run('large');
    expect($large->refresh()->status)->toBe(RunStatus::Completed)
        ->and($impex->result($large))->toBe(['length' => 200_000]);

    // A runtime override for a flow.
    FlowOverrideModel::query()->create(['slug' => 'large', 'enabled' => false]);
    expect($impex->flows()->enabled('large'))->toBeFalse();

    $this->getJson('/impex/runs')->assertOk()->assertJsonCount(4, 'data');
    $this->getJson('/impex/runs/'.$signalled->id.'/steps')->assertOk();
    $this->getJson('/impex/runs/'.$signalled->id.'/owners')->assertOk()->assertJsonCount(1, 'data');

    foreach (['runs', 'run_steps', 'run_owners', 'signals', 'timers', 'batches', 'batch_items', 'artifacts', 'flows'] as $key) {
        expect(renamedRows($key))->toBeGreaterThan(0, $key);
    }

    expect(DB::table(Tables::name('timers'))->whereNotNull('fired_at')->count())->toBe(1)
        ->and(DB::table(Tables::name('signals'))->whereNotNull('consumed_at')->count())->toBe(1);
});

it('records an inbound webhook in the ledger and starts its flow', function (): void {
    $body = (string) json_encode(['sku' => 'ABC-1']);

    $this->call('POST', '/impex/channels/supplier-feed', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, 'shhh'),
    ], content: $body)->assertStatus(202);

    expect(renamedRows('messages'))->toBe(1)
        ->and(renamedRows('runs'))->toBe(1);

    $this->getJson('/impex/messages')->assertOk()->assertJsonCount(1, 'data');
});

it('creates channels and subscribers, enforcing their unique names', function (): void {
    $this->postJson('/impex/channels', [
        'name' => 'acme-webhook',
        'direction' => 'outbound',
        'transport' => 'http',
        'options' => ['url' => 'https://acme.test/hooks'],
    ])->assertCreated();

    $this->postJson('/impex/channels', ['name' => 'acme-webhook', 'direction' => 'outbound', 'transport' => 'http'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->postJson('/impex/subscribers', ['name' => 'Acme', 'client_id' => 'acme'])->assertCreated();
    $this->postJson('/impex/subscribers', ['name' => 'Acme again', 'client_id' => 'acme'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('client_id');

    expect(renamedRows('channels'))->toBe(1)
        ->and(renamedRows('subscribers'))->toBe(1);
});

it('detects, fans out, delivers, serves, sweeps and prunes a subscription', function (): void {
    Queue::fake();
    Http::fake(['vendor.test/*' => Http::response(['ok' => true])]);

    $subscriber = $this->postJson('/impex/subscribers', ['name' => 'Vendor'])->assertCreated()->json('data.id');
    mcpTool(CreateSubscriptionTool::class, [
        'subscriber' => $subscriber,
        'stream' => 'fixture.products',
        'subjects' => ['ABC-1', 'DEF-2'],
        'endpoint' => ['url' => 'https://vendor.test/hooks'],
    ])->assertOk();
    $subscription = SubscriptionModel::query()->sole()->id;

    FakeCatalog::put('ABC-1', ['price' => 10, 'name' => 'Hammer', 'category' => '/tools/']);
    FakeCatalog::put('DEF-2', ['price' => 5, 'name' => 'Apron', 'category' => '/garden/']);
    app(Impex::class)->streams()->touch('fixture.products', ['ABC-1', 'DEF-2']);
    app(Impex::class)->streams()->publish('fixture.orders', 'order-1', 'order.placed', ['total' => 25]);

    expect(renamedRows('stream_touches'))->toBe(2);

    while (app(Detector::class)->run('fixture.products') > 0) {
        //
    }

    expect(renamedRows('stream_touches'))->toBe(0)
        ->and(renamedRows('subject_states'))->toBe(2)
        ->and(renamedRows('subscription_subjects'))->toBe(2)
        ->and(renamedRows('subscription_events'))->toBe(2);

    app(SubscriptionSweeper::class)->sweep();

    $this->getJson("/impex/subscriptions/{$subscription}/events")->assertOk()->assertJsonCount(2, 'data');

    expect(app(Dispatcher::class)->deliver($subscription))->toBe(2);

    Http::assertSentCount(1);

    $model = SubscriptionModel::query()->findOrFail($subscription);

    expect($model->cursor)->toBe((int) DB::table(Tables::name('events'))->where('stream', 'fixture.products')->max('id'))
        ->and(DeliveryModel::query()->sole()->status)->toBe(DeliveryStatus::Succeeded)
        ->and(renamedRows('channels'))->toBe(1)
        ->and(DB::table(Tables::name('messages'))->whereNotNull('delivery_id')->count())->toBe(1);

    $this->getJson("/impex/subscriptions/{$subscription}/deliveries")->assertOk()->assertJsonCount(1, 'data');

    $this->postJson("/impex/subscriptions/{$subscription}/subjects", ['add' => ['XYZ-9'], 'remove' => ['DEF-2']])->assertOk();

    expect(DB::table(Tables::name('subscription_subjects'))->orderBy('subject_key')->pluck('subject_key')->all())
        ->toBe(['ABC-1', 'XYZ-9']);

    config()->set('impex.retention.events_days', 0);
    Carbon::setTestNow(Carbon::now()->addDay());
    $this->artisan('impex:prune')->assertSuccessful();
    Carbon::setTestNow();

    expect(renamedRows('events'))->toBe(0)
        ->and(renamedRows('subscription_events'))->toBe(0);

    $this->deleteJson("/impex/subscriptions/{$subscription}")->assertSuccessful();

    expect(renamedRows('subscriptions'))->toBe(0)
        ->and(renamedRows('subscription_subjects'))->toBe(0);
});
