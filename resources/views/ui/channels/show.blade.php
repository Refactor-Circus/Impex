<x-atrium::layout :title="$channel->name">
    <x-atrium::page-header :title="$channel->name" :description="$channel->direction->value.' · '.$channel->transport">
        <x-slot:actions>
            @if ($stored)
                @impexCan('update', $stored)
                    <form method="POST" action="{{ route('atrium.impex.channels.rotate-secret', $stored->name) }}">
                        @csrf
                        <x-atrium::icon-button icon="key" :label="__('impex::impex.rotate_secret')" variant="outline" type="submit" data-testid="rotate-secret" />
                    </form>
                @endimpexCan

                @impexCan('delete', $stored)
                    <form method="POST" action="{{ route('atrium.impex.channels.destroy', $stored->name) }}">
                        @csrf
                        @method('DELETE')
                        <x-atrium::icon-button icon="trash" :label="__('impex::impex.delete')" variant="outline" type="submit" data-testid="delete-channel" />
                    </form>
                @endimpexCan
            @endif
        </x-slot:actions>
    </x-atrium::page-header>

    <div class="mt-5 flex flex-col gap-5">
        <x-atrium::flash />

        <x-atrium::card>
            <x-atrium::description-list>
                <x-atrium::description-list.item :term="__('impex::impex.status')">{{ $channel->status->value }}</x-atrium::description-list.item>
                <x-atrium::description-list.item :term="__('impex::impex.body_policy')">{{ __('impex::impex.body_policies.'.$channel->bodyPolicy->value) }}</x-atrium::description-list.item>
                <x-atrium::description-list.item :term="__('impex::impex.signature')">{{ $channel->verifiesSignatures() ? __('impex::impex.verified') : __('impex::impex.unsigned') }}</x-atrium::description-list.item>

                @if ($channel->flow)
                    <x-atrium::description-list.item :term="__('impex::impex.flow')">{{ $channel->flow }}</x-atrium::description-list.item>
                @endif

                <x-atrium::description-list.item :term="__('impex::impex.source')">{{ $stored ? __('impex::impex.stored') : __('impex::impex.from_config') }}</x-atrium::description-list.item>
            </x-atrium::description-list>
        </x-atrium::card>

        @if ($stored)
            @impexCan('update', $stored)
                <x-atrium::card :title="__('impex::impex.edit_channel')">
                    <form method="POST" action="{{ route('atrium.impex.channels.update', $stored->name) }}" class="flex flex-col gap-4" data-testid="update-channel">
                        @csrf
                        @method('PATCH')

                        <div class="flex flex-wrap items-end gap-3">
                            @include('impex::ui.channels.partials.fields', ['channel' => $channel->describe()])
                        </div>

                        <x-atrium::form.actions>
                            <x-atrium::button type="submit">{{ __('impex::impex.save') }}</x-atrium::button>
                        </x-atrium::form.actions>
                    </form>
                </x-atrium::card>
            @endimpexCan
        @else
            <x-atrium::card :title="__('impex::impex.options')">
                <pre class="overflow-x-auto rounded-radius bg-surface-alt p-3 text-xs dark:bg-surface-dark-alt">{{ json_encode($channel->options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                <p class="mt-2 text-sm text-on-surface dark:text-on-surface-dark">{{ __('impex::impex.configured_channel_hint') }}</p>
            </x-atrium::card>
        @endif
    </div>
</x-atrium::layout>
