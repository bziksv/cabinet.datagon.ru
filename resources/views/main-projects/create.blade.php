@extends('layouts.app')

@section('title', __('Create project'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-main-projects.css') }}">
@endsection

@section('content')
    <div class="cabinet-main-projects-page cabinet-mp-form">
        <div class="mb-3">
            <h2 class="h4 mb-1">
                <i class="bi bi-plus-circle me-2 text-primary"></i>{{ __('Create project') }}
            </h2>
            <p class="text-secondary small mb-0">
                {{ __('This module allows you to add services that are displayed on the main page') }}
            </p>
        </div>

        {!! Form::open(['route' => 'main-projects.store', 'method' => 'POST']) !!}
        @include('main-projects.partials.form', ['roles' => $roles])
        {!! Form::close() !!}
    </div>
@endsection

@section('js')
    @include('main-projects.partials.form-preview-js')
@endsection
