{{--
    The impex.run-link section on another package's screen (ScreenRegistrations):
    the host's record, the row in a row slot, is a run the viewer may open.
    The host keeps the flow name in its own cell, so the link reads "Open run"
    and names the flow for assistive technology.
--}}
<a href="{{ route('atrium.impex.runs.show', $record) }}" data-testid="impex-run-link" class="underline-offset-2 hover:underline" aria-label="{{ __('impex::impex.open_run_flow', ['flow' => $record->flow]) }}">{{ __('impex::impex.open_run') }}</a>
