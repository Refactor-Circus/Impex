<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Signal\Actions;

use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Signal\Events\RunSignalledActionEvent;
use RefactorCircus\Impex\Domains\Signal\Events\RunSignallingActionEvent;
use RefactorCircus\Impex\Domains\Signal\Models\SignalModel;
use RefactorCircus\Impex\Impex;

final class SignalRunAction
{
    public function __construct(private readonly Impex $impex) {}

    /**
     * @return array<string, mixed>
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

    /**
     * Deliver the signal.
     *
     * Returns null when `if_running` was set and the run had already finished —
     * a no-op the caller asked for, rather than a conflict.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(RunModel $run, array $data): ?SignalModel
    {
        RunSignallingActionEvent::dispatch($run, $data);

        $result = $this->perform($run, $data);

        RunSignalledActionEvent::dispatch($run, $result);

        return $result;
    }

    /**
     * Deliver the signal.
     *
     * Returns null when `if_running` was set and the run had already finished —
     * a no-op the caller asked for, rather than a conflict.
     *
     * @param  array<string, mixed>  $data
     */
    private function perform(RunModel $run, array $data): ?SignalModel
    {
        /** @var string $name */
        $name = $data['name'];

        /** @var string|null $key */
        $key = $data['idempotency_key'] ?? null;

        if (($data['if_running'] ?? false) === true) {
            return $this->impex->signalIfRunning($run, $name, $data['payload'] ?? null, $key)
                ? $run->signals()->where('name', $name)->latest('delivered_at')->first()
                : null;
        }

        return $this->impex->signal($run, $name, $data['payload'] ?? null, $key);
    }
}
