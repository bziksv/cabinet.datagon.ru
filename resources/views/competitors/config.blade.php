@component('component.card', [
    'title' => __('Competitor Analyzer Settings'),
    'titleHtml' => e(__('Competitor Analyzer Settings')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-competitor-analysis'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-competitor-analysis.css') }}?v={{ @filemtime(public_path('css/cabinet-competitor-analysis.css')) ?: time() }}">
    @endslot

    <div class="cabinet-competitor-analysis-page cabinet-ca-config-page">
        @include('competitors.partials.module-nav', ['active' => 'config', 'admin' => true])
        @include('competitors.partials.limit-banner')

        <div class="row g-3 align-items-start">
            <div class="col-xl-8">
                <div class="card card-outline card-primary shadow-sm cabinet-ca-settings-card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('Analyzer Settings') }}</h3>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('competitor.edit.config') }}" method="post" class="row g-3">
                            @csrf

                            <div class="col-12">
                                <label class="form-label" for="agrigators">{{ __('List of aggregator sites') }}</label>
                                <textarea name="agrigators"
                                          id="agrigators"
                                          rows="8"
                                          class="form-control font-monospace"
                                >{{ $config->agrigators }}</textarea>
                                <p class="form-text mb-0">Один домен на строку. Подсветка в SERP и расчёт геозависимости (совпадение топ-URL между городами без маркетплейсов и агрегаторов).</p>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="urls_length">Стандартная длинна таблицы <strong>«{{ __('Landing Page analysis') }}»</strong></label>
                                {!! Form::select('urls_length', array_unique([
                                        $config->urls_length => $config->urls_length,
                                        '10' => 10,
                                        '25' => 25,
                                        '50' => 50,
                                        '100' => 100,
                                ]), null, ['class' => 'form-select', 'id' => 'urls_length']) !!}
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="positions_length">Стандартная длинна таблицы <strong>«{{ __('Analysis by the percentage of getting into the top and middle positions') }}»</strong></label>
                                {!! Form::select('positions_length', array_unique([
                                        $config->positions_length => $config->positions_length,
                                        '10' => 10,
                                        '25' => 25,
                                        '50' => 50,
                                        '100' => 100,
                                ]), null, ['class' => 'form-select', 'id' => 'positions_length']) !!}
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="count_top_10">Среднее количество повторений для вхождения слова в рекомендации <strong>Топ 10</strong></label>
                                <input type="number"
                                       name="count_repeat_top_10"
                                       id="count_top_10"
                                       class="form-control"
                                       min="0"
                                       value="{{ $config->count_repeat_top_10 }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="count_top_20">Среднее количество повторений для вхождения слова в рекомендации <strong>Топ 20</strong></label>
                                <input type="number"
                                       name="count_repeat_top_20"
                                       id="count_top_20"
                                       class="form-control"
                                       min="0"
                                       value="{{ $config->count_repeat_top_20 }}">
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1" aria-hidden="true"></i>{{ __('Update') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="info-box shadow-sm mb-3">
                    <span class="info-box-icon text-bg-primary">
                        <i class="fas fa-chart-bar" aria-hidden="true"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('General statistics of the module') }}</span>
                        <span class="info-box-number">{{ number_format($counter, 0, ',', ' ') }}</span>
                        <span class="info-box-text">{{ __('Scans in the current month') }}</span>
                    </div>
                </div>

                <div class="card shadow-sm cabinet-ca-users-stats-card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('Unique users') }}</h3>
                    </div>
                    <ul class="list-group list-group-flush">
                        @foreach([30, 60, 90] as $days)
                            <li class="list-group-item d-flex align-items-center justify-content-between gap-3">
                                <span class="text-secondary">{{ $days }} {{ __('days') }}</span>
                                <strong class="cabinet-ca-users-stat-value">{{ number_format($uniqueUsers[$days] ?? 0, 0, ',', ' ') }}</strong>
                            </li>
                        @endforeach
                    </ul>
                    <div class="card-footer text-secondary small py-2">
                        По запускам анализа (уникальные user_id за период).
                    </div>
                </div>
            </div>
        </div>
    </div>
@endcomponent
