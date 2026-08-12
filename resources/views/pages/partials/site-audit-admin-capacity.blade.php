@php
    /** @var array $capacity */
    /** @var array $fields */
    $eff = $capacity['effective'] ?? [];
    $defaults = $capacity['defaults'] ?? [];
    $activeList = $capacity['active_list'] ?? [];
    $waitingList = $capacity['waiting_list'] ?? [];
@endphp

<div class="card shadow-sm border-0 mb-3 cabinet-sa-admin-capacity">
    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h3 class="card-title h6 mb-0">Слоты и очередь</h3>
        <div class="d-flex flex-wrap gap-2">
            <form action="{{ route('pages.site-audit.admin.promote') }}" method="post" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    Поднять очередь
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="small text-secondary">Занято слотов</div>
                <div class="fs-5 fw-semibold">
                    {{ number_format((int) ($capacity['active'] ?? 0), 0, '', ' ') }}
                    <span class="text-secondary fw-normal fs-6">/
                        {{ number_format((int) ($eff['global_max_active_crawls'] ?? 0), 0, '', ' ') }}</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small text-secondary">Свободно</div>
                <div class="fs-5 fw-semibold">{{ number_format((int) ($capacity['free_slots'] ?? 0), 0, '', ' ') }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small text-secondary">Ждёт слот</div>
                <div class="fs-5 fw-semibold @if(($capacity['waiting'] ?? 0) > 0) text-warning @endif">
                    {{ number_format((int) ($capacity['waiting'] ?? 0), 0, '', ' ') }}
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small text-secondary">Воркеры (ориентир)</div>
                <div class="fs-5 fw-semibold">{{ number_format((int) ($capacity['workers_hint'] ?? 0), 0, '', ' ') }}</div>
                <div class="small text-secondary">numprocs site_audit</div>
            </div>
        </div>

        <p class="small text-secondary mb-3">
            Источник лимитов:
            <strong>{{ ($capacity['source'] ?? 'config') === 'admin' ? 'админка' : 'config / .env' }}</strong>
            @if(!empty($capacity['updated_at']))
                · сохранено {{ $capacity['updated_at'] }}
            @endif
            · глобальный слот ≈ числу воркеров; на пользователя — отдельно (иначе PSI одной проверки блокирует следующую).
        </p>

        <form action="{{ route('pages.site-audit.admin.settings') }}" method="post" class="row g-3">
            @csrf
            @foreach($fields as $key => $meta)
                <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="sa-cap-{{ $key }}">{{ $meta['label'] }}</label>
                    <input type="number"
                           class="form-control form-control-sm @error($key) is-invalid @enderror"
                           name="{{ $key }}"
                           id="sa-cap-{{ $key }}"
                           min="{{ (int) $meta['min'] }}"
                           max="{{ (int) $meta['max'] }}"
                           value="{{ old($key, $eff[$key] ?? $meta['min']) }}"
                           required>
                    <div class="form-text">
                        {{ $meta['help'] }}
                        @if(isset($defaults[$key]) && (int) ($eff[$key] ?? 0) !== (int) $defaults[$key])
                            <span class="text-muted">(config: {{ number_format((int) $defaults[$key], 0, '', ' ') }})</span>
                        @endif
                    </div>
                    @error($key)
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            @endforeach
            <div class="col-12">
                <button type="submit" class="btn btn-sm btn-primary">Сохранить лимиты</button>
            </div>
        </form>

        @if($activeList !== [] || $waitingList !== [])
            <hr class="my-3">
            <div class="row g-3">
                @if($activeList !== [])
                    <div class="col-lg-6">
                        <h4 class="h6">Активные</h4>
                        <ul class="list-unstyled small mb-0">
                            @foreach($activeList as $row)
                                <li class="mb-1">
                                    <a href="{{ $row['url'] }}">#{{ $row['id'] }}</a>
                                    · {{ $row['domain'] }}
                                    · u{{ $row['user_id'] }}
                                    · {{ $row['status_label'] }}
                                    · {{ $row['progress'] }}
                                    @if(!empty($row['updated_at']))
                                        · {{ $row['updated_at'] }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if($waitingList !== [])
                    <div class="col-lg-6">
                        <h4 class="h6">Ожидают слот</h4>
                        <ul class="list-unstyled small mb-0">
                            @foreach($waitingList as $row)
                                <li class="mb-1">
                                    <a href="{{ $row['url'] }}">#{{ $row['id'] }}</a>
                                    · {{ $row['domain'] }}
                                    · u{{ $row['user_id'] }}
                                    @if(!empty($row['block']))
                                        · ждёт: {{ $row['block'] }}
                                    @endif
                                    @if(!empty($row['created_at']))
                                        · с {{ $row['created_at'] }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
