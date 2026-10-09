<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('RefactorCircus\Impex')
    ->toUseStrictTypes();

arch('models are final and named for their entity')
    ->expect([
        'RefactorCircus\Impex\Domains\Artifact\Models',
        'RefactorCircus\Impex\Domains\Batch\Models',
        'RefactorCircus\Impex\Domains\Channel\Models',
        'RefactorCircus\Impex\Domains\Flow\Models',
        'RefactorCircus\Impex\Domains\Message\Models',
        'RefactorCircus\Impex\Domains\Run\Models',
        'RefactorCircus\Impex\Domains\Signal\Models',
        'RefactorCircus\Impex\Domains\Subscription\Models',
    ])
    ->classes()
    ->toBeFinal()
    ->toHaveSuffix('Model');

arch('jobs carry identifiers only, never payloads')
    ->expect('RefactorCircus\Impex\Jobs')
    ->toOnlyUse([
        'Illuminate\Bus\Queueable',
        'Illuminate\Contracts\Queue\Interruptible',
        'Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing',
        'Illuminate\Contracts\Queue\ShouldQueue',
        'Illuminate\Foundation\Bus\Dispatchable',
        'Illuminate\Queue\InteractsWithQueue',
        'Illuminate\Queue\SerializesModels',
        'RefactorCircus\Impex\Domains\Batch\Services\BatchRunner',
        'RefactorCircus\Impex\Domains\Run\Services\Engine',
        'RefactorCircus\Impex\Domains\Subscription\Services\Detector',
        'RefactorCircus\Impex\Domains\Subscription\Services\Dispatcher',
        'RefactorCircus\Impex\Domains\Subscription\Services\Exporter',
        'RefactorCircus\Impex\Domains\Subscription\Services\SubscriptionJobs',
        'RefactorCircus\Impex\Support\Concerns\UsesConfiguredMiddleware',
        'RefactorCircus\Impex\Support\JobMiddleware',
    ]);

// Parity is the default, with declared exceptions. An Action reachable over
// HTTP but not MCP is a test failure unless it is listed here with a reason.
arch('every use case is reachable from both the HTTP API and MCP')
    ->expect(fn (): array => parityGaps())
    ->toBeEmpty();
