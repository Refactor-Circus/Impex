<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Lock;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RefactorCircus\Impex\Domains\Channel\Support\StandardWebhooksSigner;
use RefactorCircus\Impex\Domains\Subscription\Actions\CreateSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Enums\DeliveryStatus;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionDisabled;
use RefactorCircus\Impex\Domains\Subscription\Models\DeliveryModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\Detector;
use RefactorCircus\Impex\Domains\Subscription\Services\Dispatcher;
use RefactorCircus\Impex\Domains\Subscription\Services\Exporter;
use RefactorCircus\Impex\Domains\Subscription\Services\FanOut;
use RefactorCircus\Impex\Impex;
use RefactorCircus\Impex\Tests\Fixtures\FakeCatalog;
use RefactorCircus\Impex\Tests\Fixtures\OrderStreamFixture;
use RefactorCircus\Impex\Tests\Fixtures\ProductStreamFixture;

beforeEach(function (): void {
    FakeCatalog::reset();
    Queue::fake();
    config()->set('impex.streams', [ProductStreamFixture::class, OrderStreamFixture::class]);
    config()->set('impex.outbound.guard', false);
    config()->set('impex.subscriptions.delivery.backoff.jitter', false);
});

/**
 * @param  array<string, mixed>  $data
 * @return array{subscription: SubscriptionModel, secret: string|null}
 */
function subscribe(array $data, ?SubscriberModel $subscriber = null): array
{
    return app(CreateSubscriptionAction::class)->execute(
        $subscriber ?? SubscriberModel::factory()->create(),
        ['stream' => 'fixture.products', ...$data],
    );
}

function detect(string $stream = 'fixture.products'): void
{
    while (app(Detector::class)->run($stream) > 0) {
        //
    }
}

/**
 * @return list<int>
 */
