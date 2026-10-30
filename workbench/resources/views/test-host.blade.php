{{--
    The test host's list screen (TestHostScreenController). `header.actions`
    always renders; with a registered tab open its panel replaces the page
    content, and `before`, the rows and `after` render only while the built-in
    tab is open. Each row's first cell is its key in plain text, and its last
    cell holds the row's `row.actions` sections.
--}}
@php($screen = $resolved->screen)

<x-atrium::layout :title="$screen->key">
    <x-slot:header>
        <x-atrium::page-header :title="$screen->key" description="A workbench list screen that packages extend through Atrium::screens().">
            <x-slot:actions>
                <x-atrium::screen-sections :screen="$resolved" slot="header.actions" />
            </x-slot:actions>
        </x-atrium::page-header>

        <x-atrium::screen-tabs :screen="$resolved" class="mt-4" />
    </x-slot:header>

    @if ($resolved->openRegisteredTab !== null)
        <x-atrium::screen-tab-panel :screen="$resolved" />
    @else
        <x-atrium::screen-sections :screen="$resolved" slot="before" class="mb-6" />

        <x-atrium::table data-testid="host-content">
            <x-slot:head>
                <x-atrium::table.row>
                    <x-atrium::table.cell heading>Record</x-atrium::table.cell>
                    <x-atrium::table.cell heading><span class="sr-only">Actions</span></x-atrium::table.cell>
                </x-atrium::table.row>
            </x-slot:head>

            @foreach ($rows as $row)
                <x-atrium::table.row data-testid="row-{{ $loop->index }}">
                    <x-atrium::table.cell>{{ $row->getKey() }}</x-atrium::table.cell>
                    <x-atrium::table.cell numeric>
                        <x-atrium::screen-sections :screen="$resolved" slot="row.actions" :row="$row" class="inline-flex" />
                    </x-atrium::table.cell>
                </x-atrium::table.row>
            @endforeach
        </x-atrium::table>

        <x-atrium::screen-sections :screen="$resolved" slot="after" class="mt-6" />
    @endif
</x-atrium::layout>
