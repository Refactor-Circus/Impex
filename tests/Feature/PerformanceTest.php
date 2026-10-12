<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RefactorCircus\Impex\Domains\Artifact\Services\PayloadStore;
use RefactorCircus\Impex\Domains\Batch\Models\BatchItemModel;
use RefactorCircus\Impex\Domains\Batch\Models\BatchModel;
use RefactorCircus\Impex\Domains\Run\Actions\ListRunsAction;
use RefactorCircus\Impex\Domains\Run\Data\ListRunsData;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Services\JobRouter;
use RefactorCircus\Impex\Domains\Subscription\Actions\CreateSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListSubscriptionsAction;
use RefactorCircus\Impex\Domains\Subscription\Actions\UpdateSubscriptionSubjectsAction;
use RefactorCircus\Impex\Domains\Subscription\Data\CreateSubscriptionData;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscriptionsData;
use RefactorCircus\Impex\Domains\Subscription\Data\UpdateSubscriptionSubjectsData;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\Detector;
use RefactorCircus\Impex\Domains\Subscription\Services\SubscriptionJobs;
use RefactorCircus\Impex\Domains\Subscription\Services\SubscriptionSweeper;
use RefactorCircus\Impex\Impex;
use RefactorCircus\Impex\Jobs\DeliverSubscription;
use RefactorCircus\Impex\Jobs\DetectStream;
use RefactorCircus\Impex\Jobs\DriveRun;
use RefactorCircus\Impex\Tests\Fixtures\BatchFlow;
use RefactorCircus\Impex\Tests\Fixtures\Calls;
use RefactorCircus\Impex\Tests\Fixtures\FakeCatalog;
use RefactorCircus\Impex\Tests\Fixtures\LinearFlow;
use RefactorCircus\Impex\Tests\Fixtures\OrderStreamFixture;
use RefactorCircus\Impex\Tests\Fixtures\ProductStreamFixture;

beforeEach(function (): void {
    FakeCatalog::reset();
    Calls::reset();
    config()->set('impex.outbound.guard', false);
    config()->set('impex.streams', [ProductStreamFixture::class, OrderStreamFixture::class]);
    config()->set('impex.flows', ['linear' => LinearFlow::class, 'batching' => BatchFlow::class]);
});

/**
 * @param  array<string, mixed>  $data
 */
function perfSubscribe(array $data = []): SubscriptionModel
{
    return app(CreateSubscriptionAction::class)->execute(
        SubscriberModel::factory()->create(),
        CreateSubscriptionData::from(['stream' => 'fixture.products', ...$data]),
    )['subscription'];
}

function perfDetect(string $stream = 'fixture.products'): void
{
    while (app(Detector::class)->run($stream) > 0) {
        //
    }
}

/**
 * The names of the events a subscription was routed, oldest first.
 *
 * @return list<string|null>
 */
function perfRoutedNames(SubscriptionModel $subscription): array
{
    return DB::table('impex_subscription_events')
        ->join('impex_events', 'impex_events.id', '=', 'impex_subscription_events.event_id')
        ->where('impex_subscription_events.subscription_id', $subscription->id)
        ->orderBy('impex_events.id')
        ->get(['impex_events.subject_key', 'impex_events.name'])
        ->map(fn (object $row): ?string => $row->name === null ? $row->subject_key : $row->name.':'.$row->subject_key)
        ->all();
}

/**
 * @return list<string>
 */
function perfQueries(Closure $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    DB::disableQueryLog();

    return array_map(fn (array $query): string => $query['query'], DB::getQueryLog());
}

it('hands an append fan-out that failed straight back instead of wedging the stream', function (): void {
    Queue::fake();

    $subscription = perfSubscribe(['stream' => 'fixture.orders']);

    app(Impex::class)->streams()->publish('fixture.orders', 'order-1', 'order.placed', ['total' => 1]);
    app(Impex::class)->streams()->publish('fixture.orders', 'order-2', 'order.placed', ['total' => 2]);

    $fail = true;

    DB::listen(function (QueryExecuted $query) use (&$fail): void {
        if ($fail && str_contains($query->sql, 'into "impex_subscription_events"')) {
            throw new RuntimeException('the database went away');
        }
    });

    expect(fn (): int => app(Detector::class)->run('fixture.orders'))->toThrow(RuntimeException::class);

    expect(DB::table('impex_events')->whereNotNull('batch')->count())->toBe(0)
        ->and(DB::table('impex_events')->whereNull('fanned_out_at')->count())->toBe(2);

    $fail = false;

    expect(app(Detector::class)->run('fixture.orders'))->toBe(2)
        ->and(DB::table('impex_subscription_events')->where('subscription_id', $subscription->id)->count())->toBe(2);
});

