<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use RefactorCircus\Impex\Domains\Run\Events\RunCancelledActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunCancellingActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Impex;

final class CancelRunAction
{
    public function __construct(private readonly Impex $impex) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(RunModel $run, array $data = []): RunModel
    {
        RunCancellingActionEvent::dispatch($run, is_string($data['reason'] ?? null) ? $data['reason'] : null);

        $result = $this->perform($run, $data);

        RunCancelledActionEvent::dispatch($result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function perform(RunModel $run, array $data = []): RunModel
    {
        /** @var string|null $reason */
        $reason = $data['reason'] ?? null;

        return $this->impex->cancel($run, $reason);
    }
}
