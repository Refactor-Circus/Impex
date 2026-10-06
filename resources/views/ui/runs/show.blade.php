@use(JayI\Impex\Atrium\Badges)
@use(JayI\Impex\Domains\Run\Enums\RunStatus)
@use(JayI\Impex\Domains\Run\Enums\StepPhase)
@use(JayI\Impex\Domains\Run\Enums\StepStatus)
@use(JayI\Impex\Domains\Message\Models\MessageModel)
@use(JayI\Impex\Domains\Run\Models\RunOwnerModel)
@use(JayI\Impex\Domains\Run\Models\RunStepModel)
@use(JayI\Impex\Domains\Signal\Models\SignalModel)

<x-atrium::layout :title="$run->flow">
    <x-atrium::page-header :title="$run->flow">
        <x-slot:actions>
            @include('impex::ui.partials.status-dot', ['status' => $run->status, 'testid' => 'run-status'])

            @unless ($run->status->isFinished())
                @impexCan('cancel', $run)
                    <form method="POST" action="{{ route('atrium.impex.runs.cancel', $run) }}">
                        @csrf
                        <x-atrium::icon-button icon="stop-circle" :label="__('impex::impex.cancel')" variant="outline" type="submit" data-testid="cancel-run" />
                    </form>
                @endimpexCan
            @endunless

            @impexCan('retry', $run)
                <form method="POST" action="{{ route('atrium.impex.runs.retry', $run) }}">
                    @csrf
                    <x-atrium::icon-button icon="arrow-path" :label="__('impex::impex.retry')" variant="outline" type="submit" data-testid="retry-run" />
                </form>
            @endimpexCan
        </x-slot:actions>
    </x-atrium::page-header>

    <div class="mt-5 flex flex-col gap-5">
        <x-atrium::flash />

        <x-atrium::card>
            <x-atrium::description-list>
                <x-atrium::description-list.item :term="__('impex::impex.run_id')" class="font-mono">{{ $run->getKey() }}</x-atrium::description-list.item>
                <x-atrium::description-list.item :term="__('impex::impex.trigger')">{{ $run->trigger->value }}</x-atrium::description-list.item>

                @if ($run->idempotency_key)
                    <x-atrium::description-list.item :term="__('impex::impex.idempotency_key')" class="font-mono">{{ $run->idempotency_key }}</x-atrium::description-list.item>
                @endif

                <x-atrium::description-list.item :term="__('impex::impex.started')">{{ $run->started_at?->diffForHumans() ?? __('impex::impex.none') }}</x-atrium::description-list.item>
                <x-atrium::description-list.item :term="__('impex::impex.finished')">{{ $run->finished_at?->diffForHumans() ?? __('impex::impex.none') }}</x-atrium::description-list.item>

                @if ($run->parent_run_id)
                    <x-atrium::description-list.item :term="__('impex::impex.parent')">
                        <a class="underline-offset-2 hover:underline"
                           href="{{ route('atrium.impex.runs.show', $run->parent_run_id) }}">{{ $run->parent_run_id }}</a>
                    </x-atrium::description-list.item>
                @endif
            </x-atrium::description-list>
        </x-atrium::card>

        @if ($run->error)
            <x-atrium::alert variant="danger" :title="$run->error['class'] ?? __('impex::impex.status')">
                {{ $run->error['message'] ?? '' }}
            </x-atrium::alert>
        @endif

        @if ($run->status === RunStatus::Waiting)
            @impexCan('create', SignalModel::class, [$run])
                <x-atrium::card :title="__('impex::impex.send_signal')" data-testid="signal-card">
                    <form method="POST" action="{{ route('atrium.impex.runs.signal', $run) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <x-atrium::form.input name="name" :label="__('impex::impex.signal_name')" required class="w-56" />
                        <x-atrium::form.input name="payload" :label="__('impex::impex.signal_payload')" class="w-72" />
                        <x-atrium::icon-button icon="paper-airplane" :label="__('impex::impex.send')" variant="primary" type="submit" data-testid="send-signal" />
                    </form>
                </x-atrium::card>
            @endimpexCan
        @endif

        @impexCan('viewAny', RunStepModel::class, [$run])
            <x-atrium::card :title="__('impex::impex.steps')" data-testid="steps-card">
                @if ($steps->isEmpty())
                    <x-atrium::empty-state :title="__('impex::impex.no_steps')" />
                @else
                    <ul class="flex flex-col gap-3">
                        @foreach ($steps as $step)
                            @php($isRollback = $step->phase === StepPhase::Rollback)

                            <li @class([
                                'rounded-radius border p-3',
                                'border-outline dark:border-outline-dark' => ! $isRollback,
                                'border-dashed border-warning' => $isRollback,
                            ])>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-sm">{{ $step->name }}</span>

                                    @include('impex::ui.partials.status-dot', ['status' => $step->status])

                                    @if ($isRollback)
                                        <x-atrium::badge variant="warning">{{ __('impex::impex.rollback') }}</x-atrium::badge>
                                    @endif

                                    @if ($step->undone && $step->status !== StepStatus::Undone)
                                        <x-atrium::badge>{{ __('impex::impex.undone') }}</x-atrium::badge>
                                    @endif
                                </div>

                                <p class="mt-1 text-xs text-on-surface dark:text-on-surface-dark">
                                    #{{ $step->sequence }} &middot; {{ $step->type->value }}
                                    @if ($step->attempts > 1)
                                        &middot; {{ __('impex::impex.attempts', ['count' => $step->attempts]) }}
                                    @endif
                                    @if ($step->resumptions > 0)
                                        &middot; {{ __('impex::impex.resumed', ['count' => $step->resumptions]) }}
                                    @endif
                                    @if ($step->result_artifact_id)
                                        &middot; {{ __('impex::impex.result_on_disk') }}
                                    @endif
                                </p>

                                @if ($step->error)
                                    <p class="mt-1 text-xs text-danger">{{ $step->error['message'] ?? '' }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-atrium::card>
        @endimpexCan

        @impexCan('viewAny', RunOwnerModel::class, [$run])
            <x-atrium::card :title="__('impex::impex.owners')" data-testid="owners-card">
                @if ($run->owners->isEmpty())
                    <x-atrium::empty-state :title="__('impex::impex.no_owners')" />
                @else
                    <x-atrium::table compact>
                        <x-slot:head>
                            <x-atrium::table.row>
                                <x-atrium::table.cell heading>{{ __('impex::impex.role') }}</x-atrium::table.cell>
                                <x-atrium::table.cell heading>{{ __('impex::impex.type') }}</x-atrium::table.cell>
                                <x-atrium::table.cell heading>{{ __('impex::impex.id') }}</x-atrium::table.cell>
                            </x-atrium::table.row>
                        </x-slot:head>

                        @foreach ($run->owners as $owner)
                            <x-atrium::table.row>
                                <x-atrium::table.cell>{{ $owner->role }}</x-atrium::table.cell>
                                <x-atrium::table.cell class="font-mono text-xs">{{ $owner->owner_type }}</x-atrium::table.cell>
                                <x-atrium::table.cell class="font-mono text-xs">{{ $owner->owner_id }}</x-atrium::table.cell>
                            </x-atrium::table.row>
                        @endforeach
                    </x-atrium::table>
                @endif
            </x-atrium::card>
        @endimpexCan

        @impexCan('viewAny', MessageModel::class)
            <x-atrium::card :title="__('impex::impex.messages')" data-testid="messages-card">
                @if ($messages->isEmpty())
                    <x-atrium::empty-state :title="__('impex::impex.no_messages')" />
                @else
                    <x-atrium::table compact>
                        <x-slot:head>
                            <x-atrium::table.row>
                                <x-atrium::table.cell heading>{{ __('impex::impex.direction') }}</x-atrium::table.cell>
                                <x-atrium::table.cell heading>{{ __('impex::impex.channel') }}</x-atrium::table.cell>
                                <x-atrium::table.cell heading>{{ __('impex::impex.endpoint') }}</x-atrium::table.cell>
                                <x-atrium::table.cell heading>{{ __('impex::impex.when') }}</x-atrium::table.cell>
                            </x-atrium::table.row>
                        </x-slot:head>

                        @foreach ($messages as $message)
                            <x-atrium::table.row>
                                <x-atrium::table.cell>
                                    <x-atrium::badge :variant="Badges::forDirection($message->direction)">{{ $message->direction->value }}</x-atrium::badge>
                                </x-atrium::table.cell>
                                <x-atrium::table.cell>{{ $message->channel }}</x-atrium::table.cell>
                                <x-atrium::table.cell>
                                    <a class="underline-offset-2 hover:underline"
                                       href="{{ route('atrium.impex.messages.show', $message) }}">{{ $message->endpoint }}</a>
                                </x-atrium::table.cell>
                                <x-atrium::table.cell>{{ $message->occurred_at?->diffForHumans() }}</x-atrium::table.cell>
                            </x-atrium::table.row>
                        @endforeach
                    </x-atrium::table>
                @endif
            </x-atrium::card>
        @endimpexCan

        <x-atrium::audit-trail source="impex" :subject="$run" />
    </div>
</x-atrium::layout>
