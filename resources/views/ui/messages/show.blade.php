@use(RefactorCircus\Impex\Atrium\Badges)
@use(RefactorCircus\Impex\Atrium\ScreenAccess)

<x-atrium::layout :title="$message->channel">
    <x-atrium::page-header :title="$message->channel" :description="$message->endpoint">
        <x-slot:actions>
            <x-atrium::badge :variant="Badges::forDirection($message->direction)">{{ $message->direction->value }}</x-atrium::badge>
        </x-slot:actions>
    </x-atrium::page-header>

    <div class="mt-5 flex flex-col gap-5">
        <x-atrium::card>
            <x-atrium::description-list>
                <x-atrium::description-list.item :term="__('impex::impex.transport')">{{ $message->transport }}{{ $message->method ? ' · '.$message->method : '' }}</x-atrium::description-list.item>

                @if ($message->status_code)
                    <x-atrium::description-list.item :term="__('impex::impex.status')">{{ $message->status_code }}</x-atrium::description-list.item>
                @endif

                @if ($message->duration_ms !== null)
                    <x-atrium::description-list.item :term="__('impex::impex.duration')">{{ __('impex::impex.milliseconds', ['count' => $message->duration_ms]) }}</x-atrium::description-list.item>
                @endif

                @if ($message->signature_valid !== null)
                    <x-atrium::description-list.item :term="__('impex::impex.signature')">
                        @include('impex::ui.partials.status-dot', [
                            'variant' => Badges::forSignature($message->signature_valid),
                            'label' => $message->signature_valid ? __('impex::impex.verified') : __('impex::impex.bad_signature'),
                            'state' => $message->signature_valid ? 'verified' : 'bad_signature',
                            'testid' => 'message-signature',
                        ])
                    </x-atrium::description-list.item>
                @endif

                <x-atrium::description-list.item :term="__('impex::impex.size')">{{ __('impex::impex.bytes', ['count' => $message->bytes]) }}</x-atrium::description-list.item>

                @if ($message->run_id)
                    <x-atrium::description-list.item :term="__('impex::impex.run')" class="font-mono">
                        @if ($message->run !== null && ScreenAccess::allows('view', $message->run))
                            <a class="underline-offset-2 hover:underline"
                               href="{{ route('atrium.impex.runs.show', $message->run_id) }}" data-testid="message-run">{{ $message->run_id }}</a>
                        @else
                            {{ $message->run_id }}
                        @endif
                    </x-atrium::description-list.item>
                @endif
            </x-atrium::description-list>
        </x-atrium::card>

        @if ($message->headers)
            <x-atrium::card :title="__('impex::impex.headers')">
                <pre class="overflow-x-auto rounded-radius bg-surface-alt p-3 text-xs dark:bg-surface-dark-alt">{{ json_encode($message->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </x-atrium::card>
        @endif

        <x-atrium::card :title="__('impex::impex.body')">
            <pre class="overflow-x-auto rounded-radius bg-surface-alt p-3 text-xs dark:bg-surface-dark-alt">{{ $message->body_preview ?? __('impex::impex.empty_body') }}</pre>

            @if ($message->body_artifact_id)
                <p class="mt-2 text-sm text-on-surface dark:text-on-surface-dark">
                    {{ __('impex::impex.body_on_disk', ['id' => $message->body_artifact_id]) }}
                </p>
            @endif
        </x-atrium::card>

        <x-atrium::audit-trail source="impex" :subject="$message" />
    </div>
</x-atrium::layout>
