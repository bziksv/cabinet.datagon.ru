<p class="cabinet-mon-create-hint-step">{{ __('Monitoring v2 create step project hint') }}</p>
<div class="row">
    <div class="col-lg-7">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title mb-0">{{ __('Monitoring v2 create project card') }}</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    {!! Form::label('name', __('Project name')) !!}
                    {!! Form::text('name', null, ['class' => 'form-control', 'placeholder' => __('Monitoring v2 create name ph'), 'autocomplete' => 'organization']) !!}
                </div>
                <div class="form-group mb-0">
                    {!! Form::label('url', __('Monitoring v2 create domain label')) !!}
                    {!! Form::text('url', null, ['class' => 'form-control', 'placeholder' => 'example.com', 'autocomplete' => 'off', 'inputmode' => 'url']) !!}
                    <small class="form-text text-muted">{{ __('Monitoring v2 create url help') }}</small>
                </div>
            </div>
            <div class="card-footer text-muted small">
                {{ __('Monitoring v2 create step project footer') }}
            </div>
        </div>
    </div>
</div>