function routedTo(SubscriptionModel $subscription): array
{
    return DB::table('impex_subscription_events')
        ->where('subscription_id', $subscription->id)
        ->orderBy('event_id')
        ->pluck('event_id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();
}

function touchProducts(string ...$skus): void
{
    app(Impex::class)->streams()->touch('fixture.products', $skus);
}

it('sends nothing when a touched subject has not changed', function (): void {
    $subscription = subscribe([])['subscription'];
    FakeCatalog::put('ABC-1', ['price' => 10, 'name' => 'Widget', 'category' => '/tools/']);

    touchProducts('ABC-1');
    detect();
    touchProducts('ABC-1');
    detect();

    expect(routedTo($subscription))->toHaveCount(1)
        ->and(DB::table('impex_events')->count())->toBe(1)
        ->and(DB::table('impex_stream_touches')->count())->toBe(0);
});

it('routes a change only to subscriptions following the topic that changed', function (): void {
    $pricing = subscribe(['topics' => ['pricing']])['subscription'];
    $content = subscribe(['topics' => ['content']])['subscription'];

    FakeCatalog::put('ABC-1', ['price' => 10, 'name' => 'Widget', 'category' => '/tools/']);
    touchProducts('ABC-1');
    detect();

    FakeCatalog::put('ABC-1', ['price' => 12, 'name' => 'Widget', 'category' => '/tools/']);
    touchProducts('ABC-1');
    detect();

    $event = DB::table('impex_events')->latest('id')->first();

    expect(routedTo($pricing))->toHaveCount(2)
        ->and(routedTo($pricing)[1])->toBe((int) $event->id)
        ->and(routedTo($content))->toHaveCount(1)
        ->and((int) $event->topics)->toBe(0b01);
});

it('sends a subject that moves into scope to the subscribers it reaches, whole', function (): void {
    $garden = subscribe(['topics' => ['pricing'], 'filter' => ['category' => '/garden/']])['subscription'];

    FakeCatalog::put('ABC-1', ['price' => 10, 'name' => 'Rake', 'category' => '/tools/']);
    touchProducts('ABC-1');
    detect();

    expect(routedTo($garden))->toBe([]);

    // Only the category changed: no topic moved, yet it now belongs to them.
    FakeCatalog::put('ABC-1', ['price' => 10, 'name' => 'Rake', 'category' => '/garden/']);
    touchProducts('ABC-1');
    detect();

    $event = DB::table('impex_events')->whereIn('id', routedTo($garden))->sole();

    expect($event->kind)->toBe('changed')
        ->and($event->name)->toBe('entered_scope');
});

it('matches filtered and listed subscriptions by their rules, not by expanding them', function (): void {
    $tools = subscribe(['filter' => ['category' => '/tools/']])['subscription'];
    $listed = subscribe(['subjects' => ['XYZ-9']])['subscription'];

    FakeCatalog::put('ABC-1', ['price' => 10, 'name' => 'Hammer', 'category' => '/tools/hand/']);
    FakeCatalog::put('DEF-2', ['price' => 5, 'name' => 'Apron', 'category' => '/garden/']);
    FakeCatalog::put('XYZ-9', ['price' => 7, 'name' => 'Rake', 'category' => '/garden/']);
    touchProducts('ABC-1', 'DEF-2', 'XYZ-9');
    detect();

    $subjects = fn (SubscriptionModel $s): array => DB::table('impex_events')
        ->whereIn('id', routedTo($s))->pluck('subject_key')->all();

    expect($subjects($tools))->toBe(['ABC-1'])
        ->and($subjects($listed))->toBe(['XYZ-9']);
});

it('tells a subscriber to forget a subject that leaves its scope or is deleted', function (): void {
    $tools = subscribe(['filter' => ['category' => '/tools/']])['subscription'];
    $everything = subscribe([])['subscription'];

    FakeCatalog::put('ABC-1', ['price' => 10, 'name' => 'Hammer', 'category' => '/tools/']);
    touchProducts('ABC-1');
    detect();

    // Recategorised: still a product, but no longer one tools subscribers get.
    FakeCatalog::put('ABC-1', ['price' => 10, 'name' => 'Hammer', 'category' => '/garden/']);
    touchProducts('ABC-1');
    detect();

    $kinds = fn (SubscriptionModel $s): array => DB::table('impex_events')
        ->whereIn('id', routedTo($s))->orderBy('id')->pluck('kind')->all();

    expect($kinds($tools))->toBe(['changed', 'removed']);

    // Deleted: everyone who had it hears, once; tools already forgot it.
    unset(FakeCatalog::$products['ABC-1']);
    touchProducts('ABC-1');
    detect();

    expect($kinds($tools))->toBe(['changed', 'removed'])
        ->and($kinds($everything))->toBe(['changed', 'removed']);
});

it('pushes a signed batch, folding repeated changes to one entry per subject', function (): void {
    Http::fake(['vendor.test/*' => Http::response(['ok' => true])]);

    $created = subscribe(['endpoint' => ['url' => 'https://vendor.test/hooks'], 'format' => 'slice']);
    $subscription = $created['subscription'];

    FakeCatalog::put('ABC-1', ['price' => 10, 'name' => 'Hammer', 'category' => '/tools/']);
    touchProducts('ABC-1');
    detect();
    FakeCatalog::put('ABC-1', ['price' => 11, 'name' => 'Hammer', 'category' => '/tools/']);
    touchProducts('ABC-1');
    detect();
    FakeCatalog::put('ABC-1', ['price' => 11, 'name' => 'Claw hammer', 'category' => '/tools/']);
    touchProducts('ABC-1');
    detect();

    expect(app(Dispatcher::class)->deliver($subscription->id))->toBe(3);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($created): bool {
        $body = $request->body();
        $expected = StandardWebhooksSigner::signature(
            (string) $created['secret'],
            $request->header('webhook-id')[0],
            (int) $request->header('webhook-timestamp')[0],
            $body,
        );

        $events = $request->data()['events'];

        return $request->header('webhook-signature')[0] === 'v1,'.$expected
            && count($events) === 1
            && $events[0]['subject'] === 'ABC-1'
            && $events[0]['topics'] === ['pricing', 'content']
            && $events[0]['data'] === ['pricing' => 11, 'content' => 'Claw hammer'];
    });

    $subscription->refresh();

    expect($subscription->cursor)->toBe((int) DB::table('impex_events')->max('id'))
        ->and($subscription->pending_at)->toBeNull()
        ->and(DeliveryModel::query()->sole()->status)->toBe(DeliveryStatus::Succeeded);
});

it('backs off a failing endpoint without moving the cursor, then switches it off', function (): void {
    Event::fake([SubscriptionDisabled::class]);
    Http::fake(['vendor.test/*' => Http::response('down', 503)]);
    config()->set('impex.subscriptions.delivery.breaker_threshold', 2);

    $subscription = subscribe(['endpoint' => ['url' => 'https://vendor.test/hooks']])['subscription'];

    FakeCatalog::put('ABC-1', ['price' => 10, 'name' => 'Hammer', 'category' => '/tools/']);
    touchProducts('ABC-1');
    detect();

    app(Dispatcher::class)->deliver($subscription->id);
    $subscription->refresh();

    expect($subscription->cursor)->toBe(0)
        ->and($subscription->failures)->toBe(1)
        ->and($subscription->paused_until?->isFuture())->toBeTrue()
        ->and($subscription->last_error['message'] ?? '')->toContain('503');

    // Still backing off: nothing is sent.
    app(Dispatcher::class)->deliver($subscription->id);
    Http::assertSentCount(1);

    $this->travel(1)->hours();
    app(Dispatcher::class)->deliver($subscription->id);

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Disabled);
    Event::assertDispatched(SubscriptionDisabled::class);
});

