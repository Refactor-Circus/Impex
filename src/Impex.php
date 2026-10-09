<?php

declare(strict_types=1);

namespace RefactorCircus\Impex;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use RefactorCircus\Impex\Domains\Artifact\Enums\ArtifactKind;
use RefactorCircus\Impex\Domains\Artifact\Services\PayloadStore;
use RefactorCircus\Impex\Domains\Batch\Models\BatchItemModel;
use RefactorCircus\Impex\Domains\Channel\Data\OutboundMessage;
use RefactorCircus\Impex\Domains\Channel\Data\Receipt;
use RefactorCircus\Impex\Domains\Channel\Services\ChannelRegistry;
use RefactorCircus\Impex\Domains\Channel\Services\ChannelSender;
use RefactorCircus\Impex\Domains\Channel\Services\TransportManager;
use RefactorCircus\Impex\Domains\Flow\Exceptions\DisabledFlowException;
use RefactorCircus\Impex\Domains\Flow\Services\FlowRegistry;
use RefactorCircus\Impex\Domains\Flow\Support\FlowArguments;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Message\Services\MessageRecorder;
use RefactorCircus\Impex\Domains\Message\Services\OutboundRecorder;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Services\Engine;
use RefactorCircus\Impex\Domains\Run\Support\RunHandle;
use RefactorCircus\Impex\Domains\Run\Support\RunQuery;
use RefactorCircus\Impex\Domains\Signal\Exceptions\CannotSignalTerminalRunException;
use RefactorCircus\Impex\Domains\Signal\Models\SignalModel;
use RefactorCircus\Impex\Domains\Subscription\Services\Streams;

/**
 * The package's public entry point.
 */
class Impex
{
    public function __construct(
        private readonly FlowRegistry $flows,
        private readonly Engine $engine,
        private readonly PayloadStore $payloads,
        private readonly MessageRecorder $messages,
        private readonly OutboundRecorder $outbound,
        private readonly ChannelRegistry $channels,
        private readonly ChannelSender $sender,
        private readonly TransportManager $transports,
        private readonly Streams $streams,
    ) {}

    /**
     * The flow catalogue.
     */
    public function flows(): FlowRegistry
    {
        return $this->flows;
    }

