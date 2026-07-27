{{-- Список URL-кандидатов для внешнего антиплагиата (SSR или AJAX) --}}
<div class="d-flex flex-wrap align-items-center mb-2" style="gap:8px">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="sa-plag-landings">Только посадочные</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="sa-plag-clear">Снять выбор</button>
    <span class="small text-muted" id="sa-plag-selected">Выбрано: 0 / {{ $plagiarismMaxUrls }}</span>
    <button type="button" class="btn btn-sm btn-primary ms-auto" id="sa-plag-run" {{ !empty($running) ? 'disabled' : '' }}>
        {{ !empty($running) ? 'Проверка…' : 'Проверить выбранные' }}
    </button>
</div>

<div class="cabinet-sa-table-wrap mb-3" style="max-height:320px;overflow:auto">
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
            <tr>
                <td>
                    <input type="checkbox" class="sa-plag-cb" value="{{ $c['url'] }}"
                           data-landing="{{ !empty($c['is_landing']) ? '1' : '0' }}"
                        {{ !empty($running) ? 'disabled' : '' }}>
                </td>
                <td class="small"><a href="{{ $c['url'] }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($c['url'], 70) }}</a></td>
                <td class="small text-muted">{{ \Illuminate\Support\Str::limit($c['title'] ?? '—', 50) }}</td>
                <td>{{ (int) ($c['word_count'] ?? 0) }}</td>
                <td>@if(!empty($c['is_landing']))<span class="badge bg-info text-dark">посадочная</span>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
