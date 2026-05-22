@extends('layouts.app')

@section('title', __('User visit statistics') . ' — ' . $user->email)

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}">
    @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-css'])
    <link rel="stylesheet" href="{{ asset('css/cabinet-user-visit-statistics.css') }}">
@endsection

@section('content')
    @php
        $summary = $report['summary'];
        $isAdmin = \App\User::isUserAdmin();
    @endphp

    <div class="cabinet-user-visit-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div class="min-w-0">
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb mb-0 small">
                        @if($isAdmin)
                            <li class="breadcrumb-item"><a href="{{ route('users.index') }}">{{ __('Users') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('users.statistics') }}">{{ __('General statistics users') }}</a></li>
                        @endif
                        <li class="breadcrumb-item active">{{ __('Statistics') }}</li>
                    </ol>
                </nav>
                <h2 class="h4 mb-1 text-break">{{ $user->email }}</h2>
                <p class="text-secondary small mb-0">
                    @if(trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')))
                        {{ trim($user->name . ' ' . $user->last_name) }} ·
                    @endif
                    ID {{ $user->id }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($isAdmin)
                    <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Users') }}</a>
                    <a href="{{ route('users.statistics') }}" class="btn btn-sm btn-outline-secondary">{{ __('General statistics users') }}</a>
                @endif
            </div>
        </div>

        @if(!$hasAnyVisits)
            <div class="alert alert-light border text-center py-5">
                <i class="bi bi-inbox text-secondary display-6 d-block mb-2"></i>
                <p class="mb-0">{{ __('No visit statistics for this user yet.') }}</p>
            </div>
        @else
            <div class="card shadow-sm mb-3">
                <div class="card-body py-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-lg-8">
                            <label class="form-label small mb-1" for="cabinet-uvs-date-range">{{ __('Period') }}</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                                <input type="text" class="form-control" id="cabinet-uvs-date-range" value="{{ $dateRange }}" readonly>
                                <button type="button" class="btn btn-primary" id="cabinet-uvs-apply">
                                    <i class="bi bi-check-lg me-1"></i>{{ __('Apply') }}
                                </button>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4 d-flex flex-wrap gap-2">
                            @foreach([7 => __('7 days'), 30 => __('30 days'), 60 => __('60 days')] as $days => $label)
                                <button type="button" class="btn btn-sm btn-outline-secondary cabinet-uvs-preset" data-days="{{ $days }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div id="cabinet-uvs-content">
                @include('users.partials.visit-statistics-body', ['report' => $report])
            </div>
        @endif
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
    @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
    <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
    @include('users.partials.visit-statistics-scripts', [
        'user' => $user,
        'activeDates' => $hasAnyVisits ? ($report['active_dates'] ?? []) : [],
    ])
@endsection
