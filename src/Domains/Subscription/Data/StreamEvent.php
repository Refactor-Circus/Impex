<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Data;

use Illuminate\Support\Carbon;
use RefactorCircus\Impex\Domains\Subscription\Enums\EventKind;
use stdClass;

/**
 * One row of the event sequence.
 */
final readonly class StreamEvent
{
    /**
     * @param  array<string, mixed>|null  $payload  An append event's data.
     */
    public function __construct(
        public int $id,
        public string $stream,
        public string $subjectKey,
        public EventKind $kind,
        public int $topics,
        public Carbon $occurredAt,
        public ?string $name = null,
        public ?array $payload = null,
    ) {}

    /**
     * A row of `impex_events` as the query builder returns it.
     */
    public static function fromRow(stdClass $row): self
    {
        /** @var array<string, mixed>|null $payload */
        $payload = is_string($row->payload ?? null) ? json_decode($row->payload, true) : null;

        return new self(
            id: (int) $row->id,
            stream: (string) $row->stream,
            subjectKey: (string) $row->subject_key,
            kind: EventKind::from((string) $row->kind),
            topics: (int) $row->topics,
            occurredAt: Carbon::parse((string) $row->occurred_at),
            name: is_string($row->name ?? null) ? $row->name : null,
            payload: $payload,
        );
    }

    /**
     * The same event carrying the topics of every event it stands for.
     */
    public function withTopics(int $topics): self
    {
        return new self($this->id, $this->stream, $this->subjectKey, $this->kind, $topics, $this->occurredAt, $this->name, $this->payload);
    }
}
