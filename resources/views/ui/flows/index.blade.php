@use(RefactorCircus\Impex\Atrium\Badges)
@use(RefactorCircus\Impex\Domains\Run\Models\RunModel)

{{-- The `impex.flows` list screen: header actions always render, after any
     of the page's own; an open registered tab's panel replaces the page, and
     `before`, `after` and each row's `row.actions` (in its last cell)
     render only around it. --}}
<x-atrium::layout :title="__('impex::impex.flows')">
    <x-atrium::page-header :title="__('impex::impex.flows')">
        @if ($resolved->sections('header.actions') !== [])
            <x-slot:actions>
                <x-atrium::screen-sections :screen="$resolved" slot="header.actions" />
            </x-slot:actions>
        @endif
    </x-atrium::page-header>

    <x-atrium::screen-tabs :screen="$resolved" class="mt-4" />

    @if ($resolved->openRegisteredTab !== null)
        <x-atrium::screen-tab-panel :screen="$resolved" class="mt-5" />
    @else
        <x-atrium::screen-sections :screen="$resolved" slot="before" class="mt-5" />

        <div class="mt-5 flex flex-col gap-4" data-testid="impex-content">
            <x-atrium::flash />

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
                                @include('impex::ui.partials.status-dot', [
                                    'variant' => Badges::forFlow($enabled),
                                    'label' => $enabled ? __('impex::impex.enabled') : __('impex::impex.disabled'),
                                    'state' => $enabled ? 'enabled' : 'disabled',
                                ])
                            </x-atrium::table.cell>
                            <x-atrium::table.cell>
                                @impexCan('create', RunModel::class, [$flow['slug']])
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

                                <x-atrium::screen-sections :screen="$resolved" slot="row.actions" :row="$rows[$flow['slug']]" />
                            </x-atrium::table.cell>
                        </x-atrium::table.row>
                    @endforeach
                </x-atrium::table>
            @endif
        </div>

        <x-atrium::screen-sections :screen="$resolved" slot="after" class="mt-5" />
    @endif
</x-atrium::layout>