    /**
     * Start a run and queue its first drive.
     *
     * Returns immediately with a pending run — nothing is executed inline, so
     * a trigger endpoint can respond inside API Gateway's timeout however long
     * the flow takes to finish.
     *
     * @param  array<int|string, mixed>  $arguments  Positional, or keyed by
     *                                               parameter name to be applied
     *                                               as named arguments.
     * @param  array<string, string>  $tags
     * @param  iterable<int|string, Model>  $owners  models keyed by role
     * @param  string|null  $queue  The queue its jobs run on; null for the
     *                              flow's override, then the default.
     *
     * @throws DisabledFlowException when the flow is paused
     */
    public function run(
        string $slug,
        array $arguments = [],
        RunTrigger $trigger = RunTrigger::Code,
        ?string $idempotencyKey = null,
        array $tags = [],
        iterable $owners = [],
        ?string $version = null,
        DateTimeInterface|int|null $expiresAt = null,
        ?string $queue = null,
        ?string $connection = null,
    ): RunModel {
        $existing = $this->existingRun($slug, $idempotencyKey);

        if ($existing instanceof RunModel) {
            return $existing;
        }

        // Every trigger respects a paused flow — code, channels and the
        // schedule as well as the API.
        if (! $this->flows->enabled($slug)) {
            throw DisabledFlowException::slug($slug);
        }

        $class = $this->flows->class($slug);
        $override = $this->flows->override($slug);
        $arguments = FlowArguments::withDefaults($class, $arguments, $override->defaults ?? []);
        // Keys are kept rather than flattened: a caller may pass arguments by
        // name, which the engine applies as named arguments, so the names have
        // to survive into the stored payload and back out on replay. A plain
        // positional list has no string keys and is unaffected.
        $stored = $this->payloads->put($arguments, ArtifactKind::Payload);

        try {
            $run = RunModel::query()->create([
                'flow' => $slug,
                'flow_class' => $class,
                'status' => RunStatus::Pending,
                'trigger' => $trigger,
                'idempotency_key' => $idempotencyKey,
                'flow_version' => $version ?? $this->flows->version($slug),
                'input' => $stored['inline'],
                'input_artifact_id' => $stored['artifact_id'],
                'tags' => $tags === [] ? null : $tags,
                'expires_at' => $this->deadline($expiresAt),
                // The caller's queue first (a channel's own), then the flow's
                // override from the dashboard, then the engine default.
                'queue' => $queue ?? $override?->queue,
                'queue_connection' => $connection ?? $override?->queue_connection,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent redelivery won the insert between the lookup above
            // and this one, so its run is the run.
            return $this->existingRun($slug, $idempotencyKey) ?? throw $e;
        }

        foreach ($owners as $role => $owner) {
            $this->addOwner($run, $owner, is_string($role) ? $role : 'owner');
        }

        $this->engine->start($run);

        return $run;
    }

    /**
     * Start a run and drive it to completion in this process.
     *
     * For tests, and for short flows behind a request. Steps execute inline
     * rather than being queued, so nothing here needs a worker. A flow that
     * parks on a signal or a timer will not finish and is returned as it
     * stands; the budget is `impex.limits.sync_seconds`.
     *
     * @param  array<int|string, mixed>  $arguments  Positional, or keyed by
     *                                               parameter name.
     * @param  array<string, string>  $tags
     * @param  iterable<int|string, Model>  $owners
     */
    public function runSync(
        string $slug,
        array $arguments = [],
        RunTrigger $trigger = RunTrigger::Code,
        ?string $idempotencyKey = null,
        array $tags = [],
        iterable $owners = [],
        ?string $version = null,
        ?int $seconds = null,
    ): RunModel {
        $run = $this->run($slug, $arguments, $trigger, $idempotencyKey, $tags, $owners, $version);

        /** @var int $budget */
        $budget = $seconds ?? config('impex.limits.sync_seconds', 15);

        return $this->engine->driveToCompletion($run, $budget);
    }

    /**
     * Ask for runs.
     */
    public function query(): RunQuery
    {
        return new RunQuery;
    }

    /**
     * A run you can act on directly.
     */
    public function handle(RunModel|string $run): RunHandle
    {
        return new RunHandle($run instanceof RunModel ? $run : RunModel::query()->findOrFail($run));
    }

    /**
     * Give a model a stake in a run.
     *
     * No user, team or customer tables ship with this package: the host app
     * decides what those are, and the hierarchy between them lives there.
     */
    public function addOwner(RunModel $run, Model $owner, string $role = 'owner'): void
    {
        $run->owners()->firstOrCreate([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'role' => $role,
        ]);
    }

    /**
     * Deliver a signal to a run.
     *
     * Accepted by any non-terminal run — pending, running, or waiting. A signal
     * that arrives before the flow reaches its wait is held, not lost.
     *
     * @throws CannotSignalTerminalRunException if the run has finished
     */
    public function signal(RunModel $run, string $name, mixed $payload = null, ?string $idempotencyKey = null): SignalModel
    {
        return $this->engine->deliverSignal($run, $name, $payload, $idempotencyKey);
    }

    /**
     * Deliver a signal, treating a finished run as a no-op.
     *
     * Use this when losing a race with the run's own completion is expected
     * rather than exceptional.
     */
    public function signalIfRunning(RunModel $run, string $name, mixed $payload = null, ?string $idempotencyKey = null): bool
    {
        if ($run->status->isFinished()) {
            return false;
        }

        try {
            $this->engine->deliverSignal($run, $name, $payload, $idempotencyKey);
        } catch (CannotSignalTerminalRunException) {
            // The run finished between the check and the write.
            return false;
        }

        return true;
    }

    /**
     * Cancel a run that has not finished.
     */
    public function cancel(RunModel $run, ?string $reason = null): RunModel
    {
        if ($run->status->isFinished()) {
            return $run;
        }

        $run->update([
            'status' => RunStatus::Cancelled,
            'error' => $reason === null ? $run->error : ['message' => $reason],
            'finished_at' => now(),
        ]);

        return $run->refresh();
    }

    /**
     * Re-queue a drive for a run that stalled.
     */
    public function retry(RunModel $run): RunModel
    {
        if ($run->status === RunStatus::Failed) {
            $run->update(['status' => RunStatus::Running, 'finished_at' => null]);
        }

        $this->engine->dispatchDrive($run);

        return $run->refresh();
    }

    /**
     * The catalogue of channels, configured and stored.
     */
    public function channels(): ChannelRegistry
    {
        return $this->channels;
    }

    /**
     * The streams subscribers follow: register one, touch a snapshot
     * stream's subjects when they may have changed, or publish to an append
     * stream.
     */
    public function streams(): Streams
    {
        return $this->streams;
    }

    /**
     * The transports channels send through. extend() adds one.
     */
    public function transports(): TransportManager
    {
        return $this->transports;
    }

    /**
     * Send through an outbound channel, recorded in the ledger.
     *
     * An array is sent as JSON, a string as it is. A failed send — a refused
     * connection, a 500 — comes back as a failed receipt, not an exception,
     * so retrying is the caller's call.
     *
     * @param  OutboundMessage|array<int|string, mixed>|string  $message
     */
    public function send(string $channel, OutboundMessage|array|string $message): Receipt
    {
        $message = match (true) {
            $message instanceof OutboundMessage => $message,
            is_array($message) => OutboundMessage::json($message),
            default => new OutboundMessage(body: $message),
        };

        return $this->sender->send($channel, $message);
    }

    /**
     * An HTTP client whose traffic is recorded against a channel.
     *
     * Pass the run and step so each call is traceable to the work that made it.
     */
    public function http(string $channel, ?string $runId = null, ?string $stepId = null): PendingRequest
    {
        return $this->outbound->client($channel, $runId, $stepId);
    }

    /**
     * Record egress the HTTP middleware cannot see — a file drop, an SFTP put,
     * a message published to another system.
     *
     * @param  array<string, mixed>|null  $headers
     */
    public function record(
        string $channel,
        string $endpoint,
        ?string $body = null,
        string $transport = 'file',
        Direction $direction = Direction::Outbound,
        ?string $runId = null,
        ?array $headers = null,
    ): MessageModel {
        return $this->messages->record(
            direction: $direction,
            channel: $channel,
            transport: $transport,
            endpoint: $endpoint,
            body: $body,
            runId: $runId,
            headers: $headers,
        );
    }

    /**
     * Read a recorded message body back from the ledger.
     */
    public function body(MessageModel $message): ?string
    {
        return $this->messages->body($message);
    }

    /**
     * Stream a finished batch's items without holding them in memory.
     *
     * @return LazyCollection<int, BatchItemModel>
     */
    public function batchItems(string $batchId): LazyCollection
    {
        return BatchItemModel::query()
            ->where('batch_id', $batchId)
            ->orderBy('id')
            ->lazyById(1000);
    }

    /**
     * Read a run's result, from the inline column or the artifact disk.
     */
    public function result(RunModel $run): mixed
    {
        return $this->payloads->get($run->result, $run->result_artifact_id);
    }

    /**
     * The run a flow already started under an idempotency key.
     *
     * Scoped to the flow, because two channels may legitimately deliver the
     * same key to different flows.
     */
    private function existingRun(string $slug, ?string $idempotencyKey): ?RunModel
    {
        if ($idempotencyKey === null) {
            return null;
        }

        return RunModel::query()
            ->where('flow', $slug)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Normalise a deadline given as a moment or a number of seconds.
     */
    private function deadline(DateTimeInterface|int|null $expiresAt): ?DateTimeInterface
    {
        if ($expiresAt === null) {
            /** @var int|null $default */
            $default = config('impex.deadlines.run');

            return $default === null ? null : Carbon::now()->addSeconds($default);
        }

        return is_int($expiresAt) ? Carbon::now()->addSeconds($expiresAt) : $expiresAt;
    }
}
