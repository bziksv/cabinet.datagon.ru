@php
    $project = $project ?? null;
    $isEdit = $project !== null;
    $buttonsText = '';
    if ($isEdit && !empty($project->buttons)) {
        $decoded = json_decode($project->buttons, true);
        if (is_array($decoded)) {
            $buttonsText = implode("\n", $decoded);
        }
    }
@endphp

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1 text-primary"></i>
    {{ __('After saving, add translation keys to') }} <code>resources/lang/ru.json</code>
    {{ __('for title and description if needed.') }}
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-0">
            <div class="card-header">
                <h3 class="card-title h6 mb-0">{{ __('Main settings') }}</h3>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="title">{{ __('Title') }} <span class="text-danger">*</span></label>
                    {!! Form::text('title', $isEdit ? $project->title : null, ['class' => 'form-control', 'id' => 'title', 'required' => true]) !!}
                    <div class="form-text">{{ __('Translation key or plain text shown in the menu.') }}</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="position">{{ __('Position in the menu') }} <span class="text-danger">*</span></label>
                    {!! Form::number('position', $isEdit ? $project->position : null, ['class' => 'form-control', 'id' => 'position', 'required' => true, 'min' => 0]) !!}
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">{{ __('Project description') }} <span class="text-danger">*</span></label>
                    {!! Form::textarea('description', $isEdit ? $project->description : null, ['class' => 'form-control', 'id' => 'description', 'rows' => 3, 'required' => true]) !!}
                </div>
                <div class="col-12">
                    <label class="form-label" for="link">{{ __('Link') }} <span class="text-danger">*</span></label>
                    {!! Form::text('link', $isEdit ? $project->link : null, ['class' => 'form-control', 'id' => 'link', 'required' => true, 'placeholder' => '/monitoring']) !!}
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="icon">{{ __('Icon (HTML)') }} <span class="text-danger">*</span></label>
                    {!! Form::text('icon', $isEdit ? $project->icon : null, [
                        'class' => 'form-control font-monospace',
                        'id' => 'icon',
                        'required' => true,
                        'placeholder' => '<i class="bi bi-grid"></i>',
                    ]) !!}
                    <div class="form-text">
                        {{ __('Bootstrap Icons') }}: <code>&lt;i class="bi bi-…"&gt;&lt;/i&gt;</code>
                        · <a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener">icons.getbootstrap.com</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="color">{{ __('Color module') }} <span class="text-danger">*</span></label>
                    <input type="color"
                           id="color"
                           name="color"
                           class="form-control form-control-color w-100"
                           value="{{ $isEdit ? $project->color : '#0d6efd' }}"
                           required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="access">{{ __('Access') }}</label>
                    {!! Form::select('access[]', $roles, $isEdit ? $project->access : [], [
                        'class' => 'form-select',
                        'id' => 'access',
                        'multiple' => true,
                        'size' => min(6, max(3, $roles->count())),
                    ]) !!}
                    <div class="form-text">
                        {{ __('Sidebar visibility: which roles see this menu item for regular users. Empty = all roles.') }}
                        {{ __('Not the same as permission') }}
                        <a href="{{ route('manage-access.index') }}">«{{ __('Main projects') }}»</a>
                        {{ __('on') }}
                        <a href="{{ route('manage-access.index') }}">{{ __('Roles and permissions') }}</a>
                        ({{ __('opens') }} /main-projects).
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               name="show"
                               id="show"
                               value="1"
                               @if(!$isEdit || $project->show) checked @endif>
                        <label class="form-check-label" for="show">
                            {{ __('Show on home page for regular users') }}
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header">
                <button class="btn btn-link text-decoration-none p-0 fw-semibold text-body"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#cabinet-mp-stats-collapse"
                        aria-expanded="{{ $isEdit && (!empty($project->controller) || !empty($buttonsText)) ? 'true' : 'false' }}">
                    <i class="bi bi-bar-chart me-1"></i>{{ __('Statistics settings') }}
                    <span class="text-secondary fw-normal small">({{ __('optional') }})</span>
                </button>
            </div>
            <div id="cabinet-mp-stats-collapse" class="collapse @if($isEdit && (!empty($project->controller) || !empty($buttonsText))) show @endif">
                <div class="card-body">
                    <p class="small text-secondary">
                        {{ __('If a controller is set, visits and actions are collected. Format: one line per method.') }}
                        <code>ControllerName</code>, <code>@index</code>, <code>!actionName</code>
                    </p>
                    <div class="mb-3">
                        <label class="form-label" for="controller">{{ __('Controller') }}</label>
                        {!! Form::textarea('controller', $isEdit ? $project->controller : null, [
                            'class' => 'form-control font-monospace',
                            'id' => 'controller',
                            'rows' => 5,
                            'placeholder' => "MonitoringController\n@index\n!export",
                        ]) !!}
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="buttons">{{ __('Tracked button labels') }}</label>
                        <textarea name="buttons"
                                  id="buttons"
                                  class="form-control font-monospace"
                                  rows="4"
                                  placeholder="{{ __('One button label per line') }}">{{ old('buttons', $buttonsText) }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm sticky-top" style="top: 0.75rem;">
            <div class="card-header">
                <h3 class="card-title h6 mb-0">{{ __('Preview') }}</h3>
            </div>
            <div class="card-body text-center">
                <div class="cabinet-mp-icon-live mx-auto mb-2" id="cabinet-mp-preview-icon" aria-hidden="true">
                    {!! $isEdit ? $project->icon : '<i class="bi bi-grid"></i>' !!}
                </div>
                <div class="fw-semibold" id="cabinet-mp-preview-title">{{ $isEdit ? __($project->title) : __('Title') }}</div>
                <p class="small text-secondary mb-0" id="cabinet-mp-preview-desc">
                    {{ $isEdit && $project->description ? __($project->description) : __('Description') }}
                </p>
            </div>
            <div class="card-footer d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>{{ $isEdit ? __('Update') : __('Add') }}
                </button>
                <a href="{{ route('main-projects.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
            </div>
        </div>
    </div>
</div>
