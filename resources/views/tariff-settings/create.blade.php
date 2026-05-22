@extends('layouts.app')

@section('title', __('Add limit'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-tariff-settings.css') }}">
@endsection

@section('content')
    <div class="cabinet-tariff-settings-page">
        <div class="mb-3">
            <h2 class="h4 mb-1">
                <i class="bi bi-plus-circle me-2 text-primary"></i>{{ __('Add limit') }}
            </h2>
            <p class="text-secondary small mb-0">{{ __('Create a property (code), then add numeric limits per tariff on the list page.') }}</p>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                {!! Form::open(['method' => 'POST', 'route' => ['tariff-settings.store']]) !!}
                @include('tariff-settings.partials._form')
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>{{ __('Save') }}
                    </button>
                    <a href="{{ route('tariff-settings.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endsection
