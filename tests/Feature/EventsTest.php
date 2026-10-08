<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use JayI\Foundation\Contracts\ActionFinishedEvent;
use JayI\Foundation\Contracts\ActionStartingEvent;
use JayI\Foundation\Contracts\ModelLifecycleEvent;
use JayI\Impex\Domains\Flow\Actions\ListFlowsAction;
use JayI\Impex\Domains\Flow\Actions\RunFlowAction;
use JayI\Impex\Domains\Flow\Events\FlowRanActionEvent;
use JayI\Impex\Domains\Flow\Events\FlowRunningActionEvent;
use JayI\Impex\Domains\Flow\Exceptions\UnknownFlowException;
use JayI\Impex\Domains\Run\Actions\ShowRunAction;
use JayI\Impex\Domains\Run\Events\RunCreatingEvent;
use JayI\Impex\Domains\Run\Events\RunShownActionEvent;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Tests\Fixtures\Calls;
use JayI\Impex\Tests\Fixtures\LinearFlow;

beforeEach(function (): void {
    Calls::reset();
    config()->set('queue.default', 'sync');
    config()->set('impex.flows', ['linear' => LinearFlow::class]);
});

/**
 * Record every event of a kind, in order.
 *
 * @param  class-string  $kind
 * @return ArrayObject<int, object>
 */
function recordImpexEvents(string $kind): ArrayObject
{
    /** @var ArrayObject<int, object> $seen */
    $seen = new ArrayObject;

    Event::listen($kind, function (object $event) use ($seen): void {
        $seen->append($event);
    });

    return $seen;
}

it('maps every Eloquent hook of every model to its own event', function (): void {
    $hooks = ['retrieved', 'creating', 'created', 'updating', 'updated', 'saving', 'saved', 'deleting', 'deleted', 'replicating'];

    $models = array_map(
        fn (string $path): string => 'JayI\\Impex\\Domains\\'.basename(dirname($path, 2)).'\\Models\\'.basename($path, '.php'),
        glob(dirname(__DIR__, 2).'/src/Domains/*/Models/*.php') ?: [],
    );

    foreach ($models as $class) {
        $map = (fn (): array => $this->dispatchesEvents)->call(new $class);

        expect(array_keys($map))->toEqualCanonicalizing($hooks)
            ->and(array_map(fn (string $event): bool => is_subclass_of($event, ModelLifecycleEvent::class), array_values($map)))
            ->not->toContain(false);
    }

    expect($models)->toHaveCount(14);
});

it('fires model events as a run executes', function (): void {
    $seen = recordImpexEvents(ModelLifecycleEvent::class);

    $run = app(RunFlowAction::class)->execute('linear', ['arguments' => [1]]);
    RunModel::query()->find($run->getKey());

    $fired = collect($seen)
        ->map(fn (ModelLifecycleEvent $event): string => class_basename($event->model()).'.'.$event->hook())
        ->unique();

    // Steps are claimed and completed with atomic query-builder updates, which
    // Eloquent does not turn into model events; their creation still is one.
    expect($fired)->toContain('RunModel.creating', 'RunModel.created', 'RunModel.updated', 'RunModel.retrieved', 'RunStepModel.creating', 'RunStepModel.created', 'RunStepModel.retrieved');
});

it('lets a creating listener stop a run being recorded', function (): void {
    Event::listen(RunCreatingEvent::class, fn (): bool => false);

    expect(RunModel::query()->create(['flow' => 'linear', 'flow_class' => LinearFlow::class])->exists)->toBeFalse()
        ->and(RunModel::query()->count())->toBe(0);
});

it('gives every action exactly one start and one finish event', function (): void {
    $actions = glob(dirname(__DIR__, 2).'/src/Domains/*/Actions/*Action.php') ?: [];
    $unpaired = [];

    foreach ($actions as $path) {
        $source = (string) file_get_contents($path);

        preg_match_all('/([A-Za-z]+ActionEvent)::dispatch/', $source, $matches);

        $kinds = array_map(
            // Each action's events live in its domain's Events namespace.
            fn (string $event): string => is_subclass_of('JayI\\Impex\\Domains\\'.basename(dirname($path, 2)).'\\Events\\'.$event, ActionStartingEvent::class) ? 'start' : 'finish',
            $matches[1],
        );

        sort($kinds);

        if ($kinds !== ['finish', 'start']) {
            $unpaired[] = basename($path, '.php');
        }
    }

    expect($actions)->toHaveCount(35)
        ->and($unpaired)->toBe([]);
});

it('starts and finishes each action, carrying its input and result', function (): void {
    $starts = recordImpexEvents(ActionStartingEvent::class);
    $finishes = recordImpexEvents(ActionFinishedEvent::class);

    $run = app(RunFlowAction::class)->execute('linear', ['arguments' => [1]]);
    app(ShowRunAction::class)->execute($run);
    app(ListFlowsAction::class)->execute();

    expect(array_map(fn (object $event): string => class_basename($event), $starts->getArrayCopy()))
        ->toBe(['FlowRunningActionEvent', 'RunShowingActionEvent', 'FlowsListingActionEvent'])
        ->and(array_map(fn (object $event): string => class_basename($event), $finishes->getArrayCopy()))
        ->toBe(['FlowRanActionEvent', 'RunShownActionEvent', 'FlowsListedActionEvent']);

    expect($starts[0])->toBeInstanceOf(FlowRunningActionEvent::class)
        ->and($starts[0]->slug)->toBe('linear')
        ->and($finishes[0])->toBeInstanceOf(FlowRanActionEvent::class)
        ->and($finishes[0]->run->is($run))->toBeTrue()
        ->and($finishes[1])->toBeInstanceOf(RunShownActionEvent::class);
});

it('starts a refused action but never finishes it', function (): void {
    $starts = recordImpexEvents(FlowRunningActionEvent::class);
    $finishes = recordImpexEvents(FlowRanActionEvent::class);

    expect(fn (): RunModel => app(RunFlowAction::class)->execute('missing'))->toThrow(UnknownFlowException::class);

    expect($starts)->toHaveCount(1)
        ->and($finishes)->toHaveCount(0);
});

it('finishes an action only once the transaction around it commits', function (): void {
    $finishes = recordImpexEvents(FlowRanActionEvent::class);

    DB::transaction(function () use ($finishes): void {
        app(RunFlowAction::class)->execute('linear', ['arguments' => [1]]);

        expect($finishes)->toHaveCount(0);
    });

    expect($finishes)->toHaveCount(1);
});
