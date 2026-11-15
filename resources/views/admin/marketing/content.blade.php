@extends('layouts.app')

@section('title', __('Marketing content plan') . ' — ' . __('Site marketing'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
@endsection

@section('content')
    @php
        $fmt = static function ($n) { return number_format((int) $n, 0, '', ' '); };
        $isTerm = $filters['type'] === 'term';
        $newLabel = __('Marketing new content item');
        $actions = '<a href="' . e(route('admin.marketing.content.create', ['type' => $filters['type']])) . '" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e($newLabel) . '</a>'
            . '<form method="post" action="' . e(route('admin.marketing.content.import')) . '" class="d-inline">' . csrf_field()
            . '<button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-in-down me-1" aria-hidden="true"></i>' . e(__('Marketing import')) . '</button></form>';
    @endphp

    <div class="cabinet-marketing-page">
        @include('admin.marketing.partials.header', ['active' => 'content', 'actions' => $actions])

        <ul class="nav nav-pills mb-3 gap-1">
            @foreach($contentTypes as $code => $t)
                <li class="nav-item">
                    <a class="nav-link py-1 {{ $filters['type'] === $code ? 'active' : '' }}" href="{{ route('admin.marketing.content', ['type' => $code]) }}">
                        {{ __($t['title']) }} <span class="badge text-bg-light ms-1">{{ $fmt($typeCounts[$code] ?? 0) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <form method="get" action="{{ route('admin.marketing.content') }}" class="card shadow-sm mb-3">
            <input type="hidden" name="type" value="{{ $filters['type'] }}">
            @if($sort['sort'] !== '')
                <input type="hidden" name="sort" value="{{ $sort['sort'] }}">
                <input type="hidden" name="dir" value="{{ $sort['dir'] }}">
            @endif
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-3">
                        <label class="form-label small mb-1" for="mk-q">{{ __('Search') }}</label>
                        <input type="search" name="q" id="mk-q" class="form-control form-control-sm" value="{{ $filters['q'] }}" placeholder="{{ __('Marketing search') }}">
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <label class="form-label small mb-1" for="mk-cat">{{ __('Marketing category') }}</label>
                        <select name="category" id="mk-cat" class="form-select form-select-sm" data-mk-select2 data-placeholder="{{ __('Marketing all') }}">
                            <option value=""></option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" @if($filters['category'] === $cat) selected @endif>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label small mb-1" for="mk-status">{{ __('Status') }}</label>
                        <select name="status" id="mk-status" class="form-select form-select-sm">
                            <option value="">{{ __('Marketing all') }}</option>
                            @foreach($contentStatuses as $code => $st)
                                <option value="{{ $code }}" @if($filters['status'] === $code) selected @endif>{{ __($st['title']) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2 col-lg-1">
                        <label class="form-label small mb-1" for="mk-pr">{{ __('Marketing priority') }}</label>
                        <select name="priority" id="mk-pr" class="form-select form-select-sm">
                            <option value="">{{ __('Marketing all') }}</option>
                            @foreach($priorities as $p)
                                <option value="{{ $p }}" @if($filters['priority'] === $p) selected @endif>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-1">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="top" value="1" id="mk-top" @if($filters['top'] === '1') checked @endif>
                            <label class="form-check-label small" for="mk-top">{{ __('Marketing top only') }}</label>
                        </div>
                    </div>
                    <div class="col-6 col-md-12 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1" aria-hidden="true"></i>{{ __('Filter') }}</button>
                        <a href="{{ route('admin.marketing.content', ['type' => $filters['type']]) }}" class="btn btn-sm btn-outline-secondary">{{ __('Reset') }}</a>
                    </div>
                </div>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 3rem;" class="text-secondary">№</th>
                            @include('admin.marketing.partials.sort-th', ['col' => 'title', 'label' => $isTerm ? __('Marketing summary terms') : __('Marketing summary articles')])
                            @include('admin.marketing.partials.sort-th', ['col' => 'category', 'label' => __('Marketing category')])
                            <th>{{ __('Marketing main query') }}</th>
                            <th>{{ __('Marketing modules') }}</th>
                            @include('admin.marketing.partials.sort-th', ['col' => 'priority', 'label' => __('Marketing priority'), 'style' => 'width: 5rem;'])
                            @include('admin.marketing.partials.sort-th', ['col' => 'status', 'label' => __('Status'), 'style' => 'width: 8rem;'])
                            @include('admin.marketing.partials.sort-th', ['col' => 'updated_at', 'label' => __('Marketing updated'), 'style' => 'width: 7rem;'])
                            <th style="width: 5rem;">{{ __('Marketing brief') }}</th>
                            <th style="width: 3rem;"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td class="small text-secondary">{{ ($items->currentPage() - 1) * $items->perPage() + $loop->iteration }}</td>
                                <td>
                                    <a href="{{ route('admin.marketing.content.edit', $item->id) }}" class="fw-medium text-decoration-none">{{ $item->title }}</a>
                                    @if($item->is_top)<span class="badge text-bg-warning ms-1"><i class="bi bi-star-fill" aria-hidden="true"></i> {{ __('Marketing top') }}</span>@endif
                                    @if($item->aliases)<div class="small text-secondary">{{ $item->aliases }}</div>@endif
                                    @if($item->url)<div class="small"><a href="{{ $item->url }}" target="_blank" rel="noopener">{{ $item->url }}</a></div>@endif
                                </td>
                                <td class="small">{{ $item->category ?? '—' }}</td>
                                <td class="small">{{ $item->main_query ?? '—' }}</td>
                                <td class="small">
                                    @forelse($item->moduleList() as $slug)
                                        @php $u = \App\MarketingContentItem::moduleUrl($slug); @endphp
                                        @if($u)<a href="{{ $u }}" target="_blank" rel="noopener" class="d-inline-block me-1"><code>{{ $slug }}</code></a>@else<span class="me-1">{{ $slug }}</span>@endif
                                    @empty
                                        —
                                    @endforelse
                                </td>
                                <td><span class="badge text-bg-light border">{{ $item->priority }}</span></td>
                                <td>@include('admin.marketing.partials.badge', ['map' => $contentStatuses, 'value' => $item->status])</td>
                                <td class="small text-secondary text-nowrap">{{ $item->updated_at ? $item->updated_at->format('d.m.Y') : '—' }}</td>
                                <td class="text-nowrap">
                                    <i class="bi {{ $item->hasBrief() ? 'bi-file-earmark-check-fill text-success' : 'bi-file-earmark text-secondary opacity-50' }}" aria-label="{{ __('Marketing brief filled') }}"></i>
                                    <i class="bi {{ $item->hasLsi() ? 'bi-tags-fill text-success' : 'bi-tags text-secondary opacity-50' }} ms-1" aria-label="{{ __('Marketing lsi filled') }}"></i>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.marketing.content.edit', $item->id) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="p-3 text-secondary small">{{ __('Marketing nothing found') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($items->hasPages())
                <div class="card-footer">{{ $items->links('pagination::bootstrap-4') }}</div>
            @endif
        </div>
        <p class="small text-secondary mt-2 mb-0">
            <i class="bi bi-file-earmark-check-fill text-success" aria-hidden="true"></i> — {{ __('Marketing brief filled') }} ·
            <i class="bi bi-tags-fill text-success" aria-hidden="true"></i> — {{ __('Marketing lsi filled') }} ·
            {{ $fmt($items->total()) }}
        </p>
    </div>
@endsection

@section('js')
    @include('admin.marketing.partials.select2')
@endsection
