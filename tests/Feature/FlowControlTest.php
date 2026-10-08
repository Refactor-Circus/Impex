<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use JayI\Impex\Domains\Artifact\Enums\ArtifactKind;
use JayI\Impex\Domains\Artifact\Models\ArtifactModel;
use JayI\Impex\Domains\Artifact\Services\PayloadStore;
use JayI\Impex\Domains\Flow\Exceptions\DisabledFlowException;
use JayI\Impex\Domains\Flow\Models\FlowOverrideModel;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Impex;
use JayI\Impex\Tests\Fixtures\LinearFlow;
use JayI\Impex\Tests\Fixtures\PayloadFlow;

beforeEach(function (): void {
    Queue::fake();
    config()->set('impex.flows', ['linear' => LinearFlow::class, 'ingest' => PayloadFlow::class]);
});

it('refuses to start a paused flow from code, not only from the API', function (): void {
    FlowOverrideModel::query()->create(['slug' => 'linear', 'enabled' => false]);

    app(Impex::class)->run('linear', [1]);
})->throws(DisabledFlowException::class);

it('tells a sender to retry later while the channel\'s flow is paused', function (): void {
    config()->set('impex.channels', ['supplier' => ['signing_secret' => 'shhh', 'flow' => 'ingest', 'idempotency_header' => 'X-Request-Id']]);
    FlowOverrideModel::query()->create(['slug' => 'ingest', 'enabled' => false]);

    $body = (string) json_encode(['sku' => 'A']);
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, 'shhh'),
        'HTTP_X_REQUEST_ID' => 'req-1',
    ];

    $this->call('POST', '/impex/channels/supplier', server: $server, content: $body)
        ->assertStatus(503)
        ->assertHeader('Retry-After');

    expect(RunModel::query()->count())->toBe(0)
        ->and(MessageModel::query()->count())->toBe(1);

    // Back on: the retry starts one run, and the ledger keeps one row.
    FlowOverrideModel::query()->where('slug', 'ingest')->update(['enabled' => true]);

    $this->call('POST', '/impex/channels/supplier', server: $server, content: $body)->assertStatus(202);

    expect(RunModel::query()->count())->toBe(1)
        ->and(MessageModel::query()->count())->toBe(1);
});

it('runs a flow on the queue its override names, unless the caller names one', function (): void {
    FlowOverrideModel::query()->create(['slug' => 'linear', 'queue' => 'slow', 'queue_connection' => 'sqs']);

    $run = app(Impex::class)->run('linear', [1]);
    $explicit = app(Impex::class)->run('linear', [2], queue: 'channel-queue');

    expect($run->queue)->toBe('slow')
        ->and($run->queue_connection)->toBe('sqs')
        ->and($explicit->queue)->toBe('channel-queue');
});

it('prunes finished runs with their steps, and expired artifacts with their files', function (): void {
    Storage::fake('local');
    config()->set('impex.artifacts.inline_threshold', 10);

    $old = RunModel::query()->create([
        'flow' => 'linear',
        'flow_class' => LinearFlow::class,
        'status' => RunStatus::Completed,
        'trigger' => 'code',
        'finished_at' => now()->subDays(200),
    ]);
    RunStepModel::query()->create(['run_id' => $old->id, 'phase' => 'forward', 'sequence' => 1, 'type' => 'action', 'name' => 'x', 'status' => 'completed']);

    $artifact = app(PayloadStore::class)->put(str_repeat('x', 100), ArtifactKind::Payload);
    $stored = ArtifactModel::query()->findOrFail($artifact['artifact_id']);
    $stored->update(['expires_at' => now()->subDay()]);

    Storage::disk('local')->assertExists($stored->path);

    $this->artisan('impex:prune')->assertSuccessful();

    expect(RunModel::query()->count())->toBe(0)
        ->and(RunStepModel::query()->count())->toBe(0)
        ->and(ArtifactModel::query()->count())->toBe(0);

    Storage::disk('local')->assertMissing($stored->path);
});
