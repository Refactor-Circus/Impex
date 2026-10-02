@use(JayI\Impex\Atrium\Badges)
@use(JayI\Impex\Models\Run)

<x-atrium::layout :title="__('impex::impex.flows')">
    <x-atrium::page-header :title="__('impex::impex.flows')" />

    <div class="mt-5 flex flex-col gap-4">
        @include('impex::ui.partials.status')

        @if ($flows === [])
            <x-atrium::empty-state :title="__('impex::impex.no_flows')" />
        @else
            <x-atrium::table striped>
                <x-slot:head>
                    <x-atrium::table.row>
                        <x-atrium::table.cell heading>{{ __('impex::impex.slug') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading>{{ __('impex::impex.schedule') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading>{{ __('impex::impex.enabled') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading>{{ __('impex::impex.start') }}</x-atrium::table.cell>
                    </x-atrium::table.row>
                </x-slot:head>

                @foreach ($flows as $flow)
                    <x-atrium::table.row>
                        <x-atrium::table.cell><code class="text-xs">{{ $flow['slug'] }}</code></x-atrium::table.cell>
                        <x-atrium::table.cell>{{ $flow['schedule'] ?? __('impex::impex.none') }}</x-atrium::table.cell>
                        <x-atrium::table.cell>
                            @php($enabled = (bool) ($flow['enabled'] ?? true))
                            <x-impex::status :variant="Badges::forFlow($enabled)"
                                             :label="$enabled ? __('impex::impex.enabled') : __('impex::impex.disabled')"
                                             :data-status="$enabled ? 'enabled' : 'disabled'" />
                        </x-atrium::table.cell>
                        <x-atrium::table.cell>
                            @impexCan('create', Run::class, [$flow['slug']])
                                <form method="POST" action="{{ route('atrium.impex.flows.run', $flow['slug']) }}"
                                      class="flex items-end gap-2">
                                    @csrf
                                    <x-atrium::form.input name="arguments" :label="null"
                                                          placeholder='["example", 1]' class="w-56 font-mono text-xs" />
                                    <x-atrium::icon-button icon="play" :label="__('impex::impex.start')" variant="primary" type="submit" size="sm"
                                                           data-testid="run-{{ $flow['slug'] }}"
                                                           :disabled="! $enabled" />
                                </form>
                            @endimpexCan
                        </x-atrium::table.cell>
                    </x-atrium::table.row>
                @endforeach
            </x-atrium::table>
        @endif
    </div>
</x-atrium::layout>
