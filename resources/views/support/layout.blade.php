@extends('layouts.app')

@section('title', $pageTitle ?? __('Support'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-support.css') }}">
    @yield('support-css')
@stop

@section('content')
    <div class="cabinet-support-page">
        @hasSection('support-header')
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h4 mb-0">
                    <i class="bi bi-headset me-2 text-primary" aria-hidden="true"></i>
                    @yield('support-header')
                </h2>
                @hasSection('support-header-tools')
                    <div class="d-flex flex-wrap gap-2">
                        @yield('support-header-tools')
                    </div>
                @endif
            </div>
        @endif

        @yield('support-above')

        <div class="row g-3">
            @include('support.partials.sidebar')
            <div class="col-lg-9">
                @yield('support-main')
            </div>
        </div>
    </div>
@stop

@section('js')
    @yield('support-js')
@stop
