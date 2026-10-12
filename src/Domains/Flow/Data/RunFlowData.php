<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Data;

use RefactorCircus\Keystone\Data\Data;

/**
 * Input for RunFlowAction: the flow's arguments and how the run starts.
 */
final readonly class RunFlowData extends Data
{
    /**
     * @param  array<int, mixed>  $arguments  Positional, in order.
     * @param  array<string, string>  $tags
     * @param  bool  $wait  Drive the run inline, bounded by
     *                      impex.limits.sync_seconds.
     */
    public function __construct(
        public array $arguments = [],
        public ?string $idempotencyKey = null,
        public array $tags = [],
        public ?string $version = null,
        public ?int $expiresIn = null,
        public bool $wait = false,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'arguments' => ['sometimes', 'array'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:191'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:191'],
            'version' => ['sometimes', 'nullable', 'string', 'max:64'],
            'expires_in' => ['sometimes', 'integer', 'min:1'],
            'wait' => ['sometimes', 'boolean'],
        ];
    }
}
