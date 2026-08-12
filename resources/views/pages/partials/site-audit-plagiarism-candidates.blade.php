{{-- Список URL-кандидатов для внешнего антиплагиата (SSR или AJAX) --}}
@php
    $plagiarismCandidatesTotal = (int) ($plagiarismCandidatesTotal ?? count($plagiarismCandidates));
    $plagiarismCandidatesTruncated = !empty($plagiarismCandidatesTruncated);
@endphp
<div class="d-flex flex-wrap align-items-center mb-2" style="gap:8px">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="sa-plag-landings">Только посадочные</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="sa-plag-clear">Снять выбор</button>
    <span class="small text-muted" id="sa-plag-selected">Выбрано: 0 / {{ $plagiarismMaxUrls }}</span>
    <input type="search" class="form-control form-control-sm" id="sa-plag-filter" placeholder="Фильтр по URL или title…" style="max-width:260px">
    <button type="button" class="btn btn-sm btn-primary ms-auto" id="sa-plag-run" {{ !empty($running) ? 'disabled' : '' }}>
        {{ !empty($running) ? 'Проверка…' : 'Проверить выбранные' }}
    </button>
</div>
<div class="small text-muted mb-2" id="sa-plag-list-note">
    В списке: <strong>{{ number_format(count($plagiarismCandidates), 0, '', ' ') }}</strong> стр. этой проверки
    @if($plagiarismCandidatesTruncated && $plagiarismCandidatesTotal > count($plagiarismCandidates))
        (из {{ number_format($plagiarismCandidatesTotal, 0, '', ' ') }} — показаны первые по объёму текста + посадочные)
    @endif
</div>

<div class="cabinet-sa-table-wrap mb-3" style="max-height:420px;overflow:auto">
    <table class="table table-sm mb-0" id="sa-plag-table">
        <thead class="thead-light">
        <tr>
            <th style="width:36px"></th>
            <th>URL</th>
            <th>Title</th>
            <th>Слов</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @foreach($plagiarismCandidates as $c)
            @php
                $rowQ = mb_strtolower(($c['url'] ?? '') . ' ' . ($c['title'] ?? ''));
            @endphp
            <tr data-sa-plag-row data-q="{{ e($rowQ) }}">
                <td>
                    <input type="checkbox" class="sa-plag-cb" value="{{ $c['url'] }}"
                           data-landing="{{ !empty($c['is_landing']) ? '1' : '0' }}"
                        {{ !empty($running) ? 'disabled' : '' }}>
                </td>
                <td class="small"><a href="{{ $c['url'] }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($c['url'], 70) }}</a></td>
                <td class="small text-muted">{{ \Illuminate\Support\Str::limit($c['title'] ?? '—', 50) }}</td>
                <td>{{ number_format((int) ($c['word_count'] ?? 0), 0, '', ' ') }}</td>
                <td>@if(!empty($c['is_landing']))<span class="badge bg-info text-dark">посадочная</span>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
