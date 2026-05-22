@extends('layouts.app')

@section('title', __('Edit project'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-main-projects.css') }}">
@endsection

@section('content')
    <div class="cabinet-main-projects-page cabinet-mp-form">
        <div class="mb-3">
            <h2 class="h4 mb-1">
                <i class="bi bi-pencil-square me-2 text-primary"></i>{{ __('Edit project') }}
            </h2>
            <p class="text-secondary small mb-0">{{ __($data->title) }} · #{{ $data->id }}</p>
        </div>

        {!! Form::model($data, ['route' => ['main-projects.update', $data->id], 'method' => 'PUT']) !!}
        @include('main-projects.partials.form', ['roles' => $roles, 'project' => $data])
        {!! Form::close() !!}
    </div>
@endsection

@section('js')
    @include('main-projects.partials.form-preview-js')
@endsection