it('takes back append events tagged by a worker that never finished, once the lease lapses', function (): void {
    Queue::fake();

    $subscription = perfSubscribe(['stream' => 'fixture.orders']);

    app(Impex::class)->streams()->publish('fixture.orders', 'order-1', 'order.placed');

    // A worker tagged the event and died before its transaction committed.
    DB::table('impex_events')->update(['batch' => 'dead-worker', 'claimed_at' => Carbon::now()]);

    expect(app(Detector::class)->run('fixture.orders'))->toBe(0);

    Carbon::setTestNow(Carbon::now()->addSeconds(301));

    expect(app(Detector::class)->run('fixture.orders'))->toBe(1)
        ->and(DB::table('impex_events')->whereNull('fanned_out_at')->count())->toBe(0)
        ->and(DB::table('impex_subscription_events')->where('subscription_id', $subscription->id)->count())->toBe(1);

    Carbon::setTestNow();
});

it('recovers append events wedged by a tag with no claim time', function (): void {
    Queue::fake();

    perfSubscribe(['stream' => 'fixture.orders']);

    app(Impex::class)->streams()->publish('fixture.orders', 'order-1', 'order.placed');

    // What the tag-outside-the-transaction bug left behind.
    DB::table('impex_events')->update(['batch' => 'wedged']);

    expect(app(Detector::class)->run('fixture.orders'))->toBe(1)
        ->and(DB::table('impex_events')->whereNull('fanned_out_at')->count())->toBe(0);
});

it('gives each detection shard its own chunk of the backlog', function (): void {
    Queue::fake();

    config()->set('impex.subscriptions.detection.chunk', 10);

    foreach (range(1, 3) as $page) {
        Carbon::setTestNow(Carbon::now()->addSecond());

        $keys = array_map(fn (int $n): string => sprintf('P%d-%02d', $page, $n), range(1, 10));

        foreach ($keys as $key) {
            FakeCatalog::put($key, ['price' => 1, 'name' => $key, 'category' => '/tools/']);
        }

        app(Impex::class)->streams()->touch('fixture.products', $keys);
    }

    Carbon::setTestNow();

    // Shard 1 starts a chunk in, so it does not collide with shard 0.
    expect(app(Detector::class)->run('fixture.products', 1))->toBe(10)
        ->and(DB::table('impex_stream_touches')->where('subject_key', 'like', 'P2-%')->count())->toBe(0)
        ->and(DB::table('impex_stream_touches')->count())->toBe(20);

    // A shard that loses the race for its chunk looks again past it.
    $raced = false;

    DB::listen(function (QueryExecuted $query) use (&$raced): void {
        if (! $raced && str_starts_with($query->sql, 'select "subject_key" from "impex_stream_touches"')) {
            $raced = true;
            DB::table('impex_stream_touches')->update(['lease_token' => 'other', 'leased_until' => Carbon::now()->addMinute()]);
            DB::table('impex_stream_touches')->where('subject_key', 'like', 'P3-%')->update(['lease_token' => null, 'leased_until' => null]);
        }
    });

    expect(app(Detector::class)->run('fixture.products'))->toBe(10)
        ->and(DB::table('impex_stream_touches')->where('subject_key', 'like', 'P3-%')->count())->toBe(0);
});

it('fans an append stream out from one shard, so events stay in order', function (): void {
    Queue::fake();

    perfSubscribe(['stream' => 'fixture.orders']);

    app(Impex::class)->streams()->publish('fixture.orders', 'order-1', 'order.placed');

    expect(app(Detector::class)->run('fixture.orders', 1))->toBe(0)
        ->and(app(Detector::class)->run('fixture.orders', 0))->toBe(1);
});

it('queues one detection job per shard and one delivery per subscription while one waits', function (): void {
    Queue::fake();

    config()->set('impex.subscriptions.detection.concurrency', 2);

    app(Impex::class)->streams()->touch('fixture.products', ['A']);
    app(Impex::class)->streams()->touch('fixture.products', ['B']);
    app(Impex::class)->streams()->touch('fixture.products', ['C']);

    Queue::assertPushed(DetectStream::class, 2);

    app(SubscriptionJobs::class)->deliver('01JSUBSCRIPTION0000000000');
    app(SubscriptionJobs::class)->deliver('01JSUBSCRIPTION0000000000');

    Queue::assertPushed(DeliverSubscription::class, 1);
});

