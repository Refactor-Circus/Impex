<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Actions;

use JayI\Impex\Domains\Run\Events\RunRetriedActionEvent;
use JayI\Impex\Domains\Run\Events\RunRetryingActionEvent;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Impex;

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
