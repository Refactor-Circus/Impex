<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use RefactorCircus\Impex\Domains\Run\Events\RunRetriedActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunRetryingActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Impex;

final class RetryRunAction
{
    public function __construct(private readonly Impex $impex) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    public function execute(RunModel $run): RunModel
    {
        RunRetryingActionEvent::dispatch($run);

        $result = $this->perform($run);

        RunRetriedActionEvent::dispatch($result);

        return $result;
    }

    private function perform(RunModel $run): RunModel
    {
        return $this->impex->retry($run);
    }
}
