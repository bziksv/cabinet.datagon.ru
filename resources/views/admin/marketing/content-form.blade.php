@extends('layouts.app')

@php
    $isNew = !$item->exists;
    $heading = $isNew ? __('Marketing new content item') : $item->title;
@endphp

@section('title', $heading . ' — ' . __('Site marketing'))

@section('content')
    @php
        $v = static function (string $field) use ($item) {
            $val = old($field, $item->getAttribute($field));
            if ($val instanceof \DateTimeInterface) {
                return $val->format('Y-m-d');
            }
            return $val;
        };
    @endphp

    <div class="cabinet-marketing-page">
        @include('admin.marketing.partials.header', ['active' => 'content'])

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <a href="{{ route('admin.marketing.content', ['type' => $item->type]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('Marketing back') }}
            </a>
            <h3 class="h5 mb-0">{{ $heading }}</h3>
            @unless($isNew)
                <span class="badge text-bg-light border">{{ __($contentTypes[$item->type]['short'] ?? $item->type) }}</span>
                @if($item->plan_number)<span class="badge text-bg-light border">{{ __('Marketing plan row', ['n' => $item->plan_number]) }}</span>@endif
                @include('admin.marketing.partials.badge', ['map' => $contentStatuses, 'value' => $item->status])
            @endunless
        </div>

        @include('admin.marketing.partials.errors')

        <form method="post" action="{{ $isNew ? route('admin.marketing.content.store') : route('admin.marketing.content.update', $item->id) }}">
            @csrf
            @unless($isNew) @method('PUT') @endunless

            <div class="row g-3">
                <div class="col-12 col-xl-8">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header"><h4 class="card-title h6 mb-0"><i class="bi bi-clipboard-check me-1 text-primary" aria-hidden="true"></i>{{ __('Marketing brief') }}</h4></div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="mk-main">{{ __('Marketing main query') }}</label>
                                    <input type="text" name="main_query" id="mk-main" class="form-control" maxlength="191" value="{{ $v('main_query') }}">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label fw-semibold" for="mk-intent">{{ __('Marketing intent') }}</label>
                                    <input type="text" name="intent" id="mk-intent" class="form-control" maxlength="64" value="{{ $v('intent') }}">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label fw-semibold" for="mk-format">{{ __('Marketing format') }}</label>
                                    <input type="text" name="format" id="mk-format" class="form-control" maxlength="64" value="{{ $v('format') }}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="mk-secondary">{{ __('Marketing secondary queries') }}</label>
                                <textarea name="secondary_queries" id="mk-secondary" rows="5" class="form-control">{{ $v('secondary_queries') }}</textarea>
                                <div class="form-text">{{ __('Marketing secondary queries hint') }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="mk-outline">{{ __('Marketing outline') }}</label>
                                <textarea name="outline" id="mk-outline" rows="12" class="form-control font-monospace small">{{ $v('outline') }}</textarea>
                                <div class="form-text">{{ __('Marketing outline hint') }}</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="mk-length">{{ __('Marketing target length') }}</label>
                                    <input type="text" name="target_length" id="mk-length" class="form-control" maxlength="128" value="{{ $v('target_length') }}">
                                    <div class="form-text">{{ __('Marketing target length hint') }}</div>
                                </div>
                                <div class="col-12 col-md-8">
                                    <label class="form-label fw-semibold" for="mk-modules">{{ __('Marketing modules') }}</label>
                                    <input type="text" name="modules" id="mk-modules" class="form-control" maxlength="500" value="{{ $v('modules') }}">
                                    <div class="form-text">
                                        {{ __('Marketing modules hint') }}
                                        @foreach($item->moduleList() as $slug)
                                            @php $u = \App\MarketingContentItem::moduleUrl($slug); @endphp
                                            @if($u) · <a href="{{ $u }}" target="_blank" rel="noopener">{{ $slug }}</a>@endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label fw-semibold" for="mk-related">{{ __('Marketing related terms') }}</label>
                                <textarea name="related_terms" id="mk-related" rows="2" class="form-control">{{ $v('related_terms') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-3">
                        <div class="card-header"><h4 class="card-title h6 mb-0"><i class="bi bi-tags me-1 text-primary" aria-hidden="true"></i>{{ __('Marketing lsi') }}</h4></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <textarea name="lsi_words" id="mk-lsi" rows="5" class="form-control" aria-label="{{ __('Marketing lsi') }}">{{ $v('lsi_words') }}</textarea>
                                <div class="form-text">{{ __('Marketing lsi hint') }}</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 col-md-8">
                                    <label class="form-label fw-semibold" for="mk-lsi-src">{{ __('Marketing lsi source') }}</label>
                                    <input type="text" name="lsi_source" id="mk-lsi-src" class="form-control" maxlength="191" value="{{ $v('lsi_source') }}">
                                    <div class="form-text">{{ __('Marketing lsi source hint') }}</div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="mk-lsi-date">{{ __('Marketing lsi date') }}</label>
                                    <input type="date" name="lsi_analyzed_at" id="mk-lsi-date" class="form-control" value="{{ $v('lsi_analyzed_at') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div class="card shadow-sm mb-3">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="mk-type">{{ __('Marketing type') }}</label>
                                <select name="type" id="mk-type" class="form-select">
                                    @foreach($contentTypes as $code => $t)
                                        <option value="{{ $code }}" @if($v('type') === $code) selected @endif>{{ __($t['title']) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="mk-title">{{ __('Marketing title') }}</label>
                                <input type="text" name="title" id="mk-title" class="form-control" required maxlength="191" value="{{ $v('title') }}">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold" for="mk-status">{{ __('Status') }}</label>
                                    <select name="status" id="mk-status" class="form-select">
                                        @foreach($contentStatuses as $code => $st)
                                            <option value="{{ $code }}" @if($v('status') === $code) selected @endif>{{ __($st['title']) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold" for="mk-priority">{{ __('Marketing priority') }}</label>
                                    <select name="priority" id="mk-priority" class="form-select">
                                        @foreach($priorities as $p)
                                            <option value="{{ $p }}" @if($v('priority') === $p) selected @endif>{{ $p }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input type="hidden" name="is_top" value="0">
                                <input class="form-check-input" type="checkbox" name="is_top" value="1" id="mk-top" @if($v('is_top')) checked @endif>
                                <label class="form-check-label" for="mk-top">{{ __('Marketing top') }}</label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="mk-category">{{ __('Marketing category') }}</label>
                                <input type="text" name="category" id="mk-category" class="form-control" maxlength="191" list="mk-category-list" value="{{ $v('category') }}">
                                <datalist id="mk-category-list">
                                    @foreach($categories as $cat)<option value="{{ $cat }}">@endforeach
                                </datalist>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="mk-aliases">{{ __('Marketing aliases') }}</label>
                                <input type="text" name="aliases" id="mk-aliases" class="form-control" maxlength="500" value="{{ $v('aliases') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="mk-slug">{{ __('Marketing slug') }}</label>
                                <input type="text" name="slug" id="mk-slug" class="form-control" maxlength="191" value="{{ $v('slug') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="mk-url">{{ __('Marketing url') }}</label>
                                <input type="text" name="url" id="mk-url" class="form-control" maxlength="500" value="{{ $v('url') }}" placeholder="https://titlo.ru/…">
                                @if($item->url)
                                    <div class="form-text"><a href="{{ $item->url }}" target="_blank" rel="noopener">{{ __('Marketing open') }} <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></div>
                                @endif
                            </div>
                            <div class="mb-0">
                                <label class="form-label fw-semibold" for="mk-notes">{{ __('Marketing notes') }}</label>
                                <textarea name="notes" id="mk-notes" rows="4" class="form-control">{{ $v('notes') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i>{{ __('Save') }}</button>
                        <a href="{{ route('admin.marketing.content', ['type' => $item->type]) }}" class="btn btn-outline-secondary">{{ __('Marketing back') }}</a>
                    </div>
                </div>
            </div>
        </form>

        @unless($isNew)
            <form method="post" action="{{ route('admin.marketing.content.destroy', $item->id) }}" class="mt-3 pt-3 border-top" onsubmit='return confirm(@json(__('Marketing delete confirm')))'>
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('Delete') }}</button>
            </form>
        @endunless
    </div>
@endsection
