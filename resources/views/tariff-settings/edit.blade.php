@extends('layouts.app')

@section('title', __('Edit limit'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-tariff-settings.css') }}">
@endsection

@section('content')
    <div class="cabinet-tariff-settings-page">
        <div class="mb-3">
            <h2 class="h4 mb-1">
                <i class="bi bi-pencil me-2 text-primary"></i>{{ __('Edit limit') }}
            </h2>
            <p class="text-secondary small mb-0">
                <code>{{ $setting->code }}</code>
                · {{ __('Tariff values are edited on the main page.') }}
            </p>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                {!! Form::model($setting, ['method' => 'PATCH', 'route' => ['tariff-settings.update', $setting->id]]) !!}
                @include('tariff-settings.partials._form')
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>{{ __('Update') }}
                    </button>
                    <a href="{{ route('tariff-settings.index') }}#{{ $setting->code }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endsection
