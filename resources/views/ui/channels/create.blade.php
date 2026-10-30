{{-- The `impex.channel-create` detail screen: header actions always render, after any
     of the page's own; an open registered tab's panel replaces the page, and
     `before`, `after` and the `aside` column (only when it has something to
     show) render only around it. --}}
@php($aside = $resolved->openRegisteredTab === null && $resolved->sections('aside') !== [])
<x-atrium::layout :title="__('impex::impex.new_channel')">
    <x-atrium::page-header :title="__('impex::impex.new_channel')">
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

        @if ($aside)
            <div class="mt-5 grid gap-6 lg:grid-cols-3">
                <div class="flex flex-col gap-5 lg:col-span-2" data-testid="impex-content">
        @else
                <div class="mt-5 flex flex-col gap-5" data-testid="impex-content">
        @endif
                    <x-atrium::flash />

                    <x-atrium::card>
                        <form method="POST" action="{{ route('atrium.impex.channels.store') }}" class="flex flex-col gap-4" data-testid="create-channel">
                            @csrf

                            <div class="flex flex-wrap items-end gap-3">
                                <x-atrium::form.input name="name" :label="__('impex::impex.name')" :value="old('name')" :hint="__('impex::impex.channel_name_hint')" required wrapper="w-72" />

                                <x-atrium::form.select
                                    name="direction"
                                    :label="__('impex::impex.direction')"
                                    :options="collect($directions)->mapWithKeys(fn ($direction) => [$direction->value => $direction->value])"
                                    :selected="old('direction', 'outbound')"
                                    wrapper="w-44" />
                            </div>

                            <div class="flex flex-wrap items-end gap-3">
                                @include('impex::ui.channels.partials.fields', ['channel' => null])
                            </div>

                            <x-atrium::form.actions>
                                <x-atrium::button type="submit">{{ __('impex::impex.create') }}</x-atrium::button>
                                <x-atrium::button variant="ghost" :href="route('atrium.impex.channels.index')">{{ __('impex::impex.cancel') }}</x-atrium::button>
                            </x-atrium::form.actions>
                        </form>
                    </x-atrium::card>
                </div>

        @if ($aside)
                <aside class="flex flex-col gap-4" data-testid="screen-aside">
                    <x-atrium::screen-sections :screen="$resolved" slot="aside" />
                </aside>
            </div>
        @endif

        <x-atrium::screen-sections :screen="$resolved" slot="after" class="mt-5" />
    @endif
</x-atrium::layout>