it('serves the same events from the feed, a page at a time', function (): void {
    $subscription = subscribe([])['subscription'];

    foreach (['A', 'B', 'C'] as $i => $sku) {
        FakeCatalog::put($sku, ['price' => $i, 'name' => $sku, 'category' => '/x/']);
    }

    touchProducts('A', 'B', 'C');
    detect();

    $first = app(Impex::class)->streams()->get('fixture.products');
    expect($first->key())->toBe('fixture.products');

    $page = $this->getJson("/impex/subscriptions/{$subscription->id}/events?limit=2")
        ->assertOk()
        ->assertJsonPath('has_more', true)
        ->assertJsonCount(2, 'data');

    $this->getJson("/impex/subscriptions/{$subscription->id}/events?after=".$page->json('cursor'))
        ->assertOk()
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('data.0.subject', 'C');
});

it('delivers every event of an append stream, in order, unfolded', function (): void {
    Http::fake(['vendor.test/*' => Http::response()]);

    $subscription = subscribe(['stream' => 'fixture.orders', 'endpoint' => ['url' => 'https://vendor.test/orders'], 'format' => 'full'])['subscription'];

    app(Impex::class)->streams()->publish('fixture.orders', 'order-1', 'order.placed', ['total' => 25]);
    app(Impex::class)->streams()->publish('fixture.orders', 'order-1', 'order.shipped', ['carrier' => 'UPS'], ['shipping']);
    detect('fixture.orders');

    app(Dispatcher::class)->deliver($subscription->id);

    Http::assertSent(function (Request $request): bool {
        $events = $request->data()['events'];

        return array_column($events, 'name') === ['order.placed', 'order.shipped']
            && $events[1]['data'] === ['carrier' => 'UPS']
            && $events[1]['topics'] === ['shipping'];
    });
});

it('detects a chunk with the same number of queries however many subscribers there are', function (): void {
    $count = function (int $subscribers): int {
        DB::table('impex_subscription_events')->delete();
        SubscriptionModel::query()->delete();

        for ($i = 0; $i < $subscribers; $i++) {
            subscribe($i % 2 === 0 ? [] : ['filter' => ['category' => '/tools/']]);
        }

        app(FanOut::class)->flush();

        // Under one insert chunk of matches, which is the only part of the
        // work that grows with the number of subscribers it reaches.
        foreach (range(1, 20) as $n) {
            FakeCatalog::put("SKU-{$n}", ['price' => $n + $subscribers, 'name' => "P{$n}", 'category' => '/tools/']);
        }

        touchProducts(...array_map(fn (int $n): string => "SKU-{$n}", range(1, 20)));

        FakeCatalog::$loads = 0;
        DB::flushQueryLog();
        DB::enableQueryLog();
        detect();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    // Prime the history first, so both measured runs find prior events.
    $count(1);

    $few = $count(2);
    $many = $count(40);

    expect($many)->toBe($few)
        // One snapshot load per chunk, never one per subject.
        ->and(FakeCatalog::$loads)->toBe(1);
});

it('exports everything a subscription covers and moves its cursor past it', function (): void {
    Storage::fake('local');
    $subscription = subscribe([])['subscription'];

    FakeCatalog::put('ABC-1', ['price' => 10, 'name' => 'Hammer', 'category' => '/tools/']);
    touchProducts('ABC-1');
    detect();

    $path = app(Exporter::class)->export($subscription->id);

    expect(Storage::disk('local')->get((string) $path))->toContain('"subject":"ABC-1"')
        ->and($subscription->refresh()->cursor)->toBe((int) DB::table('impex_events')->max('id'));
});

it('reports a lock it cannot release instead of failing a finished delivery', function (): void {
    Http::fake(['vendor.test/*' => Http::response()]);

    Cache::extend('unreleasable', fn (): Repository => Cache::repository(new class extends ArrayStore
    {
        public function lock($name, $seconds = 0, $owner = null): Lock
        {
            return new class($name, $seconds, $owner) extends Lock
            {
                public function acquire(): bool
                {
                    return true;
                }

                public function release(): bool
                {
                    throw new RuntimeException('cache went away');
                }

                public function forceRelease(): void {}

                protected function getCurrentOwner(): string
                {
                    return $this->owner;
                }
            };
        }
    }));
    config()->set('cache.stores.unreleasable', ['driver' => 'unreleasable']);
    config()->set('impex.cache.store', 'unreleasable');

    $reported = [];
    app(ExceptionHandler::class)->reportable(function (RuntimeException $e) use (&$reported): void {
        $reported[] = $e->getMessage();
    });

    $subscription = subscribe(['endpoint' => ['url' => 'https://vendor.test/hooks']])['subscription'];
    FakeCatalog::put('ABC-1', ['price' => 10, 'name' => 'Hammer', 'category' => '/tools/']);
    touchProducts('ABC-1');
    detect();

    expect(app(Dispatcher::class)->deliver($subscription->id))->toBe(1)
        ->and($subscription->refresh()->cursor)->toBeGreaterThan(0)
        ->and($reported)->toContain('cache went away');
});
