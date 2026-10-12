<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Data;

use RefactorCircus\Keystone\Data\Data;

/**
 * Input for SignalRunAction: the signal, the data it hands the flow, and
 * whether a finished run is a no-op rather than a conflict.
 */
final readonly class SignalRunData extends Data
{
    public function __construct(
        public string $name,
        public mixed $payload = null,
        public ?string $idempotencyKey = null,
        public bool $ifRunning = false,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'payload' => ['sometimes', 'nullable'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:191'],
            'if_running' => ['sometimes', 'boolean'],
        ];
    }
}
