<div class="cabinet-mon-create__actions" role="group" aria-label="{{ __('Monitoring v2 create actions') }}">
    @if(in_array('back', $buttons))
        <a href="{{ route('monitoring.v2') }}" class="btn btn-outline-secondary">{{ __('Monitoring v2 create back list') }}</a>
    @endif

    @if(in_array('previous', $buttons))
        <button type="button" class="btn btn-outline-secondary cabinet-mon-create__btn-prev" onclick="stepper.previous()">{{ __('Monitoring v2 create btn back') }}</button>
    @endif

    @if(in_array('next', $buttons))
        <button type="button" class="btn btn-primary cabinet-mon-create__btn-next" onclick="stepper.next()">{{ __('Monitoring v2 create btn next') }}</button>
    @endif

    @if(in_array('action', $buttons))
        <a href="{{ route('monitoring.v2') }}" class="btn btn-success">{{ __('Monitoring v2 create finish btn') }}</a>
    @endif
</div>
