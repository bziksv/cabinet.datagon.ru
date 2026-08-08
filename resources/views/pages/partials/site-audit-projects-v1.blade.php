<section class="card border shadow-sm cabinet-sa-panel h-100" id="sa-projects" data-sa-tour="projects">
                    <div class="card-header py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h2 class="h6 mb-0 fw-semibold">Ваши сайты</h2>
                        <div class="small text-secondary" data-sa-pro>
                            @if(isset($projectsLimit))
                                проектов {{ $projects->count() }} / {{ (int) $projectsLimit }}
                            @endif
                            @if(isset($schedulesLimit))
                                · автоснятий {{ (int) ($schedulesUsed ?? 0) }} / {{ (int) $schedulesLimit }}
                            @endif
                        </div>
                    </div>
                    @forelse($projects as $project)
                        @php
                            $last = $project->crawls->first();
                            $sch = ($schedules ?? collect())->get($project->id);
                            $schSettings = ($sch && is_array($sch->settings_json)) ? $sch->settings_json : [];
                            $schWeekday = (int) ($schSettings['weekday'] ?? now()->dayOfWeekIso);
                            $schHour = (int) ($schSettings['hour'] ?? 4);
                            $schSpeed = (string) ($schSettings['crawl_speed'] ?? 'normal');
                            $schConc = max(1, (int) ($schSettings['concurrency'] ?? 1));
                            $schPages = (int) ($schSettings['pages_limit'] ?? ($pagesLimit ?? 100));
                            $maxConc = max(1, (int) ($concurrencyLimit ?? 1));
                            $maxPages = max(1, (int) ($pagesLimit ?? 100));
                        @endphp
                        @php
                            $isProjectOwner = auth()->id() && (int) $project->user_id === (int) auth()->id();
                        @endphp
                        <div class="cabinet-sa-project">
                            <div class="cabinet-sa-project__main">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-body text-truncate">{{ $project->domain }}</div>
                                    <div class="small text-secondary">
                                        @if($last)
                                            последний краул
                                            <a href="{{ route('pages.site-audit.crawl.show', $last->id) }}">#{{ $last->id }}</a>
                                            · {{ $last->statusLabelRu() }}
                                            · {{ $last->pages_fetched }}/{{ $last->pages_total }}
                                        @else
                                            ещё не запускался
                                        @endif
                                        @if(!empty($project->team))
                                            · <span class="text-body">команда {{ $project->team->title }}</span>
                                        @elseif(!$isProjectOwner)
                                            · <span class="text-body">доступ по команде</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex flex-shrink-0 gap-1">
                                        @if($last)
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('pages.site-audit.crawl.show', $last->id) }}">Открыть</a>
                                            @if(($project->crawls_count ?? 0) > 1)
                                                <a class="btn btn-sm btn-outline-secondary"
                                                   href="{{ route('pages.site-audit.crawl.show', $last->id) }}#sa-archive">Архив</a>
                                            @endif
                                        @endif
                                    @if($isProjectOwner)
                                    <form method="POST" action="{{ route('pages.site-audit.project.destroy', $project->id) }}" class="d-inline"
                                          data-cabinet-confirm="Удалить проект {{ e($project->domain) }} и все краулы?"
                                          data-cabinet-confirm-title="Удаление проекта"
                                          data-cabinet-confirm-ok="Удалить"
                                          data-cabinet-confirm-danger="1">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger cabinet-sa-project-del" title="Удалить">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                            <span class="cabinet-sa-project-del__text">Удалить</span>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </div>
                            @if($isProjectOwner && !empty($teamAccessReady))
                                <form method="POST" action="{{ route('pages.site-audit.project.team', $project->id) }}" class="cabinet-sa-project__team">
                                    @csrf
                                    <div class="d-flex flex-wrap align-items-end gap-2">
                                        <div class="flex-grow-1" style="min-width:10rem">
                                            <label class="form-label small mb-1" for="sa-team-{{ $project->id }}">Команда из чеклиста</label>
                                            <select name="team_id" id="sa-team-{{ $project->id }}" class="form-select form-select-sm">
                                                <option value="0">Без команды</option>
                                                @foreach(($checklistTeams ?? []) as $team)
                                                    <option value="{{ $team->id }}" @if((int) ($project->team_id ?? 0) === (int) $team->id) selected @endif>
                                                        {{ $team->title }}
                                                        @if(isset($team->members_count)) · {{ (int) $team->members_count }} чел.@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Сохранить</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#sa-team-create-modal">
                                            Создать…
                                        </button>
                                        <a href="{{ route('profile.index') }}#team" class="btn btn-sm btn-link px-1">Управление</a>
                                    </div>
                                    <div class="form-text">Участники команды увидят отчёты этого сайта в Аудите.</div>
                                </form>
                            @elseif($isProjectOwner && empty($teamAccessReady))
                                <div class="cabinet-sa-project__team small text-secondary">
                                    Команды чеклиста пока недоступны.
                                </div>
                            @endif
                            @if($isProjectOwner && !empty($canSchedule))
                                <form method="POST" action="{{ route('pages.site-audit.schedule', $project->id) }}" class="cabinet-sa-project__schedule" data-sa-tour="schedule" data-sa-pro>
                                    @csrf
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <div class="cabinet-sa-check-row mb-0">
                                            <div class="form-check mb-0">
                                                <input type="checkbox" class="form-check-input" id="sa-sch-{{ $project->id }}"
                                                       name="enabled" value="1" {{ $sch && $sch->enabled ? 'checked' : '' }}>
                                                <label class="form-check-label fw-medium" for="sa-sch-{{ $project->id }}">Авторасписание</label>
                                            </div>
                                            @include('pages.partials.site-audit-tip', ['tip' => "Автозапуск аудита в выбранный день и час (МСК).\nЛимит слотов: Free 0 / Optimal 2 / Ultimate 5 / Maximum 10.\nЧасы 11–14 недоступны (пик).\nСписывает краул из месячного лимита."])
                                        </div>
                                        @if($sch && $sch->enabled && $sch->next_run_at)
                                            <span class="badge text-bg-primary">
                                                след. {{ $sch->next_run_at->format('d.m.Y H:i') }} МСК
                                            </span>
                                        @else
                                            <span class="small text-secondary">выкл. — настройки скрыты</span>
                                        @endif
                                    </div>
                                    <div class="cabinet-sa-project__schedule-body">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1" for="sa-sch-freq-{{ $project->id }}">Как часто</label>
                                            <select name="frequency" id="sa-sch-freq-{{ $project->id }}" class="form-select form-select-sm">
                                                @foreach(($scheduleFrequencies ?? []) as $freqCode => $freqLabel)
                                                    <option value="{{ $freqCode }}"
                                                        {{ ($sch ? \App\SiteAuditSchedule::normalizeFrequency($sch->frequency) : 'weekly') === $freqCode ? 'selected' : '' }}>
                                                        {{ $freqLabel }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1" for="sa-sch-wd-{{ $project->id }}">День недели</label>
                                            <select name="weekday" id="sa-sch-wd-{{ $project->id }}" class="form-select form-select-sm">
                                                @foreach(($scheduleWeekdays ?? []) as $wd => $wdLabel)
                                                    <option value="{{ $wd }}" {{ (int) $schWeekday === (int) $wd ? 'selected' : '' }}>{{ $wdLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1" for="sa-sch-hour-{{ $project->id }}">Час (МСК)</label>
                                            <select name="hour" id="sa-sch-hour-{{ $project->id }}" class="form-select form-select-sm">
                                                @php
                                                    $peakHours = [11, 12, 13, 14];
                                                    if (in_array($schHour, $peakHours, true)) {
                                                        $schHour = 4;
                                                    }
                                                @endphp
                                                @for($h = 0; $h <= 23; $h++)
                                                    @if(in_array($h, $peakHours, true))
                                                        <option value="{{ $h }}" disabled>{{ sprintf('%02d:00', $h) }} — пик</option>
                                                    @else
                                                        <option value="{{ $h }}" {{ $schHour === $h ? 'selected' : '' }}>
                                                            {{ sprintf('%02d:00', $h) }}
                                                        </option>
                                                    @endif
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label small mb-1" for="sa-sch-speed-{{ $project->id }}">Скорость на поток</label>
                                            <select name="crawl_speed" id="sa-sch-speed-{{ $project->id }}" class="form-select form-select-sm">
                                                <option value="slow" {{ $schSpeed === 'slow' ? 'selected' : '' }}>Медленно (~1 URL/с на поток)</option>
                                                <option value="normal" {{ $schSpeed === 'normal' ? 'selected' : '' }}>Обычная (~5 URL/с на поток)</option>
                                                <option value="fast" {{ $schSpeed === 'fast' ? 'selected' : '' }}>Быстрая (~10 URL/с на поток)</option>
                                                <option value="turbo" {{ $schSpeed === 'turbo' ? 'selected' : '' }}>Турбо (~15 URL/с на поток) — только свои сайты</option>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1" for="sa-sch-conc-{{ $project->id }}">Потоки</label>
                                            <select name="concurrency" id="sa-sch-conc-{{ $project->id }}" class="form-select form-select-sm">
                                                @for($n = 1; $n <= $maxConc; $n++)
                                                    <option value="{{ $n }}" {{ $schConc === $n ? 'selected' : '' }}>{{ $n }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1" for="sa-sch-limit-{{ $project->id }}">Лимит URL</label>
                                            <input type="text" class="form-control form-control-sm sa-num-space" name="pages_limit"
                                                   id="sa-sch-limit-{{ $project->id }}"
                                                   inputmode="numeric" autocomplete="off"
                                                   value="{{ number_format((int) min($schPages, $maxPages), 0, '', ' ') }}"
                                                   data-min="1" data-max="{{ $maxPages }}">
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <button type="submit" class="btn btn-sm btn-primary w-100">Сохранить</button>
                                        </div>
                                    </div>
                                    <div class="small cabinet-sa-project__schedule-note mt-2">
                                        Запуск в выбранный день и час (МСК). 11:00–14:00 недоступны (пик). После сохранения — точная дата «след. …».
                                    </div>
                                    </div>
                                </form>
                            @elseif($isProjectOwner)
                                <div class="cabinet-sa-project__schedule small text-secondary" data-sa-pro>
                                    Авторасписание недоступно на бесплатном тарифе (0 слотов). Optimal — 2, Ultimate — 5, Maximum — 10.
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="card-body">
                            <div class="alert alert-light border text-center py-4 mb-0 text-secondary">
                                Проектов пока нет — запустите первый краул.
                            </div>
                        </div>
                    @endforelse
                </section>
