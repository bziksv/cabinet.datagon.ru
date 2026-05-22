@extends('layouts.app')

@section('title', __('Editing a profile ') . $user->email)

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-user-edit.css') }}">
@endsection

@section('content')
    @php
        $displayName = trim($user->full_name) ?: $user->email;
    @endphp

    <div class="cabinet-user-edit-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div class="min-w-0">
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb mb-0 small">
                        <li class="breadcrumb-item"><a href="{{ route('users.index') }}">{{ __('Users') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Edit') }}</li>
                    </ol>
                </nav>
                <h2 class="h4 mb-1 text-break">
                    <i class="bi bi-person-gear me-2 text-primary" aria-hidden="true"></i>{{ __('Editing a profile ') }}{{ $user->email }}
                </h2>
                <p class="text-secondary small mb-0">
                    {{ $displayName }} · ID {{ $user->id }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('visit.statistics', $user->id) }}" class="btn btn-sm btn-outline-info">
                    <i class="bi bi-pie-chart me-1"></i>{{ __('User statistic') }}
                </a>
                <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>{{ __('Users') }}
                </a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-4 col-xl-3">
                @include('users.partials.edit-aside', [
                    'user' => $user,
                    'lang' => $lang,
                    'activePay' => $activePay,
                    'tariffName' => $tariffName,
                    'telegramConnected' => $telegramConnected,
                    'canManageStatistic' => $canManageStatistic,
                ])
            </div>

            <div class="col-lg-8 col-xl-9">
                <div class="card shadow-sm cabinet-user-edit-form-card">
                    <div class="card-header py-3">
                        <h3 class="card-title h6 mb-0">
                            <i class="bi bi-sliders me-1 text-primary"></i>{{ __('Account settings') }}
                        </h3>
                    </div>

                    {!! Form::model($user, ['method' => 'PATCH', 'route' => ['users.update', $user->id], 'class' => 'cabinet-user-edit-form']) !!}
                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <p class="text-secondary small">{{ __('Main data') }}</p>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                {!! Form::label('name', __('Name'), ['class' => 'form-label']) !!}
                                {!! Form::text('name', null, [
                                    'class' => 'form-control' . ($errors->has('name') ? ' is-invalid' : ''),
                                    'placeholder' => __('Name'),
                                    'required' => true,
                                ]) !!}
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                {!! Form::label('last_name', __('Last name'), ['class' => 'form-label']) !!}
                                {!! Form::text('last_name', null, [
                                    'class' => 'form-control' . ($errors->has('last_name') ? ' is-invalid' : ''),
                                    'placeholder' => __('Last name'),
                                    'required' => true,
                                ]) !!}
                                @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                {!! Form::label('email', __('Email'), ['class' => 'form-label']) !!}
                                {!! Form::email('email', null, [
                                    'class' => 'form-control' . ($errors->has('email') ? ' is-invalid' : ''),
                                    'placeholder' => __('Email'),
                                    'required' => true,
                                ]) !!}
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                {!! Form::label('lang', __('Interface language'), ['class' => 'form-label']) !!}
                                {!! Form::select('lang', $lang, null, [
                                    'class' => 'form-select flags' . ($errors->has('lang') ? ' is-invalid' : ''),
                                ]) !!}
                                @error('lang') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <p class="text-secondary small">{{ __('Access and roles') }}</p>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                {!! Form::label('role', __('Roles'), ['class' => 'form-label']) !!}
                                {!! Form::select('role[]', $roleOptions, null, [
                                    'class' => 'form-select' . ($errors->has('role') ? ' is-invalid' : ''),
                                    'id' => 'user-edit-roles',
                                    'multiple' => true,
                                    'required' => true,
                                ]) !!}
                                <div class="form-text">{{ __('Select one or more roles for this user.') }}</div>
                                @error('role') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            @if($canManageStatistic)
                                <div class="col-md-6">
                                    {!! Form::label('statistic', __('Track statistic'), ['class' => 'form-label']) !!}
                                    @php
                                        $statisticValue = (string) old('statistic', ($user->statistic ?? false) ? '1' : '0');
                                    @endphp
                                    <select name="statistic" id="statistic" class="form-select{{ $errors->has('statistic') ? ' is-invalid' : '' }}">
                                        <option value="1" @selected($statisticValue === '1')>{{ __('Yes') }}</option>
                                        <option value="0" @selected($statisticValue === '0')>{{ __('No') }}</option>
                                    </select>
                                    <div class="form-text">{{ __('Include user in visit statistics reports.') }}</div>
                                    @error('statistic') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endif
                        </div>

                        @if($superAdmin)
                            <p class="text-secondary small">{{ __('Security') }}</p>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    {!! Form::label('password', __('Password'), ['class' => 'form-label']) !!}
                                    <div class="input-group">
                                        <input type="password"
                                               class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}"
                                               name="password"
                                               id="user-edit-password"
                                               autocomplete="new-password"
                                               placeholder="{{ __('Leave it blank if you don\'t want to change') }}">
                                        <button type="button"
                                                class="btn btn-outline-secondary cabinet-user-edit-toggle-pwd"
                                                data-target="user-edit-password"
                                                title="{{ __('Show password') }}">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="user-edit-generate-password">
                                            {{ __('Generate password') }}
                                        </button>
                                    </div>
                                    <div class="form-text">{{ __('Use at least 8 characters.') }}</div>
                                    @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>{{ __('Save') }}
                        </button>
                    </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/select2/js/select2.js') }}"></script>
    <script>
        (function ($) {
            function generatePassword(length) {
                var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*',
                    result = '',
                    i;
                for (i = 0; i < length; i++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return result;
            }

        $(function () {
            $('#user-edit-generate-password').on('click', function () {
                var pwd = generatePassword(12);
                $('#user-edit-password').attr('type', 'text').val(pwd);
            });

            $('.cabinet-user-edit-toggle-pwd').on('click', function () {
                var $input = $('#' + $(this).data('target'));
                var isPwd = $input.attr('type') === 'password';
                $input.attr('type', isPwd ? 'text' : 'password');
                $(this).find('i').toggleClass('bi-eye bi-eye-slash');
            });

            $('.flags').select2({
                minimumResultsForSearch: Infinity,
                width: '100%',
                templateResult: function (state) {
                    if (!state.id) {
                        return state.text;
                    }
                    var baseUrl = '/img/flags';
                    return $(
                        '<span><img src="' + baseUrl + '/' + state.element.value.toLowerCase() + '.png" class="img-flag" alt="" /> ' + state.text + '</span>'
                    );
                },
            });

            if ($('#user-edit-roles').length && $.fn.select2) {
                $('#user-edit-roles').select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    closeOnSelect: false,
                });
            }
        });
        })(jQuery);
    </script>
@endsection
