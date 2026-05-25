@extends('layouts.app')

@section('title', $pageTitle ?? __('Ideas board'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-ideas.css') }}">
    @yield('ideas-css')
@stop

@section('content')
    <div class="cabinet-ideas-page">
        @yield('ideas-content')
    </div>
@stop

@section('js')
    @yield('ideas-js')
@stop
