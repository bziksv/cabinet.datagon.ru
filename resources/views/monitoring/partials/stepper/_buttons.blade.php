@if(in_array('back', $buttons))
    <a href="{{ route('monitoring.v2') }}" class="btn btn-outline-secondary">{{ __('Monitoring v2 create back list') }}</a>
@endif

@if(in_array('previous', $buttons))
    <button type="button" class="btn btn-outline-secondary" onclick="stepper.previous()">{{ __('Back') }}</button>
@endif

@if(in_array('next', $buttons))
    <button type="button" class="btn btn-primary" onclick="stepper.next()">{{ __('Next') }}</button>
@endif

@if(in_array('action', $buttons))
    <a href="{{ route('monitoring.v2') }}" class="btn btn-success">{{ __('Monitoring v2 create finish btn') }}</a>
@endif
