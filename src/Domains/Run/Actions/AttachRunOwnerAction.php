<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use RefactorCircus\Impex\Domains\Run\Events\RunOwnerAttachedActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunOwnerAttachingActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Run\Models\RunOwnerModel;

final class AttachRunOwnerAction
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'owner_type' => ['required', 'string', 'max:191'],
            'owner_id' => ['required', 'string', 'max:64'],
            'role' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(RunModel $run, array $data): RunOwnerModel
    {
        RunOwnerAttachingActionEvent::dispatch($run, $data);

        $result = $this->perform($run, $data);

        RunOwnerAttachedActionEvent::dispatch($run, $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function perform(RunModel $run, array $data): RunOwnerModel
    {
        /** @var RunOwnerModel $owner */
        $owner = $run->owners()->firstOrCreate([
            'owner_type' => $data['owner_type'],
            'owner_id' => $data['owner_id'],
            'role' => $data['role'],
        ]);

        return $owner;
    }
}