it('queues one drive per run while one is waiting', function (): void {
    Queue::fake();

    $run = app(Impex::class)->run('linear', [1]);

    app(JobRouter::class)->drive($run);
    app(JobRouter::class)->drive($run);

    Queue::assertPushed(DriveRun::class, 1);
});

it('sweeps for pending detection with a fixed number of indexed checks', function (): void {
    Queue::fake();

    app(Impex::class)->streams()->touch('fixture.products', ['A', 'B']);
    app(Impex::class)->streams()->publish('fixture.orders', 'order-1', 'order.placed');

    $queries = perfQueries(fn (): array => app(SubscriptionSweeper::class)->sweep());

    expect(implode("\n", $queries))->not->toContain('distinct')
        ->and(app(SubscriptionSweeper::class)->sweep()['detections'])->toBe(2);
});

it('seeds a chunk of batch items with one insert, not one per item', function (): void {
    config()->set('queue.default', 'sync');

    $queries = perfQueries(fn (): RunModel => app(Impex::class)->run('batching', [3]));

    $inserts = array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'insert or ignore into "impex_batch_items"'));
    $lookups = array_filter($queries, fn (string $sql): bool => str_contains($sql, '"item_key" = ?'));

    // Three pages of two items: three inserts, and no read-back per item.
    expect($inserts)->toHaveCount(3)
        ->and($lookups)->toBe([])
        ->and(BatchItemModel::query()->count())->toBe(6)
        ->and(BatchModel::query()->value('total'))->toBe(6);
});

it('reads a stored artifact from the disk once per process', function (): void {
    Queue::fake();

    Storage::fake('local');
    config()->set('impex.artifacts.inline_threshold', 10);

    $payloads = app(PayloadStore::class);
    $stored = $payloads->put(['big' => str_repeat('x', 100)]);

    $queries = perfQueries(function () use ($payloads, $stored): void {
        expect($payloads->get(null, $stored['artifact_id']))->toBe(['big' => str_repeat('x', 100)])
            ->and($payloads->get(null, $stored['artifact_id']))->toBe(['big' => str_repeat('x', 100)]);
    });

    expect($queries)->toHaveCount(1);

    $payloads->forget();

    expect(perfQueries(fn (): mixed => $payloads->get(null, $stored['artifact_id'])))->toHaveCount(1);
});

it('keeps no more decoded artifacts than the memo allows', function (): void {
    Queue::fake();

    Storage::fake('local');
    config()->set('impex.artifacts.inline_threshold', 10);
    config()->set('impex.artifacts.memo_bytes', 150);

    $payloads = app(PayloadStore::class);
    $first = $payloads->put(str_repeat('a', 100));
    $second = $payloads->put(str_repeat('b', 100));

    $payloads->get(null, $first['artifact_id']);
    $payloads->get(null, $second['artifact_id']);

    // The second pushed the first out.
    expect(perfQueries(fn (): mixed => $payloads->get(null, $second['artifact_id'])))->toHaveCount(0)
        ->and(perfQueries(fn (): mixed => $payloads->get(null, $first['artifact_id'])))->toHaveCount(1);
});

