<?php

declare(strict_types=1);

namespace Workbench\App\Bench;

use Closure;
use Illuminate\Support\Facades\DB;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Services\ChannelRegistry;
use RefactorCircus\Impex\Domains\Subscription\Actions\CreateSubscriberAction;
use RefactorCircus\Impex\Domains\Subscription\Actions\CreateSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\Detector;
use RefactorCircus\Impex\Domains\Subscription\Services\Dispatcher;
use RefactorCircus\Impex\Impex;

/**
 * Measures the subscription pipeline end to end on synthetic data: touching,
 * detection with fan-out, a re-touch where most subjects did not change, and
 * delivery against a faked endpoint.
 */
final class SubscriptionBench
{
    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    public function run(int $subjects, int $subscriptions, float $changed): array
    {
        config()->set('queue.default', 'null');
        config()->set('impex.outbound.guard', false);

        $impex = app(Impex::class);
        $impex->streams()->register(BenchStream::class);
        $impex->transports()->extend('bench', fn ($app): InstantTransport => $app->make(InstantTransport::class));
        BenchStream::$generation = [];
        InstantTransport::$sent = 0;

        $this->subscribe($subscriptions, $subjects);
        ChannelModel::query()->update(['transport' => 'bench']);
        app(ChannelRegistry::class)->flush();
        $keys = array_map(fn (int $n): string => 'SKU-'.$n, range(1, $subjects));
        $rows = [];

        $touch = $this->time(fn () => $impex->streams()->touch('bench.products', $keys));
        $rows[] = ['Touch '.number_format($subjects), $this->secs($touch), $this->rate($subjects, $touch).' subjects/s', $this->memory()];

        $detect = $this->time(fn () => $this->detect());
        $events = DB::table('impex_events')->count();
        $routed = DB::table('impex_subscription_events')->count();
        $rows[] = ['Detect (first look)', $this->secs($detect), $this->rate($subjects, $detect).' subjects/s · '.number_format($events).' events · '.number_format($routed).' routed', $this->memory()];

        $changes = (int) round($subjects * $changed);

        foreach (array_slice($keys, 0, $changes) as $key) {
            BenchStream::$generation[$key] = 1;
        }

        $impex->streams()->touch('bench.products', $keys);
        $retouch = $this->time(fn () => $this->detect());
        $rows[] = [
            sprintf('Re-detect (%s changed)', number_format($changes)),
            $this->secs($retouch),
            $this->rate($subjects, $retouch).' subjects/s · '.number_format(DB::table('impex_events')->count() - $events).' new events',
            $this->memory(),
        ];

        $delivered = 0;
        $deliver = $this->time(function () use (&$delivered): void {
            foreach (SubscriptionModel::query()->pluck('id') as $id) {
                $delivered += app(Dispatcher::class)->deliver((string) $id);
            }
        });
        $rows[] = ['Deliver (instant endpoint)', $this->secs($deliver), $this->rate($delivered, $deliver).' events/s · '.number_format(InstantTransport::$sent).' requests', $this->memory()];

        return $rows;
    }

    private function subscribe(int $count, int $subjects): void
    {
        for ($i = 0; $i < $count; $i++) {
            $kind = $i % 10;
            $data = ['stream' => 'bench.products', 'endpoint' => ['url' => 'https://bench.test/'.$i]];

            if ($kind >= 5 && $kind < 9) {
                $data['filter'] = ['category' => '/c'.($i % 50).'/'];
            } elseif ($kind === 9) {
                $data['subjects'] = array_map(fn (int $n): string => 'SKU-'.$n, range(1 + $i, min($subjects, 100 + $i)));
            }

            if ($i % 3 === 0) {
                $data['topics'] = ['pricing'];
            }

            app(CreateSubscriptionAction::class)->execute(
                app(CreateSubscriberAction::class)->execute(['name' => 'Bench '.$i]),
                $data,
            );
        }
    }

    private function detect(): void
    {
        while (app(Detector::class)->run('bench.products') > 0) {
            //
        }
    }

    private function time(Closure $work): float
    {
        $started = microtime(true);
        $work();

        return microtime(true) - $started;
    }

    private function memory(): string
    {
        return number_format(memory_get_peak_usage(true) / 1048576).' MB';
    }

    private function secs(float $seconds): string
    {
        return number_format($seconds, 2).'s';
    }

    private function rate(int $count, float $seconds): string
    {
        return number_format($seconds > 0 ? $count / $seconds : 0);
    }
}
