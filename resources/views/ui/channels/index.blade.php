@use(RefactorCircus\Impex\Atrium\Badges)
@use(RefactorCircus\Impex\Domains\Channel\Models\ChannelModel)

<x-atrium::layout :title="__('impex::impex.channels')">
    <x-atrium::page-header :title="__('impex::impex.channels')">
        <x-slot:actions>
            @impexCan('create', ChannelModel::class)
                <x-atrium::button :href="route('atrium.impex.channels.create')" data-testid="new-channel">{{ __('impex::impex.new_channel') }}</x-atrium::button>
            @endimpexCan
        </x-slot:actions>
    </x-atrium::page-header>

    <div class="mt-5 flex flex-col gap-5">
        <x-atrium::flash />

        @if ($channels === [])
            <x-atrium::empty-state :title="__('impex::impex.no_channels')" />
        @else
            <x-atrium::table striped>
                <x-slot:head>
                    <x-atrium::table.row>
                        <x-atrium::table.cell heading>{{ __('impex::impex.name') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading>{{ __('impex::impex.direction') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading>{{ __('impex::impex.transport') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading>{{ __('impex::impex.path') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading>{{ __('impex::impex.status') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading>{{ __('impex::impex.signature') }}</x-atrium::table.cell>
                    </x-atrium::table.row>
                </x-slot:head>

                @foreach ($channels as $channel)
                    <x-atrium::table.row>
                        <x-atrium::table.cell>
                            <a class="underline-offset-2 hover:underline" href="{{ route('atrium.impex.channels.show', $channel['name']) }}"><code class="text-xs">{{ $channel['name'] }}</code></a>
                        </x-atrium::table.cell>
                        <x-atrium::table.cell>{{ $channel['direction'] }}</x-atrium::table.cell>
                        <x-atrium::table.cell>{{ $channel['transport'] }}</x-atrium::table.cell>
                        <x-atrium::table.cell>
                            @if ($channel['direction'] === 'inbound')
                                <code class="text-xs">{{ $channel['path'] ?? 'channels/'.$channel['name'] }}</code>
                            @else
                                {{ __('impex::impex.none') }}
                            @endif
                        </x-atrium::table.cell>
                        <x-atrium::table.cell>{{ $channel['status'] }}{{ $channel['stored'] ? '' : ' · '.__('impex::impex.from_config') }}</x-atrium::table.cell>
                        <x-atrium::table.cell>
                            @php($signed = (bool) ($channel['verifies_signatures'] ?? false))
                            @include('impex::ui.partials.status-dot', [
                                'variant' => Badges::forSignature($signed ?: null),
                                'label' => $signed ? __('impex::impex.verified') : __('impex::impex.unsigned'),
                                'state' => $signed ? 'verified' : 'unsigned',
                            ])
                        </x-atrium::table.cell>
                    </x-atrium::table.row>
                @endforeach
            </x-atrium::table>
        @endif
    </div>
</x-atrium::layout>