it('finds who had a subject from its holdings, not its event history', function (): void {
    Queue::fake();

    $subscription = perfSubscribe(['filter' => ['category' => '/tools/']]);

    FakeCatalog::put('A', ['price' => 1, 'name' => 'A', 'category' => '/tools/']);
    app(Impex::class)->streams()->touch('fixture.products', ['A']);
    perfDetect();

    expect(DB::table('impex_subscription_holdings')->where('subscription_id', $subscription->id)->pluck('subject_key')->all())->toBe(['A']);

    // History pruned: the holding still says the subscriber has A.
    DB::table('impex_subscription_events')->delete();
    DB::table('impex_events')->delete();

    FakeCatalog::put('A', ['price' => 1, 'name' => 'A', 'category' => '/garden/']);
    app(Impex::class)->streams()->touch('fixture.products', ['A']);

    $queries = perfQueries(fn () => perfDetect());

    expect(perfRoutedNames($subscription))->toBe(['left_scope:A'])
        ->and(DB::table('impex_subscription_holdings')->count())->toBe(0)
        ->and(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "impex_events" where "stream" = ? and "subject_key" in')))->toBe([]);
});

it('rebuilds holdings from history when a subscription that took everything is given a list', function (): void {
    Queue::fake();

    $subscription = perfSubscribe();

    foreach (['A', 'B'] as $key) {
        FakeCatalog::put($key, ['price' => 1, 'name' => $key, 'category' => '/tools/']);
    }

    app(Impex::class)->streams()->touch('fixture.products', ['A', 'B']);
    perfDetect();

    expect(DB::table('impex_subscription_holdings')->count())->toBe(0);

    app(UpdateSubscriptionSubjectsAction::class)->execute($subscription, new UpdateSubscriptionSubjectsData(add: ['A']));

    expect(DB::table('impex_subscription_holdings')->orderBy('subject_key')->pluck('subject_key')->all())->toBe(['A', 'B']);

    // B is off the list now, and was sent before: it is told to forget it.
    FakeCatalog::put('B', ['price' => 2, 'name' => 'B', 'category' => '/tools/']);
    app(Impex::class)->streams()->touch('fixture.products', ['B']);
    perfDetect();

    expect(perfRoutedNames($subscription->refresh()))->toBe(['A', 'B', 'left_scope:B']);
});

it('marks a subscription pending only when the mark is missing or old', function (): void {
    Queue::fake();

    $subscription = perfSubscribe();
    $marked = Carbon::now()->subSecond()->startOfSecond();

    SubscriptionModel::query()->whereKey($subscription->id)->update(['pending_at' => $marked]);

    FakeCatalog::put('A', ['price' => 1, 'name' => 'A', 'category' => '/tools/']);
    app(Impex::class)->streams()->touch('fixture.products', ['A']);
    perfDetect();

    expect($subscription->refresh()->pending_at?->equalTo($marked))->toBeTrue();

    SubscriptionModel::query()->whereKey($subscription->id)->update(['pending_at' => Carbon::now()->subMinute()]);

    FakeCatalog::put('A', ['price' => 2, 'name' => 'A', 'category' => '/tools/']);
    app(Impex::class)->streams()->touch('fixture.products', ['A']);
    perfDetect();

    expect($subscription->refresh()->pending_at?->greaterThan(Carbon::now()->subSeconds(5)))->toBeTrue();
});

it('pages through runs started in the same second without skipping or repeating one', function (): void {
    Queue::fake();

    $now = Carbon::now()->startOfSecond();

    $ids = collect(range(1, 5))->map(fn (): string => (string) RunModel::factory()->create(['created_at' => $now])->getKey())->sort()->values()->all();

    $seen = [];
    $cursor = null;

    do {
        $page = app(ListRunsAction::class)->execute(ListRunsData::from(['per_page' => 2, 'cursor' => $cursor]));
        $seen = [...$seen, ...$page->pluck('id')->map(fn (mixed $id): string => (string) $id)->all()];
        $cursor = $page->nextCursor()?->encode();
    } while ($cursor !== null);

    expect($seen)->toBe(array_reverse($ids));
});

it('prunes a run\'s batch items and steps in slices before the run itself', function (): void {
    config()->set('queue.default', 'sync');

    $run = app(Impex::class)->run('batching', [3]);
    $run->refresh()->update(['finished_at' => Carbon::now()->subYears(2), 'status' => RunStatus::Completed]);

    $queries = perfQueries(fn (): int => (new RunModel)->pruneAll());

    $items = array_key_first(array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'delete from "impex_batch_items"')));
    $runs = array_key_first(array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'delete from "impex_runs"')));

    expect($items)->not->toBeNull()
        ->and($runs)->toBeGreaterThan($items)
        ->and(RunModel::query()->count())->toBe(0)
        ->and(BatchItemModel::query()->count())->toBe(0)
        ->and(DB::table('impex_run_steps')->count())->toBe(0);
});

it('lists subscriptions with the same number of queries however many there are', function (): void {
    Queue::fake();

    $count = function (int $subscriptions): int {
        SubscriptionModel::query()->delete();

        foreach (range(1, $subscriptions) as $ignored) {
            perfSubscribe(['endpoint' => ['url' => 'https://vendor.test/hook']]);
        }

        return count(perfQueries(function (): void {
            app(ListSubscriptionsAction::class)->execute(new ListSubscriptionsData)
                ->each(fn (SubscriptionModel $subscription): string => $subscription->subscriber->name.$subscription->channel?->name);
        }));
    };

    expect($count(5))->toBe($count(1));
});

it('reads a flow\'s override once when starting a run', function (): void {
    Queue::fake();

    $queries = perfQueries(fn (): RunModel => app(Impex::class)->run('linear', [1]));

    expect(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "impex_flows"')))->toHaveCount(1);
});
