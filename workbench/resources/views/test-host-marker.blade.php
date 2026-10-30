{{--
    A marker section or tab for the test host's tests: its `marker` data, then
    the record it was rendered for, if any.
--}}
<span>marker {{ $marker ?? '' }}@if ($record !== null) for {{ $record->getKey() }}@endif</span>
