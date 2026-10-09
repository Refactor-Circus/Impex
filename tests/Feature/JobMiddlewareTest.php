<?php

declare(strict_types=1);

use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RefactorCircus\Impex\Contracts\JobMiddlewareFactory;
use RefactorCircus\Impex\Impex;
use RefactorCircus\Impex\Jobs\DeliverSubscription;
use RefactorCircus\Impex\Jobs\DriveRun;
use RefactorCircus\Impex\Jobs\ExecuteStep;
use RefactorCircus\Impex\Tests\Fixtures\Calls;
use RefactorCircus\Impex\Tests\Fixtures\LinearFlow;

final class RecordJobMiddleware
{
    public function handle(object $job, Closure $next): mixed
    {
        Calls::record('middleware:'.class_basename($job));

        return $next($job);
    }
}

final class OneDeliveryPerSubscription implements JobMiddlewareFactory
{
    public function middleware(object $job): array
    {
        return $job instanceof DeliverSubscription ? [new WithoutOverlapping($job->subscriptionId)] : [];
    }
}

beforeEach(function (): void {
    Calls::reset();
});

it('gives every job the wildcard middleware, then its own class\'s', function (): void {
    config()->set('impex.jobs.middleware', [
        '*' => [RecordJobMiddleware::class],
        DeliverSubscription::class => [[RateLimited::class, 'impex-deliveries'], OneDeliveryPerSubscription::class],
    ]);

    $delivery = (new DeliverSubscription('sub-1'))->middleware();

    expect($delivery)->toHaveCount(3)
        ->and($delivery[0])->toBeInstanceOf(RecordJobMiddleware::class)
        ->and($delivery[1])->toBeInstanceOf(RateLimited::class)
        ->and($delivery[2])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($delivery[2]->key)->toBe('sub-1')
        ->and((new DriveRun('run-1'))->middleware())->toHaveCount(1);
});

it('runs the configured middleware around the engine\'s jobs', function (): void {
    config()->set('queue.default', 'sync');
    config()->set('impex.flows', ['linear' => LinearFlow::class]);
    config()->set('impex.jobs.middleware', [
        DriveRun::class => [RecordJobMiddleware::class],
        ExecuteStep::class => [RecordJobMiddleware::class],
    ]);

    app(Impex::class)->run('linear', [1]);

    expect(Calls::count('middleware:DriveRun'))->toBeGreaterThan(0)
        ->and(Calls::count('middleware:ExecuteStep'))->toBeGreaterThan(0);
});

it('has no middleware unless some is configured', function (): void {
    expect((new DriveRun('run-1'))->middleware())->toBe([]);
});

it('refuses an entry that names no class', function (): void {
    config()->set('impex.jobs.middleware', ['*' => ['not-a-class']]);

    (new DriveRun('run-1'))->middleware();
})->throws(InvalidArgumentException::class);
